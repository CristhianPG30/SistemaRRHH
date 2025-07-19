<?php
// Iniciar sesión de manera segura al principio de todo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$usuario_a_editar = [
    'idUsuario' => '',
    'username' => '',
    'id_rol_fk' => '',
    'id_persona_fk' => '',
    'persona_nombre' => ''
];
$is_edit_mode = false;
$page_title = 'Agregar Nuevo Usuario';

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
        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Usuario no encontrado.'];
        header("Location: usuarios.php");
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { die('Error de validación de seguridad.'); }
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
$personas_sin_usuario = $conn->query("SELECT idPersona, CONCAT(Nombre, ' ', Apellido1, ' ', Apellido2) AS nombre_completo FROM persona WHERE idPersona NOT IN (SELECT id_persona_fk FROM usuario)");
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
        .container { max-width: 800px; margin-top: 2rem; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem_1.5rem rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #5e72e4, #825ee4); color: #ffffff; text-align: center; padding: 1.5rem; }
        .form-label.required::after { content: " *"; color: #dc3545; }
        .form-stepper { display: flex; justify-content: space-between; width: 100%; margin: 1.5rem 0; }
        .step { text-align: center; flex: 1; position: relative; }
        .step-circle { width: 35px; height: 35px; border-radius: 50%; background-color: #e9ecef; color: #8898aa; display: flex; align-items: center; justify-content: center; font-weight: 600; margin: 0 auto 0.5rem; border: 3px solid #e9ecef; transition: all 0.3s; }
        .step-title { font-size: 0.8rem; font-weight: 500; color: #8898aa; }
        .step.active .step-circle { background-color: #5e72e4; color: white; border-color: #5e72e4; }
        .step.active .step-title { color: #5e72e4; font-weight: 600; }
        .step::after { content: ''; position: absolute; top: 16px; left: 50%; width: 100%; height: 3px; background-color: #e9ecef; z-index: -1; }
        .step:last-child::after { display: none; }
        .form-step { display: none; animation: fadeIn 0.4s; }
        .form-step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        #password-strength ul { list-style: none; padding-left: 0; font-size: 0.85rem; }
        #password-strength .invalid::before { content: '❌'; margin-right: 0.5rem; }
        #password-strength .valid::before { content: '✅'; margin-right: 0.5rem; }
        .invalid { color: #dc3545; }
        .valid { color: #198754; }
        .strength-meter { height: 6px; background: #e9ecef; border-radius: 6px; overflow: hidden; }
        .strength-meter div { height: 100%; width: 0; background: #dc3545; transition: all 0.3s; }
        #persona-list { max-height: 250px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 0.5rem; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="card">
            <div class="card-header"><h3 class="mb-0"><?= htmlspecialchars($page_title); ?></h3></div>
            <div class="card-body p-4 p-md-5">

                <div class="form-stepper">
                    <div class="step active" data-step="1"><div class="step-circle">1</div><div class="step-title">Datos de Usuario</div></div>
                    <div class="step" data-step="2"><div class="step-circle">2</div><div class="step-title">Asignación de Rol</div></div>
                    <div class="step" data-step="3"><div class="step-circle">3</div><div class="step-title">Asociar Persona</div></div>
                </div>

                <?php
                if (isset($_SESSION['flash_message'])) {
                    echo "<div class='alert alert-{$_SESSION['flash_message']['type']}'>{$_SESSION['flash_message']['message']}</div>";
                    unset($_SESSION['flash_message']);
                }
                ?>
                <form id="user-form" method="POST" action="form_usuario.php<?= $is_edit_mode ? '?id=' . $usuario_a_editar['idUsuario'] : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="idUsuario" value="<?= $usuario_a_editar['idUsuario']; ?>">
                    
                    <div class="form-step active" data-step-content="1">
                        <h5 class="mb-4">Paso 1: Credenciales de Acceso</h5>
                        <div class="mb-3"><label for="username" class="form-label required">Nombre de Usuario</label><div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control" name="username" value="<?= htmlspecialchars($usuario_a_editar['username']); ?>" required></div></div>
                        <div class="mb-3"><label for="password" class="form-label <?= !$is_edit_mode ? 'required' : ''; ?>"><?= $is_edit_mode ? 'Nueva Contraseña (Opcional)' : 'Contraseña'; ?></label><div class="input-group"><span class="input-group-text"><i class="bi bi-key"></i></span><input type="password" class="form-control" name="password" id="password" <?= !$is_edit_mode ? 'required' : ''; ?>><button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button></div><small class="form-text text-muted"><?= $is_edit_mode ? 'Dejar en blanco para no cambiar.' : 'Establezca una contraseña segura.'; ?></small></div>
                        <div id="password-strength" class="mb-3">
                            <div class="strength-meter"><div id="strength-bar"></div></div>
                            <ul>
                                <li id="length" class="invalid">Al menos 8 caracteres</li>
                                <li id="uppercase" class="invalid">Una letra mayúscula</li>
                                <li id="lowercase" class="invalid">Una letra minúscula</li>
                                <li id="number" class="invalid">Un número</li>
                                <li id="special" class="invalid">Un carácter especial (!@#$%&*)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-step" data-step-content="2">
                         <h5 class="mb-4">Paso 2: Permisos en el Sistema</h5>
                        <div class="mb-3"><label for="id_rol_fk" class="form-label required">Rol</label><select class="form-select" name="id_rol_fk" required><option value="">Seleccione un rol</option><?php mysqli_data_seek($roles, 0); while ($rol = $roles->fetch_assoc()): ?><option value="<?= $rol['idIdRol']; ?>" <?= ($usuario_a_editar['id_rol_fk'] == (int)$rol['idIdRol']) ? 'selected' : ''; ?>><?= htmlspecialchars($rol['descripcion']); ?></option><?php endwhile; ?></select></div>
                    </div>

                    <div class="form-step" data-step-content="3">
                        <h5 class="mb-4">Paso 3: Vínculo con Persona</h5>
                        <div class="mb-4"><label for="id_persona_fk" class="form-label required">Persona a Asociar</label>
                            <?php if ($is_edit_mode): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($usuario_a_editar['persona_nombre']); ?>" disabled readonly>
                                <input type="hidden" name="id_persona_fk" value="<?= $usuario_a_editar['id_persona_fk']; ?>">
                                <small class="form-text text-muted">La persona asociada a un usuario no puede ser cambiada.</small>
                            <?php else: ?>
                                <input type="text" id="search-persona" class="form-control mb-2" placeholder="Buscar persona...">
                                <div id="persona-list">
                                    <?php mysqli_data_seek($personas_sin_usuario, 0); while ($persona = $personas_sin_usuario->fetch_assoc()): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="id_persona_fk" id="persona-<?= $persona['idPersona']; ?>" value="<?= $persona['idPersona']; ?>" required>
                                        <label class="form-check-label" for="persona-<?= $persona['idPersona']; ?>">
                                            <?= htmlspecialchars($persona['nombre_completo']); ?>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;"><i class="bi bi-arrow-left me-1"></i>Anterior</button>
                        <a href="usuarios.php" class="btn btn-outline-secondary" id="cancelBtn"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                        <button type="button" class="btn btn-primary" id="nextBtn">Siguiente<i class="bi bi-arrow-right ms-1"></i></button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;"><i class="bi bi-save me-1"></i><?= $is_edit_mode ? 'Actualizar Usuario' : 'Guardar Usuario'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentStep = 1;
            const steps = document.querySelectorAll('.form-step');
            const stepIndicators = document.querySelectorAll('.step');
            const nextBtn = document.getElementById('nextBtn');
            const prevBtn = document.getElementById('prevBtn');
            const submitBtn = document.getElementById('submitBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            function showStep(stepNumber) {
                steps.forEach(step => step.classList.remove('active'));
                document.querySelector(`[data-step-content="${stepNumber}"]`).classList.add('active');

                stepIndicators.forEach((step, index) => {
                    step.classList.remove('active');
                    if (index + 1 === stepNumber) {
                        step.classList.add('active');
                    }
                });

                prevBtn.style.display = stepNumber > 1 ? 'inline-block' : 'none';
                cancelBtn.style.display = stepNumber === 1 ? 'inline-block' : 'none';
                nextBtn.style.display = stepNumber < steps.length ? 'inline-block' : 'none';
                submitBtn.style.display = stepNumber === steps.length ? 'inline-block' : 'none';
            }

            function validateStep(stepNumber) {
                let isValid = true;
                const currentStepContent = document.querySelector(`[data-step-content="${stepNumber}"]`);
                const inputs = currentStepContent.querySelectorAll('input[required], select[required], input[type=radio][required]');
                
                inputs.forEach(input => {
                    if (input.type === 'radio') {
                        const radioGroup = document.getElementsByName(input.name);
                        if (![...radioGroup].some(radio => radio.checked)) {
                            isValid = false;
                        }
                    } else if (!input.value.trim()) {
                        isValid = false;
                    }

                    if (input.id === 'password' && input.value.length > 0) {
                        const val = input.value;
                        if(val.length < 8 || !val.match(/[A-Z]/) || !val.match(/[a-z]/) || !val.match(/[0-9]/) || !val.match(/[!@#$%&*]/)) {
                           isValid = false;
                           alert('La nueva contraseña no cumple con los requisitos de seguridad.');
                        }
                    }
                });

                if(!isValid) {
                     alert('Por favor, completa todos los campos requeridos de este paso.');
                }
                return isValid;
            }

            nextBtn.addEventListener('click', () => {
                if (validateStep(currentStep)) {
                    if (currentStep < steps.length) {
                        currentStep++;
                        showStep(currentStep);
                    }
                }
            });

            prevBtn.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            });

            // Password strength checker
            const password = document.getElementById('password');
            const strengthBar = document.getElementById('strength-bar');
            const criteria = {
                length: document.getElementById('length'),
                uppercase: document.getElementById('uppercase'),
                lowercase: document.getElementById('lowercase'),
                number: document.getElementById('number'),
                special: document.getElementById('special')
            };

            password.addEventListener('input', function() {
                const val = password.value;
                let strength = 0;
                
                const validations = {
                    length: val.length >= 8,
                    uppercase: val.match(/[A-Z]/),
                    lowercase: val.match(/[a-z]/),
                    number: val.match(/[0-9]/),
                    special: val.match(/[!@#$%&*]/)
                };

                for (const key in validations) {
                    if(validations[key]) {
                        criteria[key].classList.remove('invalid');
                        criteria[key].classList.add('valid');
                        strength++;
                    } else {
                        criteria[key].classList.remove('valid');
                        criteria[key].classList.add('invalid');
                    }
                }
                
                strengthBar.style.width = (strength * 20) + '%';
                if (strength <= 2) strengthBar.style.background = '#dc3545';
                else if (strength <= 4) strengthBar.style.background = '#ffc107';
                else strengthBar.style.background = '#198754';
            });
            
            // Toggle password visibility
            document.getElementById('togglePassword').addEventListener('click', function () {
                const icon = this.querySelector('i');
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
            
             // Search functionality for persona list
            const searchPersona = document.getElementById('search-persona');
            if(searchPersona) {
                searchPersona.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const personaItems = document.querySelectorAll('#persona-list .form-check');
                    personaItems.forEach(item => {
                        const label = item.querySelector('label').textContent.toLowerCase();
                        item.style.display = label.includes(filter) ? '' : 'none';
                    });
                });
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>