<?php
session_start();

// --- LÓGICA AJAX PARA OBTENER HISTORIAL ---
if (isset($_GET['ajax']) && isset($_GET['colaborador_id'])) {
    require_once 'db.php';
    header('Content-Type: application/json');
    $id_colaborador = intval($_GET['colaborador_id']);
    $historial = [];
    if ($id_colaborador > 0) {
        $stmt_hist = $conn->prepare("SELECT Fecharealizacion, Calificacion, Comentarios FROM evaluaciones WHERE Colaborador_idColaborador = ? ORDER BY Fecharealizacion DESC LIMIT 5");
        $stmt_hist->bind_param("i", $id_colaborador);
        $stmt_hist->execute();
        $result_hist = $stmt_hist->get_result();
        $historial = $result_hist->fetch_all(MYSQLI_ASSOC);
        $stmt_hist->close();
    }
    echo json_encode($historial);
    $conn->close();
    exit;
}
// --- FIN LÓGICA AJAX ---


if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$roles_permitidos = [1, 3, 4];
$usuario_rol = $_SESSION['rol'] ?? 0;
$id_jefe_logueado = $_SESSION['colaborador_id'] ?? 0;

if (!in_array($usuario_rol, $roles_permitidos)) {
    include 'header.php';
    echo '<div class="container" style="margin-left:280px;max-width:600px;padding-top:3rem;">
            <div class="alert alert-danger shadow text-center">
                <i class="bi bi-shield-exclamation" style="font-size:2.5rem;"></i><br>
                <b>No tienes permisos para acceder a la evaluación de empleados.</b>
            </div>
          </div>';
    include 'footer.php';
    exit;
}

require_once 'db.php';

$msg = "";
$msg_type = "success";

// --- OBTENER COLABORADORES SEGÚN ROL ---
$colaboradores = [];
$sql_colaboradores = "SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2
                      FROM colaborador c
                      INNER JOIN persona p ON c.id_persona_fk = p.idPersona
                      WHERE c.activo = 1";

if ($usuario_rol == 3) { // Si es Jefatura, filtrar por su ID
    $sql_colaboradores .= " AND c.id_jefe_fk = ?";
    $stmt = $conn->prepare($sql_colaboradores);
    $stmt->bind_param("i", $id_jefe_logueado);
} else { // Si es Admin o RRHH, no se aplica filtro de jefe
    $stmt = $conn->prepare($sql_colaboradores);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $colaboradores[] = $row;
}
$stmt->close();


// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_colaborador'])) {
    $id_colaborador_evaluado = intval($_POST['id_colaborador']);
    $fecha = date('Y-m-d');
    $puntualidad = intval($_POST['puntualidad']);
    $desempeno = intval($_POST['desempeno']);
    $trabajo_equipo = intval($_POST['trabajo_equipo']);
    $comentarios = trim($_POST['comentarios']);
    
    // Calificación promedio
    $calificacion = round(($puntualidad + $desempeno + $trabajo_equipo) / 3);

    // --- VALIDACIÓN DE PERMISOS ANTES DE GUARDAR ---
    $puede_evaluar = false;
    if ($usuario_rol == 1 || $usuario_rol == 4) {
        $puede_evaluar = true; // Admin y RRHH pueden evaluar a todos
    } elseif ($usuario_rol == 3) {
        // Verificar que el colaborador evaluado pertenece al equipo del jefe
        $stmt_verify = $conn->prepare("SELECT COUNT(*) FROM colaborador WHERE idColaborador = ? AND id_jefe_fk = ?");
        $stmt_verify->bind_param("ii", $id_colaborador_evaluado, $id_jefe_logueado);
        $stmt_verify->execute();
        $stmt_verify->bind_result($count);
        $stmt_verify->fetch();
        $stmt_verify->close();
        if ($count > 0) {
            $puede_evaluar = true;
        }
    }
    
    if ($id_colaborador_evaluado && $calificacion && $puede_evaluar) {
        $stmt_insert = $conn->prepare("INSERT INTO evaluaciones (Colaborador_idColaborador, Fecharealizacion, Calificacion, Comentarios) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("isis", $id_colaborador_evaluado, $fecha, $calificacion, $comentarios);
        if ($stmt_insert->execute()) {
            $msg = "¡Evaluación registrada correctamente!";
            $msg_type = "success";
        } else {
            $msg = "Error al guardar la evaluación.";
            $msg_type = "danger";
        }
        $stmt_insert->close();
    } else {
        if (!$puede_evaluar) {
            $msg = "Error: No tienes permiso para evaluar a este colaborador.";
        } else {
            $msg = "Todos los campos de calificación son obligatorios.";
        }
        $msg_type = "danger";
    }
}
?>

<?php include 'header.php'; ?>

