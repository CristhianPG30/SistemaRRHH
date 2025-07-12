<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$username = $_SESSION['username'] ?? null;
$persona = null;
$correo = '';
$telefono = '';

if ($username) {
    // 1. Buscar persona
    $sql = "SELECT p.* FROM usuario u
            INNER JOIN persona p ON u.id_persona_fk = p.idPersona
            WHERE u.username = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $persona = $res->fetch_assoc();
    $stmt->close();

    if ($persona) {
        // 2. Buscar primer correo
        $sql = "SELECT Correo FROM persona_correos WHERE idPersona_fk = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $persona['idPersona']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $correo = $row['Correo'];
        $stmt->close();

        // 3. Buscar primer teléfono
        $sql = "SELECT t.numero 
                FROM persona_telefonos pt
                INNER JOIN telefono t ON pt.id_telefono_fk = t.id_Telefono
                WHERE pt.id_persona_fk = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $persona['idPersona']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $telefono = $row['numero'];
        $stmt->close();
    }
}

if (!$persona) {
    include 'header.php';
    echo "<div class='alert alert-danger text-center' style='margin-left:280px;margin-top:40px;max-width:600px;'>
            No se pudo encontrar tu perfil personal.
          </div>";
    include 'footer.php';
    exit;
}

$msg = "";
$msg_type = "info"; // Tipo de mensaje por defecto

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo_nuevo = trim($_POST['correo'] ?? '');
    $telefono_nuevo = trim($_POST['telefono'] ?? '');
    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono_nuevo); // Limpiar teléfono
    
    // --- INICIO CORRECCIÓN: Validaciones del lado del servidor ---
    $errors = [];
    if (!filter_var($correo_nuevo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del correo electrónico es inválido.";
    }
    if (!preg_match('/^[245678]\d{7}$/', $telefono_limpio)) {
        $errors[] = "El teléfono debe tener 8 dígitos y empezar con 2, 4, 5, 6, 7 u 8.";
    }

    if (!empty($errors)) {
        $msg = implode("<br>", $errors);
        $msg_type = "danger";
    } else {
        // --- FIN CORRECCIÓN ---

        // Actualizar correo
        if ($correo_nuevo !== $correo) {
            $sql = "SELECT idCorreo FROM persona_correos WHERE idPersona_fk = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $persona['idPersona']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $sql2 = "UPDATE persona_correos SET Correo = ? WHERE idCorreo = ?";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("si", $correo_nuevo, $row['idCorreo']);
                $stmt2->execute();
                $stmt2->close();
            } else {
                $sql2 = "INSERT INTO persona_correos (Correo, idPersona_fk) VALUES (?, ?)";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("si", $correo_nuevo, $persona['idPersona']);
                $stmt2->execute();
                $stmt2->close();
            }
            $correo = $correo_nuevo;
        }

        // Actualizar teléfono
        if ($telefono_limpio !== $telefono) {
            $sql = "SELECT pt.id_telefono_fk FROM persona_telefonos pt WHERE pt.id_persona_fk = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $persona['idPersona']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $sql2 = "UPDATE telefono SET numero = ? WHERE id_Telefono = ?";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("si", $telefono_limpio, $row['id_telefono_fk']);
                $stmt2->execute();
                $stmt2->close();
            } else {
                $sql2 = "INSERT INTO telefono (numero, descripcion) VALUES (?, '')";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("s", $telefono_limpio);
                $stmt2->execute();
                $idTelefonoNuevo = $conn->insert_id;
                $stmt2->close();

                $sql3 = "INSERT INTO persona_telefonos (id_persona_fk, id_telefono_fk) VALUES (?, ?)";
                $stmt3 = $conn->prepare($sql3);
                $stmt3->bind_param("ii", $persona['idPersona'], $idTelefonoNuevo);
                $stmt3->execute();
                $stmt3->close();
            }
            $telefono = $telefono_limpio;
        }

        if (empty($errors)) {
           $msg = "¡Perfil actualizado correctamente!";
           $msg_type = "success";
        }
    }
}
?>

<?php include 'header.php'; ?>

<style>
.perfil-card {
    background: #fff;
    border-radius: 2.2rem;
    max-width: 470px;
    margin: 2.8rem auto 2rem auto;
    box-shadow: 0 8px 32px #13c6f135;
    padding: 2.2rem 2.3rem 2rem 2.3rem;
    border: 1.5px solid #d0f0fc;
    animation: fadeInPerfil .7s;
}
@keyframes fadeInPerfil { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}
.perfil-title {
    font-weight: 900; font-size: 2rem; color: #179ad7; letter-spacing: .7px;
    margin-bottom: .7rem;
    text-align:center;
}
.perfil-label { font-weight:700; color:#1783b0;}
.form-control, .form-select { border-radius:1.2rem; }
</style>

<div class="container" style="margin-left: 280px;">
    <div class="perfil-card">
        <div class="perfil-title"><i class="bi bi-person-circle"></i> Mi Perfil</div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> text-center"><?= $msg ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="perfil-label">Nombre completo</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($persona['Nombre'].' '.$persona['Apellido1'].' '.$persona['Apellido2']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="perfil-label">Cédula</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($persona['Cedula']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="perfil-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($correo) ?>" 
                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$"
                       title="Por favor, ingresa un correo electrónico válido." required>
            </div>
            <div class="mb-3">
                <label class="perfil-label">Teléfono</label>
                <input type="tel" name="telefono" id="telefono" class="form-control" value="<?= htmlspecialchars($telefono) ?>"
                       pattern="^[245678]\d{3}-?\d{4}$"
                       maxlength="9"
                       title="El formato debe ser XXXX-XXXX y empezar con 2, 4, 5, 6, 7 u 8." required>
            </div>
            <div class="mb-3">
                <label class="perfil-label">Rol de usuario</label>
                <input type="text" class="form-control" value="<?php
                    $roles = [1=>'Administrador',2=>'Colaborador',3=>'Jefatura',4=>'Recursos Humanos'];
                    echo $roles[$_SESSION['rol']] ?? 'Usuario';
                ?>" disabled>
            </div>
            <div class="mb-4 text-center">
                <button type="submit" class="btn btn-info px-4" style="border-radius:1.3rem;"><i class="bi bi-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('telefono');
    
    // Función para formatear el número de teléfono con guion
    function formatPhoneNumber(value) {
        if (!value) return value;
        const phoneNumber = value.replace(/[^\d]/g, '');
        const phoneNumberLength = phoneNumber.length;
        if (phoneNumberLength < 5) return phoneNumber;
        return `${phoneNumber.slice(0, 4)}-${phoneNumber.slice(4, 8)}`;
    }

    // Formatear el valor inicial al cargar la página
    phoneInput.value = formatPhoneNumber(phoneInput.value);

    // Formatear mientras el usuario escribe
    phoneInput.addEventListener('input', function (e) {
        e.target.value = formatPhoneNumber(e.target.value);
    });
});
</script>