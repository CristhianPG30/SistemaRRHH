<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$persona_id = $_SESSION['persona_id'] ?? 0;
$mensaje = '';
$mensaje_tipo = '';

// --- FUNCIONES DE CÁLCULO ---
function obtenerDiasFeriados() {
    $feriadosFilePath = 'js/feriados.json';
    if (!file_exists($feriadosFilePath)) return [];
    $feriados_data = json_decode(file_get_contents($feriadosFilePath), true);
    return is_array($feriados_data) ? array_column($feriados_data, 'fecha') : [];
}

function calcularDiasLaboralesSolicitados($fecha_inicio, $fecha_fin) {
    if (empty($fecha_inicio) || empty($fecha_fin)) return 0;
    $dias_laborales = 0;
    $feriados = obtenerDiasFeriados();
    try {
        $periodo = new DatePeriod(new DateTime($fecha_inicio), new DateInterval('P1D'), (new DateTime($fecha_fin))->modify('+1 day'));
        foreach ($periodo as $fecha) {
            if ($fecha->format('N') < 6 && !in_array($fecha->format('Y-m-d'), $feriados)) {
                $dias_laborales++;
            }
        }
    } catch (Exception $e) {
        return 0;
    }
    return $dias_laborales;
}

// Traer idColaborador y fecha ingreso
$idColaborador = 0;
$fecha_ingreso = date('Y-m-d');
if ($persona_id > 0) {
    $stmt = $conn->prepare("SELECT idColaborador, fecha_ingreso FROM colaborador WHERE id_persona_fk = ?");
    $stmt->bind_param("i", $persona_id);
    $stmt->execute();
    $result_colab = $stmt->get_result();
    if($row_colab = $result_colab->fetch_assoc()){
        $idColaborador = $row_colab['idColaborador'];
        $fecha_ingreso = $row_colab['fecha_ingreso'];
    }
    $stmt->close();
}


// Calcular días disponibles
$fecha_ingreso = $fecha_ingreso ?: date('Y-m-d');
$hoy = date('Y-m-d');
$dt1 = new DateTime($fecha_ingreso);
$dt2 = new DateTime($hoy);
$meses_laborados = ($dt1->diff($dt2)->y * 12) + $dt1->diff($dt2)->m;
$dias_acumulados = floor($meses_laborados * 1); // 1 día por mes

$dias_tomados = 0;
if ($idColaborador > 0) {
    // --- CORRECCIÓN: Usar dias_habiles para el cálculo ---
    $sql_tomados = "SELECT SUM(dias_habiles) as total
    FROM permisos 
    WHERE id_colaborador_fk = ? 
      AND id_tipo_permiso_fk = (SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion) = 'vacaciones')
      AND id_estado_fk = (SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion) = 'aprobado')";
    $stmt_tomados = $conn->prepare($sql_tomados);
    $stmt_tomados->bind_param("i", $idColaborador);
    $stmt_tomados->execute();
    $stmt_tomados->bind_result($dias_tomados_db);
    $stmt_tomados->fetch();
    $stmt_tomados->close();
    $dias_tomados = $dias_tomados_db ?: 0;
}

