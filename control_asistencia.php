<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) { // Verificar que el usuario tiene rol de colaborador
    header('Location: login.php');
    exit;
}

// --- CORRECCIÓN: Añadir zona horaria para asegurar que la fecha actual sea la correcta ---
date_default_timezone_set('America/Costa_Rica');

include 'db.php'; // Conexión a la base de datos

$username = $_SESSION['username'];
$persona_id = $_SESSION['persona_id'];

// --- FUNCIONES AUXILIARES ---
function obtenerDiasFeriados($anio, $conn) {
    $feriadosFilePath = 'js/feriados.json';
    if (!file_exists($feriadosFilePath)) return [];
    $feriados_data = json_decode(file_get_contents($feriadosFilePath), true);
    return is_array($feriados_data) ? array_column($feriados_data, 'fecha') : [];
}


// --- LÓGICA DE FILTROS Y DATOS ---
$fecha_inicio_str = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin_str = $_GET['fecha_fin'] ?? date('Y-m-t');
$fechaHoy = date('Y-m-d'); // Fecha actual para la comparación

// Obtener todos los registros de asistencia del colaborador en el rango de una vez
$sql_asistencia = "SELECT Fecha, Entrada, Salida FROM control_de_asistencia WHERE Persona_idPersona = ? AND Fecha BETWEEN ? AND ?";
$stmt = $conn->prepare($sql_asistencia);
$stmt->bind_param("iss", $persona_id, $fecha_inicio_str, $fecha_fin_str);
$stmt->execute();
$result_asistencia = $stmt->get_result();
$asistencias = [];
while ($row = $result_asistencia->fetch_assoc()) {
    $asistencias[$row['Fecha']] = $row;
}
$stmt->close();
$conn->close();

