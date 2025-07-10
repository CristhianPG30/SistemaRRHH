<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['persona_id'])) {
    header('Location: login.php');
    exit;
}

// 1. Obtener idColaborador del jefe logueado
$persona_id = $_SESSION['persona_id'];
$idColaboradorJefe = 0; // Inicializar para evitar errores
$stmt = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ?");
$stmt->bind_param("i", $persona_id);
$stmt->execute();
$stmt->bind_result($idColaboradorJefe);
$stmt->fetch();
$stmt->close();

if (!$idColaboradorJefe) {
    die("No tienes perfil de jefe configurado en el sistema.");
}

// Mensaje de feedback
$mensaje = '';
$mensaje_tipo = '';

// 2. Procesar la aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['aprobar'])) {
        $idHorasExtra = intval($_POST['idHorasExtra']);
        $sqlUpdate = "UPDATE horas_extra SET estado = 'Aprobada', Observaciones = 'Aprobada por la jefatura el ".date('d-m-Y H:i')."' WHERE idPermisos = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("i", $idHorasExtra);
        if ($stmt->execute()) {
            $mensaje = "¡Horas extra aprobadas correctamente!";
            $mensaje_tipo = 'success';
        } else {
            $mensaje = "Error al aprobar las horas extra.";
            $mensaje_tipo = 'danger';
        }
        $stmt->close();
    }
    if (isset($_POST['rechazar']) && !empty($_POST['motivo_rechazo'])) {
        $idHorasExtra = intval($_POST['idHorasExtra']);
        $motivoRechazo = trim($_POST['motivo_rechazo']);
        $sqlUpdate = "UPDATE horas_extra SET estado = 'Rechazada', Observaciones = ? WHERE idPermisos = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("si", $motivoRechazo, $idHorasExtra);
        if ($stmt->execute()) {
            $mensaje = "Solicitud rechazada correctamente.";
            $mensaje_tipo = 'warning';
        } else {
            $mensaje = "Error al rechazar la solicitud.";
            $mensaje_tipo = 'danger';
        }
        $stmt->close();
    }
}

// 3. Solo ver solicitudes de tus subordinados (jerarquía)
$sql = "
SELECT he.idPermisos, p.Nombre, p.Apellido1, he.Fecha, he.cantidad_horas, he.Motivo, he.estado
FROM horas_extra he
JOIN colaborador c ON he.Colaborador_idColaborador = c.idColaborador
JOIN persona p ON c.id_persona_fk = p.idPersona
WHERE he.estado = 'Justificada'
  AND c.id_jefe_fk = ?
ORDER BY he.Fecha DESC
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
    <title>Solicitudes de Horas Extra | Equipo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f8fd; font-family: 'Poppins', sans-serif; }
        .container { padding-top: 40px; margin-left: 280px; }
        .titulo-seccion {
            font-weight: 900;
            color: #176cb1;
            font-size: 2.1rem;
            margin-bottom: 12px;
            text-align: center;
        }
        .subtitulo-seccion {
            color: #1e4d6d;
            text-align: center;
            margin-bottom: 30px;
        }
        .card {
            border-radius: 1.2rem;
            box-shadow: 0 2px 18px 0 rgba(44,62,80,.09);
            border: none;
        }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .badge-estado { font-size: .95em; padding: .4em .75em; border-radius: .5rem; font-weight: 600;}
        .acciones-btns { display: flex; gap: 8px; justify-content: center;}
        .btn-aprobar { background: #28a745; color: #fff; border: none; width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;}
        .btn-aprobar:hover { background: #218838; }
        .btn-rechazar { background: #dc3545; color: #fff; border: none; width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;}
        .btn-rechazar:hover { background: #c82333; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <div class="titulo-seccion"><i class="bi bi-clock-history"></i> Aprobar Horas Extra</div>
    <div class="subtitulo-seccion">Revisa y gestiona las solicitudes de horas extra justificadas por tu equipo.</div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Colaborador</th>
                        <th>Fecha</th>
                        <th>Horas Extra</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['Nombre'].' '.$row['Apellido1']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['Fecha']))) ?></td>
                                <td><?= htmlspecialchars($row['cantidad_horas']) ?> horas</td>
                                <td><?= htmlspecialchars($row['Motivo']) ?></td>
                                <td>
                                    <span class="badge badge-estado bg-warning text-dark">Justificada</span>
                                </td>
                                <td>
                                    <div class="acciones-btns">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="idHorasExtra" value="<?= $row['idPermisos'] ?>">
                                            <button type="submit" name="aprobar" class="btn btn-aprobar" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <button class="btn btn-rechazar" data-bs-toggle="modal" data-bs-target="#rechazoModal"
                                                onclick="setRechazoId(<?= $row['idPermisos'] ?>)" title="Rechazar">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">
                                <i class="bi bi-emoji-smile fs-4"></i><br>
                                ¡Excelente! No hay solicitudes de horas extra pendientes en tu equipo.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="rechazoModal" tabindex="-1" aria-labelledby="rechazoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="rechazoModalLabel"><i class="bi bi-x-octagon-fill text-danger me-2"></i>Motivo del Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idHorasExtra" id="rechazoId" value="">
                    <div class="mb-3">
                        <label for="motivo_rechazo" class="form-label">Por favor, especifica el motivo del rechazo:</label>
                        <textarea class="form-control" id="motivo_rechazo" name="motivo_rechazo" rows="3" required></textarea>
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
<?php $stmt->close(); $conn->close(); ?>