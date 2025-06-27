<?php
// form_usuario.php - VERSIÓN FINAL CORREGIDA Y REDISEÑADA

// Iniciar sesión y conectar a la base de datos
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict'
]);

// Código de seguridad para proteger la página
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
// Solo un administrador (rol=1) puede acceder a esta página
if ($_SESSION['rol'] != 1) {
    die("Acceso denegado. No tienes permisos para gestionar usuarios.");
}

include 'db.php';

// Generar token CSRF si no existe, para proteger el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inicializar variables
$is_edit_mode = false;
$idUsuario = '';
$username = '';
$id_rol_fk = '';
$id_persona_fk = '';
$error_message = '';
$success_message = '';

// --- Lógica para procesar el envío del formulario (cuando se da clic en Guardar/Actualizar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificar el token de seguridad CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Error de validación de seguridad. Por favor, recargue la página y vuelva a intentarlo.');
    }

    // 2. Obtener y limpiar los datos del formulario
    $idUsuario = isset($_POST['idUsuario']) ? intval($_POST['idUsuario']) : 0;
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $id_rol_fk = intval($_POST['id_rol_fk']);
    $id_persona_fk = intval($_POST['id_persona_fk']);
    $is_edit_mode = ($idUsuario > 0);

    // 3. Validaciones de negocio
    if (empty($username) || empty($id_rol_fk) || (!$is_edit_mode && empty($id_persona_fk))) {
        $error_message = "Por favor, completa todos los campos obligatorios.";
    } elseif (!$is_edit_mode && empty($password)) {
        $error_message = "La contraseña es obligatoria para nuevos usuarios.";
    } else {
        // Verificar si el nombre de usuario ya está en uso por OTRA persona
        $stmt_check = $conn->prepare("SELECT idUsuario FROM usuario WHERE username = ? AND idUsuario != ?");
        $stmt_check->bind_param("si", $username, $idUsuario);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error_message = "El nombre de usuario ya está en uso. Por favor, elija otro.";
        }
        $stmt_check->close();
    }

    // 4. Si no hay errores de validación, proceder a guardar en la BD
    if (empty($error_message)) {
        if (!$is_edit_mode) {
            // --- LÓGICA PARA CREAR NUEVO USUARIO ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Encriptar contraseña
            $stmt = $conn->prepare("INSERT INTO usuario (username, password, id_rol_fk, id_persona_fk) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $username, $hashed_password, $id_rol_fk, $id_persona_fk);
        } else {
            // --- LÓGICA PARA ACTUALIZAR USUARIO EXISTENTE ---
            if (!empty($password)) {
                // Si se proporcionó una nueva contraseña, se encripta y actualiza
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuario SET username = ?, password = ?, id_rol_fk = ? WHERE idUsuario = ?");
                $stmt->bind_param("ssii", $username, $hashed_password, $id_rol_fk, $idUsuario);
            } else {
                // Si no se cambia la contraseña, solo se actualizan los otros campos
                $stmt = $conn->prepare("UPDATE usuario SET username = ?, id_rol_fk = ? WHERE idUsuario = ?");
                $stmt->bind_param("sii", $username, $id_rol_fk, $idUsuario);
            }
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Operación de usuario realizada con éxito.";
            $_SESSION['message_type'] = "success";
            header("Location: usuarios.php"); // Redirigir a la lista de usuarios
            exit;
        } else {
            $error_message = "Error al guardar el usuario: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Lógica para mostrar el formulario (cuando se carga la página) ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit_mode = true;
    $idUsuario = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT username, id_rol_fk, id_persona_fk FROM usuario WHERE idUsuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $username = $user_data['username'];
        $id_rol_fk = $user_data['id_rol_fk'];
        $id_persona_fk = $user_data['id_persona_fk'];
    } else { $error_message = "Usuario no encontrado."; }
    $stmt->close();
}

// Cargar datos para los menús desplegables
$roles = $conn->query("SELECT idIdRol, descripcion FROM idrol");
// La consulta de personas es diferente si estamos editando o creando
$personas_query_sql = "SELECT idPersona, Nombre, Apellido1 FROM persona WHERE idPersona NOT IN (SELECT id_persona_fk FROM usuario" . ($is_edit_mode ? " WHERE idUsuario != $idUsuario" : "") . ")";
$personas_sin_usuario = $conn->query($personas_query_sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit_mode ? 'Editar Usuario' : 'Agregar Usuario'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .container { max-width: 700px; margin-top: 30px; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #343a40; color: #ffffff; text-align: center; border-top-left-radius: 15px; border-top-right-radius: 15px; padding: 1.2rem; }
        .form-label.required::after { content: " *"; color: red; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><?php echo $is_edit_mode ? 'Editar Usuario' : 'Agregar Nuevo Usuario'; ?></h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form method="POST" action="form_usuario.php<?php echo $is_edit_mode ? '?id=' . $idUsuario : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="idUsuario" value="<?php echo $idUsuario; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label required">Nombre de Usuario</label>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label <?php echo !$is_edit_mode ? 'required' : ''; ?>">
                            <?php echo $is_edit_mode ? 'Nueva Contraseña (dejar en blanco para no cambiar)' : 'Contraseña'; ?>
                        </label>
                        <input type="password" class="form-control" name="password" <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_rol_fk" class="form-label required">Rol</label>
                        <select class="form-select" name="id_rol_fk" required>
                            <option value="">Seleccione un rol</option>
                            <?php mysqli_data_seek($roles, 0); while ($rol = $roles->fetch_assoc()): ?>
                                <option value="<?php echo $rol['idIdRol']; ?>" <?php if ($id_rol_fk == $rol['idIdRol']) echo 'selected'; ?>><?php echo htmlspecialchars($rol['descripcion']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_persona_fk" class="form-label required">Persona a Asociar</label>
                        <select class="form-select" name="id_persona_fk" <?php echo $is_edit_mode ? 'disabled' : 'required'; ?>>
                            <?php if ($is_edit_mode): 
                                $persona_actual_q = $conn->query("SELECT Nombre, Apellido1 FROM persona WHERE idPersona = " . intval($id_persona_fk));
                                if($persona_actual = $persona_actual_q->fetch_assoc()): ?>
                                    <option value="<?php echo $id_persona_fk; ?>" selected><?php echo htmlspecialchars($persona_actual['Nombre'] . ' ' . $persona_actual['Apellido1']); ?></option>
                                <?php endif; ?>
                            <?php else: ?>
                                <option value="">Seleccione una persona sin usuario</option>
                                <?php mysqli_data_seek($personas_sin_usuario, 0); ?>
                                <?php while ($persona = $personas_sin_usuario->fetch_assoc()): ?>
                                    <option value="<?php echo $persona['idPersona']; ?>"><?php echo htmlspecialchars($persona['Nombre'] . ' ' . $persona['Apellido1']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                         <?php if ($is_edit_mode): ?>
                            <input type="hidden" name="id_persona_fk" value="<?php echo $id_persona_fk; ?>">
                            <small class="form-text text-muted">La persona asociada a un usuario no puede ser cambiada.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="usuarios.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?php echo $is_edit_mode ? 'Actualizar Usuario' : 'Guardar Usuario'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>