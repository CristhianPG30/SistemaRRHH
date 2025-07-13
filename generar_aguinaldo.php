<?php
session_start();
// Restringe el acceso solo a roles de Administrador (1) y RRHH (4)
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Ya no se necesitan variables de mensaje, la acción se reflejará al recargar.
$resultados_generados = [];
$anio_seleccionado = date('Y');

// --- LÓGICA PARA ELIMINAR AGUINALDO (se activa con el formulario POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_anio'])) {
    $anio_a_eliminar = intval($_POST['eliminar_anio']);
    $stmt_delete = $conn->prepare("DELETE FROM aguinaldo WHERE periodo = ?");
    $stmt_delete->bind_param("i", $anio_a_eliminar);
    $stmt_delete->execute();
    $stmt_delete->close();
    // Redirigir para limpiar el POST y evitar reenvíos de formulario
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- VERIFICACIÓN SI EL AGUINALDO DEL AÑO ACTUAL YA FUE GENERADO ---
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM aguinaldo WHERE periodo = ?");
$stmt_check->bind_param("i", $anio_seleccionado);
$stmt_check->execute();
$stmt_check->bind_result($conteo);
$stmt_check->fetch();
$stmt_check->close();
$ya_generado = ($conteo > 0);

// --- LÓGICA PRINCIPAL PARA GENERAR EL AGUINALDO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar']) && !$ya_generado) {
    $fecha_inicio = ($anio_seleccionado - 1) . '-12-01';
    $fecha_fin = $anio_seleccionado . '-11-30';

    $sql = "SELECT p.id_colaborador_fk, pers.Nombre, pers.Apellido1, SUM(p.salario_bruto) as total_salarios FROM planillas p JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador JOIN persona pers ON c.id_persona_fk = pers.idPersona WHERE p.fecha_generacion BETWEEN ? AND ? GROUP BY p.id_colaborador_fk, pers.Nombre, pers.Apellido1";
    $stmt_salarios = $conn->prepare($sql);
    $stmt_salarios->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt_salarios->execute();
    $result_salarios = $stmt_salarios->get_result();

    $aguinaldos_a_procesar = [];
    while ($row = $result_salarios->fetch_assoc()) {
        $aguinaldo_calculado = $row['total_salarios'] / 12;
        if ($aguinaldo_calculado > 0) {
            $detalle_mensual = [];
            $sql_detalle = "SELECT DATE_FORMAT(fecha_generacion, '%Y-%m') as mes, SUM(salario_bruto) as total_mes FROM planillas WHERE id_colaborador_fk = ? AND fecha_generacion BETWEEN ? AND ? GROUP BY mes ORDER BY mes";
            $stmt_det = $conn->prepare($sql_detalle);
            $stmt_det->bind_param("iss", $row['id_colaborador_fk'], $fecha_inicio, $fecha_fin);
            $stmt_det->execute();
            $res_det = $stmt_det->get_result();
            while ($fila = $res_det->fetch_assoc()) { $detalle_mensual[] = $fila; }
            $stmt_det->close();

            $aguinaldos_a_procesar[] = [
                'id_colaborador' => $row['id_colaborador_fk'], 'nombre_completo' => $row['Nombre'] . ' ' . $row['Apellido1'],
                'monto' => $aguinaldo_calculado, 'total_salarios' => $row['total_salarios'], 'detalle_mensual' => $detalle_mensual
            ];
        }
    }
    $stmt_salarios->close();

    if (!empty($aguinaldos_a_procesar)) {
        $conn->begin_transaction();
        try {
            $stmt_delete_prev = $conn->prepare("DELETE FROM aguinaldo WHERE periodo = ?");
            $stmt_delete_prev->bind_param("i", $anio_seleccionado);
            $stmt_delete_prev->execute();
            $stmt_delete_prev->close();

            $stmt_insert = $conn->prepare("INSERT INTO aguinaldo (id_colaborador_fk, periodo, monto_calculado, monto_pagado, fecha_pago) VALUES (?, ?, ?, 0.00, ?)");
            $fecha_pago_defecto = $anio_seleccionado . '-12-20';
            foreach ($aguinaldos_a_procesar as $aguinaldo) {
                $stmt_insert->bind_param("iids", $aguinaldo['id_colaborador'], $anio_seleccionado, $aguinaldo['monto'], $fecha_pago_defecto);
                $stmt_insert->execute();
            }
            $conn->commit();
            $ya_generado = true;
            $resultados_generados = $aguinaldos_a_procesar;
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
        }
        $stmt_insert->close();
    }
}

