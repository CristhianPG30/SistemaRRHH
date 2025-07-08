<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
if (!$colaborador_id) {
    echo "<script>alert('Error: No se encontró el ID del colaborador.'); window.location.href='login.php';</script>";
    exit;
}

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_permiso = $_GET['tipo_permiso'] ?? '';

// Obtener lista de tipos de permiso
$tipos = [];
$resultTipos = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat");
while ($row = $resultTipos->fetch_assoc()) {
    $tipos[$row['idTipoPermiso']] = $row['Descripcion'];
}

// Consulta principal de solicitudes
$sql = "SELECT p.*, t.Descripcion AS tipo 
        FROM permisos p
        INNER JOIN tipo_permiso_cat t ON p.id_tipo_permiso_fk = t.idTipoPermiso
        WHERE p.id_colaborador_fk = ?";
$params = [$colaborador_id];
$types = "i";

if ($fecha_inicio && $fecha_fin) {
    $sql .= " AND DATE(p.fecha_inicio) BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
    $types .= "ss";
} elseif ($fecha_inicio) {
    $sql .= " AND DATE(p.fecha_inicio) >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
} elseif ($fecha_fin) {
    $sql .= " AND DATE(p.fecha_inicio) <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}
if ($tipo_permiso && is_numeric($tipo_permiso)) {
    $sql .= " AND p.id_tipo_permiso_fk = ?";
    $params[] = $tipo_permiso;
    $types .= "i";
}
$sql .= " ORDER BY p.fecha_inicio DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Solicitudes - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f3f7ff 0%, #e6f2fb 100%); font-family: 'Poppins', sans-serif; }
        .container-solicitudes { max-width: 1000px; margin: 48px auto 0; }
        .card-main { background: #fff; border-radius: 2.2rem; box-shadow: 0 8px 32px 0 rgba(44,62,80,.12); padding: 2.2rem 2rem 1.5rem 2rem;}
        .titulo { font-weight: 900; font-size: 2rem; color: #204d78; display: flex; gap: .8rem; align-items: center; }
        .titulo i { color: #3bb3f6; font-size: 2.2rem; }
        .filtros { display: flex; flex-wrap: wrap; gap: 1.1rem; align-items: end; margin-bottom: 1.6rem; }
        .filtros label { font-weight: 600; color: #2b7fba; }
        .filtros select, .filtros input { border-radius: .8rem; }
        .btn-filtrar { background: linear-gradient(90deg, #249dff 80%, #57e4ff 100%); color: #fff; font-weight: 700; border-radius: .7rem;}
        .btn-filtrar:hover { background: linear-gradient(90deg, #57e4ff 20%, #249dff 100%);}
        .table-solicitudes { border-radius: 1rem; overflow: hidden; background: #f6fafd;}
        .table-solicitudes th { background: #e8f6ff; color: #267cb0; font-weight: 700; }
        .table-solicitudes td, .table-solicitudes th { text-align: center; vertical-align: middle;}
        .badge-aprobado { background: #00b87f; color: #fff; }
        .badge-pendiente { background: #ffd237; color: #6a4d00; }
        .badge-rechazado { background: #ff6565; color: #fff; }
        .badge-otros { background: #c1c9d7; color: #404c57; }
        @media (max-width: 700px){ .card-main{padding:1rem;} .titulo{font-size:1.2rem;}.table-solicitudes td, .table-solicitudes th{font-size:.93rem;}}
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="container-solicitudes">
    <div class="card-main">
        <div class="titulo mb-4">
            <i class="bi bi-list-check"></i> Mis Solicitudes
        </div>

        <!-- Filtros -->
        <form method="get" class="filtros">
            <div>
                <label>Fecha inicio</label>
                <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div>
                <label>Fecha fin</label>
                <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div>
                <label>Tipo</label>
                <select name="tipo_permiso" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $id => $desc): ?>
                        <option value="<?= $id ?>" <?= ($tipo_permiso == $id) ? 'selected' : '' ?>><?= htmlspecialchars($desc) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <button class="btn btn-filtrar"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>

        <!-- Tabla de solicitudes -->
        <div class="table-responsive mt-3">
            <table class="table table-solicitudes table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Fecha solicitud</th>
                        <th>Rango solicitado</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                        <th>Observaciones</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['tipo']) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_solicitud'])) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?> 
                                <?= ($row['fecha_fin'] && $row['fecha_fin'] != $row['fecha_inicio']) ? ' - '.date('d/m/Y', strtotime($row['fecha_fin'])) : '' ?>
                            </td>
                            <td>
                                <?php
                                    $estado = strtolower($row['id_estado_fk']);
                                    // Puedes ajustar el mapeo de estados aquí
                                    $badge = 'badge-otros';
                                    $estadoLabel = 'Otro';
                                    if ($row['id_estado_fk'] == 1) { $badge = 'badge-pendiente'; $estadoLabel = 'Pendiente'; }
                                    elseif ($row['id_estado_fk'] == 2) { $badge = 'badge-aprobado'; $estadoLabel = 'Aprobado'; }
                                    elseif ($row['id_estado_fk'] == 3) { $badge = 'badge-rechazado'; $estadoLabel = 'Rechazado'; }
                                    echo "<span class='badge $badge'>$estadoLabel</span>";
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['motivo']) ?></td>
                            <td><?= htmlspecialchars($row['observaciones']) ?></td>
                            <td>
                                <?php if ($row['comprobante_url']): ?>
                                    <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-arrow-down"></i> Ver</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-muted">No se encontraron solicitudes registradas.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
