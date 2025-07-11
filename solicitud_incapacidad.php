<?php
include 'header.php';
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Establecer la zona horaria para asegurar que la fecha del servidor sea la correcta
date_default_timezone_set('America/Costa_Rica');

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$mensaje = '';
$tipoMensaje = '';

// Definir fecha límite para los calendarios
$fechaHoy = date('Y-m-d');

// Procesar solicitud de incapacidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fecha_inicio'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $comentario = trim($_POST['comentario'] ?? '');
    
    // --- VALIDACIONES DEL LADO DEL SERVIDOR EN ORDEN CORRECTO---
    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        $mensaje = "Debes adjuntar un comprobante válido.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_inicio > $fechaHoy) {
        $mensaje = "No puedes registrar una incapacidad para una fecha futura.";
        $tipoMensaje = 'danger';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser anterior a la de inicio.";
        $tipoMensaje = 'danger';
    } else {
        // Se verifica si el rango de fechas solicitado se cruza con un permiso existente.
        $check_sql = "SELECT id_colaborador_fk FROM permisos 
                      WHERE id_colaborador_fk = ? 
                      AND id_estado_fk != 5 -- Excluir rechazados
                      AND (
                          (? BETWEEN fecha_inicio AND fecha_fin) OR 
                          (? BETWEEN fecha_inicio AND fecha_fin) OR
                          (fecha_inicio BETWEEN ? AND ?)
                      )";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("issss", $colaborador_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $mensaje = "El rango de fechas seleccionado se cruza con un permiso ya existente.";
            $tipoMensaje = 'danger';
        } else {
            // Si todo es correcto, proceder a guardar
            $carpeta_destino = "uploads/incapacidades/";
            if (!is_dir($carpeta_destino)) {
                mkdir($carpeta_destino, 0777, true);
            }
            $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $file_url = $carpeta_destino . uniqid('incap_') . "." . $ext;
            
            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $file_url)) {
                $stmt_tipo = $conn->query("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion) = 'incapacidad' LIMIT 1");
                $tipo_incapacidad_id = $stmt_tipo->fetch_assoc()['idTipoPermiso'];

                $stmt_estado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion) = 'pendiente' LIMIT 1");
                $estado_pendiente_id = $stmt_estado->fetch_assoc()['idEstado'];

                $sql = "INSERT INTO permisos (id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_solicitud, fecha_inicio, fecha_fin, motivo, observaciones, comprobante_url) 
                        VALUES (?, ?, ?, NOW(), ?, ?, 'Incapacidad Médica', ?, ?)";
                $stmt_insert = $conn->prepare($sql);
                $stmt_insert->bind_param("iiissss", $colaborador_id, $tipo_incapacidad_id, $estado_pendiente_id, $fecha_inicio, $fecha_fin, $comentario, $file_url);

                if ($stmt_insert->execute()) {
                    $mensaje = "¡Solicitud de incapacidad enviada correctamente!";
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = "Error al enviar la solicitud: " . $conn->error;
                    $tipoMensaje = 'danger';
                }
                $stmt_insert->close();
            } else {
                $mensaje = "Error al subir el archivo comprobante.";
                $tipoMensaje = 'danger';
            }
        }
        $check_stmt->close();
    }
}


// --- Historial de incapacidades ---
$historial = [];
$sql_historial = "SELECT 
                    p.fecha_inicio, 
                    p.fecha_fin, 
                    DATEDIFF(p.fecha_fin, p.fecha_inicio) + 1 as cantidad, 
                    p.observaciones as comentario, 
                    p.comprobante_url, 
                    ec.Descripcion as estado
                  FROM permisos p
                  JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
                  WHERE p.id_colaborador_fk = ?
                  AND p.id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion) = 'incapacidad')
                  ORDER BY p.fecha_inicio DESC";

