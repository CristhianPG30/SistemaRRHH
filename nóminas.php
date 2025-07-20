<?php
session_start();
// Restringe el acceso a roles autorizados (Admin y Planilla)
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

// --- INICIALIZACIÓN Y LÓGICA DE ACCIONES ---
$mensaje = '';
$meses_espanol = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

// Determina el mes y año para la previsualización
$anio_seleccionado = isset($_GET['anio_previsualizar']) ? intval($_GET['anio_previsualizar']) : intval(date('Y'));
$mes_seleccionado = isset($_GET['mes_previsualizar']) ? intval($_GET['mes_previsualizar']) : intval(date('n'));

// Lógica para eliminar una planilla generada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_planilla'])) {
    $anio_eliminar = intval($_POST['anio_eliminar']);
    $mes_eliminar = intval($_POST['mes_eliminar']);
    $fecha_eliminar_str = sprintf("%04d-%02d-01", $anio_eliminar, $mes_eliminar);
    $fecha_generacion_eliminar = date('Y-m-d', strtotime($fecha_eliminar_str));

    $conn->begin_transaction();
    try {
        // Eliminar detalles de deducciones asociados
        $stmt_deducciones = $conn->prepare("DELETE FROM deducciones_detalle WHERE fecha_generacion_planilla = ?");
        $stmt_deducciones->bind_param("s", $fecha_generacion_eliminar);
        $stmt_deducciones->execute();
        $stmt_deducciones->close();

        // Eliminar la planilla principal
        $stmt_planilla = $conn->prepare("DELETE FROM planillas WHERE fecha_generacion = ?");
        $stmt_planilla->bind_param("s", $fecha_generacion_eliminar);
        $stmt_planilla->execute();
        $stmt_planilla->close();

        $conn->commit();
        $mensaje = '<div class="alert alert-success alert-dismissible fade show">La planilla de ' . htmlspecialchars($meses_espanol[$mes_eliminar]) . ' de ' . htmlspecialchars($anio_eliminar) . ' ha sido eliminada.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $mensaje = '<div class="alert alert-danger alert-dismissible fade show">Error al eliminar la planilla: ' . $e->getMessage() . '</div>';
    }
}

// --- FUNCIONES DE CÁLCULO ---

function obtenerDiasFeriados($anio)
{
    $feriadosFilePath = 'js/feriados.json';
    if (!file_exists($feriadosFilePath)) return [];
    $feriados_data = json_decode(file_get_contents($feriadosFilePath), true);
    return is_array($feriados_data) ? array_column($feriados_data, 'fecha') : [];
}

function calcularDiasLaborales($anio, $mes)
{
    $dias_laborales = [];
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $feriados = obtenerDiasFeriados($anio);
    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
        if (date('N', strtotime($fecha)) < 6 && !in_array($fecha, $feriados)) {
            $dias_laborales[] = $fecha;
        }
    }
    return $dias_laborales;
}

function planillaYaGenerada($anio, $mes, $conn)
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM planillas WHERE YEAR(fecha_generacion) = ? AND MONTH(fecha_generacion) = ?");
    $stmt->bind_param("ii", $anio, $mes);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0] > 0;
}

function calcularDeduccionesDeLey($salario_bruto, $conn)
{
    $deducciones = ['total' => 0, 'monto_ccss' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            $nombre_limpio = trim($nombre);
            $porcentaje_float = floatval(trim($porcentaje));
            
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * ($porcentaje_float / 100);
                $deducciones['total'] += $monto;
                
                if (stripos($nombre_limpio, 'CCSS') !== false) {
                    $deducciones['monto_ccss'] = $monto;
                }

                $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => $nombre_limpio, 'monto' => $monto, 'porcentaje' => $porcentaje_float];
            }
        }
    }
    return $deducciones;
}

/**
 * [CORRECCIÓN DEFINITIVA] Calcula el impuesto sobre la renta usando un método progresivo estándar.
 */