// --- OBTENER HISTORIAL DE AGUINALDOS YA GENERADOS ---
$historial_query = $conn->query("SELECT DISTINCT periodo FROM aguinaldo ORDER BY periodo DESC");
$historial_aguinaldos = $historial_query->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<?php include 'header.php'; ?>

<style>
/* Estilos existentes */
.gen-agu-card {
    background: #fff;
    border-radius: 1.5rem;
    max-width: 950px;
    margin: 3rem auto;
    box-shadow: 0 6px 25px rgba(0,0,0,0.08);
    padding: 2.5rem;
}
.modal-header, .modal-footer {
    border-color: #dee2e6;
}

/* ======== CSS PARA EL CUADRO DE DIÁLOGO PERSONALIZADO ======== */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none; /* Oculto por defecto */
    align-items: center;
    justify-content: center;
    z-index: 1050; /* Encima de otros elementos */
}
.custom-modal-content {
    background: #fff;
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    text-align: center;
    max-width: 400px;
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.custom-modal-content h4 {
    margin-top: 0;
    font-weight: 600;
}
.custom-modal-content p {
    margin: 1rem 0;
    color: #6c757d;
}
.custom-modal-buttons {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
    gap: 1rem;
}
.custom-modal-buttons button {
    border: none;
    padding: 0.6rem 1.2rem;
    border-radius: 0.25rem;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}
.custom-modal-buttons button:hover {
    opacity: 0.8;
}
.btn-confirm-delete {
    background-color: #d33;
    color: white;
}
.btn-cancel-delete {
    background-color: #f0f0f0;
    color: #333;
}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="gen-agu-card">
        <div class="text-center">
            <i class="bi bi-calculator-fill text-primary" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <h2 class="card-title" style="font-weight: 700;">Generar Aguinaldos</h2>
            <p class="text-muted">
                El cálculo se basa en la Ley 2412 de Costa Rica:<br>
                <b>Período:</b> <span class="text-primary"><?= date('d/m/Y', strtotime(($anio_seleccionado-1).'-12-01')) ?></span> al
                <span class="text-primary"><?= date('d/m/Y', strtotime($anio_seleccionado.'-11-30')) ?></span>.<br>
                <b>Fórmula:</b> (Suma de salarios brutos del período) / 12 = Aguinaldo
            </p>
        </div>
        
        <form method="POST" action="generar_aguinaldo.php" class="mt-4 border-top pt-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <label for="anio" class="form-label fw-bold">Año a Generar:</label>
                    <input type="text" class="form-control" id="anio" name="anio" value="<?= $anio_seleccionado ?>" readonly>
                </div>
                <div class="col-md-4 mt-3 mt-md-0 text-center">
                    <button type="submit" name="generar" class="btn btn-primary w-100" <?= $ya_generado ? 'disabled' : '' ?>>
                        <i class="bi bi-play-circle-fill me-2"></i>
                        <?= $ya_generado ? 'Cálculo ya realizado' : 'Generar Aguinaldo ' . $anio_seleccionado ?>
                    </button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($resultados_generados)): ?>
            <div class="mt-5">
                <h4 class="text-center mb-3">Detalle del Aguinaldo Generado para <?= $anio_seleccionado ?></h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Colaborador</th>
                                <th class="text-end">Total Salarios Brutos</th>
                                <th class="text-end">Aguinaldo Calculado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_generados as $resultado): ?>
                            <tr>
                                <td><?= htmlspecialchars($resultado['nombre_completo']) ?></td>
                                <td class="text-end">₡<?= number_format($resultado['total_salarios'], 2) ?></td>
                                <td class="text-end fw-bold text-success">₡<?= number_format($resultado['monto'], 2) ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick='mostrarDetalle(<?= json_encode($resultado, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-eye-fill"></i> Ver Detalle
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="gen-agu-card">
        <h4 class="text-center mb-4"><i class="bi bi-archive-fill me-2"></i>Historial de Aguinaldos</h4>
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th>Período (Año)</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historial_aguinaldos)): ?>
                        <?php foreach ($historial_aguinaldos as $historial): ?>
                        <tr>
                            <td><?= htmlspecialchars($historial['periodo']) ?></td>
                            <td class="text-end">
                                <a href="descargar_aguinaldo.php?anio=<?= $historial['periodo'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Descargar</a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarEliminacion(<?= $historial['periodo'] ?>)"><i class="bi bi-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">Aún no se han generado aguinaldos.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="formEliminar" method="POST" action="generar_aguinaldo.php" style="display:none;">
    <input type="hidden" name="eliminar_anio" id="eliminarAnioInput">