$dias_disponibles = max($dias_acumulados - $dias_tomados, 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $motivo = trim($_POST['motivo']);

    $resultTipo = $conn->query("SELECT idTipoPermiso FROM tipo_permiso_cat WHERE LOWER(Descripcion)='vacaciones' LIMIT 1");
    $idTipoPermiso = $resultTipo->fetch_assoc()['idTipoPermiso'] ?? 1;

    $resultEstado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion)='pendiente' LIMIT 1");
    $idEstadoPendiente = $resultEstado->fetch_assoc()['idEstado'] ?? 3;
    
    $resultEstadoRechazado = $conn->query("SELECT idEstado FROM estado_cat WHERE LOWER(Descripcion)='rechazado' LIMIT 1");
    $idEstadoRechazado = $resultEstadoRechazado->fetch_assoc()['idEstado'] ?? 5;

    $dias_solicitados_laborales = calcularDiasLaboralesSolicitados($fecha_inicio, $fecha_fin);

    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $mensaje = "Debes seleccionar una fecha de inicio y de fin.";
        $mensaje_tipo = 'warning';
    } elseif ($fecha_fin < $fecha_inicio) {
        $mensaje = "La fecha de fin no puede ser anterior a la de inicio.";
        $mensaje_tipo = 'danger';
    } elseif ($dias_solicitados_laborales <= 0) {
        $mensaje = "Las fechas seleccionadas no contienen días laborales válidos.";
        $mensaje_tipo = 'danger';
    } elseif ($dias_solicitados_laborales > $dias_disponibles) {
        $mensaje = "No tienes suficientes días disponibles ($dias_solicitados_laborales solicitados vs $dias_disponibles disponibles).";
        $mensaje_tipo = 'danger';
    } elseif ($idColaborador > 0) {
        
        // --- INICIO DE LA CORRECCIÓN: Consulta de cruce de fechas mejorada ---
        $check_sql = "SELECT tpc.Descripcion AS tipo_permiso 
                      FROM permisos p
                      JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                      WHERE p.id_colaborador_fk = ? AND p.id_estado_fk != ? 
                      AND ? <= p.fecha_fin AND ? >= p.fecha_inicio
                      LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiss", $idColaborador, $idEstadoRechazado, $fecha_inicio, $fecha_fin);
        $check_stmt->execute();
        $result_check = $check_stmt->get_result();
        
        if ($result_check->num_rows > 0) {
            $conflicto = $result_check->fetch_assoc();
            $tipo_conflicto = strtolower($conflicto['tipo_permiso']);
            // Se crea un mensaje específico y se usa la variable correcta ($mensaje_tipo)
            $mensaje = "Ya tienes una solicitud de '{$tipo_conflicto}' que se cruza con estas fechas.";
            $mensaje_tipo = 'danger';
        } else {
            // Si no hay conflictos, se procede a insertar una nueva solicitud
            $stmt_insert = $conn->prepare("INSERT INTO permisos (id_colaborador_fk, id_tipo_permiso_fk, id_estado_fk, fecha_solicitud, fecha_inicio, fecha_fin, motivo, dias_habiles) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
            $stmt_insert->bind_param("iiisssi", $idColaborador, $idTipoPermiso, $idEstadoPendiente, $fecha_inicio, $fecha_fin, $motivo, $dias_solicitados_laborales);
            if ($stmt_insert->execute()) {
                // Se usa una sesión para mostrar el mensaje después de redirigir
                $_SESSION['flash_message'] = ['tipo' => 'success', 'mensaje' => '¡Solicitud de vacaciones enviada correctamente!'];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $mensaje = "Error al registrar la solicitud: " . $conn->error;
                $mensaje_tipo = 'danger';
            }
            $stmt_insert->close();
        }
        $check_stmt->close();
        // --- FIN DE LA CORRECCIÓN ---
    }
}

// Manejo de mensajes flash para mostrar después de una redirección
if(isset($_SESSION['flash_message'])){
    $mensaje = $_SESSION['flash_message']['mensaje'];
    $mensaje_tipo = $_SESSION['flash_message']['tipo'];
    unset($_SESSION['flash_message']);
}

