<?php
session_start();
date_default_timezone_set('America/Costa_Rica');

// 1. Validar que el usuario sea de RRHH (rol 4) o Administrador (rol 1)
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// --- CONSULTAS PARA EL DASHBOARD DE RRHH ---

// 2. Total de colaboradores activos
$result_colaboradores = $conn->query("SELECT COUNT(*) as total FROM colaborador WHERE activo = 1");
$total_colaboradores = $result_colaboradores->fetch_assoc()['total'] ?? 0;

// 3. Total de solicitudes de permisos pendientes (estado 3 = Pendiente)
$result_permisos = $conn->query("SELECT COUNT(*) as total FROM permisos WHERE id_estado_fk = 3");
$permisos_pendientes = $result_permisos->fetch_assoc()['total'] ?? 0;

// 4. Monto total de la última planilla generada
$result_planilla = $conn->query("SELECT SUM(salario_neto) as total_neto FROM planillas WHERE fecha_generacion = (SELECT MAX(fecha_generacion) FROM planillas)");
$ultima_planilla_total = $result_planilla->fetch_assoc()['total_neto'] ?? 0;

// 5. Datos para el gráfico: solicitudes de los últimos 7 días
$labels_grafico = [];
$data_grafico = [];
$fecha_fin_grafico = date('Y-m-d');
$fecha_inicio_grafico = date('Y-m-d', strtotime('-6 days'));

$sql_grafico = "SELECT DATE(fecha_solicitud) as fecha, COUNT(*) as cantidad
                FROM permisos 
                WHERE fecha_solicitud BETWEEN ? AND ?
                GROUP BY DATE(fecha_solicitud)
                ORDER BY fecha ASC";
$stmt_grafico = $conn->prepare($sql_grafico);
$stmt_grafico->bind_param("ss", $fecha_inicio_grafico, $fecha_fin_grafico);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();
$datos_dias = [];
while($row = $result_grafico->fetch_assoc()) {
    $datos_dias[$row['fecha']] = $row['cantidad'];
}
$stmt_grafico->close();

for ($i = 0; $i < 7; $i++) {
    $fecha_iterada = date('Y-m-d', strtotime("-$i days"));
    $labels_grafico[] = date('d/m', strtotime($fecha_iterada));
    $data_grafico[] = $datos_dias[$fecha_iterada] ?? 0;
}
$labels_grafico = array_reverse($labels_grafico);
$data_grafico = array_reverse($data_grafico);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Recursos Humanos - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .stat-card { border-radius: 1rem; color: white; transition: transform 0.2s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { display: flex; justify-content: space-between; align-items: center; }
        .stat-card .stat-value { font-size: 2.5rem; font-weight: 700; }
        .stat-card .stat-icon { font-size: 4rem; opacity: 0.2; }
        .module-card { text-decoration: none; color: inherit; }
        .module-card .card { border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.2s ease; height: 100%; }
        .module-card .card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-color: var(--icon-color); }
        .module-card i { font-size: 2.5rem; color: var(--icon-color); }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800" style="font-weight: 600;">Panel de Recursos Humanos</h1>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card stat-card" style="background: linear-gradient(45deg, #5e72e4, #7952b3);">
                    <div class="card-body">
                        <div>
                            <div class="text-uppercase">Colaboradores Activos</div>
                            <div class="stat-value"><?= $total_colaboradores ?></div>
                        </div>
                        <i class="bi bi-people-fill stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card" style="background: linear-gradient(45deg, #ff9f43, #ff6b6b);">
                    <div class="card-body">
                        <div>
                            <div class="text-uppercase">Solicitudes Pendientes</div>
                            <div class="stat-value"><?= $permisos_pendientes ?></div>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card" style="background: linear-gradient(45deg, #1dd1a1, #10ac84);">
                    <div class="card-body">
                        <div>
                            <div class="text-uppercase">Última Planilla</div>
                            <div class="stat-value">₡<?= number_format($ultima_planilla_total, 2) ?></div>
                        </div>
                        <i class="bi bi-cash-stack stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mb-3">Módulos de Gestión</h4>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <a href="personas.php" class="module-card" style="--icon-color: #5e72e4;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-person-lines-fill"></i><h6 class="mt-3">Empleados</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="nóminas.php" class="module-card" style="--icon-color: #1cc88a;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-calculator-fill"></i><h6 class="mt-3">Planillas</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="permisos.php" class="module-card" style="--icon-color: #f6c23e;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-calendar-check-fill"></i><h6 class="mt-3">Permisos</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="horasextra.php" class="module-card" style="--icon-color: #e74a3b;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-clock-history"></i><h6 class="mt-3">Horas Extra</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="evaluacion.php" class="module-card" style="--icon-color: #36b9cc;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-star-fill"></i><h6 class="mt-3">Evaluaciones</h6></div></div>
                </a>
            </div>
             <div class="col-md-6 col-lg-3">
                <a href="liquidación.php" class="module-card" style="--icon-color: #858796;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-box-arrow-left"></i><h6 class="mt-3">Liquidaciones</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="aguinaldo.php" class="module-card" style="--icon-color: #f5365c;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-gift-fill"></i><h6 class="mt-3">Aguinaldos</h6></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="reporte_global.php" class="module-card" style="--icon-color: #fd7e14;">
                    <div class="card"><div class="card-body text-center p-4"><i class="bi bi-bar-chart-line-fill"></i><h6 class="mt-3">Reportes</h6></div></div>
                </a>
            </div>
        </div>
        
        <div class="card mt-5">
            <div class="card-body">
                 <h5 class="card-title">Actividad de Solicitudes (Últimos 7 días)</h5>
                 <canvas id="activityChart"></canvas>
            </div>
        </div>

    </div>
</main>
    
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_grafico) ?>,
                datasets: [{
                    label: 'Nº de Solicitudes',
                    data: <?= json_encode($data_grafico) ?>,
                    backgroundColor: 'rgba(94, 114, 228, 0.7)',
                    borderColor: 'rgba(94, 114, 228, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>