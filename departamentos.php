<?php
session_start();
require_once 'db.php';

// Seguridad: Solo usuarios logueados y con rol permitido pueden acceder.
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}

// --- BLOQUE API: MANEJA LAS PETICIONES AJAX ---
// Si es una petición AJAX, el script procesa la solicitud y termina.
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $conn->begin_transaction();

    try {
        // --- Lógica para realizar acciones (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? null;
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$action || !$id) throw new Exception('Acción o ID no proporcionado.');

            $message = "";
            switch ($action) {
                case 'activate':
                    // Activa el departamento
                    $stmt_dep = $conn->prepare("UPDATE departamento SET id_estado_fk = 1 WHERE idDepartamento = ?");
                    $stmt_dep->bind_param("i", $id);
                    $stmt_dep->execute();
                    $stmt_dep->close();
                    
                    // Activa a los colaboradores de ese departamento
                    $stmt_col = $conn->prepare("UPDATE colaborador SET activo = 1 WHERE id_departamento_fk = ?");
                    $stmt_col->bind_param("i", $id);
                    $stmt_col->execute();
                    $stmt_col->close();

                    $message = "Departamento y sus colaboradores han sido activados.";
                    break;
                
                case 'deactivate':
                    // Desactiva el departamento
                    $stmt_dep = $conn->prepare("UPDATE departamento SET id_estado_fk = 2 WHERE idDepartamento = ?");
                    $stmt_dep->bind_param("i", $id);
                    $stmt_dep->execute();
                    $stmt_dep->close();

                    // Desactiva a los colaboradores de ese departamento
                    $stmt_col = $conn->prepare("UPDATE colaborador SET activo = 0 WHERE id_departamento_fk = ?");
                    $stmt_col->bind_param("i", $id);
                    $stmt_col->execute();
                    $stmt_col->close();
                    
                    $message = "Departamento y sus colaboradores han sido desactivados.";
                    break;
                
                case 'delete':
                    // Primero, verificar si el departamento tiene colaboradores asignados.
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM colaborador WHERE id_departamento_fk = ?");
                    $check_stmt->bind_param("i", $id);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($result['count'] > 0) {
                        throw new Exception("No se puede eliminar. El departamento tiene colaboradores asignados.");
                    }

                    // Si no hay colaboradores, se procede a eliminar.
                    $stmt = $conn->prepare("DELETE FROM departamento WHERE idDepartamento = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $message = "Departamento eliminado permanentemente.";
                    break;

                default:
                    throw new Exception("Acción no válida.");
            }

            $conn->commit();
            echo json_encode(['success' => $message]);
        }

        // --- Lógica para obtener detalles (GET) ---
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $idDepartamento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$idDepartamento) throw new Exception('ID de departamento no válido.');

            $response = ['departamento' => null, 'colaboradores' => []];
            
            // Detalles del departamento
            $stmt_dep = $conn->prepare("SELECT d.idDepartamento, d.nombre, d.descripcion, d.id_estado_fk, e.Descripcion as estado FROM departamento d JOIN estado_cat e ON d.id_estado_fk = e.idEstado WHERE d.idDepartamento = ?");
            $stmt_dep->bind_param("i", $idDepartamento);
            $stmt_dep->execute();
            $response['departamento'] = $stmt_dep->get_result()->fetch_assoc();
            $stmt_dep->close();

            // Colaboradores del departamento
            if ($response['departamento']) {
                $stmt_col = $conn->prepare("SELECT CONCAT(p.Nombre, ' ', p.Apellido1) as nombre_completo, c.activo FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.id_departamento_fk = ? ORDER BY p.Nombre ASC");
                $stmt_col->bind_param("i", $idDepartamento);
                $stmt_col->execute();
                $response['colaboradores'] = $stmt_col->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_col->close();
            }

            echo json_encode($response);
        }

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400); // Bad Request
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    $conn->close();
    exit; 
}


