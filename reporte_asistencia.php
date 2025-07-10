<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) { // Solo Admin y RRHH
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// --- Lógica de Filtros ---
$f_colaborador = $_GET['colaborador'] ?? '';
$f_desde = $_GET['desde'] ?? '';
$f_hasta = $_GET['hasta'] ?? '';

// --- Consulta Principal ---
$sql = "SELECT 
            p.Nombre, p.Apellido1, 
            ca.Fecha, ca.Entrada, ca.Salida,
            TIMEDIFF(ca.Salida, ca.Entrada) as HorasTrabajadas,
            CASE 
                WHEN ca.Entrada > '08:05:00' THEN 'Tardía'
                ELSE 'A tiempo'
            END as EstadoEntrada
        FROM control_de_asistencia ca
        JOIN persona p ON ca.Persona_idPersona = p.idPersona
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($f_colaborador)) {
    $sql .= " AND (p.Nombre LIKE ? OR p.Apellido1 LIKE ?)";
    $like_f_colaborador = "%{$f_colaborador}%";
    $params[] = $like_f_colaborador;
    $params[] = $like_f_colaborador;
    $types .= "ss";
}
if (!empty($f_desde)) {
    $sql .= " AND ca.Fecha >= ?";
    $params[] = $f_desde;
    $types .= "s";
}
if (!empty($f_hasta)) {
    $sql .= " AND ca.Fecha <= ?";
    $params[] = $f_hasta;
    $types .= "s";
}

$sql .= " ORDER BY ca.Fecha DESC, p.Nombre ASC";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<?php include 'header.php'; ?>

<style>
.report-card { background: #fff; border-radius: 1.5rem; box-shadow: 0 6px 30px rgba(0,0,0,0.07); padding: 2rem; }
.report-title { font-weight: 700; color: #343a40; }
.filter-form { background-color: #f8f9fa; padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; }
</style>

<div class="container py-5" style="margin-left: 280px;">
    <div class="report-card">
        <h2 class="report-title mb-4"><i class="bi bi-calendar-check-fill text-primary"></i> Reporte de Asistencia</h2>

        <form method="GET" class="filter-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="colaborador" class="form-label">Colaborador</label>
                    <input type="text" name="colaborador" id="colaborador" class="form-control" value="<?= htmlspecialchars($f_colaborador) ?>" placeholder="Nombre o apellido...">
                </div>
                <div class="col-md-3">
                    <label for="desde" class="form-label">Desde</label>
                    <input type="date" name="desde" id="desde" class="form-control" value="<?= htmlspecialchars($f_desde) ?>">
                </div>
                <div class="col-md-3">
                    <label for="hasta" class="form-label">Hasta</label>
                    <input type="date" name="hasta" id="hasta" class="form-control" value="<?= htmlspecialchars($f_hasta) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Colaborador</th>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Horas Trabajadas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido1']) ?></td>
                                <td><?= date("d/m/Y", strtotime($row['Fecha'])) ?></td>
                                <td><?= htmlspecialchars($row['Entrada']) ?></td>
                                <td><?= htmlspecialchars($row['Salida']) ?></td>
                                <td><?= htmlspecialchars($row['HorasTrabajadas']) ?></td>
                                <td>
                                    <span class="badge <?= $row['EstadoEntrada'] == 'A tiempo' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($row['EstadoEntrada']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No se encontraron registros con los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>