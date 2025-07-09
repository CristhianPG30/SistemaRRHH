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

$sql = "SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2
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

// Obtener registros de aguinaldo para este colaborador
$aguinaldos = [];
$sql = "SELECT periodo, monto_calculado, monto_pagado, fecha_pago
        FROM aguinaldo
        WHERE id_colaborador_fk = ?
        ORDER BY periodo DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_colaborador);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $aguinaldos[] = $row;
$stmt->close();
?>

<?php include 'header.php'; ?>

<style>
.agu-glass-card {
    background: rgba(255,255,255,0.89);
    border-radius: 2.1rem;
    max-width: 630px;
    margin: 2.8rem auto 2.5rem auto;
    box-shadow: 0 10px 38px #1de0cf2b, 0 2px 7px #6de5e91c;
    padding: 2.2rem 2.3rem 2.2rem 2.3rem;
    border: 1.5px solid #d0f0fc;
    animation: fadeInAgu .7s;
    backdrop-filter: blur(11px);
}
@keyframes fadeInAgu { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}
.agu-title {
    font-weight: 900; font-size: 2.25rem; color: #0bbf90; letter-spacing: .8px; margin-bottom:1.1rem;text-align:center;
    text-shadow:0 3px 14px #1ad7fb29;
}
.agu-label { font-weight:700; color:#20ad8e;}
.agu-table {
    border-radius: 1.3rem; overflow: hidden; margin-top:1.2rem;
    box-shadow: 0 1px 9px #12c6b11c;
}
.agu-table th, .agu-table td {font-size:1.09rem;}
.agu-table th {
    background: linear-gradient(90deg,#e8fff7 40%,#dcf7f7 100%);
    color:#14bda6;font-weight:800;letter-spacing:.7px;
    border-bottom: 2px solid #e0ece8;
}
.agu-table td {
    font-weight:600;
    border-bottom: 1.5px solid #e8f8f7;
}
.agu-table tr:last-child td { border-bottom: none; }
.agu-no {color:#a4b4bc;text-align:center;font-size:1.16rem;}
.agu-tag-pagado {
    background: #17cfaa; color: #fff; padding: .21rem .8rem; border-radius: 1rem;
    font-size: .96rem; margin-left: .5rem; font-weight:700;
}
.agu-tag-pendiente {
    background: #ffe498; color: #9b6e13; padding: .21rem .8rem; border-radius: 1rem;
    font-size: .96rem; margin-left: .5rem; font-weight:700;
}
.agu-filter-bar {
    margin-bottom: 1.2rem; margin-top: .7rem;
    display: flex; justify-content: end; align-items: center; gap:.9rem;
}
.agu-filter-bar input {
    border-radius: 1.4rem; font-size: 1.08rem; padding:.5rem 1.1rem; max-width:180px;
}
@media (max-width: 700px) {
    .agu-glass-card { padding:1.3rem .4rem 1.4rem .4rem; border-radius:1.1rem; }
}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="agu-glass-card animate__animated animate__fadeIn">
        <div class="agu-title"><i class="bi bi-gift"></i> Mi Aguinaldo</div>
        <div class="alert alert-info text-center mb-3" style="font-size:1.07rem;">
            El aguinaldo mostrado es <b>informativo</b>, según los registros de Recursos Humanos.<br>
            Consulta con RRHH para detalles oficiales.
        </div>
        <div class="agu-label mb-2">
            Colaborador: <?= htmlspecialchars($datos_colab['Nombre'].' '.$datos_colab['Apellido1'].' '.$datos_colab['Apellido2']) ?>
        </div>
        <div class="agu-filter-bar">
            <label for="filtroAguinaldo" style="color:#14bda6;font-weight:600;">Filtrar por año: </label>
            <input type="text" id="filtroAguinaldo" class="form-control" placeholder="Ej: 2024">
        </div>
        <div class="table-responsive">
            <table class="table agu-table mt-1" id="tablaAguinaldo">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Monto calculado</th>
                        <th>Monto pagado</th>
                        <th>Fecha de pago</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($aguinaldos): ?>
                        <?php foreach ($aguinaldos as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['periodo']) ?></td>
                                <td>
                                    ₡<?= number_format($a['monto_calculado'],2) ?>
                                </td>
                                <td>
                                    ₡<?= number_format($a['monto_pagado'],2) ?>
                                    <?php
                                    if ($a['monto_pagado'] >= $a['monto_calculado'] && $a['monto_pagado'] > 0) {
                                        echo '<span class="agu-tag-pagado">Pagado</span>';
                                    } elseif ($a['monto_pagado'] > 0) {
                                        echo '<span class="agu-tag-pagado">Parcial</span>';
                                    } else {
                                        echo '<span class="agu-tag-pendiente">Pendiente</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= $a['fecha_pago'] ? htmlspecialchars(date('d/m/Y', strtotime($a['fecha_pago']))) : '--' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="agu-no">
                                <i class="bi bi-info-circle"></i> Aún no tienes registros de aguinaldo.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- Interactividad: filtrado por año en vivo -->
<script>
document.getElementById('filtroAguinaldo').addEventListener('keyup', function() {
    var filtro = this.value.toLowerCase();
    var filas = document.querySelectorAll('#tablaAguinaldo tbody tr');
    filas.forEach(function(f){
        let txt = f.textContent.toLowerCase();
        f.style.display = (txt.indexOf(filtro) !== -1 || filtro == "") ? '' : 'none';
    });
});
</script>
