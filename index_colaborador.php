<?php
session_start();
date_default_timezone_set('America/Costa_Rica'); 

// 1. LÓGICA DE BACKEND (Funcionalidad original conservada)
//================================================================
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; 

$username = $_SESSION['username'];
$persona_id = $_SESSION['persona_id'];

if (!isset($_SESSION['colaborador_id']) || is_null($_SESSION['colaborador_id'])) {
    die("Error: ID de colaborador no definido en la sesión.");
}

$colaborador_id = $_SESSION['colaborador_id'];
$fechaHoy = date('Y-m-d');
$mensaje = '';
$tipoMensaje = '';

// Verificar estado de asistencia del día
$sql = "SELECT * FROM control_de_asistencia WHERE Persona_idPersona = ? AND Fecha = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $persona_id, $fechaHoy);
$stmt->execute();
$result = $stmt->get_result();
$asistencia = ($result->num_rows > 0) ? $result->fetch_assoc() : null;
$stmt->close();

$marcoEntrada = ($asistencia && $asistencia['Entrada'] != NULL);
$marcoSalida = ($asistencia && $asistencia['Salida'] != NULL);

// Procesar Marcar Entrada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_entrada'])) {
    $horaEntrada = date('H:i:s');
    if (!$marcoEntrada) {
        $sqlEntrada = "INSERT INTO control_de_asistencia (Persona_idPersona, Fecha, Entrada, Abierto) VALUES (?, ?, ?, 1)";
        $stmtEntrada = $conn->prepare($sqlEntrada);
        $stmtEntrada->bind_param("iss", $persona_id, $fechaHoy, $horaEntrada);
        if ($stmtEntrada->execute()) {
            $mensaje = "¡Entrada marcada con éxito a las $horaEntrada!";
            $tipoMensaje = 'success';
            $marcoEntrada = true;
            if ($asistencia) {
                $asistencia['Entrada'] = $horaEntrada;
            } else {
                $asistencia = ['Entrada' => $horaEntrada, 'Salida' => null];
            }
        } else {
            $mensaje = "Error al marcar la entrada.";
            $tipoMensaje = 'danger';
        }
        $stmtEntrada->close();
    }
}

