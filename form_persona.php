<?php  
// Iniciar la sesión y conectar a la base de datos
session_start();
include 'db.php'; // Asegúrate de tener tu archivo db.php configurado correctamente

// Variables iniciales
$idPersona = '';
$nombre = '';
$apellido1 = '';
$apellido2 = '';
$cedula = '';
$fecha_nac = '';
$salario = '';
$correo_electronico = ''; // Inicializamos la variable de correo electrónico
$direccion_exacta = ''; // Inicializamos la variable de dirección
$estado_civil = '';
$nacionalidad = '';
$genero = '';
$telefono = '';
$departamento_id = '';
$provincia_id = '';
$canton_id = '';
$distrito_id = '';

// Consultas para obtener datos relacionados
$estados_civiles = $conn->query("SELECT * FROM estado_civil");
$nacionalidades = $conn->query("SELECT * FROM nacionalidad");
$generos = $conn->query("SELECT * FROM genero_cat");
$departamentos = $conn->query("SELECT * FROM departamento");

// Verificar si es edición (si hay un ID en la URL)
if (isset($_GET['id'])) {
    $idPersona = $_GET['id'];
    $result = $conn->query("
        SELECT p.*, p.correo_electronico, d.Dir_exacta, t.Numero_de_Telefono, d.Provincias_idProvincias, d.Cantones_idCanton, d.Distritos_idDistritos 
        FROM persona p 
        LEFT JOIN direccion d ON p.Direccion_idDireccion = d.idDireccion 
        LEFT JOIN telefono t ON p.Telefono_id_Telefono = t.id_Telefono 
        WHERE p.idPersona = $idPersona");
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $nombre = $row['Nombre'];
        $apellido1 = $row['Apellido1'];
        $apellido2 = $row['Apellido2'];
        $cedula = $row['Cedula'];
        $fecha_nac = $row['Fecha_nac'];
        $salario = $row['Salario_bruto'];
        $correo_electronico = $row['correo_electronico']; // Obtenemos el correo electrónico
        $direccion_exacta = $row['Dir_exacta']; // Obtenemos la dirección actual
        $estado_civil = $row['Estado_civil_idEstado_civil'];
        $nacionalidad = $row['Nacionalidad_idNacionalidad'];
        $genero = $row['Genero_cat_idGenero_cat'];
        $telefono = $row['Numero_de_Telefono']; // Obtenemos el número de teléfono real
        $departamento_id = $row['Departamento_idDepartamento'];
        $provincia_id = $row['Provincias_idProvincias'];
        $canton_id = $row['Cantones_idCanton'];
        $distrito_id = $row['Distritos_idDistritos'];
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $apellido1 = $_POST['apellido1'];
    $apellido2 = $_POST['apellido2'];
    $cedula = $_POST['cedula'];
    $fecha_nac = $_POST['fecha_nac'];
    $salario =  $_POST['salario']; // Remover comas antes de guardar
    $correo_electronico = $_POST['correo_electronico']; // Añadido campo de correo electrónico
    $direccion_exacta = $_POST['direccion_exacta'];
    $estado_civil = $_POST['estado_civil'];
    $nacionalidad = $_POST['nacionalidad'];
    $genero = $_POST['genero'];
    $departamento_id = $_POST['departamento'];
    $provincia_id = $_POST['provincia'];
    $canton_id = $_POST['canton'];
    $distrito_id = $_POST['distrito'];

// Validación de nombre y apellidos para evitar números o símbolos
if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $nombre) || !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellido1) || !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellido2)) {
    echo "<script>alert('El nombre y los apellidos no deben contener números ni símbolos.'); window.history.back();</script>";
    die();
}


    // Validación de edad (mayor de 18 años)
    $fecha_nacimiento = new DateTime($fecha_nac);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nacimiento)->y;

   // Validación de edad
if ($edad < 18) {
    echo "<script>alert('La persona debe ser mayor de 18 años.'); window.history.back();</script>";
    die();
}

