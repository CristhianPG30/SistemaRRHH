<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) { header('Location: login.php'); exit; }
$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;

if (!$colaborador_id || !$persona_id) {
    echo "<script>alert('Error: No se encontró el ID del colaborador o persona en la sesión.'); window.location.href='login.php';</script>";
    exit;
}

$mensaje = '';
$tipoMensaje = '';

// Procesar justificación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['justificar_horas_extra'])) {
    $fecha = $_POST['fecha'];
    $motivo = trim($_POST['motivo']);
    $sql = "UPDATE horas_extra SET Motivo = ?, estado = 'Pendiente' 
            WHERE Fecha = ? AND Colaborador_idColaborador = ? AND (Motivo IS NULL OR Motivo = '')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $motivo, $fecha, $colaborador_id);

    if ($stmt->execute()) {
        $mensaje = "Justificación de horas extra enviada para $fecha.";
        $tipoMensaje = 'success';
    } else {
        $mensaje = "Error al enviar la justificación: " . $conn->error;
        $tipoMensaje = 'danger';
    }
}

// Historial y resumen
$sqlHistorial = "SELECT Fecha, cantidad_horas, Motivo, estado FROM horas_extra WHERE Colaborador_idColaborador = ? ORDER BY Fecha DESC";
$stmtHistorial = $conn->prepare($sqlHistorial);
$stmtHistorial->bind_param("i", $colaborador_id);
$stmtHistorial->execute();
$resultHistorial = $stmtHistorial->get_result();

