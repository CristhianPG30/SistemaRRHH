<?php 
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

// Ruta al archivo de configuración
$configFilePath = 'js/configuracion.json';

// Incluir la conexión a la base de datos
include 'db.php';

// Leer la configuración desde el archivo JSON
$configData = json_decode(file_get_contents($configFilePath), true);

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inicializar variables de mensaje
if (!isset($_SESSION['message'])) {
    $_SESSION['message'] = '';
    $_SESSION['message_type'] = '';
}

// Manejar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar el token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = 'Token CSRF inválido.';
        $_SESSION['message_type'] = 'danger';
        header("Location: configuración.php"); // Asegúrate de que el nombre del archivo sea correcto
        exit;
    }

    // Actualizar configuración del sistema
    if (isset($_POST['update_config'])) {
        $configData['nombre_empresa'] = $_POST['nombre_empresa'];
        $configData['tarifa_hora_extra'] = (float)$_POST['tarifa_hora_extra'];

        // Guardar la configuración actualizada en el archivo JSON
        if (file_put_contents($configFilePath, json_encode($configData, JSON_PRETTY_PRINT))) {
            $_SESSION['message'] = 'Configuración actualizada con éxito.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error al actualizar la configuración.';
            $_SESSION['message_type'] = 'danger';
        }
        header("Location: configuración.php");
        exit;
    }

    // Agregar nueva deducción
    elseif (isset($_POST['add_deduction'])) {
        $descripcion = $_POST['descripcion'];
        $porcentaje = isset($_POST['porcentaje']) && $_POST['porcentaje'] !== '' ? floatval($_POST['porcentaje']) : null;
        $aplica_general = isset($_POST['aplica_general']) ? 1 : 0;

        // Insertar en la base de datos
        $stmt = $conn->prepare("INSERT INTO deducciones (Persona_idPersona, descripcion, Porcentaje, tipo_deduccion, aplica_general) VALUES (NULL, ?, ?, 'porcentaje', ?)");
        $stmt->bind_param("sdi", $descripcion, $porcentaje, $aplica_general);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Deducción agregada con éxito.';
            $_SESSION['message_type'] = 'success';
            header("Location: configuración.php");
            exit;
        } else {
            $_SESSION['message'] = 'Error al agregar la deducción: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }
        $stmt->close();
    }

    // Editar deducción existente
    elseif (isset($_POST['edit_deduction'])) {
        $idDeduccion = intval($_POST['idDeduccion']);
        $descripcion = $_POST['descripcion'];
        $porcentaje = isset($_POST['porcentaje']) && $_POST['porcentaje'] !== '' ? floatval($_POST['porcentaje']) : null;
        $aplica_general = isset($_POST['aplica_general']) ? 1 : 0;

        // Actualizar en la base de datos
        $stmt = $conn->prepare("UPDATE deducciones SET descripcion = ?, Porcentaje = ?, aplica_general = ? WHERE idDeduccion = ?");
        $stmt->bind_param("sdii", $descripcion, $porcentaje, $aplica_general, $idDeduccion);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Deducción actualizada con éxito.';
            $_SESSION['message_type'] = 'success';
            header("Location: configuración.php");
            exit;
        } else {
            $_SESSION['message'] = 'Error al actualizar la deducción: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }
        $stmt->close();
    }

    // Eliminar deducción
    elseif (isset($_POST['delete_deduction'])) {
        $idDeduccion = intval($_POST['idDeduccion']);

        // Eliminar de la base de datos
        $stmt = $conn->prepare("DELETE FROM deducciones WHERE idDeduccion = ?");
        $stmt->bind_param("i", $idDeduccion);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Deducción eliminada con éxito.';
            $_SESSION['message_type'] = 'success';
            header("Location: configuración.php");
            exit;
        } else {
            $_SESSION['message'] = 'Error al eliminar la deducción: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }
        $stmt->close();
    }

    // Agregar nueva jerarquía con validaciones adicionales
    elseif (isset($_POST['add_jerarquia'])) {
        $colaborador_id = intval($_POST['colaborador_id']);
        $jefe_id = isset($_POST['jefe_id']) && $_POST['jefe_id'] !== '' ? intval($_POST['jefe_id']) : null;
        $departamento_id = intval($_POST['departamento_id']);

        // Validar que el colaborador no sea su propio jefe
        if ($colaborador_id === $jefe_id) {
            $_SESSION['message'] = 'Un colaborador no puede ser su propio jefe.';
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        } else {
            // Verificar si la jerarquía ya existe para este colaborador en cualquier departamento
            if ($jefe_id === null) {
                $stmt = $conn->prepare("SELECT idJerarquia FROM jerarquia WHERE Colaborador_idColaborador = ? AND Departamento_idDepartamento = ?");
                $stmt->bind_param("ii", $colaborador_id, $departamento_id);
            } else {
                $stmt = $conn->prepare("SELECT idJerarquia FROM jerarquia WHERE Colaborador_idColaborador = ? AND (Departamento_idDepartamento = ? OR Jefe_idColaborador = ?)");
                $stmt->bind_param("iii", $colaborador_id, $departamento_id, $jefe_id);
            }
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $_SESSION['message'] = 'La jerarquía ya existe o el colaborador ya está asignado con el mismo jefe en otro departamento.';
                $_SESSION['message_type'] = 'danger';
                header("Location: configuración.php");
                exit;
            } else {
                // Insertar en la base de datos
                if ($jefe_id === null) {
                    $stmt = $conn->prepare("INSERT INTO jerarquia (Colaborador_idColaborador, Departamento_idDepartamento) VALUES (?, ?)");
                    $stmt->bind_param("ii", $colaborador_id, $departamento_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO jerarquia (Colaborador_idColaborador, Jefe_idColaborador, Departamento_idDepartamento) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $colaborador_id, $jefe_id, $departamento_id);
                }

                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Jerarquía agregada con éxito.';
                    $_SESSION['message_type'] = 'success';
                    header("Location: configuración.php");
                    exit;
                } else {
                    $_SESSION['message'] = 'Error al agregar la jerarquía: ' . $stmt->error;
                    $_SESSION['message_type'] = 'danger';
                    header("Location: configuración.php");
                    exit;
                }
            }
            $stmt->close();
        }
    }

    // Editar jerarquía existente
    elseif (isset($_POST['edit_jerarquia'])) {
        $idJerarquia = intval($_POST['idJerarquia']);
        $colaborador_id = intval($_POST['colaborador_id']);
        $jefe_id = isset($_POST['jefe_id']) && $_POST['jefe_id'] !== '' ? intval($_POST['jefe_id']) : null;
        $departamento_id = intval($_POST['departamento_id']);

        // Validar que el colaborador no sea su propio jefe
        if ($colaborador_id === $jefe_id) {
            $_SESSION['message'] = 'Un colaborador no puede ser su propio jefe.';
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }

        // Actualizar en la base de datos
        if ($jefe_id === null) {
            $stmt = $conn->prepare("UPDATE jerarquia SET Colaborador_idColaborador = ?, Jefe_idColaborador = NULL, Departamento_idDepartamento = ? WHERE idJerarquia = ?");
            $stmt->bind_param("iii", $colaborador_id, $departamento_id, $idJerarquia);
        } else {
            $stmt = $conn->prepare("UPDATE jerarquia SET Colaborador_idColaborador = ?, Jefe_idColaborador = ?, Departamento_idDepartamento = ? WHERE idJerarquia = ?");
            $stmt->bind_param("iiii", $colaborador_id, $jefe_id, $departamento_id, $idJerarquia);
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Jerarquía actualizada con éxito.';
            $_SESSION['message_type'] = 'success';
            header("Location: configuración.php");
            exit;
        } else {
            $_SESSION['message'] = 'Error al actualizar la jerarquía: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }
        $stmt->close();
    }

    // Eliminar jerarquía
    elseif (isset($_POST['delete_jerarquia'])) {
        $idJerarquia = intval($_POST['idJerarquia']);

        // Eliminar de la base de datos
        $stmt = $conn->prepare("DELETE FROM jerarquia WHERE idJerarquia = ?");
        $stmt->bind_param("i", $idJerarquia);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Jerarquía eliminada con éxito.';
            $_SESSION['message_type'] = 'success';
            header("Location: configuración.php");
            exit;
        } else {
            $_SESSION['message'] = 'Error al eliminar la jerarquía: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: configuración.php");
            exit;
        }
        $stmt->close();
    }
}

