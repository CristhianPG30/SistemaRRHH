<?php 
session_start();
include 'db.php';

// Inicializar variables
$idUsuario = '';
$username = 'usuario'; // Valor por defecto para el campo "Usuario". Cámbialo a '' si prefieres que esté vacío.
$password = '';
$IdRol = '';
$Persona_idPersona = '';
$change_password = false;

$error = ''; // Variable para mensajes de error
$success = ''; // Variable para mensajes de éxito

// Consultar los roles y las personas de la base de datos
$roles = $conn->query("SELECT * FROM idrol");
$personas = $conn->query("SELECT * FROM persona");

// Verificar si es edición (si hay un ID en la URL)
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $idUsuario = intval($_GET['id']); // Convertir el id a un entero para mayor seguridad

    // Usar consulta preparada para obtener los datos del usuario que se está editando
    $stmt = $conn->prepare("SELECT * FROM usuario WHERE idUsuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $username = $row['username']; // Sobrescribir el valor por defecto si hay un nombre de usuario
        $IdRol = $row['IdRol_idIdRol'];
        $Persona_idPersona = $row['Persona_idPersona'];
    } else {
        $error = "Usuario no encontrado.";
    }

    $stmt->close(); // Cerrar la consulta preparada
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Obtener y sanitizar los datos del formulario
    $username = trim($_POST['username']);
    $change_password = isset($_POST['change_password']);
    $password = $change_password ? trim($_POST['password']) : '';
    $IdRol = intval($_POST['IdRol']);
    $Persona_idPersona = intval($_POST['Persona_idPersona']);

    // Validaciones básicas
    if (empty($username) || empty($IdRol) || empty($Persona_idPersona)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    }

    if ($change_password && empty($password)) {
        $error = "Por favor, ingresa una nueva contraseña o desmarca la opción para no cambiarla.";
    }

    if (empty($error)) {
        // Verificar si el nombre de usuario ya existe
        $stmt = $conn->prepare("SELECT * FROM usuario WHERE username = ? AND idUsuario != ?");
        $stmt->bind_param("si", $username, $idUsuario);
        $stmt->execute();
        $check_username = $stmt->get_result();

        if ($check_username->num_rows > 0) {
            $error = "El nombre de usuario ya está en uso. Por favor, elija otro.";
        }

        $stmt->close();

        // Verificar si la persona ya está asociada a un usuario
        $stmt = $conn->prepare("SELECT * FROM usuario WHERE Persona_idPersona = ? AND idUsuario != ?");
        $stmt->bind_param("ii", $Persona_idPersona, $idUsuario);
        $stmt->execute();
        $check_persona = $stmt->get_result();

        if ($check_persona->num_rows > 0) {
            $error = "Esta persona ya está asociada a un usuario.";
        }

        $stmt->close();

        // Si no hay errores, proceder a insertar o actualizar el registro
        if (empty($error)) {
            if (empty($idUsuario)) {
                // Crear nuevo usuario
                $stored_password = !empty($password) ? $password : NULL;
                $stmt = $conn->prepare("INSERT INTO usuario (username, password, IdRol_idIdRol, Persona_idPersona) 
                                        VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $username, $stored_password, $IdRol, $Persona_idPersona);
                if ($stmt->execute()) {
                    $success = "Usuario agregado con éxito.";
                    $username = $password = '';
                    $IdRol = '';
                    $Persona_idPersona = '';
                } else {
                    $error = "Error al agregar el usuario: " . $stmt->error;
                }
            } else {
                // Actualizar usuario existente
                if ($change_password && !empty($password)) {
                    $stored_password = $password;
                    $stmt = $conn->prepare("UPDATE usuario SET username = ?, password = ?, 
                                            IdRol_idIdRol = ?, Persona_idPersona = ? 
                                            WHERE idUsuario = ?");
                    $stmt->bind_param("ssiii", $username, $stored_password, $IdRol, $Persona_idPersona, $idUsuario);
                } else {
                    $stmt = $conn->prepare("UPDATE usuario SET username = ?, 
                                            IdRol_idIdRol = ?, Persona_idPersona = ? 
                                            WHERE idUsuario = ?");
                    $stmt->bind_param("siii", $username, $IdRol, $Persona_idPersona, $idUsuario);
                }

                if ($stmt->execute()) {
                    $success = "Usuario actualizado con éxito.";
                } else {
                    $error = "Error al actualizar el usuario: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo empty($idUsuario) ? 'Agregar Usuario' : 'Editar Usuario'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .container {
            max-width: 600px;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
        }
        .card-header {
            background-color: #343a40;
            color: #ffffff;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .btn-save {
            background-color: #5cb85c;
            border-color: #5cb85c;
            color: #ffffff;
        }
        .btn-save:hover {
            background-color: #4cae4c;
            border-color: #4cae4c;
        }
        .btn-cancel {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff;
        }
        .btn-cancel:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .form-label.required::after {
            content: " *";
            color: red;
        }
        .alert {
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="card p-4">
            <div class="card-header">
                <h3 class="mb-0"><?php echo empty($idUsuario) ? 'Agregar Usuario' : 'Editar Usuario'; ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>
                <form action="form_usuario.php<?php if (!empty($idUsuario)) { echo '?id=' . $idUsuario; } ?>" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label required">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <?php if (!empty($idUsuario)): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="change_password" name="change_password" <?php echo $change_password ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="change_password">Cambiar contraseña</label>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3" id="password_field" style="<?php echo (!empty($idUsuario) && !$change_password) ? 'display: none;' : ''; ?>">
                        <label for="password" class="form-label <?php echo empty($idUsuario) ? 'required' : ''; ?>">
                            <?php echo empty($idUsuario) ? 'Contraseña' : 'Nueva Contraseña'; ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" <?php echo (empty($idUsuario) || $change_password) ? 'required' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label for="IdRol" class="form-label required">Tipo de Rol</label>
                        <select class="form-select" id="IdRol" name="IdRol" required>
                            <option value="">Seleccione un rol</option>
                            <?php while ($rol = $roles->fetch_assoc()): ?>
                                <option value="<?php echo $rol['idIdRol']; ?>" <?php if ($IdRol == $rol['idIdRol']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($rol['Descripcion']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="Persona_idPersona" class="form-label required">Persona Asociada</label>
                        <select class="form-select" id="Persona_idPersona" name="Persona_idPersona" required>
                            <option value="">Seleccione una persona</option>
                            <?php while ($persona = $personas->fetch_assoc()): ?>
                                <option value="<?php echo $persona['idPersona']; ?>" <?php if ($Persona_idPersona == $persona['idPersona']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($persona['Nombre'] . ' ' . $persona['Apellido1'] . ' ' . $persona['Apellido2']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="usuarios.php" class="btn btn-cancel"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                        <button type="submit" name="save" class="btn btn-save"><i class="bi bi-save me-1"></i><?php echo empty($idUsuario) ? 'Guardar' : 'Actualizar'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const changePasswordCheckbox = document.getElementById('change_password');
            const passwordField = document.getElementById('password_field');
            const passwordInput = document.getElementById('password');

            if (changePasswordCheckbox) {
                changePasswordCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        passwordField.style.display = 'block';
                        passwordInput.required = true;
                    } else {
                        passwordField.style.display = 'none';
                        passwordInput.required = false;
                        passwordInput.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>
