<?php
session_start();
include 'db.php';

// 1. Validar sesión y rol (Jefatura, Admin, RRHH)
$roles_permitidos = [1, 3, 4]; 
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: login.php');
    exit;
}

// 2. Obtener el idColaborador del jefe logueado (si aplica)
$idColaboradorJefe = ($_SESSION['rol'] == 3) ? ($_SESSION['colaborador_id'] ?? 0) : null;

// 3. Procesar aprobación/rechazo
$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Usar id_colaborador_fk y fecha_inicio como clave compuesta para identificar el permiso
    $id_colaborador = intval($_POST['id_colaborador_fk']);
    $fecha_inicio   = $_POST['fecha_inicio'];
    
    if ($_POST['accion'] == 'Aprobar') {
        $stmt = $conn->prepare("UPDATE permisos SET id_estado_fk = 4 WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
        $stmt->bind_param("is", $id_colaborador, $fecha_inicio);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Permiso aprobado correctamente." : "Error al aprobar el permiso.";
        $mensaje_tipo = $ok ? 'success' : 'danger';
        $stmt->close();
    } elseif ($_POST['accion'] == 'Rechazar' && !empty($_POST['comentario_rechazo'])) {
        $comentario = trim($_POST['comentario_rechazo']);
        $stmt = $conn->prepare("UPDATE permisos SET id_estado_fk = 5, observaciones = ? WHERE id_colaborador_fk = ? AND fecha_inicio = ?");
        $stmt->bind_param("sis", $comentario, $id_colaborador, $fecha_inicio);
        $ok = $stmt->execute();
        $mensaje = $ok ? "Permiso rechazado correctamente." : "Error al rechazar el permiso.";
        $mensaje_tipo = $ok ? 'warning' : 'danger';
        $stmt->close();
    }
}

// 4. Obtener tipos de permiso para el filtro
$tipos_permiso_filtro = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat ORDER BY Descripcion")->fetch_all(MYSQLI_ASSOC);
$filtro_tipo_id = $_GET['tipo_permiso'] ?? '';


// 5. Construir consulta de permisos pendientes con filtro (SIN la columna 'idPermisos')
$sql = "
SELECT 
    p.id_colaborador_fk,
    p.fecha_inicio,
    p.fecha_fin,
    p.hora_inicio,
    p.hora_fin,
    p.motivo,
    p.comprobante_url,
    tpc.Descripcion AS tipo_permiso,
    CONCAT(per.Nombre, ' ', per.Apellido1) AS colaborador
FROM permisos p
JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador
JOIN persona per ON c.id_persona_fk = per.idPersona
WHERE p.id_estado_fk = 3"; // Estado 3 = Pendiente

$params = [];
$types = "";

if ($idColaboradorJefe) {
    $sql .= " AND c.id_jefe_fk = ?";
    $params[] = $idColaboradorJefe;
    $types .= "i";
}
if (!empty($filtro_tipo_id)) {
    $sql .= " AND tpc.idTipoPermiso = ?";
    $params[] = $filtro_tipo_id;
    $types .= "i";
}

$sql .= " ORDER BY p.fecha_inicio ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Función para asignar colores a los badges
function get_badge_class_for_permiso($tipo_permiso) {
    $tipo = strtolower($tipo_permiso);
    if (strpos($tipo, 'vacaciones') !== false) return 'text-bg-success';
    if (strpos($tipo, 'incapacidad') !== false) return 'text-bg-warning';
    if (strpos($tipo, 'luto') !== false) return 'text-bg-dark';
    if (strpos($tipo, 'médica') !== false) return 'text-bg-primary';
    return 'text-bg-info';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Solicitudes de Permisos | Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .main-content { margin-left: 280px; padding: 2.5rem; }
        .card-main {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
        }
        .card-header-custom {
            padding: 1.5rem;
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            border-radius: 1rem 1rem 0 0;
        }
        .card-title-custom { font-weight: 600; font-size: 1.5rem; color: #32325d; }
        .table thead th { font-weight: 600; color: #8898aa; background-color: #f6f9fc; }
        .table td, .table th { vertical-align: middle; text-align: center; }
        .badge-tipo { font-size: .85em; font-weight: 600; }
        .acciones-btns .btn { width: 38px; height: 38px; }
        .filter-panel { background-color: #f6f9fc; border-radius: 0.75rem; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="main-content">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-calendar2-check-fill me-2 text-primary"></i>Solicitudes de Permisos Pendientes</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="filter-panel p-3 mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="tipo_permiso" class="form-label">Filtrar por tipo de permiso</label>
                        <select name="tipo_permiso" id="tipo_permiso" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Todos los tipos --</option>
                            <?php foreach ($tipos_permiso_filtro as $tipo): ?>
                                <option value="<?= $tipo['idTipoPermiso'] ?>" <?= ($filtro_tipo_id == $tipo['idTipoPermiso']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['Descripcion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>


            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Tipo de Permiso</th>
                            <th>Rango de Fechas</th>
                            <th>Horario</th>
                            <th>Motivo</th>
                            <th>Comprobante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['colaborador']) ?></strong></td>
                                <td><span class="badge rounded-pill <?= get_badge_class_for_permiso($row['tipo_permiso']) ?> badge-tipo"><?= htmlspecialchars($row['tipo_permiso']) ?></span></td>
                                <td>
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_inicio']))) ?>
                                    <?php if ($row['fecha_fin'] != $row['fecha_inicio']): ?>
                                        al <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_fin']))) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['hora_inicio'] && $row['hora_inicio'] != '00:00:00'): ?>
                                        <span class="badge bg-light text-dark"><?= date('g:i A', strtotime($row['hora_inicio'])) ?> - <?= date('g:i A', strtotime($row['hora_fin'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Día completo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['motivo']) ?></td>
                                <td>
                                    <?php if (!empty($row['comprobante_url'])): ?>
                                        <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ver</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="acciones-btns d-flex justify-content-center gap-2">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="id_colaborador_fk" value="<?= $row['id_colaborador_fk'] ?>">
                                            <input type="hidden" name="fecha_inicio" value="<?= $row['fecha_inicio'] ?>">
                                            <input type="hidden" name="accion" value="Aprobar">
                                            <button type="submit" class="btn btn-success" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <button class="btn btn-danger" title="Rechazar" onclick="mostrarModalRechazo('<?= $row['id_colaborador_fk'] ?>', '<?= $row['fecha_inicio'] ?>')"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted p-4"><i class="bi bi-emoji-smile fs-4 d-block mb-2"></i> No hay solicitudes de permisos pendientes que coincidan con el filtro.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-octagon-fill text-danger me-2"></i>Motivo del Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_colaborador_fk" id="id_colaborador_fk_rechazo">
                    <input type="hidden" name="fecha_inicio" id="fecha_inicio_rechazo">
                    <input type="hidden" name="accion" value="Rechazar">
                    <div class="mb-3">
                        <label for="comentario_rechazo" class="form-label">Por favor, indica el motivo del rechazo:</label>
                        <textarea class="form-control" id="comentario_rechazo" name="comentario_rechazo" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
function mostrarModalRechazo(idColaborador, fechaInicio) {
    document.getElementById('id_colaborador_fk_rechazo').value = idColaborador;
    document.getElementById('fecha_inicio_rechazo').value = fechaInicio;
    var modal = new bootstrap.Modal(document.getElementById('modalRechazo'));
    modal.show();
}
</script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>