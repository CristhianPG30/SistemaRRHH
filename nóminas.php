<?php  
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

// --- INICIALIZACIÓN Y LÓGICA DE ACCIONES ---
$mensaje = '';
$meses_espanol = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];

// La previsualización ahora siempre es para el mes y año actual
$anio_seleccionado = intval(date('Y'));
$mes_seleccionado = intval(date('n'));

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

// --- FUNCIONES DE CÁLCULO (sin cambios) ---
function obtenerDiasFeriados($anio) { return ["$anio-01-01", "$anio-04-11", "$anio-05-01", "$anio-07-25", "$anio-08-15", "$anio-09-15", "$anio-12-25"]; }
function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = [];
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $feriados = obtenerDiasFeriados($anio);
    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
        if (date('N', strtotime($fecha)) <= 5 && !in_array($fecha, $feriados)) {
            $dias_laborales[] = $fecha;
        }
    }
    return $dias_laborales;
}
function planillaYaGenerada($anio, $mes, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM planillas WHERE YEAR(fecha_generacion) = ? AND MONTH(fecha_generacion) = ?");
    $stmt->bind_param("ii", $anio, $mes); $stmt->execute();
    return $stmt->get_result()->fetch_row()[0] > 0;
}
function calcularDeduccionesDeLey($salario_bruto, $conn) {
    $deducciones = ['total' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) { while ($row = $result->fetch_assoc()) { @list($nombre, $porcentaje) = explode(':', $row['Descripcion']); if (is_numeric(trim($porcentaje))) { $monto = $salario_bruto * (floatval(trim($porcentaje)) / 100); $deducciones['total'] += $monto; $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => trim($nombre), 'monto' => $monto, 'porcentaje' => $porcentaje]; } } }
    return $deducciones;
}
function calcularImpuestoRenta($salario_imponible) {
    $tax_brackets_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    if (!file_exists($tax_brackets_path)) return 0;
    $tax_brackets = json_decode(file_get_contents($tax_brackets_path), true); $impuesto = 0;
    foreach ($tax_brackets as $tramo) { if ($salario_imponible > $tramo['salario_minimo']) { $monto_en_tramo = ($tramo['salario_maximo'] === null) ? ($salario_imponible - $tramo['salario_minimo']) : (min($salario_imponible, $tramo['salario_maximo']) - $tramo['salario_minimo']); if($monto_en_tramo > 0) $impuesto += $monto_en_tramo * ($tramo['porcentaje'] / 100); } }
    return max(0, $impuesto);
}
function obtenerCategoriasSalariales($conn) {
    $result = $conn->query("SELECT idCategoria_salarial, Cantidad_Salarial_Tope FROM categoria_salarial ORDER BY Cantidad_Salarial_Tope ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
function determinarCategoriaSalarial($salario_bruto, $categorias) {
    foreach ($categorias as $categoria) { if ($salario_bruto <= $categoria['Cantidad_Salarial_Tope']) return $categoria['idCategoria_salarial']; }
    return !empty($categorias) ? end($categorias)['idCategoria_salarial'] : null;
}

function obtenerPlanilla($anio, $mes, $conn) {
    $categorias_salariales = obtenerCategoriasSalariales($conn);
    $dias_laborales_del_mes = calcularDiasLaborales($anio, $mes);
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, c.idColaborador, c.salario_bruto as salario_base FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.activo = 1";
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

        $asistencias_del_mes = [];
        $stmt_asist = $conn->prepare("SELECT DISTINCT DATE(Fecha) as Fecha FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asist->bind_param("iii", $colaborador['idPersona'], $mes, $anio);
        $stmt_asist->execute();
        $res_asist = $stmt_asist->get_result();
        while($row = $res_asist->fetch_assoc()) { $asistencias_del_mes[] = $row['Fecha']; }
        $stmt_asist->close();

        $permisos_del_mes = [];
        $stmt_perm = $conn->prepare("SELECT fecha_inicio, fecha_fin FROM permisos WHERE id_colaborador_fk = ? AND id_estado_fk = 4");
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        while($row = $res_perm->fetch_assoc()) {
            $inicio = new DateTime($row['fecha_inicio']); $fin = new DateTime($row['fecha_fin']); $fin->modify('+1 day');
            $rango_fechas = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
            foreach($rango_fechas as $fecha) { if($fecha->format('n') == $mes) $permisos_del_mes[] = $fecha->format('Y-m-d'); }
        }
        $stmt_perm->close();

        $dias_pagables_raw = array_unique(array_merge($asistencias_del_mes, $permisos_del_mes));
        $dias_a_pagar_efectivos = array_intersect($dias_pagables_raw, $dias_laborales_del_mes);
        $numero_dias_a_pagar = count($dias_a_pagar_efectivos);
        $dias_ausencia = count($dias_laborales_transcurridos) - $numero_dias_a_pagar;
        $pago_ordinario = $numero_dias_a_pagar * $salario_diario;

        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio); $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0); $stmt_he->close();
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5);

        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;
        $id_categoria = determinarCategoriaSalarial($salario_bruto_calculado, $categorias_salariales);
        $deducciones = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $impuesto_renta = calcularImpuestoRenta($salario_bruto_calculado - $deducciones['total']);
        $total_deducciones_final = $deducciones['total'] + $impuesto_renta;
        if($impuesto_renta > 0){
            $deducciones['detalles'][] = ['id' => 99, 'descripcion' => 'Impuesto Renta', 'monto' => $impuesto_renta];
        }
        $salario_neto = $salario_bruto_calculado - $total_deducciones_final;

        $planilla[] = array_merge($colaborador, [
            'id_categoria_salarial_fk' => $id_categoria, 
            'salario_bruto_calculado' => $salario_bruto_calculado,
            'total_horas_extra' => $total_horas_extra, 
            'pago_horas_extra' => $pago_horas_extra,
            'dias_pagados' => $numero_dias_a_pagar,
            'dias_ausencia' => $dias_ausencia,
            'deducciones_detalles' => $deducciones['detalles'], 
            'impuesto_renta' => $impuesto_renta,
            'total_deducciones' => $total_deducciones_final, 
            'salario_neto' => $salario_neto
        ]);
    }
    return $planilla;
}

