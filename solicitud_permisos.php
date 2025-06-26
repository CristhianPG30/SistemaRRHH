<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

include 'db.php';

// Verificar la antigüedad del colaborador y calcular días de vacaciones disponibles
$vacaciones_disponibles = 0;
$antiguedad_suficiente = false;

if (isset($_SESSION['persona_id'])) {
    $persona_id = $_SESSION['persona_id'];

    // Calcular la antigüedad
    $sql_antiguedad = "SELECT Fechadeingreso FROM colaborador WHERE Persona_idPersona = $persona_id";
    $result_antiguedad = $conn->query($sql_antiguedad);

    if ($result_antiguedad->num_rows > 0) {
        $row_antiguedad = $result_antiguedad->fetch_assoc();
        $fecha_ingreso = new DateTime($row_antiguedad['Fechadeingreso']);
        $fecha_actual = new DateTime();
        $diferencia = $fecha_actual->diff($fecha_ingreso);

        // Si tiene al menos 1 año de antigüedad, permitir solicitar vacaciones
        if ($diferencia->y >= 1) {
            $antiguedad_suficiente = true;

            // Calcular el número de años trabajados y asignar 12 días de vacaciones más 2 de descanso por cada año trabajado
            $anios_trabajados = $diferencia->y;
            $vacaciones_disponibles = ($anios_trabajados * 12) + ($anios_trabajados * 2);
            
            // Verificar si ya tiene un registro de vacaciones disponibles en la base de datos
            $sql_vacaciones = "SELECT Cantidad_Disponible FROM vacaciones WHERE Persona_idPersona = $persona_id";
            $result_vacaciones = $conn->query($sql_vacaciones);

            if ($result_vacaciones->num_rows > 0) {
                $row_vacaciones = $result_vacaciones->fetch_assoc();
                $vacaciones_disponibles = $row_vacaciones['Cantidad_Disponible'];
            } else {
                // Insertar la cantidad de vacaciones inicial si no existe
                $sql_insert_vacaciones = "INSERT INTO vacaciones (cantidad_solicitada, Cantidad_Disponible, Persona_idPersona) VALUES (0, $vacaciones_disponibles, $persona_id)";
                $conn->query($sql_insert_vacaciones);
            }
        }
    }
}

// Lógica de solicitud de permiso
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $tipo_permiso = $_POST['tipo_permiso'];
    $motivo = $_POST['motivo'];

    if (isset($_SESSION['persona_id'])) {
        $persona_id = $_SESSION['persona_id'];
    } else {
        echo "<script>alert('El ID de la persona no está disponible en la sesión.');</script>";
        exit;
    }

    // Calcular días solicitados
    $dias_solicitados = (new DateTime($fecha_fin))->diff(new DateTime($fecha_inicio))->days + 1;

 // Verificar si tiene suficientes días de vacaciones disponibles para la solicitud
if ($tipo_permiso == 'vacaciones') {
    // Supongamos que la fecha de inicio de las vacaciones está en una variable llamada $fecha_inicio
    $fecha_actual = date("Y-m-d");
    
    if (!$antiguedad_suficiente) {
        echo "<script>alert('No tienes suficiente antigüedad para solicitar vacaciones.'); window.history.back();</script>";
        die();
    } elseif ($dias_solicitados > $vacaciones_disponibles) {
        echo "<script>alert('No tienes suficientes días de vacaciones disponibles.'); window.history.back();</script>";
        die();
    } elseif ($fecha_inicio <= $fecha_actual) {
        echo "<script>alert('La fecha de inicio de las vacaciones debe ser en el futuro.'); window.history.back();</script>";
        die();
    } else {
        $vacaciones_disponibles -= $dias_solicitados;
        $sql_update_vacaciones = "UPDATE vacaciones SET Cantidad_Disponible = $vacaciones_disponibles WHERE Persona_idPersona = $persona_id";
        $conn->query($sql_update_vacaciones);
    }
}



    // Subida del comprobante (opcional)
    $comprobante = '';
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] == 0) {
        $comprobante = 'uploads/' . basename($_FILES['comprobante']['name']);
        if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $comprobante)) {
            echo "<script>alert('No se pudo mover el archivo al directorio uploads. Verifica los permisos.');</script>";
        }
    }

    // Inserción en la base de datos
    $comprobante_sql = !empty($comprobante) ? "'$comprobante'" : "NULL";
    $sql = "INSERT INTO permisos (FechaSolicitud, Fechainicio, FechaFin, TipoPermiso, Motivo, Comprobante, Colaborador_idColaborador, Persona_idPersona, Estado) 
            VALUES (NOW(), '$fecha_inicio', '$fecha_fin', '$tipo_permiso', '$motivo', $comprobante_sql, {$_SESSION['colaborador_id']}, $persona_id, 'Pendiente')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Solicitud enviada con éxito');</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// Configuración de paginación
