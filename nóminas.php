<?php 
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

include 'db.php'; // Conexión a la base de datos

// Inicializar mensaje
$mensaje = '';

// Definir nombres de los meses en español
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

function obtenerDiasFeriados($anio) {
    return ["$anio-01-01", "$anio-04-11", "$anio-05-01", "$anio-07-25", "$anio-08-15", "$anio-09-15", "$anio-12-25"];
}

function esDiaFeriado($fecha, $anio_actual) {
    return in_array($fecha, obtenerDiasFeriados($anio_actual));
}

function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = 0;
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%s-%02d-%02d", $anio, $mes, $dia);
        $timestamp = strtotime($fecha);
        $dia_semana = date("N", $timestamp);
        if ($dia_semana >= 1 && $dia_semana <= 5 && !esDiaFeriado($fecha, $anio)) {
            $dias_laborales++;
        }
    }
    return $dias_laborales;
}

$horas_diarias = 9;

$config_path = __DIR__ . "/js/configuracion.json";
$configuracion = json_decode(file_get_contents($config_path), true);
$tarifa_hora_extra = $configuracion['tarifa_hora_extra'];

$tax_brackets_path = "js/tramos_impuesto_renta.json";
$tax_brackets = json_decode(file_get_contents($tax_brackets_path), true);

$anio_actual = date('Y');
$mes_actual = date('n');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['filtrar']) || isset($_POST['generar_planilla']))){
    $mes_actual = $_POST['mes'];
    $anio_actual = $_POST['anio'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['mes']) && isset($_GET['anio'])) {
    $mes_actual = $_GET['mes'];
    $anio_actual = $_GET['anio'];
}

function planillaYaGenerada($anio, $mes) {
    global $conn;
    $sql = "SELECT COUNT(*) AS cantidad FROM planillas WHERE YEAR(fecha_generacion) = ? AND MONTH(fecha_generacion) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $anio, $mes);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['cantidad'] > 0;
}

function esMesFuturo($anio, $mes) {
    $fecha_seleccionada = DateTime::createFromFormat('Y-n-d', "$anio-$mes-01")->setTime(0, 0);
    $fecha_actual = new DateTime('first day of this month');
    $fecha_actual->setTime(0,0);
    return $fecha_seleccionada > $fecha_actual;
}

function esMesPasado($anio, $mes) {
    $fecha_seleccionada = DateTime::createFromFormat('Y-n-d', "$anio-$mes-01")->setTime(0, 0);
    $fecha_actual = new DateTime('first day of this month');
    $fecha_actual->setTime(0,0);
    return $fecha_seleccionada < $fecha_actual;
}

$dias_laborales_mes = (!empty($mes_actual) && !empty($anio_actual)) ? calcularDiasLaborales($anio_actual, $mes_actual) : 0;

function calcularImpuestoRenta($salario) {
    global $tax_brackets;
    $impuesto = 0;
    foreach ($tax_brackets as $tramo) {
        if ($salario > $tramo['salario_minimo']) {
            $monto_imponible = ($tramo['salario_maximo'] === null) ? ($salario - $tramo['salario_minimo']) : (min($salario, $tramo['salario_maximo']) - $tramo['salario_minimo']);
            $impuesto += $monto_imponible * ($tramo['porcentaje'] / 100);
        }
    }
    return $impuesto;
}

// CORRECCIÓN FINAL: La función ahora interpreta la columna 'Descripcion' para obtener el nombre y el porcentaje.
function calcularDeduccionesGenerales($salario_bruto_calculado) {
    global $conn;
    $deducciones_calculadas = [];
    $total_deducciones = 0;

    // Se asume que la tabla es 'tipo_deduccion_cat' como en configuracion.php
    $sql = "SELECT Descripcion FROM tipo_deduccion_cat";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Separar el nombre del porcentaje usando el delimitador ':'
            $parts = explode(':', $row['Descripcion']);
            
            // Verificar si el formato es "Nombre:Porcentaje"
            if (count($parts) === 2 && is_numeric(trim($parts[1]))) {
                $nombre_deduccion = trim($parts[0]);
                $porcentaje = floatval(trim($parts[1]));

                $monto = $salario_bruto_calculado * ($porcentaje / 100);
                $total_deducciones += $monto;

                $deducciones_calculadas[] = [
                    'descripcion' => $nombre_deduccion,
                    'porcentaje' => $porcentaje,
                    'monto' => $monto
                ];
            }
        }
    }

    return ['total' => $total_deducciones, 'detalles' => $deducciones_calculadas];
}


