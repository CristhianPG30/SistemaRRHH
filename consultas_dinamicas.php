<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// --- Opciones para los filtros ---
$colaboradores = $conn->query("SELECT c.idColaborador, CONCAT(p.Nombre, ' ', p.Apellido1) as nombre_completo FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona ORDER BY nombre_completo ASC")->fetch_all(MYSQLI_ASSOC);
$tipos_permiso = $conn->query("SELECT idTipoPermiso, Descripcion FROM tipo_permiso_cat ORDER BY Descripcion")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Consulta Dinámica ---
$resultados = [];
$columnas = [];
$titulo_reporte = 'Consultas Dinámicas';
$modulo = $_GET['modulo'] ?? '';
$id_colaborador_filtro = $_GET['id_colaborador'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$mes_filtro = $_GET['mes'] ?? '';
$anio_filtro = $_GET['anio'] ?? date('Y');

if (!empty($modulo)) {
    $sql_base = "";
    $params = [];
    $types = "";

    switch ($modulo) {
        case 'usuarios_activos':
            $titulo_reporte = 'Usuarios Activos';
            $columnas = ['Nombre Completo', 'Cédula', 'Departamento', 'Fecha Ingreso'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Nombre Completo`, p.Cedula, d.nombre AS `Departamento`, DATE_FORMAT(c.fecha_ingreso, '%d/%m/%Y') AS `Fecha Ingreso`
                         FROM colaborador c
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                         WHERE c.activo = 1";
            break;

        case 'usuarios_inactivos':
            $titulo_reporte = 'Usuarios Inactivos';
            $columnas = ['Nombre Completo', 'Cédula', 'Departamento', 'Fecha Liquidación'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Nombre Completo`, p.Cedula, d.nombre AS `Departamento`, DATE_FORMAT(l.fecha_liquidacion, '%d/%m/%Y') AS `Fecha Liquidación`
                         FROM colaborador c
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                         LEFT JOIN liquidaciones l ON c.idColaborador = l.id_colaborador_fk
                         WHERE c.activo = 0";
            break;

        case 'control_marcas':
            $titulo_reporte = 'Control de Marcas';
            $columnas = ['Colaborador', 'Fecha', 'Entrada', 'Salida', 'Horas Trabajadas'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, DATE_FORMAT(ca.Fecha, '%d/%m/%Y') AS `Fecha`, ca.Entrada, ca.Salida, TIMEDIFF(ca.Salida, ca.Entrada) as `Horas Trabajadas`
                         FROM control_de_asistencia ca
                         JOIN persona p ON ca.Persona_idPersona = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND p.idPersona = (SELECT id_persona_fk FROM colaborador WHERE idColaborador = ?)"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            if (!empty($mes_filtro)) { $sql_base .= " AND MONTH(ca.Fecha) = ?"; $params[] = $mes_filtro; $types .= 'i'; }
            if (!empty($anio_filtro)) { $sql_base .= " AND YEAR(ca.Fecha) = ?"; $params[] = $anio_filtro; $types .= 'i'; }
            break;

        case 'horas_extra':
        case 'permisos':
        case 'incapacidades':
        case 'vacaciones':
            $tipo_permiso_map = [
                'horas_extra' => 'Horas Extra',
                'permisos' => 'Permiso',
                'incapacidades' => 'Incapacidad',
                'vacaciones' => 'Vacaciones'
            ];
            $titulo_reporte = 'Reporte de ' . ucfirst($modulo);
            $columnas = ['Colaborador', 'Tipo', 'Estado', 'Fechas', 'Motivo/Observaciones'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, tpc.Descripcion AS `Tipo`, ec.Descripcion AS `Estado`, CONCAT(DATE_FORMAT(pe.fecha_inicio, '%d/%m/%Y'), ' al ', DATE_FORMAT(pe.fecha_fin, '%d/%m/%Y')) as `Fechas`, pe.motivo AS `Motivo/Observaciones`
                         FROM permisos pe
                         JOIN colaborador c ON pe.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN tipo_permiso_cat tpc ON pe.id_tipo_permiso_fk = tpc.idTipoPermiso
                         JOIN estado_cat ec ON pe.id_estado_fk = ec.idEstado
                         WHERE tpc.Descripcion LIKE ?";
            $params[] = '%' . $tipo_permiso_map[$modulo] . '%';
            $types .= 's';
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND c.idColaborador = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            if (!empty($estado_filtro)) { $sql_base .= " AND ec.Descripcion = ?"; $params[] = $estado_filtro; $types .= 's'; }
            break;

        case 'liquidaciones':
            $titulo_reporte = 'Detalle de Liquidaciones';
            $columnas = ['Colaborador', 'Fecha', 'Motivo', 'Monto Neto'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, DATE_FORMAT(l.fecha_liquidacion, '%d/%m/%Y') as `Fecha`, l.motivo as `Motivo`, l.monto_neto as `Monto Neto`
                         FROM liquidaciones l
                         JOIN colaborador c ON l.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND l.id_colaborador_fk = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            break;

        case 'planilla':
            $titulo_reporte = 'Detalle de Planilla';
            $columnas = ['Colaborador', 'Fecha', 'Salario Bruto', 'Deducciones', 'Salario Neto'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) as `Colaborador`, DATE_FORMAT(pl.fecha_generacion, '%d/%m/%Y') as `Fecha`, pl.salario_bruto as `Salario Bruto`, pl.total_deducciones as `Deducciones`, pl.salario_neto as `Salario Neto`
                         FROM planillas pl
                         JOIN colaborador c ON pl.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($mes_filtro)) { $sql_base .= " AND MONTH(pl.fecha_generacion) = ?"; $params[] = $mes_filtro; $types .= 'i'; }
            if (!empty($anio_filtro)) { $sql_base .= " AND YEAR(pl.fecha_generacion) = ?"; $params[] = $anio_filtro; $types .= 'i'; }
            break;
    }

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
            <h2 class="query-title"><i class="bi bi-funnel-fill text-primary"></i> <?= htmlspecialchars($titulo_reporte) ?></h2>
        </div>

        <div class="filter-panel">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="modulo" class="form-label fw-bold">Seleccione el Reporte:</label>
                        <select name="modulo" id="modulo" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Elegir Módulo --</option>
                            <optgroup label="Colaboradores">
                                <option value="usuarios_activos" <?= ($modulo == 'usuarios_activos') ? 'selected' : '' ?>>Usuarios Activos</option>
                                <option value="usuarios_inactivos" <?= ($modulo == 'usuarios_inactivos') ? 'selected' : '' ?>>Usuarios Inactivos</option>
                            </optgroup>
                            <optgroup label="Registros">
                                <option value="control_marcas" <?= ($modulo == 'control_marcas') ? 'selected' : '' ?>>Control de Marcas</option>
                                <option value="horas_extra" <?= ($modulo == 'horas_extra') ? 'selected' : '' ?>>Horas Extra</option>
                                <option value="permisos" <?= ($modulo == 'permisos') ? 'selected' : '' ?>>Permisos</option>
                                <option value="incapacidades" <?= ($modulo == 'incapacidades') ? 'selected' : '' ?>>Incapacidades</option>
                                <option value="vacaciones" <?= ($modulo == 'vacaciones') ? 'selected' : '' ?>>Vacaciones</option>
                            </optgroup>
                             <optgroup label="Financiero">
                                <option value="liquidaciones" <?= ($modulo == 'liquidaciones') ? 'selected' : '' ?>>Liquidaciones</option>
                                <option value="planilla" <?= ($modulo == 'planilla') ? 'selected' : '' ?>>Planilla</option>
                            </optgroup>
                        </select>
                    </div>

                    <?php if (in_array($modulo, ['control_marcas', 'horas_extra', 'permisos', 'incapacidades', 'vacaciones', 'liquidaciones'])): ?>
                    <div class="col-md-4">
                        <label class="form-label">Colaborador:</label>
                        <select name="id_colaborador" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($colaboradores as $col): ?>
                            <option value="<?= $col['idColaborador'] ?>" <?= ($id_colaborador_filtro == $col['idColaborador']) ? 'selected' : '' ?>><?= htmlspecialchars($col['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array($modulo, ['horas_extra', 'permisos', 'incapacidades', 'vacaciones'])): ?>
                    <div class="col-md-4">
                        <label class="form-label">Estado:</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="Aprobado" <?= ($estado_filtro == 'Aprobado') ? 'selected' : '' ?>>Aprobado</option>
                            <option value="Rechazado" <?= ($estado_filtro == 'Rechazado') ? 'selected' : '' ?>>Rechazado</option>
                            <option value="Pendiente" <?= ($estado_filtro == 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
                        </select>
                    </div>
                    <?php endif; ?>

                     <?php if (in_array($modulo, ['control_marcas', 'planilla'])): ?>
                     <div class="col-md-2">
                        <label class="form-label">Mes:</label>
                        <select name="mes" class="form-select">
                            <option value="">Todos</option>
                            <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($mes_filtro == $m) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label class="form-label">Año:</label>
                        <input type="number" name="anio" class="form-control" value="<?= htmlspecialchars($anio_filtro) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col-md-2 align-self-end">
                         <button type="submit" class="btn btn-primary w-100">Consultar</button>
                    </div>
                </div>
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
        <?php elseif(!empty($modulo)): ?>
            <div class="alert alert-info text-center mt-4">No se encontraron resultados para los filtros seleccionados.</div>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>