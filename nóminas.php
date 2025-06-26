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
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// Lista de días feriados costarricenses
function obtenerDiasFeriados($anio) {
    return [
        "$anio-01-01", // Año Nuevo
        "$anio-04-11", // Juan Santamaría
        "$anio-05-01", // Día del Trabajo
        "$anio-07-25", // Anexión de Guanacaste
        "$anio-08-02", // Día de la Virgen de los Ángeles
        "$anio-08-15", // Día de la Madre
        "$anio-09-15", // Día de la Independencia
        "$anio-12-01", // Abolición del Ejército
        "$anio-12-25"  // Navidad
    ];
}

// Función para verificar si una fecha es un día feriado
function esDiaFeriado($fecha, $anio_actual) {
    $dias_feriados = obtenerDiasFeriados($anio_actual);
    return in_array($fecha, $dias_feriados);
}

// Función para calcular el número de días laborales (lunes a viernes) en un mes
function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = 0;
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%s-%02d-%02d", $anio, $mes, $dia);
        $timestamp = strtotime($fecha);
        $dia_semana = date("N", $timestamp); // 1 (lunes) a 7 (domingo)

        // Verificar si es día laboral y no es feriado
        if ($dia_semana >= 1 && $dia_semana <= 5 && !esDiaFeriado($fecha, $anio)) {
            $dias_laborales++;
        }
    }
    return $dias_laborales;
}

// Definir horas diarias directamente en el código
$horas_diarias = 9; // Horas trabajadas por día

// Cargar configuración desde configuracion.json
$config_path = __DIR__ . "/js/configuracion.json";
if (!file_exists($config_path)) {
    die("Error: El archivo de configuración no existe en la ruta especificada.");
}

$configuracion_json = file_get_contents($config_path);
if ($configuracion_json === false) {
    die("Error: No se pudo leer el archivo de configuración.");
}

$configuracion = json_decode($configuracion_json, true);
if ($configuracion === null) {
    die("Error: El archivo de configuración contiene JSON inválido.");
}

// Verificar que 'tarifa_hora_extra' exista en la configuración
if (!isset($configuracion['tarifa_hora_extra'])) {
    die("Error: La configuración está incompleta. Asegúrate de que 'tarifa_hora_extra' esté definida.");
}

// Parámetros dinámicos
$anio_actual = date('Y');
$mes_actual = date('n'); // Número del mes sin ceros iniciales

// Si se ha enviado una solicitud de filtro o para generar la planilla, actualizamos los valores de mes y año
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['filtrar']) || isset($_POST['generar_planilla']))){
    $mes_actual = $_POST['mes'];
    $anio_actual = $_POST['anio'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['mes']) && isset($_GET['anio'])) {
    $mes_actual = $_GET['mes'];
    $anio_actual = $_GET['anio'];
}

// Verificar si la planilla ya fue generada
function planillaYaGenerada($anio, $mes) {
    global $conn;
    $sql = "SELECT COUNT(*) AS cantidad FROM planillas WHERE YEAR(Fecha_generacion) = ? AND MONTH(Fecha_generacion) = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error en la preparación de la consulta: " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("ii", $anio, $mes);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['cantidad'] > 0;
}

// Verificar si el mes seleccionado es futuro al mes actual
function esMesFuturo($anio, $mes) {
    $fecha_actual = new DateTime();
    $fecha_seleccionada = DateTime::createFromFormat('Y-n', "$anio-$mes");
    return $fecha_seleccionada > $fecha_actual;
}

// Nueva función para verificar si el mes es pasado al mes actual
function esMesPasado($anio, $mes) {
    $fecha_actual = new DateTime();
    $fecha_seleccionada = DateTime::createFromFormat('Y-n', "$anio-$mes");
    $fecha_actual->modify('first day of this month');
    return $fecha_seleccionada < $fecha_actual;
}

// Validar que se haya seleccionado mes y año antes de calcular días laborales
if (empty($mes_actual) || empty($anio_actual)) {
    $dias_laborales_mes = 0;
} else {
    // Cálculo de días laborales
    $dias_laborales_mes = calcularDiasLaborales($anio_actual, $mes_actual);
    if ($dias_laborales_mes == 0) {
        die("Error: No hay días laborales en el mes seleccionado.");
    }
}

