<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';
include 'header.php'; // Incluir el header principal

$persona_id = $_SESSION['persona_id'];
$mensaje = '';
$tipoMensaje = '';

// PROCESAR JUSTIFICACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['justificar_id'])) {
    $id = intval($_POST['justificar_id']);
    $motivo = trim($_POST['motivo']);
    if (!empty($motivo)) {
        $stmtCheck = $conn->prepare("SELECT estado FROM horas_extra WHERE Persona_idPersona = ? AND idPermisos = ?");
        $stmtCheck->bind_param("ii", $persona_id, $id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($estadoActual);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($estadoActual == 'Pendiente' || $estadoActual == 'Justificada') {
            $estado_justificada = 'Justificada';
            $stmt = $conn->prepare("UPDATE horas_extra SET Motivo = ?, estado = ? WHERE Persona_idPersona = ? AND idPermisos = ?");
            $stmt->bind_param("ssii", $motivo, $estado_justificada, $persona_id, $id);
            if ($stmt->execute()) {
                $mensaje = "¡Hora extra justificada correctamente!";
                $tipoMensaje = 'success';
            }
            $stmt->close();
        } else {
            $mensaje = "No se puede modificar una solicitud que ya ha sido revisada.";
            $tipoMensaje = 'danger';
        }
    } else {
        $mensaje = "Por favor, escribe el motivo de la justificación.";
        $tipoMensaje = 'warning';
    }
}

// TRAER HORAS EXTRA
$stmt = $conn->prepare("SELECT idPermisos, Fecha, hora_inicio, hora_fin, cantidad_horas, Motivo, estado, Observaciones
                       FROM horas_extra
                       WHERE Persona_idPersona = ?
                       ORDER BY Fecha DESC, hora_inicio DESC");
$stmt->bind_param("i", $persona_id);
$stmt->execute();
$result = $stmt->get_result();
$horasExtra = [];
while ($row = $result->fetch_assoc()) {
    $horasExtra[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Horas Extra - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important;
            font-family: 'Poppins', sans-serif;
        }
        .main-container {
            max-width: 1100px;
            margin: 48px auto 0;
            padding: 0 15px;
        }
        .main-card {
            background: #fff;
            border-radius: 2.1rem;
            box-shadow: 0 8px 38px 0 rgba(44,62,80,.12);
            padding: 2.2rem 2.1rem 1.7rem 2.1rem;
            margin-bottom: 2.2rem;
            animation: fadeInDown 0.9s;
        }
        .card-title-custom {
            font-size: 2.2rem;
            font-weight: 900;
            color: #1a3961;
            letter-spacing: .7px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .card-title-custom i { color: #3499ea; font-size: 2.2rem; }
        .text-center { color: #3a6389; }
        .table-custom {
            background: #f8fafd;
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 4px 24px #23b6ff10;
        }
        .table-custom th {
            background: #e9f6ff;
            color: #288cc8;
            font-weight: 700;
        }
        .table-custom td, .table-custom th {
            padding: 0.8rem 0.7rem;
            text-align: center;
            vertical-align: middle;
        }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-primary { background-color: #bee7fa !important; color: #157099 !important; }
        .badge.bg-success { background-color: #01b87f !important; }
        .badge.bg-danger { background-color: #ff6565 !important; }
        @media (max-width: 992px) {
            .main-card { padding: 1.1rem 0.5rem; }
            .card-title-custom { font-size: 1.5rem; }
            .table-custom th, .table-custom td { font-size: .9rem; padding: 0.5rem 0.3rem;}
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="main-card">
        <div class="card-title-custom">
            <i class="bi bi-clock-history"></i> Mis Horas Extra
        </div>
        <p class="text-center mb-4">Justifica tus horas extra pendientes de revisión.</p>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> text-center"><?= htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Total Horas</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Observaciones del Jefe</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($horasExtra)): ?>
                        <tr><td colspan="8">No tienes horas extra registradas.</td></tr>
                    <?php else: foreach ($horasExtra as $hx): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($hx['Fecha'])) ?></td>
                            <td><?= htmlspecialchars($hx['hora_inicio']) ?></td>
                            <td><?= htmlspecialchars($hx['hora_fin']) ?></td>
                            <td><?= htmlspecialchars($hx['cantidad_horas']) ?></td>
                            <td><?= htmlspecialchars($hx['Motivo'] ?: 'N/A') ?></td>
                            <td>
                                <?php
                                $estado_lower = strtolower($hx['estado']);
                                if ($estado_lower == 'pendiente') echo '<span class="badge bg-warning">Pendiente</span>';
                                else if ($estado_lower == 'justificada') echo '<span class="badge bg-primary">Justificada</span>';
                                else if ($estado_lower == 'aprobada') echo '<span class="badge bg-success">Aprobada</span>';
                                else if ($estado_lower == 'rechazada') echo '<span class="badge bg-danger">Rechazada</span>';
                                else echo '<span class="badge bg-secondary">'.htmlspecialchars($hx['estado']).'</span>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($hx['Observaciones'] ?: '-') ?></td>
                            <td>
                                <?php if ($estado_lower == 'pendiente' || $estado_lower == 'justificada'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="mostrarJustificar('<?= $hx['idPermisos'] ?>', '<?= htmlspecialchars($hx['Motivo'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-pencil-square"></i> <?= $estado_lower == 'pendiente' ? 'Justificar' : 'Editar' ?>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Revisado</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalJustificar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem;">
            <div class="modal-header">
                <h5 class="modal-title">Justificar Horas Extra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="formJustificar">
                    <input type="hidden" name="justificar_id" id="justificar_id">
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de la justificación:</label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar Justificación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalJustificar = new bootstrap.Modal(document.getElementById('modalJustificar'));
    function mostrarJustificar(id, motivo) {
        document.getElementById('justificar_id').value = id;
        document.getElementById('motivo').value = motivo || "";
        modalJustificar.show();
    }
</script>

</body>
</html>