// Procesar Marcar Salida
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_salida'])) {
    $horaSalida = date('H:i:s');
    if ($marcoEntrada && !$marcoSalida) {
        $sqlSalida = "UPDATE control_de_asistencia SET Salida = ?, Abierto = 0 WHERE Persona_idPersona = ? AND Fecha = ?";
        $stmtSalida = $conn->prepare($sqlSalida);
        $stmtSalida->bind_param("sis", $horaSalida, $persona_id, $fechaHoy);
        if ($stmtSalida->execute()) {
            $mensaje = "¡Salida marcada con éxito a las $horaSalida!";
            $tipoMensaje = 'success';
            $marcoSalida = true;
            $asistencia['Salida'] = $horaSalida;

            // Lógica de Horas Extra
            $horaEntradaTimestamp = strtotime($asistencia['Entrada']);
            $horaSalidaTimestamp = strtotime($horaSalida);
            $horasTrabajadas = ($horaSalidaTimestamp - $horaEntradaTimestamp) / 3600;

            if ($horasTrabajadas > 9) {
                $horasExtra = $horasTrabajadas - 9;
                $horasCompletas = floor($horasExtra);
                if (($horasExtra - $horasCompletas) * 60 >= 30) $horasCompletas += 1;
                
                if ($horasCompletas > 0) {
                    $horaInicioExtra = date('H:i:s', strtotime('+9 hours', $horaEntradaTimestamp));
                    $sqlHorasExtra = "INSERT INTO horas_extra (Fecha, hora_inicio, hora_fin, cantidad_horas, Motivo, estado, Colaborador_idColaborador, Persona_idPersona) VALUES (?, ?, ?, ?, 'Horas extra automáticas', 'Pendiente', ?, ?)";
                    $stmtHorasExtra = $conn->prepare($sqlHorasExtra);
                    $stmtHorasExtra->bind_param("ssdisi", $fechaHoy, $horaInicioExtra, $horaSalida, $horasCompletas, $colaborador_id, $persona_id);
                    if ($stmtHorasExtra->execute()) {
                        $mensaje .= " Se han registrado $horasCompletas horas extra para revisión.";
                    }
                    $stmtHorasExtra->close();
                }
            }
        } else {
            $mensaje = "Error al marcar la salida.";
            $tipoMensaje = 'danger';
        }
        $stmtSalida->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard del Colaborador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fc;
        }

        .main-content {
            padding-left: 280px; /* Ancho de la barra lateral */
            padding-top: 2rem;
            padding-right: 2rem;
        }

        .dashboard-header {
            margin-bottom: 2.5rem;
        }
        .dashboard-header h1 {
            font-weight: 600;
            color: #32325d;
        }
        .live-clock {
            font-size: 2rem;
            font-weight: 600;
            color: #5e72e4;
        }
        
        .attendance-card {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
        }
        
        .action-button {
            border: none;
            border-radius: 0.75rem;
            padding: 1.5rem 1rem;
            font-size: 1.1rem;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        .action-button i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        .btn-clock-in { background-color: #e9fbf3; color: #2dce89; }
        .btn-clock-in:hover:not(:disabled) { background-color: #2dce89; color: #fff; transform: translateY(-3px); }
        .btn-clock-out { background-color: #fdecea; color: #f5365c; }
        .btn-clock-out:hover:not(:disabled) { background-color: #f5365c; color: #fff; transform: translateY(-3px); }
        .action-button:disabled { background-color: #e9ecef; color: #adb5bd; cursor: not-allowed; }
        
        .module-card {
            background-color: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #525f7f;
            display: block;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .module-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #5e72e4;
        }
        .module-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .main-content {
                padding-left: 2rem;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-content">
    <div class="dashboard-header">
        <h1>Hola, <?php echo htmlspecialchars(explode(' ', $username)[0]); ?></h1>
        <p class="text-muted">Bienvenido a tu panel de control.</p>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="attendance-card">
                <h5 class="mb-4">Registro de Asistencia del Día</h5>
                 <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipoMensaje; ?> text-center" role="alert">
                        <?= htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="row g-3">
                        <div class="col">
                            <button class="action-button btn-clock-in" type="submit" name="marcar_entrada" <?php if ($marcoEntrada) echo 'disabled'; ?>>
                                <i class="bi bi-box-arrow-in-right"></i>Marcar Entrada
                            </button>
                        </div>
                        <div class="col">
                            <button class="action-button btn-clock-out" type="submit" name="marcar_salida" <?php if (!$marcoEntrada || $marcoSalida) echo 'disabled'; ?>>
                                <i class="bi bi-box-arrow-right"></i>Marcar Salida
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-4 d-flex">
            <div class="attendance-card w-100 justify-content-center d-flex flex-column">
                <div id="live-clock">--:--:--</div>
                <div id="live-date" class="text-muted small">-- de ------ de ----</div>
            </div>
        </div>
    </div>

    <h3 class="mt-5 mb-3">Accesos Rápidos</h3>
    <div class="row g-4">
        <div class="col-lg-3 col-md-6">
            <a href="solicitud_permisos.php" class="module-card">
                <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
                <h5 class="card-title">Permisos y Vacaciones</h5>
            </a>
        </div>
        <div class="col-lg-3 col-md-6">
            <a href="horas_extra.php" class="module-card">
                <div class="icon"><i class="bi bi-clock-history"></i></div>
                <h5 class="card-title">Horas Extra</h5>
            </a>
        </div>
        <div class="col-lg-3 col-md-6">
            <a href="evaluacion.php" class="module-card">
                <div class="icon"><i class="bi bi-star-fill"></i></div>
                <h5 class="card-title">Mis Evaluaciones</h5>
            </a>
        </div>
        <div class="col-lg-3 col-md-6">
            <a href="salario.php" class="module-card">
                <div class="icon"><i class="bi bi-cash-coin"></i></div>
                <h5 class="card-title">Mi Salario</h5>
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const clockElement = document.getElementById('live-clock');
        const dateElement = document.getElementById('live-date');

        function updateTime() {
            const now = new Date();
            clockElement.textContent = now.toLocaleTimeString('es-ES');
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.textContent = now.toLocaleDateString('es-ES', options);
        }
        updateTime();
        setInterval(updateTime, 1000);
    });
</script>

</body>
</html>