$tarifa_hora_extra = $configuracion['tarifa_hora_extra']; // Tarifa fija por hora extra

// Cargar los tramos de impuesto sobre la renta desde el archivo JSON
$tax_brackets_path = "js/tramos_impuesto_renta.json";
if (!file_exists($tax_brackets_path)) {
    die("Error: No se pudo cargar el archivo de tramos de impuesto sobre la renta.");
}

$tax_brackets_json = file_get_contents($tax_brackets_path);
$tax_brackets = json_decode($tax_brackets_json, true);
if (!$tax_brackets) {
    die("Error: No se pudo cargar el archivo de tramos de impuesto sobre la renta.");
}

// Función para calcular el impuesto sobre la renta
function calcularImpuestoRenta($salario) {
    global $tax_brackets;
    $impuesto = 0;

    foreach ($tax_brackets as $tramo) {
        $salario_min = $tramo['salario_minimo'];
        $salario_max = $tramo['salario_maximo'];
        $porcentaje = $tramo['porcentaje'];

        if ($salario > $salario_min) {
            if ($salario_max && $salario > $salario_max) {
                $monto_imponible = $salario_max - $salario_min;
            } else {
                $monto_imponible = $salario - $salario_min;
            }
            $impuesto += $monto_imponible * ($porcentaje / 100);
        }
    }
    return $impuesto;
}

// Función para calcular y desglosar las deducciones generales
function calcularDeduccionesGenerales($salario_total) {
    global $conn;
    $deducciones = [];
    $total_deducciones = 0;

    $sql = "SELECT descripcion, porcentaje FROM deducciones WHERE aplica_general = 1 AND tipo_deduccion = 'porcentaje'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($deduccion = $result->fetch_assoc()) {
            $monto = $salario_total * ($deduccion['porcentaje'] / 100);
            $total_deducciones += $monto;
            $deducciones[] = [
                'descripcion' => $deduccion['descripcion'],
                'porcentaje' => $deduccion['porcentaje'],
                'monto' => $monto
            ];
        }
    }

    return ['total' => $total_deducciones, 'detalles' => $deducciones];
}

// Función para obtener las categorías salariales
function obtenerCategoriasSalariales() {
    global $conn;
    $sql = "SELECT Cantidad_Salarial_Tope, idCategoria_salarial FROM categoria_salarial ORDER BY Cantidad_Salarial_Tope ASC";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error al obtener categorías salariales: " . htmlspecialchars($conn->error));
    }
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[$row['Cantidad_Salarial_Tope']] = $row['idCategoria_salarial'];
    }
    return $categorias;
}

// Función para determinar la categoría salarial
function determinarCategoriaSalarial($salario_bruto) {
    $categorias = obtenerCategoriasSalariales();
    $categoria_id = NULL;

    foreach ($categorias as $tope => $idCategoria) {
        if ($salario_bruto <= $tope) {
            $categoria_id = $idCategoria;
            break;
        }
    }

    if ($categoria_id === NULL && !empty($categorias)) {
        // Asignar la categoría con el tope más alto disponible
        end($categorias);
        $categoria_id = current($categorias);
    }

    return $categoria_id;
}

// Función para guardar la planilla en la base de datos
function guardarPlanilla($planilla, $anio_actual, $mes_actual) {
    global $conn;
    foreach ($planilla as $colaborador) {
        // Verificar que se tenga el ID de categoría salarial
        if (!isset($colaborador['Categoria_salarial_idCategoria_salarial']) || $colaborador['Categoria_salarial_idCategoria_salarial'] === NULL) {
            die("Error: 'Categoria_salarial_idCategoria_salarial' no está definido para el colaborador con Cédula " . htmlspecialchars($colaborador['Cedula']));
        }

        $sql = "INSERT INTO planillas 
                (Fecha, Persona_idPersona, Categoria_salarial_idCategoria_salarial, Salario_bruto, Horas_extra, Deducciones, Salario_neto, Vacaciones, Monto_incapacidad, Fecha_generacion) 
                VALUES 
                (NOW(), 
                 (SELECT idPersona FROM persona WHERE Cedula = ?), 
                 ?, ?, ?, ?, ?, ?, ?, ?)";

        $fecha_generacion = sprintf("%s-%02d-01", $anio_actual, $mes_actual);

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Error en la preparación de la consulta: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param(
            "sidddddds",
            $colaborador['Cedula'],
            $colaborador['Categoria_salarial_idCategoria_salarial'],
            $colaborador['Salario_bruto'],
            $colaborador['Horas_extra'],
            $colaborador['Deducciones_generales'],
            $colaborador['Salario_neto'],
            $colaborador['Monto_vacaciones'],
            $colaborador['Monto_incapacidad'],
            $fecha_generacion
        );

        if (!$stmt->execute()) {
            die("Error al ejecutar la consulta: " . htmlspecialchars($stmt->error));
        }

        $stmt->close();
    }
}

