<?php
session_start();
include 'db.php'; // Conexión a la base de datos

// Verificar si el usuario está autenticado y tiene el rol de administrador
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';
$configFilePath = 'js/configuracion.json';

// --- LÓGICA DE GESTIÓN (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manejo de Configuración General
    if (isset($_POST['update_config'])) {
        $configData = ['nombre_empresa' => $_POST['nombre_empresa'], 'tarifa_hora_extra' => floatval($_POST['tarifa_hora_extra'])];
        if (file_put_contents($configFilePath, json_encode($configData, JSON_PRETTY_PRINT))) {
            $message = 'Configuración del sistema actualizada con éxito.';
            $message_type = 'success';
        } else {
            $message = 'Error al guardar la configuración.';
            $message_type = 'danger';
        }
    }
    // Manejo de Deducciones
    if (isset($_POST['save_deduction'])) {
        $id = intval($_POST['idTipoDeduccion']);
        $nombre_deduccion = trim($_POST['nombre_deduccion']);
        $porcentaje = floatval($_POST['porcentaje']);
        if (empty($nombre_deduccion) || $porcentaje < 0) {
            $message = 'El nombre y un porcentaje válido son requeridos.';
            $message_type = 'danger';
        } else {
            $descripcion_db = $nombre_deduccion . ':' . $porcentaje;
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tipo_deduccion_cat SET Descripcion = ? WHERE idTipoDeduccion = ?");
                $stmt->bind_param("si", $descripcion_db, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO tipo_deduccion_cat (Descripcion) VALUES (?)");
                $stmt->bind_param("s", $descripcion_db);
            }
            if ($stmt->execute()) { $message = 'La deducción ha sido guardada con éxito.'; $message_type = 'success'; }
            else { $message = 'Error al guardar la deducción.'; $message_type = 'danger'; }
            $stmt->close();
        }
    }
    if (isset($_POST['delete_deduction_id'])) {
        $id = intval($_POST['delete_deduction_id']);
        $stmt = $conn->prepare("DELETE FROM tipo_deduccion_cat WHERE idTipoDeduccion = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) { $message = 'Deducción eliminada con éxito.'; $message_type = 'success'; }
        else { $message = 'Error al eliminar la deducción.'; $message_type = 'danger'; }
        $stmt->close();
    }
    // Manejo de Jerarquías
    if (isset($_POST['save_jerarquia'])) {
        $id = intval($_POST['idJerarquia']);
        $colaborador_id = intval($_POST['colaborador_id']);
        $jefe_id = empty($_POST['jefe_id']) ? null : intval($_POST['jefe_id']);
        $departamento_id = intval($_POST['departamento_id']);
        if ($colaborador_id === $jefe_id) {
            $message = 'Un colaborador no puede ser su propio jefe.';
            $message_type = 'danger';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE jerarquia SET Colaborador_idColaborador = ?, Jefe_idColaborador = ?, Departamento_idDepartamento = ? WHERE idJerarquia = ?");
                $stmt->bind_param("iiii", $colaborador_id, $jefe_id, $departamento_id, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO jerarquia (Colaborador_idColaborador, Jefe_idColaborador, Departamento_idDepartamento) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $colaborador_id, $jefe_id, $departamento_id);
            }
            if ($stmt->execute()) { $message = 'Jerarquía guardada con éxito.'; $message_type = 'success'; }
            else { $message = 'Error al guardar la jerarquía. Es posible que el colaborador ya esté asignado.'; $message_type = 'danger'; }
            $stmt->close();
        }
    }
    if (isset($_POST['delete_jerarquia_id'])) {
        $id = intval($_POST['delete_jerarquia_id']);
        $stmt = $conn->prepare("DELETE FROM jerarquia WHERE idJerarquia = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) { $message = 'Jerarquía eliminada con éxito.'; $message_type = 'success'; }
        else { $message = 'Error al eliminar la jerarquía.'; $message_type = 'danger'; }
        $stmt->close();
    }
}

