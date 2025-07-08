<?php
include 'header.php';
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;
$mensaje = '';
$tipoMensaje = '';

// Procesar solicitud de incapacidad
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    $fechaHoy = date('Y-m-d');
    $file_url = '';

    // Manejo del archivo comprobante (opcional)
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == UPLOAD_ERR_OK) {
        $carpeta_destino = "uploads/comprobantes/";
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0777, true);
        }
        $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
        $file_url = $carpeta_destino . uniqid('incapacidad_') . "." . $ext;
        move_uploaded_file($_FILES['comprobante']['tmp_name'], $file_url);
    }

    // Obtener el id_tipo_permiso_fk correspondiente a "Incapacidad"
    $stmt = $conn->prepare("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion = 'Incapacidad' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($tipo_incapacidad_id);
    $stmt->fetch();
    $stmt->close();

    // Obtener el id_estado_fk (pendiente)
    $stmt = $conn->prepare("SELECT idEstado FROM estado_cat WHERE Descripcion = 'Pendiente' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($estado_pendiente_id);
    $stmt->fetch();
    $stmt->close();

    // Validaciones
    $dias_nuevos = 0;
    if ($fecha_inicio && $fecha_fin) {
        $dias_nuevos = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 + 1;
    }
    if ($fecha_inicio < $fechaHoy || $fecha_fin < $fechaHoy) {
        $mensaje = "No puedes solicitar incapacidad en fechas anteriores a hoy.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser menor que la de inicio.";
        $tipoMensaje = 'danger';
    } elseif ($dias_nuevos <= 0) {
        $mensaje = "La cantidad de días debe ser mayor a 0.";
        $tipoMensaje = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO permisos (
            id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_solicitud, fecha_inicio, fecha_fin, motivo, observaciones, comprobante_url
        ) VALUES (?, ?, ?, NOW(), ?, ?, 'Incapacidad', ?, ?)");
        $stmt->bind_param(
            "iiissss",
            $colaborador_id,
            $tipo_incapacidad_id,
            $estado_pendiente_id,
            $fecha_inicio,
            $fecha_fin,
            $comentario,
            $file_url
        );
        if ($stmt->execute()) {
            $mensaje = "✅ Solicitud de incapacidad enviada correctamente.";
            $tipoMensaje = 'success';
        } else {
            $mensaje = "Error al enviar la solicitud.";
            $tipoMensaje = 'danger';
        }
        $stmt->close();
    }
}

// 2. Historial de incapacidades (de permisos)
$historial = [];
$sql = "SELECT fecha_inicio, fecha_fin, DATEDIFF(fecha_fin, fecha_inicio) + 1 as cantidad, observaciones, comprobante_url, id_estado_fk
        FROM permisos
        WHERE id_colaborador_fk = ?
          AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion = 'Incapacidad')
        ORDER BY fecha_inicio DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($h_inicio, $h_fin, $h_cantidad, $h_obs, $h_comprobante, $h_estado);
while ($stmt->fetch()) {
    $historial[] = [
        'inicio' => $h_inicio,
        'fin' => $h_fin,
        'cantidad' => intval($h_cantidad),
        'obs' => $h_obs,
        'comprobante' => $h_comprobante,
        'estado' => $h_estado
    ];
}
$stmt->close();