</form>

<div class="modal fade" id="modalDetalleAguinaldo" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleLabel">Detalle de Aguinaldo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="mb-3">Colaborador: <span id="detalleNombre" class="fw-bold"></span></h6>
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr class="table-light"><th colspan="2" class="text-center">Resumen del Cálculo</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total Salarios Brutos (Período)</td>
                            <td class="text-end" id="detalleTotalSalarios"></td>
                        </tr>
                        <tr>
                            <td><b>Aguinaldo Calculado (/ 12)</b></td>
                            <td class="text-end fw-bold text-success" id="detalleMontoAguinaldo"></td>
                        </tr>
                    </tbody>
                </table>
                <h6 class="mt-4 mb-3 text-center">Desglose de Salarios Mensuales</h6>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr><th>Mes</th><th class="text-end">Salario Bruto del Mes</th></tr>
                        </thead>
                        <tbody id="detalleTablaMeses"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>


<div id="customConfirmModal" class="custom-modal-overlay">
    <div class="custom-modal-content">
        <h4>Confirmar Eliminación</h4>
        <p id="customConfirmMessage">¿Estás seguro? Esta acción es irreversible.</p>
        <div class="custom-modal-buttons">
            <button id="btnCancel" class="btn-cancel-delete">Cancelar</button>
            <button id="btnConfirm" class="btn-confirm-delete">Sí, eliminar</button>
        </div>
    </div>
</div>


<script>
// Función para mostrar detalle de Bootstrap (sin cambios)
function mostrarDetalle(data) {
    const currencyFormatter = new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC' });
    document.getElementById('detalleNombre').textContent = data.nombre_completo;
    document.getElementById('detalleTotalSalarios').textContent = currencyFormatter.format(data.total_salarios);
    document.getElementById('detalleMontoAguinaldo').textContent = currencyFormatter.format(data.monto);

    const tablaMeses = document.getElementById('detalleTablaMeses');
    tablaMeses.innerHTML = ''; 

    if (data.detalle_mensual && data.detalle_mensual.length > 0) {
        data.detalle_mensual.forEach(detalle => {
            const row = tablaMeses.insertRow();
            row.innerHTML = `<td>${detalle.mes}</td><td class="text-end">${currencyFormatter.format(detalle.total_mes)}</td>`;
        });
    } else {
        const row = tablaMeses.insertRow();
        row.innerHTML = `<td colspan="2" class="text-center text-muted">No hay desglose mensual disponible.</td>`;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetalleAguinaldo'));
    modal.show();
}

// JAVASCRIPT PARA CONTROLAR EL CUADRO DE DIÁLOGO PERSONALIZADO
function confirmarEliminacion(anio) {
    const modal = document.getElementById('customConfirmModal');
    const message = document.getElementById('customConfirmMessage');
    const btnConfirm = document.getElementById('btnConfirm');
    const btnCancel = document.getElementById('btnCancel');

    // Actualiza el mensaje con el año específico
    message.textContent = `Se eliminará el registro de aguinaldo del año ${anio}. ¡Esta acción no se puede revertir!`;

    // Muestra el cuadro de diálogo
    modal.style.display = 'flex';

    // Acción al confirmar
    btnConfirm.onclick = function() {
        document.getElementById('eliminarAnioInput').value = anio;
        document.getElementById('formEliminar').submit();
    };

    // Acción al cancelar
    btnCancel.onclick = function() {
        modal.style.display = 'none';
    };

    // Opcional: cerrar si se hace clic en el fondo oscuro
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
}
</script>

<?php include 'footer.php'; ?>