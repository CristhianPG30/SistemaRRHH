<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Solo administrador puede acceder
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

// --- LÓGICA DE ELIMINACIÓN SOLO EN TABLA usuario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $idUsuario = intval($_POST['delete_user_id']);
    if ($idUsuario > 0) {
        // Solo elimina el usuario, nada más
        $stmt = $conn->prepare("DELETE FROM usuario WHERE idUsuario = ?");
        $stmt->bind_param("i", $idUsuario);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Usuario eliminado con éxito.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error al eliminar el usuario.'];
        }
        $stmt->close();
        header("Location: usuarios.php");
        exit;
    }
}

// --- LÓGICA DE BÚSQUEDA Y VISUALIZACIÓN ---
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql_users = "SELECT u.idUsuario, u.username, r.descripcion AS rol, r.idIdRol, CONCAT(p.Nombre, ' ', p.Apellido1) AS persona_nombre
              FROM usuario u
              JOIN idrol r ON u.id_rol_fk = r.idIdRol
              JOIN persona p ON u.id_persona_fk = p.idPersona
              WHERE u.username LIKE ? OR p.Nombre LIKE ? OR p.Apellido1 LIKE ?
              ORDER BY p.Nombre";
$like_term = "%" . $search_term . "%";
$stmt_users = $conn->prepare($sql_users);
$stmt_users->bind_param("sss", $like_term, $like_term, $like_term);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

function getRoleInfo($roleName) {
    switch (strtolower($roleName)) {
        case 'administrador':
            return ['icon' => 'bi-shield-lock-fill', 'color' => 'danger'];
        case 'recursos humanos':
        case 'rrhh':
            return ['icon' => 'bi-person-gear', 'color' => 'info'];
        case 'jefatura':
            return ['icon' => 'bi-person-supervisor', 'color' => 'warning'];
        case 'colaborador':
            return ['icon' => 'bi-person', 'color' => 'primary'];
        default:
            return ['icon' => 'bi-person-badge', 'color' => 'secondary'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .card-main { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .table-header { background-color: #4e73df; color: #fff; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .btn-action { width: 38px; height: 38px; }
        .role-badge { font-size: 0.9em; font-weight: 500; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0" style="font-weight: 600;">Gestión de Usuarios</h2>
            <a href="form_usuario.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i> Agregar Usuario</a>
        </div>
        
        <?php
        if (isset($_SESSION['flash_message'])) {
            echo "<div class='alert alert-{$_SESSION['flash_message']['type']} alert-dismissible fade show' role='alert'>
                    {$_SESSION['flash_message']['message']}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
            unset($_SESSION['flash_message']);
        }
        ?>

        <div class="card card-main">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-10"><input type="text" name="search" class="form-control" placeholder="Buscar por nombre de usuario o persona..." value="<?= htmlspecialchars($search_term); ?>"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-secondary w-100">Buscar</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-header">
                            <tr><th>Usuario</th><th>Persona Asociada</th><th>Rol</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($result_users->num_rows > 0): while ($row = $result_users->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['username']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['persona_nombre']); ?></td>
                                    <td>
                                        <?php $roleInfo = getRoleInfo($row['rol']); ?>
                                        <span class="badge role-badge text-bg-<?= $roleInfo['color'] ?>">
                                            <i class="bi <?= $roleInfo['icon'] ?> me-1"></i>
                                            <?= htmlspecialchars($row['rol']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="form_usuario.php?id=<?= $row['idUsuario']; ?>" class="btn btn-light btn-sm btn-action" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square text-primary"></i></a>
                                        <button type="button" class="btn btn-light btn-sm btn-action" data-bs-toggle="tooltip" title="Eliminar" onclick="confirmDelete(<?= $row['idUsuario']; ?>)"><i class="bi bi-trash text-danger"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted p-4">No se encontraron usuarios.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Estás seguro de que deseas eliminar este usuario? Esta acción es irreversible.</p></div><div class="modal-footer"><form id="deleteForm" method="POST" action="usuarios.php"><input type="hidden" name="delete_user_id" id="delete_user_id_input"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Eliminar</button></form></div></div></div></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });
        function confirmDelete(id) {
            document.getElementById('delete_user_id_input').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