// --- OBTENCIÓN DE DATOS PARA LA VISTA ---
$configData = json_decode(file_get_contents($configFilePath), true);
$deducciones_raw = $conn->query("SELECT * FROM tipo_deduccion_cat ORDER BY Descripcion");
$jerarquias = $conn->query("SELECT j.idJerarquia, j.Colaborador_idColaborador, j.Jefe_idColaborador, j.Departamento_idDepartamento, CONCAT(p.Nombre, ' ', p.Apellido1) AS colaborador_nombre, CONCAT(jefe_p.Nombre, ' ', jefe_p.Apellido1) AS jefe_nombre, d.nombre AS departamento_nombre FROM jerarquia j JOIN colaborador c ON j.Colaborador_idColaborador = c.idColaborador JOIN persona p ON c.id_persona_fk = p.idPersona LEFT JOIN colaborador jefe_c ON j.Jefe_idColaborador = jefe_c.idColaborador LEFT JOIN persona jefe_p ON jefe_c.id_persona_fk = jefe_p.idPersona JOIN departamento d ON j.Departamento_idDepartamento = d.idDepartamento ORDER BY d.nombre, jefe_nombre, p.Nombre");
$colaboradores = $conn->query("SELECT c.idColaborador, CONCAT(p.Nombre, ' ', p.Apellido1) AS nombre_completo FROM colaborador c JOIN persona p ON c.id_persona_fk = p.idPersona WHERE c.activo = 1 ORDER BY p.Nombre");
$departamentos = $conn->query("SELECT d.* FROM departamento d JOIN estado_cat e ON d.id_estado_fk = e.idEstado WHERE e.Descripcion = 'activo' ORDER BY d.nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fc; }
        .card { 
            border: none;
            border-radius: 1rem; 
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05);
            transition: all 0.3s ease-in-out;
            height: 100%;
        }
        .card:hover { transform: translateY(-5px); }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h5 {
            font-weight: 600;
            color: #343a40;
            margin: 0;
            display: flex;
            align-items: center;
        }
        .card-header h5 i {
            color: #4e73df;
            font-size: 1.5rem;
        }
        .list-group-item .badge { font-size: 0.9em; }

        /* Estilos mejorados para la tarjeta de Jerarquías */
        .jerarquia-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .jerarquia-item:last-child {
            border-bottom: none;
        }
        .colaborador-info {
            font-weight: 600;
            color: #212529;
        }
        .departamento-info {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .jefe-info {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #495057;
        }
        .jefe-info i {
            margin-right: 0.5rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5 mb-5">
        <h2 class="text-center mb-5" style="font-weight: 600;">Configuración y Mantenimientos</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Columna de Configuración General y Deducciones -->
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>General</h5></div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="update_config" value="1">
                            <div class="mb-3"><label for="nombre_empresa" class="form-label fw-bold">Nombre de Empresa</label><input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" value="<?php echo htmlspecialchars($configData['nombre_empresa']); ?>" required></div>
                            <div class="mb-3"><label for="tarifa_hora_extra" class="form-label fw-bold">Tarifa por Hora Extra (₡)</label><input type="number" step="0.01" class="form-control" id="tarifa_hora_extra" name="tarifa_hora_extra" value="<?php echo htmlspecialchars($configData['tarifa_hora_extra']); ?>" required></div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Guardar</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-minus me-2"></i>Deducciones</h5><button class="btn btn-sm btn-outline-primary" onclick="openDeductionModal()"><i class="bi bi-plus"></i></button></div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                            <?php if ($deducciones_raw && $deducciones_raw->num_rows > 0): ?>
                                <?php while ($deduccion = $deducciones_raw->fetch_assoc()): 
                                    $parts = explode(':', $deduccion['Descripcion']);
                                    $nombre_ded = htmlspecialchars($parts[0]);
                                    $porcentaje_ded = isset($parts[1]) ? htmlspecialchars($parts[1]) : '0.00';
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo $nombre_ded; ?> <span class="badge bg-light text-dark border"><?php echo $porcentaje_ded; ?>%</span>
                                        <div>
                                            <button class="btn btn-sm btn-light" onclick='openDeductionModal(<?php echo json_encode($deduccion); ?>)'><i class="bi bi-pencil-fill text-primary"></i></button>
                                            <button class="btn btn-sm btn-light" onclick="confirmDelete('deduction', <?php echo $deduccion['idTipoDeduccion']; ?>)"><i class="bi bi-trash-fill text-danger"></i></button>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="list-group-item text-muted text-center p-3">No hay deducciones definidas.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Columna de Jerarquías -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-diagram-3-fill me-2"></i>Jerarquías</h5><button class="btn btn-sm btn-outline-primary" onclick="openJerarquiaModal()"><i class="bi bi-plus"></i></button></div>
                    <div class="card-body p-3">
                        <?php if ($jerarquias && $jerarquias->num_rows > 0): ?>
                            <?php while ($jerarquia = $jerarquias->fetch_assoc()): ?>
                                <div class="jerarquia-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="colaborador-info"><?php echo htmlspecialchars($jerarquia['colaborador_nombre']); ?></div>
                                        <div class="departamento-info"><?php echo htmlspecialchars($jerarquia['departamento_nombre']); ?></div>
                                        <div class="jefe-info mt-1">
                                            <i class="bi bi-arrow-return-right"></i>
                                            <span>Jefe: <?php echo htmlspecialchars($jerarquia['jefe_nombre'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-light" onclick='openJerarquiaModal(<?php echo json_encode($jerarquia); ?>)'><i class="bi bi-pencil-fill text-primary"></i></button>
                                        <button class="btn btn-sm btn-light" onclick="confirmDelete('jerarquia', <?php echo $jerarquia['idJerarquia']; ?>)"><i class="bi bi-trash-fill text-danger"></i></button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center text-muted p-5">No hay jerarquías definidas.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div class="modal fade" id="deductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="deductionForm" method="POST"><div class="modal-header"><h5 class="modal-title" id="deductionModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="save_deduction" value="1"><input type="hidden" name="idTipoDeduccion" id="idTipoDeduccion"><div class="mb-3"><label for="nombre_deduccion" class="form-label">Nombre</label><input type="text" class="form-control" name="nombre_deduccion" id="nombre_deduccion" required></div><div class="mb-3"><label for="porcentaje" class="form-label">Porcentaje (%)</label><input type="number" step="0.01" class="form-control" name="porcentaje" id="porcentaje" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
    <div class="modal fade" id="jerarquiaModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="jerarquiaForm" method="POST"><div class="modal-header"><h5 class="modal-title" id="jerarquiaModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="save_jerarquia" value="1"><input type="hidden" name="idJerarquia" id="idJerarquia"><div class="mb-3"><label for="colaborador_id" class="form-label">Colaborador</label><select class="form-select" name="colaborador_id" id="colaborador_id" required><?php mysqli_data_seek($colaboradores, 0); while ($c = $colaboradores->fetch_assoc()) echo "<option value='{$c['idColaborador']}'>".htmlspecialchars($c['nombre_completo'])."</option>"; ?></select></div><div class="mb-3"><label for="jefe_id" class="form-label">Jefe Directo</label><select class="form-select" name="jefe_id" id="jefe_id"><option value="">-- Sin Jefe --</option><?php mysqli_data_seek($colaboradores, 0); while ($c = $colaboradores->fetch_assoc()) echo "<option value='{$c['idColaborador']}'>".htmlspecialchars($c['nombre_completo'])."</option>"; ?></select></div><div class="mb-3"><label for="departamento_id" class="form-label">Departamento</label><select class="form-select" name="departamento_id" id="departamento_id" required><?php mysqli_data_seek($departamentos, 0); while ($d = $departamentos->fetch_assoc()) echo "<option value='{$d['idDepartamento']}'>".htmlspecialchars($d['nombre'])."</option>"; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Estás seguro de que deseas eliminar este registro? Esta acción es irreversible.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Sí, Eliminar</button></div></div></div></div>
    <form method="POST" id="deleteForm" style="display:none;"><input type="hidden" id="delete_id_input" name=""></form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deductionModal = new bootstrap.Modal(document.getElementById('deductionModal'));
        const jerarquiaModal = new bootstrap.Modal(document.getElementById('jerarquiaModal'));
        const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));

        function openDeductionModal(data = null) {
            const form = document.getElementById('deductionForm'); form.reset();
            if (data) {
                const parts = data.Descripcion.split(':');
                document.getElementById('deductionModalLabel').innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Deducción';
                document.getElementById('idTipoDeduccion').value = data.idTipoDeduccion;
                document.getElementById('nombre_deduccion').value = parts[0];
                document.getElementById('porcentaje').value = parts[1] || '0.00';
            } else {
                document.getElementById('deductionModalLabel').innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i>Nueva Deducción';
                document.getElementById('idTipoDeduccion').value = '';
            }
            deductionModal.show();
        }

        function openJerarquiaModal(data = null) {
            const form = document.getElementById('jerarquiaForm'); form.reset();
            if (data) {
                document.getElementById('jerarquiaModalLabel').innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Jerarquía';
                document.getElementById('idJerarquia').value = data.idJerarquia;
                document.getElementById('colaborador_id').value = data.Colaborador_idColaborador;
                document.getElementById('jefe_id').value = data.Jefe_idColaborador || '';
                document.getElementById('departamento_id').value = data.Departamento_idDepartamento;
            } else {
                document.getElementById('jerarquiaModalLabel').innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i>Nueva Jerarquía';
                document.getElementById('idJerarquia').value = '';
            }
            jerarquiaModal.show();
        }

        function confirmDelete(type, id) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const deleteForm = document.getElementById('deleteForm');
            const input = document.getElementById('delete_id_input');
            confirmBtn.onclick = function() {
                input.name = `delete_${type}_id`;
                input.value = id;
                deleteForm.submit();
            };
            confirmDeleteModal.show();
        }
    </script>
</body>
</html>