// Obtener las deducciones actuales
$sql = "SELECT * FROM deducciones WHERE Persona_idPersona IS NULL";
$result = $conn->query($sql);
$deducciones = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $deducciones[] = $row;
    }
}

// Obtener las jerarquías actuales
$sql = "SELECT j.idJerarquia, p.Nombre AS ColaboradorNombre, p.Apellido1 AS ColaboradorApellido, 
               jefe_p.Nombre AS JefeNombre, jefe_p.Apellido1 AS JefeApellido, d.nombre AS DepartamentoNombre
        FROM jerarquia j
        JOIN colaborador c ON j.Colaborador_idColaborador = c.idColaborador
        JOIN persona p ON c.Persona_idPersona = p.idPersona
        LEFT JOIN colaborador jefe ON j.Jefe_idColaborador = jefe.idColaborador
        LEFT JOIN persona jefe_p ON jefe.Persona_idPersona = jefe_p.idPersona
        JOIN departamento d ON j.Departamento_idDepartamento = d.idDepartamento";

$result = $conn->query($sql);
$jerarquias = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $jerarquias[] = $row;
    }
}

// Obtener lista de colaboradores para los formularios
$sql = "SELECT c.idColaborador, p.Nombre AS nombre, p.Apellido1 AS apellido1 
        FROM colaborador c
        JOIN persona p ON c.Persona_idPersona = p.idPersona";

