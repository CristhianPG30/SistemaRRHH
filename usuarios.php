<?php 
// Iniciar la sesión
session_start();
include 'db.php';  // Asegúrate de tener la conexión a la base de datos correctamente

// Función para mostrar mensajes de sesión y luego limpiarlos
function displaySessionMessage() {
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        $message = htmlspecialchars($_SESSION['message']);
        $type = htmlspecialchars($_SESSION['message_type']);

        // Mapa de tipos de mensaje a clases de Bootstrap
        $alertTypes = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];

        // Obtener la clase de alerta correspondiente
        $alertClass = isset($alertTypes[$type]) ? $alertTypes[$type] : 'alert-info';

        echo "
            <div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Cerrar'></button>
            </div>
        ";

        // Limpiar los mensajes de sesión
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Manejar la eliminación de usuarios si se ha confirmado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    // Validar y sanitizar el ID del usuario
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($userId > 0) {
        // Prepara y ejecuta la consulta de eliminación
        $stmt = $conn->prepare("DELETE FROM usuario WHERE idUsuario = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Usuario eliminado con éxito.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al eliminar el usuario: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }

        $stmt->close();
    } else {
        $_SESSION['message'] = 'ID de usuario inválido.';
        $_SESSION['message_type'] = 'error';
    }

    // Redirigir para evitar reenvío de formulario
    header("Location: mantenimiento_usuarios.php");
    exit;
}

// Consulta para obtener la lista de usuarios
$query = "
    SELECT u.idUsuario, u.username, r.Descripcion AS rol, CONCAT(p.Nombre, ' ', p.Apellido1, ' ', p.Apellido2) AS persona
    FROM usuario u
    LEFT JOIN idrol r ON u.IdRol_idIdRol = r.idIdRol
    LEFT JOIN persona p ON u.Persona_idPersona = p.idPersona
    ORDER BY u.username ASC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Usuarios</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            padding-top: 40px;
            padding-bottom: 40px;
        }

        h1 {
            font-size: 2.8rem;
            color: #343a40;
            text-align: center;
            margin-bottom: 40px;
            font-weight: 700;
        }

        /* Tarjeta de Usuarios */
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
        }

        .card-header {
            background-color: #343a40; /* Color neutro */
            color: #ffffff;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 20px;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 500;
        }

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

        /* Tabla de Usuarios */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background-color: #f1f3f5;
            color: #495057;
            border: 1px solid #dee2e6; /* Bordes entre celdas */
            text-align: center;
            vertical-align: middle;
        }

        .table tbody td {
            border: 1px solid #dee2e6; /* Bordes entre celdas */
            text-align: center;
            vertical-align: middle;
        }

        .table tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }

        .table tbody tr:hover {
            background-color: #e9ecef;
        }

        /* Botones de Acción */
        .btn-action {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .btn-edit {
            background-color: #0056b3; /* Azul más oscuro y menos llamativo */
            border-color: #0056b3;
            color: #ffffff;
        }

        .btn-edit:hover {
            background-color: #004494;
            border-color: #003776;
            color: #ffffff;
        }

        .btn-delete {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff;
        }

        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #ffffff;
        }

        /* Estilos para la Modal */
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

        /* Responsividad de la Tabla */
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }

            .btn-add {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1>Mantenimiento de Usuarios</h1>

            <!-- Sección de Mensajes -->
            <?php displaySessionMessage(); ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lista de Usuarios</h5>
                    <a href="form_usuario.php" class="btn btn-add">
                        <i class="bi bi-person-plus-fill me-2"></i> Agregar Usuario
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Persona Asociada</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['rol']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['persona']) . "</td>";
                                        echo "<td class='text-center'>
                                                <a href='form_usuario.php?id=" . $row['idUsuario'] . "' class='btn btn-edit btn-action me-2' title='Editar'>
                                                    <i class='bi bi-pencil-square'></i>
                                                </a>
                                                <button type='button' class='btn btn-delete btn-action' title='Eliminar' data-bs-toggle='modal' data-bs-target='#deleteModal' data-user-id='" . $row['idUsuario'] . "'>
                                                    <i class='bi bi-trash'></i>
                                                </button>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center'>No hay usuarios registrados.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="mantenimiento_usuarios.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar este usuario?</p>
                        <input type="hidden" name="user_id" id="delete_user_id" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_user" class="btn btn-delete">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para pasar el ID del usuario al modal de eliminación
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const modalBodyInput = deleteModal.querySelector('#delete_user_id');
            modalBodyInput.value = userId;
        });
    </script>
</body>

</html>
