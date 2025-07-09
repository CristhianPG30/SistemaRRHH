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

// Calcular días acumulados por antigüedad (15 días/año)
$fecha_ingreso = $fecha_ingreso ?: date('Y-m-d');
$hoy = date('Y-m-d');
$dt1 = new DateTime($fecha_ingreso);
$dt2 = new DateTime($hoy);
$antiguedad_años = $dt1->diff($dt2)->y + ($dt1->diff($dt2)->m / 12);
$dias_acumulados = floor($antiguedad_años * 15);

// Días ya tomados SOLO si el permiso está aprobado
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

// ---- PROCESAR NUEVA SOLICITUD ----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $motivo = trim($_POST['motivo']);

    // Buscar id_tipo_permiso_fk para vacaciones
    $resultTipo = $conn->query("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion)='vacaciones' LIMIT 1");
    $tipo = $resultTipo->fetch_assoc();
    $idTipoPermiso = $tipo['idTipoPermiso'] ?? 1;

    // Estado pendiente
    $resultEstado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion)='pendiente' LIMIT 1");
    $estado = $resultEstado->fetch_assoc();
    $idEstado = $estado['idEstado'] ?? 3;

    // Validación: no puede solicitar más de los disponibles
    $dias_solicitados = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 + 1;

    // Verifica si ya existe una solicitud igual (mismo colaborador y misma fecha_inicio)
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
        $mensaje = "No tiene suficientes días disponibles.";
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

// ---- HISTORIAL DE SOLICITUDES DEL USUARIO ----
$stmt = $conn->prepare("
SELECT 
    p.fecha_inicio, 
    p.fecha_fin, 
    DATEDIFF(p.fecha_fin, p.fecha_inicio) + 1 as dias_solicitados, 
    ec.Descripcion AS estado, 
    p.observaciones,
    p.motivo
FROM permisos p
JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
WHERE tpc.Descripcion = 'Vacaciones' AND p.id_colaborador_fk = ?
ORDER BY p.fecha_inicio DESC
");
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
    <title>Vacaciones | Colaborador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', Arial, sans-serif; background: linear-gradient(120deg, #d4f0fc 0%, #f7fbff 100%);}
        .vac-card {
            border-radius: 1.8rem; box-shadow: 0 0.5rem 2.2rem #399bf72c;
            max-width: 460px; margin: 2.5rem auto; background: #fff;
        }
        .vac-card .card-header {
            background: linear-gradient(90deg, #399bf7 60%, #72d6fd 100%);
            color: #fff; font-size: 1.55rem; font-weight: bold;
            border-radius: 1.8rem 1.8rem 0 0; letter-spacing: 0.4px;
        }
        .badge-vac {
            font-size: 1.15rem;
            padding: 0.7em 1.2em;
            border-radius: 1.2rem;
            font-weight: 600;
            background: linear-gradient(90deg, #399bf7 60%, #72d6fd 100%);
            color: #fff;
        }
        .form-control, input[type="date"] { border-radius: 0.8rem; font-size: 1.07rem; }
        .form-control:focus { border-color: #399bf7; box-shadow: 0 0 0 2px #399bf72b;}
        .btn-vac {
            background: linear-gradient(90deg, #399bf7 60%, #72d6fd 100%);
            color: #fff; font-weight: bold; font-size: 1.13rem;
            padding: 0.9em 0; border-radius: 1.2rem; box-shadow: 0 3px 13px #399bf728; margin-top: 10px;
            letter-spacing: 0.8px; transition: background .19s, box-shadow .19s;
        }
        .btn-vac:hover { background: linear-gradient(90deg, #72d6fd 10%, #399bf7 100%); }
        .alert { font-size: 1.08rem; border-radius: 1.1rem;}
        .tabla-historial {
            border-radius: 1.2rem; background: #f9fcff;
            overflow: hidden; box-shadow: 0 1px 10px #b2e6fa18;
        }
        .tabla-historial th {
            background: #e1f2fe;
            color: #2285bd;
            font-weight: 700;
            font-size: 1.04rem;
        }
        .tabla-historial td, .tabla-historial th {
            text-align: center; vertical-align: middle;
        }
        .badge.bg-warning { color: #855800; background: #fff3cd;}
        .badge.bg-success { background: #5bd18b;}
        .badge.bg-danger { background: #f66b5d;}
        @media (max-width:600px){
            .vac-card { max-width: 98vw; }
            .tabla-historial th, .tabla-historial td { font-size: 0.97rem;}
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <div class="vac-card card mt-5 animate__animated animate__fadeInDown">
        <div class="card-header d-flex justify-content-center align-items-center gap-2">
            <i class="bi bi-umbrella"></i> Solicitar Vacaciones
        </div>
        <div class="card-body">
            <form method="post" class="mb-1">
                <div class="mb-3 d-flex justify-content-center">
                    <span class="badge-vac shadow"><i class="bi bi-moon-stars"></i> Vacaciones disponibles: <?= $dias_disponibles ?> días</span>
                </div>
                <div class="row mb-3 g-2">
                    <div class="col-6">
                        <label class="form-label fw-bold">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Fecha fin</label>
                        <input type="date" name="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Comentario (opcional)</label>
                    <input type="text" name="motivo" class="form-control" maxlength="100" placeholder="Ejemplo: Viaje familiar">
                </div>
                <button type="submit" name="solicitar" class="btn btn-vac w-100">
                    <i class="bi bi-send-check"></i> Enviar Solicitud
                </button>
            </form>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $mensaje_tipo ?> mt-3 mb-0 text-center"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-2 text-center mt-5" style="font-weight: 700; color: #2176ae; font-size: 1.22rem;">
        <i class="bi bi-clock-history"></i> Historial de vacaciones
    </div>
    <div class="table-responsive">
        <table class="table tabla-historial table-bordered align-middle mb-5">
            <thead>
                <tr>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Días</th>
                    <th>Estado</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-secondary">Aún no tienes solicitudes registradas.</td>
                    </tr>
                <?php else: foreach ($solicitudes as $sol): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($sol['fecha_inicio'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($sol['fecha_fin'])) ?></td>
                        <td><?= htmlspecialchars($sol['dias_solicitados']) ?></td>
                        <td>
                            <?php
                            if (strtolower($sol['estado']) == 'pendiente')
                                echo '<span class="badge bg-warning text-dark">Pendiente</span>';
                            else if (strtolower($sol['estado']) == 'aprobado')
                                echo '<span class="badge bg-success">Aprobado</span>';
                            else if (strtolower($sol['estado']) == 'rechazado')
                                echo '<span class="badge bg-danger">Rechazado</span>';
                            else
                                echo '<span class="badge bg-info">'.htmlspecialchars($sol['estado']).'</span>';
                            ?>
                        </td>
                        <td><?= htmlspecialchars($sol['motivo']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
