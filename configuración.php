<?php
session_start();
include 'db.php';

// Verificar si el usuario está autenticado y tiene el rol de administrador
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = 'success';
$configFilePath = 'js/configuracion.json';
$feriadosFilePath = 'js/feriados.json';

// --- LÓGICA DE GESTIÓN (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manejo de Configuración General
    if (isset($_POST['update_config'])) {
        $configData = [
            'nombre_empresa' => $_POST['nombre_empresa'],
            'tarifa_hora_extra' => floatval($_POST['tarifa_hora_extra'])
        ];
        if (file_put_contents($configFilePath, json_encode($configData, JSON_PRETTY_PRINT))) {
            $message = 'Configuración del sistema actualizada con éxito.';
        } else {
            $message = 'Error al guardar la configuración.';
            $message_type = 'danger';
        }
    }
    
    // --- LÓGICA PARA GESTIONAR FERIADOS ---
    $feriados = file_exists($feriadosFilePath) ? json_decode(file_get_contents($feriadosFilePath), true) : [];

    // Añadir feriado
    if (isset($_POST['add_feriado'])) {
        $nueva_fecha = $_POST['fecha_feriado'];
        $nueva_desc = trim($_POST['descripcion_feriado']);
        if (!empty($nueva_fecha) && !empty($nueva_desc)) {
            $fecha_existente = false;
            foreach ($feriados as $feriado) {
                if ($feriado['fecha'] === $nueva_fecha) {
                    $fecha_existente = true;
                    break;
                }
            }
            if (!$fecha_existente) {
                $feriados[] = ['fecha' => $nueva_fecha, 'descripcion' => $nueva_desc];
                usort($feriados, function($a, $b) {
                    return strtotime($a['fecha']) - strtotime($b['fecha']);
                });
                file_put_contents($feriadosFilePath, json_encode($feriados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $message = 'Feriado agregado con éxito.';
            } else {
                $message = 'Error: La fecha de este feriado ya existe.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Ambos campos (fecha y descripción) son obligatorios para agregar un feriado.';
            $message_type = 'danger';
        }
    }

    // Eliminar feriado
    if (isset($_POST['delete_feriado'])) {
        $fecha_a_eliminar = $_POST['fecha_a_eliminar'];
        $feriados = array_filter($feriados, function($feriado) use ($fecha_a_eliminar) {
            return $feriado['fecha'] !== $fecha_a_eliminar;
        });
        file_put_contents($feriadosFilePath, json_encode(array_values($feriados), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = 'Feriado eliminado con éxito.';
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
            if ($stmt->execute()) {
                $message = 'La deducción ha sido guardada con éxito.';
            } else {
                $message = 'Error al guardar la deducción.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
    
    // Eliminar Deducción
    if (isset($_POST['delete_deduction_id'])) {
        $id = intval($_POST['delete_deduction_id']);
        $stmt = $conn->prepare("DELETE FROM tipo_deduccion_cat WHERE idTipoDeduccion = ?");
        $stmt->bind_param("i", $id);
        $message = $stmt->execute() ? 'Deducción eliminada con éxito.' : 'Error al eliminar la deducción.';
        $message_type = $stmt->execute() ? 'success' : 'danger';
        $stmt->close();
    }
}

// --- OBTENCIÓN DE DATOS PARA LA VISTA ---
$configData = json_decode(file_get_contents($configFilePath), true);
$deducciones_raw = $conn->query("SELECT * FROM tipo_deduccion_cat ORDER BY Descripcion");
$feriados_actuales = file_exists($feriadosFilePath) ? json_decode(file_get_contents($feriadosFilePath), true) : [];
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
        .main-container { margin-left: 280px; padding: 2.5rem; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07); }
        .card-header { background-color: #ffffff; border-bottom: 1px solid #e9ecef; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .card-header h5 { font-weight: 600; color: #32325d; margin: 0; }
        .card-header h5 i { color: #5e72e4; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <h2 class="text-center mb-5" style="font-weight: 600;">Configuración y Mantenimientos</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Configuración General</h5></div>
                    </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-minus me-2"></i>Deducciones de Ley</h5><button class="btn btn-sm btn-outline-primary" onclick="openDeductionModal()"><i class="bi bi-plus"></i> Nueva Deducción</button></div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                            <?php mysqli_data_seek($deducciones_raw, 0); while ($deduccion = $deducciones_raw->fetch_assoc()): 
                                $parts = explode(':', $deduccion['Descripcion']);
                                $nombre_ded = htmlspecialchars($parts[0]);
                                $porcentaje_ded = isset($parts[1]) ? htmlspecialchars($parts[1]) : '0.00';
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div><?= $nombre_ded; ?></div>
                                <div>
                                    <span class="badge bg-light text-dark border me-2"><?= $porcentaje_ded; ?>%</span>
                                    <button class="btn btn-sm btn-light" onclick='openDeductionModal(<?= json_encode($deduccion); ?>)'><i class="bi bi-pencil text-primary"></i></button>
                                    <button class="btn btn-sm btn-light" onclick="confirmDelete('deduction', <?= $deduccion['idTipoDeduccion']; ?>)"><i class="bi bi-trash text-danger"></i></button>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-calendar-event-fill me-2"></i>Gestión de Feriados</h5></div>
                    <div class="card-body p-4">
                        <form method="POST" class="row g-2 mb-4 align-items-end">
                            <div class="col-sm-5"><label class="form-label fw-bold">Fecha</label><input type="date" class="form-control" name="fecha_feriado" required></div>
                            <div class="col-sm-5"><label class="form-label fw-bold">Descripción</label><input type="text" class="form-control" name="descripcion_feriado" required></div>
                            <div class="col-sm-2"><button type="submit" name="add_feriado" class="btn btn-primary w-100"><i class="bi bi-plus"></i></button></div>
                        </form>
                        <ul class="list-group list-group-flush">
                            <?php if (empty($feriados_actuales)): ?>
                                <li class="list-group-item text-muted text-center">No hay feriados configurados.</li>
                            <?php else: foreach ($feriados_actuales as $feriado): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div><?= htmlspecialchars(date('d/m/Y', strtotime($feriado['fecha']))) ?> - <strong><?= htmlspecialchars($feriado['descripcion']) ?></strong></div>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este feriado?');">
                                    <input type="hidden" name="fecha_a_eliminar" value="<?= $feriado['fecha'] ?>">
                                    <button type="submit" name="delete_feriado" class="btn btn-sm btn-light" title="Eliminar"><i class="bi bi-trash text-danger"></i></button>
                                </form>
                            </li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="deductionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><div class="modal-header"><h5 class="modal-title" id="deductionModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="save_deduction" value="1"><input type="hidden" name="idTipoDeduccion" id="idTipoDeduccion"><div class="mb-3"><label class="form-label">Nombre de Deducción</label><input type="text" class="form-control" name="nombre_deduccion" id="nombre_deduccion" required></div><div class="mb-3"><label class="form-label">Porcentaje (%)</label><input type="number" step="0.01" class="form-control" name="porcentaje" id="porcentaje" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div>
    
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">¿Seguro que deseas eliminar?</div><div class="modal-footer"><form method="POST" id="deleteForm"><input type="hidden" id="delete_id_input" name=""></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Sí, Eliminar</button></div></div></div></div>
    
    <?php include 'footer.php'; ?>
    <script>
        const deductionModal = new bootstrap.Modal(document.getElementById('deductionModal'));
        const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));

        function openDeductionModal(data = null) {
            const form = document.getElementById('deductionModal').querySelector('form');
            form.reset();
            const label = document.getElementById('deductionModalLabel');
            if (data) {
                const parts = data.Descripcion.split(':');
                label.innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Deducción';
                form.idTipoDeduccion.value = data.idTipoDeduccion;
                form.nombre_deduccion.value = parts[0];
                form.porcentaje.value = parts[1] || '0.00';
            } else {
                label.innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i>Nueva Deducción';
                form.idTipoDeduccion.value = '';
            }
            deductionModal.show();
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