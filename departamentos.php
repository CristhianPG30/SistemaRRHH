<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['rol'] != 1 && $_SESSION['rol'] != 2)) {
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
        $stmt->execute();
        $stmt->close();
        $msg = "¡Departamento agregado!";
    } else {
        $msg = "Todos los campos son obligatorios.";
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
            $msg_type = "success";
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

// Eliminar departamento (opcional)
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("DELETE FROM departamento WHERE idDepartamento=$del_id");
    $msg = "Departamento eliminado.";
    $msg_type = "success";
}

// Obtener estados
$estados = [];
$res = $conn->query("SELECT idEstado, Descripcion FROM estado_cat");
while ($row = $res->fetch_assoc()) $estados[] = $row;

// Obtener departamentos
$departamentos = [];
$res = $conn->query("SELECT d.*, e.Descripcion as estado FROM departamento d INNER JOIN estado_cat e ON d.id_estado_fk = e.idEstado ORDER BY d.nombre ASC");
while ($row = $res->fetch_assoc()) $departamentos[] = $row;
?>

<?php include 'header.php'; ?>

<style>
.page-center {
    min-height: 94vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card-depto {
    width: 100%;
    max-width: 920px;
    border-radius: 1.7rem;
    box-shadow: 0 6px 32px #13c6f135;
    background: #fff;
    padding: 2.3rem 2.2rem 2.2rem 2.2rem;
}
@media (max-width: 1000px) {
    .card-depto { padding: 1.3rem .5rem; }
}
.table-depto th, .table-depto td {
    vertical-align: middle;
    text-align: center;
}
.btn-glow {
    box-shadow: 0 2px 10px #18c0ff35;
    transition: box-shadow .14s;
}
.btn-glow:hover {
    box-shadow: 0 6px 28px #18c0ff65;
}
.card-title-strong {
    font-weight: 900; font-size: 2.1rem; color: #149edb; letter-spacing: .5px;
}
</style>

<div class="page-center">
    <div class="card card-depto animate__animated animate__fadeIn">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="card-title-strong"><i class="bi bi-diagram-3"></i> Departamentos</span>
            <button class="btn btn-primary btn-glow" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle"></i> Nuevo</button>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive rounded">
            <table class="table table-depto table-hover align-middle">
                <thead class="table-info">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th style="min-width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($departamentos as $dep): ?>
                    <tr>
                        <td><?= $dep['idDepartamento'] ?></td>
                        <td><?= htmlspecialchars($dep['nombre']) ?></td>
                        <td><?= htmlspecialchars($dep['descripcion']) ?></td>
                        <td>
                            <span class="badge <?= ($dep['id_estado_fk']==1 ? 'bg-success' : 'bg-secondary') ?>">
                                <?= htmlspecialchars($dep['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <button 
                                class="btn btn-warning btn-sm btn-glow"
                                title="Editar"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal"
                                onclick='cargarDatosEditar(<?= json_encode($dep, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            ><i class="bi bi-pencil-square"></i></button>
                            <a href="?delete=<?= $dep['idDepartamento'] ?>"
                                onclick="return confirm('¿Eliminar departamento?');"
                                class="btn btn-danger btn-sm btn-glow" title="Eliminar"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white"><i class="bi bi-plus-circle"></i> Nuevo Departamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="add" value="1">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descripción</label>
          <input type="text" name="descripcion" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Estado</label>
          <select name="id_estado_fk" class="form-select" required>
            <?php foreach ($estados as $est): ?>
              <option value="<?= $est['idEstado'] ?>"><?= htmlspecialchars($est['Descripcion']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Agregar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar (solo 1 en toda la página, se llena con JS) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" id="formEditarDepto">
      <div class="modal-header bg-info">
        <h5 class="modal-title text-white"><i class="bi bi-pencil-square"></i> Editar Departamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descripción</label>
          <input type="text" name="descripcion" id="edit_descripcion" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Estado</label>
          <select name="id_estado_fk" id="edit_estado" class="form-select" required>
            <?php foreach ($estados as $est): ?>
              <option value="<?= $est['idEstado'] ?>"><?= htmlspecialchars($est['Descripcion']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar Cambios</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
<!-- Bootstrap y animate.css para animaciones -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Función para cargar datos en el modal de edición
function cargarDatosEditar(dep) {
    document.getElementById('edit_id').value = dep.idDepartamento;
    document.getElementById('edit_nombre').value = dep.nombre;
    document.getElementById('edit_descripcion').value = dep.descripcion;
    document.getElementById('edit_estado').value = dep.id_estado_fk;
}
</script>
