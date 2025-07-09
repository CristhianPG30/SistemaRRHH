<?php
include 'header.php';
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;
$mensaje = '';
$tipoMensaje = '';

// --- Catálogo de tipos de permiso (solo los permitidos para esta sección, no vacaciones ni incapacidades) ---
$tipos = [];
$res_tipos = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat WHERE LOWER(Descripcion) NOT IN ('vacaciones','incapacidad','incapacidades')");
while($row = $res_tipos->fetch_assoc()) {
    $tipos[] = $row;
}

// --- ID estado pendiente ---
$estado_pendiente = 3; // según tu tabla estado_cat

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $id_tipo_permiso = intval($_POST['id_tipo_permiso'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fechaHoy = date('Y-m-d');
    $archivo = '';

    // Validación
    if ($fecha_inicio < $fechaHoy || $fecha_fin < $fechaHoy) {
        $mensaje = "No puedes solicitar permisos en fechas anteriores a hoy.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser menor que la de inicio.";
        $tipoMensaje = 'danger';
    } elseif (empty($motivo)) {
        $mensaje = "Debes especificar un motivo.";
        $tipoMensaje = 'danger';
    } elseif (!$id_tipo_permiso) {
        $mensaje = "Debes seleccionar un tipo de permiso.";
        $tipoMensaje = 'danger';
    } else {
        // Manejar archivo
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $nombre_archivo = 'permiso_' . time() . '_' . rand(100,999) . '.' . $ext;
            $ruta_destino = "uploads/" . $nombre_archivo;
            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_destino)) {
                $archivo = $nombre_archivo;
            }
        }
        $sql = "INSERT INTO permisos (id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_solicitud, fecha_inicio, fecha_fin, motivo, observaciones, comprobante_url)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiisssss", $colaborador_id, $id_tipo_permiso, $estado_pendiente, $fecha_inicio, $fecha_fin, $motivo, $observaciones, $archivo);
        if ($stmt->execute()) {
            $mensaje = "✅ Permiso solicitado correctamente.";
            $tipoMensaje = 'success';
        } else {
            $mensaje = "Error al registrar el permiso.";
            $tipoMensaje = 'danger';
        }
        $stmt->close();
    }
}

// --- Historial de permisos del colaborador (excluye vacaciones e incapacidad) ---
$historial = [];
$sql = "SELECT fecha_inicio, fecha_fin, motivo, observaciones, comprobante_url, id_tipo_permiso_fk, id_estado_fk 
        FROM permisos 
        WHERE id_colaborador_fk = ? 
          AND id_tipo_permiso_fk IN (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion) NOT IN ('vacaciones','incapacidad','incapacidades'))
        ORDER BY fecha_inicio DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($h_inicio, $h_fin, $h_motivo, $h_obs, $h_archivo, $h_tipo, $h_estado);
while ($stmt->fetch()) {
    $historial[] = [
        'inicio' => $h_inicio,
        'fin' => $h_fin,
        'motivo' => $h_motivo,
        'obs' => $h_obs,
        'archivo' => $h_archivo,
        'tipo' => $h_tipo,
        'estado' => $h_estado
    ];
}
$stmt->close();

// --- Mostrar tipo de permiso ---
function nombreTipoPermiso($id, $tipos) {
    foreach($tipos as $tipo) {
        if ($tipo['idTipoPermiso'] == $id) return $tipo['Descripcion'];
    }
    return 'Desconocido';
}

