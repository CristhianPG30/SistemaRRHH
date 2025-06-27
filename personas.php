<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Verificar si el usuario está autenticado y tiene permisos
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// --- INICIO DE LÓGICA DE GESTIÓN ---

// Manejo de la activación/desactivación de un colaborador
if (isset($_GET['toggle_id'])) {
    $idPersona = intval($_GET['toggle_id']);
    
    // Buscar si el colaborador ya existe
    $stmt = $conn->prepare("SELECT idColaborador, activo FROM colaborador WHERE Persona_idPersona = ?");
    $stmt->bind_param("i", $idPersona);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $colaborador = $result->fetch_assoc();
        $nuevoEstado = $colaborador['activo'] ? 0 : 1;
        
        $stmt_update = $conn->prepare("UPDATE colaborador SET activo = ? WHERE Persona_idPersona = ?");
        $stmt_update->bind_param("ii", $nuevoEstado, $idPersona);
        if($stmt_update->execute()){
            $message = 'Estado del colaborador actualizado correctamente.';
            $message_type = 'success';
        } else {
            $message = 'Error al actualizar el estado.';
            $message_type = 'danger';
        }
        $stmt_update->close();
    }
    $stmt->close();
}

// Manejo de la eliminación de una persona
if (isset($_POST['delete_id'])) {
    $idPersona = intval($_POST['delete_id']);

    $conn->begin_transaction();
    
    try {
        // Eliminar registros dependientes
        $conn->query("DELETE FROM usuario WHERE id_persona_fk = $idPersona");
        // La tabla colaborador tiene una clave foránea, pero por seguridad la eliminamos primero
        $conn->query("DELETE FROM colaborador WHERE id_persona_fk = $idPersona");
        // Añadir aquí otras eliminaciones en cascada si es necesario...

        // Finalmente, eliminar la persona
        $stmt = $conn->prepare("DELETE FROM persona WHERE idPersona = ?");
        $stmt->bind_param("i", $idPersona);
        $stmt->execute();
        
        $conn->commit();
        $message = 'Persona eliminada correctamente.';
        $message_type = 'success';
        
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = 'Error al eliminar la persona. Puede tener registros asociados.';
        $message_type = 'danger';
    }
}

// --- FIN DE LÓGICA DE GESTIÓN ---

// --- INICIO DE CORRECCIÓN DE CONSULTA DE DEPARTAMENTOS ---
// Obtener listas para filtros y formularios (Consulta corregida)
$departamentos = $conn->query("
    SELECT d.idDepartamento, d.nombre 
    FROM departamento d
    JOIN estado_cat e ON d.id_estado_fk = e.idEstado
    WHERE e.Descripcion = 'Activo'
");
// --- FIN DE CORRECCIÓN DE CONSULTA DE DEPARTAMENTOS ---

$departamento_id_filter = isset($_GET['departamento_id']) ? intval($_GET['departamento_id']) : '';
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';


// --- INICIO DE CORRECCIÓN DE CONSULTA PRINCIPAL ---
// Construcción de la consulta principal (corregida para que coincida con el esquema)
$sql = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Apellido2, p.Cedula,
               d.nombre AS Departamento, c.activo AS Estado
        FROM persona p
        LEFT JOIN colaborador c ON p.idPersona = c.id_persona_fk
        LEFT JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
        WHERE 1=1";
// --- FIN DE CORRECCIÓN DE CONSULTA PRINCIPAL ---


$params = [];
$types = '';

if ($departamento_id_filter) {
    // La columna del departamento está en 'colaborador', no en 'persona'
    $sql .= " AND c.id_departamento_fk = ?";
    $params[] = $departamento_id_filter;
    $types .= 'i';
}

if ($search_term) {
    $sql .= " AND (p.Nombre LIKE ? OR p.Apellido1 LIKE ? OR p.Cedula LIKE ?)";
    $like_term = "%" . $search_term . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= 'sss';
}

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_personas = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Personas - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }

        .card-main {
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .table thead th {
            background-color: #343a40;
            color: #fff;
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-action {
            width: 38px;
            height: 38px;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.7em;
            font-weight: 500;
        }

    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0" style="font-weight: 700;">Gestión de Personas</h2>
            <a href="form_persona.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-2"></i> Agregar Persona
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-main">
            <div class="card-body">
                <!-- Filtros y Búsqueda -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, apellido o cédula..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-5">
                        <select name="departamento_id" class="form-select">
                            <option value="">Filtrar por departamento...</option>
                            <?php while ($row_depto = $departamentos->fetch_assoc()): ?>
                                <option value="<?php echo $row_depto['idDepartamento']; ?>" <?php if ($departamento_id_filter == $row_depto['idDepartamento']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row_depto['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Filtrar</button>
                    </div>
                </form>

                <!-- Tabla de Personas -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Cédula</th>
                                <th>Departamento</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_personas->num_rows > 0): ?>
                                <?php while ($row = $result_personas->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['Nombre'] . " " . $row['Apellido1'] . " " . $row['Apellido2']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Departamento'] ?? 'No asignado'); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['Estado'] == 1): ?>
                                                <span class="badge rounded-pill bg-success status-badge">Activo</span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-danger status-badge">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="form_persona.php?id=<?php echo $row['idPersona']; ?>" class="btn btn-outline-primary btn-sm btn-action" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-action" title="Eliminar" onclick="confirmDelete(<?php echo $row['idPersona']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <a href="personas.php?toggle_id=<?php echo $row['idPersona']; ?>" class="btn btn-outline-secondary btn-sm btn-action" title="<?php echo $row['Estado'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="bi <?php echo $row['Estado'] ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No se encontraron personas con los filtros seleccionados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar a esta persona? Esta acción no se puede deshacer y eliminará todos los registros asociados.</p>
                </div>
                <div class="modal-footer">
                    <form id="deleteForm" method="POST" action="personas.php">
                        <input type="hidden" name="delete_id" id="delete_id_input">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id) {
            document.getElementById('delete_id_input').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
