<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['rol'] == 2)) { // Solo Admin, RRHH, Jefatura
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// 1. Total colaboradores activos
$r = $conn->query("SELECT COUNT(*) as total FROM colaborador WHERE activo = 1");
$colaboradores_activos = $r->fetch_assoc()['total'] ?? 0;

// 2. Colaboradores por departamento
$departamentos = [];
$d = $conn->query("SELECT dep.descripcion as departamento, COUNT(c.idColaborador) as cantidad
                   FROM colaborador c
                   INNER JOIN departamento dep ON c.id_departamento_fk = dep.idDepartamento
                   WHERE c.activo = 1
                   GROUP BY departamento");
while ($row = $d->fetch_assoc()) $departamentos[] = $row;

// 3. Total liquidaciones y monto pagado (AJUSTADO a tu tabla)
$l = $conn->query("SELECT COUNT(*) as total_liq, SUM(monto_neto) as monto FROM liquidaciones");
$liq = $l->fetch_assoc();
$total_liquidaciones = $liq['total_liq'] ?? 0;
$total_liq_monto = $liq['monto'] ?? 0;

// 4. Promedio de salario bruto
$s = $conn->query("SELECT AVG(salario_bruto) as promedio FROM colaborador WHERE activo = 1");
$prom_salario = $s->fetch_assoc()['promedio'] ?? 0;

// 5. Promedio aguinaldo (actual)
$anio = date('Y');
$desde = date('Y-m-d', strtotime(($anio-1).'-12-01'));
$hasta = date('Y-m-d', strtotime($anio.'-11-30'));
$a = $conn->query("SELECT AVG(suma_salario) as promedio
                   FROM (
                        SELECT id_colaborador_fk, SUM(salario_bruto) as suma_salario
                        FROM planillas
                        WHERE fecha_generacion BETWEEN '$desde' AND '$hasta'
                        GROUP BY id_colaborador_fk
                    ) as t");
$prom_aguinaldo = ($a && $a->num_rows > 0) ? ($a->fetch_assoc()['promedio']/12) : 0;

// 6. Total días de vacaciones
$v = $conn->query("SELECT SUM(dias) as total_vac FROM vacaciones WHERE estado = 'Aprobado'");
$total_vacaciones = $v->fetch_assoc()['total_vac'] ?? 0;

// 7. Total días de permisos (solo si existe la tabla permisos)
$total_permisos = "No disponible";
try {
    $p = $conn->query("SELECT SUM(DATEDIFF(fecha_final, fecha_inicio)+1) as total_perm FROM permisos WHERE estado = 'Aprobado'");
    $row_perm = $p ? $p->fetch_assoc() : ['total_perm'=>0];
    $total_permisos = $row_perm['total_perm'] ?? 0;
} catch (Exception $e) {
    $total_permisos = "No disponible";
}
?>

<?php include 'header.php'; ?>

<style>
.rep-card {background: #fff; border-radius: 2rem; box-shadow:0 8px 32px #13c6f135; padding:2rem 2rem 2.5rem 2rem; margin:2.5rem auto 2rem auto; max-width:980px;}
.rep-indicador {display:flex;align-items:center;gap:1.1rem; border-radius:1.3rem;background:#f5fbfe;padding:1.2rem 2rem;margin-bottom:1.3rem; box-shadow:0 2px 9px #2bcdff13;}
.rep-indicador .icon {font-size:2.1rem;color:#23b6ee;}
.rep-indicador .dato {font-size:1.6rem;font-weight:900;color:#1567b6;}
.rep-indicador .desc {font-size:1.07rem; font-weight:700; color:#888;}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="rep-card">
        <h2 class="mb-4" style="font-weight:900;letter-spacing:.7px;color:#179ad7;">
            <i class="bi bi-bar-chart-fill"></i> Reporte Global de Recursos Humanos
        </h2>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="dato"><?= $colaboradores_activos ?></div>
                        <div class="desc">Colaboradores activos</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="dato">₡<?= number_format($prom_salario,2) ?></div>
                        <div class="desc">Salario bruto promedio</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-gift"></i></div>
                    <div>
                        <div class="dato">₡<?= number_format($prom_aguinaldo,2) ?></div>
                        <div class="desc">Aguinaldo promedio <?= date('Y') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-bag-check"></i></div>
                    <div>
                        <div class="dato"><?= $total_liquidaciones ?></div>
                        <div class="desc">Liquidaciones realizadas</div>
                        <div style="font-size:.98rem;color:#16a454;"><b>Total pagado:</b> ₡<?= number_format($total_liq_monto,2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-suitcase-lg"></i></div>
                    <div>
                        <div class="dato"><?= $total_vacaciones ?></div>
                        <div class="desc">Días de vacaciones tomados</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="rep-indicador">
                    <div class="icon"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="dato"><?= $total_permisos ?></div>
                        <div class="desc">Días de permisos tomados</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Gráfica de colaboradores por departamento -->
        <div class="card mt-4 mb-3 shadow" style="border-radius:1.1rem;">
            <div class="card-header bg-white" style="font-weight:700; color:#1567b6; font-size:1.13rem;">
                <i class="bi bi-diagram-3"></i> Distribución de colaboradores por departamento
            </div>
            <div class="card-body">
                <canvas id="depChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js para gráfica de departamentos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('depChart').getContext('2d');
const depData = {
    labels: <?= json_encode(array_column($departamentos,'departamento')) ?>,
    datasets: [{
        data: <?= json_encode(array_column($departamentos,'cantidad')) ?>,
        backgroundColor: [
            '#17b9ee','#8ae4fd','#44d9bc','#80efc6','#e1ffb1','#c0ffd1','#ffa7a7','#ffd6b1','#ffecb1'
        ]
    }]
};
const depChart = new Chart(ctx, {
    type: 'doughnut',
    data: depData,
    options: {
        responsive:true,
        plugins: {
            legend: {position:'bottom'}
        }
    }
});
</script>
<?php include 'footer.php'; ?>
