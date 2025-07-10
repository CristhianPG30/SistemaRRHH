<?php
session_start();
// --- CORRECCIÓN: Permitir acceso a Administrador (1) y Recursos Humanos (4) ---
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Mensaje de feedback
$msg = "";
$msg_type = "success";

// Agregar departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_estado_fk = intval($_POST['id_estado_fk']);
    if ($nombre && $id_estado_fk) {
        $stmt = $conn->prepare("INSERT INTO departamento (nombre, descripcion, id_estado_fk) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $descripcion, $id_estado_fk);
        if($stmt->execute()){
             $msg = "¡Departamento agregado!";
        } else {
            $msg = "Error al agregar el departamento.";
            $msg_type = "danger";
        }
        $stmt->close();
    } else {
        $msg = "El nombre y el estado son campos obligatorios.";
        $msg_type = "danger";
    }
}

// Editar departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_estado_fk = intval($_POST['id_estado_fk']);
    if ($nombre && $id_estado_fk && $edit_id) {
        $stmt = $conn->prepare("UPDATE departamento SET nombre=?, descripcion=?, id_estado_fk=? WHERE idDepartamento=?");
        $stmt->bind_param("ssii", $nombre, $descripcion, $id_estado_fk, $edit_id);
        if ($stmt->execute()) {
            $msg = "¡Departamento actualizado!";
        } else {
            $msg = "Error al actualizar.";
            $msg_type = "danger";
        }
        $stmt->close();
    } else {
        $msg = "Todos los campos son obligatorios.";
        $msg_type = "danger";
    }
}

// Eliminar departamento (usando POST para más seguridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM departamento WHERE idDepartamento = ?");
    $stmt->bind_param("i", $del_id);
    if($stmt->execute()){
        $msg = "Departamento eliminado.";
    } else {
        $msg = "Error al eliminar. El departamento puede estar en uso.";
        $msg_type = "danger";
    }
    $stmt->close();
}

// Obtener estados
$estados = $conn->query("SELECT idEstado, Descripcion FROM estado_cat")->fetch_all(MYSQLI_ASSOC);

// Obtener departamentos
$departamentos = $conn->query("SELECT d.*, e.Descripcion as estado FROM departamento d INNER JOIN estado_cat e ON d.id_estado_fk = e.idEstado ORDER BY d.nombre ASC")->fetch_all(MYSQLI_ASSOC);
?>

<?php include 'header.php'; ?>
<style>
    .main-container {
        margin-left: 280px; /* Ajustar al ancho del sidebar */
        padding: 2.5rem;
    }
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
    .card-title-custom {
        font-weight: 600;
        font-size: 1.5rem;
        color: #32325d;
    }
    .table th {
        font-weight: 600;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    .action-btn {
        width: 38px;
        height: 38px;
    }
</style>

<div class="main-container">
    <div class="card card-main">
        <div class="card-header-custom">
            <h4 class="card-title-custom mb-0"><i class="bi bi-building me-2"></i>Gestión de Departamentos</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle me-2"></i> Nuevo Departamento</button>
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
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($departamentos as $dep): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dep['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($dep['descripcion']) ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill <?= ($dep['id_estado_fk']==1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis') ?>">
                                    <?= htmlspecialchars($dep['estado']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-light btn-sm action-btn" title="Editar" data-bs-toggle="modal" data-bs-target="#editModal" onclick='cargarDatosEditar(<?= json_encode($dep) ?>)'><i class="bi bi-pencil-square text-primary"></i></button>
                                <button class="btn btn-light btn-sm action-btn" title="Eliminar" onclick="confirmDelete(<?= $dep['idDepartamento'] ?>)"><i class="bi bi-trash-fill text-danger"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Departamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="add" value="1">
        <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3" required></textarea></div>
        <div class="mb-3"><label class="form-label">Estado</label><select name="id_estado_fk" class="form-select" required><?php foreach ($estados as $est) echo "<option value='{$est['idEstado']}'>".htmlspecialchars($est['Descripcion'])."</option>"; ?></select></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button></div>
</form></div></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" id="formEditarDepto">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Departamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" id="edit_nombre" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3" required></textarea></div>
        <div class="mb-3"><label class="form-label">Estado</label><select name="id_estado_fk" id="edit_estado" class="form-select" required><?php foreach ($estados as $est) echo "<option value='{$est['idEstado']}'>".htmlspecialchars($est['Descripcion'])."</option>"; ?></select></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar Cambios</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button></div>
</form></div></div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">¿Seguro que deseas eliminar este departamento?</div>
    <div class="modal-footer"><form id="deleteForm" method="post"><input type="hidden" name="delete_id" id="delete_id_input"></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button><button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit();">Sí, Eliminar</button></div>
</div></div></div>

<?php include 'footer.php'; ?>
<script>
function cargarDatosEditar(dep) {
    document.getElementById('edit_id').value = dep.idDepartamento;
    document.getElementById('edit_nombre').value = dep.nombre;
    document.getElementById('edit_descripcion').value = dep.descripcion;
    document.getElementById('edit_estado').value = dep.id_estado_fk;
}
function confirmDelete(id) {
    document.getElementById('delete_id_input').value = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
}
</script>
</body>
</html>