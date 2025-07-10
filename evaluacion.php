<?php
session_start();

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
body {
    background: linear-gradient(120deg, #e8f7ff 0%, #e2f0fa 60%, #d8e6ef 100%);
    font-family: 'Segoe UI', 'Nunito', 'Roboto', sans-serif;
}
.eval-glass-center {
    min-height: 94vh;
    min-width: 100vw;
    display: flex;
    align-items: center;
    justify-content: center;
}
.eval-glass-card {
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(10px);
    box-shadow: 0 6px 32px #179ad77e, 0 2px 6px #6de5e920;
    border-radius: 2.2rem;
    max-width: 440px;
    width: 100%;
    padding: 2.6rem 2rem 2rem 2rem;
    animation: glassfade .7s;
}
@keyframes glassfade {
    0% { opacity:0; transform: translateY(30px);}
    100%{ opacity:1; transform: none;}
}
.eval-brand-bar {
    background: linear-gradient(90deg, #0e8acb 0%, #14e0ec 100%);
    border-radius: 2rem 2rem 0 0;
    margin: -2.6rem -2rem 1.6rem -2rem;
    padding: 1.5rem 2rem 1.1rem 2rem;
    text-align: center;
    box-shadow: 0 3px 22px #11bff844;
}
.eval-brand-title {
    color: #fff;
    font-size: 1.45rem;
    font-weight: 900;
    letter-spacing: 1.2px;
    margin-bottom: 0;
    text-shadow: 0 2px 20px #0e122870;
}
.eval-brand-icon {
    font-size: 2.3rem;
    color: #fff;
    filter: drop-shadow(0 2px 12px #18c0ff93);
    margin-right: .5rem;
}
.form-label {
    font-weight: 700;
    color: #1783b0;
}
.btn-glow {
    background: linear-gradient(90deg, #14c8ee 60%, #21aaff 100%);
    color: #fff;
    font-weight: 800;
    letter-spacing: .7px;
    border: none;
    border-radius: 1rem;
    padding: .7rem 1.7rem;
    margin-top: .3rem;
    box-shadow: 0 2px 16px #18c0ff45;
    transition: box-shadow .14s, background .2s;
}
.btn-glow:hover {
    background: linear-gradient(90deg, #23b6ff 30%, #47d9fd 100%);
    color: #fff;
    box-shadow: 0 6px 24px #18c0ff85;
}
.select-lg {
    font-size: 1.13rem;
    padding: .65rem;
    border-radius: 1.2rem;
}
#avg-badge {
    font-size: 1.03rem;
    border-radius: 1rem;
    background: #e2f4ff;
    color: #0e86cb;
    margin: .8rem auto .3rem auto;
    padding: .35rem 1.1rem;
    font-weight: 700;
    display: none;
    box-shadow: 0 1px 7px #18c0ff13;
}
.eval-footer {
    margin-top: 1.5rem;
    font-size: .95rem;
    color: #179ad7;
    text-align: center;
    opacity: .68;
    letter-spacing: .5px;
}
@media (max-width: 550px) {
    .eval-glass-card { padding: 1.5rem .3rem 1.4rem .3rem; border-radius: 1.1rem; }
    .eval-brand-bar { border-radius: 1.1rem 1.1rem 0 0; }
}
</style>

<div class="eval-glass-center">
    <div class="eval-glass-card">
        <div class="eval-brand-bar">
            <span class="eval-brand-icon"><i class="bi bi-stars"></i></span>
            <span class="eval-brand-title">Evaluación de Empleado</span>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show text-center" role="alert" style="font-size:1.08rem;">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-3" id="evalForm" autocomplete="off">
            <div class="col-12">
                <label for="id_colaborador" class="form-label">
                    Empleado a Evaluar
                    <i class="bi bi-info-circle text-info" data-bs-toggle="tooltip" title="Seleccione el colaborador que desea evaluar."></i>
                </label>
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
            <div class="col-12 d-flex justify-content-between align-items-center gap-2">
                <div style="flex:1;">
                    <label class="form-label">Puntualidad
                        <i class="bi bi-info-circle text-info" data-bs-toggle="tooltip" title="¿Llega a tiempo, cumple horarios?"></i>
                    </label>
                    <select name="puntualidad" id="puntualidad" class="form-select select-lg" required>
                        <option value="">--</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="form-label">Desempeño
                        <i class="bi bi-info-circle text-info" data-bs-toggle="tooltip" title="¿Cómo realiza su trabajo y tareas?"></i>
                    </label>
                    <select name="desempeno" id="desempeno" class="form-select select-lg" required>
                        <option value="">--</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="form-label">Trabajo en equipo
                        <i class="bi bi-info-circle text-info" data-bs-toggle="tooltip" title="¿Colabora y trabaja bien con otros?"></i>
                    </label>
                    <select name="trabajo_equipo" id="trabajo_equipo" class="form-select select-lg" required>
                        <option value="">--</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 text-center">
                <div id="avg-badge">
                    Promedio: <span id="promedio-num"></span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Comentarios adicionales</label>
                <textarea name="comentarios" class="form-control" rows="2" style="border-radius:1rem;" placeholder="Escribe aquí tus observaciones o sugerencias"></textarea>
            </div>
            <div class="col-12 text-center">
                <button type="submit" class="btn btn-glow px-5"><i class="bi bi-send-check"></i> Guardar Evaluación</button>
            </div>
        </form>
        <div class="eval-footer">
            <i class="bi bi-shield-check"></i> Esta evaluación es confidencial y solo será visible para RRHH y jefatura autorizada.
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Inicializar tooltips Bootstrap
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// Promedio dinámico
function calcularPromedio() {
    const p = parseInt(document.getElementById('puntualidad').value) || 0;
    const d = parseInt(document.getElementById('desempeno').value) || 0;
    const t = parseInt(document.getElementById('trabajo_equipo').value) || 0;
    const total = [p, d, t].filter(n=>n>0).length;
    let avg = (total === 3) ? ((p + d + t) / 3).toFixed(2) : '';
    const badge = document.getElementById('avg-badge');
    document.getElementById('promedio-num').textContent = avg;
    badge.style.display = (avg ? 'inline-block' : 'none');
}
['puntualidad','desempeno','trabajo_equipo'].forEach(function(id){
    document.getElementById(id).addEventListener('change', calcularPromedio);
});
</script>