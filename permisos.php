<?php 
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

include 'db.php'; // Conexión a la base de datos

// Verificar si se ha enviado una acción (aprobar o rechazar)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $permiso_id = $_POST['permiso_id'];
    $accion = $_POST['accion']; // 'Aprobar' o 'Rechazar'

    // Consulta para obtener detalles del permiso
    $permiso_sql = "SELECT * FROM permisos WHERE idPermisos = $permiso_id";
    $permiso_result = $conn->query($permiso_sql);
    $permiso = $permiso_result->fetch_assoc();

    if ($accion == 'Aprobar') {
        $nuevo_estado = 'Aprobado';
        $sql = "UPDATE permisos SET estado = '$nuevo_estado', Observaciones = NULL WHERE idPermisos = $permiso_id";

        // Verificar si el permiso es de tipo "vacaciones" para restarlo del saldo de vacaciones
        if ($permiso['TipoPermiso'] === 'vacaciones') {
            // Si cantidad_horas no está definido, asignar un valor predeterminado de 0
            $cantidad_horas = isset($permiso['cantidad_horas']) ? $permiso['cantidad_horas'] : 0;
            $dias_solicitados = $cantidad_horas / 9; // Convertir horas a días (asumiendo jornada de 9 horas)
            $colaborador_id = $permiso['Colaborador_idColaborador'];

            // Actualizar el saldo de vacaciones en la tabla 'vacaciones'
            $vacaciones_sql = "UPDATE vacaciones SET Cantidad_Disponible = GREATEST(0, Cantidad_Disponible - $dias_solicitados)
                               WHERE Colaborador_idColaborador = $colaborador_id";
            if ($conn->query($vacaciones_sql) !== TRUE) {
                echo "<script>alert('Error al actualizar las vacaciones: " . $conn->error . "');</script>";
            }
        }

        // Registrar en la tabla incapacidades si el tipo es "enfermedad"
        if ($permiso['TipoPermiso'] === 'enfermedad') {
            $cantidad = 1;
            $razon = $permiso['Motivo'];
            $fecha_inicio = $permiso['FechaInicio'];
            $fecha_fin = $permiso['FechaFin'];
            $colaborador_id = $permiso['Colaborador_idColaborador'];

            $insert_incapacidad_sql = "INSERT INTO incapacidades (Cantidad, Razon, Fecha_inicio, Fecha_fin, Colaborador_idColaborador)
                                       VALUES ('$cantidad', '$razon', '$fecha_inicio', '$fecha_fin', '$colaborador_id')";

            if ($conn->query($insert_incapacidad_sql) !== TRUE) {
                echo "<script>alert('Error al registrar la incapacidad: " . $conn->error . "');</script>";
            }
        }

    } elseif ($accion == 'Rechazar') {
        $observacion_rechazo = $_POST['comentario_rechazo'];
        $nuevo_estado = 'Rechazado';
        $sql = "UPDATE permisos SET estado = '$nuevo_estado', Observaciones = '$observacion_rechazo' WHERE idPermisos = $permiso_id";
    }

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Permiso actualizado correctamente.');</script>";
    } else {
        echo "<script>alert('Error al actualizar el permiso: " . $conn->error . "');</script>";
    }
}

// Obtener todas las solicitudes de permisos pendientes
$sql = "SELECT p.*, c.Nombre AS colaborador FROM permisos p
        JOIN persona c ON p.Persona_idPersona = c.idPersona
        WHERE p.estado = 'Pendiente'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; }
        .navbar-custom { background-color: #2c3e50; padding: 15px 20px; }
        .navbar-brand { color: #ffffff; font-weight: bold; }
        .welcome-text { font-size: 1.1rem; color: #f39c12; margin-right: 20px; }
        .btn-logout { border-color: #e74c3c; color: #e74c3c; }
        .container { padding-top: 30px; }
        .table th, .table td { text-align: center; vertical-align: middle; }
        .btn-approve { background-color: #28a745; color: white; }
        .btn-reject { background-color: #dc3545; color: white; }
        .comprobante-img { max-width: 100px; border-radius: 10px; }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <h1>Gestión de Permisos</h1>
        <p class="text-center">A continuación se muestran las solicitudes de permisos de los colaboradores, incluyendo los comprobantes.</p>

        <!-- Tabla de solicitudes de permisos -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Colaborador</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Motivo</th>
                        <th>Comprobante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['colaborador']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FechaInicio']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FechaFin']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Motivo']) . "</td>";
                            echo "<td><a href='" . htmlspecialchars($row['Comprobante']) . "' target='_blank'>Comprobante</a></td>";
                            echo "<td>
                                    <form method='post' style='display:inline-block;'>
                                        <input type='hidden' name='permiso_id' value='" . htmlspecialchars($row['idPermisos']) . "'>
                                        <input type='hidden' name='accion' value='Aprobar'>
                                        <button type='submit' class='btn btn-approve'>Aprobar</button>
                                    </form>
                                    <button class='btn btn-reject' onclick='mostrarComentario(" . $row['idPermisos'] . ")'>Rechazar</button>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No hay permisos pendientes.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para el comentario de rechazo -->
    <div class="modal fade" id="modalComentarioRechazo" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Motivo de Rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="comentario_rechazo" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="comentario_rechazo" name="comentario_rechazo" required></textarea>
                        </div>
                        <input type="hidden" name="permiso_id" id="permiso_id_rechazo">
                        <input type="hidden" name="accion" value="Rechazar">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-danger">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarComentario(idPermiso) {
            document.getElementById('permiso_id_rechazo').value = idPermiso;
            var modal = new bootstrap.Modal(document.getElementById('modalComentarioRechazo'));
            modal.show();
        }
    </script>
</body>

</html>

<?php
$conn->close();
?>