$result = $conn->query($sql);
$colaboradores = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

// Obtener lista de jefes (todos los colaboradores que pueden ser jefes)
$jefes = $colaboradores;

// Obtener lista de departamentos
$sql = "SELECT idDepartamento, nombre FROM departamento WHERE estado = 'activo'";
$result = $conn->query($sql);
$departamentos = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departamentos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Estilos personalizados para una paleta de colores más armoniosa */
        .card-header-primary {
            background-color: #6c757d; /* Gris medio */
            color: #fff;
        }
        .card-header-success {
            background-color: #28a745; /* Verde */
            color: #fff;
        }
        .card-header-info {
            background-color: #6610f2; /* Morado */
            color: #fff;
        }
        /* Estilo para las alertas */
        .alert-position {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        /* Estilos adicionales para mejorar la apariencia */
        .card {
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        /* Personalización de botones */
        .btn-primary {
            background-color: #6c757d; /* Gris medio */
            border-color: #6c757d;
        }
        .btn-primary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-secondary {
            background-color: #adb5bd; /* Gris claro */
            border-color: #adb5bd;
        }
        .btn-secondary:hover {
            background-color: #858d96;
            border-color: #6c757d;
        }
        /* Mejora de badges */
        .badge-success {
            background-color: #28a745;
        }
        .badge-secondary {
            background-color: #6c757d;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Configuración del Sistema</h1>

    <!-- Sección de Mensajes -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show alert-position" role="alert">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php 
            // Limpiar el mensaje después de mostrarlo
            $_SESSION['message'] = '';
            $_SESSION['message_type'] = '';
        ?>
    <?php endif; ?>

    <div class="row">
        <!-- Configuración del Sistema -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h5 class="mb-0"><i class="bi bi-gear-wide-connected me-2"></i>Configuración del Sistema</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="update_config" value="1">
                        <div class="mb-3">
                            <label for="nombre_empresa" class="form-label">Nombre de la Empresa</label>
                            <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" value="<?= htmlspecialchars($configData['nombre_empresa']); ?>" required>
                        </div>
                     
                        <div class="mb-3">
                            <label for="tarifa_hora_extra" class="form-label">Tarifa por Hora Extra (₡)</label>
                            <input type="number" step="0.01" class="form-control" id="tarifa_hora_extra" name="tarifa_hora_extra" value="<?= htmlspecialchars($configData['tarifa_hora_extra']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Configuración</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Gestión de Deducciones -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header card-header-success d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Deducciones</h5>
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalDeduccion" onclick="nuevaDeduccion()">
                        <i class="bi bi-plus-circle me-1"></i>Agregar
                    </button>
                </div>
                <div class="card-body">
                    <!-- Tabla de Deducciones -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Porcentaje</th>
                                    <th>Aplica General</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($deducciones) > 0): ?>
                                    <?php foreach ($deducciones as $deduccion): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($deduccion['descripcion']); ?></td>
                                            <td><?= $deduccion['Porcentaje'] !== null ? number_format($deduccion['Porcentaje'], 2) . '%' : 'N/A'; ?></td>
                                            <td><?= $deduccion['aplica_general'] == 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>'; ?></td>
                                            <td>
                                                <!-- Botones de Editar y Eliminar -->
                                                <button type="button" class="btn btn-sm btn-primary me-1" onclick='editarDeduccion(<?= json_encode($deduccion, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar esta deducción?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="idDeduccion" value="<?= $deduccion['idDeduccion']; ?>">
                                                    <button type="submit" name="delete_deduction" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay deducciones disponibles.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gestión de Jerarquías -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-info d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Jerarquías</h5>
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalJerarquia" onclick="nuevaJerarquia()">
                        <i class="bi bi-plus-circle me-1"></i>Agregar
                    </button>
                </div>
                <div class="card-body">
                    <!-- Tabla de Jerarquías -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Jefe</th>
                                    <th>Departamento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($jerarquias) > 0): ?>
                                    <?php foreach ($jerarquias as $jerarquia): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($jerarquia['ColaboradorNombre'] . ' ' . $jerarquia['ColaboradorApellido']); ?></td>
                                            <td><?= $jerarquia['JefeNombre'] ? htmlspecialchars($jerarquia['JefeNombre'] . ' ' . $jerarquia['JefeApellido']) : '<span class="badge badge-secondary">Sin Jefe</span>'; ?></td>
                                            <td><?= htmlspecialchars($jerarquia['DepartamentoNombre']); ?></td>
                                            <td>
                                                <!-- Botones de Editar y Eliminar -->
                                                <button type="button" class="btn btn-sm btn-primary me-1" onclick='editarJerarquia(<?= json_encode($jerarquia, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar esta jerarquía?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="idJerarquia" value="<?= $jerarquia['idJerarquia']; ?>">
                                                    <button type="submit" name="delete_jerarquia" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay jerarquías disponibles.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal para Agregar/Editar Deducción -->
<div class="modal fade" id="modalDeduccion" tabindex="-1" aria-labelledby="modalDeduccionLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="modalDeduccionLabel">Agregar Deducción</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
              <input type="hidden" id="idDeduccion" name="idDeduccion">
              <div class="mb-3">
                  <label for="descripcion" class="form-label">Descripción</label>
                  <input type="text" class="form-control" id="descripcion" name="descripcion" required>
              </div>
              <div class="mb-3">
                  <label for="porcentaje" class="form-label">Porcentaje (%)</label>
                  <input type="number" step="0.01" class="form-control" id="porcentaje" name="porcentaje" required>
              </div>
              <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="aplica_general" name="aplica_general">
                  <label class="form-check-label" for="aplica_general">
                      Aplica a Todos los Colaboradores
                  </label>
              </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="add_deduction" id="btnAddDeduction" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Agregar</button>
            <button type="submit" name="edit_deduction" id="btnEditDeduction" class="btn btn-primary" style="display:none;"><i class="bi bi-save me-1"></i>Guardar Cambios</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cerrar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para Agregar/Editar Jerarquía -->
<div class="modal fade" id="modalJerarquia" tabindex="-1" aria-labelledby="modalJerarquiaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="modalJerarquiaLabel">Agregar Jerarquía</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
              <input type="hidden" id="idJerarquia" name="idJerarquia">
              <div class="mb-3">
                  <label for="colaborador_id" class="form-label">Colaborador</label>
                  <select class="form-select" id="colaborador_id" name="colaborador_id" required>
                      <option value="">Seleccione un colaborador</option>
                      <?php foreach ($colaboradores as $colaborador): ?>
                          <option value="<?= $colaborador['idColaborador']; ?>">
                              <?= htmlspecialchars($colaborador['nombre'] . ' ' . $colaborador['apellido1']); ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="jefe_id" class="form-label">Jefe</label>
                  <select class="form-select" id="jefe_id" name="jefe_id">
                      <option value="">Sin Jefe</option>
                      <?php foreach ($jefes as $jefe): ?>
                          <option value="<?= $jefe['idColaborador']; ?>">
                              <?= htmlspecialchars($jefe['nombre'] . ' ' . $jefe['apellido1']); ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="departamento_id" class="form-label">Departamento</label>
                  <select class="form-select" id="departamento_id" name="departamento_id" required>
                      <option value="">Seleccione un departamento</option>
                      <?php foreach ($departamentos as $departamento): ?>
                          <option value="<?= $departamento['idDepartamento']; ?>">
                              <?= htmlspecialchars($departamento['nombre']); ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="add_jerarquia" id="btnAddJerarquia" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Agregar</button>
            <button type="submit" name="edit_jerarquia" id="btnEditJerarquia" class="btn btn-primary" style="display:none;"><i class="bi bi-save me-1"></i>Guardar Cambios</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cerrar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Funciones para Deducciones
    function editarDeduccion(deduccion) {
        // Llenar el formulario con los datos de la deducción
        document.getElementById('modalDeduccionLabel').innerText = 'Editar Deducción';
        document.getElementById('idDeduccion').value = deduccion.idDeduccion;
        document.getElementById('descripcion').value = deduccion.descripcion;
        document.getElementById('porcentaje').value = deduccion.Porcentaje;
        document.getElementById('aplica_general').checked = deduccion.aplica_general == 1 ? true : false;

        // Mostrar botón de Editar y ocultar botón de Agregar
        document.getElementById('btnEditDeduction').style.display = 'inline-block';
        document.getElementById('btnAddDeduction').style.display = 'none';
    }

    function nuevaDeduccion() {
        // Limpiar el formulario
        document.getElementById('modalDeduccionLabel').innerText = 'Agregar Deducción';
        document.getElementById('idDeduccion').value = '';
        document.getElementById('descripcion').value = '';
        document.getElementById('porcentaje').value = '';
        document.getElementById('aplica_general').checked = false;

        // Mostrar botón de Agregar y ocultar botón de Editar
        document.getElementById('btnEditDeduction').style.display = 'none';
        document.getElementById('btnAddDeduction').style.display = 'inline-block';
    }

    // Funciones para Jerarquías
    function editarJerarquia(jerarquia) {
        // Llenar el formulario con los datos de la jerarquía
        document.getElementById('modalJerarquiaLabel').innerText = 'Editar Jerarquía';
        document.getElementById('idJerarquia').value = jerarquia.idJerarquia;
        document.getElementById('colaborador_id').value = jerarquia.Colaborador_idColaborador;
        document.getElementById('jefe_id').value = jerarquia.Jefe_idColaborador;
        // Seleccionar el departamento
        document.getElementById('departamento_id').value = jerarquia.Departamento_idDepartamento;

        // Mostrar botón de Editar y ocultar botón de Agregar
        document.getElementById('btnEditJerarquia').style.display = 'inline-block';
        document.getElementById('btnAddJerarquia').style.display = 'none';

        // Cambiar el título del modal
        document.getElementById('modalJerarquiaLabel').innerText = 'Editar Jerarquía';
    }

    function nuevaJerarquia() {
        // Limpiar el formulario
        document.getElementById('modalJerarquiaLabel').innerText = 'Agregar Jerarquía';
        document.getElementById('idJerarquia').value = '';
        document.getElementById('colaborador_id').value = '';
        document.getElementById('jefe_id').value = '';
        document.getElementById('departamento_id').value = '';

        // Mostrar botón de Agregar y ocultar botón de Editar
        document.getElementById('btnEditJerarquia').style.display = 'none';
        document.getElementById('btnAddJerarquia').style.display = 'inline-block';
    }
</script>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
