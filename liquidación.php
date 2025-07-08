<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;

// 1. Obtener datos laborales del colaborador
$sql = "SELECT c.fecha_ingreso, p.Nombre, p.Apellido1, p.Apellido2
        FROM colaborador c 
        JOIN persona p ON c.id_persona_fk = p.idPersona
        WHERE c.idColaborador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($fecha_ingreso, $nombre, $apellido1, $apellido2);
$stmt->fetch();
$stmt->close();

$fecha_hoy = date('Y-m-d');
$fecha_inicio = $fecha_ingreso ? new DateTime($fecha_ingreso) : null;
$fecha_fin = new DateTime($fecha_hoy);
$antiguedad = $fecha_inicio ? $fecha_inicio->diff($fecha_fin) : null;
$anios = $antiguedad ? $antiguedad->y : 0;
$meses = $antiguedad ? $antiguedad->m : 0;
$dias = $antiguedad ? $antiguedad->d : 0;

// 2. Obtener salario promedio últimos 6 meses
$sql = "SELECT AVG(salario_bruto) FROM planillas WHERE id_colaborador_fk = ? AND fecha_generacion >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($salario_prom);
$stmt->fetch();
$stmt->close();
$salario_prom = $salario_prom ?: 0;

// 3. Calcular vacaciones no disfrutadas
// Días generados por ley (12 por año trabajado)
$dias_generados = $anios * 12 + intval(($meses / 12) * 12);
// Días disfrutados
$sql = "SELECT SUM(DATEDIFF(fecha_fin, fecha_inicio)+1) FROM permisos WHERE id_colaborador_fk = ? AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE Descripcion LIKE '%Vacaciones%' LIMIT 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($dias_tomados);
$stmt->fetch();
$stmt->close();
$dias_tomados = $dias_tomados ?: 0;
$dias_pendientes = max(0, $dias_generados - $dias_tomados);

// 4. Aguinaldo proporcional (últimos 12 meses)
$sql = "SELECT SUM(salario_bruto) FROM planillas WHERE id_colaborador_fk = ? AND fecha_generacion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$stmt->bind_result($aguinaldo_total);
$stmt->fetch();
$stmt->close();
$aguinaldo_proporcional = ($aguinaldo_total / 12) * (($meses + ($dias/30)) / 12);

// 5. Cesantía y preaviso (según años)
$preaviso = $anios >= 1 ? $salario_prom : ($salario_prom * 0.5);
$cesantia = $anios >= 1 ? min($anios,8) * $salario_prom : 0; // Hasta 8 años

// 6. Liquidación total simulada
$monto_vacaciones = ($salario_prom / 30) * $dias_pendientes;
$monto_aguinaldo = $aguinaldo_proporcional;
$monto_total = $preaviso + $cesantia + $monto_vacaciones + $monto_aguinaldo;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Liquidación - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body { background: linear-gradient(135deg,#e9f7ff,#f7fcfe 100%);}
        .liq-card { background:#fff; border-radius:2.3rem; box-shadow:0 6px 40px #23b6ff13; padding:2.6rem; margin:2rem 0;}
        .liq-title { color:#13558c; font-weight:bold; font-size:2rem; text-align:center; margin-bottom:1.7rem;}
        .liq-summary { background:linear-gradient(90deg,#f1fbff,#e2f5fd); border-radius:1.3rem; padding:1.3rem 2.1rem; margin-bottom:2.2rem;}
        .liq-label { color:#1292c5; font-weight:600; }
        .liq-value { font-size:1.5rem; font-weight:700; color:#1183b8; }
        .table-liq th { background: #eaf7fd; color: #2176ae;}
        .modal-header { background: #23b6ff11; }
        .modal-title { color:#1d6fa5; font-weight:600;}
        .badge-success { background:#12ce74; }
        .badge-warning { background:#ffe05c; color:#735900;}
        .badge-danger { background:#ee6d4d;}
        .animate__fadeInDown { animation-duration:.8s;}
        @media(max-width:650px){.liq-card{padding:1.1rem;}.liq-title{font-size:1.3rem;}}
    </style>
</head>
<body>
<div class="container">
    <div class="liq-card animate__animated animate__fadeInDown">
        <div class="liq-title">
            <i class="bi bi-bank2"></i> Cálculo Simulado de Mi Liquidación
        </div>
        <div class="liq-summary row text-center mb-4">
            <div class="col-6 col-md-3">
                <div class="liq-label">Colaborador</div>
                <div class="liq-value"><?= htmlspecialchars("$nombre $apellido1 $apellido2") ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="liq-label">Antigüedad</div>
                <div class="liq-value"><?= "$anios años, $meses meses" ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="liq-label">Salario Promedio</div>
                <div class="liq-value">₡<?= number_format($salario_prom,2) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="liq-label">Vacaciones Pendientes</div>
                <div class="liq-value"><?= $dias_pendientes ?> días</div>
            </div>
        </div>
        <h5 class="mb-4 mt-3 text-primary"><i class="bi bi-list-check"></i> Desglose de Beneficios</h5>
        <table class="table table-liq table-bordered text-center">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Monto (₡)</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Preaviso</td>
                    <td><?= number_format($preaviso,2) ?></td>
                    <td><span class="badge bg-info">1 salario promedio<?= $anios < 1 ? " x 0.5 (menos de 1 año)" : "" ?></span></td>
                </tr>
                <tr>
                    <td>Cesantía</td>
                    <td><?= number_format($cesantia,2) ?></td>
                    <td><span class="badge bg-success"><?= $anios >= 1 ? min($anios,8) . " salario(s)" : "No aplica" ?></span></td>
                </tr>
                <tr>
                    <td>Vacaciones no disfrutadas</td>
                    <td><?= number_format($monto_vacaciones,2) ?></td>
                    <td><span class="badge bg-warning"><?= $dias_pendientes ?> días</span></td>
                </tr>
                <tr>
                    <td>Aguinaldo proporcional</td>
                    <td><?= number_format($monto_aguinaldo,2) ?></td>
                    <td><span class="badge bg-secondary">Últimos 12 meses</span></td>
                </tr>
                <tr style="font-weight:700; font-size:1.09rem;">
                    <td>TOTAL LIQUIDACIÓN</td>
                    <td colspan="2" class="text-success">₡<?= number_format($monto_total,2) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="alert alert-info text-center mt-4" style="border-radius:1rem;">
            <i class="bi bi-info-circle"></i>
            <b>Nota:</b> Este cálculo es simulado según normativa costarricense y tu información registrada en el sistema. <br>
            Consulta RRHH para la liquidación oficial y personalizada.
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
