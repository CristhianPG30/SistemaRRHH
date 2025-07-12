<?php
include 'header.php';
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$mensaje = '';
$tipoMensaje = '';

// Catálogo de tipos de permiso
$tipos = [];
$res_tipos = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat WHERE LOWER(Descripcion) NOT IN ('vacaciones','incapacidad','incapacidades')");
while($row = $res_tipos->fetch_assoc()) {
    $tipos[] = $row;
}

$res_estado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion) = 'pendiente' LIMIT 1");
$estado_pendiente_id = $res_estado->fetch_assoc()['idEstado'] ?? 3;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $es_por_horas = isset($_POST['por_horas']);
    $fecha_fin = $es_por_horas ? $fecha_inicio : ($_POST['fecha_fin'] ?? '');
    
    // --- INICIO DE LA CORRECCIÓN ---
    // Se asigna '00:00:00' como valor por defecto en lugar de null.
    $hora_inicio = $es_por_horas ? ($_POST['hora_inicio'] ?? '00:00:00') : '00:00:00';
    $hora_fin = $es_por_horas ? ($_POST['hora_fin'] ?? '00:00:00') : '00:00:00';
    // --- FIN DE LA CORRECCIÓN ---
    
    $motivo = trim($_POST['motivo'] ?? '');
    $id_tipo_permiso = intval($_POST['id_tipo_permiso'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $archivo_url = '';
    $fechaHoy = date('Y-m-d');

    // Validaciones
    if (empty($fecha_inicio) || empty($fecha_fin) || empty($motivo) || empty($id_tipo_permiso) || ($es_por_horas && (empty($_POST['hora_inicio']) || empty($_POST['hora_fin'])))) {
        $mensaje = "Debes completar todos los campos obligatorios.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_inicio < $fechaHoy) {
        $mensaje = "No puedes solicitar permisos para fechas pasadas.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser anterior a la de inicio.";
        $tipoMensaje = 'danger';
    } elseif ($es_por_horas && !empty($_POST['hora_inicio']) && !empty($_POST['hora_fin']) && $_POST['hora_fin'] <= $_POST['hora_inicio']) {
        $mensaje = "La hora de fin debe ser posterior a la hora de inicio.";
        $tipoMensaje = 'danger';
    } else {
        $check_sql = "SELECT id_colaborador_fk FROM permisos WHERE id_colaborador_fk = ? AND fecha_inicio = ? AND id_estado_fk != 5";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $colaborador_id, $fecha_inicio);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $mensaje = "Ya tienes un permiso registrado para esa fecha de inicio.";
            $tipoMensaje = 'danger';
        } else {
            if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == UPLOAD_ERR_OK) {
                $carpeta_destino = "uploads/permisos/";
                if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0777, true);
                $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
                $nombre_archivo = 'permiso_' . $colaborador_id . '_' . time() . '.' . $ext;
                $ruta_destino = $carpeta_destino . $nombre_archivo;
                if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_destino)) {
                    $archivo_url = $ruta_destino;
                }
            }
            
            $sql = "INSERT INTO permisos (id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_solicitud, fecha_inicio, fecha_fin, motivo, observaciones, comprobante_url, hora_inicio, hora_fin)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiisssssss", $colaborador_id, $id_tipo_permiso, $estado_pendiente_id, $fecha_inicio, $fecha_fin, $motivo, $observaciones, $archivo_url, $hora_inicio, $hora_fin);
            
            if ($stmt->execute()) {
                $mensaje = "¡Permiso solicitado correctamente!";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error al registrar el permiso: " . $stmt->error;
                $tipoMensaje = 'danger';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Historial de permisos (sin cambios)
$historial = [];
$sql_historial = "SELECT p.fecha_inicio, p.fecha_fin, p.hora_inicio, p.hora_fin, p.motivo, p.observaciones, p.comprobante_url, tpc.Descripcion AS tipo_permiso, ec.Descripcion AS estado
                  FROM permisos p
                  JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                  JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
                  WHERE p.id_colaborador_fk = ? 
                  AND LOWER(tpc.Descripcion) NOT IN ('vacaciones','incapacidad','incapacidades')
                  ORDER BY p.fecha_inicio DESC";
$stmt_historial = $conn->prepare($sql_historial);
$stmt_historial->bind_param("i", $colaborador_id);
$stmt_historial->execute();
$result_historial = $stmt_historial->get_result();
while ($row = $result_historial->fetch_assoc()) {
    $historial[] = $row;
}
$stmt_historial->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Permiso - Edginton S.A.</title>
    <style>
        body { background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important; font-family: 'Poppins', sans-serif; }
        .main-container { max-width: 880px; margin: 48px auto 0; padding: 0 15px; }
        .main-card { background: #fff; border-radius: 2.1rem; box-shadow: 0 8px 38px 0 rgba(44,62,80,.12); padding: 2.2rem 2.1rem 1.7rem 2.1rem; margin-bottom: 2.2rem; animation: fadeInDown 0.9s; }
        .card-title-custom { font-size: 2.2rem; font-weight: 900; color: #1a3961; letter-spacing: .7px; margin-bottom: 0.5rem; display: flex; align-items: center; gap: .8rem; }
        .card-title-custom i { color: #3499ea; font-size: 2.2rem; }
        .form-label { color: #288cc8; font-weight: 600; }
        .form-control, .form-select { border-radius: 0.9rem; }
        .btn-submit-custom { background: linear-gradient(90deg, #1f8ff7 75%, #53e3fc 100%); color: #fff; font-weight: 700; font-size: 1.05rem; border-radius: 0.8rem; padding: .63rem 1.5rem; box-shadow: 0 2px 12px #1f8ff722; width: 100%; margin-top: 1rem; border: none; }
        .btn-submit-custom:hover { background: linear-gradient(90deg, #53e3fc 25%, #1f8ff7 100%); color: #fff; }
        .table-custom { background: #f8fafd; border-radius: 1.15rem; overflow: hidden; box-shadow: 0 4px 24px #23b6ff10; }
        .table-custom th { background: #e9f6ff; color: #288cc8; font-weight: 700; font-size: 1rem; }
        .table-custom td, .table-custom th { padding: 0.75rem 0.7rem; text-align: center; vertical-align: middle; }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-success { background-color: #01b87f !important; }
        .badge.bg-danger { background-color: #ff6565 !important; }
        .section-title { font-weight: 700; color: #1a3961; font-size: 1.4rem; margin-bottom: 1rem; text-align: center; }
        @media (max-width: 600px) { .main-card { padding: 1.1rem 0.3rem 0.9rem 0.3rem; } .card-title-custom { font-size: 1.3rem; } .table-custom th, .table-custom td { font-size: .85rem; padding: 0.4rem 0.3rem;} }
    </style>
</head>
<body>

<div class="main-container">
    <div class="main-card">
        <div class="card-title-custom animate__animated animate__fadeInDown">
            <i class="bi bi-calendar-plus-fill"></i> Solicitar Permiso
        </div>
        <p class="text-center mb-4">Registra aquí tus solicitudes de permisos.</p>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> text-center"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="permisoForm" novalidate>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Tipo de Permiso*</label>
                    <select name="id_tipo_permiso" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($tipos as $tipo): ?>
                            <option value="<?= $tipo['idTipoPermiso'] ?>"><?= htmlspecialchars($tipo['Descripcion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de Inicio*</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6" id="fechaFinContainer">
                    <label class="form-label">Fecha de Fin*</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="por_horas" id="porHorasCheck">
                        <label class="form-check-label" for="porHorasCheck">
                            Permiso por horas (en un solo día)
                        </label>
                    </div>
                </div>
                <div class="col-md-6" id="horaInicioContainer" style="display: none;">
                    <label class="form-label">Hora de Inicio*</label>
                    <input type="time" name="hora_inicio" id="hora_inicio" class="form-control">
                </div>
                <div class="col-md-6" id="horaFinContainer" style="display: none;">
                    <label class="form-label">Hora de Fin*</label>
                    <input type="time" name="hora_fin" id="hora_fin" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Motivo*</label>
                    <input type="text" name="motivo" class="form-control" placeholder="Ej: Cita médica, Asunto personal" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Observaciones (Opcional)</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Detalles adicionales..."></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Adjuntar Comprobante (Opcional)</label>
                    <input type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
            <button type="submit" class="btn btn-submit-custom">
                <i class="bi bi-send-fill"></i> Enviar Solicitud
            </button>
        </form>
    </div>

    <div class="main-card mt-4">
        <div class="section-title">
            <i class="bi bi-clock-history"></i> Historial de Permisos
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr><td colspan="6">No tienes permisos registrados.</td></tr>
                    <?php else: foreach ($historial as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['tipo_permiso']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                                <?= ($row['fecha_fin'] && $row['fecha_fin'] != $row['fecha_inicio']) ? ' al '.date('d/m/Y', strtotime($row['fecha_fin'])) : '' ?>
                            </td>
                            <td>
                                <?php if ($row['hora_inicio'] && $row['hora_inicio'] != '00:00:00'): ?>
                                    <span class="badge bg-info"><?= date('g:i A', strtotime($row['hora_inicio'])) ?> - <?= date('g:i A', strtotime($row['hora_fin'])) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Día completo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['motivo']) ?></td>
                            <td>
                                <?php
                                $estado_lower = strtolower($row['estado']);
                                if ($estado_lower == 'pendiente') echo '<span class="badge bg-warning">Pendiente</span>';
                                else if ($estado_lower == 'aprobado') echo '<span class="badge bg-success">Aprobado</span>';
                                else if ($estado_lower == 'rechazado') echo '<span class="badge bg-danger">Rechazado</span>';
                                else echo '<span class="badge bg-info">'.htmlspecialchars($row['estado']).'</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($row['comprobante_url']): ?>
                                    <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    const porHorasCheck = document.getElementById('porHorasCheck');
    const fechaFinContainer = document.getElementById('fechaFinContainer');
    const horaInicioContainer = document.getElementById('horaInicioContainer');
    const horaFinContainer = document.getElementById('horaFinContainer');
    const horaInicioInput = document.getElementById('hora_inicio');
    const horaFinInput = document.getElementById('hora_fin');

    fechaInicio.addEventListener('change', function() {
        if (!porHorasCheck.checked && fechaFin.value < fechaInicio.value) {
            fechaFin.value = fechaInicio.value;
        }
        fechaFin.min = fechaInicio.value;
    });

    porHorasCheck.addEventListener('change', function() {
        const esPorHoras = this.checked;
        fechaFinContainer.style.display = esPorHoras ? 'none' : 'block';
        horaInicioContainer.style.display = esPorHoras ? 'block' : 'none';
        horaFinContainer.style.display = esPorHoras ? 'block' : 'none';
        
        fechaFin.required = !esPorHoras;
        horaInicioInput.required = esPorHoras;
        horaFinInput.required = esPorHoras;

        if (esPorHoras) {
            fechaFin.value = fechaInicio.value;
        }
    });

    fechaInicio.addEventListener('input', function() {
        if (porHorasCheck.checked) {
            fechaFin.value = this.value;
        }
    });

    (() => {
        'use strict';
        const form = document.getElementById('permisoForm');
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    })();
</script>

</body>
</html>