// Función para obtener la planilla
function obtenerPlanilla($anio_actual, $mes_actual) {
    global $conn, $dias_laborales_mes, $horas_diarias, $tarifa_hora_extra;

    // Verificar que se haya seleccionado mes y año
    if (empty($mes_actual) || empty($anio_actual)) {
        return [];
    }

    // Consulta para obtener los datos básicos de los colaboradores
    $sql = "SELECT p.Nombre, p.Apellido1, p.Cedula, p.Salario_bruto FROM persona p";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error al obtener los colaboradores: " . htmlspecialchars($conn->error));
    }
    $colaboradores = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($colaboradores as &$colaborador) {
        $colaborador_id = $colaborador['Cedula'];
        $salario_bruto = $colaborador['Salario_bruto'];

        // Determinar la categoría salarial
        $categoria_id = determinarCategoriaSalarial($salario_bruto);

        // Verificar que se haya asignado una categoría salarial
        if ($categoria_id === NULL) {
            die("Error: No se pudo determinar la categoría salarial para el colaborador con Cédula " . htmlspecialchars($colaborador['Cedula']));
        }

        // Añadir la categoría al colaborador
        $colaborador['Categoria_salarial_idCategoria_salarial'] = $categoria_id;

        // Calcular salario por hora
        $salario_por_hora = $salario_bruto / ($dias_laborales_mes * $horas_diarias);

        // Calcular horas trabajadas según control de asistencia
        $asistencia_sql = "SELECT Entrada, Salida FROM control_de_asistencia 
                           WHERE Persona_idPersona = (SELECT idPersona FROM persona WHERE Cedula = ?) 
                           AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?";
        $stmt_asistencia = $conn->prepare($asistencia_sql);
        if (!$stmt_asistencia) {
            die("Error en la preparación de la consulta de asistencia: " . htmlspecialchars($conn->error));
        }
        $stmt_asistencia->bind_param("sii", $colaborador_id, $mes_actual, $anio_actual);
        $stmt_asistencia->execute();
        $asistencia_result = $stmt_asistencia->get_result();

        $total_horas_trabajadas = 0;
        while ($asistencia = $asistencia_result->fetch_assoc()) {
            if ($asistencia['Entrada'] && $asistencia['Salida']) {
                $entrada = new DateTime($asistencia['Entrada']);
                $salida = new DateTime($asistencia['Salida']);
                $intervalo = $salida->diff($entrada);
                $horas_trabajadas = $intervalo->h + ($intervalo->i / 60);
                $total_horas_trabajadas += $horas_trabajadas;
            }
        }

        $stmt_asistencia->close();

        // Calcular horas no trabajadas
        $horas_esperadas = $dias_laborales_mes * $horas_diarias;
        $horas_no_trabajadas = max(0, $horas_esperadas - $total_horas_trabajadas);
        $descuento_por_horas_no_trabajadas = $horas_no_trabajadas * $salario_por_hora;

        // Obtener total de horas extra
        $he_sql = "SELECT SUM(TIME_TO_SEC(cantidad_horas) / 3600) AS Total_horas_extra FROM horas_extra 
                   WHERE estado = 'Aprobado' AND Persona_idPersona = (SELECT idPersona FROM persona WHERE Cedula = ?) 
                   AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?";
        $stmt_he = $conn->prepare($he_sql);
        if (!$stmt_he) {
            die("Error en la preparación de la consulta de horas extra: " . htmlspecialchars($conn->error));
        }
        $stmt_he->bind_param("sii", $colaborador_id, $mes_actual, $anio_actual);
        $stmt_he->execute();
        $result_he = $stmt_he->get_result();
        $he_data = $result_he->fetch_assoc();
        $total_horas_extra = $he_data['Total_horas_extra'] ?: 0;
        $pago_horas_extra = $total_horas_extra * $tarifa_hora_extra;

        $stmt_he->close();

        // Calcular días de vacaciones
        $vacaciones_sql = "SELECT SUM(DATEDIFF(FechaFin, FechaInicio) + 1) AS dias_vacaciones 
                           FROM permisos 
                           WHERE TipoPermiso = 'vacaciones' AND Estado = 'Aprobado' 
                           AND Colaborador_idColaborador = (SELECT idColaborador FROM colaborador WHERE Persona_idPersona = (SELECT idPersona FROM persona WHERE Cedula = ?))
                           AND MONTH(FechaInicio) = ? AND YEAR(FechaInicio) = ?";
        $stmt_vacaciones = $conn->prepare($vacaciones_sql);
        if (!$stmt_vacaciones) {
            die("Error en la preparación de la consulta de vacaciones: " . htmlspecialchars($conn->error));
        }
        $stmt_vacaciones->bind_param("sii", $colaborador_id, $mes_actual, $anio_actual);
        $stmt_vacaciones->execute();
        $vacaciones_result = $stmt_vacaciones->get_result();
        $vacacion = $vacaciones_result->fetch_assoc();
        $dias_vacaciones = $vacacion['dias_vacaciones'] ?: 0;
        $salario_diario = $salario_bruto / $dias_laborales_mes;
        $monto_vacaciones = $dias_vacaciones * $salario_diario;

        $stmt_vacaciones->close();

        // Calcular días de incapacidad
        $incapacidades_sql = "SELECT SUM(DATEDIFF(FechaFin, FechaInicio) + 1) AS dias_incapacidad 
                              FROM permisos 
                              WHERE TipoPermiso = 'enfermedad' AND Estado = 'Aprobado'
                              AND Colaborador_idColaborador = (SELECT idColaborador FROM colaborador WHERE Persona_idPersona = (SELECT idPersona FROM persona WHERE Cedula = ?))
                              AND MONTH(FechaInicio) = ? AND YEAR(FechaInicio) = ?";
        $stmt_incapacidad = $conn->prepare($incapacidades_sql);
        if (!$stmt_incapacidad) {
            die("Error en la preparación de la consulta de incapacidades: " . htmlspecialchars($conn->error));
        }
        $stmt_incapacidad->bind_param("sii", $colaborador_id, $mes_actual, $anio_actual);
        $stmt_incapacidad->execute();
        $incapacidades_result = $stmt_incapacidad->get_result();
        $incapacidad = $incapacidades_result->fetch_assoc();
        $dias_incapacidad = $incapacidad['dias_incapacidad'] ?: 0;
        $monto_incapacidad = $dias_incapacidad * $salario_diario;

        $stmt_incapacidad->close();

        // Calcular salario total antes de deducciones
        $salario_total = $salario_bruto + $pago_horas_extra - $descuento_por_horas_no_trabajadas + $monto_vacaciones + $monto_incapacidad;

        // Calcular y desglosar las deducciones generales
        $deducciones = calcularDeduccionesGenerales($salario_total);
        $deducciones_generales = $deducciones['total'];

        // Aplicar impuesto sobre la renta
        $impuesto_renta = calcularImpuestoRenta($salario_total);

        // Sumar todas las deducciones para calcular el salario neto
        $salario_neto = $salario_total - $deducciones_generales - $impuesto_renta;

        // Guardar resultados
        $colaborador['Horas_trabajadas'] = $total_horas_trabajadas;
        $colaborador['Horas_extra'] = $pago_horas_extra;
        $colaborador['Total_horas_extra'] = $total_horas_extra;
        $colaborador['Horas_no_trabajadas'] = $horas_no_trabajadas;
        $colaborador['Descuento_por_horas_no_trabajadas'] = $descuento_por_horas_no_trabajadas;
        $colaborador['Monto_vacaciones'] = $monto_vacaciones;
        $colaborador['Dias_vacaciones'] = $dias_vacaciones;
        $colaborador['Monto_incapacidad'] = $monto_incapacidad;
        $colaborador['Dias_incapacidad'] = $dias_incapacidad;
        $colaborador['Impuesto_renta'] = $impuesto_renta;
        $colaborador['Deducciones_generales'] = $deducciones_generales;
        $colaborador['Deducciones_detalles'] = $deducciones['detalles'];
        $colaborador['Salario_neto'] = max(0, $salario_neto);
    }
    return $colaboradores;
}

