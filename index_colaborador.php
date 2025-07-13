<?php
session_start();
date_default_timezone_set('America/Costa_Rica');

// Validación estricta de sesión
if (
    !isset($_SESSION['username']) ||
    !isset($_SESSION['persona_id']) ||
    !isset($_SESSION['colaborador_id']) ||
    empty($_SESSION['colaborador_id'])
) {
    // Limpia la sesión para evitar errores de datos incompletos
    session_unset();
    session_destroy();
    header('Location: login.php?error=SesionInvalida');
    exit;
}

include 'db.php';

$username      = $_SESSION['username'];
$persona_id    = $_SESSION['persona_id'];
$colaborador_id = $_SESSION['colaborador_id'];
$fechaHoy      = date('Y-m-d');
$mensaje       = '';
$tipoMensaje   = '';

// --- Consultar asistencia de hoy ---
$stmt = $conn->prepare("SELECT * FROM control_de_asistencia WHERE Persona_idPersona = ? AND Fecha = ?");
$stmt->bind_param("is", $persona_id, $fechaHoy);
$stmt->execute();
$result = $stmt->get_result();
$asistencia = ($result->num_rows > 0) ? $result->fetch_assoc() : null;
$stmt->close();

$marcoEntrada = ($asistencia && $asistencia['Entrada'] != null);
$marcoSalida  = ($asistencia && $asistencia['Salida'] != null && $asistencia['Salida'] != '00:00:00');

// --- Marcar entrada ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_entrada'])) {
    $horaEntrada = date('H:i:s');
    if (!$marcoEntrada) {
        $stmtEntrada = $conn->prepare("INSERT INTO control_de_asistencia (Persona_idPersona, Fecha, Entrada, Abierto) VALUES (?, ?, ?, 1)");
        $stmtEntrada->bind_param("iss", $persona_id, $fechaHoy, $horaEntrada);
        if ($stmtEntrada->execute()) {
            $mensaje = "¡Entrada marcada con éxito a las $horaEntrada!";
            $tipoMensaje = 'success';
            // Actualiza los datos para la nueva carga
            $marcoEntrada = true;
            $asistencia = ['Entrada' => $horaEntrada, 'Salida' => null];
        } else {
            $mensaje = "Error al marcar la entrada.";
            $tipoMensaje = 'danger';
        }
        $stmtEntrada->close();
    }
}

// --- Marcar salida y calcular horas extra ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_salida'])) {
    $horaSalida = date('H:i:s');
    if ($marcoEntrada && (!$marcoSalida || ($asistencia['Salida'] == '00:00:00'))) {
        $stmtSalida = $conn->prepare("UPDATE control_de_asistencia SET Salida = ?, Abierto = 0 WHERE Persona_idPersona = ? AND Fecha = ?");
        $stmtSalida->bind_param("sis", $horaSalida, $persona_id, $fechaHoy);

        if ($stmtSalida->execute()) {
            $mensaje = "¡Salida marcada con éxito a las $horaSalida!";
            $tipoMensaje = 'success';

            // Refresca datos de asistencia
            $stmt = $conn->prepare("SELECT * FROM control_de_asistencia WHERE Persona_idPersona = ? AND Fecha = ?");
            $stmt->bind_param("is", $persona_id, $fechaHoy);
            $stmt->execute();
            $result = $stmt->get_result();
            $asistencia = ($result->num_rows > 0) ? $result->fetch_assoc() : null;
            $stmt->close();

            $asistencia['Salida'] = $horaSalida;

            // --- HORAS EXTRA (mayor a 8h y más de 30min) ---
            $horaEntradaTimestamp = strtotime($asistencia['Entrada']);
            $horaSalidaTimestamp  = strtotime($horaSalida);
            $totalSegundos        = $horaSalidaTimestamp - $horaEntradaTimestamp;
            $horasTrabajadas      = $totalSegundos / 3600;

            if ($horasTrabajadas > 8) {
                $extraSegundos = $totalSegundos - (8 * 3600);
                if ($extraSegundos > 1800) { // Más de 30 minutos extra
                    $horasExtra   = floor($extraSegundos / 3600);
                    $minutosExtra = floor(($extraSegundos % 3600) / 60);
                    $cantidad_horas = $horasExtra + (($minutosExtra >= 30) ? 1 : 0);

                    if ($cantidad_horas > 0) {
                        $horaInicioExtra = date('H:i:s', strtotime('+8 hours', $horaEntradaTimestamp));
                        $observaciones   = '';

                        // Prevenir duplicados
                        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM horas_extra WHERE Fecha = ? AND Persona_idPersona = ? AND hora_inicio = ? AND hora_fin = ?");
                        $stmtCheck->bind_param("siss", $fechaHoy, $persona_id, $horaInicioExtra, $horaSalida);
                        $stmtCheck->execute();
                        $stmtCheck->bind_result($existe);
                        $stmtCheck->fetch();
                        $stmtCheck->close();

                        if ($existe == 0) {
                            $stmtHorasExtra = $conn->prepare("INSERT INTO horas_extra (Fecha, hora_inicio, hora_fin, cantidad_horas, Motivo, estado, Colaborador_idColaborador, Persona_idPersona, Observaciones) VALUES (?, ?, ?, ?, 'Horas extra automáticas', 'Pendiente', ?, ?, ?)");
                            $stmtHorasExtra->bind_param("sssdiis", $fechaHoy, $horaInicioExtra, $horaSalida, $cantidad_horas, $colaborador_id, $persona_id, $observaciones);
                            if ($stmtHorasExtra->execute()) {
                                $mensaje .= " Se han registrado $cantidad_horas hora(s) extra como 'Pendiente de solicitud'.";
                            } else {
                                $mensaje .= " (Error al registrar horas extra: " . $stmtHorasExtra->error . ")";
                            }
                            $stmtHorasExtra->close();
                        } else {
                            $mensaje .= " (Horas extra ya estaban registradas para este rango horario.)";
                        }
                    }
                }
            }
            // --- FIN HORAS EXTRA ---
        } else {
            $mensaje = "Error al marcar la salida.";
            $tipoMensaje = 'danger';
        }
        $stmtSalida->close();
    }
}