function calcularImpuestoRenta($salario_imponible, $cantidad_hijos, $es_casado)
{
    // 1. Cargar configuración desde JSON.
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    $default_return = ['total' => 0, 'bruto' => 0, 'credito_hijos' => 0, 'credito_conyuge' => 0, 'tramo_aplicado' => 'Exento', 'desglose_bruto' => []];
    
    if (!file_exists($tax_config_path)) { return $default_return; }
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    $current_year = date('Y');
    $tramos_key = 'tramos_salariales_' . $current_year;
    $creditos_key = 'creditos_fiscales_' . $current_year;
    
    $tramos_renta = $config_data[$tramos_key] ?? [];
    $creditos_fiscales = $config_data[$creditos_key] ?? ['hijo' => 0, 'conyuge' => 0];

    // 2. Calcular impuesto bruto progresivo.
    $impuesto_bruto = 0;
    $tramo_aplicado_final = 'Exento';
    $desglose_bruto = [];
    
    // El impuesto se calcula sobre el exceso de cada tramo.
    foreach ($tramos_renta as $tramo) {
        if ($salario_imponible > $tramo['min']) {
            $base_en_tramo = 0;
            if ($tramo['max'] === null) { 
                $base_en_tramo = $salario_imponible - $tramo['min'];
            } else {
                $base_en_tramo = min($salario_imponible, $tramo['max']) - $tramo['min'];
            }

            if ($base_en_tramo > 0 && $tramo['tasa'] > 0) {
                $impuesto_del_tramo = $base_en_tramo * ($tramo['tasa'] / 100);
                $impuesto_sobre_exceso = $tramo['impuesto_sobre_exceso_de'] ?? 0;
                $impuesto_bruto = $impuesto_del_tramo + $impuesto_sobre_exceso;
                
                $desglose_bruto[] = [
                    'descripcion' => 'Sobre exceso de ₡' . number_format($tramo['min'], 0) . ' al ' . $tramo['tasa'] . '%',
                    'monto' => $impuesto_del_tramo
                ];
            }
        }
    }

    // Determina el tramo final
    foreach(array_reverse($tramos_renta) as $tramo) {
        if ($salario_imponible > $tramo['min']) {
            $tramo_aplicado_final = $tramo['tasa'] > 0 ? $tramo['tasa'] . '%' : 'Exento';
            break;
        }
    }

    // 3. Calcular créditos fiscales.
    $credito_hijos = $cantidad_hijos * ($creditos_fiscales['hijo'] ?? 0);
    $credito_conyuge = $es_casado ? ($creditos_fiscales['conyuge'] ?? 0) : 0;
    
    // 4. Calcular impuesto neto.
    $impuesto_final = $impuesto_bruto - $credito_hijos - $credito_conyuge;
    
    return [
        'total' => max(0, $impuesto_final), 
        'bruto' => $impuesto_bruto,
        'credito_hijos' => $credito_hijos,
        'credito_conyuge' => $credito_conyuge,
        'tramo_aplicado' => $tramo_aplicado_final,
        'desglose_bruto' => $desglose_bruto
    ];
}


