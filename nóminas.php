<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

// --- INICIALIZACIÓN Y LÓGICA DE ACCIONES ---
$mensaje = '';
$meses_espanol = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

$anio_seleccionado = isset($_GET['anio_previsualizar']) ? intval($_GET['anio_previsualizar']) : intval(date('Y'));
$mes_seleccionado = isset($_GET['mes_previsualizar']) ? intval($_GET['mes_previsualizar']) : intval(date('n'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_planilla'])) {
    $anio_eliminar = intval($_POST['anio_eliminar']);
    $mes_eliminar = intval($_POST['mes_eliminar']);
    $fecha_eliminar_str = sprintf("%04d-%02d-01", $anio_eliminar, $mes_eliminar);
    $fecha_generacion_eliminar = date('Y-m-d', strtotime($fecha_eliminar_str));

    $conn->begin_transaction();
    try {
        $stmt_deducciones = $conn->prepare("DELETE FROM deducciones_detalle WHERE fecha_generacion_planilla = ?");
        $stmt_deducciones->bind_param("s", $fecha_generacion_eliminar);
        $stmt_deducciones->execute();
        $stmt_deducciones->close();

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
    $deducciones = ['total' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * (floatval(trim($porcentaje)) / 100);
                $deducciones['total'] += $monto;
                $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => trim($nombre), 'monto' => $monto, 'porcentaje' => floatval(trim($porcentaje))];
            }
        }
    }
    return $deducciones;
}