// --- LÓGICA PARA LA PÁGINA NORMAL ---
$query = "
    SELECT 
        d.idDepartamento, d.nombre, d.descripcion, d.id_estado_fk,
        e.Descripcion as estado,
        (SELECT COUNT(*) FROM colaborador c WHERE c.id_departamento_fk = d.idDepartamento AND c.activo = 1) as num_colaboradores
    FROM departamento d 
    JOIN estado_cat e ON d.id_estado_fk = e.idEstado 
    ORDER BY d.nombre ASC
";
$departamentos = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Departamentos | Edginton S.A.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-container { margin-left: 280px; padding: 2.5rem; }
        .card-main { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07); }
        .list-group-item { cursor: pointer; transition: background-color 0.2s, border-color 0.2s; }
        .list-group-item.active { background-color: #0d6efd; border-color: #0d6efd; color: white; }
        .list-group-item.active .text-muted { color: rgba(255,255,255,0.75) !important; }
        .department-details-pane { min-height: 400px; }
        .placeholder-pane { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #adb5bd; text-align: center; }
        .collaborator-list img { width: 40px; height: 40px; object-fit: cover; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="main-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>Departamentos</h2>
    </div>

    <div id="feedback-alert-container"></div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card card-main">
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="search-department" class="form-control bg-light border-0" placeholder="Buscar departamento...">
                    </div>
                    <div class="list-group" id="department-list" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($departamentos as $dep): ?>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-id="<?= $dep['idDepartamento'] ?>">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($dep['nombre']) ?></h6>
                                    <small class="text-muted"><?= $dep['num_colaboradores'] ?> Colaboradores Activos</small>
                                </div>
                                <span class="badge rounded-pill <?= ($dep['id_estado_fk']==1 ? 'text-bg-success' : 'text-bg-secondary') ?>">
                                    <?= htmlspecialchars($dep['estado']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-main department-details-pane" id="department-details">
                <div class="card-body placeholder-pane" id="details-placeholder">
                    <i class="bi bi-buildings-fill display-1 text-light"></i>
                    <h5 class="mt-3">Selecciona un departamento</h5>
                    <p class="text-center w-75">Haz clic en un departamento de la lista para ver sus detalles y realizar acciones.</p>
                </div>
                <div id="details-content" class="d-none"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmationModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn" id="confirmActionButton">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentList = document.getElementById('department-list');
    const detailsContent = document.getElementById('details-content');
    const detailsPlaceholder = document.getElementById('details-placeholder');
    const searchInput = document.getElementById('search-department');
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    let currentDepartmentId = null;

    departmentList.addEventListener('click', function(e) {
        e.preventDefault();
        const item = e.target.closest('.list-group-item');
        if (!item) return;

        document.querySelectorAll('.list-group-item.active').forEach(active => active.classList.remove('active'));
        item.classList.add('active');

        currentDepartmentId = item.dataset.id;
        loadDepartmentDetails(currentDepartmentId);
    });

    async function loadDepartmentDetails(id) {
        detailsPlaceholder.classList.add('d-none');
        detailsContent.innerHTML = `<div class="d-flex justify-content-center align-items-center h-100"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div></div>`;
        detailsContent.classList.remove('d-none');

        try {
            const response = await fetch(`departamentos.php?ajax=1&action=get_details&id=${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error(`Error de red: ${response.status} ${response.statusText}`);
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            renderDepartmentDetails(data);

        } catch (error) {
            console.error('Error al cargar detalles:', error);
            detailsContent.innerHTML = `<div class="alert alert-danger m-4"><strong>Error:</strong> ${error.message}</div>`;
        }
    }
    
    function renderDepartmentDetails(data) {
        if (!data.departamento) {
            detailsContent.innerHTML = `<div class="alert alert-warning m-4">No se encontraron detalles. Puede que haya sido eliminado.</div>`;
            return;
        }

        let collaboratorsHtml = '<div class="text-center text-muted p-3">No hay colaboradores en este departamento.</div>';
        if (data.colaboradores && data.colaboradores.length > 0) {
            collaboratorsHtml = data.colaboradores.map(col => `
                <div class="d-flex align-items-center p-2 border-bottom">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(col.nombre_completo)}&background=random&color=fff" class="rounded-circle me-3" alt="Avatar">
                    <h6 class="mb-0">${col.nombre_completo}</h6>
                    <span class="ms-auto badge rounded-pill ${col.activo == 1 ? 'text-bg-success' : 'text-bg-secondary'}">${col.activo == 1 ? 'Activo' : 'Inactivo'}</span>
                </div>
            `).join('');
        }

        const isActive = data.departamento.id_estado_fk == 1;
        const toggleButtonClass = isActive ? 'btn-warning' : 'btn-success';
        const toggleButtonIcon = isActive ? 'bi-slash-circle' : 'bi-check-circle';
        const toggleButtonText = isActive ? 'Desactivar' : 'Activar';

        detailsContent.innerHTML = `
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="fw-bold text-primary mb-1">${data.departamento.nombre}</h4>
                        <p class="text-muted">${data.departamento.descripcion || 'Sin descripción.'}</p>
                    </div>
                    <div class="d-flex flex-column align-items-end">
                        <span class="badge fs-6 rounded-pill ${isActive ? 'text-bg-success' : 'text-bg-secondary'}">${data.departamento.estado}</span>
                        <div class="btn-group mt-3">
                            <button class="btn ${toggleButtonClass} btn-sm" data-action="${isActive ? 'deactivate' : 'activate'}"><i class="bi ${toggleButtonIcon}"></i> ${toggleButtonText}</button>
                            <button class="btn btn-danger btn-sm" data-action="delete"><i class="bi bi-trash"></i> Eliminar</button>
                        </div>
                    </div>
                </div>
                <hr>
                <h5 class="fw-bold mt-4"><i class="bi bi-people-fill me-2"></i>Colaboradores (${data.colaboradores.length})</h5>
                <div class="collaborator-list mt-3" style="max-height: 450px; overflow-y: auto;">
                    ${collaboratorsHtml}
                </div>
            </div>
        `;
    }

    detailsContent.addEventListener('click', function(e) {
        const button = e.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const modalTitle = document.getElementById('confirmationModalTitle');
        const modalBody = document.getElementById('confirmationModalBody');
        const confirmButton = document.getElementById('confirmActionButton');

        if (action === 'delete') {
            modalTitle.textContent = 'Confirmar Eliminación';
            modalBody.textContent = '¿Estás seguro de que deseas eliminar este departamento? Esta acción no se puede deshacer.';
            confirmButton.className = 'btn btn-danger';
            confirmButton.onclick = () => handleDepartmentAction('delete');
        } else if (action === 'deactivate' || action === 'activate') {
            const verb = action === 'deactivate' ? 'desactivar' : 'activar';
            modalTitle.textContent = `Confirmar ${verb.charAt(0).toUpperCase() + verb.slice(1)}`;
            modalBody.textContent = `¿Estás seguro de que deseas ${verb} este departamento? Todos sus colaboradores también cambiarán de estado.`;
            confirmButton.className = action === 'deactivate' ? 'btn btn-warning' : 'btn btn-success';
            confirmButton.onclick = () => handleDepartmentAction(action);
        }
        confirmationModal.show();
    });

    async function handleDepartmentAction(action) {
        confirmationModal.hide();
        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', currentDepartmentId);

            const response = await fetch('departamentos.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Error en el servidor.');
            
            showAlert(result.success, 'success');
            setTimeout(() => window.location.reload(), 1500);

        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
    
    function showAlert(message, type = 'success') {
        const container = document.getElementById('feedback-alert-container');
        container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        document.querySelectorAll('#department-list .list-group-item').forEach(item => {
            const name = item.querySelector('h6').textContent.toLowerCase();
            item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
        });
    });
});
</script>
</body>
</html>