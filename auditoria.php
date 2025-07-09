<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['rol'] > 2)) { // Solo Admin y RRHH (roles 1 y 2)
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Obtener filtros del formulario
$f_usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
$f_accion = isset($_GET['accion']) ? trim($_GET['accion']) : '';
$f_desde = isset($_GET['desde']) ? $_GET['desde'] : '';
$f_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';

// Preparar consulta SQL con filtros
$sql = "SELECT * FROM auditoria WHERE 1";
$params = [];
if ($f_usuario) {
    $sql .= " AND usuario LIKE ?";
    $params[] = "%$f_usuario%";
}
if ($f_accion) {
    $sql .= " AND accion = ?";
    $params[] = $f_accion;
}
if ($f_desde) {
    $sql .= " AND fecha_hora >= ?";
    $params[] = $f_desde . " 00:00:00";
}
if ($f_hasta) {
    $sql .= " AND fecha_hora <= ?";
    $params[] = $f_hasta . " 23:59:59";
}
$sql .= " ORDER BY fecha_hora DESC LIMIT 250";

$stmt = $conn->prepare($sql);
// Bind dinámico
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$auditorias = [];
while ($row = $result->fetch_assoc()) $auditorias[] = $row;
$stmt->close();

// Obtener acciones distintas para el filtro
$acciones = [];
$res2 = $conn->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion ASC");
while ($row = $res2->fetch_assoc()) $acciones[] = $row['accion'];
?>

<?php include 'header.php'; ?>

<style>
.aud-card {background: #fff; border-radius: 2rem; box-shadow:0 8px 32px #13c6f135; padding:2rem 2rem 2.5rem 2rem; margin:2.5rem auto 2rem auto; max-width:1120px;}
.aud-title {font-weight:900;font-size:2rem;color:#179ad7;letter-spacing:.7px; margin-bottom:1.4rem;}
.aud-table th, .aud-table td {padding:.51rem .75rem;font-size:1.05rem;}
.aud-table th {background: #eaf7ff; color:#0d6797;}
.aud-table tbody tr:hover {background: #f7fbff;}
.aud-table {border-radius:1rem;overflow:hidden;}
.aud-filter {margin-bottom:1.5rem;padding:1rem 2rem;background:#f3fcff;border-radius:1.1rem;box-shadow:0 1px 10px #13c6f112;}
.aud-filter .form-control, .aud-filter .form-select {border-radius:1rem;}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="aud-card">
        <div class="aud-title">
            <i class="bi bi-shield-check"></i> Auditoría de acciones del sistema
        </div>
        <!-- Filtros -->
        <form class="row aud-filter g-2 align-items-end" method="get" autocomplete="off">
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($f_usuario) ?>" placeholder="Nombre de usuario">
            </div>
            <div class="col-md-3">
                <label class="form-label">Acción</label>
                <select name="accion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $f_accion == $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($f_desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($f_hasta) ?>">
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-info px-4" type="submit"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>
        <!-- Tabla auditoría -->
        <div class="table-responsive">
            <table class="table aud-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Tabla afectada</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($auditorias): ?>
                    <?php foreach ($auditorias as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['fecha_hora']) ?></td>
                            <td><?= htmlspecialchars($a['usuario']) ?></td>
                            <td><?= htmlspecialchars($a['accion']) ?></td>
                            <td><?= htmlspecialchars($a['tabla_afectada']) ?></td>
                            <td><?= htmlspecialchars($a['descripcion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-secondary">No hay registros de auditoría con esos filtros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
