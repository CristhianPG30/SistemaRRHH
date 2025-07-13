<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// --- Permitir acceso a Administrador (1) y Recursos Humanos (4) ---
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// --- Manejo de activación/desactivación ---
if (isset($_GET['toggle_id'])) {
    $idPersona = intval($_GET['toggle_id']);
    $stmt = $conn->prepare("SELECT idColaborador, activo FROM colaborador WHERE id_persona_fk = ?");
    $stmt->bind_param("i", $idPersona);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $colaborador = $result->fetch_assoc();
        $nuevoEstado = $colaborador['activo'] ? 0 : 1;
        $stmt_update = $conn->prepare("UPDATE colaborador SET activo = ? WHERE idColaborador = ?");
        $stmt_update->bind_param("ii", $nuevoEstado, $colaborador['idColaborador']);
        $stmt_update->execute();
        $stmt_update->close();
        header("Location: personas.php");
        exit;
    }
    $stmt->close();
}

// --- LÓGICA DE BORRADO PERMANENTE CORREGIDA ---
if (isset($_POST['delete_id'])) {
    // Solo Admin puede borrar permanentemente
    if ($_SESSION['rol'] != 1) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'No tienes permiso para realizar esta acción.'];
        header('Location: personas.php');
        exit;
    }
    
    $idPersona = intval($_POST['delete_id']);
    $conn->begin_transaction();
    try {
        // Obtenemos los IDs de colaborador asociados a la persona
        $colabs = [];
        $resColab = $conn->query("SELECT idColaborador FROM colaborador WHERE id_persona_fk = $idPersona");
        while ($colab = $resColab->fetch_assoc()) {
            $colabs[] = $colab['idColaborador'];
        }

        // Si existen colaboradores, borramos todas sus dependencias
        if (count($colabs) > 0) {
            $colabs_imploded = implode(',', $colabs);
            $conn->query("UPDATE colaborador SET id_jefe_fk = 1 WHERE id_jefe_fk IN ($colabs_imploded) AND id_jefe_fk <> 1");
            $conn->query("DELETE FROM jerarquia WHERE Colaborador_idColaborador IN ($colabs_imploded) OR Jefe_idColaborador IN ($colabs_imploded)");
            $conn->query("DELETE FROM aguinaldo WHERE id_colaborador_fk IN ($colabs_imploded)");
            $conn->query("DELETE FROM evaluaciones WHERE Colaborador_idColaborador IN ($colabs_imploded)");
            $conn->query("DELETE FROM horas_extra WHERE Colaborador_idColaborador IN ($colabs_imploded)");
            $conn->query("DELETE FROM liquidaciones WHERE id_colaborador_fk IN ($colabs_imploded)");
            $conn->query("DELETE FROM permisos WHERE id_colaborador_fk IN ($colabs_imploded)");
            
            // ¡¡LÍNEA FALTANTE AÑADIDA!! Borra los detalles de deducciones ANTES que las planillas.
            $conn->query("DELETE FROM deducciones_detalle WHERE id_colaborador_fk IN ($colabs_imploded)");
            
            $conn->query("DELETE FROM planillas WHERE id_colaborador_fk IN ($colabs_imploded)");
            $conn->query("DELETE FROM vacaciones WHERE id_colaborador_fk IN ($colabs_imploded)");
        }

        // Borramos dependencias directas de la persona
        $conn->query("DELETE FROM horas_extra WHERE Persona_idPersona = $idPersona");
        $conn->query("DELETE FROM control_de_asistencia WHERE Persona_idPersona = $idPersona");
        $conn->query("DELETE FROM persona_correos WHERE idPersona_fk = $idPersona");
        $conn->query("DELETE FROM persona_telefonos WHERE id_persona_fk = $idPersona");
        $conn->query("DELETE FROM usuario WHERE id_persona_fk = $idPersona");
        
        // Borramos el colaborador y finalmente la persona
        $conn->query("DELETE FROM colaborador WHERE id_persona_fk = $idPersona");
        $stmt = $conn->prepare("DELETE FROM persona WHERE idPersona = ?");
        $stmt->bind_param("i", $idPersona);
        $stmt->execute();
        
        $conn->commit();
        $message = 'Persona eliminada permanentemente.';
        $message_type = 'success';
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        // Con la corrección, no deberías volver a ver este error.
        $message = 'Error al eliminar. La persona puede tener registros asociados. Detalle: ' . $exception->getMessage();
        $message_type = 'danger';
    }
}

// El resto del archivo permanece igual...

function getIdentificacionInfo($cedula) {
    $numeroLimpio = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($numeroLimpio) == 9) return ['tipo' => 'Cédula Nacional', 'icono' => 'bi-person-vcard-fill', 'color' => 'primary'];
    if (strlen($numeroLimpio) >= 10 && strlen($numeroLimpio) <= 12) return ['tipo' => 'Residencia (DIMEX)', 'icono' => 'bi-person-badge-fill', 'color' => 'success'];
    if (preg_match('/^[A-Z0-9]+$/i', $cedula) && strlen($cedula) < 20) return ['tipo' => 'Pasaporte', 'icono' => 'bi-passport-fill', 'color' => 'info'];
    return ['tipo' => 'Otro Documento', 'icono' => 'bi-question-circle-fill', 'color' => 'secondary'];
}