function obtenerCategoriasSalariales($conn)
{
    $result = $conn->query("SELECT idCategoria_salarial, Cantidad_Salarial_Tope FROM categoria_salarial ORDER BY Cantidad_Salarial_Tope ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function determinarCategoriaSalarial($salario_bruto, $categorias)
{
    foreach ($categorias as $categoria) {
        if ($salario_bruto <= $categoria['Cantidad_Salarial_Tope']) return $categoria['idCategoria_salarial'];
    }
    return !empty($categorias) ? end($categorias)['idCategoria_salarial'] : null;
}

function obtenerPlanilla($anio, $mes, $conn)
{
    $categorias_salariales = obtenerCategoriasSalariales($conn);
    $dias_laborales_del_mes = calcularDiasLaborales($anio, $mes);
    
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, p.cantidad_hijos, p.id_estado_civil_fk, c.idColaborador, c.salario_bruto as salario_base 
                          FROM colaborador c 
                          JOIN persona p ON c.id_persona_fk = p.idPersona 
                          WHERE c.activo = 1 AND c.idColaborador NOT IN (SELECT id_colaborador_fk FROM liquidaciones)";
    
    $result_colaboradores = $conn->query($sql_colaboradores);
    if (!$result_colaboradores) return [];
    
    $planilla = [];
    $fecha_actual_str = date('Y-m-d');
    
    $dias_laborales_transcurridos = [];
    if ($anio < date('Y') || ($anio == date('Y') && $mes < date('n'))) {
        $dias_laborales_transcurridos = $dias_laborales_del_mes;
    } else {
        foreach ($dias_laborales_del_mes as $dia) {
            if ($dia <= $fecha_actual_str) $dias_laborales_transcurridos[] = $dia;
        }
    }

    $permisos_con_goce = ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad', 'médico'];

    while ($colaborador = $result_colaboradores->fetch_assoc()) {
        $idColaborador = $colaborador['idColaborador'];
        $salario_base = floatval($colaborador['salario_base']);
        $salario_diario = $salario_base / 30;

        $asistencias_del_mes = [];
        $stmt_asist = $conn->prepare("SELECT DISTINCT DATE(Fecha) as Fecha FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asist->bind_param("iii", $colaborador['idPersona'], $mes, $anio);
        $stmt_asist->execute();
        $res_asist = $stmt_asist->get_result();
        while($row = $res_asist->fetch_assoc()) { $asistencias_del_mes[] = $row['Fecha']; }
        $stmt_asist->close();

        $dias_permiso_con_goce = [];
        $dias_permiso_sin_goce = [];
        $desglose_permisos = [];
        
        $sql_permisos = "SELECT p.fecha_inicio, p.fecha_fin, tpc.Descripcion AS tipo_permiso FROM permisos p JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso WHERE p.id_colaborador_fk = ? AND p.id_estado_fk = 4";
        $stmt_perm = $conn->prepare($sql_permisos);
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        while($row_perm = $res_perm->fetch_assoc()) {
            $inicio = new DateTime($row_perm['fecha_inicio']);
            $fin = new DateTime($row_perm['fecha_fin']);
            $fin->modify('+1 day');
            $rango = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
            
            foreach($rango as $fecha) {
                $fecha_str = $fecha->format('Y-m-d');
                if ($fecha->format('n') == $mes && date('N', strtotime($fecha_str)) < 6) {
                    $tipo = $row_perm['tipo_permiso'];
                    $desglose_permisos[$tipo] = ($desglose_permisos[$tipo] ?? 0) + 1;
                    if (in_array(strtolower($tipo), $permisos_con_goce)) {
                        $dias_permiso_con_goce[] = $fecha_str;
                    } else {
                        $dias_permiso_sin_goce[] = $fecha_str;
                    }
                }
            }
        }
        $stmt_perm->close();

        $dias_cubiertos = array_unique(array_merge($asistencias_del_mes, $dias_permiso_con_goce, $dias_permiso_sin_goce));
        $dias_ausencia_injustificada = count(array_diff($dias_laborales_transcurridos, $dias_cubiertos));
        
        $desglose_ajustes_negativos = [];
        if ($dias_ausencia_injustificada > 0) {
            $desglose_ajustes_negativos[] = ['descripcion' => 'Ausencias Injustificadas (' . $dias_ausencia_injustificada . ' días)', 'monto' => $dias_ausencia_injustificada * $salario_diario];
        }
        foreach($desglose_permisos as $tipo => $dias) {
            if (!in_array(strtolower($tipo), $permisos_con_goce) && $dias > 0) {
                $desglose_ajustes_negativos[] = ['descripcion' => 'Permiso s/g: ' . $tipo . ' (' . $dias . ' días)', 'monto' => $dias * $salario_diario];
            }
        }
        
        $total_deducciones_previas = array_sum(array_column($desglose_ajustes_negativos, 'monto'));
        $pago_ordinario = $salario_base - $total_deducciones_previas;

        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio); $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0); $stmt_he->close();
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5);
        
        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;
        $id_categoria = determinarCategoriaSalarial($salario_bruto_calculado, $categorias_salariales);
        
        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $salario_imponible_renta = $salario_bruto_calculado - $deducciones_ley['monto_ccss'];
        
        $es_casado = ($colaborador['id_estado_civil_fk'] == 2);
        $calculo_renta = calcularImpuestoRenta($salario_imponible_renta, $colaborador['cantidad_hijos'], $es_casado);
        $impuesto_renta_neto = $calculo_renta['total'];
        
        $total_deducciones_finales = $deducciones_ley['total'] + $impuesto_renta_neto;
        $salario_neto = $salario_bruto_calculado - $total_deducciones_finales;
        
        $planilla[] = array_merge($colaborador, [
            'id_categoria_salarial_fk' => $id_categoria,
            'salario_bruto_calculado' => $salario_bruto_calculado,
            'total_horas_extra' => $total_horas_extra,
            'pago_horas_extra' => $pago_horas_extra,
            'desglose_permisos_pagados' => $desglose_permisos,
            'desglose_ajustes_negativos' => $desglose_ajustes_negativos,
            'salario_imponible' => $salario_imponible_renta,
            'deducciones_legales_detalles' => $deducciones_ley['detalles'],
            'desglose_renta' => $calculo_renta,
            'total_deducciones' => $total_deducciones_finales,
            'salario_neto' => $salario_neto
        ]);
    }
    return $planilla;
}

