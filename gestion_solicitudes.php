<?php
session_start();
include 'db.php';

// 1. Validar que el usuario sea Administrador o de RRHH
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}
$isAdmin = ($_SESSION['rol'] == 1);

// --- BLOQUE API: MANEJA LAS ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $conn->begin_transaction();
    try {
        $action = $_POST['action'];
        $tipo_registro = $_POST['tipo_registro'] ?? '';
        if (empty($tipo_registro)) throw new Exception("Tipo de registro no especificado.");

        if ($tipo_registro === 'permiso') {
            $id_colaborador = filter_input(INPUT_POST, 'id_colaborador', FILTER_VALIDATE_INT);
            $fecha_inicio = $_POST['fecha'] ?? '';
            if (!$id_colaborador || !$fecha_inicio) throw new Exception("Faltan identificadores para el permiso.");
            
            switch ($action) {
                case 'approve':
                case 'reject':
                    $observaciones = $_POST['observaciones'] ?? ($action === 'approve' ? 'Aprobado por RRHH/Admin' : '');
                    $nuevo_estado = ($action === 'approve') ? 4 : 5;
                    $stmt = $conn->prepare("UPDATE permisos SET id_estado_fk = ?, observaciones = ? WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
                    $stmt->bind_param("isis", $nuevo_estado, $observaciones, $id_colaborador, $fecha_inicio);
                    break;
                case 'delete':
                    if (!$isAdmin) throw new Exception("No tienes permiso para eliminar.");
                    $stmt = $conn->prepare("DELETE FROM permisos WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
                    $stmt->bind_param("is", $id_colaborador, $fecha_inicio);
                    break;
                default:
                     throw new Exception("Acción no reconocida para permisos.");
            }
        } elseif ($tipo_registro === 'horas_extra') {
            $id_registro = filter_input(INPUT_POST, 'id_registro', FILTER_VALIDATE_INT);
            if (!$id_registro) throw new Exception("Falta el identificador para las horas extra.");

            switch ($action) {
                case 'approve':
                case 'reject':
                    $observaciones = $_POST['observaciones'] ?? ($action === 'approve' ? 'Aprobado por RRHH/Admin' : '');
                    $nuevo_estado_str = ($action === 'approve') ? 'Aprobada' : 'Rechazada';
                    $stmt = $conn->prepare("UPDATE horas_extra SET estado = ?, Observaciones = ? WHERE idPermisos = ?");
                    $stmt->bind_param("ssi", $nuevo_estado_str, $observaciones, $id_registro);
                    break;
                case 'delete':
                    if (!$isAdmin) throw new Exception("No tienes permiso para eliminar.");
                    $stmt = $conn->prepare("DELETE FROM horas_extra WHERE idPermisos = ?");
                    $stmt->bind_param("i", $id_registro);
                    break;
                default:
                    throw new Exception("Acción no reconocida para horas extra.");
            }
        } else {
            throw new Exception("Tipo de registro desconocido.");
        }
        
        $stmt->execute();
        if ($stmt->affected_rows === 0) throw new Exception("No se encontró el registro o no se pudo aplicar la acción.");
        $stmt->close();
        
        $conn->commit();
        $message = "Acción completada exitosamente.";
        echo json_encode(['success' => $message]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

// --- LÓGICA DE FILTROS Y CONSULTA PARA MOSTRAR LA PÁGINA ---
$filtro_estado = $_GET['estado'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_colaborador = $_GET['colaborador'] ?? '';

$sql = "
    (SELECT 
        'permiso' AS tipo_registro, NULL AS id_registro, p.id_colaborador_fk AS id_colaborador,
        CONCAT(per.Nombre, ' ', per.Apellido1) AS colaborador, d.nombre AS departamento,
        tpc.Descripcion AS tipo_solicitud, p.fecha_inicio AS fecha, p.fecha_fin AS fecha_fin,
        p.hora_inicio, p.hora_fin, p.motivo, p.observaciones, p.comprobante_url, ec.Descripcion AS estado,
        CONCAT(jefe_p.Nombre, ' ', jefe_p.Apellido1) AS nombre_jefe
    FROM permisos p
    JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
    JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
    JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador
    JOIN persona per ON c.id_persona_fk = per.idPersona
    JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
    LEFT JOIN colaborador jefe_c ON c.id_jefe_fk = jefe_c.idColaborador
    LEFT JOIN persona jefe_p ON jefe_c.id_persona_fk = jefe_p.idPersona)
    UNION ALL
    (SELECT 
        'horas_extra' AS tipo_registro, he.idPermisos AS id_registro, he.Colaborador_idColaborador AS id_colaborador,
        CONCAT(p.Nombre, ' ', p.Apellido1) AS colaborador, d.nombre AS departamento, 'Horas Extra' AS tipo_solicitud,
        he.Fecha AS fecha, he.Fecha AS fecha_fin, he.hora_inicio, he.hora_fin, he.Motivo AS motivo,
        he.Observaciones AS observaciones, '' AS comprobante_url, he.estado,
        CONCAT(jefe_p.Nombre, ' ', jefe_p.Apellido1) AS nombre_jefe
    FROM horas_extra he
    JOIN colaborador c ON he.Colaborador_idColaborador = c.idColaborador
    JOIN persona p ON c.id_persona_fk = p.idPersona
    JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
    LEFT JOIN colaborador jefe_c ON c.id_jefe_fk = jefe_c.idColaborador
    LEFT JOIN persona jefe_p ON jefe_c.id_persona_fk = jefe_p.idPersona)
    ORDER BY fecha DESC
";
$solicitudes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Filtrado con PHP
if ($filtro_colaborador) {
    $solicitudes = array_filter($solicitudes, function($s) use ($filtro_colaborador) {
        return stripos($s['colaborador'], $filtro_colaborador) !== false;
    });
}
if ($filtro_tipo) {
    $solicitudes = array_filter($solicitudes, function($s) use ($filtro_tipo) {
        return $s['tipo_solicitud'] == $filtro_tipo;
    });
}
if ($filtro_estado) {
    $filtro_estado_lower = strtolower($filtro_estado);
    $solicitudes = array_filter($solicitudes, function($s) use ($filtro_estado_lower) {
        $estado_actual_lower = strtolower($s['estado']);
        if ($filtro_estado_lower === 'aprobado') return in_array($estado_actual_lower, ['aprobado', 'aprobada']);
        if ($filtro_estado_lower === 'rechazado') return in_array($estado_actual_lower, ['rechazado', 'rechazada']);
        return $estado_actual_lower == $filtro_estado_lower;
    });
}

$tipos_disponibles = array_unique(array_column($solicitudes, 'tipo_solicitud'));
sort($tipos_disponibles);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Solicitudes | Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-content { margin-left: 280px; padding: 2.5rem; }
        .card-main { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07); }
        .card-header-custom { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #e9ecef; border-radius: 1rem 1rem 0 0; }
        .card-title-custom { font-weight: 600; font-size: 1.5rem; color: #32325d; }
        .filter-panel { background-color: #f6f9fc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; }
        .table thead th { font-weight: 600; color: #8898aa; background-color: #f6f9fc; }
        .badge { font-size: .85em; padding: .45em .8em; font-weight: 600; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="main-content">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-folder-check me-2"></i>Gestión de Solicitudes</h4>
        </div>
        <div class="card-body p-4">
            <div id="feedback-alert"></div>
            
            <div class="filter-panel">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4"><label class="form-label">Colaborador</label><input type="text" name="colaborador" class="form-control" value="<?= htmlspecialchars($filtro_colaborador) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="">Todos</option><?php foreach($tipos_disponibles as $t):?><option value="<?=htmlspecialchars($t)?>" <?=($filtro_tipo==$t)?'selected':''?>><?=htmlspecialchars($t)?></option><?php endforeach;?></select></div>
                    <div class="col-md-3"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="">Todos</option><option value="Pendiente" <?= ($filtro_estado == 'Pendiente')?'selected':''?>>Pendiente</option><option value="Justificada" <?= ($filtro_estado == 'Justificada')?'selected':''?>>Justificada</option><option value="Aprobado" <?= ($filtro_estado == 'Aprobado')?'selected':''?>>Aprobado</option><option value="Rechazado" <?= ($filtro_estado == 'Rechazado')?'selected':''?>>Rechazado</option></select></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filtrar</button></div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Colaborador</th><th>Departamento</th><th>Tipo de Solicitud</th><th>Fecha</th><th class="text-center">Estado</th><th class="text-center">Acciones</th></tr></thead>
                    <tbody>
                        <?php if (count($solicitudes) > 0): foreach ($solicitudes as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['colaborador']) ?></strong></td>
                                <td><?= htmlspecialchars($row['departamento']) ?></td>
                                <td><?= htmlspecialchars($row['tipo_solicitud']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha']))) ?></td>
                                <td class="text-center">
                                    <?php
                                        $estado = strtolower($row['estado']);
                                        $clase_badge = 'secondary';
                                        if (in_array($estado, ['aprobado', 'aprobada'])) $clase_badge = 'success';
                                        elseif (in_array($estado, ['rechazado', 'rechazada'])) $clase_badge = 'danger';
                                        elseif (in_array($estado, ['pendiente', 'justificada'])) $clase_badge = 'warning text-dark';
                                    ?>
                                    <span class="badge bg-<?= $clase_badge ?>"><?= htmlspecialchars(ucfirst($row['estado'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" onclick='openManagementModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-gear-fill"></i> Gestionar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted p-4">No hay solicitudes con los filtros actuales.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="managementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Gestionar Solicitud</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="managementModalBody"></div>
            <div class="modal-footer justify-content-between" id="managementModalFooter"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectReasonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Motivo del Rechazo</h5></div>
            <div class="modal-body"><textarea id="rejectReasonText" class="form-control" rows="3" placeholder="Escribe el motivo aquí..."></textarea></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-target="#managementModal" data-bs-toggle="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="handleAction('reject')">Confirmar Rechazo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar esta solicitud de forma permanente? <strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Sí, Eliminar</button>
            </div>
        </div>
    </div>
</div>


<?php include 'footer.php'; ?>
<script>
const managementModal = new bootstrap.Modal(document.getElementById('managementModal'));
const rejectReasonModal = new bootstrap.Modal(document.getElementById('rejectReasonModal'));
const deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
let currentRequestData = null;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

function openManagementModal(data) {
    currentRequestData = data;
    const body = document.getElementById('managementModalBody');
    const footer = document.getElementById('managementModalFooter');
    
    let content = `<p><strong>Colaborador:</strong> ${data.colaborador}</p>
                   <p><strong>Tipo:</strong> ${data.tipo_solicitud}</p>
                   <p><strong>Fecha:</strong> ${new Date(data.fecha).toLocaleDateString('es-CR')}</p>
                   <p><strong>Motivo:</strong> ${data.motivo || 'N/A'}</p>
                   <p><strong>Observaciones:</strong> ${data.observaciones || 'N/A'}</p>`;
    body.innerHTML = content;

    let footerContent = '<div class="me-auto"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div><div>';
    const estado = data.estado.toLowerCase();

    if (estado === 'pendiente' || estado === 'justificada') {
        footerContent += `<button class="btn btn-success" onclick="handleAction('approve')"><i class="bi bi-check-circle"></i> Aprobar</button> `;
        footerContent += `<button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#rejectReasonModal"><i class="bi bi-x-circle"></i> Rechazar</button> `;
    }

    if (isAdmin) {
        footerContent += `<button class="btn btn-danger" onclick="confirmDelete()"><i class="bi bi-trash"></i> Eliminar</button>`;
    }
    footerContent += '</div>';
    footer.innerHTML = footerContent;

    managementModal.show();
}

async function handleAction(action) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('tipo_registro', currentRequestData.tipo_registro);

    if (currentRequestData.tipo_registro === 'permiso') {
        formData.append('id_colaborador', currentRequestData.id_colaborador);
        formData.append('fecha', currentRequestData.fecha);
    } else { // horas_extra
        formData.append('id_registro', currentRequestData.id_registro);
    }

    if (action === 'reject') {
        const reason = document.getElementById('rejectReasonText').value;
        if (!reason.trim()) {
            showAlert('Debe proporcionar un motivo para el rechazo.', 'danger');
            return;
        }
        formData.append('observaciones', reason);
        rejectReasonModal.hide();
    }

    try {
        const response = await fetch('gestion_solicitudes.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!response.ok) throw new Error(result.error || 'Error desconocido en el servidor.');

        showAlert(result.success, 'success');
        managementModal.hide();
        setTimeout(() => window.location.reload(), 1500);

    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function confirmDelete() {
    managementModal.hide();
    const confirmBtn = document.getElementById('confirmDeleteButton');
    confirmBtn.onclick = function() {
        deleteConfirmModal.hide();
        handleAction('delete');
    }
    deleteConfirmModal.show();
}

function showAlert(message, type = 'success') {
    const container = document.getElementById('feedback-alert');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}
</script>
</body>
</html>