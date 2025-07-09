<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['rol'] != 1 && $_SESSION['rol'] != 4)) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Obtener colaboradores activos
$colaboradores = [];
$res = $conn->query("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2, c.fecha_ingreso, c.salario_bruto
                     FROM colaborador c
                     INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                     ORDER BY p.Nombre ASC");
while ($row = $res->fetch_assoc()) $colaboradores[] = $row;

$colaborador = null;
if (isset($_GET['colaborador'])) {
    $idc = intval($_GET['colaborador']);
    foreach ($colaboradores as $c) {
        if ($c['idColaborador'] == $idc) $colaborador = $c;
    }
}

$msg = "";
$msg_type = "success";

// Función AJUSTADA a ley costarricense 2025
function calcular_liquidacion_cr($salario_base, $fecha_ingreso, $fecha_salida, $motivo, $salarios_ultimo_periodo = []) {
    $dias = (strtotime($fecha_salida) - strtotime($fecha_ingreso)) / 86400;
    $anos = floor($dias/365);
    $dias_remanente = $dias - ($anos * 365);

    // PREAVISO
    $preaviso = ($motivo == "Despido") ? $salario_base : 0;

    // CESANTÍA (solo en despido sin responsabilidad)
    $cesantia = 0;
    if ($motivo == "Despido") {
        if ($anos == 0) {
            $cesantia = ($dias/365) * 7 * ($salario_base/30);
        } elseif ($anos == 1) {
            $cesantia = 7 * ($salario_base/30);
        } elseif ($anos == 2) {
            $cesantia = 14 * ($salario_base/30);
        } elseif ($anos == 3) {
            $cesantia = 19.5 * ($salario_base/30);
        } elseif ($anos == 4) {
            $cesantia = 30 * ($salario_base/30);
        } elseif ($anos == 5) {
            $cesantia = 60 * ($salario_base/30);
        } elseif ($anos == 6) {
            $cesantia = 90 * ($salario_base/30);
        } elseif ($anos == 7) {
            $cesantia = 120 * ($salario_base/30);
        } else {
            $cesantia = 150 * ($salario_base/30);
        }
    }

    // VACACIONES: proporcional a días laborados (12 días por año)
    $vacaciones = ($dias/365)*12*($salario_base/30);

    // AGUINALDO proporcional
    if (count($salarios_ultimo_periodo) > 0) {
        $aguinaldo = array_sum($salarios_ultimo_periodo)/12;
    } else {
        $meses = round($dias/30.4, 2);
        $aguinaldo = ($salario_base * $meses) / 12;
    }

    return [
        'dias_laborados' => round($dias),
        'preaviso'       => round($preaviso,2),
        'cesantia'       => round($cesantia,2),
        'vacaciones'     => round($vacaciones,2),
        'aguinaldo'      => round($aguinaldo,2)
    ];
}

// Guardar liquidación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_liq'])) {
    $id_colaborador_fk = intval($_POST['colaborador']);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $motivo = $_POST['motivo'];
    $salario_base = floatval($_POST['salario_base']);
    $dias_laborados = intval($_POST['dias_laborados']);
    $preaviso = floatval($_POST['preaviso']);
    $cesantia = floatval($_POST['cesantia']);
    $vacaciones = floatval($_POST['vacaciones']);
    $aguinaldo = floatval($_POST['aguinaldo']);
    $deducciones = floatval($_POST['deducciones']);
    $total = floatval($_POST['total']);
    $detalle = trim($_POST['detalle']);

    $stmt = $conn->prepare("INSERT INTO liquidaciones 
        (id_colaborador_fk, fecha, motivo, salario_base, dias_laborados, preaviso, cesantia, vacaciones, aguinaldo, deducciones, total, detalle)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issiddddddds", $id_colaborador_fk, $fecha, $motivo, $salario_base, $dias_laborados, $preaviso, $cesantia, $vacaciones, $aguinaldo, $deducciones, $total, $detalle);
    if ($stmt->execute()) {
        $msg = "¡Liquidación registrada según ley!";
        $msg_type = "success";
    } else {
        $msg = "Error al guardar la liquidación.";
        $msg_type = "danger";
    }
    $stmt->close();
}
?>

<?php include 'header.php'; ?>

<style>
.liq-card {
    background: #fff;
    border-radius: 2rem;
    max-width: 650px;
    margin: 2.6rem auto 2rem auto;
    box-shadow: 0 8px 32px #13c6f135;
    padding: 2.2rem 2.3rem 2rem 2.3rem;
    border: 1.5px solid #d0f0fc;
    animation: fadeInLiq .7s;
}
@keyframes fadeInLiq { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}
.liq-title {
    font-weight: 900; font-size: 2rem; color: #18a8e0; letter-spacing: .7px;
    margin-bottom: .7rem;
    text-align:center;
}
.liq-table th, .liq-table td {
    padding: .50rem .6rem;
    text-align: right;
    vertical-align: middle;
    font-size: 1.07rem;
}
.liq-table th {background: #f2fbff; color: #0d6797; font-weight:700;}
.liq-table tfoot td {font-size:1.18rem; font-weight:900; color:#188b4f; border-top: 2px solid #c8e6dd;}
input[type="number"], input[type="text"] {text-align:right;}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="liq-card">
        <div class="liq-title">
            <i class="bi bi-calculator"></i> Calcular Liquidación según ley
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Selección de colaborador -->
        <form method="get" class="mb-4" style="text-align:center;">
            <label for="colaborador" style="font-weight:700;">Colaborador:</label>
            <select name="colaborador" id="colaborador" class="form-select d-inline-block" style="width:260px;display:inline-block;" onchange="this.form.submit()">
                <option value="">Seleccione...</option>
                <?php foreach ($colaboradores as $col): ?>
                    <option value="<?= $col['idColaborador'] ?>" <?= isset($_GET['colaborador']) && $_GET['colaborador'] == $col['idColaborador']?'selected':'' ?>>
                        <?= htmlspecialchars($col['Nombre'].' '.$col['Apellido1'].' '.$col['Apellido2']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-info ms-2" type="submit">Ver</button></noscript>
        </form>

        <?php if ($colaborador): ?>
        <form method="post" id="liqForm">
            <input type="hidden" name="colaborador" value="<?= $colaborador['idColaborador'] ?>">
            <div class="mb-2"><b>Nombre:</b> <?= htmlspecialchars($colaborador['Nombre'].' '.$colaborador['Apellido1'].' '.$colaborador['Apellido2']) ?></div>
            <div class="mb-2"><b>Salario Base:</b> ₡<span id="salario_base_show"><?= number_format($colaborador['salario_bruto'],2) ?></span>
                <input type="hidden" name="salario_base" id="salario_base" value="<?= $colaborador['salario_bruto'] ?>">
            </div>
            <div class="mb-2"><b>Fecha Ingreso:</b> <?= $colaborador['fecha_ingreso'] ?></div>
            <div class="mb-2">
                <label class="form-label">Fecha Salida:</label>
                <input type="date" name="fecha" id="fecha" class="form-control d-inline-block" style="max-width:180px;" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Motivo:</label>
                <select name="motivo" id="motivo" class="form-select" style="max-width:250px;display:inline-block;" required>
                    <option value="Renuncia">Renuncia</option>
                    <option value="Despido">Despido</option>
                    <option value="Jubilación">Jubilación</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <?php
            // Lógica ajustada a la ley CR (ajusta motivo/fecha según POST si ya está definido)
            $motivo = isset($_POST['motivo']) ? $_POST['motivo'] : 'Renuncia';
            $fecha_salida = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
            $sug = calcular_liquidacion_cr(
                $colaborador['salario_bruto'],
                $colaborador['fecha_ingreso'],
                $fecha_salida,
                $motivo
            );
            ?>

            <table class="table liq-table mb-2">
                <tbody>
                    <tr>
                        <th>Días Laborados:</th>
                        <td><input type="number" name="dias_laborados" id="dias_laborados" class="form-control" value="<?= $sug['dias_laborados'] ?>" readonly></td>
                    </tr>
                    <tr>
                        <th>Preaviso:</th>
                        <td><input type="number" name="preaviso" id="preaviso" class="form-control" step="0.01" value="<?= $sug['preaviso'] ?>" required readonly></td>
                    </tr>
                    <tr>
                        <th>Cesantía:</th>
                        <td><input type="number" name="cesantia" id="cesantia" class="form-control" step="0.01" value="<?= $sug['cesantia'] ?>" required readonly></td>
                    </tr>
                    <tr>
                        <th>Vacaciones:</th>
                        <td><input type="number" name="vacaciones" id="vacaciones" class="form-control" step="0.01" value="<?= $sug['vacaciones'] ?>" required readonly></td>
                    </tr>
                    <tr>
                        <th>Aguinaldo:</th>
                        <td><input type="number" name="aguinaldo" id="aguinaldo" class="form-control" step="0.01" value="<?= $sug['aguinaldo'] ?>" required readonly></td>
                    </tr>
                    <tr>
                        <th>Deducciones:</th>
                        <td><input type="number" name="deducciones" id="deducciones" class="form-control" step="0.01" value="0" required></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align:left;"><b>Total a Pagar:</b></td>
                        <td><input type="number" name="total" id="total" class="form-control" step="0.01" value="<?= $sug['preaviso']+$sug['cesantia']+$sug['vacaciones']+$sug['aguinaldo'] ?>" readonly></td>
                    </tr>
                </tfoot>
            </table>
            <div class="mb-3">
                <label>Detalle / Comentario:</label>
                <textarea name="detalle" class="form-control" rows="2"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" name="guardar_liq" class="btn btn-success px-4"><i class="bi bi-save"></i> Guardar Liquidación</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Si cambias deducciones, actualiza el total
document.getElementById('deducciones')?.addEventListener('input', function() {
    let preaviso = parseFloat(document.getElementById('preaviso').value) || 0;
    let cesantia = parseFloat(document.getElementById('cesantia').value) || 0;
    let vacaciones = parseFloat(document.getElementById('vacaciones').value) || 0;
    let aguinaldo = parseFloat(document.getElementById('aguinaldo').value) || 0;
    let deducciones = parseFloat(this.value) || 0;
    let total = preaviso + cesantia + vacaciones + aguinaldo - deducciones;
    document.getElementById('total').value = total.toFixed(2);
});
</script>

<?php include 'footer.php'; ?>