$planilla = obtenerPlanilla($anio_actual, $mes_actual);

// Procesar la solicitud para generar la planilla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_planilla'])) {
    // Validar que se haya seleccionado mes y año
    if (empty($_POST['mes']) || empty($_POST['anio'])) {
        $mensaje = '<div class="alert alert-danger">Por favor, seleccione el mes y el año antes de generar la planilla.</div>';
    } else {
        $mes_actual = $_POST['mes'];
        $anio_actual = $_POST['anio'];

        // Agregar validación para evitar generar planilla de meses pasados o futuros
        if (esMesPasado($anio_actual, $mes_actual)) {
            $mensaje = '<div class="alert alert-danger">No se puede generar la planilla para meses anteriores al actual. Por favor, seleccione un mes válido.</div>';
        } elseif (esMesFuturo($anio_actual, $mes_actual)) {
            $mensaje = '<div class="alert alert-danger">No se puede generar la planilla para meses futuros. Por favor, seleccione un mes válido.</div>';
        } else {
            if (planillaYaGenerada($anio_actual, $mes_actual)) {
                $mensaje = '<div class="alert alert-warning">La planilla del mes de ' . $meses_espanol[(int)$mes_actual] . ' del año ' . $anio_actual . ' ya fue generada. Se mostrarán los datos almacenados.</div>';
            } else {
                guardarPlanilla($planilla, $anio_actual, $mes_actual);
                $mensaje = '<div class="alert alert-success">Planilla generada exitosamente.</div>';
            }
        }
    }
}