// --- Badge color estado ---
function estadoBadge($id) {
    switch ($id) {
        case 3: return '<span class="badge bg-warning text-dark">Pendiente</span>';
        case 4: return '<span class="badge bg-success">Aprobado</span>';
        case 5: return '<span class="badge bg-danger">Rechazado</span>';
        default: return '<span class="badge bg-secondary">Desconocido</span>';
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    body { background: linear-gradient(135deg, #f5faff 0%, #f4f7fc 100%) !important; }
    .center-container { min-height: 95vh; display: flex; align-items: center; justify-content: center; }
    .form-card { background: #fff; border-radius: 2.3rem; box-shadow: 0 8px 48px 0 rgba(44,62,80,.14); padding: 3.2rem 2.1rem 2rem 2.1rem; max-width: 540px; width: 100%; animation: fadeInDown 0.9s;}
    @keyframes fadeInDown { 0% { opacity: 0; transform: translateY(-25px);} 100% { opacity: 1; transform: translateY(0);} }
    .form-title { font-weight: 900; font-size: 2.1rem; margin-bottom: 1.1rem; color: #233b4b; display: flex; align-items: center; justify-content: center; gap: 0.7rem;}
    .form-title i { color: #399bf7; font-size: 2.5rem;}
    .input-group-text { background: #f6faff; border: none; color: #399bf7; font-size: 1.2rem; border-radius: 1.1rem 0 0 1.1rem;}
    .form-control { font-size: 1.13rem; padding: .9rem .8rem; border-radius: 0 1.1rem 1.1rem 0;}
    .form-control:focus { border-color: #399bf7; box-shadow: 0 0 0 2px #399bf727;}
    .btn-app { background: linear-gradient(90deg, #399bf7 75%, #72d6fd 100%); color: #fff; font-weight: 700; font-size: 1.15rem; padding: 1.03rem 0; border-radius: 1.3rem; box-shadow: 0 2px 12px #399bf723; transition: background .17s, box-shadow .17s; width: 100%; margin-top: 10px; letter-spacing: .7px;}
    .btn-app:hover { background: linear-gradient(90deg, #72d6fd 15%, #399bf7 100%); color: #fff; box-shadow: 0 8px 24px #399bf722; }
    .alert { font-size: 1.05rem; margin-bottom: 1.3rem; border-radius: 1.1rem;}
    .historial-title { font-weight: 700; color: #166cc2; margin-top: 2.5rem; font-size: 1.18rem; margin-bottom: .7rem;}
    .table-permisos { border-radius: 1.1rem; overflow: hidden; background: #f8fbfd;}
    .table-permisos th { background: #f0faff; color: #238cc8;}
    .table-permisos td, .table-permisos th { font-size: .98rem; padding: 0.73rem;}
    @media (max-width:600px){ .form-card { max-width: 98vw; padding: 2.2rem 1.1rem 1.5rem 1.1rem; } .form-title { font-size: 1.2rem;} .table-permisos td, .table-permisos th { font-size: .87rem; padding: .43rem;}}
</style>

<div class="center-container">
    <div>
        <div class="form-card">
            <div class="form-title animate__animated animate__fadeInDown">
                <i class="bi bi-calendar-check"></i> Solicitar Permiso
            </div>
            <div id="msg-php">
                <?php if($mensaje): ?>
                    <div class="alert alert-<?= $tipoMensaje ?> text-center animate__animated animate__fadeInDown" id="php-alert">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="needs-validation" id="permisoForm" novalidate autocomplete="off">
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-list-ul"></i></span>
                    <select name="id_tipo_permiso" id="id_tipo_permiso" class="form-control" required>
                        <option value="">Seleccione tipo de permiso</option>
                        <?php foreach($tipos as $tipo): ?>
                            <option value="<?= $tipo['idTipoPermiso'] ?>"><?= htmlspecialchars($tipo['Descripcion']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="invalid-feedback">Tipo requerido.</div>
                </div>
                <div class="row gx-2 gy-2">
                    <div class="col-6 input-group mb-4">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Fecha inicio requerida.</div>
                    </div>
                    <div class="col-6 input-group mb-4">
                        <span class="input-group-text"><i class="bi bi-calendar-event-fill"></i></span>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Fecha fin requerida.</div>
                    </div>
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-pen"></i></span>
                    <input type="text" name="motivo" id="motivo" class="form-control" placeholder="Motivo del permiso" maxlength="120" required>
                    <div class="invalid-feedback">Motivo requerido.</div>
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                    <input type="text" name="observaciones" id="observaciones" class="form-control" placeholder="Observaciones (opcional)" maxlength="200">
                </div>
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="bi bi-paperclip"></i></span>
                    <input type="file" name="comprobante" id="comprobante" class="form-control" accept=".pdf,.jpg,.png,.jpeg">
                </div>
                <button type="submit" id="btnEnviar" class="btn btn-app mt-1">
                    <span id="btnText"><i class="bi bi-send"></i> Enviar Solicitud</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </form>
        </div>
        <div class="historial-title mt-5 mb-2 animate__animated animate__fadeInDown">
            <i class="bi bi-clock-history"></i> Historial de permisos
        </div>
        <div class="table-responsive">
            <table class="table table-permisos table-bordered text-center">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Motivo</th>
                        <th>Observaciones</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historial)): ?>
                        <?php foreach ($historial as $row): ?>
                        <tr>
                            <td><?= nombreTipoPermiso($row['tipo'], $tipos) ?></td>
                            <td><?= $row['inicio'] ? date('d/m/Y', strtotime($row['inicio'])) : '-' ?></td>
                            <td><?= $row['fin'] ? date('d/m/Y', strtotime($row['fin'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['motivo']) ?></td>
                            <td><?= htmlspecialchars($row['obs']) ?></td>
                            <td><?= estadoBadge($row['estado']) ?></td>
                            <td>
                                <?php if ($row['archivo']): ?>
                                    <a href="uploads/<?= htmlspecialchars($row['archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-arrow-down"></i> Ver</a>
                                <?php else: ?>
                                    -
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-muted">No hay permisos registrados.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const fechaInicio = document.getElementById('fecha_inicio');
const fechaFin = document.getElementById('fecha_fin');
fechaInicio.addEventListener('change', function() {
    fechaFin.min = fechaInicio.value;
    if(fechaFin.value < fechaInicio.value) fechaFin.value = fechaInicio.value;
});
fechaFin.addEventListener('change', function() {
    if(fechaFin.value < fechaInicio.value) fechaFin.value = fechaInicio.value;
});
// Bootstrap validation + spinner
(() => {
  'use strict';
  const form = document.getElementById('permisoForm');
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
