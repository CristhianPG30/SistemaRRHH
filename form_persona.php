<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

include 'db.php';

// --- Acceso para Administrador (1) y Recursos Humanos (4) ---
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = "<div class='alert alert-{$_SESSION['flash_message']['type']} alert-dismissible fade show' role='alert'>
                            {$_SESSION['flash_message']['message']}
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                          </div>";
    unset($_SESSION['flash_message']);
}

// --- Inicialización de Variables ---
$is_edit_mode = false;
$idPersona = ''; $nombre = ''; $apellido1 = ''; $apellido2 = ''; $cedula = ''; $fecha_nac = '';
$correo_electronico = ''; $direccion_exacta = ''; $estado_civil_id = '';
$nacionalidad_id = ''; $genero_id = ''; $telefono = ''; $departamento_id = '';
$provincia_id = ''; $canton_id = ''; $distrito_id = '';
$salario_bruto = ''; $cantidad_hijos = '0'; $fecha_ingreso = '';
$page_title = 'Agregar Nueva Persona';

// --- Datos para Selectores ---
$opciones_estado_civil = [1 => 'Soltero(a)', 2 => 'Casado(a)', 3 => 'Divorciado(a)', 4 => 'Viudo(a)', 5 => 'Unión Libre'];
$opciones_genero = [1 => 'Masculino', 2 => 'Femenino', 3 => 'Prefiero no indicar'];
$nacionalidades_db = $conn->query("SELECT idNacionalidad, Descripcion FROM nacionalidad ORDER BY Descripcion");
$departamentos = $conn->query("SELECT idDepartamento, nombre FROM departamento JOIN estado_cat ON departamento.id_estado_fk = estado_cat.idEstado WHERE estado_cat.Descripcion = 'Activo' ORDER BY nombre");
$jefes_activos = $conn->query("SELECT c.idColaborador, p.Nombre, p.Apellido1, p.Apellido2 FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.activo = 1 ORDER BY p.Nombre, p.Apellido1");

// --- Lógica de Edición (Cargar Datos Existentes) ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit_mode = true;
    $idPersona = intval($_GET['id']);
    $page_title = 'Editar Información de Persona';

    $sql = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Apellido2, p.Cedula, p.Fecha_nac, p.id_estado_civil_fk,
                   p.id_nacionalidad_fk, p.id_genero_cat_fk, p.cantidad_hijos,
                   c.id_departamento_fk, c.salario_bruto, c.fecha_ingreso, d.Dir_exacta, d.Provincias_idProvincias,
                   d.Cantones_idCanton, d.Distritos_idDistritos, t.numero AS Numero_de_Telefono, pc.Correo
            FROM persona p
            LEFT JOIN colaborador c ON p.idPersona = c.id_persona_fk
            LEFT JOIN direccion d ON p.id_direccion_fk = d.idDireccion
            LEFT JOIN persona_telefonos pt ON p.idPersona = pt.id_persona_fk
            LEFT JOIN telefono t ON pt.id_telefono_fk = t.id_Telefono
            LEFT JOIN persona_correos pc ON p.idPersona = pc.IdPersona_fk
            WHERE p.idPersona = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idPersona);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $nombre = $data['Nombre']; $apellido1 = $data['Apellido1']; $apellido2 = $data['Apellido2'];
        $cedula = $data['Cedula']; $fecha_nac = $data['Fecha_nac'];
        $estado_civil_id = $data['id_estado_civil_fk']; $nacionalidad_id = $data['id_nacionalidad_fk']; $genero_id = $data['id_genero_cat_fk'];
        $correo_electronico = $data['Correo']; $telefono = $data['Numero_de_Telefono'];
        $direccion_exacta = $data['Dir_exacta'];
        $provincia_id = $data['Provincias_idProvincias'];
        $canton_id = $data['Cantones_idCanton'];
        $distrito_id = $data['Distritos_idDistritos'];
        $departamento_id = $data['id_departamento_fk'];
        $salario_bruto = $data['salario_bruto'] ?? '';
        $cantidad_hijos = $data['cantidad_hijos'] ?? '0';
        $fecha_ingreso = $data['fecha_ingreso'] ? date('Y-m-d', strtotime($data['fecha_ingreso'])) : '';
    }
    $stmt->close();
}

