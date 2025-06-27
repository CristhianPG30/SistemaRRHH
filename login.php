<?php 
session_start();
include 'db.php'; // Conexión a la base de datos

$errorMessage = '';

// Función para sanitizar entradas
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitizar entradas
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $errorMessage = "Por favor, ingresa tu usuario y contraseña.";
    } else {
        // Consulta para obtener información del usuario
        $sql = "SELECT u.*, c.idColaborador, c.activo 
                FROM usuario u 
                LEFT JOIN colaborador c ON u.id_persona_fk = c.id_persona_fk 
                WHERE BINARY u.username = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
        
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
        
                // Verificar la contraseña
                if ($password == $user['password']) {
                    if ($user['activo'] == 1 || is_null($user['activo'])) {
                        // Asignar variables de sesión
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['rol'] = $user['id_rol_fk'];
                        $_SESSION['persona_id'] = $user['id_persona_fk'];
        
                        if (!empty($user['idColaborador'])) {
                            $_SESSION['colaborador_id'] = $user['idColaborador'];
                        } else {
                            unset($_SESSION['colaborador_id']);
                        }
        
                        // --- INICIO DE LA CORRECCIÓN ---
                        // Recuperar información del jefe y departamento directamente desde la tabla colaborador
                        if (!empty($user['idColaborador'])) {
                            $colaborador_id = $user['idColaborador'];
                            
                            $sql_jerarquia = "SELECT c.id_jefe_fk, c.id_departamento_fk, d.nombre AS NombreDepartamento
                                              FROM colaborador c
                                              JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
                                              WHERE c.idColaborador = ?";
                            $stmt_jerarquia = $conn->prepare($sql_jerarquia);
                            if ($stmt_jerarquia) {
                                $stmt_jerarquia->bind_param("i", $colaborador_id);
                                $stmt_jerarquia->execute();
                                $result_jerarquia = $stmt_jerarquia->get_result();
                                if ($result_jerarquia->num_rows > 0) {
                                    $jerarquia = $result_jerarquia->fetch_assoc();
                                    $_SESSION['Jefe_idColaborador'] = $jerarquia['id_jefe_fk'];
                                    $_SESSION['Departamento_idDepartamento'] = $jerarquia['id_departamento_fk'];
                                    $_SESSION['NombreDepartamento'] = $jerarquia['NombreDepartamento'];
                                } else {
                                    $_SESSION['Jefe_idColaborador'] = null;
                                    $_SESSION['Departamento_idDepartamento'] = null;
                                    $_SESSION['NombreDepartamento'] = 'Sin Departamento';
                                }
                                $stmt_jerarquia->close();
                            } else {
                                $errorMessage = "Error interno al buscar jerarquía. Inténtalo de nuevo más tarde.";
                            }
                        } else {
                            $_SESSION['Jefe_idColaborador'] = null;
                            $_SESSION['Departamento_idDepartamento'] = null;
                            $_SESSION['NombreDepartamento'] = 'Sin Departamento';
                        }
                        // --- FIN DE LA CORRECCIÓN ---
        
                        // Redirige según el rol
                        switch ($_SESSION['rol']) {
                            case 1: header("Location: index_administrador.php"); break;
                            case 2: header("Location: index_colaborador.php"); break;
                            case 3: header("Location: index_jefatura.php"); break;
                            case 4: header("Location: index_rrhh.php"); break;
                            default: header("Location: login.php"); break;
                        }
                        exit();
                    } else {
                        $errorMessage = "Tu cuenta ha sido desactivada. Contacta con el administrador.";
                    }
                } else {
                    $errorMessage = "Usuario o contraseña incorrectos.";
                }
            } else {
                $errorMessage = "Usuario o contraseña incorrectos.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Error interno. Inténtalo de nuevo más tarde.";
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: url('img/FondoLogin2.png') no-repeat center center/cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background-color: rgba(255, 255, 255, 0.85);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 1.2s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        .form-control { border-radius: 10px; transition: all 0.3s ease; }
        .form-control:focus { box-shadow: 0 0 10px rgba(0, 123, 255, 0.5); border-color: #007bff; }
        .btn-primary { background-color: #007bff; border: none; transition: background-color 0.3s ease; }
        .btn-primary:hover { background-color: #0056b3; }
        .toggle-password { cursor: pointer; }
        .card-header { font-weight: bold; font-size: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .input-group-text { background-color: transparent; border: none; cursor: pointer; }
        .error-message { color: #e74c3c; margin-top: 10px; text-align: center; font-size: 0.9rem; }
    </style>
</head>

<body>

    <div class="login-container shadow-lg">
        <div class="card-header">Inicio de Sesión</div>

        <form action="" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Ingresa tu usuario" required>
            </div>
            <div class="mb-3 position-relative">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                    <span class="input-group-text">
                        <i id="toggle-icon" class="bi bi-eye-slash toggle-password"></i>
                    </span>
                </div>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="error-message alert alert-danger">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Iniciar Sesión</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('.toggle-password');
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggle-icon');

        togglePassword.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>

</html>
