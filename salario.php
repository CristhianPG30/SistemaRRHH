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

// Configurar la localización a español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');

// Verificar si colaborador_id está definido en la sesión
if (!isset($_SESSION['colaborador_id'])) {
    die("Error: ID de colaborador no definido en la sesión.");
}

$idColaborador = $_SESSION['colaborador_id']; // ID de colaborador actual

// Obtener el historial de salarios del colaborador
$sql = "SELECT p.Fecha AS fecha, p.Salario_bruto AS salario_bruto, 
               p.Horas_extra AS horas_extra_monto, 
               p.Vacaciones AS vacaciones_monto, 
               p.Monto_incapacidad AS incapacidad_monto, 
               p.Deducciones AS total_deducciones, 
               p.Salario_neto AS salario_neto 
        FROM planillas p
        JOIN colaborador c ON p.Persona_idPersona = c.Persona_idPersona
        WHERE c.idColaborador = ?
        ORDER BY p.Fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$result = $stmt->get_result();
$historial_salarios = [];

while ($row = $result->fetch_assoc()) {
    // Convertir la fecha a mes en español
    $fecha = new DateTime($row['fecha']);
    $mes = strftime('%B', $fecha->getTimestamp()); // Obtiene el mes en español

    // Obtener los montos
    $horas_extra_monto = number_format((float) $row['horas_extra_monto'], 2);
    $vacaciones_monto = number_format((float) $row['vacaciones_monto'], 2);
    $incapacidad_monto = number_format((float) $row['incapacidad_monto'], 2);

    // Asignar los valores
    $row['fecha'] = ucfirst($mes); // Capitalizar la primera letra
    $row['horas_extra'] = "₡{$horas_extra_monto}";
    $row['vacaciones'] = "₡{$vacaciones_monto}";
    $row['incapacidad'] = "₡{$incapacidad_monto}";

    $historial_salarios[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Salarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th, .table td {
            text-align: center;
        }
        .text-success {
            color: #28a745; /* Color verde para Salario Neto */
        }
        .text-danger {
            color: #dc3545; /* Color rojo para Total Deducciones */
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4 text-center">Historial de Salarios</h2>
    
    <!-- Tabla de Historial de Salarios -->
    <div class="card mb-4">
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Salario Bruto</th>
                        <th>Horas Extra</th>
                        <th>Vacaciones</th>
                        <th>Incapacidad</th>
                        <th class="text-danger">Total Deducciones</th>
                        <th class="text-success">Salario Neto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial_salarios as $salario): ?>
                        <tr>
                            <td><?= htmlspecialchars($salario['fecha']); ?></td>
                            <td>₡ <?= number_format($salario['salario_bruto'], 2); ?></td>
                            <td><?= htmlspecialchars($salario['horas_extra']); ?></td>
                            <td><?= htmlspecialchars($salario['vacaciones']); ?></td>
                            <td><?= htmlspecialchars($salario['incapacidad']); ?></td>
                            <td class="text-danger">₡ <?= number_format($salario['total_deducciones'], 2); ?></td>
                            <td class="text-success">₡ <?= number_format($salario['salario_neto'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