$stmt_historial = $conn->prepare($sql_historial);
$stmt_historial->bind_param("i", $colaborador_id);
$stmt_historial->execute();
$result = $stmt_historial->get_result();
while ($row = $result->fetch_assoc()) {
    $historial[] = $row;
}
$stmt_historial->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Incapacidad - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important; font-family: 'Poppins', sans-serif; }
        .main-container { max-width: 880px; margin: 48px auto 0; padding: 0 15px; }
        .main-card { background: #fff; border-radius: 2.1rem; box-shadow: 0 8px 38px 0 rgba(44,62,80,.12); padding: 2.2rem 2.1rem 1.7rem 2.1rem; margin-bottom: 2.2rem; animation: fadeInDown 0.9s; }
        .card-title-custom { font-size: 2.2rem; font-weight: 900; color: #1a3961; margin-bottom: 0.5rem; display: flex; align-items: center; gap: .8rem; }
        .card-title-custom i { color: #01b87f; font-size: 2.2rem; }
        .text-center { color: #3a6389; }
        .form-label { color: #018f62; font-weight: 600; }
        .form-control { border-radius: 0.9rem; }
        .btn-submit-custom { background: linear-gradient(90deg, #01b87f 75%, #53e3fc 100%); color: #fff; font-weight: 700; border-radius: 0.8rem; padding: .63rem 1.5rem; width: 100%; margin-top: 1rem; border: none; }
        .btn-submit-custom:hover { background: linear-gradient(90deg, #53e3fc 25%, #01b87f 100%); color: #fff; }
        .table-custom { background: #f8fafd; border-radius: 1.15rem; overflow: hidden; box-shadow: 0 4px 24px #23b6ff10; }
        .table-custom th { background: #e9f6ff; color: #288cc8; font-weight: 700; }
        .table-custom td, .table-custom th { padding: 0.75rem 0.7rem; text-align: center; vertical-align: middle; }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-success { background-color: #01b87f !important; }
        .badge.bg-danger { background-color: #ff6565 !important; }
        .section-title { font-weight: 700; color: #1a3961; font-size: 1.4rem; margin-bottom: 1rem; text-align: center; }
        @media (max-width: 600px) { .main-card { padding: 1.1rem 0.3rem; } .card-title-custom { font-size: 1.3rem; } .table-custom th, .table-custom td { font-size: .85rem; padding: 0.4rem 0.3rem;} }
    </style>
</head>
<body>

<div class="main-container">
    <div class="main-card">
        <div class="card-title-custom">
            <i class="bi bi-bandaid-fill"></i> 
            <span>Registrar Incapacidad</span>
            <i class="bi bi-info-circle-fill text-info" 
               style="font-size: 1.2rem; cursor: pointer;"
               data-bs-toggle="tooltip" 
               data-bs-html="true"
               title="<div class='text-start'>
                        <strong>Instrucciones:</strong><br>
                        - Las fechas no pueden ser futuras.<br>
                        - La fecha de fin no puede ser anterior a la de inicio.<br>
                        - El comprobante es obligatorio.<br>
                        - No puedes registrar una incapacidad si las fechas se cruzan con un permiso ya existente.
                      </div>">
            </i>
            </div>
        <p class="text-center mb-4">Adjunta el comprobante y registra tus fechas de incapacidad.</p>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> text-center"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="incapacidadForm" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" max="<?= $fechaHoy ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" max="<?= $fechaHoy ?>" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Comentario (Opcional)</label>
                    <input type="text" name="comentario" class="form-control" placeholder="Ej: Gripe, consulta médica...">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Comprobante (Obligatorio)</label>
                    <input type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
            </div>
            <button type="submit" class="btn btn-submit-custom">
                <i class="bi bi-send-fill"></i> Enviar Solicitud
            </button>
        </form>
    </div>

    <div class="main-card mt-4">
        <div class="section-title">
            <i class="bi bi-clock-history"></i> Historial de Incapacidades
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días</th>
                        <th>Comentario</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr><td colspan="6">No tienes incapacidades registradas.</td></tr>
                    <?php else: foreach ($historial as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></td>
                            <td><?= htmlspecialchars($row['cantidad']) ?></td>
                            <td><?= htmlspecialchars($row['comentario'] ?: '-') ?></td>
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
                                    <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inicializar los tooltips de Bootstrap
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    fechaInicio.addEventListener('change', function() {
        if (fechaFin.value < fechaInicio.value) {
            fechaFin.value = fechaInicio.value;
        }
        fechaFin.min = fechaInicio.value;
    });

    (() => {
        'use strict';
        const form = document.getElementById('incapacidadForm');
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