// Obtener historial de planillas generadas
function obtenerHistorialPlanillas() {
    global $conn;
    $sql = "SELECT DISTINCT YEAR(Fecha_generacion) as anio, MONTH(Fecha_generacion) as mes FROM planillas ORDER BY anio DESC, mes DESC";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error al obtener el historial de planillas: " . htmlspecialchars($conn->error));
    }
    $historial = [];
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
    return $historial;
}

$historial_planillas = obtenerHistorialPlanillas();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Planilla - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h1>Gestión de Planilla</h1>
    <p class="text-center">Consulta y genera la planilla de los colaboradores.</p>

    <?php if ($mensaje): ?>
        <?= $mensaje ?>
    <?php endif; ?>

    <form method="POST">
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="mes" class="form-label">Mes:</label>
                <select name="mes" id="mes" class="form-select" required>
                    <option value="">Seleccione un mes</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $mes_actual ? 'selected' : '' ?>><?= $meses_espanol[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="anio" class="form-label">Año:</label>
                <select name="anio" id="anio" class="form-select" required>
                    <option value="">Seleccione un año</option>
                    <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $anio_actual ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" name="filtrar" class="btn btn-primary">Filtrar</button>
            </div>
        </div>
        <button type="submit" name="generar_planilla" class="btn btn-success mb-3">Generar Planilla</button>
    </form>

    <!-- Tabla de Planilla -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Colaborador</th>
                    <th>Cédula</th>
                    <th>Categoría Salarial</th>
                    <th>Salario Bruto</th>
                    <th>Horas Extra</th>
                    <th>Vacaciones</th>
                    <th>Incapacidades</th>
                    <th>Horas Trabajadas</th>
                    <th>Horas No Trabajadas</th>
                    <th>Deducciones</th>
                    <th>Salario Neto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($planilla) && is_array($planilla)): ?>
                    <?php foreach ($planilla as $colaborador): ?>
                    <tr>
                        <td><?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido1']); ?></td>
                        <td><?= htmlspecialchars($colaborador['Cedula']); ?></td>
                        <td><?= htmlspecialchars($colaborador['Categoria_salarial_idCategoria_salarial']); ?></td>
                        <td>₡<?= number_format($colaborador['Salario_bruto'], 2); ?></td>
                        <td>₡<?= number_format($colaborador['Horas_extra'], 2); ?></td>
                        <td>₡<?= number_format($colaborador['Monto_vacaciones'], 2); ?> (<?= $colaborador['Dias_vacaciones']; ?> días)</td>
                        <td>₡<?= number_format($colaborador['Monto_incapacidad'], 2); ?> (<?= $colaborador['Dias_incapacidad']; ?> días)</td>
                        <td><?= number_format($colaborador['Horas_trabajadas'], 2); ?> horas</td>
                        <td><?= number_format($colaborador['Horas_no_trabajadas'], 2); ?> horas</td>
                        <td class="text-danger">₡<?= number_format($colaborador['Deducciones_generales'], 2); ?></td>
                        <td class="text-success">₡<?= number_format($colaborador['Salario_neto'], 2); ?></td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#detalleModal<?= htmlspecialchars($colaborador['Cedula']); ?>">Detalles</button>
                        </td>
                    </tr>

                    <!-- Modal de Detalles -->
                    <div class="modal fade" id="detalleModal<?= htmlspecialchars($colaborador['Cedula']); ?>" tabindex="-1" aria-labelledby="detalleModalLabel<?= htmlspecialchars($colaborador['Cedula']); ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="detalleModalLabel<?= htmlspecialchars($colaborador['Cedula']); ?>">Detalles de Salario</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Colaborador:</strong> <?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido1']); ?></p>
                                    <p><strong>Cédula:</strong> <?= htmlspecialchars($colaborador['Cedula']); ?></p>
                                    <p><strong>Categoría Salarial ID:</strong> <?= htmlspecialchars($colaborador['Categoria_salarial_idCategoria_salarial']); ?></p>
                                    <p><strong>Salario Bruto:</strong> ₡<?= number_format($colaborador['Salario_bruto'], 2); ?></p>
                                    <p><strong>Impuesto sobre la Renta:</strong> ₡<?= number_format($colaborador['Impuesto_renta'], 2); ?></p>
                                    <p><strong>Horas Trabajadas:</strong> <?= number_format($colaborador['Horas_trabajadas'], 2); ?> horas</p>
                                    <p><strong>Horas Extra:</strong> <?= number_format($colaborador['Total_horas_extra'], 2); ?> horas (₡<?= number_format($colaborador['Horas_extra'], 2); ?>)</p>
                                    <p><strong>Vacaciones:</strong> ₡<?= number_format($colaborador['Monto_vacaciones'], 2); ?> (<?= $colaborador['Dias_vacaciones']; ?> días)</p>
                                    <p><strong>Incapacidades:</strong> ₡<?= number_format($colaborador['Monto_incapacidad'], 2); ?> (<?= $colaborador['Dias_incapacidad']; ?> días)</p>
                                    <p><strong>Horas No Trabajadas:</strong> <?= number_format($colaborador['Horas_no_trabajadas'], 2); ?> horas (₡<?= number_format($colaborador['Descuento_por_horas_no_trabajadas'], 2); ?>)</p>
                                    <p><strong>Deducciones:</strong> ₡<?= number_format($colaborador['Deducciones_generales'], 2); ?></p>
                                    <ul>
                                        <?php if (!empty($colaborador['Deducciones_detalles']) && is_array($colaborador['Deducciones_detalles'])): ?>
                                            <?php foreach ($colaborador['Deducciones_detalles'] as $deduccion): ?>
                                            <li><?= htmlspecialchars($deduccion['descripcion']); ?>: ₡<?= number_format($deduccion['monto'], 2); ?> (<?= $deduccion['porcentaje']; ?>%)</li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                    <p><strong>Salario Neto:</strong> ₡<?= number_format($colaborador['Salario_neto'], 2); ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center">No hay datos para el mes y año seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Historial de Planillas -->
    <h2>Historial de Planillas Generadas</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Mes</th>
                    <th>Año</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($historial_planillas) && is_array($historial_planillas)): ?>
                    <?php foreach ($historial_planillas as $historial): ?>
                        <tr>
                            <td><?= $meses_espanol[(int)$historial['mes']]; ?></td>
                            <td><?= $historial['anio']; ?></td>
                            <td>
                                <a href="descargar_planilla.php?mes=<?= $historial['mes']; ?>&anio=<?= $historial['anio']; ?>" class="btn btn-primary btn-sm">Descargar Planilla</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No hay planillas generadas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>