// --- CORRECCIÓN #1: Función de Impuesto sobre la Renta actualizada ---
function calcularImpuestoRenta($salario_imponible, $cantidad_hijos)
{
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    if (!file_exists($tax_config_path)) return 0;
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    $tax_brackets = $config_data['tramos'] ?? [];
    $credito_por_hijo = $config_data['creditos_fiscales']['hijo'] ?? 0;
    
    $impuesto_calculado = 0;

    // Recorre los tramos en orden inverso para encontrar el correcto
    for ($i = count($tax_brackets) - 1; $i >= 0; $i--) {
        $tramo = $tax_brackets[$i];
        if ($salario_imponible > $tramo['salario_minimo']) {
            $excedente = $salario_imponible - $tramo['salario_minimo'];
            $impuesto_calculado = ($tramo['monto_fijo'] ?? 0) + ($excedente * ($tramo['porcentaje'] / 100));
            break; 
        }
    }
    
    $credito_total_hijos = $cantidad_hijos * $credito_por_hijo;
    $impuesto_final = $impuesto_calculado - $credito_total_hijos;
    
    return max(0, $impuesto_final);
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
    
    // --- CORRECCIÓN #2: Se añade p.cantidad_hijos a la consulta ---
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, p.cantidad_hijos, c.idColaborador, c.salario_bruto as salario_base 
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
            if ($dia <= $fecha_actual_str) {
                $dias_laborales_transcurridos[] = $dia;
            }
        }
    }

    while ($colaborador = $result_colaboradores->fetch_assoc()) {
        $idColaborador = $colaborador['idColaborador'];
        $salario_base = floatval($colaborador['salario_base']);
        $salario_diario = $salario_base / 30;
        $salario_hora = $salario_base / 240;

        $asistencias_del_mes = [];
        $stmt_asist = $conn->prepare("SELECT DISTINCT DATE(Fecha) as Fecha FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asist->bind_param("iii", $colaborador['idPersona'], $mes, $anio);
        $stmt_asist->execute();
        $res_asist = $stmt_asist->get_result();
        while ($row = $res_asist->fetch_assoc()) {
            $asistencias_del_mes[] = $row['Fecha'];
        }
        $stmt_asist->close();

        $permisos_pagados = [];
        $permisos_del_mes_por_tipo = [];
        $deduccion_permisos_sin_goce = 0;
        
        $sql_permisos = "SELECT p.fecha_inicio, p.fecha_fin, p.hora_inicio, p.hora_fin, tpc.Descripcion AS tipo_permiso 
                         FROM permisos p
                         JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                         WHERE p.id_colaborador_fk = ? AND p.id_estado_fk = 4";
        $stmt_perm = $conn->prepare($sql_permisos);
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        while ($row = $res_perm->fetch_assoc()) {
            $tipo = $row['tipo_permiso'];
            $es_pagado = in_array(strtolower($tipo), ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad']);
            
            if ($row['hora_inicio'] && $row['hora_fin']) {
                if (!$es_pagado) {
                    $horas = (strtotime($row['hora_fin']) - strtotime($row['hora_inicio'])) / 3600;
                    $deduccion_permisos_sin_goce += $horas * $salario_hora;
                }
            } else {
                $inicio = new DateTime($row['fecha_inicio']);
                $fin = new DateTime($row['fecha_fin']);
                $fin->modify('+1 day');
                $rango = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
                foreach ($rango as $fecha) {
                    if ($fecha->format('n') == $mes) {
                        $fecha_str = $fecha->format('Y-m-d');
                        if ($es_pagado) {
                            $permisos_pagados[] = $fecha_str;
                            $permisos_del_mes_por_tipo[$tipo] = ($permisos_del_mes_por_tipo[$tipo] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        $stmt_perm->close();

        $dias_asistencia_efectivos = array_intersect($asistencias_del_mes, $dias_laborales_del_mes);
        $dias_pagables_raw = array_unique(array_merge($dias_asistencia_efectivos, $permisos_pagados));
        $numero_dias_a_pagar = count($dias_pagables_raw);
        $dias_ausencia = count($dias_laborales_transcurridos) - $numero_dias_a_pagar;
        
        $deduccion_por_ausencia = $dias_ausencia > 0 ? $dias_ausencia * $salario_diario : 0;
        $pago_ordinario = $salario_base - $deduccion_por_ausencia;

        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio);
        $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0);
        $stmt_he->close();
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5);

        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;
        $id_categoria = determinarCategoriaSalarial($salario_bruto_calculado, $categorias_salariales);
        
        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $ccss_deduction_amount = 0;
        foreach ($deducciones_ley['detalles'] as $ded) {
            if (stripos($ded['descripcion'], 'CCSS') !== false) {
                $ccss_deduction_amount = $ded['monto'];
                break;
            }
        }
        
        $salario_imponible_renta = $salario_bruto_calculado - $ccss_deduction_amount;
        
        // --- CORRECCIÓN #3: Se pasa la cantidad de hijos al cálculo de renta ---
        $impuesto_renta = calcularImpuestoRenta($salario_imponible_renta, $colaborador['cantidad_hijos']);

        $deducciones_ley['detalles'][] = ['id' => 99, 'descripcion' => 'Impuesto sobre la Renta', 'monto' => $impuesto_renta, 'porcentaje' => 0];
        if ($deduccion_permisos_sin_goce > 0) {
            $deducciones_ley['detalles'][] = ['id' => 100, 'descripcion' => 'Deducción por permisos sin goce', 'monto' => $deduccion_permisos_sin_goce, 'porcentaje' => 0];
        }
        
        $total_deducciones_final = $deducciones_ley['total'] + $impuesto_renta + $deduccion_permisos_sin_goce;
        $salario_neto = $salario_bruto_calculado - $total_deducciones_final;
        
        $planilla[] = array_merge($colaborador, [
            'id_categoria_salarial_fk' => $id_categoria,
            'salario_bruto_calculado' => $salario_bruto_calculado,
            'salario_imponible_renta' => $salario_imponible_renta,
            'total_horas_extra' => $total_horas_extra,
            'pago_horas_extra' => $pago_horas_extra,
            'dias_pagados' => $numero_dias_a_pagar,
            'dias_ausencia' => $dias_ausencia,
            'deduccion_ausencia' => $deduccion_por_ausencia,
            'desglose_dias' => [
                'asistencia' => count($dias_asistencia_efectivos),
                'permisos' => $permisos_del_mes_por_tipo
            ],
            'deducciones_detalles' => $deducciones_ley['detalles'],
            'total_deducciones' => $total_deducciones_final,
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

        $stmt_planilla = $conn->prepare("INSERT INTO planillas (id_colaborador_fk, fecha_generacion, id_categoria_salarial_fk, salario_bruto, total_horas_extra, total_deducciones, salario_neto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_deduccion = $conn->prepare("INSERT INTO deducciones_detalle (id_colaborador_fk, fecha_generacion_planilla, id_tipo_deduccion_fk, monto) VALUES (?, ?, ?, ?)");
        
        foreach ($planilla_data as $col) {
            $stmt_planilla->bind_param("isidddd", $col['idColaborador'], $fecha_generacion, $col['id_categoria_salarial_fk'], $col['salario_bruto_calculado'], $col['pago_horas_extra'], $col['total_deducciones'], $col['salario_neto']);
            $stmt_planilla->execute();
            foreach ($col['deducciones_detalles'] as $deduccion) {
                if ($deduccion['id'] == 99 || $deduccion['id'] == 100) {
                    continue;
                }
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

$planilla_previsualizada = obtenerPlanilla($anio_seleccionado, $mes_seleccionado, $conn);
$ya_fue_generada = planillaYaGenerada($anio_seleccionado, $mes_seleccionado, $conn);
$es_mes_actual_para_generar = ($anio_seleccionado == date('Y') && $mes_seleccionado == date('n'));
$puede_generar = $es_mes_actual_para_generar;
$mensaje_generar = '';
if (!$es_mes_actual_para_generar) $mensaje_generar = "Solo se puede generar la planilla del mes actual.";


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_planilla'])) {
    if ($puede_generar) {
        $mes_a_generar = intval($_POST['mes']);
        $anio_a_generar = intval($_POST['anio']);
        $planilla_a_generar = obtenerPlanilla($anio_a_generar, $mes_a_generar, $conn);

        if (guardarPlanilla($planilla_a_generar, $anio_a_generar, $mes_a_generar, $conn)) {
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Planilla - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
                    <select name="mes_previsualizar" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($meses_espanol as $num => $nombre) echo "<option value='$num' " . ($num == $mes_seleccionado ? 'selected' : '') . ">$nombre</option>"; ?>
                    </select>
                    <input type="number" name="anio_previsualizar" class="form-control form-control-sm" value="<?= $anio_seleccionado ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body">
                <div class="alert alert-info"><strong>Previsualización para: <?= htmlspecialchars($meses_espanol[$mes_seleccionado]) ?> de <?= htmlspecialchars($anio_seleccionado) ?>.</strong></div>
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
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmarGeneracionModal"
                            data-ya-generada="<?= $ya_fue_generada ? 'true' : 'false' ?>"
                            <?= !$puede_generar ? 'disabled title="' . $mensaje_generar . '"' : '' ?>>
                        <i class="bi bi-check-circle me-2"></i><?= $ya_fue_generada ? 'Regenerar Planilla' : 'Generar y Guardar Planilla' ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-archive-fill me-2"></i>Historial de Planillas</h6></div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3"><label class="form-label">Filtrar Mes:</label><select name="historial_mes" class="form-select"><option value="">Todos los meses</option><?php foreach ($meses_espanol as $num => $nombre) echo "<option value='$num' " . ($num == $historial_mes ? 'selected' : '') . ">$nombre</option>"; ?></select></div>
                        <div class="col-md-5 mb-3"><label class="form-label">Filtrar Año:</label><input type="number" name="historial_anio" class="form-control" placeholder="Ej: 2025" value="<?= htmlspecialchars($historial_anio) ?>"></div>
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
    
    <?php foreach ($planilla_previsualizada as $col): ?>
    <div class="modal fade" id="detalleModal<?= $col['idColaborador'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 1rem;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary fw-bold">
                        <i class="bi bi-person-badge-fill me-2"></i>
                        Detalle Salarial: <?= htmlspecialchars($col['Nombre'] . ' ' . $col['Apellido1']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-success fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>INGRESOS</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between"><span>Salario Base Mensual:</span> <strong>₡<?= number_format($col['salario_base'], 2) ?></strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Pago Horas Extra (<?= number_format($col['total_horas_extra'], 2) ?>h):</span> <strong>+ ₡<?= number_format($col['pago_horas_extra'], 2) ?></strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-danger fw-bold"><i class="bi bi-dash-circle-fill me-2"></i>DEDUCCIONES</h6>
                             <ul class="list-group list-group-flush">
                                <?php if($col['dias_ausencia'] > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between"><span>Ausencias (<?= $col['dias_ausencia'] ?> días):</span> <strong>- ₡<?= number_format($col['deduccion_ausencia'], 2) ?></strong></li>
                                <?php endif; ?>
                                <?php foreach ($col['deducciones_detalles'] as $deduccion): ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span><?= htmlspecialchars($deduccion['descripcion']) ?>
                                            <?php if($deduccion['porcentaje'] > 0): ?>
                                                <small class="text-muted">(<?= $deduccion['porcentaje'] ?>%)</small>
                                            <?php endif; ?>
                                        </span> 
                                        <strong>- ₡<?= number_format($deduccion['monto'], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <div class="bg-light p-3 rounded">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Salario Bruto (Ajustado por ausencias):</span>
                            <span class="fw-bold">₡<?= number_format($col['salario_bruto_calculado'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Base para Renta (Bruto Ajustado - CCSS):</span>
                            <span>₡<?= number_format($col['salario_imponible_renta'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-danger mt-1">
                            <span>Total Deducciones:</span>
                            <span>- ₡<?= number_format($col['total_deducciones'], 2) ?></span>
                        </div>
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
    
    <div class="modal fade" id="confirmarGeneracionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarModalLabel">Confirmar Acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmarModalText"></p>
                    <div class="alert alert-warning" id="regenerarWarning" style="display:none;">
                        <strong>¡Atención!</strong> Esta acción eliminará los registros anteriores de la planilla de este mes y los reemplazará con los nuevos cálculos.
                    </div>
                </div>
                <div class="modal-footer">
                    <form id="generarPlanillaForm" method="POST">
                        <input type="hidden" name="mes" value="<?= htmlspecialchars($mes_seleccionado) ?>">
                        <input type="hidden" name="anio" value="<?= htmlspecialchars($anio_seleccionado) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="generar_planilla" class="btn btn-success" id="btnConfirmarGenerar">Confirmar y Generar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eliminarModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>¿Estás seguro de que deseas eliminar la planilla de <b id="periodoEliminar"></b>? Esta acción no se puede deshacer.</p></div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST"><input type="hidden" name="mes_eliminar" id="mes_eliminar_input"><input type="hidden" name="anio_eliminar" id="anio_eliminar_input"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="eliminar_planilla" class="btn btn-danger">Sí, Eliminar</button></form>
            </div>
        </div></div>
    </div>
    
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