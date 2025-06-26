<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Verifica que el usuario haya iniciado sesión y redirige si no es así
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Obtener el ID de colaborador y persona desde la sesión
$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;

// Verificar que los IDs estén en la sesión
if (!$colaborador_id || !$persona_id) {
    echo "<script>alert('Error: No se encontró el ID del colaborador o persona en la sesión.'); window.location.href='login.php';</script>";
    exit;
}

// Procesar el envío de justificación de horas extra
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['justificar_horas_extra'])) {
    $fecha = $_POST['fecha'];
    $motivo = $_POST['motivo'];

    // Actualizar la solicitud de horas extra con el motivo del colaborador
    $sql = "UPDATE horas_extra SET Motivo = ?, estado = 'Pendiente' 
            WHERE Fecha = ? AND Colaborador_idColaborador = ? AND (Motivo IS NULL OR Motivo = '')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $motivo, $fecha, $colaborador_id);

    if ($stmt->execute()) {
        echo "<script>alert('Justificación de horas extra enviada con éxito para la fecha $fecha.');</script>";
    } else {
        echo "<script>alert('Error al enviar la justificación: " . $conn->error . "');</script>";
    }
}

// Consultar el historial de horas extra del colaborador
$sqlHistorial = "SELECT Fecha, cantidad_horas, Motivo, estado FROM horas_extra WHERE Colaborador_idColaborador = ?";
$stmtHistorial = $conn->prepare($sqlHistorial);
$stmtHistorial->bind_param("i", $colaborador_id);
$stmtHistorial->execute();
$resultHistorial = $stmtHistorial->get_result();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Horas Extra - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            padding-top: 30px;
        }
        h1 {
            font-size: 2.5rem;
            color: #004085;
            text-align: center;
            margin-bottom: 30px;
        }
        .table-responsive {
            margin-top: 30px;
        }
        .btn-logout {
            border-color: #e74c3c;
            color: #e74c3c;
            padding: 5px 12px;
        }
        .btn-logout:hover {
            background-color: #e74c3c;
            color: #ffffff;
        }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

    <div class="container">
        <h1>Historial de Horas Extra</h1>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horas Extra</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultHistorial->num_rows > 0): ?>
                        <?php while ($row = $resultHistorial->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['Fecha']); ?></td>
                                <td><?php
                                    // Mostrar horas extra con redondeo: menos de 30 minutos no cuenta, más de 30 se redondea a una hora
                                    $horasExtraDecimal = (float) $row['cantidad_horas'];
                                    $horas = floor($horasExtraDecimal);
                                    $minutos = ($horasExtraDecimal - $horas) * 60;
                                    if ($minutos > 30) {
                                        $horas += 1;
                                    }
                                    echo $horas > 0 ? $horas . ' hora(s)' : 'No laboradas';
                                ?></td>
                                <td><?php echo htmlspecialchars($row['Motivo']) ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($row['estado']); ?></td>
                                <td>
                                    <?php if (empty($row['Motivo'])): ?>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#justificacionModal" data-fecha="<?php echo htmlspecialchars($row['Fecha']); ?>">Enviar Justificación</button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Justificación Enviada</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No hay horas extra registradas</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="justificacionModal" tabindex="-1" aria-labelledby="justificacionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Justificar Horas Extra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="fecha" id="fechaJustificacion">
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo</label>
                            <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="justificar_horas_extra" class="btn btn-primary">Enviar Justificación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var justificacionModal = document.getElementById('justificacionModal');
        justificacionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var fecha = button.getAttribute('data-fecha');
            document.getElementById('fechaJustificacion').value = fecha;
        });
    </script>
</body>

</html>

<?php $conn->close(); ?>