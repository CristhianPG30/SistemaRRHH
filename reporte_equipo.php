<?php
session_start();
include 'db.php';

// Validar que el usuario sea Jefatura (rol 3) y tenga un ID de colaborador en la sesión
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 3 || !isset($_SESSION['colaborador_id'])) {
    header('Location: login.php');
    exit;
}

$id_jefe = $_SESSION['colaborador_id'];
$equipo_data = [];

// Consulta para obtener las evaluaciones y datos de los colaboradores del equipo
$sql = "SELECT 
            c.idColaborador,
            CONCAT(p.Nombre, ' ', p.Apellido1) AS nombre_completo,
            d.nombre AS departamento,
            e.Calificacion,
            e.Fecharealizacion
        FROM colaborador c
        JOIN persona p ON c.id_persona_fk = p.idPersona
        JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
        LEFT JOIN (
            -- Subconsulta para obtener la última evaluación de cada colaborador
            SELECT 
                e1.Colaborador_idColaborador, 
                e1.Calificacion, 
                e1.Fecharealizacion
            FROM evaluaciones e1
            INNER JOIN (
                SELECT 
                    Colaborador_idColaborador, 
                    MAX(Fecharealizacion) AS MaxFecha
                FROM evaluaciones
                GROUP BY Colaborador_idColaborador
            ) e2 ON e1.Colaborador_idColaborador = e2.Colaborador_idColaborador AND e1.Fecharealizacion = e2.MaxFecha
        ) e ON c.idColaborador = e.Colaborador_idColaborador
        WHERE c.id_jefe_fk = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_jefe);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $equipo_data[] = $row;
}
$stmt->close();

// Calcular estadísticas para el dashboard
$total_colaboradores = count($equipo_data);
$total_evaluaciones = 0;
$suma_calificaciones = 0;
$distribucion_calificaciones = [
    '5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0
];

foreach ($equipo_data as $miembro) {
    if (!is_null($miembro['Calificacion'])) {
        $total_evaluaciones++;
        $suma_calificaciones += $miembro['Calificacion'];
        $calif_redondeada = round($miembro['Calificacion']);
        if (array_key_exists($calif_redondeada, $distribucion_calificaciones)) {
            $distribucion_calificaciones[$calif_redondeada]++;
        }
    }
}

$calificacion_promedio = ($total_evaluaciones > 0) ? round($suma_calificaciones / $total_evaluaciones, 2) : 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Equipo - Sistema RRHH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fc; font-family: 'Poppins', sans-serif; }
        .container-main { max-width: 1100px; margin-top: 40px; }
        .report-card { border-radius: 1.25rem; box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.08); background: #fff; border: none; }
        .report-card .card-header { background: transparent; border-bottom: 1px solid #e9ecef; padding: 1.5rem; }
        .report-card .card-header h3 { font-weight: 700; color: #5e72e4; margin: 0; }
        .stat-card { text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #f6f9fc 0%, #e9ecef 100%); border-radius: 1rem; }
        .stat-icon { font-size: 2.5rem; margin-bottom: 0.5rem; color: #5e72e4; }
        .stat-value { font-size: 2.2rem; font-weight: 700; color: #32325d; }
        .stat-label { font-size: 0.9rem; color: #8898aa; text-transform: uppercase; letter-spacing: 0.5px; }
        .table thead th { background: #f6f9fc; color: #8898aa; font-weight: 600; border: none; text-align: center; }
        .table tbody td { vertical-align: middle; text-align: center; }
        .stars { color: #ffd600; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container container-main">
        <div class="card report-card">
            <div class="card-header">
                <h3><i class="bi bi-clipboard-data-fill me-2"></i>Reporte de Rendimiento del Equipo</h3>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4 g-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                            <div class="stat-value"><?= $total_colaboradores ?></div>
                            <div class="stat-label">Colaboradores</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="bi bi-star-half"></i></div>
                            <div class="stat-value"><?= $calificacion_promedio ?> <span style="font-size: 1.2rem;">/ 5</span></div>
                            <div class="stat-label">Calificación Promedio</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-value"><?= $total_evaluaciones ?></div>
                            <div class="stat-label">Evaluaciones Realizadas</div>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="text-center mb-3">Distribución de Calificaciones</h5>
                    <canvas id="performanceChart"></canvas>
                </div>

                <h5 class="mb-3">Detalle del Equipo</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Departamento</th>
                                <th>Última Calificación</th>
                                <th>Fecha de Evaluación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($equipo_data)): ?>
                                <?php foreach ($equipo_data as $miembro): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($miembro['nombre_completo']) ?></td>
                                        <td><?= htmlspecialchars($miembro['departamento']) ?></td>
                                        <td>
                                            <?php if (!is_null($miembro['Calificacion'])): ?>
                                                <span class="stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi <?= $i <= round($miembro['Calificacion']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                                    <?php endfor; ?>
                                                </span>
                                                (<?= number_format($miembro['Calificacion'], 1) ?>)
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !is_null($miembro['Fecharealizacion']) ? date('d/m/Y', strtotime($miembro['Fecharealizacion'])) : '<span class="text-muted">N/A</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted p-4">No tienes colaboradores asignados a tu equipo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['5 Estrellas', '4 Estrellas', '3 Estrellas', '2 Estrellas', '1 Estrella'],
                    datasets: [{
                        label: 'Nº de Colaboradores',
                        data: [
                            <?= $distribucion_calificaciones['5'] ?>,
                            <?= $distribucion_calificaciones['4'] ?>,
                            <?= $distribucion_calificaciones['3'] ?>,
                            <?= $distribucion_calificaciones['2'] ?>,
                            <?= $distribucion_calificaciones['1'] ?>
                        ],
                        backgroundColor: [
                            '#2dce89',
                            '#5e72e4',
                            '#ffd600',
                            '#fb6340',
                            '#f5365c'
                        ],
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Rendimiento general del equipo' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>