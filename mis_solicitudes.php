<?php
session_start();
include 'db.php';
include 'header.php'; // Se incluye el header al principio

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
if (!$colaborador_id) {
    // Es buena práctica manejar este error de forma más controlada
    die("Error: No se encontró el ID del colaborador. Por favor, inicie sesión de nuevo.");
}

// --- Lógica de Filtros ---
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_permiso_filtro = $_GET['tipo_permiso'] ?? '';

// Obtener lista de todos los tipos de permiso para el filtro
$tipos_permiso_lista = [];
$resultTipos = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat");
while ($row = $resultTipos->fetch_assoc()) {
    $tipos_permiso_lista[$row['idTipoPermiso']] = $row['Descripcion'];
}

// --- Consulta principal de solicitudes ---
$sql = "SELECT 
            p.fecha_solicitud,
            p.fecha_inicio,
            p.fecha_fin,
            p.motivo,
            p.observaciones,
            p.comprobante_url,
            t.Descripcion AS tipo,
            e.Descripcion AS estado
        FROM permisos p
        INNER JOIN tipo_permiso_cat t ON p.id_tipo_permiso_fk = t.idTipoPermiso
        INNER JOIN estado_cat e ON p.id_estado_fk = e.idEstado
        WHERE p.id_colaborador_fk = ?";
$params = [$colaborador_id];
$types = "i";

if ($fecha_inicio) {
    $sql .= " AND DATE(p.fecha_inicio) >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
}
if ($fecha_fin) {
    $sql .= " AND DATE(p.fecha_inicio) <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}
if ($tipo_permiso_filtro) {
    $sql .= " AND p.id_tipo_permiso_fk = ?";
    $params[] = $tipo_permiso_filtro;
    $types .= "i";
}
$sql .= " ORDER BY p.fecha_solicitud DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Solicitudes - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important;
            font-family: 'Poppins', sans-serif;
        }
        .main-container {
            max-width: 1200px;
            margin: 48px auto 0;
            padding: 0 15px;
        }
        .main-card {
            background: #fff;
            border-radius: 2.1rem;
            box-shadow: 0 8px 38px 0 rgba(44,62,80,.12);
            padding: 2.2rem;
            margin-bottom: 2.2rem;
            animation: fadeInDown 0.9s;
        }
        .card-title-custom {
            font-size: 2.2rem;
            font-weight: 900;
            color: #1a3961;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .card-title-custom i { color: #3499ea; font-size: 2.2rem; }
        .filter-box {
            background: #f3faff;
            padding: 1.1rem 1.3rem;
            border-radius: 1.1rem;
            box-shadow: 0 2px 16px #23b6ff18;
            display: flex;
            flex-wrap: wrap;
            gap: 1.2rem;
            align-items: end;
            margin-bottom: 1.8rem;
        }
        .filter-box label { color: #288cc8; font-weight: 600; }
        .filter-box .form-control, .filter-box .form-select { border-radius: 0.8rem; }
        .btn-filtrar {
            background: linear-gradient(90deg, #1f8ff7 75%, #53e3fc 100%);
            color: #fff;
            font-weight: 700;
            border-radius: 0.8rem;
            border: none;
        }
        .btn-filtrar:hover { background: linear-gradient(90deg, #53e3fc 25%, #1f8ff7 100%); color: #fff; }
        .table-custom {
            background: #f8fafd;
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 4px 24px #23b6ff10;
        }
        .table-custom th {
            background: #e9f6ff;
            color: #288cc8;
            font-weight: 700;
        }
        .table-custom td, .table-custom th {
            padding: 0.8rem;
            text-align: center;
            vertical-align: middle;
        }
        .badge { font-size: 0.9rem; padding: 0.4em 0.8em; border-radius: 0.7rem; font-weight: 600; }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-success { background-color: #01b87f !important; color: #fff !important;}
        .badge.bg-danger { background-color: #ff6565 !important; color: #fff !important;}
        .badge.bg-info { background-color: #bee7fa !important; color: #157099 !important; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="main-card">
        <div class="card-title-custom">
            <i class="bi bi-journal-check"></i> Mis Solicitudes
        </div>
        <p class="text-center mb-4">Aquí puedes ver y filtrar el historial de todas tus solicitudes.</p>

        <form method="get" class="filter-box">
            <div>
                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div>
                <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div>
                <label for="tipo_permiso" class="form-label">Tipo de Solicitud</label>
                <select name="tipo_permiso" id="tipo_permiso" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_permiso_lista as $id => $desc): ?>
                        <option value="<?= $id ?>" <?= ($tipo_permiso_filtro == $id) ? 'selected' : '' ?>><?= htmlspecialchars($desc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-filtrar"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Fecha Solicitud</th>
                        <th>Rango Solicitado</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($result) && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['tipo']) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['fecha_solicitud'])) ?></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                                    <?= ($row['fecha_fin'] && $row['fecha_fin'] != $row['fecha_inicio']) ? ' al '.date('d/m/Y', strtotime($row['fecha_fin'])) : '' ?>
                                </td>
                                <td>
                                    <?php
                                    $estado = strtolower($row['estado']);
                                    $badge_class = 'bg-info'; // Default
                                    if ($estado == 'pendiente') $badge_class = 'bg-warning';
                                    else if ($estado == 'aprobado') $badge_class = 'bg-success';
                                    else if ($estado == 'rechazado') $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($row['estado']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['motivo'] ?: '-') ?></td>
                                <td>
                                    <?php if (!empty($row['comprobante_url'])): ?>
                                        <a href="<?= htmlspecialchars($row['comprobante_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-muted p-4">No se encontraron solicitudes con los filtros actuales.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (isset($stmt)) $stmt->close();
$conn->close();
?>