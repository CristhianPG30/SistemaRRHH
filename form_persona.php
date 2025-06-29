<?php  
session_start();
include 'db.php';

// Proteger la página
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

// Sistema de Notificaciones (Flash Messages)
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = "<div class='alert alert-{$_SESSION['flash_message']['type']} alert-dismissible fade show' role='alert'>
                        {$_SESSION['flash_message']['message']}
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                      </div>";
    unset($_SESSION['flash_message']);
}

// --- Lógica de la Página ---

$is_edit_mode = false;
$idPersona = ''; $nombre = ''; $apellido1 = ''; $apellido2 = ''; $cedula = ''; $fecha_nac = '';
$salario = 0; $correo_electronico = ''; $direccion_exacta = ''; $estado_civil_id = '';
$nacionalidad_id = ''; $genero_id = ''; $telefono = ''; $departamento_id = '';
$provincia_id = ''; $canton_id = ''; $distrito_id = '';
$page_title = 'Agregar Nueva Persona';

$opciones_estado_civil = [1 => 'Soltero(a)', 2 => 'Casado(a)', 3 => 'Divorciado(a)', 4 => 'Viudo(a)', 5 => 'Unión Libre'];
$opciones_nacionalidad = [1 => 'Costarricense', 2 => 'Extranjero Residente', 3 => 'Extranjero No Residente'];
$opciones_genero = [1 => 'Masculino', 2 => 'Femenino', 3 => 'Prefiero no indicar'];
$departamentos = $conn->query("SELECT * FROM departamento");

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit_mode = true;
    $idPersona = intval($_GET['id']);
    $page_title = 'Editar Información de Persona';
    
    $stmt = $conn->prepare("
        SELECT p.*, c.id_departamento_fk, d.Dir_exacta, d.Provincias_idProvincias, d.Cantones_idCanton, d.Distritos_idDistritos, t.numero AS Numero_de_Telefono, pc.Correo
        FROM persona p 
        LEFT JOIN colaborador c ON p.idPersona = c.id_persona_fk
        LEFT JOIN direccion d ON p.id_direccion_fk = d.idDireccion 
        LEFT JOIN persona_telefonos pt ON p.idPersona = pt.id_persona_fk
        LEFT JOIN telefono t ON pt.id_telefono_fk = t.id_Telefono
        LEFT JOIN persona_correos pc ON p.idPersona = pc.IdPersona_fk
        WHERE p.idPersona = ? LIMIT 1");
    $stmt->bind_param("i", $idPersona);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nombre = $row['Nombre']; $apellido1 = $row['Apellido1']; $apellido2 = $row['Apellido2'];
        $cedula = $row['Cedula']; $fecha_nac = $row['Fecha_nac']; $correo_electronico = $row['Correo'];
        $direccion_exacta = $row['Dir_exacta']; $estado_civil_id = $row['id_estado_civil_fk'];
        $nacionalidad_id = $row['id_nacionalidad_fk']; $genero_id = $row['id_genero_cat_fk'];
        $telefono = $row['Numero_de_Telefono']; $departamento_id = $row['id_departamento_fk'];
        $provincia_id = $row['Provincias_idProvincias']; $canton_id = $row['Cantones_idCanton']; $distrito_id = $row['Distritos_idDistritos'];
        
        $colaborador_stmt = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ?");
        $colaborador_stmt->bind_param("i", $idPersona);
        $colaborador_stmt->execute();
        $col_res = $colaborador_stmt->get_result();
        if ($col_res->num_rows > 0) {
            $idColaborador = $col_res->fetch_assoc()['idColaborador'];
            $salario_stmt = $conn->prepare("SELECT salario_bruto FROM planillas WHERE id_colaborador_fk = ? ORDER BY fecha_generacion DESC LIMIT 1");
            $salario_stmt->bind_param("i", $idColaborador);
            $salario_stmt->execute();
            $salario_res = $salario_stmt->get_result();
            if ($salario_res->num_rows > 0){ $salario = $salario_res->fetch_assoc()['salario_bruto'] ?? 0; }
            $salario_stmt->close();
        }
        $colaborador_stmt->close();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idPersona = isset($_POST['idPersona']) ? intval($_POST['idPersona']) : 0;
    $is_edit_mode = ($idPersona > 0);
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $apellido1 = $conn->real_escape_string(trim($_POST['apellido1']));
    $apellido2 = $conn->real_escape_string(trim($_POST['apellido2']));
    $cedula = $conn->real_escape_string(trim($_POST['identificacion']));
    $fecha_nac = $_POST['fecha_nac'];
    $salario_bruto_inicial = str_replace(',', '', $_POST['salario']);
    $correo_electronico_post = $conn->real_escape_string(trim($_POST['correo_electronico']));
    $direccion_exacta = $conn->real_escape_string(trim($_POST['direccion_exacta']));
    $estado_civil = intval($_POST['estado_civil']);
    $nacionalidad = intval($_POST['nacionalidad']);
    $genero = intval($_POST['genero']);
    $departamento_id = intval($_POST['departamento']);
    $provincia_id = intval($_POST['provincia']);
    $canton_id = intval($_POST['canton']);
    $distrito_id = intval($_POST['distrito']);
    $telefono_numero = $conn->real_escape_string(trim($_POST['telefono']));

    $errors = [];
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $nombre)) $errors[] = "El nombre solo debe contener letras.";
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $apellido1)) $errors[] = "El primer apellido solo debe contener letras.";
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $apellido2)) $errors[] = "El segundo apellido solo debe contener letras.";
    if (!filter_var($correo_electronico_post, FILTER_VALIDATE_EMAIL)) $errors[] = "El formato del correo electrónico no es válido.";
    if (empty($fecha_nac) || (new DateTime())->diff(new DateTime($fecha_nac))->y < 18) $errors[] = "La persona debe ser mayor de 18 años.";

    if (!empty($errors)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        header("Location: form_persona.php" . ($is_edit_mode ? "?id=$idPersona" : ""));
        exit;
    }

    $conn->begin_transaction();
    try {
        $result_dir = $conn->query("SELECT id_direccion_fk FROM persona WHERE idPersona = " . intval($idPersona));
        $direccion_id = ($result_dir && $result_dir->num_rows > 0) ? $result_dir->fetch_assoc()['id_direccion_fk'] : null;
        if ($direccion_id) { $stmt = $conn->prepare("UPDATE direccion SET Dir_exacta = ?, Provincias_idProvincias = ?, Cantones_idCanton = ?, Distritos_idDistritos = ? WHERE idDireccion = ?"); $stmt->bind_param("siiii", $direccion_exacta, $provincia_id, $canton_id, $distrito_id, $direccion_id); } 
        else { $stmt = $conn->prepare("INSERT INTO direccion (Dir_exacta, Provincias_idProvincias, Cantones_idCanton, Distritos_idDistritos) VALUES (?, ?, ?, ?)"); $stmt->bind_param("siii", $direccion_exacta, $provincia_id, $canton_id, $distrito_id); }
        $stmt->execute();
        if (!$direccion_id) $direccion_id = $stmt->insert_id;
        $stmt->close();
        
        $stmt_tel = $conn->prepare("SELECT id_Telefono FROM telefono WHERE numero = ?");
        $stmt_tel->bind_param("s", $telefono_numero);
        $stmt_tel->execute();
        $tel_res = $stmt_tel->get_result();
        if($tel_res->num_rows > 0) { $telefono_id = $tel_res->fetch_assoc()['id_Telefono']; }
        else { $stmt_insert_tel = $conn->prepare("INSERT INTO telefono (numero) VALUES (?)"); $stmt_insert_tel->bind_param("s", $telefono_numero); $stmt_insert_tel->execute(); $telefono_id = $stmt_insert_tel->insert_id; $stmt_insert_tel->close(); }
        $stmt_tel->close();

        if ($is_edit_mode) {
            $sql = "UPDATE persona SET Nombre=?, Apellido1=?, Apellido2=?, Cedula=?, Fecha_nac=?, id_direccion_fk=?, id_estado_civil_fk=?, id_nacionalidad_fk=?, id_genero_cat_fk=? WHERE idPersona=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssiiiiii", $nombre, $apellido1, $apellido2, $cedula, $fecha_nac, $direccion_id, $estado_civil, $nacionalidad, $genero, $idPersona);
        } else {
            $sql = "INSERT INTO persona (Nombre, Apellido1, Apellido2, Cedula, Fecha_nac, id_direccion_fk, id_estado_civil_fk, id_nacionalidad_fk, id_genero_cat_fk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssiiii", $nombre, $apellido1, $apellido2, $cedula, $fecha_nac, $direccion_id, $estado_civil, $nacionalidad, $genero);
        }
        $stmt->execute();
        $current_persona_id = $is_edit_mode ? $idPersona : $stmt->insert_id;
        $stmt->close();

        $stmt_map_correo_del = $conn->prepare("DELETE FROM persona_correos WHERE IdPersona_fk = ?");
        $stmt_map_correo_del->bind_param("i", $current_persona_id);
        $stmt_map_correo_del->execute();
        $stmt_map_correo_insert = $conn->prepare("INSERT INTO persona_correos (Correo, IdPersona_fk) VALUES (?, ?)");
        $stmt_map_correo_insert->bind_param("si", $correo_electronico_post, $current_persona_id);
        $stmt_map_correo_insert->execute();
        $stmt_map_correo_insert->close();
        
        $stmt_map_tel_del = $conn->prepare("DELETE FROM persona_telefonos WHERE id_persona_fk = ?");
        $stmt_map_tel_del->bind_param("i", $current_persona_id);
        $stmt_map_tel_del->execute();
        $stmt_map_tel_insert = $conn->prepare("INSERT INTO persona_telefonos (id_persona_fk, id_telefono_fk) VALUES (?, ?)");
        $stmt_map_tel_insert->bind_param("ii", $current_persona_id, $telefono_id);
        $stmt_map_tel_insert->execute();
        $stmt_map_tel_insert->close();
        
        $stmt_col_check = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ?");
        $stmt_col_check->bind_param("i", $current_persona_id);
        $stmt_col_check->execute();
        $col_res = $stmt_col_check->get_result();
        if ($col_res->num_rows > 0) {
            $idColaborador = $col_res->fetch_assoc()['idColaborador'];
            $stmt_update_col = $conn->prepare("UPDATE colaborador SET id_departamento_fk = ? WHERE idColaborador = ?");
            $stmt_update_col->bind_param("ii", $departamento_id, $idColaborador);
            $stmt_update_col->execute();
        } else {
            $stmt_col_insert = $conn->prepare("INSERT INTO colaborador (id_persona_fk, activo, fecha_ingreso, id_departamento_fk, id_jefe_fk) VALUES (?, 1, NOW(), ?, NULL)");
            $stmt_col_insert->bind_param("ii", $current_persona_id, $departamento_id);
            $stmt_col_insert->execute();
            $idColaborador = $stmt_col_insert->insert_id;
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Operación realizada con éxito!'];
        header('Location: personas.php');
        exit;

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error al guardar los datos: ' . $exception->getMessage()];
        header("Location: form_persona.php" . ($is_edit_mode ? "?id=$idPersona" : ""));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-container { max-width: 900px; }
        .form-stepper { display: flex; justify-content: space-between; width: 100%; margin: 2rem 0; }
        .form-stepper .step { display: flex; flex-direction: column; align-items: center; position: relative; flex-grow: 1; }
        .form-stepper .step .step-circle { width: 40px; height: 40px; border-radius: 50%; background-color: #d1d9e6; color: #858796; display: flex; align-items: center; justify-content: center; font-weight: 600; transition: all 0.3s; z-index: 2; border: 3px solid #d1d9e6; }
        .form-stepper .step .step-title { font-size: 0.85rem; margin-top: 0.5rem; color: #858796; font-weight: 500; }
        .form-stepper .step.active .step-circle { background-color: #4e73df; color: white; border-color: #4e73df; }
        .form-stepper .step.active .step-title { color: #4e73df; font-weight: 600; }
        .form-stepper .step.completed .step-circle { background-color: #1cc88a; color: white; border-color: #1cc88a; }
        .form-stepper .step.completed .step-title { color: #1cc88a; }
        .form-stepper .step::after { content: ''; position: absolute; top: 18px; left: 50%; width: 100%; height: 4px; background-color: #d1d9e6; z-index: 1; }
        .form-stepper .step:last-child::after { display: none; }
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .btn-primary { background-color: #4e73df; border: none; font-weight: 500; padding: 0.75rem 1.5rem; border-radius: 0.5rem; }
        .btn-secondary { background-color: #858796; border-color: #858796; font-weight: 500; padding: 0.75rem 1.5rem; border-radius: 0.5rem; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container main-container my-4">
    <div class="text-center mb-4">
        <h1 class="h3 mb-1 text-gray-800" style="font-weight: 600;"><?= htmlspecialchars($page_title); ?></h1>
        <p class="text-muted">Completa la información en cada paso para continuar.</p>
    </div>
    <?= $flash_message ?>

    <div class="form-stepper">
        <div class="step active" data-step="1"><div class="step-circle">1</div><div class="step-title">Personal</div></div>
        <div class="step" data-step="2"><div class="step-circle">2</div><div class="step-title">Contacto</div></div>
        <div class="step" data-step="3"><div class="step-circle">3</div><div class="step-title">Laboral</div></div>
    </div>

    <form id="personaForm" action="form_persona.php<?= $is_edit_mode ? '?id='.$idPersona : '' ?>" method="POST" novalidate>
        <input type="hidden" name="idPersona" value="<?= htmlspecialchars($idPersona); ?>">
        
        <div class="card p-4">
            <div class="form-step active">
                <h5 class="mb-4">Paso 1: Información Personal</h5>
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="nombre" class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($nombre); ?>" required pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$"><div class="invalid-feedback">Formato inválido. Solo se permiten letras.</div></div>
                    <div class="col-md-4 mb-3"><label for="apellido1" class="form-label">Primer Apellido</label><input type="text" class="form-control" name="apellido1" value="<?= htmlspecialchars($apellido1); ?>" required pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$"><div class="invalid-feedback">Formato inválido. Solo se permiten letras.</div></div>
                    <div class="col-md-4 mb-3"><label for="apellido2" class="form-label">Segundo Apellido</label><input type="text" class="form-control" name="apellido2" value="<?= htmlspecialchars($apellido2); ?>" required pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$"><div class="invalid-feedback">Formato inválido. Solo se permiten letras.</div></div>
                    <div class="col-md-6 mb-3"><label for="identificacion" class="form-label">Identificación (Cédula, DIMEX, etc.)</label><input type="text" class="form-control" name="identificacion" id="identificacion" maxlength="20" value="<?= htmlspecialchars($cedula); ?>" required><div class="invalid-feedback">Este campo es requerido.</div></div>
                    <div class="col-md-6 mb-3"><label for="fecha_nac" class="form-label">Fecha de Nacimiento</label><input type="date" class="form-control" name="fecha_nac" id="fecha_nac" value="<?= htmlspecialchars($fecha_nac); ?>" required><div class="invalid-feedback">La persona debe ser mayor de 18 años.</div></div>
                </div>
                 <div class="d-flex justify-content-end mt-3">
                    <a href="personas.php" class="btn btn-light me-2">Cancelar</a>
                    <button type="button" class="btn btn-primary" data-nav="next">Siguiente <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <div class="form-step">
                <h5 class="mb-4">Paso 2: Contacto y Ubicación</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="correo_electronico" class="form-label">Correo Electrónico</label><input type="email" class="form-control" name="correo_electronico" value="<?= htmlspecialchars($correo_electronico); ?>" required><div class="invalid-feedback">Ingrese un correo válido.</div></div>
                    <div class="col-md-6 mb-3"><label for="telefono" class="form-label">Teléfono</label><input type="text" class="form-control" name="telefono" id="telefono" placeholder="0000-0000" value="<?= htmlspecialchars($telefono); ?>" required><div class="invalid-feedback">Este campo es requerido.</div></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="provincia" class="form-label">Provincia</label><select class="form-select" id="provincia" name="provincia" required></select><div class="invalid-feedback">Seleccione una provincia.</div></div>
                    <div class="col-md-4 mb-3"><label for="canton" class="form-label">Cantón</label><select class="form-select" id="canton" name="canton" required></select><div class="invalid-feedback">Seleccione un cantón.</div></div>
                    <div class="col-md-4 mb-3"><label for="distrito" class="form-label">Distrito</label><select class="form-select" id="distrito" name="distrito" required></select><div class="invalid-feedback">Seleccione un distrito.</div></div>
                </div>
                <div class="mb-3"><label for="direccion_exacta" class="form-label">Dirección Exacta</label><textarea class="form-control" name="direccion_exacta" rows="2" required><?= htmlspecialchars($direccion_exacta); ?></textarea><div class="invalid-feedback">Este campo es requerido.</div></div>
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-secondary" data-nav="prev"><i class="bi bi-arrow-left"></i> Anterior</button>
                    <button type="button" class="btn btn-primary" data-nav="next">Siguiente <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <div class="form-step">
                 <h5 class="mb-4">Paso 3: Datos Laborales y Adicionales</h5>
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="departamento" class="form-label">Departamento</label><select class="form-select" name="departamento" required><option value="">Seleccione...</option><?php if($departamentos && $departamentos->num_rows > 0) { mysqli_data_seek($departamentos, 0); while ($dept = $departamentos->fetch_assoc()): ?><option value="<?= $dept['idDepartamento']; ?>" <?= ($departamento_id == $dept['idDepartamento']) ? 'selected' : ''; ?>><?= htmlspecialchars($dept['nombre']); ?></option><?php endwhile; } ?></select><div class="invalid-feedback">Seleccione un departamento.</div></div>
                    <div class="col-md-4 mb-3"><label for="salario" class="form-label">Salario Bruto Inicial</label><div class="input-group"><span class="input-group-text">₡</span><input type="text" class="form-control" name="salario" id="salario" value="<?= htmlspecialchars(number_format(floatval($salario), 2, '.', ',')); ?>" <?= $is_edit_mode ? 'readonly' : 'required' ?>></div><small class='form-text text-muted'>Solo se establece al crear.</small></div>
                    <div class="col-md-4 mb-3"><label for="estado_civil" class="form-label">Estado Civil</label><select class="form-select" name="estado_civil" required><option value="">Seleccione...</option><?php foreach ($opciones_estado_civil as $id => $desc): ?><option value="<?= $id; ?>" <?= ($estado_civil_id == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($desc); ?></option><?php endforeach; ?></select><div class="invalid-feedback">Seleccione un estado civil.</div></div>
                    <div class="col-md-6 mb-3"><label for="nacionalidad" class="form-label">Nacionalidad</label><select class="form-select" name="nacionalidad" required><option value="">Seleccione...</option><?php foreach ($opciones_nacionalidad as $id => $desc): ?><option value="<?= $id; ?>" <?= ($nacionalidad_id == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($desc); ?></option><?php endforeach; ?></select><div class="invalid-feedback">Seleccione una nacionalidad.</div></div>
                    <div class="col-md-6 mb-3"><label for="genero" class="form-label">Género</label><select class="form-select" name="genero" required><option value="">Seleccione...</option><?php foreach ($opciones_genero as $id => $desc): ?><option value="<?= $id; ?>" <?= ($genero_id == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($desc); ?></option><?php endforeach; ?></select><div class="invalid-feedback">Seleccione un género.</div></div>
                </div>
                 <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-secondary" data-nav="prev"><i class="bi bi-arrow-left"></i> Anterior</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill me-2"></i><?= $is_edit_mode ? 'Actualizar Persona' : 'Guardar Persona'; ?></button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const steps = document.querySelectorAll('.form-stepper .step');
    const formSteps = document.querySelectorAll('.form-step');
    let currentStep = 1;

    const updateStepper = () => {
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            if (stepNum < currentStep) {
                step.classList.add('completed');
            } else if (stepNum === currentStep) {
                step.classList.add('active');
            }
        });
        formSteps.forEach((formStep, index) => {
            formStep.classList.toggle('active', (index + 1) === currentStep);
        });
    };

    const validateStep = (stepIndex) => {
        const currentFormStep = formSteps[stepIndex - 1];
        let isValid = true;
        
        currentFormStep.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        const inputs = currentFormStep.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (input.hasAttribute('required')) {
                let fieldValid = true;
                const feedbackEl = input.nextElementSibling && input.nextElementSibling.classList.contains('invalid-feedback') 
                    ? input.nextElementSibling 
                    : (input.parentElement.nextElementSibling && input.parentElement.nextElementSibling.classList.contains('invalid-feedback') 
                        ? input.parentElement.nextElementSibling 
                        : null);

                if (!input.checkValidity() || (input.type !== 'date' && input.value.trim() === '')) {
                     fieldValid = false;
                     if (feedbackEl) {
                         if(input.validity.valueMissing || input.value.trim() === '') {
                             feedbackEl.textContent = 'Este campo es requerido.';
                         } else if (input.validity.patternMismatch) {
                             feedbackEl.textContent = 'Formato inválido. Solo se permiten letras.';
                         } else if (input.validity.typeMismatch) {
                             feedbackEl.textContent = 'Por favor, ingrese un correo electrónico válido.';
                         }
                     }
                }
                
                if (input.id === 'fecha_nac' && input.value) {
                    const fechaNac = new Date(input.value);
                    const hoy = new Date();
                    let edad = hoy.getFullYear() - fechaNac.getFullYear();
                    const m = hoy.getMonth() - fechaNac.getMonth();
                    if (m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())) edad--;
                    if (edad < 18) {
                        fieldValid = false;
                        if(feedbackEl) feedbackEl.textContent = 'La persona debe ser mayor de 18 años.';
                    }
                }

                if(!fieldValid){
                    input.classList.add('is-invalid');
                    isValid = false;
                }
            }
        });
        
        return isValid;
    };

    document.querySelectorAll('[data-nav]').forEach(button => {
        button.addEventListener('click', () => {
            const direction = button.dataset.nav;
            if (direction === 'next') {
                if (validateStep(currentStep)) {
                    if(currentStep < steps.length) currentStep++;
                }
            } else if (direction === 'prev') {
                if(currentStep > 1) currentStep--;
            }
            updateStepper();
        });
    });

    const provinciaSelect = document.getElementById('provincia'), cantonSelect = document.getElementById('canton'), distritoSelect = document.getElementById('distrito');
    const initialProvincia = '<?= $provincia_id ?>', initialCanton = '<?= $canton_id ?>', initialDistrito = '<?= $distrito_id ?>';
    let ubicacionesData;
    const cargarCantones = (callback) => {
        const provinciaId = provinciaSelect.value;
        cantonSelect.innerHTML = '<option value="">Seleccione...</option>';
        distritoSelect.innerHTML = '<option value="">Seleccione...</option>';
        if (provinciaId && ubicacionesData.cantones[provinciaId]) {
            for (const [id, nombre] of Object.entries(ubicacionesData.cantones[provinciaId])) cantonSelect.add(new Option(nombre, id));
        }
        if (callback) callback();
    };
    const cargarDistritos = (callback) => {
        const provinciaId = provinciaSelect.value, cantonId = cantonSelect.value;
        distritoSelect.innerHTML = '<option value="">Seleccione...</option>';
        if (provinciaId && cantonId && ubicacionesData.distritos[provinciaId]?.[cantonId]) {
            for (const [id, nombre] of Object.entries(ubicacionesData.distritos[provinciaId][cantonId])) distritoSelect.add(new Option(nombre, id));
        }
        if (callback) callback();
    };
    fetch('js/ubicaciones.json')
        .then(response => response.ok ? response.json() : Promise.reject('Error al cargar ubicaciones.'))
        .then(data => {
            ubicacionesData = data;
            provinciaSelect.innerHTML = '<option value="">Seleccione...</option>';
            for (const [id, nombre] of Object.entries(ubicacionesData.provincias)) provinciaSelect.add(new Option(nombre, id));
            if (initialProvincia) {
                provinciaSelect.value = initialProvincia;
                cargarCantones(() => {
                    if (initialCanton) {
                        cantonSelect.value = initialCanton;
                        cargarDistritos(() => { if (initialDistrito) distritoSelect.value = initialDistrito; });
                    }
                });
            }
        }).catch(error => console.error(error));
    provinciaSelect.addEventListener('change', () => cargarCantones());
    cantonSelect.addEventListener('change', () => cargarDistritos());

    document.getElementById('telefono').addEventListener('input', e => {
        let val = e.target.value.replace(/\D/g, '').slice(0, 8);
        e.target.value = val.length > 4 ? `${val.slice(0, 4)}-${val.slice(4)}` : val;
    });
    document.getElementById('salario').addEventListener('input', e => {
        let val = e.target.value.replace(/[^0-9.]/g, '');
        let parts = val.split('.');
        parts[0] = parts[0].replace(/,/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        e.target.value = parts.length > 1 ? parts[0] + '.' + parts[1].slice(0,2) : parts[0];
    });

    document.getElementById('personaForm').addEventListener('submit', event => {
        if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
            event.preventDefault();
            event.stopPropagation();
            alert('Por favor, corrige los errores marcados en el formulario antes de guardar.');
        }
    });

    updateStepper();
});
</script>
</body>
</html>