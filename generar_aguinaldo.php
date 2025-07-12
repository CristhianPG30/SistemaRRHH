<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) { // Solo Admin y RRHH
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$mensaje = '';
$tipoMensaje = 'info';
$resultados_generados = [];
// El año seleccionado ahora será siempre el año actual.
$anio_seleccionado = date('Y');

// --- LÓGICA PARA ELIMINAR AGUINALDO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_anio'])) {
    $anio_a_eliminar = intval($_POST['eliminar_anio']);
    $stmt_delete = $conn->prepare("DELETE FROM aguinaldo WHERE periodo = ?");
    $stmt_delete->bind_param("i", $anio_a_eliminar);
    if ($stmt_delete->execute()) {
        $mensaje = "El registro de aguinaldo para el año $anio_a_eliminar ha sido eliminado correctamente.";
        $tipoMensaje = 'success';
    } else {
        $mensaje = "Error al intentar eliminar el registro del aguinaldo.";
        $tipoMensaje = 'danger';
    }
    $stmt_delete->close();
}

// Verificar si ya fue generado para el año actual
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM aguinaldo WHERE periodo = ?");
$stmt_check->bind_param("i", $anio_seleccionado);
$stmt_check->execute();
$stmt_check->bind_result($conteo);
$stmt_check->fetch();
$stmt_check->close();
$ya_generado = ($conteo > 0);

if ($ya_generado && $_SERVER['REQUEST_METHOD'] != 'POST') {
     $mensaje = 'El aguinaldo para el año ' . $anio_seleccionado . ' ya ha sido calculado previamente.';
     $tipoMensaje = 'warning';
}

// Procesar el formulario SOLO si se envía y si no se ha generado ya
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar']) && !$ya_generado) {
    // Definir el rango de fechas para el cálculo: desde el 1 de diciembre del año anterior al 30 de noviembre del año seleccionado.
    $fecha_inicio = ($anio_seleccionado - 1) . '-12-01';
    $fecha_fin = $anio_seleccionado . '-11-30';

    // Obtener la suma de salarios brutos para cada colaborador en el período
    $sql = "SELECT 
                p.id_colaborador_fk,
                pers.Nombre,
                pers.Apellido1,
                SUM(p.salario_bruto) as total_salarios
            FROM planillas p
            JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador
            JOIN persona pers ON c.id_persona_fk = pers.idPersona
            WHERE p.fecha_generacion BETWEEN ? AND ?
            GROUP BY p.id_colaborador_fk, pers.Nombre, pers.Apellido1";
    $stmt_salarios = $conn->prepare($sql);
    $stmt_salarios->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt_salarios->execute();
    $result_salarios = $stmt_salarios->get_result();

    $aguinaldos_a_insertar = [];
    while ($row = $result_salarios->fetch_assoc()) {
        $aguinaldo_calculado = $row['total_salarios'] / 12;
        if ($aguinaldo_calculado > 0) {
            $aguinaldos_a_insertar[] = [
                'id_colaborador' => $row['id_colaborador_fk'],
                'nombre_completo' => $row['Nombre'] . ' ' . $row['Apellido1'],
                'monto' => $aguinaldo_calculado
            ];
        }
    }
    $stmt_salarios->close();

    // Guardar los aguinaldos calculados en la base de datos
    if (!empty($aguinaldos_a_insertar)) {
        $conn->begin_transaction();
        try {
            // Eliminar registros previos para el mismo año para evitar duplicados al regenerar
            $stmt_delete_prev = $conn->prepare("DELETE FROM aguinaldo WHERE periodo = ?");
            $stmt_delete_prev->bind_param("i", $anio_seleccionado);
            $stmt_delete_prev->execute();
            $stmt_delete_prev->close();

            $stmt_insert = $conn->prepare("INSERT INTO aguinaldo (id_colaborador_fk, periodo, monto_calculado, monto_pagado, fecha_pago) VALUES (?, ?, ?, 0.00, ?)");
            $fecha_pago_defecto = $anio_seleccionado . '-12-20';
            
            foreach ($aguinaldos_a_insertar as $aguinaldo) {
                $stmt_insert->bind_param("iids", $aguinaldo['id_colaborador'], $anio_seleccionado, $aguinaldo['monto'], $fecha_pago_defecto);
                $stmt_insert->execute();
            }
            $conn->commit();
            $mensaje = '¡El cálculo de aguinaldos para el período ' . $anio_seleccionado . ' se ha completado y guardado exitosamente!';
            $tipoMensaje = 'success';
            $ya_generado = true;
            $resultados_generados = $aguinaldos_a_insertar;

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $mensaje = "Error al guardar los aguinaldos: " . $exception->getMessage();
            $tipoMensaje = 'danger';
        }
        $stmt_insert->close();
    } else {
        $mensaje = "No se encontraron salarios en el período de cálculo. No se generó ningún registro.";
        $tipoMensaje = 'info';
    }
}

// Obtener historial de aguinaldos generados
$historial_query = $conn->query("SELECT DISTINCT periodo FROM aguinaldo ORDER BY periodo DESC");
$historial_aguinaldos = $historial_query->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<?php include 'header.php'; ?>

<style>
.gen-agu-card {
    background: #fff;
    border-radius: 1.5rem;
    max-width: 800px;
    margin: 3rem auto;
    box-shadow: 0 6px 25px rgba(0,0,0,0.08);
    padding: 2.5rem;
}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="gen-agu-card">
        <div class="text-center">
            <i class="bi bi-calculator-fill text-primary" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <h2 class="card-title" style="font-weight: 700;">Generar Aguinaldos</h2>
            <p class="text-muted">
                Este módulo calculará el aguinaldo para el año en curso (<?php echo $anio_seleccionado; ?>).
                El sistema utilizará los salarios desde el 1 de diciembre del año anterior hasta el 30 de noviembre actual.
            </p>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> mt-4">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

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
                <h4 class="text-center mb-3">Resultados de la Generación de Aguinaldo <?= $anio_seleccionado ?></h4>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Colaborador</th>
                                <th class="text-end">Monto Calculado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_generados as $resultado): ?>
                            <tr>
                                <td><?= htmlspecialchars($resultado['nombre_completo']) ?></td>
                                <td class="text-end">₡<?= number_format($resultado['monto'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="gen-agu-card">
        <h4 class="text-center mb-4"><i class="bi bi-archive-fill me-2"></i>Historial de Aguinaldos Generados</h4>
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
                                <a href="descargar_aguinaldo.php?anio=<?= $historial['periodo'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-earmark-excel"></i> Descargar
                                </a>
                                <button class="btn btn-sm btn-outline-danger" onclick="confirmarEliminacion(<?= $historial['periodo'] ?>)">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
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

<script>
function confirmarEliminacion(anio) {
    if (confirm(`¿Estás seguro de que deseas eliminar el registro de aguinaldo del año ${anio}? Esta acción no se puede deshacer.`)) {
        document.getElementById('eliminarAnioInput').value = anio;
        document.getElementById('formEliminar').submit();
    }
}
</script>

<?php include 'footer.php'; ?>