function obtenerCategoriasSalariales() {
    global $conn;
    $result = $conn->query("SELECT Cantidad_Salarial_Tope, idCategoria_salarial FROM categoria_salarial ORDER BY Cantidad_Salarial_Tope ASC");
    $categorias = [];
    if ($result) { while ($row = $result->fetch_assoc()) { $categorias[] = $row; } }
    return $categorias;
}

function determinarCategoriaSalarial($salario_bruto, $categorias) {
    foreach ($categorias as $categoria) {
        if ($salario_bruto <= $categoria['Cantidad_Salarial_Tope']) {
            return $categoria['idCategoria_salarial'];
        }
    }
    return !empty($categorias) ? end($categorias)['idCategoria_salarial'] : null;
}

function guardarPlanilla($planilla, $anio_actual, $mes_actual) {
    global $conn;
    foreach ($planilla as $colaborador) {
        $categoria_id = $colaborador['id_categoria_salarial_fk'];
        if ($categoria_id === NULL) continue;

        $sql = "INSERT INTO planillas (id_colaborador_fk, fecha_generacion, id_categoria_salarial_fk, salario_bruto, total_horas_extra, total_otros_ingresos, total_deducciones, salario_neto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $fecha_generacion = sprintf("%s-%02d-01", $anio_actual, $mes_actual);
        $total_otros_ingresos = $colaborador['Monto_vacaciones'] + $colaborador['Monto_incapacidad'];
        $total_deducciones = $colaborador['Deducciones_generales'] + $colaborador['Impuesto_renta'];
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isiddddd", 
            $colaborador['idColaborador'], $fecha_generacion, $categoria_id, 
            $colaborador['Salario_bruto'], $colaborador['Horas_extra'], $total_otros_ingresos, 
            $total_deducciones, $colaborador['Salario_neto']
        );
        $stmt->execute();
        $stmt->close();
    }
}

