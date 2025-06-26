<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) { // Verificar que el usuario tiene rol de colaborador
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos

$username = $_SESSION['username'];
$persona_id = $_SESSION['persona_id']; // Cambié el nombre de la variable para asegurarnos de que sea consistente con la base de datos
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background-color: #2c3e50; padding: 15px 20px; }
        .navbar-brand { display: flex; align-items: center; color: #ffffff; font-weight: bold; }
        .navbar-brand img { height: 45px; margin-right: 10px; }
        .container { padding-top: 30px; }
        h1 { font-size: 2.5rem; color: #004085; text-align: center; margin-bottom: 30px; }
        .table-responsive { margin-top: 30px; }
        .table th, .table td { text-align: center; }
        .filter-container { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .form-control { border-radius: 10px; }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

<!-- Main Content -->
<div class="container">
    <h1>Control de Asistencia</h1>
    <p class="text-center">Consulta tu historial de asistencia a continuación.</p>

    <!-- Filtros de fecha -->
    <form method="GET" class="filter-container">
        <div class="mb-3">
            <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : ''; ?>">
        </div>
        <div class="mb-3">
            <label for="fecha_fin" class="form-label">Fecha de Fin</label>
            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : ''; ?>">
        </div>
        <div class="d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <!-- Tabla de asistencia -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Hora de Entrada</th>
                    <th>Hora de Salida</th>
                    <th>Estado de Entrada</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Obtener las fechas de inicio y fin del formulario
                $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
                $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

                // Construir la consulta de asistencia con los filtros de fecha
                $sql_asistencia = "SELECT Fecha, Entrada, Salida, 
                                    CASE 
                                        WHEN Entrada <= '08:00:00' THEN 'A tiempo' 
                                        WHEN Entrada > '08:00:00' THEN 'Tarde' 
                                        ELSE 'Ausente' 
                                    END AS Estado 
                                    FROM control_de_asistencia 
                                    WHERE Persona_idPersona = ?";

                // Agregar filtros de fecha si están definidos
                if ($fecha_inicio && $fecha_fin) {
                    $sql_asistencia .= " AND Fecha BETWEEN ? AND ?";
                    $stmt = $conn->prepare($sql_asistencia);
                    $stmt->bind_param("iss", $persona_id, $fecha_inicio, $fecha_fin);
                } elseif ($fecha_inicio) {
                    $sql_asistencia .= " AND Fecha >= ?";
                    $stmt = $conn->prepare($sql_asistencia);
                    $stmt->bind_param("is", $persona_id, $fecha_inicio);
                } elseif ($fecha_fin) {
                    $sql_asistencia .= " AND Fecha <= ?";
                    $stmt = $conn->prepare($sql_asistencia);
                    $stmt->bind_param("is", $persona_id, $fecha_fin);
                } else {
                    $stmt = $conn->prepare($sql_asistencia);
                    $stmt->bind_param("i", $persona_id);
                }

                $sql_asistencia .= " ORDER BY Fecha DESC";
                $stmt->execute();
                $result_asistencia = $stmt->get_result();

                // Mostrar los datos de asistencia en la tabla
                if ($result_asistencia->num_rows > 0) {
                    while ($row = $result_asistencia->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['Fecha']) . "</td>";
                        echo "<td>" . ($row['Entrada'] ? htmlspecialchars($row['Entrada']) : '-') . "</td>";
                        echo "<td>" . ($row['Salida'] ? htmlspecialchars($row['Salida']) : '-') . "</td>";
                        echo "<td>" . htmlspecialchars($row['Estado']) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No se encontraron registros de asistencia.</td></tr>";
                }

                $stmt->close();
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