if (isset($_SESSION['flash_message']) && !$message) {
    $message = $_SESSION['flash_message']['message'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

$departamentos = $conn->query("SELECT d.idDepartamento, d.nombre FROM departamento d JOIN estado_cat e ON d.id_estado_fk = e.idEstado WHERE e.Descripcion = 'Activo'");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Personas - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .card-main { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .table-header { background-color: #4e73df; color: #fff; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .btn-action { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; }
        .status-badge { font-size: 0.8rem; padding: 0.5em 0.9em; font-weight: 600; }
        .id-indicator i { font-size: 1.1rem; vertical-align: middle; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0" style="font-weight: 600;">Gestión de Personas</h2>
            <a href="form_persona.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i> Agregar Persona</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card card-main">
            <div class="card-body p-4">
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Buscar por nombre, apellido o ID...">
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-building"></i></span>
                            <select id="departmentFilter" class="form-select border-0 bg-light">
                                <option value="">Todos los departamentos</option>
                                <?php mysqli_data_seek($departamentos, 0); while ($row_depto = $departamentos->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($row_depto['nombre']); ?>"><?= htmlspecialchars($row_depto['nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="personasTable">
                        <thead class="table-header">
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Identificación</th>
                                <th>Departamento</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sql = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Apellido2, p.Cedula, d.nombre AS Departamento, c.activo,
                                        CASE WHEN c.activo = 0 THEN 'Inactivo' WHEN c.fecha_ingreso > CURDATE() THEN 'Pendiente' ELSE 'Activo' END AS EstadoCalculado
                                    FROM persona p
                                    LEFT JOIN colaborador c ON p.idPersona = c.id_persona_fk
                                    LEFT JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                                    ORDER BY p.Nombre, p.Apellido1";
                            $result_personas = $conn->query($sql);
                            if ($result_personas->num_rows > 0): 
                                while ($row = $result_personas->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['Nombre'] . " " . $row['Apellido1']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($row['Apellido2']); ?></div>
                                        </td>
                                        <td>
                                            <?php $id_info = getIdentificacionInfo($row['Cedula']); ?>
                                            <span class="id-indicator" data-bs-toggle="tooltip" title="<?= $id_info['tipo'] ?>">
                                                <i class="bi <?= $id_info['icono'] ?> text-<?= $id_info['color'] ?> me-2"></i>
                                            </span>
                                            <?= htmlspecialchars($row['Cedula']); ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['Departamento'] ?? 'No asignado'); ?></td>
                                        <td class="text-center">
                                            <?php
                                            $estado = htmlspecialchars($row['EstadoCalculado']);
                                            $clase_badge = 'bg-secondary';
                                            if ($estado == 'Activo') $clase_badge = 'bg-success';
                                            elseif ($estado == 'Inactivo') $clase_badge = 'bg-danger';
                                            elseif ($estado == 'Pendiente') $clase_badge = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge rounded-pill <?= $clase_badge; ?> status-badge"><?= $estado; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <a href="form_persona.php?id=<?= $row['idPersona']; ?>" class="btn btn-light btn-sm btn-action" title="Editar"><i class="bi bi-pencil-square text-primary"></i></a>
                                            <a href="personas.php?toggle_id=<?= $row['idPersona']; ?>" class="btn btn-light btn-sm btn-action" title="<?= $row['activo'] ? 'Desactivar' : 'Activar'; ?>"><i class="bi <?= $row['activo'] ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted'; ?>" style="font-size: 1.2rem;"></i></a>
                                            <button type="button" class="btn btn-light btn-sm btn-action" title="Eliminar Permanentemente" onclick="confirmDelete(<?= $row['idPersona']; ?>, '<?= htmlspecialchars($row['Nombre'] . " " . $row['Apellido1']); ?>')"><i class="bi bi-trash text-danger"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center p-5 text-muted">No se encontraron personas registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                     <div id="no-results-message" class="text-center p-5 text-muted" style="display: none;">
                        <h5><i class="bi bi-search"></i> No se encontraron resultados</h5>
                        <p>Intenta ajustar tus filtros de búsqueda.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación Permanente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p id="deleteModalText"></p></div>
                <div class="modal-footer">
                    <form id="deleteForm" method="POST" action="personas.php">
                        <input type="hidden" name="delete_id" id="delete_id_input">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Permanentemente</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el) });

            const searchInput = document.getElementById('searchInput');
            const departmentFilter = document.getElementById('departmentFilter');
            const tableBody = document.querySelector("#personasTable tbody");
            const rows = tableBody.querySelectorAll('tr');
            const noResultsMessage = document.getElementById('no-results-message');

            function filterTable() {
                const searchText = searchInput.value.toLowerCase();
                const departmentText = departmentFilter.value.toLowerCase();
                let visibleRows = 0;
                rows.forEach(row => {
                    const name = row.cells[0].textContent.toLowerCase();
                    const id = row.cells[1].textContent.toLowerCase();
                    const department = row.cells[2].textContent.toLowerCase();
                    const matchesSearch = name.includes(searchText) || id.includes(searchText);
                    const matchesDepartment = departmentText === "" || department.includes(departmentText);
                    if (matchesSearch && matchesDepartment) {
                        row.style.display = "";
                        visibleRows++;
                    } else {
                        row.style.display = "none";
                    }
                });
                noResultsMessage.style.display = visibleRows === 0 ? "block" : "none";
            }
            
            searchInput.addEventListener('keyup', filterTable);
            departmentFilter.addEventListener('change', filterTable);
        });

        function confirmDelete(id, nombre) {
            document.getElementById('delete_id_input').value = id;
            document.getElementById('deleteModalText').innerHTML = `¿Estás seguro de que deseas eliminar a <b>${nombre}</b>? <br><span class='text-danger fw-bold'>Esta acción es irreversible y eliminará todos sus datos.</span>`;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>