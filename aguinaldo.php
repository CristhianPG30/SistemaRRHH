<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$rol = $_SESSION['rol'] ?? 0;
$persona_id = $_SESSION['persona_id'] ?? 0; 
$colaborador_id = 0;

if ($rol == 2) {
    $q = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ? LIMIT 1");
    $q->bind_param("i", $persona_id);
    $q->execute();
    $res = $q->get_result();
    $col = $res->fetch_assoc();
    $colaborador_id = $col ? $col['idColaborador'] : 0;
} else {
    $colaborador_id = isset($_GET['colaborador']) ? intval($_GET['colaborador']) : 0;
}

$colaboradores = [];
if ($rol != 2) {
    $r = $conn->query("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2 
                       FROM colaborador c
                       INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                       ORDER BY p.Nombre ASC");
    while ($row = $r->fetch_assoc()) $colaboradores[] = $row;
}

$anio = date('Y');
$desde = date('Y-m-d', strtotime(($anio-1).'-12-01'));
$hasta = date('Y-m-d', strtotime($anio.'-11-30'));

$persona = null;
if ($colaborador_id) {
    $q = $conn->prepare("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2 
                       FROM colaborador c
                       INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                       WHERE c.idColaborador = ? LIMIT 1");
    $q->bind_param("i", $colaborador_id);
    $q->execute();
    $persona = $q->get_result()->fetch_assoc();
}

$salarios = [];
if ($colaborador_id) {
    $s = $conn->prepare("SELECT fecha_generacion, salario_bruto 
                       FROM planillas 
                       WHERE id_colaborador_fk = ?
                       AND fecha_generacion BETWEEN ? AND ?
                       ORDER BY fecha_generacion ASC");
    $s->bind_param("iss", $colaborador_id, $desde, $hasta);
    $s->execute();
    $res_s = $s->get_result();
    while ($row = $res_s->fetch_assoc()) $salarios[] = $row;
}

$total_salarios = 0;
foreach ($salarios as $row) $total_salarios += $row['salario_bruto'];
$aguinaldo = $total_salarios / 12;

?>

<?php include 'header.php'; ?>

<style>
.agu-card {
    background: #fff;
    border-radius: 2rem;
    max-width: 650px;
    margin: 2.5rem auto 2rem auto;
    box-shadow: 0 8px 32px #13c6f135;
    padding: 2.2rem 2.3rem 2rem 2.3rem;
    border: 1.5px solid #d0f0fc;
    animation: fadeInAgu .7s;
}
@keyframes fadeInAgu { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}
.agu-title {
    font-weight: 900; font-size: 2rem; color: #18a8e0; letter-spacing: .7px;
    margin-bottom: .7rem;
    text-align:center;
}
.agu-table th, .agu-table td {
    padding: .45rem .6rem;
    text-align: right;
    vertical-align: middle;
    font-size: 1.07rem;
}
.agu-table th {background: #f2fbff; color: #0d6797; font-weight:700;}
.agu-table tfoot td {font-size:1.18rem; font-weight:900; color:#188b4f; border-top: 2px solid #c8e6dd;}
</style>

<div class="container" style="margin-left: 280px;">

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show" role="alert" style="max-width: 650px; margin: 1rem auto;">
            <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="agu-card">
        <div class="agu-title">
            <i class="bi bi-gift"></i> Cálculo de Aguinaldo <?= $rol != 2 ? 'por Colaborador' : 'Personal' ?>
        </div>
        
        <?php if ($rol != 2): ?>
        <form class="mb-3" method="get" style="text-align:center;">
            <label for="colaborador" style="font-weight:700;">Colaborador:</label>
            <select name="colaborador" id="colaborador" class="form-select d-inline-block" style="width:260px;display:inline-block;" onchange="this.form.submit()">
                <option value="">Seleccione...</option>
                <?php foreach ($colaboradores as $col): ?>
                    <option value="<?= $col['idColaborador'] ?>" <?= $colaborador_id==$col['idColaborador']?'selected':'' ?>>
                        <?= htmlspecialchars($col['Nombre'].' '.$col['Apellido1'].' '.$col['Apellido2']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <?php if ($persona): ?>
            <div class="mb-2" style="text-align:center;">
                <div style="font-weight:700; color:#1567b6;">
                    <?= htmlspecialchars($persona['Nombre'].' '.$persona['Apellido1'].' '.$persona['Apellido2']) ?>
                </div>
                <div style="color:#888;font-size:.98rem;">
                    <i class="bi bi-calendar"></i>
                    Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>
                </div>
            </div>
            <div class="table-responsive">
            <table class="table agu-table mb-2">
                <thead>
                    <tr>
                        <th style="text-align:left;">Mes</th>
                        <th>Salario Bruto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($salarios)): ?>
                        <?php 
                        $meses_espanol = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                        foreach ($salarios as $row): ?>
                            <tr>
                                <td style="text-align:left;"><?= htmlspecialchars($meses_espanol[date('n', strtotime($row['fecha_generacion']))]) . ' ' . date('Y', strtotime($row['fecha_generacion'])) ?></td>
                                <td>₡<?= number_format($row['salario_bruto'],2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center text-muted">No hay salarios registrados en este período.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align:left;"><b>Total salarios:</b></td>
                        <td><b>₡<?= number_format($total_salarios,2) ?></b></td>
                    </tr>
                    <tr>
                        <td style="text-align:left;"><b>Aguinaldo legal a pagar:</b></td>
                        <td><b>₡<?= number_format($aguinaldo,2) ?></b></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i>
                <b>Nota legal:</b> El aguinaldo es la doceava parte (1/12) de la suma total de salarios brutos recibidos entre el 1 de diciembre del año anterior y el 30 de noviembre actual.
            </div>
        <?php elseif($colaborador_id): ?>
            <div class="alert alert-warning text-center mt-3">
                <i class="bi bi-emoji-neutral"></i> No se encontró información para el colaborador seleccionado.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>