// Validación de correo electrónico
if (empty($correo_electronico) || !filter_var($correo_electronico, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Por favor, ingrese un correo electrónico válido.'); window.history.back();</script>";
    die();
}


    // Manejo del teléfono
    $telefono = $_POST['telefono'];
    $descripcion_telefono = isset($_POST['descripcion_telefono']) ? $_POST['descripcion_telefono'] : '';

    // Verificar si ya existe un teléfono asociado a esta persona
    $telefono_actual = $conn->query("SELECT Telefono_id_Telefono FROM persona WHERE idPersona = '$idPersona'")->fetch_assoc();
    $telefono_id_actual = $telefono_actual['Telefono_id_Telefono'];

    // Si existe un teléfono asociado, lo actualizamos, sino lo insertamos
    if (!empty($telefono_id_actual)) {
        // Actualizamos el número de teléfono
        $conn->query("UPDATE telefono SET Numero_de_Telefono = '$telefono', Descripcion_Telefono = '$descripcion_telefono' WHERE id_Telefono = '$telefono_id_actual'");
        $telefono_id = $telefono_id_actual;
    } else {
        // Insertamos un nuevo teléfono
        $conn->query("INSERT INTO telefono (Numero_de_Telefono, Descripcion_Telefono) VALUES ('$telefono', '$descripcion_telefono')");
        $telefono_id = $conn->insert_id;
    }

    // Manejo de la dirección
    $direccion_actual = $conn->query("SELECT Direccion_idDireccion FROM persona WHERE idPersona = '$idPersona'")->fetch_assoc();
    $direccion_id_actual = $direccion_actual['Direccion_idDireccion'];

    if (!empty($direccion_id_actual)) {
        // Actualizamos la dirección
        $conn->query("UPDATE direccion SET Dir_exacta = '$direccion_exacta', Provincias_idProvincias = '$provincia_id', Cantones_idCanton = '$canton_id', Distritos_idDistritos = '$distrito_id' WHERE idDireccion = '$direccion_id_actual'");
        $direccion_id = $direccion_id_actual;
    } else {
        // Insertamos una nueva dirección
        $conn->query("INSERT INTO direccion (Dir_exacta, Provincias_idProvincias, Cantones_idCanton, Distritos_idDistritos) VALUES ('$direccion_exacta', '$provincia_id', '$canton_id', '$distrito_id')");
        $direccion_id = $conn->insert_id;
    }

    // Insertar o actualizar persona
    if (!empty($idPersona)) {
        $query = "UPDATE persona SET 
            Nombre = '$nombre', Apellido1 = '$apellido1', Apellido2 = '$apellido2', 
            Cedula = '$cedula', Fecha_nac = '$fecha_nac', Salario_bruto = '$salario',
            Correo_electronico = '$correo_electronico', 
            Direccion_idDireccion = '$direccion_id', Estado_civil_idEstado_civil = '$estado_civil', 
            Nacionalidad_idNacionalidad = '$nacionalidad', Genero_cat_idGenero_cat = '$genero', 
            Telefono_id_Telefono = '$telefono_id', Departamento_idDepartamento = '$departamento_id'
            WHERE idPersona = '$idPersona'";
        $conn->query($query);
    } else {
        // Evitar que se inserte dos veces, redirigiendo después de la primera inserción
        $query = "INSERT INTO persona 
            (Nombre, Apellido1, Apellido2, Cedula, Fecha_nac, Salario_bruto, Correo_electronico, Direccion_idDireccion, 
            Estado_civil_idEstado_civil, Nacionalidad_idNacionalidad, Genero_cat_idGenero_cat, 
            Telefono_id_Telefono, Departamento_idDepartamento) 
            VALUES ('$nombre', '$apellido1', '$apellido2', '$cedula', '$fecha_nac', '$salario', '$correo_electronico', 
            '$direccion_id', '$estado_civil', '$nacionalidad', '$genero', '$telefono_id', '$departamento_id')";

        if ($conn->query($query)) {
            $idPersona = $conn->insert_id;

            // Insertar el colaborador automáticamente y activarlo
            $queryColaborador = "INSERT INTO colaborador (Persona_idPersona, activo, Fechadeingreso) 
                                 VALUES ($idPersona, 1, NOW())";
            $conn->query($queryColaborador);

            // Redirigir inmediatamente después de la inserción para evitar duplicados
            header('Location: personas.php?success=added');
            exit;
        }
    }

    // Redirigir inmediatamente después de la actualización para evitar duplicados
    if ($conn->query($query)) {
        header('Location: personas.php?success=updated');
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo empty($idPersona) ? 'Agregar Persona' : 'Editar Persona'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-size: 1.2em;
            text-align: center;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .btn-custom {
            background-color: #28a745;
            color: white;
            border-radius: 5px;
        }
        .btn-custom:hover {
            background-color: #218838;
        }
        .form-label {
            font-weight: bold;
        }

    </style>
</head>
<body>

<?php include 'header.php'; ?>

    <!-- Formulario para agregar/editar persona -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <?php echo empty($idPersona) ? 'Agregar Persona' : 'Editar Persona'; ?>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">Nombre</label>
                                        <input type="text" class="form-control" name="nombre" value="<?php echo $nombre; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido1" class="form-label">Primer Apellido</label>
                                        <input type="text" class="form-control" name="apellido1" value="<?php echo $apellido1; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="apellido2" class="form-label">Segundo Apellido</label>
                                        <input type="text" class="form-control" name="apellido2" value="<?php echo $apellido2; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="correo_electronico" class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" name="correo_electronico" value="<?php echo $correo_electronico; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cedula" class="form-label">Cédula</label>
                                <input type="text" class="form-control" name="cedula" id="cedula" maxlength="11" placeholder="*-****-****" value="<?php echo $cedula; ?>"  required>
                                <div class="invalid-feedback">Formato de cédula incorrecto. Use *-****-****.</div>
                            </div>
                        </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fecha_nac" class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" name="fecha_nac" value="<?php echo $fecha_nac; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="salario" class="form-label">Salario base</label>
                                        <input type="text" class="form-control" name="salario" id="salario" value="<?php echo $salario; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Sección de Dirección -->
                            <h5>Dirección</h5>
                            <div class="mb-3">
                                <label for="provincia" class="form-label">Provincia</label>
                                <select class="form-control" id="provincia" name="provincia" required>
                                    <option value="">Seleccione una provincia</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="canton" class="form-label">Cantón</label>
                                <select class="form-control" id="canton" name="canton" required>
                                    <option value="">Seleccione un cantón</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="distrito" class="form-label">Distrito</label>
                                <select class="form-control" id="distrito" name="distrito" required>
                                    <option value="">Seleccione un distrito</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="direccion_exacta" class="form-label">Dirección Exacta</label>
                                <textarea class="form-control" name="direccion_exacta" rows="3"><?php echo htmlspecialchars($direccion_exacta); ?></textarea>
                            </div>

                            <hr class="my-4">

                            <!-- Sección de Otros Datos -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="estado_civil" class="form-label">Estado Civil</label>
                                        <select class="form-control" name="estado_civil" required>
                                            <option value="">Seleccione un estado civil</option>
                                            <?php while ($ec = $estados_civiles->fetch_assoc()): ?>
                                                <option value="<?php echo $ec['idEstado_civil']; ?>" <?php if ($estado_civil == $ec['idEstado_civil']) echo 'selected'; ?>>
                                                    <?php echo $ec['Estado_civil_persona']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nacionalidad" class="form-label">Nacionalidad</label>
                                        <select class="form-control" name="nacionalidad" required>
                                            <option value="">Seleccione una nacionalidad</option>
                                            <?php while ($nc = $nacionalidades->fetch_assoc()): ?>
                                                <option value="<?php echo $nc['idNacionalidad']; ?>" <?php if ($nacionalidad == $nc['idNacionalidad']) echo 'selected'; ?>>
                                                    <?php echo $nc['Tipo_nacionalidad']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="genero" class="form-label">Género</label>
                                        <select class="form-control" name="genero" required>
                                            <option value="">Seleccione un género</option>
                                            <?php while ($gen = $generos->fetch_assoc()): ?>
                                                <option value="<?php echo $gen['idGenero_cat']; ?>" <?php if ($genero == $gen['idGenero_cat']) echo 'selected'; ?>>
                                                    <?php echo $gen['Descripcion_genero']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" name="telefono" id="telefono" value="<?php echo $telefono; ?>" pattern="\d{4}-\d{4}" placeholder="xxxx-xxxx" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección de Jerarquía -->
                            <hr class="my-4">
                            <h5>Departamento</h5>
                            <div class="mb-3">
                                <label for="departamento" class="form-label">Departamento</label>
                                <select class="form-control" name="departamento" required>
                                    <option value="">Seleccione un departamento</option>
                                    <?php while ($dept = $departamentos->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['idDepartamento']; ?>" <?php if ($departamento_id == $dept['idDepartamento']) echo 'selected'; ?>>
                                            <?php echo $dept['nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom"><?php echo empty($idPersona) ? 'Guardar' : 'Actualizar'; ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    fetch('js/ubicaciones.json')
        .then(response => response.json())
        .then(ubicaciones => {
            const provinciaSelect = document.getElementById('provincia');
            const cantonSelect = document.getElementById('canton');
            const distritoSelect = document.getElementById('distrito');

            // Cargar provincias
            for (const [id, nombre] of Object.entries(ubicaciones.provincias)) {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = nombre;
                provinciaSelect.appendChild(option);
            }

            // Manejar cambio de provincia
            provinciaSelect.addEventListener('change', function () {
                const provinciaId = this.value;
                cantonSelect.innerHTML = '<option value="">Seleccione un cantón</option>';
                distritoSelect.innerHTML = '<option value="">Seleccione un distrito</option>';

                if (provinciaId && ubicaciones.cantones[provinciaId]) {
                    for (const [id, nombre] of Object.entries(ubicaciones.cantones[provinciaId])) {
                        const option = document.createElement('option');
                        option.value = id;
                        option.textContent = nombre;
                        cantonSelect.appendChild(option);
                    }
                }
            });

            // Manejar cambio de cantón
            cantonSelect.addEventListener('change', function () {
                const cantonId = this.value;
                const provinciaId = provinciaSelect.value;
                distritoSelect.innerHTML = '<option value="">Seleccione un distrito</option>';

                // Cargar distritos solo si hay canton y provincia seleccionados
                if (cantonId && ubicaciones.distritos[provinciaId] && ubicaciones.distritos[provinciaId][cantonId]) {
                    for (const [id, nombre] of Object.entries(ubicaciones.distritos[provinciaId][cantonId])) {
                        const option = document.createElement('option');
                        option.value = id;
                        option.textContent = nombre;
                        distritoSelect.appendChild(option);
                    }
                }
            });

            // Preseleccionar valores si estamos en edición
            <?php if (!empty($provincia_id) && !empty($canton_id) && !empty($distrito_id)): ?>
            provinciaSelect.value = "<?php echo $provincia_id; ?>";
            provinciaSelect.dispatchEvent(new Event('change'));
            cantonSelect.value = "<?php echo $canton_id; ?>";
            cantonSelect.dispatchEvent(new Event('change'));
            distritoSelect.value = "<?php echo $distrito_id; ?>";
            <?php endif; ?>
        });
});

// Mostrar alerta si hay error en la URL
const urlParams = new URLSearchParams(window.location.search);
const error = urlParams.get('error');
if (error === 'invalid_email') {
    alert('Por favor, ingrese un correo electrónico válido.');
}
    
    // Formatear número de teléfono
    document.getElementById('telefono').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 4) {
            value = value.substring(0, 4) + '-' + value.substring(4, 8);
        }
        e.target.value = value;
    });

    document.getElementById('cedula').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // Eliminar caracteres no numéricos
    if (value.length >= 1) value = value.slice(0, 1) + '-' + value.slice(1);
    if (value.length >= 6) value = value.slice(0, 6) + '-' + value.slice(6);
    e.target.value = value;
});

// Evitar que el usuario elimine los guiones
document.getElementById('cedula').addEventListener('keydown', function (e) {
    const cursorPos = e.target.selectionStart;
    if ((cursorPos === 1 || cursorPos === 6) && (e.key === 'Backspace' || e.key === 'Delete')) {
        e.preventDefault();
    }
});


    </script>
</body>
</html>
