<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) { // Solo Administrador
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$msg = "";
$msg_type = "success";

// --- Lógica de Actualización de Jerarquía ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_jerarquia'])) {
    $colaborador_id = intval($_POST['colaborador_id']);
    $jefe_id = empty($_POST['jefe_id']) ? 0 : intval($_POST['jefe_id']);

    if ($colaborador_id === $jefe_id) {
        $msg = 'Un colaborador no puede ser su propio jefe.';
        $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE colaborador SET id_jefe_fk = ? WHERE idColaborador = ?");
        $stmt->bind_param("ii", $jefe_id, $colaborador_id);
        if ($stmt->execute()) {
            $msg = 'Jerarquía actualizada con éxito.';
        } else {
            $msg = 'Error al actualizar la jerarquía.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

// --- Obtener Datos para Visualización ---
$filtro_depto = $_GET['filtro_depto'] ?? '';

// --- CONSULTA SQL MEJORADA ---
$sql_colaboradores = "SELECT 
                        c.idColaborador, 
                        p.Nombre, p.Apellido1,
                        d.nombre as departamento,
                        c.id_jefe_fk,
                        jefe_p.Nombre as jefe_nombre,
                        jefe_p.Apellido1 as jefe_apellido1
                      FROM colaborador c
                      JOIN persona p ON c.id_persona_fk = p.idPersona
                      JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                      LEFT JOIN colaborador AS jefe_c ON c.id_jefe_fk = jefe_c.idColaborador
                      LEFT JOIN persona AS jefe_p ON jefe_c.id_persona_fk = jefe_p.idPersona
                      WHERE c.activo = 1";

if (!empty($filtro_depto)) {
    $sql_colaboradores .= " AND c.id_departamento_fk = " . intval($filtro_depto);
}
$sql_colaboradores .= " ORDER BY p.Nombre ASC";

$res_colaboradores = $conn->query($sql_colaboradores);

// --- Función para generar avatares con iniciales ---
function generate_avatar_html($nombre, $apellido) {
    $iniciales = mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1);
    $hash = md5($iniciales . $nombre); // Añadir nombre para más variedad de color
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    // Aclarar colores para mejor legibilidad del texto blanco
    $r = min(255, $r + 50); $g = min(255, $g + 50); $b = min(255, $b + 50);

    return "<div class='avatar' style='background-color:rgb($r,$g,$b)'>$iniciales</div>";
}

$chart_data = [];
$todos_los_colaboradores = [];
while($row = $res_colaboradores->fetch_assoc()){
    // CORRECCIÓN: Construir los nombres completos en PHP para evitar errores
    $row['nombre_completo'] = $row['Nombre'] . ' ' . $row['Apellido1'];
    $row['nombre_jefe'] = $row['jefe_nombre'] ? ($row['jefe_nombre'] . ' ' . $row['jefe_apellido1']) : null;
    $todos_los_colaboradores[] = $row;
    
    $jefe_id_str = ($row['id_jefe_fk'] == 0 || $row['id_jefe_fk'] == $row['idColaborador']) ? '' : (string)$row['id_jefe_fk'];
    
    // HTML para cada nodo del organigrama
    $node_html = generate_avatar_html($row['Nombre'], $row['Apellido1']) .
                 "<div class='node-name'>" . htmlspecialchars($row['nombre_completo']) . "</div>" .
                 "<div class='node-dept'>" . htmlspecialchars($row['departamento']) . "</div>";

    $chart_data[] = [
        ['v' => (string)$row['idColaborador'], 'f' => $node_html],
        $jefe_id_str,
        htmlspecialchars($row['departamento'])
    ];
}
$departamentos = $conn->query("SELECT idDepartamento, nombre FROM departamento WHERE id_estado_fk = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>

<?php include 'header.php'; ?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<style>
    .main-container { margin-left: 280px; padding: 2.5rem; }
    .page-header { text-align: center; margin-bottom: 2.5rem; }
    .page-title { font-weight: 700; color: #32325d; }
    .page-subtitle { color: #8898aa; }
    .content-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
    }
    #chart_div {
        width: 100%;
        overflow-x: auto;
        min-height: 450px;
        padding: 1rem;
    }
    .google-visualization-orgchart-node-medium {
        border-radius: 1rem !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        background: #ffffff !important;
        padding: 1rem !important;
        border: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
    }
    .google-visualization-orgchart-node-medium:hover {
        border-color: #5e72e4 !important;
        transform: translateY(-3px);
    }
    .avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        margin: 0 auto 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        font-weight: 600;
    }
    .node-name {
        font-weight: 600;
        color: #32325d;
        font-size: 1rem;
    }
    .node-dept {
        color: #8898aa;
        font-size: 0.85rem;
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">Organigrama y Jerarquías</h1>
        <p class="page-subtitle">Visualiza y configura la estructura de reportes de la empresa.</p>
    </div>

    <div class="content-card">
        <div class="card filter-card p-3 mb-4 bg-light border-0">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="filtro_depto" class="form-label">Filtrar por Departamento</label>
                    <select name="filtro_depto" class="form-select" onchange="this.form.submit()">
                        <option value="">Mostrar Todos</option>
                        <?php foreach ($departamentos as $depto): ?>
                        <option value="<?= $depto['idDepartamento'] ?>" <?= $filtro_depto == $depto['idDepartamento'] ? 'selected' : ''?>><?= htmlspecialchars($depto['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <div id="chart_div">
            <?php if(empty($chart_data)): ?>
            <div class="alert alert-info text-center">No hay datos para mostrar en el organigrama con los filtros actuales.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="content-card mt-4">
        <h4 class="mb-3">Asignar Jefaturas</h4>
         <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Colaborador</th>
                        <th>Departamento</th>
                        <th>Jefe Actual</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($todos_los_colaboradores as $col): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($col['nombre_completo']) ?></strong></td>
                        <td><?= htmlspecialchars($col['departamento']) ?></td>
                        <td><?= htmlspecialchars($col['nombre_jefe'] ?? 'N/A') ?></td>
                        <td class="text-end">
                             <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#jerarquiaModal" onclick='openJerarquiaModal(<?= json_encode($col) ?>)'>
                                <i class="bi bi-pencil-square me-1"></i> Asignar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="jerarquiaModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="jerarquiaForm" method="POST">
    <div class="modal-header">
        <h5 class="modal-title" id="jerarquiaModalLabel">Asignar Jefe</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" name="save_jerarquia" value="1">
        <input type="hidden" name="colaborador_id" id="colaborador_id">
        <div class="mb-3">
            <label class="form-label">Colaborador:</label>
            <p class="form-control-plaintext" id="nombre_colaborador_modal"></p>
        </div>
        <div class="mb-3">
            <label for="jefe_id" class="form-label">Nuevo Jefe Directo:</label>
            <select class="form-select" name="jefe_id" id="jefe_id" required>
                <option value="0">-- Sin Jefe (Nivel Superior) --</option>
                <?php
                foreach ($todos_los_colaboradores as $jefe_opcion): ?>
                    <option value="<?= $jefe_opcion['idColaborador'] ?>"><?= htmlspecialchars($jefe_opcion['nombre_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </div>
</form>
</div></div></div>

<?php include 'footer.php'; ?>
<script>
    google.charts.load('current', {packages:['orgchart']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var chartData = <?= json_encode($chart_data); ?>;
        if (chartData.length === 0) {
            document.getElementById('chart_div').innerHTML = '<div class="alert alert-warning text-center">No hay datos suficientes para generar un organigrama.</div>';
            return;
        }

        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Name');
        data.addColumn('string', 'Manager');
        data.addColumn('string', 'ToolTip');
        data.addRows(chartData);

        var chart = new google.visualization.OrgChart(document.getElementById('chart_div'));
        chart.draw(data, {
            allowHtml: true,
            allowCollapse: true, // Habilita la función de plegar/desplegar
            nodeClass: 'google-visualization-orgchart-node-medium',
            selectedNodeClass: 'google-visualization-orgchart-node-medium'
        });
    }
    
    const jerarquiaModal = new bootstrap.Modal(document.getElementById('jerarquiaModal'));
    function openJerarquiaModal(colaborador) {
        document.getElementById('colaborador_id').value = colaborador.idColaborador;
        document.getElementById('nombre_colaborador_modal').textContent = colaborador.nombre_completo;
        
        const jefeSelect = document.getElementById('jefe_id');
        // Usar el id_jefe_fk del objeto colaborador, que ya está correcto (0 si no tiene jefe)
        jefeSelect.value = colaborador.id_jefe_fk || 0;

        // Deshabilitar la opción de que un colaborador sea su propio jefe
        for (let i = 0; i < jefeSelect.options.length; i++) {
            jefeSelect.options[i].disabled = (jefeSelect.options[i].value == colaborador.idColaborador);
        }
        
        jerarquiaModal.show();
    }
</script>
</body>
</html>