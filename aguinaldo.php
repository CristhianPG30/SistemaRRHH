<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$rol = $_SESSION['rol'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Obtener el colaborador según rol
$colaborador_id = 0;
if ($rol == 2) { // Colaborador
    $q = $conn->query("SELECT idColaborador FROM colaborador WHERE id_persona_fk = $user_id LIMIT 1");
    $col = $q->fetch_assoc();
    $colaborador_id = $col ? $col['idColaborador'] : 0;
} else { // Admin, RRHH, Jefatura
    $colaborador_id = isset($_GET['colaborador']) ? intval($_GET['colaborador']) : 0;
}

// Obtener listado de colaboradores para el filtro (solo para Admin, RRHH, Jefatura)
$colaboradores = [];
if ($rol != 2) {
    $r = $conn->query("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2 
                       FROM colaborador c
                       INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                       ORDER BY p.Nombre ASC");
    while ($row = $r->fetch_assoc()) $colaboradores[] = $row;
}

// Rango de cálculo: del 1 de diciembre año pasado al 30 de noviembre actual
$anio = date('Y');
$desde = date('Y-m-d', strtotime(($anio-1).'-12-01'));
$hasta = date('Y-m-d', strtotime($anio.'-11-30'));

// Obtener info colaborador
$persona = null;
if ($colaborador_id) {
    $q = $conn->query("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2 
                       FROM colaborador c
                       INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                       WHERE c.idColaborador = $colaborador_id LIMIT 1");
    $persona = $q->fetch_assoc();
}

// Obtener salarios brutos del periodo
$salarios = [];
if ($colaborador_id) {
    $s = $conn->query("SELECT fecha_generacion, salario_bruto 
                       FROM planillas 
                       WHERE id_colaborador_fk = $colaborador_id
                       AND fecha_generacion BETWEEN '$desde' AND '$hasta'
                       ORDER BY fecha_generacion ASC");
    while ($row = $s->fetch_assoc()) $salarios[] = $row;
}

// Calcular aguinaldo legalmente
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
    <div class="agu-card">
        <div class="agu-title">
            <i class="bi bi-gift"></i> Cálculo de Aguinaldo Legal 2025
        </div>
        <div class="mb-2" style="text-align:center;">
            <div style="font-weight:700; color:#1567b6;">
                <?= $persona ? htmlspecialchars($persona['Nombre'].' '.$persona['Apellido1'].' '.$persona['Apellido2']) : 'Seleccione un colaborador...' ?>
            </div>
            <div style="color:#888;font-size:.98rem;">
                <i class="bi bi-calendar"></i>
                Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>
            </div>
        </div>
        <?php if ($rol != 2): ?>
        <!-- Filtro de colaborador -->
        <form class="mb-3" method="get" style="text-align:center;">
            <label for="colaborador" style="font-weight:700;">Colaborador:</label>
            <select name="colaborador" id="colaborador" class="form-select d-inline-block" style="width:260px;display:inline-block;">
                <option value="">Seleccione...</option>
                <?php foreach ($colaboradores as $col): ?>
                    <option value="<?= $col['idColaborador'] ?>" <?= $colaborador_id==$col['idColaborador']?'selected':'' ?>>
                        <?= htmlspecialchars($col['Nombre'].' '.$col['Apellido1'].' '.$col['Apellido2']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-info ms-2" type="submit"><i class="bi bi-search"></i> Ver</button>
        </form>
        <?php endif; ?>

        <?php if ($persona): ?>
            <div class="table-responsive">
            <table class="table agu-table mb-2">
                <thead>
                    <tr>
                        <th style="text-align:left;">Mes</th>
                        <th>Salario Bruto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salarios as $row): ?>
                        <tr>
                            <td style="text-align:left;"><?= date('M Y', strtotime($row['fecha_generacion'])) ?></td>
                            <td>₡<?= number_format($row['salario_bruto'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
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
                <i class="bi bi-exclamation-circle"></i>
                <b>Nota legal:</b> El aguinaldo es la doceava parte (1/12) de la suma total de salarios brutos recibidos entre el 1 de diciembre del año anterior y el 30 de noviembre actual, según Ley 2412, art. 2.<br>
                El pago debe hacerse a más tardar el 20 de diciembre.
            </div>
        <?php elseif($colaborador_id): ?>
            <div class="alert alert-warning text-center mt-3">
                <i class="bi bi-emoji-neutral"></i> No se encontró información de aguinaldo para el colaborador en el periodo.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
