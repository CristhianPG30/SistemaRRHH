<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$persona_id = $_SESSION['persona_id'];
$mensaje = '';
$mensaje_tipo = '';

// Traer idColaborador y fecha ingreso
$stmt = $conn->prepare("SELECT idColaborador, fecha_ingreso FROM colaborador WHERE id_persona_fk = ?");
$stmt->bind_param("i", $persona_id);
$stmt->execute();
$stmt->bind_result($idColaborador, $fecha_ingreso);
$stmt->fetch();
$stmt->close();

// Calcular días acumulados
$fecha_ingreso = $fecha_ingreso ?: date('Y-m-d');
$hoy = date('Y-m-d');
$dt1 = new DateTime($fecha_ingreso);
$dt2 = new DateTime($hoy);
$antiguedad_años = $dt1->diff($dt2)->y + ($dt1->diff($dt2)->m / 12);
$dias_acumulados = floor($antiguedad_años * 15);

// Días ya tomados (solo aprobados)
$sql_tomados = "SELECT SUM(DATEDIFF(fecha_fin, fecha_inicio) + 1) as total
FROM permisos 
WHERE id_colaborador_fk = ? 
  AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion) = 'vacaciones')
  AND id_estado_fk = (SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion) = 'aprobado')";
$stmt = $conn->prepare($sql_tomados);
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$stmt->bind_result($dias_tomados);
$stmt->fetch();
$stmt->close();
$dias_tomados = $dias_tomados ?: 0;
$dias_disponibles = max($dias_acumulados - $dias_tomados, 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $motivo = trim($_POST['motivo']);

    $resultTipo = $conn->query("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion)='vacaciones' LIMIT 1");
    $idTipoPermiso = $resultTipo->fetch_assoc()['idTipoPermiso'] ?? 1;

    $resultEstado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion)='pendiente' LIMIT 1");
    $idEstado = $resultEstado->fetch_assoc()['idEstado'] ?? 3;

    $dias_solicitados = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 + 1;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM permisos WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
    $stmt->bind_param("is", $idColaborador, $fecha_inicio);
    $stmt->execute();
    $stmt->bind_result($existeSolicitud);
    $stmt->fetch();
    $stmt->close();

    if ($existeSolicitud > 0) {
        $mensaje = "Ya tienes una solicitud de vacaciones registrada para esa fecha de inicio.";
        $mensaje_tipo = 'danger';
    } elseif ($dias_solicitados > $dias_disponibles) {
        $mensaje = "No tienes suficientes días disponibles para esta solicitud.";
        $mensaje_tipo = 'danger';
    } elseif ($fecha_inicio && $fecha_fin && $idColaborador) {
        $stmt = $conn->prepare("INSERT INTO permisos (id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_inicio, fecha_fin, motivo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $idColaborador, $idTipoPermiso, $idEstado, $fecha_inicio, $fecha_fin, $motivo);
        if ($stmt->execute()) {
            $mensaje = "¡Solicitud de vacaciones enviada correctamente!";
            $mensaje_tipo = 'success';
        } else {
            $mensaje = "Error al registrar la solicitud.";
            $mensaje_tipo = 'danger';
        }
        $stmt->close();
    } else {
        $mensaje = "Completa todos los campos antes de enviar la solicitud.";
        $mensaje_tipo = 'warning';
    }
}

$stmt = $conn->prepare("
SELECT p.fecha_inicio, p.fecha_fin, DATEDIFF(p.fecha_fin, p.fecha_inicio) + 1 as dias_solicitados, ec.Descripcion AS estado, p.observaciones, p.motivo
FROM permisos p
JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
WHERE tpc.Descripcion = 'Vacaciones' AND p.id_colaborador_fk = ?
ORDER BY p.fecha_inicio DESC");
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = [];
while ($row = $result->fetch_assoc()) { $solicitudes[] = $row; }
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Vacaciones - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important;
            font-family: 'Poppins', sans-serif;
        }
        .main-container {
            max-width: 880px;
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
        .form-label { color: #288cc8; font-weight: 600; }
        .form-control, input[type="date"] { border-radius: 0.9rem; }
        .btn-submit-custom {
            background: linear-gradient(90deg, #1f8ff7 75%, #53e3fc 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            border-radius: 0.8rem;
            padding: .63rem 1.5rem;
            box-shadow: 0 2px 12px #1f8ff722;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-submit-custom:hover { background: linear-gradient(90deg, #53e3fc 25%, #1f8ff7 100%); color: #fff; }
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
            font-size: 1.1rem;
        }
        .table-custom td, .table-custom th {
            padding: 0.75rem 0.7rem;
            text-align: center;
            vertical-align: middle;
        }
        .badge-disponibles {
            background: linear-gradient(90deg, #01b87f 60%, #53e3fc 100%);
            color: #fff;
            font-size: 1.1rem;
            padding: .6rem 1.1rem;
            border-radius: .9rem;
            font-weight: 600;
        }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-success { background-color: #01b87f !important; }
        .badge.bg-danger { background-color: #ff6565 !important; }
        .section-title {
            font-weight: 700;
            color: #1a3961;
            font-size: 1.4rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        @media (max-width: 600px) {
            .main-card { padding: 1.1rem 0.3rem 0.9rem 0.3rem; }
            .card-title-custom { font-size: 1.3rem; }
            .table-custom th, .table-custom td { font-size: .96rem; padding: 0.4rem 0.3rem;}
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-container">
    <div class="main-card">
        <div class="card-title-custom animate__animated animate__fadeInDown">
            <i class="bi bi-sun-fill"></i> Solicitar Vacaciones
        </div>
        <p class="text-center mb-4">Planifica tu descanso y gestiona tus días libres.</p>

        <form method="post">
            <div class="mb-4 text-center">
                <span class="badge-disponibles"><i class="bi bi-check-circle"></i> Disponibles: <?= $dias_disponibles ?> días</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Comentario (Opcional)</label>
                <input type="text" name="motivo" class="form-control" placeholder="Ej: Viaje familiar" maxlength="100">
            </div>
            <button type="submit" name="solicitar" class="btn btn-submit-custom">
                <i class="bi bi-send-fill"></i> Enviar Solicitud
            </button>
        </form>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> mt-4 text-center"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
    </div>

    <div class="main-card mt-4">
        <div class="section-title">
            <i class="bi bi-clock-history"></i> Historial de Solicitudes
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr><td colspan="5">Aún no tienes solicitudes registradas.</td></tr>
                    <?php else: foreach ($solicitudes as $sol): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($sol['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($sol['fecha_fin'])) ?></td>
                            <td><?= htmlspecialchars($sol['dias_solicitados']) ?></td>
                            <td>
                                <?php
                                $estado_lower = strtolower($sol['estado']);
                                if ($estado_lower == 'pendiente') echo '<span class="badge bg-warning">Pendiente</span>';
                                else if ($estado_lower == 'aprobado') echo '<span class="badge bg-success">Aprobado</span>';
                                else if ($estado_lower == 'rechazado') echo '<span class="badge bg-danger">Rechazado</span>';
                                else echo '<span class="badge bg-info">'.htmlspecialchars($sol['estado']).'</span>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($sol['motivo']) ?: '-' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>