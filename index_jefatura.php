<?php
session_start();
include 'db.php';

// Validar acceso solo para Jefatura (rol 3)
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 3) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('America/Costa_Rica');

// --- INICIO: Lógica de Control de Asistencia para la Jefatura ---
$jefatura_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;
$mensaje_asistencia = '';
$tipoMensaje = '';
$fechaHoy = date('Y-m-d');

// Verificar estado de asistencia actual de la jefatura
$marcoEntrada = false;
$marcoSalida = false;
$asistencia = null;
if ($persona_id) {
    $stmt_asist_check = $conn->prepare("SELECT * FROM control_de_asistencia WHERE Persona_idPersona = ? AND DATE(Fecha) = ?");
    $stmt_asist_check->bind_param("is", $persona_id, $fechaHoy);
    $stmt_asist_check->execute();
    $result_asist = $stmt_asist_check->get_result();
    if($result_asist->num_rows > 0) {
        $asistencia = $result_asist->fetch_assoc();
        $marcoEntrada = !is_null($asistencia['Entrada']);
        $marcoSalida = !is_null($asistencia['Salida']) && $asistencia['Salida'] != '00:00:00';
    }
    $stmt_asist_check->close();
}


// Procesar marcaje de asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['marcar_asistencia']) && $persona_id) {
    $tipo_marca = $_POST['tipo_marca'];
    $hora_actual = date('H:i:s');

    if ($tipo_marca == 'Entrada') {
        if ($marcoEntrada) {
            $mensaje_asistencia = "Ya has marcado tu entrada el día de hoy.";
            $tipoMensaje = 'warning';
        } else {
            $stmtEntrada = $conn->prepare("INSERT INTO control_de_asistencia (Persona_idPersona, Fecha, Entrada) VALUES (?, ?, ?)");
            $stmtEntrada->bind_param("iss", $persona_id, $fechaHoy, $hora_actual);
            if ($stmtEntrada->execute()) {
                $mensaje_asistencia = "¡Entrada marcada con éxito a las $hora_actual!";
                $tipoMensaje = 'success';
                $marcoEntrada = true; // Actualiza el estado en la misma carga
            } else {
                $mensaje_asistencia = "Error al marcar la entrada.";
                $tipoMensaje = 'danger';
            }
            $stmtEntrada->close();
        }
    } elseif ($tipo_marca == 'Salida') {
        if (!$marcoEntrada) {
            $mensaje_asistencia = "Debes marcar tu entrada antes de marcar una salida.";
            $tipoMensaje = 'warning';
        } elseif ($marcoSalida) {
            $mensaje_asistencia = "Ya has marcado tu salida el día de hoy.";
            $tipoMensaje = 'warning';
        } else {
            // Actualiza la salida en el registro de hoy
            $stmtSalida = $conn->prepare("UPDATE control_de_asistencia SET Salida = ? WHERE Persona_idPersona = ? AND DATE(Fecha) = ?");
            $stmtSalida->bind_param("sis", $hora_actual, $persona_id, $fechaHoy);
            if ($stmtSalida->execute()) {
                $mensaje_asistencia = "¡Salida marcada con éxito a las $hora_actual!";
                $tipoMensaje = 'success';
                $marcoSalida = true; // Actualiza el estado en la misma carga
            } else {
                $mensaje_asistencia = "Error al marcar la salida.";
                $tipoMensaje = 'danger';
            }
            $stmtSalida->close();
        }
    }
}
// --- FIN: Lógica de Control de Asistencia ---


// Consulta: colaboradores bajo este jefe
$sql_colaboradores = "
SELECT 
    c.idColaborador,
    CONCAT(p.Nombre, ' ', p.Apellido1, ' ', p.Apellido2) AS nombre_completo,
    d.nombre AS departamento,
    c.salario_bruto,
    j.idColaborador AS jefe_id,
    CONCAT(jp.Nombre, ' ', jp.Apellido1, ' ', jp.Apellido2) AS nombre_jefe
