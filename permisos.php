<?php
session_start();
include 'db.php';

// 1. Validar sesión
if (!isset($_SESSION['username']) || !isset($_SESSION['persona_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Obtener el idColaborador del jefe logueado
$persona_id = $_SESSION['persona_id'];
$stmt = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ?");
$stmt->bind_param("i", $persona_id);
$stmt->execute();
$stmt->bind_result($idColaboradorJefe);
$stmt->fetch();
$stmt->close();

if (!$idColaboradorJefe) {
    die("No tienes perfil de jefe configurado en el sistema.");
}

// 3. Procesar aprobación/rechazo
$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_colaborador = intval($_POST['id_colaborador_fk']);
    $fecha_inicio   = $_POST['fecha_inicio'];
    if ($_POST['accion'] == 'Aprobar') {
        $stmt = $conn->prepare("UPDATE permisos SET id_estado_fk = 4 WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
        $stmt->bind_param("is", $id_colaborador, $fecha_inicio);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Permiso aprobado correctamente." : "Error al aprobar el permiso.";
        $mensaje_tipo = $ok ? 'success' : 'danger';
        $stmt->close();
    } elseif ($_POST['accion'] == 'Rechazar' && !empty($_POST['comentario_rechazo'])) {
        $comentario = $_POST['comentario_rechazo'];
        $stmt = $conn->prepare("UPDATE permisos SET id_estado_fk = 5, observaciones = ? WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
        $stmt->bind_param("sis", $comentario, $id_colaborador, $fecha_inicio);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Permiso rechazado correctamente." : "Error al rechazar el permiso.";
        $mensaje_tipo = $ok ? 'warning' : 'danger';
        $stmt->close();
    }
}

// 4. Mostrar solo permisos pendientes de mis subordinados (igual que horas extra)
$sql = "
SELECT 
    p.id_colaborador_fk,
    p.fecha_inicio,
    p.fecha_fin,
    p.motivo,
    p.observaciones,
    p.comprobante_url,
    tpc.Descripcion AS tipo_permiso,
    per.Nombre AS colaborador,
    per.Apellido1
FROM permisos p
JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador
JOIN persona per ON c.id_persona_fk = per.idPersona
WHERE p.id_estado_fk = 3
  AND c.id_jefe_fk = ?
  AND tpc.Descripcion NOT LIKE '%horas%'
ORDER BY p.fecha_inicio ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idColaboradorJefe);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Permisos de Mi Equipo | Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f8fd; font-family: 'Poppins', sans-serif; }
        .container { padding-top: 40px; }
        .titulo-permisos {
            font-weight: 900;
            color: #176cb1;
            font-size: 2.1rem;
            margin-bottom: 18px;
            text-align: center;
        }
        .card {
            border-radius: 1.2rem;
            box-shadow: 0 2px 18px 0 rgba(44,62,80,.09);
            border: none;
        }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .badge-tipo { font-size: .92em; background: #2ec4f1; color: #fff; border-radius: 6px; padding: .37em .7em;}
        .btn-aprobar { background: linear-gradient(90deg,#32d583 60%,#228b22 100%); color: #fff; border: none;}
        .btn-rechazar { background: linear-gradient(90deg,#ff6d6d 60%,#dc3545 100%); color: #fff; border: none;}
        .acciones-btns { display: flex; gap: 7px; justify-content: center;}
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <div class="titulo-permisos"><i class="bi bi-person-check"></i> Solicitudes de Permisos de Mi Equipo</div>
    <div class="text-center mb-4" style="color:#1e4d6d">Revisa, aprueba o rechaza las solicitudes de permisos de tus colaboradores directos.</div>
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>Colaborador</th>
                        <th>Tipo</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Motivo</th>
                        <th>Comprobante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><i class="bi bi-person-badge"></i> <?= htmlspecialchars($row['colaborador'] . ' ' . $row['Apellido1']) ?></td>
                            <td><span class="badge badge-tipo"><?= htmlspecialchars($row['tipo_permiso']) ?></span></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_inicio']))) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_fin']))) ?></td>
                            <td><?= htmlspecialchars($row['motivo']) ?></td>
                            <td>
                                <?php if (!empty($row['comprobante_url'])): ?>
                                    <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-arrow-down"></i> Ver</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones-btns">
                                    <!-- Aprobar -->
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id_colaborador_fk" value="<?= $row['id_colaborador_fk'] ?>">
                                        <input type="hidden" name="fecha_inicio" value="<?= $row['fecha_inicio'] ?>">
                                        <input type="hidden" name="accion" value="Aprobar">
                                        <button type="submit" class="btn btn-aprobar btn-sm" title="Aprobar"><i class="bi bi-check-circle"></i></button>
                                    </form>
                                    <!-- Rechazar -->
                                    <button class="btn btn-rechazar btn-sm" onclick="mostrarComentario('<?= $row['id_colaborador_fk'] ?>', '<?= $row['fecha_inicio'] ?>')"><i class="bi bi-x-circle"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted"><i class="bi bi-emoji-neutral"></i> No hay permisos pendientes de tu equipo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Rechazo -->
<div class="modal fade" id="modalComentarioRechazo" tabindex="-1" aria-labelledby="modalComentarioRechazoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-octagon"></i> Motivo del Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="comentario_rechazo" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="comentario_rechazo" name="comentario_rechazo" required></textarea>
                    </div>
                    <input type="hidden" name="id_colaborador_fk" id="id_colaborador_fk_rechazo">
                    <input type="hidden" name="fecha_inicio" id="fecha_inicio_rechazo">
                    <input type="hidden" name="accion" value="Rechazar">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Rechazar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function mostrarComentario(idColaborador, fechaInicio) {
    document.getElementById('id_colaborador_fk_rechazo').value = idColaborador;
    document.getElementById('fecha_inicio_rechazo').value = fechaInicio;
    var modal = new bootstrap.Modal(document.getElementById('modalComentarioRechazo'));
    modal.show();
}
</script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>