function guardarPlanilla($planilla_data, $anio, $mes, $conn)
{
    $fecha_generacion = sprintf("%04d-%02d-01", $anio, $mes);
    $conn->begin_transaction();
    try {
        $stmt_del_d = $conn->prepare("DELETE FROM deducciones_detalle WHERE fecha_generacion_planilla = ?");
        $stmt_del_d->bind_param("s", $fecha_generacion);
        $stmt_del_d->execute();
        $stmt_del_d->close();
        
        $stmt_del_p = $conn->prepare("DELETE FROM planillas WHERE fecha_generacion = ?");
        $stmt_del_p->bind_param("s", $fecha_generacion);
        $stmt_del_p->execute();
        $stmt_del_p->close();

        $stmt_planilla = $conn->prepare("INSERT INTO planillas (id_colaborador_fk, fecha_generacion, id_categoria_salarial_fk, salario_bruto, total_deducciones, salario_neto) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_deduccion = $conn->prepare("INSERT INTO deducciones_detalle (id_colaborador_fk, fecha_generacion_planilla, id_tipo_deduccion_fk, monto) VALUES (?, ?, ?, ?)");
        
        foreach ($planilla_data as $col) {
            // LÍNEA CORREGIDA
            $stmt_planilla->bind_param("isiddd", $col['idColaborador'], $fecha_generacion, $col['id_categoria_salarial_fk'], $col['salario_bruto_calculado'], $col['total_deducciones'], $col['salario_neto']);
            $stmt_planilla->execute();
            
            foreach ($col['deducciones_legales_detalles'] as $deduccion) {
                if ($deduccion['monto'] > 0) {
                    $stmt_deduccion->bind_param("isid", $col['idColaborador'], $fecha_generacion, $deduccion['id'], $deduccion['monto']);
                    $stmt_deduccion->execute();
                }
            }
        }
        $conn->commit();
        return true;
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log("Error al guardar planilla: " . $e->getMessage());
        return false;
    }
}

// Obtiene la data para la previsualización
$planilla_previsualizada = obtenerPlanilla($anio_seleccionado, $mes_seleccionado, $conn);
$ya_fue_generada = planillaYaGenerada($anio_seleccionado, $mes_seleccionado, $conn);

$puede_generar = ($anio_seleccionado == date('Y') && $mes_seleccionado == date('n'));
$mensaje_generar = !$puede_generar ? "Solo se puede generar la planilla del mes actual." : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_planilla'])) {
    if ($puede_generar) {
        if (guardarPlanilla($planilla_previsualizada, $anio_seleccionado, $mes_seleccionado, $conn)) {
            $mensaje = '<div class="alert alert-success">Planilla ' . ($ya_fue_generada ? 'regenerada y actualizada' : 'generada y guardada') . ' exitosamente.</div>';
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            $mensaje = '<div class="alert alert-danger">Error al guardar la planilla en la base de datos.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-danger">No se pudo generar esta planilla. ' . $mensaje_generar . '</div>';
    }
}

$historial_anio = isset($_GET['historial_anio']) ? intval($_GET['historial_anio']) : '';
$historial_mes = isset($_GET['historial_mes']) ? intval($_GET['historial_mes']) : '';
$sql_historial = "SELECT DISTINCT YEAR(fecha_generacion) as anio, MONTH(fecha_generacion) as mes FROM planillas WHERE 1=1";
$params_historial = [];
$types_historial = '';
if ($historial_anio) {
    $sql_historial .= " AND YEAR(fecha_generacion) = ?";
    array_push($params_historial, $historial_anio);
    $types_historial .= 'i';
}
if ($historial_mes) {
    $sql_historial .= " AND MONTH(fecha_generacion) = ?";
    array_push($params_historial, $historial_mes);
    $types_historial .= 'i';
}
$sql_historial .= " ORDER BY anio DESC, mes DESC";
$stmt_historial = $conn->prepare($sql_historial);
if (!empty($params_historial)) {
    $stmt_historial->bind_param($types_historial, ...$params_historial);
}
$stmt_historial->execute();
$historial_planillas = $stmt_historial->get_result()->fetch_all(MYSQLI_ASSOC);

function generar_datos_ejemplo($salario_base, $horas_extra, $num_hijos, $tiene_conyuge, $conn) {
    $pago_horas_extra = $horas_extra > 0 ? ($salario_base / 240) * 1.5 * $horas_extra : 0;
    $salario_bruto = $salario_base + $pago_horas_extra;
    
    $deducciones_ley = calcularDeduccionesDeLey($salario_bruto, $conn);
    $base_imponible_renta = $salario_bruto - $deducciones_ley['monto_ccss'];
    $renta = calcularImpuestoRenta($base_imponible_renta, $num_hijos, $tiene_conyuge);
    
    $total_deducciones = $deducciones_ley['total'] + $renta['total'];
    $salario_neto = $salario_bruto - $total_deducciones;
    
    return [
        'salario_base' => $salario_base,
        'pago_horas_extra' => $pago_horas_extra,
        'salario_bruto' => $salario_bruto, 
        'deducciones_ley' => $deducciones_ley,
        'base_imponible_renta' => $base_imponible_renta, 
        'renta' => $renta, 
        'total_deducciones' => $total_deducciones,
        'salario_neto' => $salario_neto,
        'horas_extra' => $horas_extra,
        'num_hijos' => $num_hijos,
        'tiene_conyuge' => $tiene_conyuge
    ];
}

// Genera los datos para ambos ejemplos con todo el desglose necesario.
$ejemplo_1_data = generar_datos_ejemplo(1500000, 0, 0, false, $conn);
$ejemplo_2_data = generar_datos_ejemplo(2500000, 10, 2, true, $conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Planilla - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .calculo-detalle { font-size: 0.85em; }
        .table-sm th, .table-sm td { padding: 0.2rem 0.4rem; }
        .table-borderless th, .table-borderless td { border: 0; }
        .fw-bold-dark { font-weight: 600; color: #333; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <main style="margin-left: 280px; padding: 2rem;">
        <h1 class="h3 mb-4">Gestión de Planilla</h1>
        <?php if ($mensaje) echo $mensaje; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary"><i class="bi bi-calendar-month me-2"></i>Cálculo y Generación de Planilla</h6>
                <form method="GET" class="d-flex gap-2">
                    <select name="mes_previsualizar" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach ($meses_espanol as $num => $nombre) echo "<option value='$num' " . ($num == $mes_seleccionado ? 'selected' : '') . ">$nombre</option>"; ?></select>
                    <input type="number" name="anio_previsualizar" class="form-control form-control-sm" value="<?= $anio_seleccionado ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body">
                <div class="alert alert-info"><strong>Previsualización para: <?= htmlspecialchars($meses_espanol[$mes_seleccionado]) ?> de <?= htmlspecialchars($anio_seleccionado) ?>.</strong></div>
                <div class="row">
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light"><tr><th>Colaborador</th><th>Salario Neto Estimado</th><th class="text-center">Detalle</th></tr></thead>
                                <tbody>
                                    <?php foreach ($planilla_previsualizada as $col): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($col['Nombre'] . ' ' . $col['Apellido1']) ?></td>
                                        <td class="text-success fw-bold">₡<?= number_format($col['salario_neto'], 2) ?></td>
                                        <td class="text-center"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detalleModal<?= $col['idColaborador'] ?>"><i class="bi bi-eye-fill"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmarGeneracionModal" data-ya-generada="<?= $ya_fue_generada ? 'true' : 'false' ?>" <?= !$puede_generar ? 'disabled title="' . $mensaje_generar . '"' : '' ?>>
                                <i class="bi bi-check-circle me-2"></i><?= $ya_fue_generada ? 'Regenerar Planilla' : 'Generar y Guardar Planilla' ?>
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card border mb-4">
                            <div class="card-header bg-light"><h6 class="m-0 fw-bold text-secondary"><i class="bi bi-calculator me-2"></i>Ejemplo 1: Sin Extras ni Créditos</h6></div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr><td class="w-75">Salario Base</td><td class="text-end">₡<?= number_format($ejemplo_1_data['salario_base'], 2) ?></td></tr>
                                    <tr class="border-bottom"><td class="pb-2">Horas Extra (<?= $ejemplo_1_data['horas_extra'] ?>h)</td><td class="text-end pb-2">+ ₡<?= number_format($ejemplo_1_data['pago_horas_extra'], 2) ?></td></tr>
                                    <tr class="table-light"><td class="fw-bold-dark">Salario Bruto</td><td class="text-end fw-bold-dark">₡<?= number_format($ejemplo_1_data['salario_bruto'], 2) ?></td></tr>
                                    <tr><td class="pt-3" colspan="2"><em>(-) Deducciones de Ley</em></td></tr>
                                    <?php foreach($ejemplo_1_data['deducciones_ley']['detalles'] as $ded): ?>
                                    <tr><td class="text-danger ps-3">&ndash; <?= htmlspecialchars($ded['descripcion']) ?> (<?= $ded['porcentaje'] ?>%)</td><td class="text-end text-danger">- ₡<?= number_format($ded['monto'], 2) ?></td></tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light"><td class="fw-bold-dark">Base Imponible (p/ Renta)</td><td class="text-end fw-bold-dark">₡<?= number_format($ejemplo_1_data['base_imponible_renta'], 2) ?></td></tr>
                                    <tr><td class="pt-3" colspan="2"><em>(-) Impuesto sobre la Renta</em></td></tr>
                                    <?php if(empty($ejemplo_1_data['renta']['desglose_bruto'])): ?>
                                    <tr class="calculo-detalle"><td class="ps-4">Exento de impuesto</td><td class="text-end">- ₡0.00</td></tr>
                                    <?php else: foreach($ejemplo_1_data['renta']['desglose_bruto'] as $tramo_renta): ?>
                                    <tr class="calculo-detalle"><td class="ps-4"><?= $tramo_renta['descripcion'] ?></td><td class="text-end">- ₡<?= number_format($tramo_renta['monto'], 2) ?></td></tr>
                                    <?php endforeach; endif; ?>
                                    <tr class="calculo-detalle border-bottom"><td class="ps-4 fw-bold">Impuesto Bruto</td><td class="text-end fw-bold">- ₡<?= number_format($ejemplo_1_data['renta']['bruto'], 2) ?></td></tr>
                                    <tr class="calculo-detalle"><td class="ps-4 text-success">Créditos Fiscales (Cónyuge/Hijos)</td><td class="text-end text-success">+ ₡0.00</td></tr>
                                    <tr class="border-bottom"><td class="text-danger ps-3 fw-bold">Total Impuesto Renta</td><td class="text-end text-danger">- ₡<?= number_format($ejemplo_1_data['renta']['total'], 2) ?></td></tr>
                                    <tr class="table-light"><td class="fw-bolder pt-2 fs-6">Salario Neto a Pagar</td><td class="text-end fw-bolder pt-2 text-success fs-6">₡<?= number_format($ejemplo_1_data['salario_neto'], 2) ?></td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="card border">
                            <div class="card-header bg-light"><h6 class="m-0 fw-bold text-secondary"><i class="bi bi-calculator-fill me-2"></i>Ejemplo 2: Con Extras y Créditos</h6></div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr><td class="w-75">Salario Base</td><td class="text-end">₡<?= number_format($ejemplo_2_data['salario_base'], 2) ?></td></tr>
                                    <tr class="border-bottom"><td class="pb-2">Horas Extra (<?= $ejemplo_2_data['horas_extra'] ?>h)</td><td class="text-end pb-2">+ ₡<?= number_format($ejemplo_2_data['pago_horas_extra'], 2) ?></td></tr>
                                    <tr class="table-light"><td class="fw-bold-dark">Salario Bruto</td><td class="text-end fw-bold-dark">₡<?= number_format($ejemplo_2_data['salario_bruto'], 2) ?></td></tr>
                                    <tr><td class="pt-3" colspan="2"><em>(-) Deducciones de Ley</em></td></tr>
                                    <?php foreach($ejemplo_2_data['deducciones_ley']['detalles'] as $ded): ?>
                                    <tr><td class="text-danger ps-3">&ndash; <?= htmlspecialchars($ded['descripcion']) ?> (<?= $ded['porcentaje'] ?>%)</td><td class="text-end text-danger">- ₡<?= number_format($ded['monto'], 2) ?></td></tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light"><td class="fw-bold-dark">Base Imponible (p/ Renta)</td><td class="text-end fw-bold-dark">₡<?= number_format($ejemplo_2_data['base_imponible_renta'], 2) ?></td></tr>
                                    <tr><td class="pt-3" colspan="2"><em>(-) Impuesto sobre la Renta</em></td></tr>
                                    <?php if(empty($ejemplo_2_data['renta']['desglose_bruto'])): ?>
                                    <tr class="calculo-detalle"><td class="ps-4">Exento de impuesto</td><td class="text-end">- ₡0.00</td></tr>
                                    <?php else: foreach($ejemplo_2_data['renta']['desglose_bruto'] as $tramo_renta): ?>
                                    <tr class="calculo-detalle"><td class="ps-4"><?= $tramo_renta['descripcion'] ?></td><td class="text-end">- ₡<?= number_format($tramo_renta['monto'], 2) ?></td></tr>
                                    <?php endforeach; endif; ?>
                                    <tr class="calculo-detalle border-bottom"><td class="ps-4 fw-bold">Impuesto Bruto</td><td class="text-end fw-bold">- ₡<?= number_format($ejemplo_2_data['renta']['bruto'], 2) ?></td></tr>
                                    <tr class="calculo-detalle"><td class="ps-4 text-success">↳ Crédito por Cónyuge</td><td class="text-end text-success">+ ₡<?= number_format($ejemplo_2_data['renta']['credito_conyuge'], 2) ?></td></tr>
                                    <tr class="calculo-detalle border-bottom"><td class="ps-4 text-success">↳ Crédito por Hijos (<?= $ejemplo_2_data['num_hijos'] ?>)</td><td class="text-end text-success">+ ₡<?= number_format($ejemplo_2_data['renta']['credito_hijos'], 2) ?></td></tr>
                                    <tr class="border-bottom"><td class="text-danger ps-3 fw-bold">Total Impuesto Renta (Neto)</td><td class="text-end text-danger">- ₡<?= number_format($ejemplo_2_data['renta']['total'], 2) ?></td></tr>
                                    <tr class="table-light"><td class="fw-bolder pt-2 fs-6">Salario Neto a Pagar</td><td class="text-end fw-bolder pt-2 text-success fs-6">₡<?= number_format($ejemplo_2_data['salario_neto'], 2) ?></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-archive-fill me-2"></i>Historial de Planillas</h6></div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3"><label class="form-label">Filtrar Mes:</label><select name="historial_mes" class="form-select"><option value="">Todos los meses</option><?php foreach ($meses_espanol as $num => $nombre) echo "<option value='$num' " . ($num == $historial_mes ? 'selected' : '') . ">$nombre</option>"; ?></select></div>
                        <div class="col-md-5 mb-3"><label class="form-label">Filtrar Año:</label><input type="number" name="historial_anio" class="form-control" placeholder="Ej: <?= date('Y') ?>" value="<?= htmlspecialchars($historial_anio) ?>"></div>
                        <div class="col-md-2 mb-3"><button type="submit" class="btn btn-info w-100"><i class="bi bi-search"></i></button></div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Período</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php if (!empty($historial_planillas)): foreach ($historial_planillas as $h): ?>
                            <tr>
                                <td><?= $meses_espanol[(int)$h['mes']] . ' ' . $h['anio'] ?></td>
                                <td class="text-end">
                                    <a href="descargar_planilla.php?mes=<?= $h['mes'] ?>&anio=<?= $h['anio'] ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Descargar</a>
                                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#eliminarModal" data-periodo="<?= $meses_espanol[(int)$h['mes']] . ' ' . $h['anio'] ?>" data-mes="<?= $h['mes'] ?>" data-anio="<?= $h['anio'] ?>"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="2" class="text-center text-muted">No se encontraron planillas con esos filtros.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <?php 
    $permisos_con_goce_modal = ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad', 'médico'];
    foreach ($planilla_previsualizada as $col): ?>
    <div class="modal fade" id="detalleModal<?= $col['idColaborador'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 1rem;">
                <div class="modal-header bg-light"><h5 class="modal-title text-primary fw-bold"><i class="bi bi-person-badge-fill me-2"></i>Detalle Salarial: <?= htmlspecialchars($col['Nombre'] . ' ' . $col['Apellido1']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="p-3 mb-3" style="background-color: #f8f9fa; border-radius: .5rem;">
                        <div class="row">
                            <div class="col-4"><small class="text-muted">SALARIO BASE</small><div class="fs-5 fw-bold">₡<?= number_format($col['salario_base'], 2) ?></div></div>
                            <div class="col-4"><small class="text-muted">SALARIO BRUTO</small><div class="fs-5 fw-bold">₡<?= number_format($col['salario_bruto_calculado'], 2) ?></div></div>
                            <div class="col-4"><small class="text-muted">SALARIO IMPONIBLE (RENTA)</small><div class="fs-5 fw-bold">₡<?= number_format($col['salario_imponible'], 2) ?></div></div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-success fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>INGRESOS Y PERMISOS CON GOCE</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between"><span>Pago Horas Extra (<?= number_format($col['total_horas_extra'], 2) ?>h)</span><strong>+ ₡<?= number_format($col['pago_horas_extra'], 2) ?></strong></li>
                                <?php foreach($col['desglose_permisos_pagados'] as $tipo => $dias): if(in_array(strtolower($tipo), $permisos_con_goce_modal) && $dias > 0): ?><li class="list-group-item d-flex justify-content-between"><span>Permiso: <?= htmlspecialchars($tipo) ?> (<?= $dias ?> días)</span><strong class="text-muted">Informativo</strong></li><?php endif; endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-danger fw-bold"><i class="bi bi-dash-circle-fill me-2"></i>AJUSTES Y DEDUCCIONES</h6>
                             <ul class="list-group list-group-flush">
                                <?php if(empty($col['desglose_ajustes_negativos'])): ?>
                                <li class="list-group-item d-flex justify-content-between"><span>Sin ajustes por ausencias</span><strong class="text-muted">- ₡0.00</strong></li>
                                <?php else: foreach($col['desglose_ajustes_negativos'] as $ajuste): ?>
                                <li class="list-group-item d-flex justify-content-between"><span><?= htmlspecialchars($ajuste['descripcion']) ?></span><strong>- ₡<?= number_format($ajuste['monto'], 2) ?></strong></li>
                                <?php endforeach; endif; ?>
                                <hr class="my-2">
                                <li class="list-group-item disabled"><small>DEDUCCIONES DE LEY</small></li>
                                <?php foreach ($col['deducciones_legales_detalles'] as $deduccion): ?>
                                <li class="list-group-item d-flex justify-content-between"><span><?= htmlspecialchars($deduccion['descripcion']) ?> (<?= $deduccion['porcentaje'] ?>%)</span><strong>- ₡<?= number_format($deduccion['monto'], 2) ?></strong></li>
                                <?php endforeach; ?>
                                <li class="list-group-item d-flex justify-content-between"><span>Impuesto sobre la Renta (<?= htmlspecialchars($col['desglose_renta']['tramo_aplicado']) ?>)</span><strong class="text-danger">- ₡<?= number_format($col['desglose_renta']['total'], 2) ?></strong></li>
                                <?php if ($col['desglose_renta']['bruto'] > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle" style="background-color: #f8f9fa;"><span>↳ Impuesto Bruto</span><span>- ₡<?= number_format($col['desglose_renta']['bruto'], 2) ?></span></li>
                                    <?php if ($col['desglose_renta']['credito_conyuge'] > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle text-success" style="background-color: #f8f9fa;"><span>↳ Crédito por Cónyuge</span><span>+ ₡<?= number_format($col['desglose_renta']['credito_conyuge'], 2) ?></span></li>
                                    <?php endif; ?>
                                    <?php if ($col['desglose_renta']['credito_hijos'] > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle text-success" style="background-color: #f8f9fa;"><span>↳ Crédito por Hijos (<?= htmlspecialchars($col['cantidad_hijos']) ?>)</span><span>+ ₡<?= number_format($col['desglose_renta']['credito_hijos'], 2) ?></span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <div class="bg-light p-3 rounded">
                        <div class="d-flex justify-content-between text-danger"><span>Total Deducciones:</span><span>- ₡<?= number_format($col['total_deducciones'], 2) ?></span></div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bolder text-success">SALARIO NETO A PAGAR:</h5>
                            <h5 class="mb-0 fw-bolder text-success">₡<?= number_format($col['salario_neto'], 2) ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div class="modal fade" id="confirmarGeneracionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="confirmarModalLabel">Confirmar Acción</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p id="confirmarModalText"></p><div class="alert alert-warning" id="regenerarWarning" style="display:none;"><strong>¡Atención!</strong> Esta acción eliminará los registros anteriores de la planilla de este mes y los reemplazará con los nuevos cálculos.</div></div><div class="modal-footer"><form id="generarPlanillaForm" method="POST"><input type="hidden" name="mes" value="<?= htmlspecialchars($mes_seleccionado) ?>"><input type="hidden" name="anio" value="<?= htmlspecialchars($anio_seleccionado) ?>"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="generar_planilla" class="btn btn-success" id="btnConfirmarGenerar">Confirmar y Generar</button></form></div></div></div></div>
    <div class="modal fade" id="eliminarModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Estás seguro de que deseas eliminar la planilla de <b id="periodoEliminar"></b>? Esta acción no se puede deshacer.</p></div><div class="modal-footer"><form id="deleteForm" method="POST"><input type="hidden" name="mes_eliminar" id="mes_eliminar_input"><input type="hidden" name="anio_eliminar" id="anio_eliminar_input"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="eliminar_planilla" class="btn btn-danger">Sí, Eliminar</button></form></div></div></div></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const confirmarModal = document.getElementById('confirmarGeneracionModal');
        if (confirmarModal) {
            confirmarModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const yaGenerada = button.getAttribute('data-ya-generada') === 'true';
                const modalText = document.getElementById('confirmarModalText');
                const regenerarWarning = document.getElementById('regenerarWarning');
                if (yaGenerada) {
                    modalText.textContent = '¿Estás seguro de que deseas regenerar la planilla para el período actual? Los datos anteriores serán reemplazados.';
                    regenerarWarning.style.display = 'block';
                } else {
                    modalText.textContent = '¿Estás seguro de que deseas generar y guardar la planilla para el período actual?';
                    regenerarWarning.style.display = 'none';
                }
            });
        }
        const eliminarModal = document.getElementById('eliminarModal');
        if(eliminarModal) {
            eliminarModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                eliminarModal.querySelector('#periodoEliminar').textContent = button.getAttribute('data-periodo');
                eliminarModal.querySelector('#mes_eliminar_input').value = button.getAttribute('data-mes');
                eliminarModal.querySelector('#anio_eliminar_input').value = button.getAttribute('data-anio');
            });
        }
    </script>
</body>
</html>