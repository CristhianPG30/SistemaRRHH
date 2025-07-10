<?php
session_start();
// --- CORRECCIÓN: Permitir acceso solo a Administrador (rol 1) ---
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$msg = "";
$msg_type = "success";

// --- Lógica de Gestión de Roles (POST) ---

// Agregar o Editar Rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    $id = intval($_POST['idIdRol']);
    $descripcion = trim($_POST['descripcion']);

    if (!empty($descripcion)) {
        if ($id > 0) { // Editar
            $stmt = $conn->prepare("UPDATE idrol SET descripcion = ? WHERE idIdRol = ?");
            $stmt->bind_param("si", $descripcion, $id);
            $msg = "¡Rol actualizado con éxito!";
        } else { // Agregar
            $stmt = $conn->prepare("INSERT INTO idrol (descripcion) VALUES (?)");
            $stmt->bind_param("s", $descripcion);
            $msg = "¡Rol agregado con éxito!";
        }

        if (!$stmt->execute()) {
            $msg = "Error al guardar el rol. Es posible que la descripción ya exista.";
            $msg_type = "danger";
        }
        $stmt->close();
    } else {
        $msg = "La descripción del rol no puede estar vacía.";
        $msg_type = "danger";
    }
}

// Eliminar Rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    // Primero, verificar si el rol está en uso
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE id_rol_fk = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        $msg = "Error: No se puede eliminar el rol porque está asignado a uno o más usuarios.";
        $msg_type = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM idrol WHERE idIdRol = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Rol eliminado con éxito.";
        } else {
            $msg = "Error al eliminar el rol.";
            $msg_type = "danger";
        }
        $stmt->close();
    }
}

// Obtener todos los roles para mostrar en la tabla
$roles = $conn->query("SELECT idIdRol, descripcion FROM idrol ORDER BY descripcion ASC")->fetch_all(MYSQLI_ASSOC);
?>

<?php include 'header.php'; ?>
<style>
    .main-container { margin-left: 280px; padding: 2.5rem; }
    .card-main {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
        background: #fff;
    }
    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }
    .card-title-custom { font-weight: 600; font-size: 1.5rem; color: #32325d; }
    .table th { font-weight: 600; }
    .table td, .table th { vertical-align: middle; }
    .action-btn { width: 38px; height: 38px; }
</style>

<div class="main-container">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Gestión de Roles</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="prepareModal()"><i class="bi bi-plus-circle me-2"></i> Nuevo Rol</button>
        </div>
        <div class="card-body p-4">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Descripción del Rol</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($roles)): ?>
                        <tr><td colspan="3" class="text-center text-muted p-4">No hay roles definidos.</td></tr>
                    <?php else: foreach ($roles as $rol): ?>
                        <tr>
                            <td><?= $rol['idIdRol'] ?></td>
                            <td><strong><?= htmlspecialchars($rol['descripcion']) ?></strong></td>
                            <td class="text-center">
                                <button class="btn btn-light btn-sm action-btn" title="Editar" data-bs-toggle="modal" data-bs-target="#roleModal" onclick='prepareModal(<?= json_encode($rol) ?>)'><i class="bi bi-pencil-square text-primary"></i></button>
                                <button class="btn btn-light btn-sm action-btn" title="Eliminar" onclick="confirmDelete(<?= $rol['idIdRol'] ?>)"><i class="bi bi-trash-fill text-danger"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roleModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="roleModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" name="save_role" value="1">
        <input type="hidden" name="idIdRol" id="idIdRol">
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción del Rol</label>
            <input type="text" name="descripcion" id="descripcion" class="form-control" required>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form></div></div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">¿Seguro que deseas eliminar este rol?</div>
    <div class="modal-footer">
        <form id="deleteForm" method="post"><input type="hidden" name="delete_id" id="delete_id_input"></form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit();">Sí, Eliminar</button>
    </div>
</div></div></div>

<?php include 'footer.php'; ?>
<script>
const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

function prepareModal(rol = null) {
    const form = document.getElementById('roleModal').querySelector('form');
    form.reset();
    const label = document.getElementById('roleModalLabel');
    if (rol) {
        label.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Rol';
        document.getElementById('idIdRol').value = rol.idIdRol;
        document.getElementById('descripcion').value = rol.descripcion;
    } else {
        label.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuevo Rol';
        document.getElementById('idIdRol').value = '';
    }
}

function confirmDelete(id) {
    document.getElementById('delete_id_input').value = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
}
</script>
</body>
</html>