$conn->close();
?>

<?php include 'header.php'; ?>

<style>
    body { background: #f3f6fb !important; margin: 0; padding: 0; }
    .main-content { margin-left: 260px; padding: 36px 24px 0 24px; min-height: 100vh; background: #f7fafd; transition: margin-left 0.3s; }
    @media (max-width: 991px) { .main-content { margin-left: 0; padding: 24px 5px 0 5px; } }
    .attendance-card { background: #fff; border-radius: 1.25rem; box-shadow: 0 4px 24px 0 rgba(44,62,80,.08); padding: 2.5rem; text-align: center; }
    .action-button { border: none; border-radius: 0.75rem; padding: 1.4rem 1rem; font-size: 1.07rem; font-weight: 500; width: 100%; margin-bottom: 5px; transition: all 0.2s; }
    .action-button i { font-size: 2rem; display: block; margin-bottom: 0.6rem; }
    .btn-clock-in { background: #e8fcec; color: #2ecc71;}
    .btn-clock-in:hover:not(:disabled) { background: #2ecc71; color: #fff; }
    .btn-clock-out { background: #fdeaea; color: #e74c3c;}
    .btn-clock-out:hover:not(:disabled) { background: #e74c3c; color: #fff; }
    .action-button:disabled { background: #f2f2f2; color: #b5b5b5;}
    .clock-card { background: linear-gradient(135deg,#5e72e4 60%,#a0aec0 100%); color: #fff; border-radius: 1.25rem; padding: 2.2rem 1.3rem; text-align: center; box-shadow: 0 4px 24px 0 rgba(44,62,80,.11); margin-bottom: 1.5rem; }
    .live-clock { font-size: 2.6rem; font-weight: 700; letter-spacing: 2px; }
    .live-date { font-size: 1.1rem; opacity: 0.9; margin-top: 0.3rem; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="dashboard-header mb-4 mt-3">
            <h1 style="font-weight: 700;">¡Hola, <?= htmlspecialchars(explode(' ', $username)[0]); ?>!</h1>
            <div class="text-muted" style="font-size: 1.13rem;">Bienvenido a tu panel de colaborador.</div>
        </div>
        <div class="row justify-content-center g-4 align-items-stretch">
            <!-- Tarjeta de Asistencia -->
            <div class="col-lg-7 col-md-10">
                <div class="attendance-card h-100">
                    <h5 class="mb-4"><i class="bi bi-clock"></i> Registro de Asistencia de Hoy</h5>
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipoMensaje; ?> text-center" role="alert">
                            <?= htmlspecialchars($mensaje); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="mb-0">
                        <div class="row g-3">
                            <div class="col">
                                <button class="action-button btn-clock-in" type="submit" name="marcar_entrada" <?php if ($marcoEntrada) echo 'disabled'; ?>>
                                    <i class="bi bi-box-arrow-in-right"></i> Marcar Entrada
                                </button>
                            </div>
                            <div class="col">
                                <button class="action-button btn-clock-out" type="submit" name="marcar_salida" <?php if (!$marcoEntrada || $marcoSalida) echo 'disabled'; ?>>
                                    <i class="bi bi-box-arrow-right"></i> Marcar Salida
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-4" style="font-size:1rem; color:#9096a6;">
                        <?php if ($marcoEntrada): ?>
                            <b>Entrada:</b> <?= htmlspecialchars($asistencia['Entrada']) ?>
                            <?php if ($asistencia['Salida'] && $asistencia['Salida'] != '00:00:00'): ?>
                                &nbsp; | &nbsp; <b>Salida:</b> <?= htmlspecialchars($asistencia['Salida']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            No has registrado tu entrada hoy.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Tarjeta del Reloj -->
            <div class="col-lg-4 col-md-6">
                <div class="clock-card h-100 d-flex align-items-center justify-content-center">
                    <div>
                        <div id="live-clock" class="live-clock">--:--:--</div>
                        <div id="live-date" class="live-date">-- de ------ de ----</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const clockElement = document.getElementById('live-clock');
        const dateElement = document.getElementById('live-date');
        function updateTime() {
            const now = new Date();
            clockElement.textContent = now.toLocaleTimeString('es-CR', { hour: '2-digit', minute: '2-digit', second: '2-digit'});
            const opcionesFecha = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            let fechaStr = new Intl.DateTimeFormat('es-CR', opcionesFecha).format(now);
            fechaStr = fechaStr.charAt(0).toUpperCase() + fechaStr.slice(1);
            dateElement.textContent = fechaStr;
        }
        updateTime();
        setInterval(updateTime, 1000);
    });
</script>
</body>
</html>
