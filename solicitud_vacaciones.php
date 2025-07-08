<?php
include 'header.php';
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;
$mensaje = '';
$tipoMensaje = '';
$dias_disponibles = 0;

// 1. Obtener fecha de ingreso del colaborador
$sql = "SELECT fecha_ingreso FROM colaborador WHERE idColaborador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($fecha_ingreso);
$stmt->fetch();
$stmt->close();

if ($fecha_ingreso) {
    // Calcular días generados desde la fecha de ingreso según ley (12 días por año, proporcional)
    $fecha_inicio_laboral = new DateTime($fecha_ingreso);
    $fecha_hoy = new DateTime();
    $diff = $fecha_inicio_laboral->diff($fecha_hoy);
    $anios = $diff->y;
    $meses = $diff->m;
    $dias = $diff->d;

    $dias_generados = $anios * 12;
    $dias_generados += floor(($meses + $dias / 30) * (12 / 12));

    // Días ya usados (de la tabla permisos, solo si es tipo vacaciones)
    $sql = "SELECT SUM(DATEDIFF(fecha_fin, fecha_inicio) + 1) FROM permisos 
            WHERE id_colaborador_fk = ? 
              AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion = 'Vacaciones')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $colaborador_id);
    $stmt->execute();
    $stmt->bind_result($dias_usados);
    $stmt->fetch();
    $stmt->close();

    $dias_usados = $dias_usados ?? 0;

    // Vacaciones disponibles reales (solo enteros y nunca negativo)
    $dias_disponibles = max(0, intval($dias_generados - $dias_usados));
} else {
    $mensaje = "No se encontró la fecha de ingreso. Contacte a RRHH.";
    $tipoMensaje = 'danger';
}

// 2. Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $dias_disponibles > 0) {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');

    $fechaHoy = date('Y-m-d');
    $dias_nuevos = 0;
    if ($fecha_inicio && $fecha_fin) {
        $dias_nuevos = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 + 1;
    }

    // Obtener el id_tipo_permiso_fk correspondiente a "Vacaciones"
    $stmt = $conn->prepare("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion = 'Vacaciones' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($tipo_vacaciones_id);
    $stmt->fetch();
    $stmt->close();

    // Obtener el id_estado_fk (pendiente)
    $stmt = $conn->prepare("SELECT idEstado FROM estado_cat WHERE Descripcion = 'Pendiente' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($estado_pendiente_id);
    $stmt->fetch();
    $stmt->close();

    if ($fecha_inicio < $fechaHoy || $fecha_fin < $fechaHoy) {
        $mensaje = "No puedes solicitar vacaciones en fechas anteriores a hoy.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser menor que la de inicio.";
        $tipoMensaje = 'danger';
    } elseif ($dias_nuevos > $dias_disponibles) {
        $mensaje = "No tienes suficientes días disponibles para esta solicitud.";
        $tipoMensaje = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO permisos (
            id_colaborador_fk, 
            id_tipo_permiso_fk, 
            id_estado_fk, 
            fecha_solicitud, 
            fecha_inicio, 
            fecha_fin, 
            motivo, 
            observaciones, 
            comprobante_url
        ) VALUES (?, ?, ?, NOW(), ?, ?, 'Vacaciones', ?, '')");
        $stmt->bind_param(
            "iiisss", 
            $colaborador_id, 
            $tipo_vacaciones_id, 
            $estado_pendiente_id, 
            $fecha_inicio, 
            $fecha_fin, 
            $comentario
        );
        if ($stmt->execute()) {
            $mensaje = "✅ Solicitud enviada correctamente. ¡Que disfrutes tus vacaciones!";
            $tipoMensaje = 'success';
            $dias_disponibles -= $dias_nuevos;
        } else {
            $mensaje = "Error al enviar la solicitud.";
            $tipoMensaje = 'danger';
        }
        $stmt->close();
    }
}

// 3. Historial de vacaciones (de permisos)
$historial = [];
$sql = "SELECT fecha_inicio, fecha_fin, DATEDIFF(fecha_fin, fecha_inicio) + 1 as cantidad, observaciones 
        FROM permisos 
        WHERE id_colaborador_fk = ? 
          AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion = 'Vacaciones')
        ORDER BY fecha_inicio DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($h_inicio, $h_fin, $h_cantidad, $h_obs);
