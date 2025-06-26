<?php  
// Iniciar la sesión
session_start();
include 'db.php';  // Asegúrate de tener la conexión a la base de datos correctamente

// Manejo de la eliminación de una persona
if (isset($_GET['delete_id'])) {
    $idPersona = $_GET['delete_id'];

    if (is_numeric($idPersona)) {
        // Eliminar registros relacionados en la tabla 'colaborador' primero
        $deleteColaboradorQuery = "DELETE FROM colaborador WHERE Persona_idPersona = $idPersona";
        $conn->query($deleteColaboradorQuery);  // Ejecutar la consulta para eliminar en 'colaborador'

        // Eliminar registros relacionados en la tabla 'usuario', si es necesario
        $deleteUsuarioQuery = "DELETE FROM usuario WHERE Persona_idPersona = $idPersona";
        $conn->query($deleteUsuarioQuery);  // Ejecutar la consulta para eliminar en 'usuario'

        // Consulta SQL para eliminar la persona
        $query = "DELETE FROM persona WHERE idPersona = $idPersona";
        if ($conn->query($query)) {
            // Redirigir con un mensaje de éxito
            header("Location: personas.php?success=deleted");
            exit();
        } else {
            echo "Error al eliminar la persona: " . $conn->error;
        }
    } else {
        echo "ID inválido.";
    }
}

// Manejo de la activación/desactivación de la persona
if (isset($_GET['toggle_id'])) {
    $idPersona = $_GET['toggle_id'];

    // Consultar el colaborador asociado a la persona para obtener el estado actual
    $queryColaborador = "SELECT idColaborador, activo FROM colaborador WHERE Persona_idPersona = $idPersona";
    $resultColaborador = $conn->query($queryColaborador);

    if ($resultColaborador && $resultColaborador->num_rows > 0) {
        // Si el colaborador existe, alternar su estado
        $colaborador = $resultColaborador->fetch_assoc();
        $idColaborador = $colaborador['idColaborador'];
        $estadoActual = $colaborador['activo'];

        // Alternar el estado actual
        $nuevoEstado = $estadoActual == 1 ? 0 : 1;
        $updateQuery = "UPDATE colaborador SET activo = $nuevoEstado WHERE idColaborador = $idColaborador";

        if ($conn->query($updateQuery)) {
            header("Location: personas.php");
            exit();
        } else {
            echo "Error al actualizar el estado: " . $conn->error;
        }
    } else {
        // Si no se encuentra un colaborador, crearlo y marcarlo como activo
        $insertQuery = "INSERT INTO colaborador (Persona_idPersona, activo, Fechadeingreso) VALUES ($idPersona, 1, NOW())";
        if ($conn->query($insertQuery)) {
            header("Location: personas.php");
            exit();
        } else {
            echo "Error al crear el colaborador: " . $conn->error;
        }
    }
}

