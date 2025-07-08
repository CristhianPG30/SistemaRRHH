<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;

// 1. Calcular aguinaldo actual (suma bruta últimos 12 meses dividido 12)
$sql = "SELECT 
    SUM(salario_bruto) as total,
    COUNT(DISTINCT MONTH(fecha_generacion)) as meses
    FROM planillas 
    WHERE id_colaborador_fk = ? AND fecha_generacion BETWEEN DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($total_bruto, $meses_trabajados);
$stmt->fetch();
$stmt->close();

$aguinaldo = $meses_trabajados > 0 ? ($total_bruto / 12) : 0;

// 2. Historial por año
$historial = [];
$sql = "SELECT YEAR(fecha_generacion) as anio, SUM(salario_bruto) as total_bruto, COUNT(DISTINCT MONTH(fecha_generacion)) as meses 
        FROM planillas 
        WHERE id_colaborador_fk = ?
        GROUP BY anio
        ORDER BY anio DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($anio, $total_anual, $meses_ano);
while ($stmt->fetch()) {
    $historial[] = [
        'anio' => $anio,
        'total' => $total_anual,
        'meses' => $meses_ano,
        'aguinaldo' => $meses_ano > 0 ? $total_anual / 12 : 0
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Aguinaldo - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #e3f2fd, #f7fcfe 100%);}
        .aguinaldo-card { background:#fff; border-radius:2.3rem; box-shadow:0 6px 40px #23b6ff13; padding:2.6rem; margin:2rem 0;}
        .aguinaldo-title { color:#15557a; font-weight:bold; font-size:2rem; text-align:center; margin-bottom:1.6rem;}
        .aguinaldo-summary { background:linear-gradient(90deg,#f1fbff,#e2f5fd); border-radius:1.3rem; padding:1.4rem 2.1rem; margin-bottom:2.2rem; text-align:center;}
        .aguinaldo-label { color:#0d89c2; font-weight:600; }
        .aguinaldo-value { font-size:2.1rem; font-weight:700; color:#1493c2; }
        .table-aguinaldo th { background: #eaf7fd; color: #1b6faa;}
        .alert-info { border-radius:1.3rem; }
        @media(max-width:650px){.aguinaldo-card{padding:1.1rem;}.aguinaldo-title{font-size:1.3rem;}}
    </style>
</head>
<body>
<div class="container">
    <div class="aguinaldo-card animate__animated animate__fadeInDown">
        <div class="aguinaldo-title">
            <i class="bi bi-gift"></i> Mi Aguinaldo Proporcional
        </div>
        <div class="aguinaldo-summary mb-4">
            <div class="row justify-content-center">
                <div class="col-12 col-md-4">
                    <div class="aguinaldo-label mb-1"><i class="bi bi-calendar-event"></i> Período actual</div>
                    <div><?= date('Y', strtotime('-1 month')) ?> - <?= date('Y') ?></div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="aguinaldo-label mb-1"><i class="bi bi-cash-coin"></i> Aguinaldo estimado</div>
                    <div class="aguinaldo-value">₡<?= number_format($aguinaldo,2) ?></div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="aguinaldo-label mb-1"><i class="bi bi-bar-chart"></i> Meses laborados</div>
                    <div><?= $meses_trabajados ?> / 12</div>
                </div>
            </div>
        </div>
        <div class="alert alert-info text-center mt-2 mb-4">
            <i class="bi bi-info-circle"></i>
            El aguinaldo se calcula sumando todos tus salarios brutos de los últimos 12 meses y dividiendo entre 12, según la ley costarricense.
        </div>
        <h5 class="mb-3 mt-4 text-primary"><i class="bi bi-clock-history"></i> Historial de Aguinaldos</h5>
        <div class="table-responsive">
            <table class="table table-aguinaldo table-bordered text-center">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>Salario total (₡)</th>
                        <th>Meses trabajados</th>
                        <th>Aguinaldo recibido (₡)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($historial): ?>
                    <?php foreach ($historial as $row): ?>
                        <tr>
                            <td><?= $row['anio'] ?></td>
                            <td><?= number_format($row['total'],2) ?></td>
                            <td><?= $row['meses'] ?></td>
                            <td><?= number_format($row['aguinaldo'],2) ?></td>
                        </tr>
                    <?php endforeach ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-muted">No hay historial registrado.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
