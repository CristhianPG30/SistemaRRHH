<?php
session_start();
include 'db.php';

// 1. Validar sesión y rol (Jefatura, Admin, RRHH)
$roles_permitidos = [1, 3, 4]; 
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: login.php');
    exit;
}

// 2. Obtener el idColaborador del jefe logueado (si aplica)
$idColaboradorJefe = ($_SESSION['rol'] == 3) ? ($_SESSION['colaborador_id'] ?? 0) : null;

// 3. Procesar aprobación/rechazo
$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // La columna en la tabla horas_extra se llama 'idPermisos', probablemente un error de copiado.
    // Usaremos una variable más clara en PHP: $id_horas_extra.
    $id_horas_extra = intval($_POST['id_horas_extra']);

    if (isset($_POST['accion']) && $_POST['accion'] == 'Aprobar') {
        $stmt = $conn->prepare("UPDATE horas_extra SET estado = 'Aprobada', Observaciones = 'Aprobado por jefatura' WHERE idPermisos = ?");
        $stmt->bind_param("i", $id_horas_extra);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Horas extra aprobadas correctamente." : "Error al aprobar las horas extra.";
        $mensaje_tipo = $ok ? 'success' : 'danger';
        $stmt->close();
    } elseif (isset($_POST['accion']) && $_POST['accion'] == 'Rechazar' && !empty($_POST['comentario_rechazo'])) {
        $comentario = trim($_POST['comentario_rechazo']);
        $stmt = $conn->prepare("UPDATE horas_extra SET estado = 'Rechazada', Observaciones = ? WHERE idPermisos = ?");
        $stmt->bind_param("si", $comentario, $id_horas_extra);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Solicitud rechazada correctamente." : "Error al rechazar la solicitud.";
        $mensaje_tipo = $ok ? 'warning' : 'danger';
        $stmt->close();
    }
}

// 4. Construir consulta de horas extra pendientes ('Justificada' es el estado pendiente para este flujo)
$sql = "
SELECT 
    he.idPermisos AS id_horas_extra,
    p.Nombre, 
    p.Apellido1, 
    he.Fecha, 
    he.cantidad_horas, 
    he.Motivo
FROM horas_extra he
JOIN colaborador c ON he.Colaborador_idColaborador = c.idColaborador
JOIN persona p ON c.id_persona_fk = p.idPersona
WHERE he.estado = 'Justificada'";

$params = [];
$types = "";

if ($idColaboradorJefe) {
    $sql .= " AND c.id_jefe_fk = ?";
    $params[] = $idColaboradorJefe;
    $types .= "i";
}

$sql .= " ORDER BY he.Fecha DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Horas Extra | Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .main-content { margin-left: 280px; padding: 2.5rem; }
        .card-main {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
        }
        .card-header-custom {
            padding: 1.5rem;
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            border-radius: 1rem 1rem 0 0;
        }
        .card-title-custom { font-weight: 600; font-size: 1.5rem; color: #32325d; }
        .table thead th { font-weight: 600; color: #8898aa; background-color: #f6f9fc; }
        .table td, .table th { vertical-align: middle; text-align: center; }
        .acciones-btns .btn { width: 38px; height: 38px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="main-content">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Solicitudes de Horas Extra Pendientes</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= htmlspecialchars($mensaje_tipo) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Fecha</th>
                            <th>Horas</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['Nombre'].' '.$row['Apellido1']) ?></strong></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['Fecha']))) ?></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($row['cantidad_horas']) ?> horas</span></td>
                                <td><?= htmlspecialchars($row['Motivo']) ?></td>
                                <td><span class="badge text-bg-warning">Justificada</span></td>
                                <td>
                                    <div class="acciones-btns d-flex justify-content-center gap-2">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="id_horas_extra" value="<?= $row['id_horas_extra'] ?>">
                                            <input type="hidden" name="accion" value="Aprobar">
                                            <button type="submit" class="btn btn-success" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <button class="btn btn-danger" title="Rechazar" onclick="mostrarModalRechazo('<?= $row['id_horas_extra'] ?>')"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted p-4"><i class="bi bi-emoji-smile fs-4 d-block mb-2"></i> No hay solicitudes de horas extra pendientes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-octagon-fill text-danger me-2"></i>Motivo del Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_horas_extra" id="id_horas_extra_rechazo">
                    <input type="hidden" name="accion" value="Rechazar">
                    <div class="mb-3">
                        <label for="comentario_rechazo" class="form-label">Por favor, indica el motivo del rechazo:</label>
                        <textarea class="form-control" id="comentario_rechazo" name="comentario_rechazo" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
function mostrarModalRechazo(idHorasExtra) {
    document.getElementById('id_horas_extra_rechazo').value = idHorasExtra;
    var modal = new bootstrap.Modal(document.getElementById('modalRechazo'));
    modal.show();
}
</script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>