<?php
session_start();
date_default_timezone_set('America/Costa_Rica'); // Ajusta a tu zona horaria

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos

$username = $_SESSION['username'];
$persona_id = $_SESSION['persona_id']; // ID de persona

// Verifica si el colaborador_id está definido y no es nulo
if (!isset($_SESSION['colaborador_id']) || is_null($_SESSION['colaborador_id'])) {
    die("Error: ID de colaborador no definido en la sesión.");
}

$colaborador_id = $_SESSION['colaborador_id']; // ID de colaborador
$fechaHoy = date('Y-m-d');

// Variables para mensajes
$mensajeEntrada = '';
$mensajeSalida = '';
$mensajeError = '';
$mensajeHorasExtra = '';

// Verificar si ya ha marcado la entrada o salida hoy
$sql = "SELECT * FROM control_de_asistencia WHERE Persona_idPersona = $persona_id AND Fecha = '$fechaHoy'";
$result = $conn->query($sql);
$marcoEntrada = false;
$marcoSalida = false;
$asistencia = null;

if ($result->num_rows > 0) {
    $asistencia = $result->fetch_assoc();
    if ($asistencia['Entrada'] != NULL) {
        $marcoEntrada = true;
    }
    if ($asistencia['Salida'] != NULL) {
        $marcoSalida = true;
    }
}

// Marcar Entrada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_entrada'])) {
    $horaEntrada = date('H:i:s');
    if (!$marcoEntrada) {
        $sqlEntrada = "INSERT INTO control_de_asistencia (Persona_idPersona, Fecha, Entrada, Abierto) VALUES ($persona_id, '$fechaHoy', '$horaEntrada', 1)";
        if ($conn->query($sqlEntrada) === TRUE) {
            $mensajeEntrada = "Has marcado la entrada con éxito.";
            $marcoEntrada = true;
            $asistencia['Entrada'] = $horaEntrada; // Actualizamos la variable $asistencia
        } else {
            $mensajeError = "Error al marcar la entrada: " . $conn->error;
        }
    } else {
        $mensajeError = "Ya has marcado tu entrada hoy.";
    }
}

// Marcar Salida
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_salida'])) {
    $horaSalida = date('H:i:s');
    if ($marcoEntrada && !$marcoSalida) {
        $sqlSalida = "UPDATE control_de_asistencia SET Salida = '$horaSalida', Abierto = 0 WHERE Persona_idPersona = $persona_id AND Fecha = '$fechaHoy'";
        if ($conn->query($sqlSalida) === TRUE) {
            $mensajeSalida = "Has marcado la salida con éxito.";
            $marcoSalida = true;
            $asistencia['Salida'] = $horaSalida; // Actualizamos la variable $asistencia

            // Calcular horas trabajadas
            $horaEntradaTimestamp = strtotime($asistencia['Entrada']);
            $horaSalidaTimestamp = strtotime($horaSalida);
            $horasTrabajadas = ($horaSalidaTimestamp - $horaEntradaTimestamp) / 3600; // Convertir a horas

            // Verificar si trabajó más de 9 horas
            if ($horasTrabajadas > 9) {
                $horasExtra = $horasTrabajadas - 9;
                $horasCompletas = floor($horasExtra);
                $minutosExtra = ($horasExtra - $horasCompletas) * 60;

                // Aplicar la regla de redondeo
                if ($minutosExtra >= 30) {
                    $horasCompletas += 1; // Redondear hacia arriba
                }

                if ($horasCompletas > 0) {
                    // Registrar las horas de inicio y fin de las horas extra
                    $horaInicioExtra = date('H:i:s', strtotime('+9 hours', $horaEntradaTimestamp));
                    $horaFinExtra = date('H:i:s', $horaSalidaTimestamp);

                    // Insertar registro de horas extra en la tabla horas_extra
                    $sqlHorasExtra = "INSERT INTO horas_extra (Fecha, hora_inicio, hora_fin, cantidad_horas, Motivo, estado, Colaborador_idColaborador, Persona_idPersona) 
                                      VALUES ('$fechaHoy', '$horaInicioExtra', '$horaFinExtra', $horasCompletas, 'Horas extra automáticas', 'Pendiente', $colaborador_id, $persona_id)";
                    
                    if ($conn->query($sqlHorasExtra) === TRUE) {
                        $mensajeHorasExtra = "Horas extra registradas: $horasCompletas horas.";
                    } else {
                        $mensajeError = "Error al registrar horas extra: " . $conn->error;
                    }
                }
            }
        } else {
            $mensajeError = "Error al marcar la salida: " . $conn->error;
        }
    } else {
        $mensajeError = "Debes marcar la entrada antes de marcar la salida o ya has marcado tu salida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Colaborador - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
       
        .container {
            padding-top: 30px;
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .clock-in-out-container {
            text-align: center;
            background-color: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        .clock-in-out-container button {
            width: 100%;
            padding: 15px;
            font-size: 1.2rem;
            border-radius: 50px;
            margin: 10px 0;
            transition: all 0.3s ease;
            border: none;
        }
        .clock-in-btn {
            background-color: #28a745;
            color: white;
        }
        .clock-in-btn:disabled, .clock-out-btn:disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        .clock-out-btn {
            background-color: #dc3545;
            color: white;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 1rem;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .message-info {
            background-color: #cce5ff;
            color: #004085;
        }
        .message-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        footer {
            background-color: #2c3e50;
            padding: 20px;
            text-align: center;
            color: #ecf0f1;
        }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <div class="clock-in-out-container">
            <h1>Marcar Entrada / Salida</h1>

            <!-- Mostrar mensajes -->
            <?php if ($mensajeEntrada): ?>
                <div class="message message-success">
                    <?= htmlspecialchars($mensajeEntrada); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensajeSalida): ?>
                <div class="message message-danger">
                    <?= htmlspecialchars($mensajeSalida); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensajeHorasExtra): ?>
                <div class="message message-info">
                    <?= htmlspecialchars($mensajeHorasExtra); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensajeError): ?>
                <div class="message message-error">
                    <?= htmlspecialchars($mensajeError); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <button class="clock-in-btn" type="submit" name="marcar_entrada" <?php if ($marcoEntrada) echo 'disabled'; ?>>
                    Marcar Entrada
                </button>
                <button class="clock-out-btn" type="submit" name="marcar_salida" <?php if (!$marcoEntrada || $marcoSalida) echo 'disabled'; ?>>
                    Marcar Salida
                </button>
            </form>
            <?php if ($marcoEntrada && isset($asistencia['Entrada'])) : ?>
                <p>Has marcado tu entrada a las: <?php echo htmlspecialchars($asistencia['Entrada']); ?></p>
            <?php endif; ?>
            <?php if ($marcoSalida && isset($asistencia['Salida'])) : ?>
                <p>Has marcado tu salida a las: <?php echo htmlspecialchars($asistencia['Salida']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        &copy; 2024 Edginton S.A. Todos los derechos reservados.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php $conn->close(); ?>