function guardarPlanilla($planilla_data, $anio, $mes, $conn) {
    $fecha_generacion = sprintf("%04d-%02d-01", $anio, $mes);
    $conn->begin_transaction();
    try {
        $stmt_planilla = $conn->prepare("INSERT INTO planillas (id_colaborador_fk, fecha_generacion, id_categoria_salarial_fk, salario_bruto, total_horas_extra, total_deducciones, salario_neto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_deduccion = $conn->prepare("INSERT INTO deducciones_detalle (id_colaborador_fk, fecha_generacion_planilla, id_tipo_deduccion_fk, monto) VALUES (?, ?, ?, ?)");
        
        foreach ($planilla_data as $col) {
            $stmt_planilla->bind_param("isidddd", $col['idColaborador'], $fecha_generacion, $col['id_categoria_salarial_fk'], $col['salario_bruto_calculado'], $col['total_horas_extra'], $col['total_deducciones'], $col['salario_neto']);
            $stmt_planilla->execute();
            foreach ($col['deducciones_detalles'] as $deduccion) {
                if($deduccion['monto'] > 0){
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
$puede_generar = $es_mes_actual_para_generar && !$ya_fue_generada;
$mensaje_generar = '';
if(!$es_mes_actual_para_generar) $mensaje_generar = "Solo se puede generar la planilla del mes actual.";
if($ya_fue_generada) $mensaje_generar = "La planilla de este mes ya fue generada.";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_planilla'])) {
    if ($puede_generar) {
        if(guardarPlanilla($planilla_previsualizada, $anio_seleccionado, $mes_seleccionado, $conn)){
            $mensaje = '<div class="alert alert-success">Planilla generada y guardada exitosamente.</div>';
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $mensaje = '<div class="alert alert-danger">Error al guardar la planilla en la base de datos.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-danger">No se pudo generar esta planilla. '.$mensaje_generar.'</div>';
    }
}

// Lógica de filtro para el historial
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
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="bi bi-calendar-month me-2"></i>Cálculo y Generación de Planilla</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info"><strong>Previsualización para el mes actual: <?= htmlspecialchars($meses_espanol[$mes_seleccionado]) ?> de <?= htmlspecialchars($anio_seleccionado) ?>.</strong></div>
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
                    <form method="POST" action="">
                        <input type="hidden" name="mes" value="<?= htmlspecialchars($mes_seleccionado) ?>">
                        <input type="hidden" name="anio" value="<?= htmlspecialchars($anio_seleccionado) ?>">
                        <button type="submit" name="generar_planilla" class="btn btn-success" <?= !$puede_generar ? 'disabled title="'.$mensaje_generar.'"' : '' ?>>
                            <i class="bi bi-check-circle me-2"></i><?= $ya_fue_generada ? 'Planilla de este mes ya fue generada' : 'Generar y Guardar Planilla' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-archive-fill me-2"></i>Historial de Planillas</h6></div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Filtrar Mes:</label>
                            <select name="historial_mes" class="form-select">
                                <option value="">Todos los meses</option>
                                <?php foreach ($meses_espanol as $num => $nombre) echo "<option value='$num' ".($num==$historial_mes ?'selected':'').">$nombre</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Filtrar Año:</label>
                            <input type="number" name="historial_anio" class="form-control" placeholder="Ej: 2025" value="<?= htmlspecialchars($historial_anio) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-info w-100"><i class="bi bi-search"></i></button>
                        </div>
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
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Detalle Salarial: <?= htmlspecialchars($col['Nombre']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p><strong>Salario Base Mensual:</strong> ₡<?= number_format($col['salario_base'], 2) ?></p>
                <p class="text-primary"><strong>Pago por Días Trabajados (<?= $col['dias_pagados'] ?> días):</strong> + ₡<?= number_format($col['salario_base'] / 30 * $col['dias_pagados'], 2) ?></p>
                <p class="text-danger"><strong>Días de Ausencia:</strong> <?= $col['dias_ausencia'] ?> días</p>
                <p class="text-success"><strong>Pago Horas Extra (<?= number_format($col['total_horas_extra'], 2) ?>h):</strong> + ₡<?= number_format($col['pago_horas_extra'], 2) ?></p>
                <hr><p><strong>Salario Bruto Calculado:</strong> ₡<?= number_format($col['salario_bruto_calculado'], 2) ?></p><hr>
                <h6>Deducciones de Ley</h6>
                <ul><?php foreach ($col['deducciones_detalles'] as $deduccion): ?><li><?= htmlspecialchars($deduccion['descripcion']) ?>: - ₡<?= number_format($deduccion['monto'], 2) ?></li><?php endforeach; ?></ul>
                <p><strong>Total Deducciones:</strong> - ₡<?= number_format($col['total_deducciones'], 2) ?></p><hr>
                <h4 class="text-success"><strong>Salario Neto a Pagar: ₡<?= number_format($col['salario_neto'], 2) ?></strong></h4>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>
    <div class="modal fade" id="eliminarModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>¿Estás seguro de que deseas eliminar la planilla de <b id="periodoEliminar"></b>? Esta acción no se puede deshacer.</p></div>
            <div class="modal-footer">
                <form method="POST"><input type="hidden" name="mes_eliminar" id="mes_eliminar_input"><input type="hidden" name="anio_eliminar" id="anio_eliminar_input"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="eliminar_planilla" class="btn btn-danger">Sí, Eliminar</button></form>
            </div>
        </div></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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