$solicitudes = [];
if ($idColaborador > 0) {
    // --- CORRECCIÓN: Usar dias_habiles para el historial ---
    $stmt_historial = $conn->prepare("
    SELECT p.fecha_inicio, p.fecha_fin, p.dias_habiles as dias_solicitados, ec.Descripcion AS estado, p.observaciones, p.motivo
    FROM permisos p
    JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
    JOIN estado_cat ec ON p.id_estado_fk = ec.idEstado
    WHERE tpc.Descripcion = 'Vacaciones' AND p.id_colaborador_fk = ?
    ORDER BY p.fecha_inicio DESC");
    $stmt_historial->bind_param("i", $idColaborador);
    $stmt_historial->execute();
    $result_historial = $stmt_historial->get_result();
    while ($row_historial = $result_historial->fetch_assoc()) { $solicitudes[] = $row_historial; }
    $stmt_historial->close();
}
$conn->close();

$feriados_para_js = file_exists('js/feriados.json') ? json_decode(file_get_contents('js/feriados.json'), true) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Vacaciones - Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body { background: linear-gradient(135deg, #eaf6ff 0%, #f4f7fc 100%) !important; font-family: 'Poppins', sans-serif; }
        .main-container { max-width: 1200px; margin: 48px auto 0; padding: 0 15px; }
        .main-card { background: #fff; border-radius: 2.1rem; box-shadow: 0 8px 38px 0 rgba(44,62,80,.12); padding: 2.2rem 2.1rem 1.7rem 2.1rem; margin-bottom: 2.2rem; animation: fadeInDown 0.9s; }
        .card-title-custom { font-size: 2.2rem; font-weight: 900; color: #1a3961; letter-spacing: .7px; margin-bottom: 0.5rem; display: flex; align-items: center; gap: .8rem; }
        .card-title-custom i { color: #3499ea; font-size: 2.2rem; }
        .card-title-custom .info-icon { font-size: 1.2rem; color: #3498db; cursor: pointer; }
        .text-center { color: #3a6389; }
        .form-label { color: #288cc8; font-weight: 600; }
        .form-control, input[type="date"] { border-radius: 0.9rem; }
        .btn-submit-custom { background: linear-gradient(90deg, #1f8ff7 75%, #53e3fc 100%); color: #fff; font-weight: 700; font-size: 1.05rem; border-radius: 0.8rem; padding: .63rem 1.5rem; box-shadow: 0 2px 12px #1f8ff722; width: 100%; margin-top: 1rem; border: none;}
        .btn-submit-custom:hover { background: linear-gradient(90deg, #53e3fc 25%, #1f8ff7 100%); color: #fff; }
        .table-custom { background: #f8fafd; border-radius: 1.15rem; overflow: hidden; box-shadow: 0 4px 24px #23b6ff10; }
        .table-custom th { background: #e9f6ff; color: #288cc8; font-weight: 700; font-size: 1.1rem; }
        .table-custom td, .table-custom th { padding: 0.75rem 0.7rem; text-align: center; vertical-align: middle; }
        .badge-disponibles { background: linear-gradient(90deg, #01b87f 60%, #53e3fc 100%); color: #fff; font-size: 1.1rem; padding: .6rem 1.1rem; border-radius: .9rem; font-weight: 600; }
        .badge.bg-warning { background-color: #ffd237 !important; color: #6a4d00 !important; }
        .badge.bg-success { background-color: #01b87f !important; }
        .badge.bg-danger { background-color: #ff6565 !important; }
        .section-title { font-weight: 700; color: #1a3961; font-size: 1.4rem; margin-bottom: 1rem; text-align: center; }
        .calendar { width: 100%; border: 1px solid #dee2e6; border-radius: 1rem; overflow: hidden; }
        .calendar-header { text-align: center; padding: 10px; background: #5e72e4; color: white; font-weight: bold; }
        .calendar-body { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background-color: #dee2e6; }
        .calendar-day, .calendar-weekday { text-align: center; padding: 10px; background-color: white; }
        .calendar-weekday { font-weight: bold; background: #f8f9fa; }
        .calendar-day.feriado { background-color: #ffc107; color: black; font-weight: bold; position: relative; cursor: pointer; }
        @media (max-width: 600px) {
            .main-card { padding: 1.1rem 0.3rem 0.9rem 0.3rem; }
            .card-title-custom { font-size: 1.3rem; }
            .table-custom th, .table-custom td { font-size: .96rem; padding: 0.4rem 0.3rem;}
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="main-container">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="main-card h-100">
                <div class="card-title-custom animate__animated animate__fadeInDown">
                    <i class="bi bi-sun-fill"></i>
                    <span>Solicitar Vacaciones</span>
                    <i class="bi bi-info-circle-fill info-icon"
                       data-bs-toggle="tooltip"
                       data-bs-html="true"
                       title="<div class='text-start'>
                                <strong>Ley de Vacaciones en Costa Rica:</strong><br>
                                - Tienes derecho a 2 semanas de vacaciones por cada 50 semanas de trabajo (1 día por mes).<br>
                                - El sistema no te permitirá solicitar vacaciones en días feriados de pago obligatorio.<br>
                                - El saldo se descuenta una vez que tu solicitud es aprobada por tu jefatura.
                              </div>">
                    </i>
                </div>
                <p class="text-center mb-4">Planifica tu descanso y gestiona tus días libres.</p>

                <form method="post">
                    <div class="mb-4 text-center">
                        <span class="badge-disponibles"><i class="bi bi-check-circle"></i> Disponibles: <?= $dias_disponibles ?> días</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Inicio</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Fin</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comentario (Opcional)</label>
                        <input type="text" name="motivo" class="form-control" placeholder="Ej: Viaje familiar" maxlength="100">
                    </div>
                    <button type="submit" name="solicitar" class="btn btn-submit-custom">
                        <i class="bi bi-send-fill"></i> Enviar Solicitud
                    </button>
                </form>

                <?php if ($mensaje): 
                    $icon_class = 'bi-info-circle-fill'; 
                    if ($mensaje_tipo == 'success') {
                        $icon_class = 'bi-check-circle-fill';
                    } elseif ($mensaje_tipo == 'danger' || $mensaje_tipo == 'warning') {
                        $icon_class = 'bi-exclamation-triangle-fill';
                    }
                ?>
                    <div class="alert alert-<?= $mensaje_tipo ?> d-flex align-items-center mt-4" role="alert">
                        <i class="bi <?= $icon_class ?> me-2" style="font-size:1.2rem;"></i>
                        <div class="fw-bold"><?= htmlspecialchars($mensaje) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="main-card h-100">
                <div id="calendar-container"></div>
            </div>
        </div>
    </div>
    
    <div class="main-card mt-4">
        <div class="section-title">
            <i class="bi bi-clock-history"></i> Historial de Solicitudes
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días Hábiles</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr><td colspan="5">Aún no tienes solicitudes registradas.</td></tr>
                    <?php else: foreach ($solicitudes as $sol): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($sol['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($sol['fecha_fin'])) ?></td>
                            <td><?= htmlspecialchars($sol['dias_solicitados']) ?></td>
                            <td>
                                <?php
                                $estado_lower = strtolower($sol['estado']);
                                if ($estado_lower == 'pendiente') echo '<span class="badge bg-warning">Pendiente</span>';
                                else if ($estado_lower == 'aprobado') echo '<span class="badge bg-success">Aprobado</span>';
                                else if ($estado_lower == 'rechazado') echo '<span class="badge bg-danger">Rechazado</span>';
                                else echo '<span class="badge bg-info">'.htmlspecialchars($sol['estado']).'</span>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($sol['motivo'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // El resto del JavaScript no necesita cambios
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        
        fechaInicioInput.addEventListener('change', function() {
            if (fechaFinInput.value < fechaInicioInput.value) {
                fechaFinInput.value = fechaInicioInput.value;
            }
            fechaFinInput.min = fechaInicioInput.value;
        });

        const feriados = <?php echo json_encode($feriados_para_js); ?>;
        const calendarContainer = document.getElementById('calendar-container');
        const now = new Date();
        let currentMonth = now.getMonth();
        let currentYear = now.getFullYear();

        function renderCalendar(month, year) {
            calendarContainer.innerHTML = '';
            const header = document.createElement('div');
            header.className = 'calendar-header d-flex justify-content-between align-items-center';
            
            const prevButton = document.createElement('button');
            prevButton.className = 'btn btn-light btn-sm';
            prevButton.innerHTML = '<i class="bi bi-arrow-left"></i>';
            prevButton.onclick = () => {
                currentMonth--;
                if (currentMonth < 0) { currentMonth = 11; currentYear--; }
                renderCalendar(currentMonth, currentYear);
            };

            const nextButton = document.createElement('button');
            nextButton.className = 'btn btn-light btn-sm';
            nextButton.innerHTML = '<i class="bi bi-arrow-right"></i>';
            nextButton.onclick = () => {
                currentMonth++;
                if (currentMonth > 11) { currentMonth = 0; currentYear++; }
                renderCalendar(currentMonth, currentYear);
            };
            
            const title = document.createElement('span');
            title.textContent = new Date(year, month).toLocaleString('es-CR', { month: 'long', year: 'numeric' });
            title.className = "mx-2";

            header.appendChild(prevButton);
            header.appendChild(title);
            header.appendChild(nextButton);
            calendarContainer.appendChild(header);

            const body = document.createElement('div');
            body.className = 'calendar-body';
            const weekdays = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'];
            weekdays.forEach(day => {
                const dayEl = document.createElement('div');
                dayEl.className = 'calendar-weekday';
                dayEl.textContent = day;
                body.appendChild(dayEl);
            });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day';
                body.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = day;
                
                const fechaCompleta = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const feriado = feriados.find(f => f.fecha === fechaCompleta);

                if (feriado) {
                    dayCell.classList.add('feriado');
                    dayCell.setAttribute('data-bs-toggle', 'tooltip');
                    dayCell.setAttribute('data-bs-title', feriado.descripcion);
                }
                body.appendChild(dayCell);
            }
            calendarContainer.appendChild(body);
            
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }

        renderCalendar(currentMonth, currentYear);
    });
</script>
</body>
</html>