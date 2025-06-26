<?php
session_start();
include 'db.php'; // Conexión a la base de datos

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];

// Procesar la aprobación o rechazo de horas extra
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['aprobar'])) {
        $idHorasExtra = $_POST['idHorasExtra'];
        $sqlUpdate = "UPDATE horas_extra SET estado = 'Aprobado' WHERE idPermisos = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("i", $idHorasExtra);
        if ($stmt->execute()) {
            echo "<script>alert('Horas extra aprobadas con éxito');</script>";
        } else {
            echo "<script>alert('Error al aprobar horas extra: " . $conn->error . "');</script>";
        }
    }

    if (isset($_POST['rechazar']) && !empty($_POST['motivo_rechazo'])) {
        $idHorasExtra = $_POST['idHorasExtra'];
        $motivoRechazo = $conn->real_escape_string($_POST['motivo_rechazo']);
        $sqlUpdate = "UPDATE horas_extra SET estado = 'Rechazado', Observaciones = ? WHERE idPermisos = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("si", $motivoRechazo, $idHorasExtra);
        if ($stmt->execute()) {
            echo "<script>alert('Horas extra rechazadas con éxito');</script>";
        } else {
            echo "<script>alert('Error al rechazar horas extra: " . $conn->error . "');</script>";
        }
    }
}

// Consultar las solicitudes de horas extra pendientes
$sql = "SELECT he.idPermisos, p.Nombre, p.Apellido1, he.Fecha, he.cantidad_horas, he.Motivo, he.estado
        FROM horas_extra he
        JOIN persona p ON he.Persona_idPersona = p.idPersona
        WHERE he.estado = 'Pendiente'";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Horas Extra - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            padding-top: 30px;
        }
        h1 {
            font-size: 2.5rem;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .table-responsive {
            margin-top: 30px;
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            transition: background-color 0.3s ease;
        }
        .btn-approve:hover {
            background-color: #218838;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            transition: background-color 0.3s ease;
        }
        .btn-reject:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <h1>Gestión de Horas Extra</h1>
    <p class="text-center">A continuación se muestran las solicitudes de horas extra de los colaboradores.</p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Colaborador</th>
                    <th>Fecha</th>
                    <th>Horas Extra</th>
                    <th>Motivo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido1']); ?></td>
                            <td><?php echo htmlspecialchars($row['Fecha']); ?></td>
                            <td><?php echo htmlspecialchars($row['cantidad_horas']); ?> horas</td>
                            <td><?php echo htmlspecialchars($row['Motivo']); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="idHorasExtra" value="<?php echo $row['idPermisos']; ?>">
                                    <button type="submit" name="aprobar" class="btn-approve">Aprobar</button>
                                </form>
                                <button class="btn-reject" data-bs-toggle="modal" data-bs-target="#rechazoModal" onclick="setRechazoId(<?php echo $row['idPermisos']; ?>)">Rechazar</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No hay solicitudes de horas extra pendientes.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="rechazoModal" tabindex="-1" aria-labelledby="rechazoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="rechazoModalLabel">Motivo del Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idHorasExtra" id="rechazoId" value="">
                    <div class="mb-3">
                        <label for="motivo_rechazo" class="form-label">Escribe el motivo del rechazo:</label>
                        <textarea class="form-control" id="motivo_rechazo" name="motivo_rechazo" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="rechazar" class="btn btn-danger">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setRechazoId(id) {
        document.getElementById('rechazoId').value = id;
    }
</script>
</body>
</html>

<?php $conn->close(); ?>