// Opcional: Obtener lista de estados para mostrar texto en vez de id_estado_fk
$estados = [];
$resEstados = $conn->query("SELECT idEstado, Descripcion FROM estado_cat");
while ($row = $resEstados->fetch_assoc()) {
    $estados[$row['idEstado']] = $row['Descripcion'];
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    body { background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important; }
    .center-container { min-height: 95vh; display: flex; align-items: center; justify-content: center; }
    .form-card { background: #fff; border-radius: 2.3rem; box-shadow: 0 8px 48px 0 rgba(44,62,80,.14); padding: 3.7rem 2.6rem 2.5rem 2.6rem; max-width: 540px; width: 100%; animation: fadeInDown 0.9s;}
    @keyframes fadeInDown { 0% { opacity: 0; transform: translateY(-25px);} 100% { opacity: 1; transform: translateY(0);} }
    .form-title { font-weight: 900; font-size: 2.2rem; margin-bottom: 1.3rem; color: #232d3b; display: flex; align-items: center; justify-content: center; letter-spacing: .3px; gap: 0.8rem;}
    .form-title i { color: #0abb87; font-size: 2.7rem;}
    .input-group-text { background: #f6faff; border: none; color: #23b6ff; font-size: 1.3rem; border-radius: 1.1rem 0 0 1.1rem;}
    .form-control { font-size: 1.17rem; padding: .97rem .9rem; border-radius: 0 1.1rem 1.1rem 0;}
    .form-control:focus { border-color: #23b6ff; box-shadow: 0 0 0 2px #2494ff26;}
    .btn-app { background: linear-gradient(90deg, #0abb87 75%, #00c6c6 100%); color: #fff; font-weight: 700; font-size: 1.17rem; padding: 1.07rem 0; border-radius: 1.3rem; box-shadow: 0 2px 12px #0abb8723; transition: background .17s, box-shadow .17s; width: 100%; margin-top: 10px; letter-spacing: .7px;}
    .btn-app:hover { background: linear-gradient(90deg, #00c6c6 15%, #0abb87 100%); color: #fff; box-shadow: 0 8px 24px #0abb8722; }
    .alert { font-size: 1.07rem; margin-bottom: 1.45rem; border-radius: 1.1rem;}
    .historial-title { font-weight: 700; color: #1976d2; margin-top: 2.5rem; font-size: 1.2rem; margin-bottom: .9rem;}
    .table-incapacidad { border-radius: 1.1rem; overflow: hidden; background: #f6f9fd;}
    .table-incapacidad th { background: #f3faff; color: #0abb87;}
    .table-incapacidad td, .table-incapacidad th { font-size: 1rem; padding: 0.75rem;}
    @media (max-width:600px){ .form-card { max-width: 98vw; padding: 2.2rem 1.1rem 1.5rem 1.1rem; } .form-title { font-size: 1.3rem;} .table-incapacidad td, .table-incapacidad th { font-size: .9rem; padding: .45rem;}}
</style>

<div class="center-container">
    <div>
        <div class="form-card">
            <div class="form-title animate__animated animate__fadeInDown">
                <i class="bi bi-emoji-dizzy"></i> Solicitar Incapacidad
            </div>
            <div id="msg-php">
                <?php if($mensaje): ?>
                    <div class="alert alert-<?= $tipoMensaje ?> text-center animate__animated animate__fadeInDown" id="php-alert">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
            </div>
            <form method="post" class="needs-validation" id="incapacidadForm" novalidate autocomplete="off" enctype="multipart/form-data">
                <div class="row gx-2 gy-2">
                    <div class="col-6 input-group mb-4">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required data-bs-toggle="tooltip" title="Fecha de inicio">
                        <div class="invalid-feedback">Fecha inicio requerida.</div>
                    </div>
                    <div class="col-6 input-group mb-4">
                        <span class="input-group-text"><i class="bi bi-calendar-event-fill"></i></span>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required data-bs-toggle="tooltip" title="Fecha de fin">
                        <div class="invalid-feedback">Fecha fin requerida.</div>
                    </div>
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                    <input type="text" name="comentario" id="comentario" class="form-control" placeholder="Motivo o comentario" maxlength="120" data-bs-toggle="tooltip" title="Explica el motivo o comentario">
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-paperclip"></i></span>
                    <input type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-bs-toggle="tooltip" title="Subir comprobante (PDF, imagen, doc)">
                </div>
                <button type="submit" id="btnEnviar" class="btn btn-app mt-1">
                    <span id="btnText"><i class="bi bi-send"></i> Enviar Solicitud</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </form>
        </div>

        <!-- HISTORIAL SIEMPRE VISIBLE -->
        <div class="historial-title mt-5 mb-2 animate__animated animate__fadeInDown">
            <i class="bi bi-clock-history"></i> Historial de incapacidades
        </div>
        <div class="table-responsive">
            <table class="table table-incapacidad table-bordered text-center">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días</th>
                        <th>Comentario</th>
                        <th>Comprobante</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historial)): ?>
                        <?php foreach ($historial as $row): ?>
                        <tr>
                            <td><?= $row['inicio'] ? date('d/m/Y', strtotime($row['inicio'])) : '-' ?></td>
                            <td><?= $row['fin'] ? date('d/m/Y', strtotime($row['fin'])) : '-' ?></td>
                            <td><?= $row['cantidad'] ?? '-' ?></td>
                            <td><?= htmlspecialchars($row['obs']) ?></td>
                            <td>
                                <?php if ($row['comprobante']): ?>
                                    <a href="<?= htmlspecialchars($row['comprobante']) ?>" target="_blank" class="btn btn-outline-info btn-sm">Ver</a>
                                <?php else: ?>
                                    <span class="text-muted">Sin archivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= 
                                    (isset($estados[$row['estado']]) && $estados[$row['estado']] == 'Aprobado') ? 'success' : (
                                        (isset($estados[$row['estado']]) && $estados[$row['estado']] == 'Rechazado') ? 'danger' : 'secondary'
                                    );
                                ?>">
                                    <?= isset($estados[$row['estado']]) ? $estados[$row['estado']] : 'Pendiente' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-muted">No hay solicitudes de incapacidad registradas.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.js"></script>
<script>
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });
const fechaInicio = document.getElementById('fecha_inicio');
const fechaFin = document.getElementById('fecha_fin');
fechaInicio.addEventListener('change', function() {
    fechaFin.min = fechaInicio.value;
    if(fechaFin.value < fechaInicio.value) fechaFin.value = fechaInicio.value;
});
(() => {
  'use strict';
  const form = document.getElementById('incapacidadForm');
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity() || (fechaFin.value && fechaInicio.value && fechaFin.value < fechaInicio.value)) {
      event.preventDefault();
      event.stopPropagation();
      if (fechaFin.value && fechaInicio.value && fechaFin.value < fechaInicio.value) {
          fechaFin.classList.add('is-invalid');
      }
    } else {
      document.getElementById('btnText').classList.add('d-none');
      document.getElementById('btnSpinner').classList.remove('d-none');
    }
    form.classList.add('was-validated');
  }, false);
})();
</script>
