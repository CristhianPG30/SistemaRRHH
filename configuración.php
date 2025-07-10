<?php
session_start();
include 'db.php';

// Verificar si el usuario está autenticado y tiene el rol de administrador
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = 'success';
$configFilePath = 'js/configuracion.json';

// --- LÓGICA DE GESTIÓN (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manejo de Configuración General
    if (isset($_POST['update_config'])) {
        $configData = [
            'nombre_empresa' => $_POST['nombre_empresa'],
            'tarifa_hora_extra' => floatval($_POST['tarifa_hora_extra'])
        ];
        if (file_put_contents($configFilePath, json_encode($configData, JSON_PRETTY_PRINT))) {
            $message = 'Configuración del sistema actualizada con éxito.';
        } else {
            $message = 'Error al guardar la configuración.';
            $message_type = 'danger';
        }
    }
    
    // Manejo de Deducciones
    if (isset($_POST['save_deduction'])) {
        $id = intval($_POST['idTipoDeduccion']);
        $nombre_deduccion = trim($_POST['nombre_deduccion']);
        $porcentaje = floatval($_POST['porcentaje']);
        if (empty($nombre_deduccion) || $porcentaje < 0) {
            $message = 'El nombre y un porcentaje válido son requeridos.';
            $message_type = 'danger';
        } else {
            $descripcion_db = $nombre_deduccion . ':' . $porcentaje;
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tipo_deduccion_cat SET Descripcion = ? WHERE idTipoDeduccion = ?");
                $stmt->bind_param("si", $descripcion_db, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO tipo_deduccion_cat (Descripcion) VALUES (?)");
                $stmt->bind_param("s", $descripcion_db);
            }
            if ($stmt->execute()) {
                $message = 'La deducción ha sido guardada con éxito.';
            } else {
                $message = 'Error al guardar la deducción.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
    
    // Eliminar Deducción
    if (isset($_POST['delete_deduction_id'])) {
        $id = intval($_POST['delete_deduction_id']);
        $stmt = $conn->prepare("DELETE FROM tipo_deduccion_cat WHERE idTipoDeduccion = ?");
        $stmt->bind_param("i", $id);
        $message = $stmt->execute() ? 'Deducción eliminada con éxito.' : 'Error al eliminar la deducción.';
        $message_type = $stmt->execute() ? 'success' : 'danger';
        $stmt->close();
    }
    
    // Manejo de Roles
    if (isset($_POST['save_role'])) {
        $id = intval($_POST['idIdRol']);
        $descripcion = trim($_POST['descripcion']);
        if (!empty($descripcion)) {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE idrol SET descripcion = ? WHERE idIdRol = ?");
                $stmt->bind_param("si", $descripcion, $id);
                $message = "¡Rol actualizado con éxito!";
            } else {
                $stmt = $conn->prepare("INSERT INTO idrol (descripcion) VALUES (?)");
                $stmt->bind_param("s", $descripcion);
                $message = "¡Rol agregado con éxito!";
            }
            if (!$stmt->execute()) {
                $message = 'Error al guardar el rol.';
                $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "La descripción del rol no puede estar vacía.";
            $message_type = "danger";
        }
    }

    // Eliminar Rol
    if (isset($_POST['delete_role_id'])) {
        $id = intval($_POST['delete_role_id']);
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE id_rol_fk = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();
        
        if ($count > 0) {
            $message = "Error: No se puede eliminar el rol porque está asignado a usuarios.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM idrol WHERE idIdRol = ?");
            $stmt->bind_param("i", $id);
            $message = $stmt->execute() ? 'Rol eliminado con éxito.' : 'Error al eliminar el rol.';
            $message_type = $stmt->execute() ? 'success' : 'danger';
            $stmt->close();
        }
    }
}

// --- OBTENCIÓN DE DATOS PARA LA VISTA ---
$configData = json_decode(file_get_contents($configFilePath), true);
$deducciones_raw = $conn->query("SELECT * FROM tipo_deduccion_cat ORDER BY Descripcion");
$roles = $conn->query("SELECT idIdRol, descripcion FROM idrol ORDER BY descripcion ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-container { margin-left: 280px; padding: 2.5rem; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07); }
        .card-header { background-color: #ffffff; border-bottom: 1px solid #e9ecef; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .card-header h5 { font-weight: 600; color: #32325d; margin: 0; }
        .card-header h5 i { color: #5e72e4; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <h2 class="text-center mb-5" style="font-weight: 600;">Configuración y Mantenimientos</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Configuración General</h5></div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="update_config" value="1">
                            <div class="mb-3"><label class="form-label fw-bold">Nombre de Empresa</label><input type="text" class="form-control" name="nombre_empresa" value="<?= htmlspecialchars($configData['nombre_empresa']); ?>" required></div>
                            <div class="mb-3"><label class="form-label fw-bold">Tarifa por Hora Extra (₡)</label><input type="number" step="0.01" class="form-control" name="tarifa_hora_extra" value="<?= htmlspecialchars($configData['tarifa_hora_extra']); ?>" required></div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Guardar Configuración</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Roles del Sistema</h5><button class="btn btn-sm btn-outline-primary" onclick="openRoleModal()"><i class="bi bi-plus"></i> Nuevo Rol</button></div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($roles as $rol): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($rol['descripcion']); ?>
                                <div>
                                    <button class="btn btn-sm btn-light" onclick='openRoleModal(<?= json_encode($rol) ?>)'><i class="bi bi-pencil text-primary"></i></button>
                                    <button class="btn btn-sm btn-light" onclick="confirmDelete('role', <?= $rol['idIdRol'] ?>)"><i class="bi bi-trash text-danger"></i></button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-minus me-2"></i>Deducciones de Ley</h5><button class="btn btn-sm btn-outline-primary" onclick="openDeductionModal()"><i class="bi bi-plus"></i> Nueva Deducción</button></div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                            <?php mysqli_data_seek($deducciones_raw, 0); while ($deduccion = $deducciones_raw->fetch_assoc()): 
                                $parts = explode(':', $deduccion['Descripcion']);
                                $nombre_ded = htmlspecialchars($parts[0]);
                                $porcentaje_ded = isset($parts[1]) ? htmlspecialchars($parts[1]) : '0.00';
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div><?= $nombre_ded; ?></div>
                                <div>
                                    <span class="badge bg-light text-dark border me-2"><?= $porcentaje_ded; ?>%</span>
                                    <button class="btn btn-sm btn-light" onclick='openDeductionModal(<?= json_encode($deduccion); ?>)'><i class="bi bi-pencil text-primary"></i></button>
                                    <button class="btn btn-sm btn-light" onclick="confirmDelete('deduction', <?= $deduccion['idTipoDeduccion']; ?>)"><i class="bi bi-trash text-danger"></i></button>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="deductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><div class="modal-header"><h5 class="modal-title" id="deductionModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="save_deduction" value="1"><input type="hidden" name="idTipoDeduccion" id="idTipoDeduccion"><div class="mb-3"><label class="form-label">Nombre de Deducción</label><input type="text" class="form-control" name="nombre_deduccion" id="nombre_deduccion" required></div><div class="mb-3"><label class="form-label">Porcentaje (%)</label><input type="number" step="0.01" class="form-control" name="porcentaje" id="porcentaje" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div>
    <div class="modal fade" id="roleModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><div class="modal-header"><h5 class="modal-title" id="roleModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="save_role" value="1"><input type="hidden" name="idIdRol" id="idIdRol"><div class="mb-3"><label class="form-label">Descripción del Rol</label><input type="text" class="form-control" name="descripcion" id="descripcion" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div>
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">¿Seguro que deseas eliminar?</div><div class="modal-footer"><form method="POST" id="deleteForm"><input type="hidden" id="delete_id_input" name=""></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Sí, Eliminar</button></div></div></div></div>
    
    <?php include 'footer.php'; ?>
    <script>
        const deductionModal = new bootstrap.Modal(document.getElementById('deductionModal'));
        const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));
        const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));

        function openDeductionModal(data = null) {
            const form = document.getElementById('deductionModal').querySelector('form'); form.reset();
            const label = document.getElementById('deductionModalLabel');
            if (data) {
                const parts = data.Descripcion.split(':');
                label.innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Deducción';
                form.idTipoDeduccion.value = data.idTipoDeduccion;
                form.nombre_deduccion.value = parts[0];
                form.porcentaje.value = parts[1] || '0.00';
            } else {
                label.innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i>Nueva Deducción';
                form.idTipoDeduccion.value = '';
            }
            deductionModal.show();
        }

        function openRoleModal(data = null) {
            const form = document.getElementById('roleModal').querySelector('form'); form.reset();
            const label = document.getElementById('roleModalLabel');
            if (data) {
                label.innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Rol';
                form.idIdRol.value = data.idIdRol;
                form.descripcion.value = data.descripcion;
            } else {
                label.innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i>Nuevo Rol';
                form.idIdRol.value = '';
            }
            roleModal.show();
        }

        function confirmDelete(type, id) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const deleteForm = document.getElementById('deleteForm');
            const input = document.getElementById('delete_id_input');
            confirmBtn.onclick = function() {
                input.name = `delete_${type}_id`;
                input.value = id;
                deleteForm.submit();
            };
            confirmDeleteModal.show();
        }
    </script>
</body>
</html>