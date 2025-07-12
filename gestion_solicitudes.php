<?php
session_start();
include 'db.php';

// 1. Validar que el usuario sea Administrador o de RRHH
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

// --- LÓGICA DE FILTROS ---
$filtro_estado = $_GET['estado'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_colaborador = $_GET['colaborador'] ?? '';

// --- CONSULTA UNIFICADA CON CAMPOS ADICIONALES PARA EL MODAL ---
$sql = "
    (SELECT 
        'permiso' AS tipo_registro,
        p.id_colaborador_fk AS id_colaborador,
        CONCAT(per.Nombre, ' ', per.Apellido1) AS colaborador,
        d.nombre AS departamento,
        tpc.Descripcion AS tipo_solicitud,
        p.fecha_inicio AS fecha,
        p.fecha_fin AS fecha_fin,
        p.hora_inicio,
        p.hora_fin,
        p.motivo,
        p.observaciones,
        p.comprobante_url,
        ec.Descripcion AS estado,
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
        'horas_extra' AS tipo_registro,
        he.Colaborador_idColaborador AS id_colaborador,
        CONCAT(p.Nombre, ' ', p.Apellido1) AS colaborador,
        d.nombre AS departamento,
        'Horas Extra' AS tipo_solicitud,
        he.Fecha AS fecha,
        he.Fecha AS fecha_fin,
        he.hora_inicio,
        he.hora_fin,
        he.Motivo AS motivo,
        he.Observaciones AS observaciones,
        '' AS comprobante_url,
        he.estado,
        CONCAT(jefe_p.Nombre, ' ', jefe_p.Apellido1) AS nombre_jefe
    FROM horas_extra he
    JOIN colaborador c ON he.Colaborador_idColaborador = c.idColaborador
    JOIN persona p ON c.id_persona_fk = p.idPersona
    JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
    LEFT JOIN colaborador jefe_c ON c.id_jefe_fk = jefe_c.idColaborador
    LEFT JOIN persona jefe_p ON jefe_c.id_persona_fk = jefe_p.idPersona)
    
    ORDER BY fecha DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Solicitudes | Edginton S.A.</title>
    <?php include 'header.php'; ?>
    <style>
        body { background: #f4f7fc; font-family: 'Poppins', sans-serif; }
        .main-content { margin-left: 280px; padding: 2.5rem; }
        .card-main { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07); }
        .card-header-custom { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #e9ecef; border-radius: 1rem 1rem 0 0; }
        .card-title-custom { font-weight: 600; font-size: 1.5rem; color: #32325d; }
        .card-title-custom i { color: #5e72e4; }
        .filter-panel { background-color: #f6f9fc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; }
        .table thead th { font-weight: 600; color: #8898aa; background-color: #f6f9fc; border-bottom-width: 1px; }
        .table td { color: #525f7f; }
        .badge { font-size: .85em; padding: .45em .8em; font-weight: 600; }
        .modal-body strong { color: #5e72e4; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-folder-check me-2"></i>Gestión de Solicitudes</h4>
        </div>
        <div class="card-body p-4">
            
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
                                        if (in_array($estado, ['aprobado', 'aprobada'])) $clase_badge = ($row['tipo_registro'] == 'horas_extra') ? 'primary' : 'success';
                                        elseif (in_array($estado, ['rechazado', 'rechazada'])) $clase_badge = 'danger';
                                        elseif (in_array($estado, ['pendiente', 'justificada'])) $clase_badge = 'warning text-dark';
                                    ?>
                                    <span class="badge bg-<?= $clase_badge ?>"><?= htmlspecialchars(ucfirst($estado)) ?></span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-info" title="Ver Detalle"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detalleModal"
                                            onclick='mostrarDetalle(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-eye"></i>
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

<div class="modal fade" id="detalleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle de la Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleModalBody">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
const detalleModal = new bootstrap.Modal(document.getElementById('detalleModal'));

function mostrarDetalle(data) {
    const body = document.getElementById('detalleModalBody');
    let content = `<p><strong>Colaborador:</strong> ${data.colaborador}</p>
                   <p><strong>Departamento:</strong> ${data.departamento}</p>
                   <p><strong>Jefe a Cargo:</strong> ${data.nombre_jefe || 'N/A'}</p>
                   <hr>
                   <p><strong>Tipo de Solicitud:</strong> ${data.tipo_solicitud}</p>`;

    if (data.tipo_registro === 'permiso') {
        content += `<p><strong>Rango de Fechas:</strong> ${new Date(data.fecha).toLocaleDateString('es-CR')} al ${new Date(data.fecha_fin).toLocaleDateString('es-CR')}</p>`;
        if(data.hora_inicio) {
            content += `<p><strong>Horario:</strong> ${data.hora_inicio} - ${data.hora_fin}</p>`;
        }
    } else { // horas_extra
        content += `<p><strong>Fecha:</strong> ${new Date(data.fecha).toLocaleDateString('es-CR')}</p>`;
        content += `<p><strong>Horario Extra:</strong> ${data.hora_inicio} - ${data.hora_fin}</p>`;
    }
    
    content += `<p><strong>Motivo / Justificación:</strong> ${data.motivo || 'No especificado'}</p>`;
    content += `<p><strong>Observaciones:</strong> ${data.observaciones || 'Sin observaciones'}</p>`;

    if(data.comprobante_url) {
        content += `<p><strong>Comprobante:</strong> <a href="${data.comprobante_url}" target="_blank" class="btn btn-sm btn-primary">Ver Documento</a></p>`;
    }
    
    body.innerHTML = content;
    detalleModal.show();
}
</script>
</body>
</html>