FROM colaborador c
INNER JOIN persona p ON c.id_persona_fk = p.idPersona
INNER JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
LEFT JOIN colaborador j ON c.id_jefe_fk = j.idColaborador
LEFT JOIN persona jp ON j.id_persona_fk = jp.idPersona
WHERE c.id_jefe_fk = ?
ORDER BY nombre_completo ASC
";
$stmt_colaboradores = $conn->prepare($sql_colaboradores);
$stmt_colaboradores->bind_param("i", $jefatura_id);
$stmt_colaboradores->execute();
$result_colaboradores = $stmt_colaboradores->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Jefatura - Sistema RRHH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fc; font-family: 'Poppins', sans-serif; }
        .main-content { margin-left: 280px; padding: 2rem;}
        .card { border-radius: 1.5rem; border: none; box-shadow: 0 0.5rem 2rem rgba(60,72,88,0.10); }
        .card-header { background: #fff; border-bottom: 1px solid #e4e7ed; padding: 1.5rem 2rem 1rem 2rem; }
        .card-body { padding: 2rem; }
        .table thead th { background: #5e72e4; color: #fff; font-weight: 600; border: none; }
        .search-box { max-width: 300px; margin-bottom: 1.2rem; }
        .no-result { text-align: center; padding: 2.5rem 0 2rem 0; color: #b0b3c3; }
        .clock-card { background: linear-gradient(135deg, #6a82fb, #5e72e4); color: white; text-align: center;}
        .clock-time { font-size: 2.8rem; font-weight: 700; letter-spacing: 2px; }
        .clock-date { font-size: 1rem; opacity: 0.9; }
        .btn-asistencia { padding: 0.7rem 1.5rem; font-weight: 600; border-radius: 50px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="row g-4">
            <div class="col-12">
                 <div class="card clock-card p-4">
                    <div id="clock" class="clock-time">00:00:00</div>
                    <div id="date" class="clock-date">Cargando fecha...</div>
                    <div class="mt-4">
                        <form method="POST" class="d-inline-flex gap-3">
                            <input type="hidden" name="tipo_marca" value="Entrada">
                            <button type="submit" name="marcar_asistencia" class="btn btn-light btn-asistencia" <?php if ($marcoEntrada) echo 'disabled'; ?>>
                                <i class="bi bi-box-arrow-in-right me-2"></i>Marcar Entrada
                            </button>
                            <input type="hidden" name="tipo_marca" value="Salida">
                            <button type="submit" name="marcar_asistencia" class="btn btn-outline-light btn-asistencia" <?php if (!$marcoEntrada || $marcoSalida) echo 'disabled'; ?>>
                                <i class="bi bi-box-arrow-left me-2"></i>Marcar Salida
                            </button>
                        </form>
                    </div>
                    <?php if ($mensaje_asistencia): ?>
                        <div class="alert alert-light mt-3 mb-0" style="color: #5e72e4; border:none;"><?= htmlspecialchars($mensaje_asistencia); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" style="font-weight: 700; color:#5e72e4;"><i class="bi bi-people-fill me-2"></i>Colaboradores a Cargo</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control search-box" id="searchInput" placeholder="Buscar colaborador...">

                        <div class="table-responsive">
                            <table class="table align-middle table-hover" id="collabTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre completo</th>
                                        <th>Departamento</th>
                                        <th>Salario bruto</th>
                                        <th>Jefe directo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($result_colaboradores && $result_colaboradores->num_rows > 0): ?>
                                    <?php $i=1; while($row = $result_colaboradores->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($row['nombre_completo']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($row['departamento']) ?></td>
                                            <td>₡<?= number_format($row['salario_bruto'], 2) ?></td>
                                            <td><?= $row['nombre_jefe'] ? htmlspecialchars($row['nombre_jefe']) : '<span class="badge bg-secondary">Sin jefe</span>' ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="no-result">
                                        <i class="bi bi-person-slash" style="font-size:2rem"></i><br>
                                        No tienes colaboradores a tu cargo.
                                    </td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if(searchInput) {
                searchInput.addEventListener('keyup', function() {
                    let filter = this.value.toLowerCase();
                    let rows = document.querySelectorAll("#collabTable tbody tr");
                    rows.forEach(function(row) {
                        if (row.cells.length > 1) {
                           let name = row.cells[1].innerText.toLowerCase();
                           row.style.display = name.includes(filter) ? "" : "none";
                        }
                    });
                });
            }

            const clockElement = document.getElementById('clock');
            const dateElement = document.getElementById('date');
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-CR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const dateString = now.toLocaleDateString('es-CR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                
                clockElement.textContent = timeString;
                dateElement.textContent = dateString.charAt(0).toUpperCase() + dateString.slice(1);
            }
            setInterval(updateClock, 1000);
            updateClock();
        });
    </script>
</body>
</html>