$sqlResumen = "SELECT COUNT(*) AS total, SUM(cantidad_horas) AS horas, 
SUM(estado='Pendiente') as pendientes, SUM(estado='Aprobado') as aprobadas
FROM horas_extra WHERE Colaborador_idColaborador = ?";
$stmtResumen = $conn->prepare($sqlResumen);
$stmtResumen->bind_param("i", $colaborador_id);
$stmtResumen->execute();
$resumen = $stmtResumen->get_result()->fetch_assoc();
$total = $resumen['total'] ?? 0;
$horas = $resumen['horas'] ?? 0;
$pendientes = $resumen['pendientes'] ?? 0;
$aprobadas = $resumen['aprobadas'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horas Extra - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f4f7fb;
            font-family: 'Inter', sans-serif;
        }
        .main-container {
            max-width: 900px;
            margin: 42px auto 0;
            padding: 0 18px;
        }
        .summary-row {
            display: flex;
            gap: 1.2rem;
            margin-bottom: 2.3rem;
            flex-wrap: wrap;
        }
        .summary-card {
            flex: 1 1 180px;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 12px #b2bec330;
            text-align: center;
            padding: 1.4rem 0.8rem 1.1rem 0.8rem;
            min-width: 160px;
            min-height: 90px;
        }
        .summary-icon {
            font-size: 2rem;
            color: #2563eb;
            opacity: 0.8;
        }
        .summary-value {
            font-size: 1.85rem;
            font-weight: 700;
            margin: 0.3rem 0 0.2rem 0;
            color: #222b45;
        }
        .summary-label {
            font-size: 1.02rem;
            color: #687083;
            font-weight: 500;
        }
        .main-card {
            background: #fff;
            border-radius: 1.2rem;
            box-shadow: 0 2px 18px #d8e3f233;
            padding: 2.2rem 2rem 1.3rem 2rem;
            margin-bottom: 1.5rem;
        }
        .main-title {
            font-size: 1.62rem;
            font-weight: 700;
            color: #253053;
            margin-bottom: 1.1rem;
            letter-spacing: .2px;
            display: flex;
            align-items: center;
            gap: .7rem;
        }
        .main-title i { font-size: 1.6rem; color: #2563eb; }
        .table thead th {
            background: #eef2f8;
            color: #3e4a67;
            font-weight: 600;
            border-bottom: 2px solid #e6e9f2;
        }
        .table tbody td, .table thead th {
            text-align: center;
            vertical-align: middle;
        }
        .badge-pendiente { background: #f6c453; color: #735302; }
        .badge-aprobado { background: #6ce8b3; color: #157148; }
        .badge-rechazado { background: #e56a6a; color: #7d1f1f; }
        .btn-app {
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            padding: .45rem 1.3rem;
            border-radius: 1.4rem;
            font-size: .98rem;
            transition: background .17s;
            border: none;
        }
        .btn-app:hover {
            background: #16347a;
            color: #fff;
        }
        .no-data-row {
            color: #abb2bf;
            background: #f8fafc;
            font-style: italic;
        }
        .modal-content { border-radius: 1.2rem; }
        @media (max-width: 700px) {
            .summary-row { flex-direction: column; gap: .7rem;}
            .main-card { padding: 1.1rem 0.5rem 0.7rem 0.5rem; }
            .main-title { font-size: 1.1rem; }
            .table th, .table td { font-size: .91rem; padding: 0.5rem 0.3rem;}
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="main-container">

    <!-- RESUMEN -->
    <div class="summary-row">
        <div class="summary-card">
            <div class="summary-icon"><i class="bi bi-collection"></i></div>
            <div class="summary-value"><?= $total ?></div>
            <div class="summary-label">Solicitudes</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="bi bi-check-circle"></i></div>
            <div class="summary-value"><?= $aprobadas ?></div>
            <div class="summary-label">Aprobadas</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="summary-value"><?= $pendientes ?></div>
            <div class="summary-label">Pendientes</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="bi bi-alarm"></i></div>
            <div class="summary-value"><?= number_format($horas, 2) ?></div>
            <div class="summary-label">Total Horas</div>
        </div>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="main-card">
        <div class="main-title"><i class="bi bi-clock-history"></i>Historial de Horas Extra</div>
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> text-center mb-4"><?= $mensaje ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horas Extra</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Justificar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasData = false;
                    while ($row = $resultHistorial->fetch_assoc()):
                        $hasData = true;
                        $estado = strtolower($row['estado']);
                        $badge = 'badge-pendiente';
                        if ($estado == 'aprobado') $badge = 'badge-aprobado';
                        elseif ($estado == 'rechazado') $badge = 'badge-rechazado';

                        $horasExtraDecimal = (float) $row['cantidad_horas'];
                        $horas = floor($horasExtraDecimal);
                        $minutos = ($horasExtraDecimal - $horas) * 60;
                        if ($minutos > 30) $horas += 1;
                        $horasExtra = $horas > 0 ? $horas . ' hora(s)' : 'Menos de 1 hora';
                    ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['Fecha'])) ?></td>
                            <td><?= $horasExtra ?></td>
                            <td><?= htmlspecialchars($row['Motivo']) ?: '<span class="text-muted">Sin justificar</span>' ?></td>
                            <td>
                                <span class="badge <?= $badge ?>">
                                    <?= ucfirst($row['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (empty($row['Motivo'])): ?>
                                    <button type="button" class="btn btn-app btn-sm" data-bs-toggle="modal" data-bs-target="#justificacionModal" data-fecha="<?= htmlspecialchars($row['Fecha']) ?>">
                                        <i class="bi bi-pencil"></i> Justificar
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                        <i class="bi bi-check-circle"></i> Enviada
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile;
                    if (!$hasData): ?>
                        <tr>
                            <td colspan="5" class="no-data-row">No hay horas extra registradas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL JUSTIFICACIÓN -->
<div class="modal fade" id="justificacionModal" tabindex="-1" aria-labelledby="justificacionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Justificar Horas Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fecha" id="fechaJustificacion">
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" maxlength="200" required placeholder="Describe el motivo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="justificar_horas_extra" class="btn btn-app">Enviar</button>
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
        document.getElementById('motivo').value = '';
    });
</script>
</body>
</html>
<?php $conn->close(); ?>