// Obtener todos los departamentos
$departamentos = $conn->query("SELECT * FROM departamento");
$departamento_id = isset($_GET['departamento_id']) ? $_GET['departamento_id'] : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Personas</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5; /* Fondo suave y neutro */
        }

        .container {
            padding-top: 40px;
            padding-bottom: 40px;
        }

        h2 {
            font-size: 2rem;
            color: #343a40;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Botón Agregar Persona */
        .btn-add {
            background-color: #28a745;
            border-color: #28a745;
            color: #ffffff;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .btn-add:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: #ffffff;
        }

        /* Tabla de Personas */
        .table {
            background-color: #ffffff;
            border-radius: 5px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #343a40; /* Color oscuro y neutro */
            color: #ffffff;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
            font-weight: 500;
        }

        .table tbody td {
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .table tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }

        .table tbody tr:hover {
            background-color: #e2e6ea;
        }

        /* Botones de Acción */
        .btn-action {
            width: 35px;
            height: 35px;
            padding: 0;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .btn-action:hover {
            transform: scale(1.05);
        }

        .btn-edit {
            background-color: #0056b3; /* Azul más oscuro */
            border-color: #0056b3;
            color: #ffffff;
        }

        .btn-edit:hover {
            background-color: #004494;
            border-color: #003776;
            color: #ffffff;
        }

        .btn-delete {
            background-color: #dc3545; /* Rojo */
            border-color: #dc3545;
            color: #ffffff;
        }

        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #ffffff;
        }

        /* Modal de Confirmación */
        .modal-header {
            background-color: #343a40;
            color: #ffffff;
        }

        .modal-footer .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff;
        }

        .modal-footer .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            color: #ffffff;
        }

        .modal-footer .btn-delete {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff;
        }

        .modal-footer .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #ffffff;
        }

        /* Mensajes de Alerta */
        .alert {
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Filtro por Departamento */
        .form-select {
            max-width: 300px;
            margin: 0 auto 20px auto;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .form-select {
                width: 100%;
            }

            .btn-add {
                width: 100%;
                text-align: center;
                margin-top: 10px;
            }

            .table thead th,
            .table tbody td {
                font-size: 0.85rem;
                padding: 10px;
            }

            h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Lista de Personas</h2>

        <!-- Filtro por departamento -->
        <form method="GET" class="mb-3 text-center">
            <select name="departamento_id" class="form-select" onchange="this.form.submit()">
                <option value="">Todos los departamentos</option>
                <?php
                while ($row_depto = $departamentos->fetch_assoc()) {
                    $selected = ($departamento_id == $row_depto['idDepartamento']) ? "selected" : "";
                    echo "<option value='" . $row_depto['idDepartamento'] . "' $selected>" . htmlspecialchars($row_depto['nombre']) . "</option>";
                }
                ?>
            </select>
        </form>

        <!-- Botón Agregar Persona -->
        <div class="d-flex justify-content-end mb-3">
            <a href="form_persona.php" class="btn btn-add">
                <i class="bi bi-person-plus-fill me-2"></i> Agregar Persona
            </a>
        </div>

        <!-- Mostrar mensaje de éxito tras eliminar una persona -->
        <?php
        if (isset($_GET['success']) && $_GET['success'] == 'deleted') {
            echo '<div class="alert alert-success">Persona eliminada correctamente.</div>';
        }
        ?>

        <!-- Tabla de Personas -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Cédula</th>
                        <th>Fecha de Nacimiento</th>
                        <th>Género</th>
                        <th>Teléfono</th>
                        <th>Departamento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Apellido2, p.Cedula, p.Fecha_nac, 
                                    g.Descripcion_genero AS Genero, t.Numero_de_Telefono AS Telefono, dep.nombre AS Departamento, 
                                    c.activo AS Estado
                            FROM persona p
                            LEFT JOIN genero_cat g ON p.Genero_cat_idGenero_cat = g.idGenero_cat
                            LEFT JOIN telefono t ON p.Telefono_id_Telefono = t.id_Telefono
                            LEFT JOIN departamento dep ON p.Departamento_idDepartamento = dep.idDepartamento
                            LEFT JOIN colaborador c ON p.idPersona = c.Persona_idPersona";

                    if ($departamento_id != '') {
                        $query .= " WHERE p.Departamento_idDepartamento = $departamento_id";
                    }

                    $result = $conn->query($query);

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Formatear la fecha de nacimiento
                            $fecha_nac = date("d/m/Y", strtotime($row['Fecha_nac']));

                            // Determinar el estado
                            $estado = ($row['Estado'] == 1) ? 'Activo' : 'Inactivo';

                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['Nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Apellido1'] . " " . $row['Apellido2']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Cedula']) . "</td>";
                            echo "<td>" . htmlspecialchars($fecha_nac) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Genero']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Telefono']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Departamento']) . "</td>";
                            echo "<td>" . htmlspecialchars($estado) . "</td>";
                            echo "<td>
                                    <a href='form_persona.php?id=" . $row['idPersona'] . "' class='btn btn-edit btn-action me-2' title='Editar'>
                                        <i class='bi bi-pencil-square'></i>
                                    </a>
                                    <a href='personas.php?delete_id=" . $row['idPersona'] . "' class='btn btn-delete btn-action me-2' title='Eliminar' onclick=\"return confirm('¿Estás seguro de que deseas eliminar esta persona?')\">
                                        <i class='bi bi-trash'></i>
                                    </a>
                                    <a href='personas.php?toggle_id=" . $row['idPersona'] . "' class='btn " . ($row['Estado'] == 1 ? "btn-warning" : "btn-success") . " btn-action' title='" . ($row['Estado'] == 1 ? "Desactivar" : "Activar") . "'>
                                        <i class='bi " . ($row['Estado'] == 1 ? "bi-toggle-off" : "bi-toggle-on") . "'></i>
                                    </a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center'>No hay personas registradas.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación (Opcional, si prefieres usar un modal en lugar de confirmación estándar) -->
  
    
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="personas.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar esta persona?</p>
                        <input type="hidden" name="user_id" id="delete_user_id" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script para pasar el ID de la persona al modal de eliminación (si usas el modal) -->
    
    <script>
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idPersona = button.getAttribute('data-id');
            const modalBodyInput = deleteModal.querySelector('#delete_user_id');
            modalBodyInput.value = idPersona;
        });
    </script>
    
</body>

</html>
