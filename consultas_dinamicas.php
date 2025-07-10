<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// --- Opciones para los filtros ---
$departamentos = $conn->query("SELECT idDepartamento, nombre FROM departamento WHERE id_estado_fk = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$tipos_permiso = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat ORDER BY Descripcion")->fetch_all(MYSQLI_ASSOC);
$estados = $conn->query("SELECT idEstado, Descripcion FROM estado_cat ORDER BY Descripcion")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Consulta Dinámica ---
$resultados = [];
$columnas = [];
$modulo_seleccionado = $_GET['modulo'] ?? '';

if (!empty($modulo_seleccionado)) {
    $sql_base = "";
    $params = [];
    $types = "";

    switch ($modulo_seleccionado) {
        case 'permisos':
            $columnas = ['Colaborador', 'Tipo de Permiso', 'Estado', 'Fecha Inicio', 'Fecha Fin', 'Motivo'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS Colaborador, tpc.Descripcion AS `Tipo de Permiso`, ec.Descripcion AS Estado, DATE_FORMAT(pe.fecha_inicio, '%d/%m/%Y') AS `Fecha Inicio`, DATE_FORMAT(pe.fecha_fin, '%d/%m/%Y') AS `Fecha Fin`, pe.motivo AS Motivo 
                         FROM permisos pe
                         JOIN colaborador c ON pe.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN tipo_permiso_cat tpc ON pe.id_tipo_permiso_fk = tpc.idTipoPermiso
                         JOIN estado_cat ec ON pe.id_estado_fk = ec.idEstado
                         JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                         WHERE 1=1";
            if (!empty($_GET['tipo_permiso'])) { $sql_base .= " AND pe.id_tipo_permiso_fk = ?"; $params[] = $_GET['tipo_permiso']; $types .= 'i'; }
            if (!empty($_GET['estado'])) { $sql_base .= " AND pe.id_estado_fk = ?"; $params[] = $_GET['estado']; $types .= 'i'; }
            break;

        case 'asistencia':
            $columnas = ['Colaborador', 'Fecha', 'Entrada', 'Salida', 'Horas Trabajadas'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS Colaborador, DATE_FORMAT(ca.Fecha, '%d/%m/%Y') AS Fecha, ca.Entrada, ca.Salida, TIMEDIFF(ca.Salida, ca.Entrada) as `Horas Trabajadas`
                         FROM control_de_asistencia ca
                         JOIN persona p ON ca.Persona_idPersona = p.idPersona
                         JOIN colaborador c ON p.idPersona = c.id_persona_fk
                         WHERE 1=1";
            break;
    }

    // Filtros comunes
    if (!empty($_GET['id_departamento'])) { $sql_base .= " AND c.id_departamento_fk = ?"; $params[] = $_GET['id_departamento']; $types .= 'i'; }
    if (!empty($_GET['fecha_desde'])) { $sql_base .= " AND (pe.fecha_inicio >= ? OR ca.Fecha >= ?)"; $params[] = $_GET['fecha_desde']; $params[] = $_GET['fecha_desde']; $types .= 'ss'; }
    if (!empty($_GET['fecha_hasta'])) { $sql_base .= " AND (pe.fecha_fin <= ? OR ca.Fecha <= ?)"; $params[] = $_GET['fecha_hasta']; $params[] = $_GET['fecha_hasta']; $types .= 'ss'; }

    if (!empty($sql_base)) {
        $stmt = $conn->prepare($sql_base);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $resultados = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<?php include 'header.php'; ?>
<style>
.query-card { background: #fff; border-radius: 1.5rem; box-shadow: 0 6px 30px rgba(0,0,0,0.07); padding: 2rem; }
.query-title { font-weight: 700; color: #32325d; }
.filter-panel { background-color: #f8f9fa; padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid #e9ecef;}
.export-buttons .btn { margin-left: 10px; }
</style>

<div class="container py-5" style="margin-left: 280px;">
    <div class="query-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="query-title"><i class="bi bi-funnel-fill text-primary"></i> Consultas Dinámicas</h2>
            <div class="export-buttons">
                <a href="exportar_consulta.php?<?= http_build_query($_GET) ?>&formato=excel" class="btn btn-success" id="exportExcel" style="display: <?= empty($resultados) ? 'none' : 'inline-block' ?>;"><i class="bi bi-file-earmark-excel"></i> Exportar a Excel</a>
                <a href="exportar_consulta.php?<?= http_build_query($_GET) ?>&formato=pdf" class="btn btn-danger" id="exportPdf" style="display: <?= empty($resultados) ? 'none' : 'inline-block' ?>;"><i class="bi bi-file-earmark-pdf"></i> Exportar a PDF</a>
            </div>
        </div>

        <div class="filter-panel">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="modulo" class="form-label fw-bold">1. Seleccione qué desea consultar:</label>
                        <select name="modulo" id="modulo" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Elegir Módulo --</option>
                            <option value="permisos" <?= ($modulo_seleccionado == 'permisos') ? 'selected' : '' ?>>Permisos y Vacaciones</option>
                            <option value="asistencia" <?= ($modulo_seleccionado == 'asistencia') ? 'selected' : '' ?>>Asistencia</option>
                        </select>
                    </div>
                </div>

                <?php if (!empty($modulo_seleccionado)): ?>
                <hr class="my-4">
                <h5 class="mb-3">2. Aplique los filtros que necesite:</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Departamento:</label>
                        <select name="id_departamento" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($departamentos as $depto): ?>
                            <option value="<?= $depto['idDepartamento'] ?>" <?= ($_GET['id_departamento'] ?? '') == $depto['idDepartamento'] ? 'selected' : '' ?>><?= htmlspecialchars($depto['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Desde:</label>
                        <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hasta:</label>
                        <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>">
                    </div>

                    <?php if($modulo_seleccionado == 'permisos'): ?>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de Permiso:</label>
                        <select name="tipo_permiso" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($tipos_permiso as $tipo): ?>
                            <option value="<?= $tipo['idTipoPermiso'] ?>" <?= ($_GET['tipo_permiso'] ?? '') == $tipo['idTipoPermiso'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['Descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado:</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($estados as $estado): ?>
                            <option value="<?= $estado['idEstado'] ?>" <?= ($_GET['estado'] ?? '') == $estado['idEstado'] ? 'selected' : '' ?>><?= htmlspecialchars($estado['Descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Realizar Consulta</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($resultados)): ?>
        <h4 class="mt-5 mb-3">Resultados de la Consulta</h4>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <?php foreach($columnas as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($resultados as $fila): ?>
                    <tr>
                        <?php foreach($columnas as $col): ?>
                        <td><?= htmlspecialchars($fila[$col] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif(isset($_GET['modulo']) && !empty($_GET['modulo'])): ?>
            <div class="alert alert-info text-center mt-4">No se encontraron resultados para los filtros seleccionados.</div>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>