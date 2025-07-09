<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Buscar colaborador asociado al usuario logueado
$username = $_SESSION['username'];
$id_colaborador = null;
$datos_colab = null;

$sql = "SELECT c.idColaborador, c.fecha_ingreso, p.Nombre, p.Apellido1, p.Apellido2
        FROM usuario u
        INNER JOIN persona p ON u.id_persona_fk = p.idPersona
        INNER JOIN colaborador c ON c.id_persona_fk = p.idPersona
        WHERE u.username = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $id_colaborador = $row['idColaborador'];
    $datos_colab = $row;
}
$stmt->close();

if (!$id_colaborador) {
    include 'header.php';
    echo "<div class='alert alert-danger text-center' style='margin-left:280px;margin-top:40px;max-width:600px;'>
            No se pudo encontrar tu perfil de colaborador. Contacte al administrador.
          </div>";
    include 'footer.php';
    exit;
}

// Buscar salario promedio en planillas
$salario_prom = 0;
$res = $conn->query("SELECT AVG(salario_bruto) as salario_prom FROM planillas WHERE id_colaborador_fk = $id_colaborador AND salario_bruto > 0");
if ($res && $r = $res->fetch_assoc()) $salario_prom = round($r['salario_prom'],2);

// Si no hay salario registrado, toma el del colaborador
if ($salario_prom == 0) {
    $res2 = $conn->query("SELECT salario_bruto FROM colaborador WHERE idColaborador = $id_colaborador");
    if ($res2 && $r2 = $res2->fetch_assoc()) $salario_prom = round($r2['salario_bruto'],2);
}

// Calcular antigüedad
$fecha_ingreso = $datos_colab['fecha_ingreso'];
$fecha_actual = date('Y-m-d');
$antig = date_diff(date_create($fecha_ingreso), date_create($fecha_actual));
$anios = $antig->y;
$meses = $antig->m;

// Preaviso (Ley CR): 1 mes de salario si lleva 1 año o más (sino fracción proporcional)
$preaviso = $salario_prom * (($anios >= 1) ? 1 : ($anios + $meses/12));

// Cesantía: 1 mes de salario por año (máximo 8 años)
$anios_cesantia = min($anios, 8);
$cesantia = $anios_cesantia * $salario_prom;

// Vacaciones pendientes: 1.25 días por mes trabajado y 12 días al año, resta días tomados si llevas el control
$dias_pendientes = 0;
$res2 = $conn->query("SELECT SUM(dias) as dias_usados FROM vacaciones WHERE id_colaborador_fk = $id_colaborador AND estado = 'Aprobada'");
$dias_usados = ($row = $res2->fetch_assoc()) ? floatval($row['dias_usados']) : 0;
$total_gen = $anios*12 + $meses*1.25;
$dias_pendientes = max($total_gen - $dias_usados, 0);
// Monto vacaciones
$vacaciones = round($salario_prom / 30 * $dias_pendientes, 2);

$total_liquidacion = round($preaviso + $cesantia + $vacaciones, 2);
?>

<?php include 'header.php'; ?>

<style>
.liq-card {
    background: #fff;
    border-radius: 2.2rem;
    max-width: 570px;
    margin: 2.8rem auto 2rem auto;
    box-shadow: 0 8px 32px #13c6f135;
    padding: 2.2rem 2.3rem 2rem 2.3rem;
    border: 1.5px solid #d0f0fc;
    animation: fadeInLiq .7s;
}
@keyframes fadeInLiq { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}
.liq-title {
    font-weight: 900; font-size: 2rem; color: #1b94ce; letter-spacing: .8px; margin-bottom:1.1rem;text-align:center;
    text-shadow:0 3px 14px #1ad7fb29;
}
.liq-label { font-weight:700; color:#1695b9;}
.liq-resume {font-size:1.08rem;color:#1598a9;margin-bottom:.2rem;}
.liq-table th, .liq-table td {font-size:1.08rem;}
.liq-table th {background: #eaf7ff; color:#0d6797;}
.liq-table td {font-weight:600;}
.liq-total {font-size:1.29rem;color:#0a7fb3;font-weight:900;}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="liq-card">
        <div class="liq-title"><i class="bi bi-cash-coin"></i> Mi Liquidación (Estimado)</div>
        <div class="alert alert-info text-center mb-3" style="font-size:1.09rem;">
            <b>Este cálculo de liquidación es solo estimado</b>.<br>
            El monto oficial será tramitado y validado por Recursos Humanos según la ley costarricense y tu caso particular.
        </div>
        <div class="liq-resume">
            <b>Colaborador:</b> <?= htmlspecialchars($datos_colab['Nombre'].' '.$datos_colab['Apellido1'].' '.$datos_colab['Apellido2']) ?><br>
            <b>Fecha de ingreso:</b> <?= htmlspecialchars(date('d/m/Y', strtotime($fecha_ingreso))) ?><br>
            <b>Antigüedad:</b> <?= $anios ?> año<?= $anios!=1?'s':'' ?> <?= $meses ?> mes<?= $meses!=1?'es':'' ?><br>
            <b>Salario promedio:</b> ₡<?= number_format($salario_prom,2) ?>
        </div>
        <table class="table liq-table mt-3">
            <tr>
                <th>Preaviso</th>
                <td>₡<?= number_format($preaviso,2) ?></td>
            </tr>
            <tr>
                <th>Cesantía</th>
                <td>₡<?= number_format($cesantia,2) ?></td>
            </tr>
            <tr>
                <th>Vacaciones pendientes</th>
                <td>₡<?= number_format($vacaciones,2) ?> <span style="color:#888;font-size:.97rem;">(<?= round($dias_pendientes,1) ?> días)</span></td>
            </tr>
            <tr>
                <th class="liq-total">Total estimado</th>
                <td class="liq-total">₡<?= number_format($total_liquidacion,2) ?></td>
            </tr>
        </table>
        <div style="color:#1783b0;font-size:.97rem;text-align:center;margin-top:.9rem;">
            <i class="bi bi-info-circle"></i> Para más detalles, consulta con Recursos Humanos.
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
