<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Usaremos un array para evitar colisión con variables del header.php
$usuario_a_editar = [
    'idUsuario' => '',
    'username' => '',
    'id_rol_fk' => '',
    'id_persona_fk' => '',
    'persona_nombre' => ''
];
$is_edit_mode = false;
$page_title = 'Agregar Nuevo Usuario';

// --- LÓGICA DE EDICIÓN (CARGAR DATOS) ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit_mode = true;
    $page_title = 'Editar Usuario';
    $idUsuario_a_editar = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT u.idUsuario, u.username, u.id_rol_fk, u.id_persona_fk, CONCAT(p.Nombre, ' ', p.Apellido1) AS persona_nombre 
                            FROM usuario u 
                            JOIN persona p ON u.id_persona_fk = p.idPersona 
                            WHERE u.idUsuario = ?");
    $stmt->bind_param("i", $idUsuario_a_editar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
        $usuario_a_editar['idUsuario'] = $data['idUsuario'];
        $usuario_a_editar['username'] = $data['username'];
        $usuario_a_editar['id_rol_fk'] = (int)$data['id_rol_fk'];
        $usuario_a_editar['id_persona_fk'] = $data['id_persona_fk'];
        $usuario_a_editar['persona_nombre'] = $data['persona_nombre'];
    } else {
        // Redirigir si el usuario no existe
        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Usuario no encontrado.'];
        header("Location: usuarios.php");
        exit;
    }
    $stmt->close();
}

// --- LÓGICA PARA PROCESAR EL FORMULARIO (GUARDAR DATOS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (Esta sección no cambia, ya estaba bien)
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { die('Error de validación de seguridad.'); }
    $idUsuario_post = intval($_POST['idUsuario']);
    $username_post = trim($_POST['username']);
    $password_post = trim($_POST['password']);
    $id_rol_fk_post = intval($_POST['id_rol_fk']);
    $id_persona_fk_post = intval($_POST['id_persona_fk']);
    $is_edit_mode_post = ($idUsuario_post > 0);

    $errors = [];
    if (empty($username_post) || empty($id_rol_fk_post) || (!$is_edit_mode_post && empty($id_persona_fk_post))) { $errors[] = "Por favor, completa todos los campos obligatorios."; }
    elseif (!$is_edit_mode_post && empty($password_post)) { $errors[] = "La contraseña es obligatoria para nuevos usuarios."; }
    
    if (empty($errors)) {
        if (!$is_edit_mode_post) {
            $hashed_password = password_hash($password_post, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuario (username, password, id_rol_fk, id_persona_fk) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $username_post, $hashed_password, $id_rol_fk_post, $id_persona_fk_post);
        } else {
            if (!empty($password_post)) {
                $hashed_password = password_hash($password_post, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuario SET username = ?, password = ?, id_rol_fk = ? WHERE idUsuario = ?");
                $stmt->bind_param("ssii", $username_post, $hashed_password, $id_rol_fk_post, $idUsuario_post);
            } else {
                $stmt = $conn->prepare("UPDATE usuario SET username = ?, id_rol_fk = ? WHERE idUsuario = ?");
                $stmt->bind_param("sii", $username_post, $id_rol_fk_post, $idUsuario_post);
            }
        }
        if ($stmt->execute()) { $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Operación de usuario realizada con éxito.']; header("Location: usuarios.php"); exit;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error al guardar: El nombre de usuario ya existe o la persona ya tiene una cuenta.']; }
        $stmt->close();
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
    }
    header("Location: form_usuario.php" . ($is_edit_mode_post ? "?id=$idUsuario_post" : ""));
    exit;
}

$roles = $conn->query("SELECT idIdRol, descripcion FROM idrol ORDER BY descripcion");
$personas_sin_usuario = $conn->query("SELECT idPersona, CONCAT(Nombre, ' ', Apellido1) AS nombre_completo FROM persona WHERE idPersona NOT IN (SELECT id_persona_fk FROM usuario)");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .container { max-width: 700px; margin-top: 3rem; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .card-header { background-color: #4e73df; color: #ffffff; text-align: center; border-top-left-radius: 1rem; border-top-right-radius: 1rem; padding: 1.5rem; }
        .form-label.required::after { content: " *"; color: red; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="card">
            <div class="card-header"><h3 class="mb-0"><?= htmlspecialchars($page_title); ?></h3></div>
            <div class="card-body p-4 p-md-5">
                <?php
                if (isset($_SESSION['flash_message'])) {
                    echo "<div class='alert alert-{$_SESSION['flash_message']['type']}'>{$_SESSION['flash_message']['message']}</div>";
                    unset($_SESSION['flash_message']);
                }
                ?>
                <form method="POST" action="form_usuario.php<?= $is_edit_mode ? '?id=' . $usuario_a_editar['idUsuario'] : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="idUsuario" value="<?= $usuario_a_editar['idUsuario']; ?>">
                    
                    <div class="mb-3"><label for="username" class="form-label required">Nombre de Usuario</label><div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control" name="username" value="<?= htmlspecialchars($usuario_a_editar['username']); ?>" required></div></div>
                    <div class="mb-3"><label for="password" class="form-label <?= !$is_edit_mode ? 'required' : ''; ?>"><?= $is_edit_mode ? 'Nueva Contraseña' : 'Contraseña'; ?></label><div class="input-group"><span class="input-group-text"><i class="bi bi-key"></i></span><input type="password" class="form-control" name="password" id="password" <?= !$is_edit_mode ? 'required' : ''; ?>><button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button></div><small class="form-text text-muted"><?= $is_edit_mode ? 'Dejar en blanco para no cambiar.' : 'Establezca una contraseña segura.'; ?></small></div>
                    <div class="mb-3"><label for="id_rol_fk" class="form-label required">Rol</label><select class="form-select" name="id_rol_fk" required><option value="">Seleccione un rol</option><?php mysqli_data_seek($roles, 0); while ($rol = $roles->fetch_assoc()): ?><option value="<?= $rol['idIdRol']; ?>" <?= ($usuario_a_editar['id_rol_fk'] == (int)$rol['idIdRol']) ? 'selected' : ''; ?>><?= htmlspecialchars($rol['descripcion']); ?></option><?php endwhile; ?></select></div>
                    <div class="mb-4"><label for="id_persona_fk" class="form-label required">Persona a Asociar</label><?php if ($is_edit_mode): ?><input type="text" class="form-control" value="<?= htmlspecialchars($usuario_a_editar['persona_nombre']); ?>" disabled readonly><input type="hidden" name="id_persona_fk" value="<?= $usuario_a_editar['id_persona_fk']; ?>"><small class="form-text text-muted">La persona asociada a un usuario no puede ser cambiada.</small><?php else: ?><select class="form-select" name="id_persona_fk" required><option value="">Seleccione una persona sin usuario</option><?php mysqli_data_seek($personas_sin_usuario, 0); while ($persona = $personas_sin_usuario->fetch_assoc()): ?><option value="<?= $persona['idPersona']; ?>"><?= htmlspecialchars($persona['nombre_completo']); ?></option><?php endwhile; ?></select><?php endif; ?></div>
                    <div class="d-flex justify-content-between"><a href="usuarios.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i>Cancelar</a><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $is_edit_mode ? 'Actualizar Usuario' : 'Guardar Usuario'; ?></button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>