$registros_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener el total de registros para la paginación
$sql_total = "SELECT COUNT(*) as total FROM permisos WHERE Colaborador_idColaborador = {$_SESSION['colaborador_id']}";
$result_total = $conn->query($sql_total);
$total_registros = $result_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Recuperar el historial de permisos con límite para la paginación
$sql_historial = "SELECT FechaSolicitud, Fechainicio, FechaFin, TipoPermiso, Motivo, Estado, Observaciones 
                  FROM permisos 
                  WHERE Colaborador_idColaborador = {$_SESSION['colaborador_id']}
                  ORDER BY FechaSolicitud DESC
                  LIMIT $inicio, $registros_por_pagina";
$result_historial = $conn->query($sql_historial);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Permisos - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-custom {
            background-color: #2c3e50;
            padding: 15px 20px;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            color: #ffffff;
            font-weight: bold;
        }
        .navbar-brand img {
            height: 45px;
            margin-right: 10px;
        }
        .navbar-nav .nav-link {
            color: #ecf0f1;
            margin-right: 10px;
        }
        .navbar-nav .nav-link:hover {
            color: #1abc9c;
        }
        .welcome-text {
            font-size: 1.1rem;
            color: #f39c12;
            margin-right: 20px;
        }
        .btn-logout {
            border-color: #e74c3c;
            color: #e74c3c;
            padding: 5px 12px;
        }
        .btn-logout:hover {
            background-color: #e74c3c;
            color: #ffffff;
        }
        .container {
            padding-top: 30px;
        }
        h1 {
            font-size: 2.5rem;
            color: #004085;
            text-align: center;
            margin-bottom: 30px;
        }
        .card-form {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .card-form h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        .form-control {
            border-radius: 10px;
        }
        .btn-submit {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #0056b3;
        }
        .form-control:focus {
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        .time-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        .estado-aprobado {
            background-color: #28a745;
            color: white;
            padding: 5px;
            border-radius: 5px;
        }
        .estado-rechazado {
            background-color: #dc3545;
            color: white;
            padding: 5px;
            border-radius: 5px;
        }
        .estado-pendiente {
            background-color: #ffc107;
            color: white;
            padding: 5px;
            border-radius: 5px;
        }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <h1>Solicitud de Permisos</h1>
        <p class="text-center">Rellena el formulario para solicitar un permiso.</p>

        <!-- Formulario de solicitud de permisos -->
        <div class="card card-form">
            <h2>Registrar Permiso</h2>
            <form action="solicitud_permisos.php" method="post" enctype="multipart/form-data">
                <p><strong>Vacaciones disponibles:</strong> <?php echo $vacaciones_disponibles; ?> días</p>
                <div class="time-container">
                    <div class="mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="tipo_permiso" class="form-label">Tipo de Permiso</label>
                    <select class="form-control" id="tipo_permiso" name="tipo_permiso" required>
                        <option value="">Selecciona el tipo de permiso</option>
                        <option value="vacaciones">Vacaciones</option>
                        <option value="personal">Permiso Personal</option>
                        <option value="enfermedad">Enfermedad</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="motivo" class="form-label">Motivo</label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" placeholder="Escribe el motivo de la solicitud" required></textarea>
                </div>

                <div class="mb-3">
                    <label for="comprobante" class="form-label">Adjuntar comprobante (opcional)</label>
                    <input type="file" class="form-control" id="comprobante" name="comprobante" accept="image/*">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn-submit">Enviar Solicitud</button>
                </div>
            </form>
        </div>

        <h2 class="mt-5 text-center">Historial de Solicitudes</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Fecha Solicitud</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Tipo de Permiso</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_historial->num_rows > 0) {
                        while ($row_historial = $result_historial->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_historial['FechaSolicitud']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_historial['Fechainicio']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_historial['FechaFin']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_historial['TipoPermiso']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_historial['Motivo']) . "</td>";
                            if ($row_historial['Estado'] == 'Aprobado') {
                                echo "<td><span class='estado-aprobado'>Aprobado</span></td>";
                            } elseif ($row_historial['Estado'] == 'Rechazado') {
                                echo "<td><span class='estado-rechazado'>Rechazado</span></td>";
                            } else {
                                echo "<td><span class='estado-pendiente'>Pendiente</span></td>";
                            }
                            echo "<td>" . (!empty($row_historial['Observaciones']) ? htmlspecialchars($row_historial['Observaciones']) : '-') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No has realizado ninguna solicitud de permisos.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
          <!-- Paginación -->
          <nav aria-label="Page navigation example" class="d-flex justify-content-center mt-4">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="solicitud_permisos.php?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
