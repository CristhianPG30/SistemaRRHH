<?php  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

include 'db.php'; // Conexión a la base de datos

// Inicializar mensaje
$mensaje = '';

// Obtener años en los que se ha generado aguinaldo
function obtenerAniosAguinaldoGenerado() {
    global $conn;
    $sql = "SELECT DISTINCT YEAR(Fechainicio) as anio FROM aguinaldo ORDER BY anio DESC";
    $result = $conn->query($sql);
    $anios = [];
    while ($row = $result->fetch_assoc()) {
        $anios[] = $row['anio'];
    }
    return $anios;
}

// Obtener años en los que no se ha generado aguinaldo
function obtenerAniosSinAguinaldo() {
    $anioActual = (int)date('Y');
    $anios = [];
    for ($i = 2020; $i <= $anioActual + 5; $i++) {
        if (!aguinaldoGenerado($i)) {
            $anios[] = $i;
        }
    }
    return $anios;
}

// Verificar si el aguinaldo ya se ha generado para el año
function aguinaldoGenerado($anio) {
    global $conn;
    $sql = "SELECT COUNT(*) AS total FROM aguinaldo WHERE YEAR(Fechainicio) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

// Calcular aguinaldos basado en los salarios netos de empleados activos
function calcularAguinaldo($anio, $fechaFin = null) {
    global $conn;
    $fechaInicio = "$anio-01-01";
    if (!$fechaFin) {
        $fechaFin = "$anio-12-31";
    }

    $sql = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, 
                   SUM(pl.Salario_neto) AS TotalSalariosNetos,
                   (SUM(pl.Salario_neto) / 12) AS Aguinaldo
            FROM persona p
            JOIN colaborador c ON p.idPersona = c.Persona_idPersona
            JOIN planillas pl ON p.idPersona = pl.Persona_idPersona
            WHERE c.activo = 1 AND pl.Fecha_generacion BETWEEN ? AND ?
                  AND pl.Salario_neto > 0 -- Solo considerar salarios netos mayores a cero
            GROUP BY p.idPersona";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fechaInicio, $fechaFin);
    $stmt->execute();
    $result = $stmt->get_result();
    $aguinaldos = $result->fetch_all(MYSQLI_ASSOC);
    return $aguinaldos;
}

// Guardar aguinaldo en la base de datos
function guardarAguinaldo($anio) {
    global $conn;
    $aguinaldos = calcularAguinaldo($anio);

    // Verificar si hay datos para generar el aguinaldo
    if (count($aguinaldos) === 0) {
        return false;
    }

    foreach ($aguinaldos as $aguinaldo) {
        $idPersona = $aguinaldo['idPersona'];
        $montoAguinaldo = $aguinaldo['Aguinaldo'];
        $totalSalariosNetos = $aguinaldo['TotalSalariosNetos'];

        $sql_colaborador = "SELECT idColaborador FROM colaborador WHERE Persona_idPersona = ?";
        $stmt_colaborador = $conn->prepare($sql_colaborador);
        $stmt_colaborador->bind_param("i", $idPersona);
        $stmt_colaborador->execute();
        $result_colaborador = $stmt_colaborador->get_result();
        $colaborador = $result_colaborador->fetch_assoc();

        // Verificar si el colaborador existe
        if (!$colaborador) {
            continue;
        }

        $idColaborador = $colaborador['idColaborador'];

        $sql_insert = "INSERT INTO aguinaldo (Fechainicio, Fechafin, Total_de_salarios, Monto_aguinaldo, Salario, Colaborador_idColaborador)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $fechaInicio = "$anio-01-01";
        $fechaFin = "$anio-12-31";
        $salario = $aguinaldo['Aguinaldo'];

        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ssdddi", $fechaInicio, $fechaFin, $totalSalariosNetos, $montoAguinaldo, $salario, $idColaborador);
        $stmt_insert->execute();
    }
    return true;
}

// Obtener aguinaldos por año
function obtenerAguinaldosPorAnio($anio) {
    global $conn;
    $sql = "SELECT a.*, p.Nombre, p.Apellido1, p.Cedula
            FROM aguinaldo a
            JOIN colaborador c ON a.Colaborador_idColaborador = c.idColaborador
            JOIN persona p ON c.Persona_idPersona = p.idPersona
            WHERE YEAR(a.Fechainicio) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    $aguinaldos = $result->fetch_all(MYSQLI_ASSOC);
    return $aguinaldos;
}

$anio_seleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$anios_historial = obtenerAniosAguinaldoGenerado();
$aguinaldo_generado = aguinaldoGenerado($anio_seleccionado);

if ($aguinaldo_generado) {
    $aguinaldos = obtenerAguinaldosPorAnio($anio_seleccionado);
} else {
    $fecha_actual = date('Y-m-d');
    $aguinaldos = calcularAguinaldo($anio_seleccionado, $fecha_actual);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_aguinaldo'])) {
    $anioSeleccionado = (int)$_POST['anio_para_aguinaldo'];

    if (!aguinaldoGenerado($anioSeleccionado)) {
        $aguinaldos_a_generar = calcularAguinaldo($anioSeleccionado);
        if (count($aguinaldos_a_generar) > 0) {
            $resultado = guardarAguinaldo($anioSeleccionado);
            if ($resultado) {
                // Almacenar el mensaje en la sesión
                $_SESSION['mensaje'] = '<div class="alert alert-success">Aguinaldo generado exitosamente para el año ' . $anioSeleccionado . '.</div>';
                header('Location: ?anio=' . $anioSeleccionado);
                exit();
            } else {
                $mensaje = '<div class="alert alert-danger">Error al generar el aguinaldo. Por favor, inténtelo de nuevo.</div>';
            }
        } else {
            $mensaje = '<div class="alert alert-danger">No se encontraron datos para generar el aguinaldo en el año ' . $anioSeleccionado . '.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-warning">El aguinaldo ya ha sido generado para el año ' . $anioSeleccionado . '.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Aguinaldos - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h1>Historial de Aguinaldos</h1>
    <p class="text-center">Consulta y genera los aguinaldos de los colaboradores.</p>

    <?php
    // Mostrar el mensaje si existe en la sesión
    if (isset($_SESSION['mensaje'])) {
        echo $_SESSION['mensaje'];
        unset($_SESSION['mensaje']);
    }
    ?>

    <?php if (!empty($mensaje)): ?>
        <?= $mensaje ?>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <?php if (count(obtenerAniosSinAguinaldo()) > 0): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAguinaldo">Generar Aguinaldo</button>
        <?php else: ?>
            <button class="btn btn-primary" disabled>No hay años disponibles para generar aguinaldo</button>
        <?php endif; ?>
    </div>

    <div class="table-responsive mb-5">
        <h2>Aguinaldos del Año <?= $anio_seleccionado; ?></h2>
        <?php if (!$aguinaldo_generado): ?>
            <p class="text-muted">Las cantidades mostradas son acumuladas hasta la fecha actual.</p>
        <?php endif; ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Cédula</th>
                    <th>Monto Aguinaldo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($aguinaldos) > 0): ?>
                    <?php foreach ($aguinaldos as $aguinaldo): ?>
                    <tr>
                        <td><?= htmlspecialchars($aguinaldo['Nombre'] . ' ' . $aguinaldo['Apellido1']); ?></td>
                        <td><?= htmlspecialchars($aguinaldo['Cedula']); ?></td>
                        <td>₡<?= number_format($aguinaldo['Monto_aguinaldo'] ?? $aguinaldo['Aguinaldo'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No hay aguinaldos calculados para este año.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Historial de Aguinaldos -->
    <h2>Historial de Aguinaldos Generados</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Año</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($anios_historial) > 0): ?>
                    <?php foreach ($anios_historial as $anio): ?>
                    <tr>
                        <td><?= $anio; ?></td>
                        <td>
                            <a href="descargar_aguinaldo.php?anio=<?= $anio; ?>" class="btn btn-success btn-sm">Descargar Aguinaldo</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">No hay aguinaldos generados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para seleccionar el año del aguinaldo -->
<div class="modal fade" id="modalAguinaldo" tabindex="-1" aria-labelledby="modalAguinaldoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAguinaldoLabel">Generar Aguinaldo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (count(obtenerAniosSinAguinaldo()) > 0): ?>
                        <div class="mb-3">
                            <label for="anio_para_aguinaldo" class="form-label">Seleccione el año:</label>
                            <select id="anio_para_aguinaldo" name="anio_para_aguinaldo" class="form-select">
                                <?php foreach (obtenerAniosSinAguinaldo() as $anio): ?>
                                    <option value="<?= $anio; ?>"><?= $anio; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="text-danger">No hay años disponibles para generar aguinaldo.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <?php if (count(obtenerAniosSinAguinaldo()) > 0): ?>
                        <button type="submit" name="generar_aguinaldo" class="btn btn-primary">Generar</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enlaces de JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
