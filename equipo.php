<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['colaborador_id'])) {
    header('Location: login.php');
    exit;
}

$jefe_id = $_SESSION['colaborador_id'];

// --- CONSULTA MEJORADA ---
// Obtenemos todos los colaboradores activos para construir el organigrama.
// Esto soluciona el problema de "no tienes equipo" si eres un jefe de alto nivel.
$sql = "SELECT 
            c.idColaborador,
            CONCAT(p.Nombre, ' ', p.Apellido1) AS nombre,
            d.nombre as departamento,
            c.id_jefe_fk,
            p.Nombre as nombre_pila,
            p.Apellido1 as apellido1
        FROM colaborador c
        JOIN persona p ON c.id_persona_fk = p.idPersona
        JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
        WHERE c.activo = 1";

$result = $conn->query($sql);
$colaboradores = $result->fetch_all(MYSQLI_ASSOC);

// Función para generar avatares con iniciales
function generate_avatar_html($nombre, $apellido) {
    $iniciales = mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1);
    $hash = md5($iniciales);
    $r = hexdec(substr($hash,0,2));
    $g = hexdec(substr($hash,2,2));
    $b = hexdec(substr($hash,4,2));
    $r = min(255, $r + 50); $g = min(255, $g + 50); $b = min(255, $b + 50);

    // Usamos addslashes para escapar las comillas para el string de JavaScript
    return addslashes("<div class='org-avatar' style='background-color:rgb($r,$g,$b)'>$iniciales</div>");
}

// Preparar datos para el organigrama de Google Charts
$org_data = [];
$team_members_count = 0;
// Buscamos al jefe actual para que sea la raíz del organigrama
foreach($colaboradores as $col) {
    if ($col['idColaborador'] == $jefe_id) {
        $jefe_html_node = generate_avatar_html($col['nombre_pila'], $col['apellido1']) . "<div class='org-name'>{$col['nombre']}</div><div class='org-dept'>{$col['departamento']}</div>";
        // La raíz del organigrama no tiene padre (segundo elemento es '')
        $org_data[] = [['v' => (string)$col['idColaborador'], 'f' => $jefe_html_node], '', $col['nombre']];
        break;
    }
}

// Función recursiva para encontrar y agregar todos los subordinados
function find_subordinates(&$org_data, $colaboradores, $manager_id) {
    global $team_members_count;
    foreach ($colaboradores as $col) {
        if ($col['id_jefe_fk'] == $manager_id) {
            $team_members_count++;
            $html_node = generate_avatar_html($col['nombre_pila'], $col['apellido1']) . "<div class='org-name'>{$col['nombre']}</div><div class='org-dept'>{$col['departamento']}</div>";
            $org_data[] = [['v' => (string)$col['idColaborador'], 'f' => $html_node], (string)$manager_id, $col['nombre']];
            // Llamada recursiva para encontrar los subordinados de este subordinado
            find_subordinates($org_data, $colaboradores, $col['idColaborador']);
        }
    }
}

// Iniciar la búsqueda de subordinados desde el jefe que inició sesión
find_subordinates($org_data, $colaboradores, $jefe_id);

// Convertir los datos a JSON para JavaScript
$org_data_json = json_encode($org_data);

// Calcular KPIs
$sql_kpi = "SELECT 
                COUNT(*) as team_count, 
                AVG(DATEDIFF(CURDATE(), c.fecha_ingreso) / 365.25) as avg_tenure,
                COUNT(DISTINCT d.nombre) as dept_count
            FROM colaborador c 
            JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
            WHERE c.id_jefe_fk = ? AND c.activo = 1";
$stmt_kpi = $conn->prepare($sql_kpi);
$stmt_kpi->bind_param('i', $jefe_id);
$stmt_kpi->execute();
$kpi_result = $stmt_kpi->get_result()->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Equipo - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        :root {
            --primary-color: #5e72e4;
            --secondary-color: #f4f7fc;
            --text-dark: #32325d;
            --text-light: #8898aa;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--secondary-color);
        }
        .main-content {
            margin-left: 280px; /* Ajusta al ancho de tu sidebar */
            padding: 2rem;
        }
        .header h1 { color: var(--text-dark); font-weight: 700; }
        .header p { color: var(--text-light); }

        .stat-card {
            background-color: #fff;
            border: none;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
        }
        .stat-card .icon {
            font-size: 2.5rem;
            padding: 1rem;
            border-radius: 50%;
            color: #fff;
        }
        .stat-card .info .value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .stat-card .info .label {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        /* Estilos para el Organigrama */
        #orgchart_div {
            width: 100%;
            overflow-x: auto;
            padding: 1rem;
        }
        .google-visualization-orgchart-node {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            background: #fff;
            padding: 0.5rem;
            text-align: center;
        }
        .org-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 0.5rem auto 0.5rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .org-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        .org-dept {
            color: var(--text-light);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="header mb-4">
            <h1><i class="bi bi-diagram-3-fill me-2"></i>Dashboard de Equipo</h1>
            <p>Una vista general e interactiva de la estructura de tu equipo.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="icon bg-primary me-3"><i class="bi bi-people-fill"></i></div>
                    <div class="info">
                        <div class="value"><?= $kpi_result['team_count'] ?? 0 ?></div>
                        <div class="label">Miembros del Equipo</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="icon bg-success me-3"><i class="bi bi-award-fill"></i></div>
                    <div class="info">
                        <div class="value"><?= number_format($kpi_result['avg_tenure'] ?? 0, 1) ?> años</div>
                        <div class="label">Antigüedad Promedio</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="icon bg-info me-3"><i class="bi bi-building-fill"></i></div>
                    <div class="info">
                        <div class="value"><?= $kpi_result['dept_count'] ?? 0 ?></div>
                        <div class="label">Departamentos</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-main">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="mb-0">Organigrama del Equipo</h5>
            </div>
            <div class="card-body">
                <?php if ($team_members_count > 0): ?>
                    <div id="orgchart_div"></div>
                <?php else: ?>
                    <div class="text-center p-5">
                        <p class="text-muted fs-4">No tienes colaboradores asignados a tu equipo.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    google.charts.load('current', {packages:['orgchart']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('object', 'Name');
        data.addColumn('string', 'Manager');
        data.addColumn('string', 'ToolTip');

        // Los datos se insertan desde PHP
        data.addRows(<?= $org_data_json ?>);

        var chart = new google.visualization.OrgChart(document.getElementById('orgchart_div'));
        
        // Opciones del gráfico
        var options = {
            'allowHtml': true, // Permite usar HTML en las tarjetas
            'nodeClass': 'google-visualization-orgchart-node',
            'selectedNodeClass': 'bg-light'
        };

        chart.draw(data, options);
    }
</script>

</body>
</html>