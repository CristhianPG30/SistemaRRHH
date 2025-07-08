<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['persona_id'])) {
    header('Location: login.php');
    exit;
}

// 1. Obtener idColaborador del jefe logueado
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

// Mensaje de feedback
$mensaje = '';
$mensaje_tipo = '';

// 2. Procesar la aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['aprobar'])) {
        $idHorasExtra = intval($_POST['idHorasExtra']);
        $sqlUpdate = "UPDATE horas_extra SET estado = 'Aprobado' WHERE idPermisos = ?";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #eef3fa; }
        .container { padding-top: 40px; }
        .custom-title {
            font-weight: 900;
            color: #176cb1;
            font-size: 2.25rem;
            text-align: center;
            margin-bottom: 12px;
        }
        .subtitle {
            font-size: 1.1rem;
            color: #5d6c7c;
            text-align: center;
            margin-bottom: 35px;
        }
        .table thead th {
            background: linear-gradient(90deg, #24c6ff 50%, #2176ae 100%);
            color: #fff;
            border-top: 2px solid #176cb1;
            font-weight: 700;
        }
        .table-hover tbody tr:hover {
            background-color: #f4faff;
        }
        .badge-estado {
            font-size: 0.97em;
            font-weight: 600;
            padding: .40em .7em;
        }
        .acciones-btns {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: center;
        }
        .btn-approve {
            background: linear-gradient(90deg,#32d583 60%,#228b22 100%);
            border: none;
            color: #fff;
            border-radius: 10px;
            padding: 7px 15px;
            font-weight: 600;
            font-size: 1em;
            transition: background .2s;
        }
        .btn-approve:hover { background: #29a960; }
        .btn-reject {
            background: linear-gradient(90deg,#ff5b5b 60%,#d90429 100%);
            border: none;
            color: #fff;
            border-radius: 10px;
            padding: 7px 15px;
            font-weight: 600;
            font-size: 1em;
            transition: background .2s;
        }
        .btn-reject:hover { background: #d90429; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <div class="custom-title">
        <i class="bi bi-people-fill"></i>
        Solicitudes de Horas Extra de Mi Equipo
    </div>
    <div class="subtitle">
        Visualiza, aprueba o rechaza las solicitudes de tus colaboradores directos de forma fácil y rápida.
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 rounded-4">
                    <thead>
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
                                    <td><i class="bi bi-person-badge"></i> <?= htmlspecialchars($row['Nombre'].' '.$row['Apellido1']) ?></td>
                                    <td><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($row['Fecha']) ?></td>
                                    <td><i class="bi bi-clock-history"></i> <?= htmlspecialchars($row['cantidad_horas']) ?> horas</td>
                                    <td><?= htmlspecialchars($row['Motivo']) ?></td>
                                    <td>
                                        <span class="badge badge-estado text-bg-warning">Justificada</span>
                                    </td>
                                    <td>
                                        <div class="acciones-btns">
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="idHorasExtra" value="<?= $row['idPermisos'] ?>">
                                                <button type="submit" name="aprobar" class="btn-approve" title="Aprobar"><i class="bi bi-check-circle"></i></button>
                                            </form>
                                            <button class="btn-reject" data-bs-toggle="modal" data-bs-target="#rechazoModal"
                                                    onclick="setRechazoId(<?= $row['idPermisos'] ?>)" title="Rechazar">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary">
                                    <i class="bi bi-emoji-neutral"></i> No hay solicitudes pendientes de tu equipo.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para motivo de rechazo -->
<div class="modal fade" id="rechazoModal" tabindex="-1" aria-labelledby="rechazoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="rechazoModalLabel"><i class="bi bi-x-octagon"></i> Motivo del Rechazo</h5>
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
<?php $stmt->close(); $conn->close(); ?>