// --- Procesamiento del Formulario (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_regenerate_id(true);
    $idPersona = isset($_POST['idPersona']) ? intval($_POST['idPersona']) : 0;
    $is_edit_mode = ($idPersona > 0);

    // Recolección y limpieza de datos...
    $nombre = trim($_POST['nombre']);
    $apellido1 = trim($_POST['apellido1']);
    $apellido2 = trim($_POST['apellido2']);
    $cedula_post = trim($_POST['cedula']);
    $fecha_nac = $_POST['fecha_nac'];
    $correo_electronico_post = trim($_POST['correo_electronico']);
    $telefono_post = trim($_POST['telefono']);
    $provincia_id_post = intval($_POST['provincia']);
    $canton_id_post = intval($_POST['canton']);
    $distrito_id_post = intval($_POST['distrito']);
    $direccion_exacta_post = trim($_POST['direccion_exacta']);
    $departamento_id_post = intval($_POST['departamento']);
    $estado_civil_post = intval($_POST['estado_civil']);
    $nacionalidad_post = intval($_POST['nacionalidad']);
    $genero_post = intval($_POST['genero']);
    $id_jefe_fk = isset($_POST['id_jefe_fk']) ? intval($_POST['id_jefe_fk']) : null;
    $salario_bruto_post = isset($_POST['salario_bruto']) ? floatval($_POST['salario_bruto']) : 0.0;
    $cantidad_hijos_post = isset($_POST['cantidad_hijos']) ? intval($_POST['cantidad_hijos']) : 0;
    $fecha_ingreso_post = trim($_POST['fecha_ingreso']);

    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono_post);
    $cedula_limpia = preg_replace('/[^a-zA-Z0-9]/', '', $cedula_post);

    // --- Validaciones del Servidor ---
    $errors = [];
    if (empty($nombre) || !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $nombre)) $errors[] = "El nombre es inválido.";
    if (empty($apellido1) || !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $apellido1)) $errors[] = "El primer apellido es inválido.";
    if (empty($apellido2) || !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u", $apellido2)) $errors[] = "El segundo apellido es inválido.";
    if (empty($cedula_limpia)) $errors[] = "El campo de identificación es obligatorio.";
    if (!preg_match('/^[245678]\d{7}$/', $telefono_limpio)) $errors[] = "El teléfono debe tener 8 dígitos y empezar con 2, 4, 5, 6, 7 u 8.";
    if (empty($correo_electronico_post) || !filter_var($correo_electronico_post, FILTER_VALIDATE_EMAIL)) $errors[] = "El correo electrónico es inválido.";
    if (empty($fecha_nac) || (new DateTime())->diff(new DateTime($fecha_nac))->y < 18) $errors[] = "La persona debe ser mayor de 18 años.";
    if (empty($fecha_ingreso_post)) $errors[] = "La fecha de ingreso es obligatoria.";
    if (intval($cantidad_hijos_post) < 0) $errors[] = "La cantidad de hijos no puede ser un valor negativo.";
    if (empty($provincia_id_post) || empty($canton_id_post) || empty($distrito_id_post)) $errors[] = "Debe seleccionar la ubicación completa.";
    if ($salario_bruto_post < 0) $errors[] = "El salario bruto no puede ser un valor negativo.";
    if (!$is_edit_mode && empty($id_jefe_fk)) $errors[] = "Debe seleccionar un jefe para el nuevo colaborador.";
    
    // Lógica de Redirección
    if (!empty($errors)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $conn->begin_transaction();
        try {
            // Dirección
            $stmt_dir = $conn->prepare("SELECT id_direccion_fk FROM persona WHERE idPersona = ?");
            $stmt_dir->bind_param("i", $idPersona); $stmt_dir->execute(); $result_dir = $stmt_dir->get_result();
            $direccion_id = ($result_dir->num_rows > 0) ? $result_dir->fetch_assoc()['id_direccion_fk'] : null;
            $stmt_dir->close();

            if ($direccion_id) { $stmt = $conn->prepare("UPDATE direccion SET Dir_exacta=?, Provincias_idProvincias=?, Cantones_idCanton=?, Distritos_idDistritos=? WHERE idDireccion=?"); $stmt->bind_param("siiii", $direccion_exacta_post, $provincia_id_post, $canton_id_post, $distrito_id_post, $direccion_id);
            } else { $stmt = $conn->prepare("INSERT INTO direccion (Dir_exacta, Provincias_idProvincias, Cantones_idCanton, Distritos_idDistritos) VALUES (?, ?, ?, ?)"); $stmt->bind_param("siii", $direccion_exacta_post, $provincia_id_post, $canton_id_post, $distrito_id_post); }
            $stmt->execute();
            if (!$direccion_id) $direccion_id = $stmt->insert_id;
            $stmt->close();

            // Teléfono
            $stmt_tel = $conn->prepare("SELECT id_Telefono FROM telefono WHERE numero = ?");
            $stmt_tel->bind_param("s", $telefono_limpio); $stmt_tel->execute(); $tel_res = $stmt_tel->get_result();
            $telefono_id = ($tel_res->num_rows > 0) ? $tel_res->fetch_assoc()['id_Telefono'] : null;
            if(!$telefono_id && !empty($telefono_limpio)) { $stmt_insert_tel = $conn->prepare("INSERT INTO telefono (numero) VALUES (?)"); $stmt_insert_tel->bind_param("s", $telefono_limpio); $stmt_insert_tel->execute(); $telefono_id = $stmt_insert_tel->insert_id; $stmt_insert_tel->close(); }
            $stmt_tel->close();

            // Persona
            if ($is_edit_mode) {
                $sql = "UPDATE persona SET Nombre=?, Apellido1=?, Apellido2=?, Cedula=?, Fecha_nac=?, id_direccion_fk=?, id_estado_civil_fk=?, id_nacionalidad_fk=?, id_genero_cat_fk=?, cantidad_hijos=? WHERE idPersona=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssiiiiii", $nombre, $apellido1, $apellido2, $cedula_limpia, $fecha_nac, $direccion_id, $estado_civil_post, $nacionalidad_post, $genero_post, $cantidad_hijos_post, $idPersona);
            } else {
                $sql = "INSERT INTO persona (Nombre, Apellido1, Apellido2, Cedula, Fecha_nac, id_direccion_fk, id_estado_civil_fk, id_nacionalidad_fk, id_genero_cat_fk, cantidad_hijos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssiiiii", $nombre, $apellido1, $apellido2, $cedula_limpia, $fecha_nac, $direccion_id, $estado_civil_post, $nacionalidad_post, $genero_post, $cantidad_hijos_post);
            }
            $stmt->execute();
            $current_persona_id = $is_edit_mode ? $idPersona : $stmt->insert_id;
            $stmt->close();

            // Correo y Teléfono (Tablas Pivote)
            $conn->execute_query("DELETE FROM persona_correos WHERE IdPersona_fk=?", [$current_persona_id]);
            $conn->execute_query("INSERT INTO persona_correos (Correo, IdPersona_fk) VALUES (?,?)", [$correo_electronico_post, $current_persona_id]);
            $conn->execute_query("DELETE FROM persona_telefonos WHERE id_persona_fk=?", [$current_persona_id]);
            if($telefono_id) $conn->execute_query("INSERT INTO persona_telefonos (id_persona_fk, id_telefono_fk) VALUES (?,?)", [$current_persona_id, $telefono_id]);

            // Colaborador
            $stmt_col_check = $conn->prepare("SELECT idColaborador FROM colaborador WHERE id_persona_fk = ?");
            $stmt_col_check->bind_param("i", $current_persona_id); $stmt_col_check->execute(); $col_res = $stmt_col_check->get_result();
            if ($col_res->num_rows > 0) {
                $conn->execute_query("UPDATE colaborador SET id_departamento_fk=?, salario_bruto=?, fecha_ingreso=? WHERE id_persona_fk=?", [$departamento_id_post, $salario_bruto_post, $fecha_ingreso_post, $current_persona_id]);
            } else {
                $conn->execute_query("INSERT INTO colaborador (id_persona_fk, activo, fecha_ingreso, id_departamento_fk, id_jefe_fk, salario_bruto) VALUES (?, 1, ?, ?, ?, ?)", [$current_persona_id, $fecha_ingreso_post, $departamento_id_post, $id_jefe_fk, $salario_bruto_post]);
            }
            $stmt_col_check->close();

            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Operación realizada con éxito!'];
            header('Location: personas.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error al guardar los datos: ' . $e->getMessage()];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?> - Sistema RRHH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .form-stepper { display: flex; justify-content: space-between; width: 100%; margin: 2rem 0; }
        .form-stepper .step { display: flex; flex-direction: column; align-items: center; position: relative; flex-grow: 1; cursor: pointer; }
        .form-stepper .step .step-circle { width: 40px; height: 40px; border-radius: 50%; background-color: #d1d9e6; color: #858796; display: flex; align-items: center; justify-content: center; font-weight: 600; transition: all 0.3s; z-index: 2; border: 3px solid #d1d9e6; }
        .form-stepper .step .step-title { font-size: 0.85rem; margin-top: 0.5rem; color: #858796; font-weight: 500; text-align: center; }
        .form-stepper .step.active .step-circle { background-color: #4e73df; color: white; border-color: #4e73df; }
        .form-stepper .step.active .step-title { color: #4e73df; font-weight: 600; }
        .form-stepper .step.completed .step-circle { background-color: #1cc88a; color: white; border-color: #1cc88a; }
        .form-stepper .step.completed .step-title { color: #1cc88a; }
        .form-stepper .step::after { content: ''; position: absolute; top: 18px; left: 50%; width: 100%; height: 4px; background-color: #d1d9e6; z-index: 1; }
        .form-stepper .step:last-child::after { display: none; }
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .btn-primary { background-color: #4e73df; border-color: #4e73df; }
        .btn-secondary { background-color: #858796; border-color: #858796; }
        .form-label.required::after { content: " *"; color: red; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="container my-4">
            <div class="text-center mb-4">
                <h1 class="h3 mb-1 text-gray-800" style="font-weight: 600;"><?= htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Completa la información en cada paso para continuar.</p>
            </div>
            <?= $flash_message ?>

            <div class="form-stepper">
                <div class="step active" data-step-target="1"><div class="step-circle">1</div><div class="step-title">Personal</div></div>
                <div class="step" data-step-target="2"><div class="step-circle">2</div><div class="step-title">Contacto</div></div>
                <div class="step" data-step-target="3"><div class="step-circle">3</div><div class="step-title">Laboral</div></div>
            </div>

            <form id="personaForm" action="form_persona.php<?= $is_edit_mode ? '?id='.$idPersona : '' ?>" method="POST" novalidate>
                <input type="hidden" name="idPersona" value="<?= htmlspecialchars($idPersona); ?>">

                <div class="card p-4">
                    <div id="validation-alert" class="alert alert-danger d-none" role="alert"></div>

                    <div class="form-step active" data-step-content="1">
                        <h5 class="mb-4">Paso 1: Información Personal</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="nombre" class="form-label required">Nombre</label><input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($nombre); ?>" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" title="El nombre solo puede contener letras y espacios." required></div>
                            <div class="col-md-4 mb-3"><label for="apellido1" class="form-label required">Primer Apellido</label><input type="text" class="form-control" name="apellido1" value="<?= htmlspecialchars($apellido1); ?>" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" title="El apellido solo puede contener letras y espacios." required></div>
                            <div class="col-md-4 mb-3"><label for="apellido2" class="form-label required">Segundo Apellido</label><input type="text" class="form-control" name="apellido2" value="<?= htmlspecialchars($apellido2); ?>" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" title="El apellido solo puede contener letras y espacios." required></div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="nacionalidad" class="form-label required">Nacionalidad</label>
                                <select class="form-select" id="nacionalidad" name="nacionalidad" required>
                                    <option value="">Seleccione...</option>
                                    <?php
                                    if ($nacionalidades_db && $nacionalidades_db->num_rows > 0) {
                                        mysqli_data_seek($nacionalidades_db, 0);
                                        while ($nac = $nacionalidades_db->fetch_assoc()):
                                            $descripcion_lower = strtolower($nac['Descripcion']);
                                            $tipo_doc = 'Pasaporte';

                                            if (strpos($descripcion_lower, 'costarricense') !== false) {
                                                $tipo_doc = 'Cedula';
                                            } elseif (strpos($descripcion_lower, 'extranjero residente') !== false) {
                                                $tipo_doc = 'DIMEX';
                                            }
                                    ?>
                                            <option value="<?= $nac['idNacionalidad']; ?>"
                                                    data-tipo-doc="<?= $tipo_doc; ?>"
                                                    <?= ($nacionalidad_id == $nac['idNacionalidad']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($nac['Descripcion']); ?>
                                            </option>
                                    <?php
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3"><label for="cedula" id="label-cedula" class="form-label required">Identificación</label><input type="text" class="form-control" id="cedula" name="cedula" value="<?= htmlspecialchars($cedula); ?>" title="Seleccione una nacionalidad primero." required></div>
                            <div class="col-md-4 mb-3"><label for="fecha_nac" class="form-label required">Fecha de Nacimiento</label><input type="date" class="form-control" name="fecha_nac" value="<?= htmlspecialchars($fecha_nac); ?>" required></div>

                            <div class="col-md-6 mb-3"><label for="cantidad_hijos" class="form-label required">Cantidad de Hijos</label><input type="number" class="form-control" name="cantidad_hijos" value="<?= htmlspecialchars($cantidad_hijos); ?>" min="0" required></div>
                            <div class="col-md-6 mb-3"><label for="genero" class="form-label required">Género</label><select class="form-select" name="genero" required><option value="">Seleccione...</option><?php foreach ($opciones_genero as $id => $desc): ?><option value="<?= $id; ?>" <?= ($genero_id == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($desc); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <a href="personas.php" class="btn btn-light me-2">Cancelar</a>
                            <button type="button" class="btn btn-primary" data-nav="next">Siguiente <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="form-step" data-step-content="2">
                        <h5 class="mb-4">Paso 2: Contacto y Ubicación</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="correo_electronico" class="form-label required">Correo Electrónico</label><input type="email" class="form-control" name="correo_electronico" value="<?= htmlspecialchars($correo_electronico); ?>" required></div>
                            <div class="col-md-6 mb-3"><label for="telefono" class="form-label required">Teléfono</label><input type="tel" class="form-control" id="telefono" name="telefono" placeholder="8888-8888" value="<?= htmlspecialchars($telefono); ?>" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="provincia" class="form-label required">Provincia</label><select class="form-select" id="provincia" name="provincia" required></select></div>
                            <div class="col-md-4 mb-3"><label for="canton" class="form-label required">Cantón</label><select class="form-select" id="canton" name="canton" required></select></div>
                            <div class="col-md-4 mb-3"><label for="distrito" class="form-label required">Distrito</label><select class="form-select" id="distrito" name="distrito" required></select></div>
                        </div>
                        <div class="mb-3"><label for="direccion_exacta" class="form-label required">Dirección Exacta</label><textarea class="form-control" name="direccion_exacta" rows="2" required><?= htmlspecialchars($direccion_exacta); ?></textarea></div>
                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" class="btn btn-secondary" data-nav="prev"><i class="bi bi-arrow-left"></i> Anterior</button>
                            <button type="button" class="btn btn-primary" data-nav="next">Siguiente <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="form-step" data-step-content="3">
                        <h5 class="mb-4">Paso 3: Datos Laborales y Adicionales</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="departamento" class="form-label required">Departamento</label><select class="form-select" name="departamento" required><option value="">Seleccione...</option><?php if($departamentos && $departamentos->num_rows > 0) { mysqli_data_seek($departamentos, 0); while ($dept = $departamentos->fetch_assoc()): ?><option value="<?= $dept['idDepartamento']; ?>" <?= ($departamento_id == $dept['idDepartamento']) ? 'selected' : ''; ?>><?= htmlspecialchars($dept['nombre']); ?></option><?php endwhile; } ?></select></div>
                            <div class="col-md-6 mb-3"><label for="fecha_ingreso" class="form-label required">Fecha de Ingreso</label><input type="date" class="form-control" name="fecha_ingreso" value="<?= htmlspecialchars($fecha_ingreso); ?>" required></div>

                            <?php if (!$is_edit_mode): ?>
                            <div class="col-md-6 mb-3"><label for="id_jefe_fk" class="form-label required">Jefe Directo</label><select class="form-select" name="id_jefe_fk" required><option value="">Seleccione...</option><?php if($jefes_activos && $jefes_activos->num_rows > 0): mysqli_data_seek($jefes_activos, 0); while ($jefe = $jefes_activos->fetch_assoc()): ?><option value="<?= $jefe['idColaborador']; ?>"><?= htmlspecialchars($jefe['Nombre'] . ' ' . $jefe['Apellido1'] . ' ' . $jefe['Apellido2']); ?></option><?php endwhile; endif; ?></select></div>
                            <?php endif; ?>

                            <div class="col-md-6 mb-3">
                                <label for="salario_bruto" class="form-label required">Salario Bruto (₡)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="salario_bruto" value="<?= htmlspecialchars($salario_bruto); ?>" required title="El salario no puede ser negativo.">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="horario" class="form-label">Horario</label>
                                <select class="form-select" id="horario" name="horario" disabled>
                                    <option value="1" selected>Diurno Regular (8:00 AM - 5:00 PM)</option>
                                </select>
                                <div class="form-text">Horario estándar para todos los colaboradores.</div>
                            </div>
                            <div class="col-md-6 mb-3"><label for="estado_civil" class="form-label required">Estado Civil</label><select class="form-select" name="estado_civil" required><option value="">Seleccione...</option><?php foreach ($opciones_estado_civil as $id => $desc): ?><option value="<?= $id; ?>" <?= ($estado_civil_id == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($desc); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" class="btn btn-secondary" data-nav="prev"><i class="bi bi-arrow-left"></i> Anterior</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill me-2"></i><?= $is_edit_mode ? 'Actualizar Persona' : 'Guardar Persona'; ?></button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const stepper = document.querySelector('.form-stepper');
        const formSteps = document.querySelectorAll('.form-step');
        const alertContainer = document.getElementById('validation-alert');
        let currentStep = 1;

        const nacionalidadSelect = document.getElementById('nacionalidad');
        const cedulaInput = document.getElementById('cedula');
        const cedulaLabel = document.getElementById('label-cedula');

        function updateCedulaValidation(isInitialLoad = false) {
            const selectedOption = nacionalidadSelect.options[nacionalidadSelect.selectedIndex];
            const tipoDocumento = selectedOption ? selectedOption.dataset.tipoDoc : null;

            if (!isInitialLoad) {
                cedulaInput.value = '';
            }

            switch(tipoDocumento) {
                case 'Cedula':
                    cedulaLabel.innerHTML = 'Cédula <span class="text-danger">*</span>';
                    cedulaInput.pattern = '^\\d{9}$';
                    cedulaInput.title = 'La cédula debe contener exactamente 9 dígitos.';
                    cedulaInput.placeholder = '123456789';
                    cedulaInput.maxLength = 9;
                    break;
                case 'DIMEX':
                    cedulaLabel.innerHTML = 'DIMEX <span class="text-danger">*</span>';
                    cedulaInput.pattern = '^\\d{11,12}$';
                    cedulaInput.title = 'El DIMEX debe contener entre 11 y 12 dígitos.';
                    cedulaInput.placeholder = '12345678901';
                    cedulaInput.maxLength = 12;
                    break;
                case 'Pasaporte':
                    cedulaLabel.innerHTML = 'Pasaporte <span class="text-danger">*</span>';
                    cedulaInput.pattern = '^[a-zA-Z0-9]{6,20}$';
                    cedulaInput.title = 'El pasaporte debe contener de 6 a 20 caracteres alfanuméricos.';
                    cedulaInput.placeholder = 'A1B2C3D4E5';
                    cedulaInput.maxLength = 20;
                    break;
                default:
                    cedulaLabel.innerHTML = 'Identificación <span class="text-danger">*</span>';
                    cedulaInput.removeAttribute('pattern');
                    cedulaInput.removeAttribute('maxLength');
                    cedulaInput.title = 'Seleccione una nacionalidad primero.';
                    cedulaInput.placeholder = '';
            }
        }
        
        nacionalidadSelect.addEventListener('change', () => updateCedulaValidation(false));
        updateCedulaValidation(true);

        function goToStep(stepNumber) {
            alertContainer.classList.add('d-none');
            currentStep = stepNumber;
            formSteps.forEach(step => step.classList.remove('active'));
            document.querySelector(`[data-step-content="${currentStep}"]`).classList.add('active');
            stepper.querySelectorAll('.step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index + 1 < currentStep) step.classList.add('completed');
                if (index + 1 === currentStep) step.classList.add('active');
            });
        }

        function validateStep(stepIndex) {
            const currentFormStep = document.querySelector(`[data-step-content="${stepIndex}"]`);
            let isValid = true;
            let errorMessages = [];
            currentFormStep.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
                input.classList.remove('is-invalid');
                let hasError = false;
                let errorMessage = '';
                const label = input.closest('.mb-3').querySelector('label')?.innerText.replace(' *', '') || 'Un campo';
                
                let valueToTest = input.value.trim();
                if (input.name === 'cedula' || input.name === 'telefono') {
                    valueToTest = valueToTest.replace(/[^a-zA-Z0-9]/g, '');
                }

                if (!valueToTest && input.required) {
                    hasError = true;
                    errorMessage = `${label} es un campo obligatorio.`;
                } else if (valueToTest) {
                    if (input.hasAttribute('pattern') && !new RegExp(input.pattern).test(valueToTest)) {
                        hasError = true;
                        errorMessage = input.getAttribute('title') || `El formato para ${label} es incorrecto.`;
                    }
                    if (input.type === 'email' && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/.test(input.value)) {
                         hasError = true;
                         errorMessage = 'Ingrese un correo electrónico válido.';
                    }
                    if (input.type === 'number' && input.hasAttribute('min') && parseFloat(valueToTest) < parseFloat(input.min)) {
                        hasError = true;
                        errorMessage = input.getAttribute('title') || `${label} no puede ser un valor negativo.`;
                    }
                    if (input.name === 'fecha_nac' && valueToTest) {
                        const birthDate = new Date(valueToTest);
                        birthDate.setMinutes(birthDate.getMinutes() + birthDate.getTimezoneOffset());
                        const today = new Date();
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const m = today.getMonth() - birthDate.getMonth();
                        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        if (age < 18) {
                            hasError = true;
                            errorMessage = 'La persona debe ser mayor de 18 años.';
                        }
                    }
                }
                if (hasError) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!errorMessages.includes(errorMessage)) {
                        errorMessages.push(errorMessage);
                    }
                }
            });
            if (!isValid) {
                alertContainer.innerHTML = '<strong>Por favor, corrija los siguientes errores:</strong><br>' + errorMessages.join('<br>');
                alertContainer.classList.remove('d-none');
            } else {
                alertContainer.classList.add('d-none');
            }
            return isValid;
        }

        document.querySelectorAll('[data-nav]').forEach(button => {
            button.addEventListener('click', () => {
                const direction = button.dataset.nav === 'next' ? 1 : -1;
                if (direction === 1 && !validateStep(currentStep)) return;
                const nextStep = currentStep + direction;
                if (nextStep > 0 && nextStep <= formSteps.length) {
                    goToStep(nextStep);
                }
            });
        });

        stepper.querySelectorAll('.step').forEach(step_el => {
            const targetStep = parseInt(step_el.dataset.stepTarget);
            step_el.addEventListener('click', () => {
                let canNavigate = true;
                for (let i = 1; i < targetStep; i++) {
                    if (!validateStep(i)) {
                        canNavigate = false;
                        goToStep(i);
                        break;
                    }
                }
                if(canNavigate) goToStep(targetStep);
            });
        });

        const phoneInput = document.getElementById('telefono');
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.slice(0, 4) + '-' + value.slice(4, 8);
            }
            e.target.value = value;
        });
        
        const telefonoInput = document.getElementById('telefono');
        telefonoInput.pattern = '^[245678]\\d{7}$';
        telefonoInput.title = 'El teléfono debe tener 8 dígitos y empezar con 2, 4, 5, 6, 7 u 8.';

        const emailInput = document.querySelector('input[name="correo_electronico"]');
        emailInput.pattern = '[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,4}$';
        emailInput.title = 'Ingrese un correo electrónico válido.';

        const provinciaSelect = document.getElementById('provincia');
        const cantonSelect = document.getElementById('canton');
        const distritoSelect = document.getElementById('distrito');
        const initialData = { provincia: '<?= $provincia_id ?? "" ?>', canton: '<?= $canton_id ?? "" ?>', distrito: '<?= $distrito_id ?? "" ?>' };
        let ubicacionesData = {};

        function populateSelect(select, items, selectedId) {
            select.innerHTML = '<option value="">Seleccione...</option>';
            for (const id in items) { select.add(new Option(items[id], id)); }
            if (selectedId) { select.value = selectedId; }
        }

        fetch('js/ubicaciones.json').then(r => r.json()).then(data => {
            ubicacionesData = data;
            populateSelect(provinciaSelect, data.provincias, initialData.provincia);
            if (initialData.provincia) provinciaSelect.dispatchEvent(new Event('change'));
        });

        provinciaSelect.addEventListener('change', () => {
            const cantones = ubicacionesData.cantones[provinciaSelect.value] || {};
            populateSelect(cantonSelect, cantones, initialData.canton);
            if (initialData.canton) { cantonSelect.dispatchEvent(new Event('change')); initialData.canton = null; }
            else { distritoSelect.innerHTML = '<option value="">Seleccione...</option>'; }
        });

        cantonSelect.addEventListener('change', () => {
            const distritos = ubicacionesData.distritos[provinciaSelect.value]?.[cantonSelect.value] || {};
            populateSelect(distritoSelect, distritos, initialData.distrito);
            if (initialData.distrito) initialData.distrito = null;
        });

        document.getElementById('personaForm').addEventListener('submit', event => {
            if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
    </script>
</body>
</html>