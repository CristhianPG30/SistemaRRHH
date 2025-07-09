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
        // Solo permitir cambiar si no está aprobada o rechazada
        $stmtCheck = $conn->prepare("SELECT estado FROM horas_extra WHERE Persona_idPersona = ? AND idPermisos = ?");
        $stmtCheck->bind_param("ii", $persona_id, $id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($estadoActual);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($estadoActual == 'Pendiente' || $estadoActual == 'Justificada') {
            $estado_justificada = 'Justificada'; // o "Pendiente de aprobación"
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
<div class="container mt-4">
    <h2>Historial de Horas Extra</h2>
    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Horas Extra</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                    <th>Justificar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($horasExtra)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No hay horas extra registradas</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($horasExtra as $hx): ?>
                        <tr>
                            <td><?= htmlspecialchars($hx['Fecha']) ?></td>
                            <td><?= htmlspecialchars($hx['hora_inicio']) ?></td>
                            <td><?= htmlspecialchars($hx['hora_fin']) ?></td>
                            <td><?= htmlspecialchars($hx['cantidad_horas']) ?></td>
                            <td>
                                <?php 
                                if ($hx['estado'] == 'Pendiente') {
                                    echo '<span class="text-muted">Sin justificar</span>';
                                } else {
                                    echo htmlspecialchars($hx['Motivo']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($hx['estado'] == 'Pendiente') {
                                    echo 'Pendiente de justificación';
                                } else if ($hx['estado'] == 'Justificada') {
                                    echo 'Pendiente de aprobación';
                                } else if ($hx['estado'] == 'Aprobada') {
                                    echo '<span class="text-success">Aprobada</span>';
                                } else if ($hx['estado'] == 'Rechazada') {
                                    echo '<span class="text-danger">Rechazada</span>';
                                } else {
                                    echo htmlspecialchars($hx['estado']);
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($hx['Observaciones']) ?></td>
                            <td>
                                <?php
                                // Botón SIEMPRE visible, cambia color y texto según estado
                                if ($hx['estado'] == 'Pendiente') {
                                    echo '<button class="btn btn-sm btn-primary" onclick="mostrarJustificar('.$hx['idPermisos'].', \'\')">Justificar</button>';
                                } else if ($hx['estado'] == 'Justificada') {
                                    $motivoSafe = htmlspecialchars($hx['Motivo'], ENT_QUOTES);
                                    echo '<button class="btn btn-sm btn-warning" onclick="mostrarJustificar('.$hx['idPermisos'].', \''.$motivoSafe.'\')">Editar justificación</button>';
                                } else if ($hx['estado'] == 'Aprobada') {
                                    echo '<button class="btn btn-sm btn-success" disabled>Aprobada</button>';
                                } else if ($hx['estado'] == 'Rechazada') {
                                    echo '<button class="btn btn-sm btn-danger" disabled>Rechazada</button>';
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
<div id="modalJustificar" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.2);">
    <div style="background:white; max-width:400px; margin:5% auto; padding:2em; border-radius:1em; position:relative;">
        <form method="post" id="formJustificar">
            <input type="hidden" name="justificar_id" id="justificar_id">
            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo de la justificación:</label>
                <textarea name="motivo" id="motivo" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn btn-success">Guardar Justificación</button>
            <button type="button" class="btn btn-secondary" onclick="cerrarJustificar()">Cancelar</button>
        </form>
    </div>
</div>
<script>
function mostrarJustificar(id, motivo) {
    document.getElementById('justificar_id').value = id;
    document.getElementById('motivo').value = motivo || "";
    document.getElementById('modalJustificar').style.display = 'block';
}
function cerrarJustificar() {
    document.getElementById('modalJustificar').style.display = 'none';
}
</script>
</body>
</html>