// Obtener feriados para el año o años del rango
$feriados = [];
$anio_inicio = date('Y', strtotime($fecha_inicio_str));
$anio_fin = date('Y', strtotime($fecha_fin_str));
for ($a = $anio_inicio; $a <= $anio_fin; $a++) {
    $feriados = array_merge($feriados, obtenerDiasFeriados($a, $conn));
}
$feriados = array_unique($feriados);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important;
            font-family: 'Poppins', sans-serif;
        }
        .container-asistencia {
            max-width: 880px;
            margin: 48px auto 0;
            padding: 0 15px;
        }
        .asistencia-card {
            background: #fff;
            border-radius: 2.1rem;
            box-shadow: 0 8px 38px 0 rgba(44,62,80,.12);
            padding: 2.2rem 2.1rem 1.7rem 2.1rem;
            margin-bottom: 2.2rem;
            animation: fadeInDown 0.9s;
        }
        @keyframes fadeInDown { 0% { opacity: 0; transform: translateY(-30px);} 100% { opacity: 1; transform: translateY(0);} }
        .asistencia-title {
            font-size: 2.2rem;
            font-weight: 900;
            color: #1a3961;
            letter-spacing: .7px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .asistencia-title i { color: #3499ea; font-size: 2.2rem; }
        .text-center { color: #3a6389; }
        .filter-box {
            display: flex;
            flex-wrap: wrap;
            gap: 1.4rem 1.1rem;
            align-items: end;
            margin-bottom: 2.1rem;
            background: #f3faff;
            padding: 1.1rem 1.3rem 0.7rem 1.3rem;
            border-radius: 1.1rem;
            box-shadow: 0 2px 16px #23b6ff18;
        }
        .filter-box label { color: #288cc8; font-weight: 600; }
        .filter-box input[type="date"] { border-radius: 0.7rem; }
        .btn-filtrar {
            background: linear-gradient(90deg, #1f8ff7 75%, #53e3fc 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            border-radius: 0.8rem;
            padding: .63rem 1.5rem;
            box-shadow: 0 2px 12px #1f8ff722;
        }
        .btn-filtrar:hover { background: linear-gradient(90deg, #53e3fc 25%, #1f8ff7 100%); color: #fff; }
        .table-asistencia {
            background: #f8fafd;
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 4px 24px #23b6ff10;
        }
        .table-asistencia th {
            background: #e9f6ff;
            color: #288cc8;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .table-asistencia td, .table-asistencia th {
            padding: 0.75rem 0.7rem;
            text-align: center;
            vertical-align: middle;
        }
        .badge-atiempo { background: #01b87f !important; color: #fff !important; font-size: 1em; }
        .badge-tarde { background: #ffd237 !important; color: #6a4d00 !important; font-size: 1em; }
        .badge-ausente { background: #ff6565 !important; color: #fff !important; font-size: 1em; }
        .no-data-row {
            color: #828ba7;
            background: #f3faff;
            font-style: italic;
        }
        @media (max-width: 600px) {
            .asistencia-card { padding: 1.1rem 0.3rem 0.9rem 0.3rem; }
            .asistencia-title { font-size: 1.3rem; }
            .table-asistencia th, .table-asistencia td { font-size: .96rem; padding: 0.4rem 0.3rem;}
            .filter-box { padding: 1rem 0.5rem 0.7rem 0.5rem; }
        }
    </style>
</head>

<body>
<?php include 'header.php'; ?>
<div class="container-asistencia">
    <div class="asistencia-card">
        <div class="asistencia-title animate__animated animate__fadeInDown">
            <i class="bi bi-journal-check"></i> Control de Asistencia
        </div>
        <p class="text-center mb-3">Consulta y filtra tu historial de asistencia aquí.</p>

        <form method="GET" class="filter-box animate__animated animate__fadeInDown">
            <div>
                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio_str); ?>">
            </div>
            <div>
                <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin_str); ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-filtrar mt-3"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-asistencia table-bordered mt-3 mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora de Entrada</th>
                        <th>Hora de Salida</th>
                        <th>Estado de Entrada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $periodo = new DatePeriod(
                        new DateTime($fecha_inicio_str),
                        new DateInterval('P1D'),
                        new DateTime($fecha_fin_str . ' +1 day')
                    );

                    $registros_mostrados = 0;
                    foreach (array_reverse(iterator_to_array($periodo)) as $fecha_obj) {
                        $fecha_actual = $fecha_obj->format('Y-m-d');
                        $dia_semana = date('N', strtotime($fecha_actual));

                        // Omitir fechas futuras (posteriores a hoy)
                        if ($fecha_actual > $fechaHoy) {
                            continue;
                        }

                        // Omitir fines de semana y feriados
                        if ($dia_semana >= 6 || in_array($fecha_actual, $feriados)) {
                            continue;
                        }

                        $registros_mostrados++;
                        $estado = 'Ausente';
                        $badge = 'badge-ausente';
                        $entrada = '-';
                        $salida = '-';

                        if (isset($asistencias[$fecha_actual])) {
                            $registro_dia = $asistencias[$fecha_actual];
                            $entrada = htmlspecialchars($registro_dia['Entrada']);
                            $salida = htmlspecialchars($registro_dia['Salida'] ?? '-');
                            
                            if ($registro_dia['Entrada'] > '08:00:00') {
                                $estado = 'Tarde';
                                $badge = 'badge-tarde';
                            } else {
                                $estado = 'A tiempo';
                                $badge = 'badge-atiempo';
                            }
                        }

                        echo "<tr>";
                        echo "<td>" . date('d/m/Y', strtotime($fecha_actual)) . "</td>";
                        echo "<td>" . $entrada . "</td>";
                        echo "<td>" . $salida . "</td>";
                        echo "<td><span class='badge $badge'>" . htmlspecialchars($estado) . "</span></td>";
                        echo "</tr>";
                    }

                    if ($registros_mostrados === 0) {
                        echo "<tr><td colspan='4' class='no-data-row'>No hay días laborables para mostrar en el rango seleccionado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>