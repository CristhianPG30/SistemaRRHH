<?php 
session_start(['cookie_httponly' => true, 'cookie_secure' => false, 'cookie_samesite' => 'Strict']);
include 'db.php';
$errorMessage = '';

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // DEBUG OPCIONAL: descomenta para ver los datos recibidos del formulario
    // echo "<pre>"; print_r($_POST); echo "</pre>";

    $username = sanitize_input($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $errorMessage = "Por favor, ingresa tu usuario y contraseña.";
    } else {
        $sql = "SELECT u.idUsuario, u.username, u.password, u.id_rol_fk, u.id_persona_fk, c.idColaborador, c.activo 
                FROM usuario u 
                LEFT JOIN colaborador c ON u.id_persona_fk = c.id_persona_fk 
                WHERE BINARY u.username = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Debug opcional para verificar los datos recuperados y el hash
                // echo "<pre>"; print_r($user); echo "</pre>";
                // echo password_hash('123', PASSWORD_DEFAULT); // genera el hash para comparar

                if (password_verify($password, $user['password'])) {
                    if ($user['activo'] == 1 || is_null($user['activo'])) {
                        session_regenerate_id(true);
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['rol'] = (int)$user['id_rol_fk'];
                        $_SESSION['persona_id'] = $user['id_persona_fk'];
                        if (!empty($user['idColaborador'])) $_SESSION['colaborador_id'] = $user['idColaborador'];
                        switch ($_SESSION['rol']) {
                            case 1: header("Location: index_administrador.php"); break;
                            case 2: header("Location: index_colaborador.php"); break;
                            case 3: header("Location: index_jefatura.php"); break;
                            case 4: header("Location: index_rrhh.php"); break;
                            default: $errorMessage = "Rol de usuario no reconocido."; break;
                        }
                        exit();
                    } else {
                        $errorMessage = "Tu cuenta ha sido desactivada.";
                    }
                } else {
                    $errorMessage = "Usuario o contraseña incorrectos.";
                }
            } else {
                $errorMessage = "Usuario o contraseña incorrectos.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Error interno del sistema.";
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
    <title>Inicio de Sesión - Sistema RRHH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5e72e4;
            --card-background: #ffffff;
            --text-color: #32325d;
            --form-bg: #f6f9fc;
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f7fc;
            overflow: hidden;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="%23cde4f9" /><stop offset="100%" stop-color="%23f4f7fc" /></linearGradient></defs><rect fill="url(%23g)" width="100%" height="100%"/><path d="M0,50 C250,150 350,0 600,100 L600,00 L0,0 Z" fill-opacity="0.1" fill="%235e72e4"></path><path d="M1200,600 C1000,500 1100,700 800,600 L1200,600 L1200,0 Z" fill-opacity="0.1" fill="%232dce89"></path></svg>');
            background-size: cover;
            background-position: center;
            z-index: -1;
        }
        .login-wrapper {
            display: grid;
            grid-template-columns: 0.9fr 1fr;
            max-width: 900px;
            width: 100%;
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            overflow: visible;
            position: relative;
        }
        .login-logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            border-right: 1px solid #e9ecef;
        }
        .company-logo {
            max-width: 220px;
            width: 100%;
        }
        .login-form-container {
            padding: 2.5rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        #bear-avatar {
            width: 120px;
            height: 120px;
            margin: -95px auto 15px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: 4px solid var(--card-background);
            position: relative;
            z-index: 10;
        }
        .form-control { border-radius: 0.5rem; padding: 0.8rem 1rem; border: 1px solid #cad1d7; background-color: var(--form-bg); }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); padding: 0.8rem; font-weight: 600; }
        .btn-primary:hover { background-color: #5165d3; border-color: #5165d3; }
        #togglePassword { cursor: pointer; }
        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-logo-container { display: none; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-logo-container">
            <img src="img/edginton.png" alt="Logo Edginton S.A." class="company-logo">
        </div>
        <div class="login-form-container">
            <div class="login-header">
                <div id="bear-avatar"></div>
                <h2>Bienvenido</h2>
                <p class="text-muted small">Ingresa tus credenciales para continuar</p>
            </div>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger text-center p-2" role="alert">
                    <?= htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="input-group-text" id="togglePassword"><i class="bi bi-eye-slash"></i></span>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const bearAvatar = document.getElementById('bear-avatar');
            const togglePassword = document.getElementById('togglePassword');
            const normalBearSrc = "url('img/oso-normal.png')";
            const hidingBearSrc = "url('img/oso-escondido.png')";
            bearAvatar.style.backgroundImage = normalBearSrc;
            if (passwordInput && bearAvatar) {
                passwordInput.addEventListener('focus', () => { bearAvatar.style.backgroundImage = hidingBearSrc; });
                passwordInput.addEventListener('blur', () => { bearAvatar.style.backgroundImage = normalBearSrc; });
            }
            if(togglePassword) {
                togglePassword.addEventListener('click', () => {
                    const icon = togglePassword.querySelector('i');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            }
        });
    </script>
</body>
</html>