<style>
    .main-container { margin-left: 280px; padding: 2.5rem; }
    .main-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
        background: #fff;
    }
    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }
    .card-title-custom { font-weight: 600; font-size: 1.25rem; color: #32325d; margin: 0; }
    .card-title-custom i { color: #5e72e4; }
    .form-label { font-weight: 600; color: #525f7f; font-size: 0.9rem; }
    .select-lg { font-size: 1.1rem; }
    .btn-submit { background-color: #5e72e4; border-color: #5e72e4; font-weight: 600; }
    #avg-badge {
        font-size: 1rem;
        background: #f6f9fc;
        color: #5e72e4;
        border: 1px solid #dee2e6;
        display: none;
    }
    /* Historial */
    .historial-list { list-style: none; padding: 0; max-height: 450px; overflow-y: auto; }
    .historial-item {
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 0.5rem;
    }
    .historial-item:last-child { border-bottom: none; }
    .historial-date { font-size: 0.8rem; font-weight: 600; color: #8898aa; }
    .historial-stars { color: #ffd600; font-size: 1.1rem; }
    .historial-comment { font-size: 0.9rem; color: #525f7f; font-style: italic; margin-top: 0.25rem; }
</style>

<div class="main-container">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="main-card h-100">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-star-half me-2"></i>Registrar Nueva Evaluación</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="evalForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="id_colaborador" class="form-label">Empleado a Evaluar</label>
                            <select name="id_colaborador" id="id_colaborador" class="form-select select-lg" required>
                                <option value="">Seleccione un colaborador...</option>
                                <?php if (empty($colaboradores) && $usuario_rol == 3): ?>
                                    <option value="" disabled>No tienes colaboradores a tu cargo.</option>
                                <?php else: ?>
                                    <?php foreach ($colaboradores as $col): ?>
                                        <option value="<?= $col['idColaborador'] ?>">
                                            <?= htmlspecialchars($col['Nombre'].' '.$col['Apellido1'].' '.$col['Apellido2']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Puntualidad</label>
                                <select name="puntualidad" id="puntualidad" class="form-select" required>
                                    <option value="">Calificar...</option>
                                    <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Desempeño</label>
                                <select name="desempeno" id="desempeno" class="form-select" required>
                                    <option value="">Calificar...</option>
                                     <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trabajo en Equipo</label>
                                <select name="trabajo_equipo" id="trabajo_equipo" class="form-select" required>
                                    <option value="">Calificar...</option>
                                     <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="text-center mb-3">
                            <div class="badge p-2" id="avg-badge">
                                Promedio Final: <strong id="promedio-num"></strong> ★
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comentarios Adicionales</label>
                            <textarea name="comentarios" class="form-control" rows="3" placeholder="Observaciones, sugerencias de mejora, etc."></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-submit px-4"><i class="bi bi-send-check me-2"></i>Guardar Evaluación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="main-card h-100">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-clock-history me-2"></i>Historial Reciente</h5>
                </div>
                <div class="card-body p-3" id="historial-container">
                    <div class="text-center text-muted p-5">
                        <i class="bi bi-person-check" style="font-size: 3rem;"></i>
                        <p class="mt-2">Seleccione un colaborador para ver su historial de evaluaciones.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Función para calcular el promedio dinámico
    function calcularPromedio() {
        const p = parseInt(document.getElementById('puntualidad').value) || 0;
        const d = parseInt(document.getElementById('desempeno').value) || 0;
        const t = parseInt(document.getElementById('trabajo_equipo').value) || 0;
        const count = [p, d, t].filter(n => n > 0).length;
        const badge = document.getElementById('avg-badge');
        
        if (count === 3) {
            let avg = ((p + d + t) / 3);
            document.getElementById('promedio-num').textContent = avg.toFixed(1);
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
    ['puntualidad','desempeno','trabajo_equipo'].forEach(id => {
        document.getElementById(id).addEventListener('change', calcularPromedio);
    });

    // --- Lógica para cargar el historial con AJAX ---
    const colaboradorSelect = document.getElementById('id_colaborador');
    const historialContainer = document.getElementById('historial-container');

    colaboradorSelect.addEventListener('change', function() {
        const colaboradorId = this.value;
        historialContainer.innerHTML = `<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>`;

        if (!colaboradorId) {
            historialContainer.innerHTML = `<div class="text-center text-muted p-5"><i class="bi bi-person-check" style="font-size: 3rem;"></i><p class="mt-2">Seleccione un colaborador para ver su historial.</p></div>`;
            return;
        }

        fetch(`evaluacion.php?ajax=1&colaborador_id=${colaboradorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    historialContainer.innerHTML = `<div class="text-center text-muted p-5"><i class="bi bi-emoji-smile" style="font-size: 3rem;"></i><p class="mt-2">Este colaborador aún no tiene evaluaciones registradas.</p></div>`;
                    return;
                }

                let html = '<ul class="historial-list">';
                data.forEach(eval => {
                    const fecha = new Date(eval.Fecharealizacion).toLocaleDateString('es-CR', { year: 'numeric', month: 'long', day: 'numeric' });
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        stars += `<i class="bi ${i <= eval.Calificacion ? 'bi-star-fill' : 'bi-star'}"></i>`;
                    }
                    const comentario = eval.Comentarios ? eval.Comentarios : 'Sin comentarios.';

                    html += `<li class="historial-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="historial-date">${fecha}</span>
                                    <span class="historial-stars">${stars} (${eval.Calificacion}/5)</span>
                                </div>
                                <p class="historial-comment mb-0">"${comentario}"</p>
                             </li>`;
                });
                html += '</ul>';
                historialContainer.innerHTML = html;
            })
            .catch(error => {
                console.error('Error al cargar el historial:', error);
                historialContainer.innerHTML = `<div class="alert alert-danger">No se pudo cargar el historial.</div>`;
            });
    });
});
</script>
</body>
</html>