function obtenerPlanilla($anio_actual, $mes_actual) {
    global $conn, $dias_laborales_mes, $horas_diarias, $tarifa_hora_extra;

    if (empty($mes_actual) || empty($anio_actual) || $dias_laborales_mes <= 0) return [];
    
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, c.idColaborador FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.activo = 1";
    
    $result_colaboradores = $conn->query($sql_colaboradores);
    if (!$result_colaboradores) die("Error al obtener los colaboradores: " . htmlspecialchars($conn->error));
    
    $colaboradores = $result_colaboradores->fetch_all(MYSQLI_ASSOC);
    $categorias_salariales = obtenerCategoriasSalariales();

    foreach ($colaboradores as &$colaborador) {
        $idColaborador = $colaborador['idColaborador'];
        $idPersona = $colaborador['idPersona'];

        $stmt_salario = $conn->prepare("SELECT salario_bruto FROM planillas WHERE id_colaborador_fk = ? ORDER BY fecha_generacion DESC LIMIT 1");
        $stmt_salario->bind_param("i", $idColaborador);
        $stmt_salario->execute();
        $salario_data = $stmt_salario->get_result()->fetch_assoc();
        $stmt_salario->close();
        
        $salario_bruto = $salario_data['salario_bruto'] ?? 0.00;
        $colaborador['Salario_bruto'] = $salario_bruto;
        
        $colaborador['id_categoria_salarial_fk'] = determinarCategoriaSalarial($salario_bruto, $categorias_salariales);
        
        $salario_por_hora = ($dias_laborales_mes > 0 && $horas_diarias > 0) ? ($salario_bruto / ($dias_laborales_mes * $horas_diarias)) : 0;
        
        $stmt_asistencia = $conn->prepare("SELECT Entrada, Salida FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asistencia->bind_param("iii", $idPersona, $mes_actual, $anio_actual);
        $stmt_asistencia->execute();
        $asistencia_result = $stmt_asistencia->get_result();
        $total_horas_trabajadas = 0;
        while ($asistencia = $asistencia_result->fetch_assoc()) {
            if ($asistencia['Entrada'] && $asistencia['Salida']) {
                $intervalo = (new DateTime($asistencia['Salida']))->diff(new DateTime($asistencia['Entrada']));
                $total_horas_trabajadas += $intervalo->h + ($intervalo->i / 60);
            }
        }
        $stmt_asistencia->close();
        
        $horas_esperadas = $dias_laborales_mes * $horas_diarias;
        $horas_no_trabajadas = max(0, $horas_esperadas - $total_horas_trabajadas);
        $descuento_por_horas_no_trabajadas = $horas_no_trabajadas * $salario_por_hora;
        
        $stmt_he = $conn->prepare("SELECT SUM(TIME_TO_SEC(cantidad_horas) / 3600) AS Total_horas_extra FROM horas_extra WHERE estado = 'Aprobado' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes_actual, $anio_actual);
        $stmt_he->execute();
        $he_data = $stmt_he->get_result()->fetch_assoc();
        $total_horas_extra = $he_data['Total_horas_extra'] ?? 0;
        $pago_horas_extra = $total_horas_extra * $tarifa_hora_extra;
        $stmt_he->close();
        
        $salario_diario = ($dias_laborales_mes > 0) ? $salario_bruto / $dias_laborales_mes : 0;
        $monto_vacaciones = 0; $dias_vacaciones = 0; $monto_incapacidad = 0; $dias_incapacidad = 0;
        
        $sql_permisos = "SELECT tpc.Descripcion AS TipoPermiso, DATEDIFF(p.fecha_fin, p.fecha_inicio) + 1 AS dias
                         FROM permisos p
                         JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                         JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
                         WHERE ec.Descripcion = 'Aprobado'
                         AND p.id_colaborador_fk = ?
                         AND MONTH(p.fecha_inicio) = ? AND YEAR(p.fecha_inicio) = ?";

        $stmt_permisos = $conn->prepare($sql_permisos);
        $stmt_permisos->bind_param("iii", $idColaborador, $mes_actual, $anio_actual);
        $stmt_permisos->execute();
        $permisos_result = $stmt_permisos->get_result();
        while($permiso = $permisos_result->fetch_assoc()){
            if(strtolower($permiso['TipoPermiso']) == 'vacaciones'){ $dias_vacaciones += $permiso['dias']; }
            elseif(strtolower($permiso['TipoPermiso']) == 'enfermedad'){ $dias_incapacidad += $permiso['dias']; }
        }
        $monto_vacaciones = $dias_vacaciones * $salario_diario;
        $monto_incapacidad = $dias_incapacidad * $salario_diario;
        $stmt_permisos->close();
        
        $salario_total_antes_deducciones = $salario_bruto + $pago_horas_extra - $descuento_por_horas_no_trabajadas + $monto_vacaciones + $monto_incapacidad;
        $deducciones = calcularDeduccionesGenerales($salario_total_antes_deducciones);
        $deducciones_generales = $deducciones['total'];
        $impuesto_renta = calcularImpuestoRenta($salario_total_antes_deducciones);
        $salario_neto = $salario_total_antes_deducciones - $deducciones_generales - $impuesto_renta;

        $colaborador = array_merge($colaborador, [
            'Horas_trabajadas' => $total_horas_trabajadas, 'Horas_extra' => $pago_horas_extra,
            'Total_horas_extra' => $total_horas_extra, 'Horas_no_trabajadas' => $horas_no_trabajadas,
            'Descuento_por_horas_no_trabajadas' => $descuento_por_horas_no_trabajadas, 'Monto_vacaciones' => $monto_vacaciones,
            'Dias_vacaciones' => $dias_vacaciones, 'Monto_incapacidad' => $monto_incapacidad, 'Dias_incapacidad' => $dias_incapacidad,
            'Impuesto_renta' => $impuesto_renta, 'Deducciones_generales' => $deducciones_generales,
            'Deducciones_detalles' => $deducciones['detalles'], 'Salario_neto' => max(0, $salario_neto)
        ]);
    }
    return $colaboradores;
}

$planilla = obtenerPlanilla($anio_actual, $mes_actual);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_planilla'])) {
    if (empty($_POST['mes']) || empty($_POST['anio'])) {
        $mensaje = '<div class="alert alert-danger">Por favor, seleccione el mes y el año.</div>';
    } elseif (esMesPasado($anio_actual, $mes_actual)) {
        $mensaje = '<div class="alert alert-danger">No se puede generar la planilla para meses anteriores.</div>';
    } elseif (esMesFuturo($anio_actual, $mes_actual)) {
        $mensaje = '<div class="alert alert-danger">No se puede generar la planilla para meses futuros.</div>';
    } else {
        if (planillaYaGenerada($anio_actual, $mes_actual)) {
            $mensaje = '<div class="alert alert-warning">La planilla del mes de ' . $meses_espanol[(int)$mes_actual] . ' del año ' . $anio_actual . ' ya fue generada.</div>';
        } else {
            guardarPlanilla($planilla, $anio_actual, $mes_actual);
            $mensaje = '<div class="alert alert-success">Planilla generada exitosamente.</div>';
            $planilla = obtenerPlanilla($anio_actual, $mes_actual);
        }
    }
}
$historial_planillas = $conn->query("SELECT DISTINCT YEAR(fecha_generacion) as anio, MONTH(fecha_generacion) as mes FROM planillas ORDER BY anio DESC, mes DESC")->fetch_all(MYSQLI_ASSOC);

// --- LÓGICA PARA EL REDISEÑO (SE MANTIENE IGUAL) ---
$total_neto = 0;
$total_deducciones = 0;
$total_colaboradores = count($planilla);
foreach ($planilla as $p) {
    $total_neto += $p['Salario_neto'];
    $total_deducciones += $p['Deducciones_generales'] + $p['Impuesto_renta'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Planilla - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .card { border: none; border-radius: 0.75rem; box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #fff; border-bottom: 1px solid #e3e6f0; padding: 1rem 1.5rem; font-weight: 600; color: #4e73df; }
        .stat-card { text-align: center; padding: 1.5rem; }
        .stat-card .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.9rem; color: #858796; text-transform: uppercase; }
        .icon-colaboradores { color: #4e73df; }
        .icon-pagar { color: #1cc88a; }
        .icon-deducciones { color: #e74a3b; }
        .table-responsive { border-radius: 0.75rem; overflow: hidden; }
        .table thead th { background-color: #4e73df; color: #fff; }
        .table td, .table th { vertical-align: middle; }
        .btn-success { background-color: #1cc88a; border-color: #1cc88a; }
        .btn-success:hover { background-color: #17a673; border-color: #17a673; }
        .btn-primary { background-color: #4e73df; border-color: #4e73df; }
        .btn-primary:hover { background-color: #2e59d9; border-color: #2e59d9; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="container mt-4 mb-5">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Planilla</h1>
    </div>

    <?php if ($mensaje): echo $mensaje; endif; ?>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon icon-colaboradores"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $total_colaboradores ?></div>
                <div class="stat-label">Colaboradores en Planilla</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon icon-pagar"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value">₡<?= number_format($total_neto, 2) ?></div>
                <div class="stat-label">Total Neto a Pagar</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stat-card">
                <div class="stat-icon icon-deducciones"><i class="bi bi-graph-down-arrow"></i></div>
                <div class="stat-value">₡<?= number_format($total_deducciones, 2) ?></div>
                <div class="stat-label">Total Deducciones</div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header"><i class="bi bi-filter"></i> Filtros y Acciones</div>
        <div class="card-body">
            <form method="POST">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label for="mes" class="form-label">Mes:</label>
                        <select name="mes" id="mes" class="form-select" required>
                            <option value="">Seleccione un mes</option>
                            <?php foreach ($meses_espanol as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="anio" class="form-label">Año:</label>
                        <select name="anio" id="anio" class="form-select" required>
                            <option value="">Seleccione un año</option>
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == $anio_actual ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3 d-flex">
                        <button type="submit" name="filtrar" class="btn btn-secondary me-2 flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                        <button type="submit" name="generar_planilla" class="btn btn-success flex-grow-1"><i class="bi bi-calculator-fill"></i> Generar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header"><i class="bi bi-table"></i> Previsualización de Planilla</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th>Colaborador</th><th>Cédula</th><th>Salario Neto</th><th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($planilla)): foreach ($planilla as $colaborador): ?>
                            <tr>
                                <td><?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido1']); ?></td>
                                <td><?= htmlspecialchars($colaborador['Cedula']); ?></td>
                                <td class="text-success fw-bold">₡<?= number_format($colaborador['Salario_neto'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#detalleModal<?= htmlspecialchars($colaborador['Cedula']); ?>"><i class="bi bi-eye-fill"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center">No hay datos para el mes y año seleccionados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="card shadow">
        <div class="card-header"><i class="bi bi-archive-fill"></i> Historial de Planillas Generadas</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" style="font-size: 0.9rem;">
                    <thead class="table-light">
                        <tr><th>Mes</th><th>Año</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($historial_planillas)): foreach ($historial_planillas as $historial): ?>
                            <tr>
                                <td><?= $meses_espanol[(int)$historial['mes']]; ?></td>
                                <td><?= $historial['anio']; ?></td>
                                <td class="text-end"><a href="descargar_planilla.php?mes=<?= $historial['mes']; ?>&anio=<?= $historial['anio']; ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Descargar</a></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center">No hay planillas generadas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($planilla)): foreach ($planilla as $colaborador): ?>
<div class="modal fade" id="detalleModal<?= htmlspecialchars($colaborador['Cedula']); ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Salario: <?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido1']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                 <p><strong>Salario Base:</strong> ₡<?= number_format($colaborador['Salario_bruto'], 2); ?></p>
                 <p><strong>Pago Horas Extra:</strong> + ₡<?= number_format($colaborador['Horas_extra'], 2); ?> (<?= number_format($colaborador['Total_horas_extra'], 2); ?> horas)</p>
                 <p><strong>Pago Vacaciones:</strong> + ₡<?= number_format($colaborador['Monto_vacaciones'], 2); ?> (<?= $colaborador['Dias_vacaciones']; ?> días)</p>
                 <p><strong>Reconocimiento Incapacidades:</strong> + ₡<?= number_format($colaborador['Monto_incapacidad'], 2); ?> (<?= $colaborador['Dias_incapacidad']; ?> días)</p>
                 <p><strong>Descuento por Ausencias:</strong> - ₡<?= number_format($colaborador['Descuento_por_horas_no_trabajadas'], 2); ?> (<?= number_format($colaborador['Horas_no_trabajadas'], 2); ?> horas)</p>
                 <hr>
                 <p><strong>Salario Bruto Calculado:</strong> ₡<?= number_format($colaborador['Salario_bruto'] + $colaborador['Horas_extra'] + $colaborador['Monto_vacaciones'] + $colaborador['Monto_incapacidad'] - $colaborador['Descuento_por_horas_no_trabajadas'], 2); ?></p>
                 <hr>
                 <p><strong>Deducciones de Ley:</strong></p>
                 <ul>
                     <?php if (!empty($colaborador['Deducciones_detalles'])): foreach ($colaborador['Deducciones_detalles'] as $deduccion): ?>
                         <li><?= htmlspecialchars($deduccion['descripcion']); ?> (<?= $deduccion['porcentaje']; ?>%): - ₡<?= number_format($deduccion['monto'], 2); ?></li>
                     <?php endforeach; endif; ?>
                 </ul>
                 <p><strong>Impuesto sobre la Renta:</strong> - ₡<?= number_format($colaborador['Impuesto_renta'], 2); ?></p>
                 <p><strong>Total Deducciones:</strong> - ₡<?= number_format($colaborador['Deducciones_generales'] + $colaborador['Impuesto_renta'], 2); ?></p>
                 <hr>
                 <h4 class="text-success"><strong>Salario Neto a Pagar:</strong> ₡<?= number_format($colaborador['Salario_neto'], 2); ?></h4>
            </div>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>