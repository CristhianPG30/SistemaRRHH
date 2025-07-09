<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$persona_id = $_SESSION['persona_id'];
$mensaje = '';

// PROCESAR JUSTIFICACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['justificar_id'])) {
    $id = intval($_POST['justificar_id']);
    $motivo = trim($_POST['motivo']);
    if ($motivo != "") {
        $stmtCheck = $conn->prepare("SELECT estado FROM horas_extra WHERE Persona_idPersona = ? AND idPermisos = ?");
        $stmtCheck->bind_param("ii", $persona_id, $id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($estadoActual);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($estadoActual == 'Pendiente' || $estadoActual == 'Justificada') {
            $estado_justificada = 'Justificada';
            $stmt = $conn->prepare("UPDATE horas_extra SET Motivo = ?, estado = ? WHERE Persona_idPersona = ? AND idPermisos = ?");
            $stmt->bind_param("ssii", $motivo, $estado_justificada, $persona_id, $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "¡Hora extra justificada correctamente! Queda pendiente de revisión del jefe.";
        } else {
            $mensaje = "No se puede justificar porque ya fue revisada.";
        }
    } else {
        $mensaje = "Por favor, escribe el motivo de la justificación.";
    }
}

// TRAER HORAS EXTRA
$stmt = $conn->prepare("SELECT idPermisos, Fecha, hora_inicio, hora_fin, cantidad_horas, Motivo, estado, Observaciones
                        FROM horas_extra
                        WHERE Persona_idPersona = ?
                        ORDER BY Fecha DESC, hora_inicio DESC");
$stmt->bind_param("i", $persona_id);
$stmt->execute();
$result = $stmt->get_result();
$horasExtra = [];
while ($row = $result->fetch_assoc()) {
    $horasExtra[] = $row;
}
$stmt->close();
$conn->close();
?>

<?php include 'header.php'; ?>

<style>
.he-container {
    max-width: 950px;
    margin: 40px auto 0 auto;
    padding: 32px 22px 26px 22px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 4px 26px #e1edf630;
    border: 1px solid #e7edf5;
}
.he-title {
    font-size: 2rem;
    font-weight: 800;
    color: #195ca8;
    margin-bottom: 22px;
    text-align: left;
    letter-spacing: .2px;
}
.he-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #f9fcff;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 0;
    box-shadow: 0 2px 10px #b9d9ec12;
}
.he-table th, .he-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e8eef6;
    text-align: left;
    font-size: 1.02rem;
    vertical-align: middle;
}
.he-table th {
    background: #f2f6fa;
    color: #144b81;
    font-weight: 700;
    border-top: 1px solid #e3ecfa;
}
.he-table tr:last-child td {
    border-bottom: none;
}
.he-status {
    font-weight: 700;
    border-radius: 16px;
    padding: 3px 16px;
    font-size: .98em;
    display: inline-block;
    background: #e5e9ef;
    color: #13486b;
    letter-spacing: .03em;
}
.he-status-pend { background: #f8e6a0; color: #857214; }
.he-status-jus  { background: #bee7fa; color: #157099; }
.he-status-apr  { background: #b2f0cd; color: #14763b; }
.he-status-rech { background: #f3c7c7; color: #a33b3b; }
.he-btn-just {
    border-radius: 16px !important;
    padding: 6px 22px !important;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    color: #fff;
    background: #1976d2;
    transition: background .17s;
}
.he-btn-just:hover { background: #145ca3; }
.he-btn-just[disabled] { opacity:.66; pointer-events:none; }
@media (max-width: 1050px) {
    .he-container {padding: 18px 2px 14px 2px;}
    .he-title {font-size: 1.2rem;}
    .he-table th, .he-table td { font-size:.96rem; padding:8px 5px;}
}
</style>

<div class="he-container">
    <div class="he-title"><i class="bi bi-clock-history"></i> Mis Horas Extra</div>
    <?php if ($mensaje): ?>
        <div class="alert alert-info shadow-sm text-center mb-3" style="font-size:1.05rem;">
            <?= htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div style="text-align:right;margin-bottom:10px;">
        <input type="text" id="heBuscar" class="form-control" style="border-radius:16px;display:inline-block;width:240px;" placeholder="Buscar en historial...">
    </div>
    <div class="table-responsive">
        <table class="he-table" id="tablaHE">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Horas Extra</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                    <th style="text-align:center;">Justificar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($horasExtra)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No hay horas extra registradas</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($horasExtra as $hx): ?>
                        <tr>
                            <td><?= htmlspecialchars($hx['Fecha']) ?></td>
                            <td><?= htmlspecialchars($hx['hora_inicio']) ?></td>
                            <td><?= htmlspecialchars($hx['hora_fin']) ?></td>
                            <td><?= htmlspecialchars($hx['cantidad_horas']) ?></td>
                            <td>
                                <?= ($hx['estado'] == 'Pendiente') ? '<span class="text-muted">Sin justificar</span>' : htmlspecialchars($hx['Motivo']); ?>
                            </td>
                            <td>
                                <?php
                                if ($hx['estado'] == 'Pendiente') {
                                    echo '<span class="he-status he-status-pend">Pendiente</span>';
                                } else if ($hx['estado'] == 'Justificada') {
                                    echo '<span class="he-status he-status-jus">Justificada</span>';
                                } else if ($hx['estado'] == 'Aprobada') {
                                    echo '<span class="he-status he-status-apr">Aprobada</span>';
                                } else if ($hx['estado'] == 'Rechazada') {
                                    echo '<span class="he-status he-status-rech">Rechazada</span>';
                                } else {
                                    echo htmlspecialchars($hx['estado']);
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($hx['Observaciones']) ?></td>
                            <td style="text-align:center;">
                                <?php
                                if ($hx['estado'] == 'Pendiente') {
                                    echo '<button class="he-btn-just" onclick="mostrarJustificar('.$hx['idPermisos'].', \'\')">Justificar</button>';
                                } else if ($hx['estado'] == 'Justificada') {
                                    $motivoSafe = htmlspecialchars($hx['Motivo'], ENT_QUOTES);
                                    echo '<button class="he-btn-just" style="background:#1ba6db;" onclick="mostrarJustificar('.$hx['idPermisos'].', \''.$motivoSafe.'\')">Editar</button>';
                                } else if ($hx['estado'] == 'Aprobada') {
                                    echo '<button class="he-btn-just" style="background:#29b16b;" disabled>Aprobada</button>';
                                } else if ($hx['estado'] == 'Rechazada') {
                                    echo '<button class="he-btn-just" style="background:#d04d4d;" disabled>Rechazada</button>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL JUSTIFICAR -->
<div id="modalJustificar" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.13);">
    <div style="background:white; max-width:410px; margin:5% auto; padding:2em 1.3em 1.7em 1.3em; border-radius:1.25em; position:relative; box-shadow:0 6px 22px #97bfe026;">
        <form method="post" id="formJustificar">
            <input type="hidden" name="justificar_id" id="justificar_id">
            <div class="mb-3">
                <label for="motivo" class="form-label" style="font-weight:600;">Motivo de la justificación:</label>
                <textarea name="motivo" id="motivo" class="form-control" rows="3" style="border-radius:1em;" required></textarea>
            </div>
            <button type="submit" class="btn btn-success" style="border-radius:1em;"><i class="bi bi-save"></i> Guardar</button>
            <button type="button" class="btn btn-secondary" style="border-radius:1em;" onclick="cerrarJustificar()"><i class="bi bi-x-lg"></i> Cancelar</button>
        </form>
    </div>
</div>
<script>
function mostrarJustificar(id, motivo) {
    document.getElementById('justificar_id').value = id;
    document.getElementById('motivo').value = motivo || "";
    document.getElementById('modalJustificar').style.display = 'block';
    setTimeout(()=>{document.getElementById('motivo').focus()}, 120);
}
function cerrarJustificar() {
    document.getElementById('modalJustificar').style.display = 'none';
}
document.getElementById('heBuscar').addEventListener('input', function() {
    var filtro = this.value.toLowerCase();
    var filas = document.querySelectorAll('#tablaHE tbody tr');
    filas.forEach(function(f){
        let txt = f.textContent.toLowerCase();
        f.style.display = (txt.indexOf(filtro) !== -1 || filtro == "") ? '' : 'none';
    });
});
</script>

<?php include 'footer.php'; ?>
