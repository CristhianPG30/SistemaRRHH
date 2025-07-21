<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// --- Opciones para los filtros (más robusto) ---
$colaboradores = $conn->query("SELECT c.idColaborador, CONCAT(p.Nombre, ' ', p.Apellido1) as nombre_completo FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.activo = 1 ORDER BY nombre_completo ASC")->fetch_all(MYSQLI_ASSOC);
$estados_permisos = $conn->query("SELECT idEstado, Descripcion FROM estado_cat ORDER BY Descripcion ASC")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Consulta Dinámica ---
$result = null;
$columnas = [];
$titulo_reporte = 'Consultas Dinámicas';
$num_resultados = 0;

$modulo = $_GET['modulo'] ?? '';
$id_colaborador_filtro = $_GET['id_colaborador'] ?? '';
$estado_permiso_filtro = $_GET['id_estado_permiso'] ?? ''; 
$estado_horas_extra_filtro = $_GET['estado_horas_extra'] ?? '';
$mes_filtro = $_GET['mes'] ?? '';
$anio_filtro = $_GET['anio'] ?? date('Y');

if (!empty($modulo)) {
    $sql_base = "";
    $params = [];
    $types = "";

    switch ($modulo) {
        case 'usuarios_activos':
            $titulo_reporte = 'Colaboradores Activos';
            $columnas = ['Nombre Completo', 'Cédula', 'Departamento', 'Fecha Ingreso'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1, ' ', p.Apellido2) AS `Nombre Completo`, p.Cedula AS `Cédula`, d.nombre AS `Departamento`, DATE_FORMAT(c.fecha_ingreso, '%d/%m/%Y') AS `Fecha Ingreso`
                         FROM colaborador c
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                         WHERE c.activo = 1";
            break;

        case 'usuarios_inactivos':
            $titulo_reporte = 'Colaboradores Inactivos';
            $columnas = ['Nombre Completo', 'Cédula', 'Departamento', 'Fecha Liquidación'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Nombre Completo`, p.Cedula AS `Cédula`, d.nombre AS `Departamento`, DATE_FORMAT(l.fecha_liquidacion, '%d/%m/%Y') AS `Fecha Liquidación`
                         FROM colaborador c
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                         LEFT JOIN liquidaciones l ON c.idColaborador = l.id_colaborador_fk
                         WHERE c.activo = 0";
            break;

        case 'control_marcas':
            $titulo_reporte = 'Control de Marcas';
            $columnas = ['Colaborador', 'Fecha', 'Entrada', 'Salida', 'Horas Trabajadas'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, DATE_FORMAT(ca.Fecha, '%d/%m/%Y') AS `Fecha`, TIME_FORMAT(ca.Entrada, '%h:%i %p') AS `Entrada`, TIME_FORMAT(ca.Salida, '%h:%i %p') AS `Salida`, TIMEDIFF(ca.Salida, ca.Entrada) as `Horas Trabajadas`
                         FROM control_de_asistencia ca
                         JOIN colaborador c ON ca.Persona_idPersona = c.id_persona_fk
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND c.idColaborador = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            if (!empty($mes_filtro)) { $sql_base .= " AND MONTH(ca.Fecha) = ?"; $params[] = $mes_filtro; $types .= 'i'; }
            if (!empty($anio_filtro)) { $sql_base .= " AND YEAR(ca.Fecha) = ?"; $params[] = $anio_filtro; $types .= 'i'; }
            break;

        case 'horas_extra':
            $titulo_reporte = 'Reporte de Horas Extra';
            $columnas = ['Colaborador', 'Fecha', 'Cantidad Horas', 'Estado', 'Motivo'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, DATE_FORMAT(he.Fecha, '%d/%m/%Y') AS `Fecha`, he.cantidad_horas AS `Cantidad Horas`, he.estado AS `Estado`, he.Motivo
                         FROM horas_extra he
                         JOIN colaborador c ON he.Colaborador_idColaborador = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND c.idColaborador = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            if (!empty($estado_horas_extra_filtro)) { $sql_base .= " AND he.estado = ?"; $params[] = $estado_horas_extra_filtro; $types .= 's'; }
            break;
        
        case 'permisos':
        case 'incapacidades':
        case 'vacaciones':
            // CORRECCIÓN: Se invierten los IDs de permisos y vacaciones para que coincidan con la base de datos.
            $tipo_permiso_map = ['permisos' => 3, 'incapacidades' => 2, 'vacaciones' => 1];
            $titulo_reporte = 'Reporte de ' . ucfirst($modulo);
            $columnas = ['Colaborador', 'Estado', 'Tipo Permiso', 'Fechas', 'Motivo/Observaciones'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, ec.Descripcion AS `Estado`, tpc.Descripcion AS `Tipo Permiso`, CONCAT(DATE_FORMAT(pe.fecha_inicio, '%d/%m/%Y'), ' al ', DATE_FORMAT(pe.fecha_fin, '%d/%m/%Y')) as `Fechas`, pe.motivo AS `Motivo/Observaciones`
                         FROM permisos pe
                         JOIN colaborador c ON pe.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         JOIN tipo_permiso_cat tpc ON pe.id_tipo_permiso_fk = tpc.idTipoPermiso
                         JOIN estado_cat ec ON pe.id_estado_fk = ec.idEstado
                         WHERE pe.id_tipo_permiso_fk = ?";
            $params[] = $tipo_permiso_map[$modulo];
            $types .= 'i';

            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND c.idColaborador = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            if (!empty($estado_permiso_filtro)) { $sql_base .= " AND ec.idEstado = ?"; $params[] = $estado_permiso_filtro; $types .= 'i'; }
            break;

        case 'liquidaciones':
            $titulo_reporte = 'Detalle de Liquidaciones';
            $columnas = ['Colaborador', 'Fecha Liquidación', 'Motivo', 'Monto Neto'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) AS `Colaborador`, DATE_FORMAT(l.fecha_liquidacion, '%d/%m/%Y') AS `Fecha Liquidación`, l.motivo AS `Motivo`, l.monto_neto AS `Monto Neto`
                         FROM liquidaciones l
                         JOIN colaborador c ON l.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND l.id_colaborador_fk = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
            break;

        case 'planilla':
            $titulo_reporte = 'Detalle de Planilla';
            $columnas = ['Colaborador', 'Fecha Generación', 'Salario Bruto', 'Deducciones', 'Salario Neto'];
            $sql_base = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1) as `Colaborador`, DATE_FORMAT(pl.fecha_generacion, '%d/%m/%Y') as `Fecha Generación`, pl.salario_bruto as `Salario Bruto`, pl.total_deducciones as `Deducciones`, pl.salario_neto as `Salario Neto`
                         FROM planillas pl
                         JOIN colaborador c ON pl.id_colaborador_fk = c.idColaborador
                         JOIN persona p ON c.id_persona_fk = p.idPersona
                         WHERE 1=1";
            if (!empty($id_colaborador_filtro)) { $sql_base .= " AND c.idColaborador = ?"; $params[] = $id_colaborador_filtro; $types .= 'i'; }
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
        $num_resultados = $result->num_rows;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consultas Dinámicas | Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <style>
        .main-content { margin-left: 280px; padding: 2.5rem; }
        .query-card { background: #fff; border-radius: 1rem; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 2rem; }
        .query-title { font-weight: 700; color: #32325d; }
        .filter-panel { background-color: #f7f9fc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border: 1px solid #e9ecef;}
        .filter-group { display: none; }
        .form-label { font-weight: 600; color: #525f7f; }
        .chart-container { max-height: 350px; margin-bottom: 2rem; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="main-content">
    <div class="query-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="query-title mb-0"><i class="bi bi-search-heart text-primary me-2"></i> <?= htmlspecialchars($titulo_reporte) ?></h2>
        </div>

        <div class="filter-panel">
            <form method="GET" id="report-form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="modulo" class="form-label"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Reporte</label>
                        <select name="modulo" id="modulo" class="form-select form-select-lg">
                            <option value="">-- Elegir un reporte --</option>
                            <optgroup label="Colaboradores">
                                <option value="usuarios_activos" <?= ($modulo == 'usuarios_activos') ? 'selected' : '' ?>>Colaboradores Activos</option>
                                <option value="usuarios_inactivos" <?= ($modulo == 'usuarios_inactivos') ? 'selected' : '' ?>>Colaboradores Inactivos</option>
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

                    <div class="col-md-3 filter-group" data-modules="control_marcas,horas_extra,permisos,incapacidades,vacaciones,liquidaciones,planilla">
                        <label for="id_colaborador" class="form-label"><i class="bi bi-person me-1"></i>Colaborador</label>
                        <select name="id_colaborador" id="id_colaborador" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach($colaboradores as $col): ?>
                            <option value="<?= $col['idColaborador'] ?>" <?= ($id_colaborador_filtro == $col['idColaborador']) ? 'selected' : '' ?>><?= htmlspecialchars($col['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 filter-group" data-modules="permisos,incapacidades,vacaciones">
                        <label for="id_estado_permiso" class="form-label"><i class="bi bi-check-circle me-1"></i>Estado</label>
                        <select name="id_estado_permiso" id="id_estado_permiso" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach($estados_permisos as $estado): ?>
                            <option value="<?= $estado['idEstado'] ?>" <?= ($estado_permiso_filtro == $estado['idEstado']) ? 'selected' : '' ?>><?= htmlspecialchars($estado['Descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 filter-group" data-modules="horas_extra">
                        <label for="estado_horas_extra" class="form-label"><i class="bi bi-check-circle me-1"></i>Estado Horas Extra</label>
                        <select name="estado_horas_extra" id="estado_horas_extra" class="form-select">
                            <option value="">-- Todos --</option>
                            <option value="Aprobada" <?= ($estado_horas_extra_filtro == 'Aprobada') ? 'selected' : '' ?>>Aprobada</option>
                            <option value="Rechazada" <?= ($estado_horas_extra_filtro == 'Rechazada') ? 'selected' : '' ?>>Rechazada</option>
                            <option value="Justificada" <?= ($estado_horas_extra_filtro == 'Justificada') ? 'selected' : '' ?>>Justificada</option>
                        </select>
                    </div>

                    <div class="col-md-2 filter-group" data-modules="control_marcas,planilla">
                         <label for="mes" class="form-label"><i class="bi bi-calendar-month me-1"></i>Mes</label>
                         <select name="mes" id="mes" class="form-select">
                             <option value="">-- Todos --</option>
                             <?php for($m=1; $m<=12; $m++): ?>
                             <option value="<?= $m ?>" <?= ($mes_filtro == $m) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(strftime("%B", mktime(0, 0, 0, $m, 1)))) ?></option>
                             <?php endfor; ?>
                         </select>
                    </div>
                    <div class="col-md-2 filter-group" data-modules="control_marcas,planilla">
                        <label for="anio" class="form-label"><i class="bi bi-calendar-event me-1"></i>Año</label>
                        <input type="number" name="anio" id="anio" class="form-control" value="<?= htmlspecialchars($anio_filtro) ?>" placeholder="Año">
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Consultar</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="results-container">
            <?php if ($result): ?>
                <div class="d-flex justify-content-between align-items-center mt-5 mb-3">
                    <h4 class="mb-0">
                        <span class="badge bg-success rounded-pill me-2"><?= $num_resultados ?></span> 
                        Resultados Encontrados
                    </h4>
                    <div class="export-buttons">
                        <button id="export-csv" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</button>
                        <button id="export-pdf" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
                    </div>
                </div>

                <?php if ($num_resultados > 0 && in_array($modulo, ['permisos', 'incapacidades', 'vacaciones', 'horas_extra'])): ?>
                    <div class="chart-container">
                        <canvas id="reportChart"></canvas>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="results-table">
                        <thead class="table-light">
                            <tr>
                                <?php foreach($columnas as $col): ?>
                                <th scope="col"><?= htmlspecialchars($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($num_resultados > 0): ?>
                                <?php while($fila = $result->fetch_assoc()): ?>
                                <tr>
                                    <?php foreach($columnas as $col): ?>
                                    <td><?= htmlspecialchars($fila[$col] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= count($columnas) ?>" class="text-center text-muted p-4">No se encontraron resultados para los filtros seleccionados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif(!empty($modulo)): ?>
                <div class="alert alert-light text-center mt-4 border"><i class="bi bi-info-circle me-2"></i>No se han encontrado resultados. Prueba con otros filtros.</div>
            <?php else: ?>
                <div class="alert alert-info text-center mt-4"><i class="bi bi-arrow-up-circle me-2"></i>Selecciona un tipo de reporte para empezar.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const moduloSelect = document.getElementById('modulo');
    const filterGroups = document.querySelectorAll('.filter-group');

    function actualizarFiltrosVisibles() {
        const selectedModulo = moduloSelect.value;
        filterGroups.forEach(group => {
            const AceptaModulos = group.dataset.modules.split(',');
            if (AceptaModulos.includes(selectedModulo)) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
    }

    moduloSelect.addEventListener('change', actualizarFiltrosVisibles);
    actualizarFiltrosVisibles();

    const resultsTable = document.getElementById('results-table');
    if (resultsTable) {
        document.getElementById('export-csv').addEventListener('click', function() {
            let csv = [];
            const rows = resultsTable.querySelectorAll('tr');
            const reportTitle = "<?= htmlspecialchars($titulo_reporte) ?>";
            csv.push(reportTitle);
            for (const row of rows) {
                const cols = row.querySelectorAll('th, td');
                const rowData = Array.from(cols).map(col => `"${col.innerText.replace(/"/g, '""')}"`);
                csv.push(rowData.join(','));
            }
            const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Reporte_${reportTitle.replace(/\s+/g,"_")}.csv`;
            link.click();
        });

        document.getElementById('export-pdf').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const reportTitle = "<?= htmlspecialchars($titulo_reporte) ?>";
            doc.text(reportTitle, 14, 16);
            doc.autoTable({ html: '#results-table', startY: 22, theme: 'grid', headStyles: { fillColor: [50, 50, 93] } });
            doc.save(`Reporte_${reportTitle.replace(/\s+/g,"_")}.pdf`);
        });
    }

    const chartCanvas = document.getElementById('reportChart');
    if (chartCanvas && <?= $num_resultados ?> > 0) {
        const ctx = chartCanvas.getContext('2d');
        const colNameToFind = 'Estado';
        const columnsHeader = Array.from(document.querySelectorAll('#results-table thead th')).map(th => th.innerText);
        const estadoColumnIndex = columnsHeader.indexOf(colNameToFind);
        
        if (estadoColumnIndex !== -1) {
            const dataCounts = {};
            const rows = resultsTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                if(cells.length > estadoColumnIndex) {
                    const estado = cells[estadoColumnIndex].innerText;
                    if(estado) dataCounts[estado] = (dataCounts[estado] || 0) + 1;
                }
            });
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(dataCounts),
                    datasets: [{
                        label: 'Cantidad por Estado',
                        data: Object.values(dataCounts),
                        backgroundColor: ['#2dce89', '#fb6340', '#f5365c', '#11cdef', '#ffd600', '#5e72e4'],
                        borderRadius: 5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    }
});
</script>
</body>
</html>
<?php
if ($result) {
    $stmt->close();
}
$conn->close();
?>