while ($stmt->fetch()) {
    $historial[] = [
        'inicio' => $h_inicio,
        'fin' => $h_fin,
        'cantidad' => intval($h_cantidad),
        'obs' => $h_obs
    ];
}
$stmt->close();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    body { background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important; }
    .center-container { min-height: 95vh; display: flex; align-items: center; justify-content: center; }
    .form-card { background: #fff; border-radius: 2.3rem; box-shadow: 0 8px 48px 0 rgba(44,62,80,.14); padding: 3.7rem 2.6rem 2.5rem 2.6rem; max-width: 540px; width: 100%; animation: fadeInDown 0.9s;}
    @keyframes fadeInDown { 0% { opacity: 0; transform: translateY(-25px);} 100% { opacity: 1; transform: translateY(0);} }
    .form-title { font-weight: 900; font-size: 2.2rem; margin-bottom: 1.3rem; color: #232d3b; display: flex; align-items: center; justify-content: center; letter-spacing: .3px; gap: 0.8rem;}
    .form-title i { color: #23b6ff; font-size: 2.7rem;}
    .vacaciones-disponibles { background: linear-gradient(90deg, #eaf9ff 70%, #d3f1ff 100%); color: #0a5487; font-weight: 600; padding: .9rem 1.1rem; border-radius: 1.1rem; margin-bottom: 1.4rem; font-size: 1.21rem; display: flex; align-items: center; gap: .7rem; justify-content: center; border: 1.5px solid #38b6ff22;}
    .vacaciones-disponibles i { font-size: 1.5rem; color: #38b6ff; }
    .input-group-text { background: #f6faff; border: none; color: #23b6ff; font-size: 1.3rem; border-radius: 1.1rem 0 0 1.1rem;}
    .form-control { font-size: 1.17rem; padding: .97rem .9rem; border-radius: 0 1.1rem 1.1rem 0;}
    .form-control:focus { border-color: #23b6ff; box-shadow: 0 0 0 2px #2494ff26;}
    .dias-a-solicitar { background: #f8fbfe; border-radius: .8rem; color: #266c9c; padding: .45rem .7rem; font-size: 1.12rem; margin-bottom: 1.2rem; font-weight: 500; display: flex; align-items: center; gap: .5rem;}
    .dias-a-solicitar i { color: #23b6ff; }
    .btn-app { background: linear-gradient(90deg, #23b6ff 75%, #47d9fd 100%); color: #fff; font-weight: 700; font-size: 1.17rem; padding: 1.07rem 0; border-radius: 1.3rem; box-shadow: 0 2px 12px #23b6ff23; transition: background .17s, box-shadow .17s; width: 100%; margin-top: 10px; letter-spacing: .7px;}
    .btn-app:hover { background: linear-gradient(90deg, #47d9fd 15%, #23b6ff 100%); color: #fff; box-shadow: 0 8px 24px #23b6ff22; }
    .alert { font-size: 1.07rem; margin-bottom: 1.45rem; border-radius: 1.1rem;}
    .historial-title { font-weight: 700; color: #1976d2; margin-top: 2.5rem; font-size: 1.2rem; margin-bottom: .9rem;}
    .table-vacaciones { border-radius: 1.1rem; overflow: hidden; background: #f6f9fd;}
    .table-vacaciones th { background: #f3faff; color: #288cc8;}
    .table-vacaciones td, .table-vacaciones th { font-size: 1rem; padding: 0.75rem;}
    @media (max-width:600px){ .form-card { max-width: 98vw; padding: 2.2rem 1.1rem 1.5rem 1.1rem; } .form-title { font-size: 1.3rem;} .table-vacaciones td, .table-vacaciones th { font-size: .9rem; padding: .45rem;}}
</style>

<div class="center-container">
    <div>
        <div class="form-card">
            <div class="form-title animate__animated animate__fadeInDown">
                <i class="bi bi-umbrella-beach"></i> Solicitar Vacaciones
            </div>
            <div class="vacaciones-disponibles animate__animated animate__fadeInDown">
                <i class="bi bi-info-circle"></i> <span>Vacaciones disponibles:</span> <span id="diasDisponibles"><?= $dias_disponibles ?></span> <span>días</span>
            </div>
            <div class="dias-a-solicitar" id="diasASolicitarBox" style="display:none;">
                <i class="bi bi-calendar-range"></i>
                <span>Días a solicitar: <b id="diasASolicitar">0</b></span>
            </div>
            <div id="msg-php">
                <?php if($mensaje): ?>
                    <div class="alert alert-<?= $tipoMensaje ?> text-center animate__animated animate__fadeInDown" id="php-alert">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
            </div>
            <form method="post" class="needs-validation" id="vacacionesForm" novalidate autocomplete="off">
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
                    <input type="text" name="comentario" id="comentario" class="form-control" placeholder="Comentario (opcional)" maxlength="120" data-bs-toggle="tooltip" title="Explica el motivo o comentario">
                </div>
                <button type="submit" id="btnEnviar" class="btn btn-app mt-1" <?= $dias_disponibles <= 0 ? 'disabled' : '' ?>>
                    <span id="btnText"><i class="bi bi-send"></i> Enviar Solicitud</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </form>
        </div>

        <!-- HISTORIAL SIEMPRE VISIBLE -->
        <div class="historial-title mt-5 mb-2 animate__animated animate__fadeInDown">
            <i class="bi bi-clock-history"></i> Historial de vacaciones
        </div>
        <div class="table-responsive">
            <table class="table table-vacaciones table-bordered text-center">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días solicitados</th>
                        <th>Comentario</th>
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
                        </tr>
                        <?php endforeach ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-muted">No hay solicitudes de vacaciones registradas.</td>
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
const diasASolicitarBox = document.getElementById('diasASolicitarBox');
const diasASolicitar = document.getElementById('diasASolicitar');
const diasDisponibles = parseInt(document.getElementById('diasDisponibles').textContent);

function calcularDias() {
    const inicio = fechaInicio.value;
    const fin = fechaFin.value;
    if (inicio && fin && fin >= inicio) {
        const dias = (new Date(fin) - new Date(inicio)) / 86400000 + 1;
        diasASolicitar.textContent = dias;
        diasASolicitarBox.style.display = 'flex';
        // Color si sobrepasa disponibles
        if (dias > diasDisponibles) {
            diasASolicitarBox.style.background = '#ffe8e8';
            diasASolicitarBox.style.color = '#a61111';
        } else {
            diasASolicitarBox.style.background = '#f8fbfe';
            diasASolicitarBox.style.color = '#266c9c';
        }
    } else {
        diasASolicitarBox.style.display = 'none';
    }
}

fechaInicio.addEventListener('change', function() {
    fechaFin.min = fechaInicio.value;
    if(fechaFin.value < fechaInicio.value) fechaFin.value = fechaInicio.value;
    calcularDias();
});
fechaFin.addEventListener('change', calcularDias);

// Validación Bootstrap + feedback instantáneo
(() => {
  'use strict';
  const form = document.getElementById('vacacionesForm');
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
