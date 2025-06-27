<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Verificar si el usuario está autenticado y tiene el rol de administrador
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// --- LÓGICA DE GESTIÓN (POST REQUESTS) ---

// Manejar la eliminación de un usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $idUsuario = intval($_POST['delete_user_id']);

    if ($idUsuario > 0) {
        $stmt = $conn->prepare("DELETE FROM usuario WHERE idUsuario = ?");
        $stmt->bind_param("i", $idUsuario);
        if ($stmt->execute()) {
            $message = 'Usuario eliminado con éxito.';
            $message_type = 'success';
        } else {
            $message = 'Error al eliminar el usuario.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// Manejar la creación o edición de un usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $idUsuario = intval($_POST['idUsuario']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $id_rol_fk = intval($_POST['id_rol_fk']);
    $id_persona_fk = intval($_POST['id_persona_fk']);

    if (empty($username) || empty($id_rol_fk) || empty($id_persona_fk)) {
        $message = "Por favor, complete todos los campos requeridos.";
        $message_type = 'danger';
    } else {
        if (empty($idUsuario)) { // Crear nuevo usuario
            if (empty($password)) {
                 $message = "La contraseña es requerida para un nuevo usuario.";
                 $message_type = 'danger';
            } else {
                $stmt_check = $conn->prepare("SELECT idUsuario FROM usuario WHERE username = ? OR id_persona_fk = ?");
                $stmt_check->bind_param("si", $username, $id_persona_fk);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $message = 'El nombre de usuario o la persona seleccionada ya tienen una cuenta asignada.';
                    $message_type = 'warning';
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO usuario (username, password, id_rol_fk, id_persona_fk) VALUES (?, ?, ?, ?)");
                    $stmt_insert->bind_param("ssii", $username, $password, $id_rol_fk, $id_persona_fk);
                    if ($stmt_insert->execute()) {
                        $message = 'Usuario agregado con éxito.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error al agregar el usuario.';
                        $message_type = 'danger';
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
        } else { // Actualizar usuario existente
            if (!empty($password)) {
                $stmt_update = $conn->prepare("UPDATE usuario SET username = ?, password = ?, id_rol_fk = ?, id_persona_fk = ? WHERE idUsuario = ?");
                $stmt_update->bind_param("ssiii", $username, $password, $id_rol_fk, $id_persona_fk, $idUsuario);
            } else {
                $stmt_update = $conn->prepare("UPDATE usuario SET username = ?, id_rol_fk = ?, id_persona_fk = ? WHERE idUsuario = ?");
                $stmt_update->bind_param("siii", $username, $id_rol_fk, $id_persona_fk, $idUsuario);
            }

            if ($stmt_update->execute()) {
                $message = 'Usuario actualizado con éxito.';
                $message_type = 'success';
            } else {
                $message = 'Error al actualizar el usuario.';
                $message_type = 'danger';
            }
            $stmt_update->close();
        }
    }
}

// --- OBTENCIÓN DE DATOS PARA LA VISTA ---
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// --- INICIO DE LA CORRECCIÓN FINAL ---
// Consulta para obtener la lista de usuarios (con el nombre de columna corregido)
$sql_users = "SELECT u.idUsuario, u.username, u.password, r.idIdRol, r.descripcion AS rol, p.idPersona, CONCAT(p.Nombre, ' ', p.Apellido1) AS persona_nombre
              FROM usuario u
              JOIN idrol r ON u.id_rol_fk = r.idIdRol
              JOIN persona p ON u.id_persona_fk = p.idPersona
              WHERE u.username LIKE ? OR p.Nombre LIKE ? OR p.Apellido1 LIKE ?";
// --- FIN DE LA CORRECCIÓN FINAL ---

$like_term = "%" . $search_term . "%";
$stmt_users = $conn->prepare($sql_users);
$stmt_users->bind_param("sss", $like_term, $like_term, $like_term);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

$roles = $conn->query("SELECT * FROM idrol ORDER BY descripcion");
// Personas que aún no tienen un usuario asignado
$personas_sin_usuario = $conn->query("SELECT idPersona, CONCAT(Nombre, ' ', Apellido1) AS nombre_completo FROM persona WHERE idPersona NOT IN (SELECT id_persona_fk FROM usuario)");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f4f6f9; }
        .card-main { border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead th { background-color: #343a40; color: #fff; vertical-align: middle; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .btn-action { width: 38px; height: 38px; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0" style="font-weight: 700;">Gestión de Usuarios</h2>
            <button type="button" class="btn btn-primary" onclick="openUserModal()">
                <i class="bi bi-person-plus-fill me-2"></i> Agregar Usuario
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-main">
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre de usuario o persona..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Persona Asociada</th>
                                <th>Rol</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_users->num_rows > 0): ?>
                                <?php while ($row = $result_users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['persona_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($row['rol']); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-action" title="Editar" onclick='openUserModal(<?php echo json_encode($row); ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-action" title="Eliminar" onclick="confirmDelete(<?php echo $row['idUsuario']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No se encontraron usuarios.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Usuario -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="userForm" method="POST" action="usuarios.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalLabel">Agregar Usuario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="save_user" value="1">
                        <input type="hidden" name="idUsuario" id="idUsuario">

                        <div class="mb-3">
                            <label for="username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted" id="passwordHelp">Dejar en blanco para no cambiar la contraseña existente.</small>
                        </div>
                        <div class="mb-3">
                            <label for="id_rol_fk" class="form-label">Rol</label>
                            <select class="form-select" id="id_rol_fk" name="id_rol_fk" required>
                                <option value="">Seleccione un rol...</option>
                                <?php mysqli_data_seek($roles, 0); while ($rol = $roles->fetch_assoc()): ?>
                                    <option value="<?php echo $rol['idIdRol']; ?>"><?php echo htmlspecialchars($rol['descripcion']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="id_persona_fk" class="form-label">Persona Asociada</label>
                            <select class="form-select" id="id_persona_fk" name="id_persona_fk" required>
                                <option value="">Seleccione una persona...</option>
                                <?php mysqli_data_seek($personas_sin_usuario, 0); while ($persona = $personas_sin_usuario->fetch_assoc()): ?>
                                    <option value="<?php echo $persona['idPersona']; ?>"><?php echo htmlspecialchars($persona['nombre_completo']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar este usuario?</p>
                </div>
                <div class="modal-footer">
                    <form id="deleteForm" method="POST" action="usuarios.php">
                        <input type="hidden" name="delete_user_id" id="delete_user_id_input">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const userModal = new bootstrap.Modal(document.getElementById('userModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        function openUserModal(userData = null) {
            const form = document.getElementById('userForm');
            form.reset();
            
            const passwordHelp = document.getElementById('passwordHelp');
            const personaSelect = document.getElementById('id_persona_fk');
            const idRolSelect = document.getElementById('id_rol_fk');

            if (userData) {
                // Modo Edición
                document.getElementById('userModalLabel').textContent = 'Editar Usuario';
                document.getElementById('idUsuario').value = userData.idUsuario;
                document.getElementById('username').value = userData.username;
                idRolSelect.value = userData.idIdRol;
                
                const personaActualOption = `<option value="${userData.idPersona}" selected>${userData.persona_nombre}</option>`;
                personaSelect.innerHTML += personaActualOption;
                personaSelect.value = userData.idPersona;
                
                passwordHelp.style.display = 'block';
                document.getElementById('password').required = false;

            } else {
                // Modo Creación
                document.getElementById('userModalLabel').textContent = 'Agregar Usuario';
                document.getElementById('idUsuario').value = '';
                
                const originalOptions = <?php 
                    $options = [];
                    mysqli_data_seek($personas_sin_usuario, 0);
                    while ($p = $personas_sin_usuario->fetch_assoc()) {
                        $options[] = $p;
                    }
                    echo json_encode($options);
                ?>;
                personaSelect.innerHTML = '<option value="">Seleccione una persona...</option>';
                originalOptions.forEach(p => {
                    personaSelect.innerHTML += `<option value="${p.idPersona}">${p.nombre_completo}</option>`;
                });

                passwordHelp.style.display = 'none';
                document.getElementById('password').required = true;
            }
            userModal.show();
        }

        function confirmDelete(id) {
            document.getElementById('delete_user_id_input').value = id;
            deleteModal.show();
        }
    </script>
</body>
</html>
