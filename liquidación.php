<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) { // Solo Admin y RRHH
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$msg = "";
$msg_type = "success";
$colaborador_seleccionado = null;
$salario_promedio_ult_6_meses = 0;
$aguinaldo_acumulado = 0;
$ultimos_salarios = [];

// --- Lógica de Filtros ---
$filtro_depto = $_GET['filtro_depto'] ?? '';

// --- Lógica de Eliminación ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $idLiquidacion = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM liquidaciones WHERE idLiquidacion = ?");
    $stmt->bind_param("i", $idLiquidacion);
    if ($stmt->execute()) {
        $msg = "Liquidación eliminada con éxito.";
        $msg_type = 'success';
    } else {
        $msg = "Error al eliminar la liquidación.";
        $msg_type = 'danger';
    }
    $stmt->close();
}

// Obtener colaboradores y verificar si ya tienen liquidación
$colaboradores = [];
$liquidaciones_existentes = array_column($conn->query("SELECT id_colaborador_fk FROM liquidaciones")->fetch_all(MYSQLI_ASSOC), 'id_colaborador_fk');
$departamentos = $conn->query("SELECT idDepartamento, nombre FROM departamento WHERE id_estado_fk = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$sql_colaboradores = "SELECT c.idColaborador, p.Nombre, p.Apellido1, c.id_departamento_fk, c.fecha_ingreso, c.salario_bruto 
                      FROM colaborador c 
                      JOIN persona p ON c.id_persona_fk = p.idPersona 
                      WHERE 1=1";
$params = [];
$types = "";
if (!empty($filtro_depto)) {
    $sql_colaboradores .= " AND c.id_departamento_fk = ?";
    $params[] = $filtro_depto;
    $types .= "i";
}
$sql_colaboradores .= " ORDER BY p.Nombre ASC";
$stmt_colaboradores = $conn->prepare($sql_colaboradores);
if($types) {
    $stmt_colaboradores->bind_param($types, ...$params);
}
$stmt_colaboradores->execute();
$res_colaboradores = $stmt_colaboradores->get_result();
while($row = $res_colaboradores->fetch_assoc()){
    $row['liquidado'] = in_array($row['idColaborador'], $liquidaciones_existentes);
    $colaboradores[] = $row;
}
$stmt_colaboradores->close();


if (isset($_GET['colaborador']) && !empty($_GET['colaborador'])) {
    $idc = intval($_GET['colaborador']);
    foreach ($colaboradores as $c) {
        if ($c['idColaborador'] == $idc) {
            $colaborador_seleccionado = $c;
            
            // CÁLCULO DEL SALARIO PROMEDIO DE LOS ÚLTIMOS 6 MESES
            $stmt_avg = $conn->prepare("SELECT salario_bruto, fecha_generacion FROM (SELECT salario_bruto, fecha_generacion FROM planillas WHERE id_colaborador_fk = ? ORDER BY fecha_generacion DESC LIMIT 6) as ultimos_salarios");
            $stmt_avg->bind_param("i", $idc);
            $stmt_avg->execute();
            $res_avg = $stmt_avg->get_result();
            if($res_avg->num_rows > 0) {
                while($row_salario = $res_avg->fetch_assoc()) $ultimos_salarios[] = $row_salario;
                $salario_promedio_ult_6_meses = array_sum(array_column($ultimos_salarios, 'salario_bruto')) / count($ultimos_salarios);
            } else {
                $salario_promedio_ult_6_meses = $c['salario_bruto'];
            }
            $stmt_avg->close();
            
            // CÁLCULO DEL AGUINALDO ACUMULADO
            $fecha_salida = $_GET['fecha_salida'] ?? date('Y-m-d');
            $ano_salida = date('Y', strtotime($fecha_salida));
            $mes_salida = date('n', strtotime($fecha_salida));
            $fecha_inicio_aguinaldo = ($mes_salida == 12) ? $ano_salida . '-12-01' : ($ano_salida - 1) . '-12-01';
            
            $stmt_ag = $conn->prepare("SELECT SUM(salario_bruto) as total_salarios FROM planillas WHERE id_colaborador_fk = ? AND fecha_generacion BETWEEN ? AND ?");
            $stmt_ag->bind_param("iss", $idc, $fecha_inicio_aguinaldo, $fecha_salida);
            $stmt_ag->execute();
            $aguinaldo_acumulado = ($stmt_ag->get_result()->fetch_assoc()['total_salarios'] ?? 0) / 12;
            $stmt_ag->close();
            
            break;
        }
    }
}

// (El resto de funciones PHP como calcular_liquidacion_cr, lógica de guardado y eliminación permanecen igual)
function calcular_liquidacion_cr($salario_promedio, $fecha_ingreso, $fecha_salida, $motivo, $aguinaldo_proporcional) {
    if (!$salario_promedio || empty($fecha_ingreso) || empty($fecha_salida)) return ['dias_laborados' => 0, 'preaviso' => 0, 'cesantia' => 0, 'vacaciones' => 0, 'aguinaldo' => 0, 'desglose_cesantia' => []];
    $dias_laborados = (strtotime($fecha_salida) - strtotime($fecha_ingreso)) / 86400;
    $meses_laborados = $dias_laborados / 30.417; 
    $salario_diario = $salario_promedio / 30;
    $preaviso = 0;
    if ($motivo === "Despido con responsabilidad patronal") {
        if ($meses_laborados >= 3 && $meses_laborados < 6) $preaviso = $salario_diario * 7;
        elseif ($meses_laborados >= 6 && $meses_laborados < 12) $preaviso = $salario_diario * 15;
        elseif ($meses_laborados >= 12) $preaviso = $salario_promedio;
    }
    $cesantia = 0;
    $desglose_cesantia = [];
    if ($motivo === "Despido con responsabilidad patronal") {
        $anos_completos = floor($meses_laborados / 12);
        $dias_cesantia_total = 0;
        $tabla_dias_cesantia = [ 1 => 19.5, 2 => 20, 3 => 20.5, 4 => 21, 5 => 21.25, 6 => 21.5, 7 => 22, 8 => 22 ];
        if ($meses_laborados >= 3 && $meses_laborados < 6) {
             $dias_cesantia_total = 7;
             $desglose_cesantia[] = ['anio' => 'De 3 a 6 meses', 'dias' => 7, 'monto' => 7 * $salario_diario];
        } elseif ($meses_laborados >= 6 && $meses_laborados < 12) {
            $dias_cesantia_total = 14;
            $desglose_cesantia[] = ['anio' => 'De 6 a 12 meses', 'dias' => 14, 'monto' => 14 * $salario_diario];
        } elseif ($meses_laborados >= 12) {
            for ($i = 1; $i <= min($anos_completos, 8); $i++) {
                $dias_anuales = $tabla_dias_cesantia[$i] ?? 22;
                $monto_anual = $dias_anuales * $salario_diario;
                $desglose_cesantia[] = ['anio' => "Año $i", 'dias' => $dias_anuales, 'monto' => $monto_anual];
                $dias_cesantia_total += $dias_anuales;
            }
        }
        $cesantia = $dias_cesantia_total * $salario_diario;
    }
    $vacaciones = ($meses_laborados * 1) * $salario_diario;
    return [ 'dias_laborados' => round($dias_laborados), 'preaviso' => round($preaviso, 2), 'cesantia' => round($cesantia, 2), 'vacaciones' => round($vacaciones, 2), 'aguinaldo' => round($aguinaldo_proporcional, 2), 'desglose_cesantia' => $desglose_cesantia ];
}

$historial_liquidaciones = $conn->query("SELECT l.idLiquidacion, l.fecha_liquidacion, l.monto_neto, p.Nombre, p.Apellido1 FROM liquidaciones l JOIN colaborador c ON l.id_colaborador_fk = c.idColaborador JOIN persona p ON c.id_persona_fk = p.idPersona ORDER BY l.fecha_liquidacion DESC")->fetch_all(MYSQLI_ASSOC);

?>

<?php include 'header.php'; ?>
<style>
.liq-card { background: #fff; border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(19, 198, 241, 0.15); padding: 2rem; }
.liq-title { font-weight: 900; color: #18a8e0; letter-spacing: .5px; margin-bottom: 1.2rem; text-align:center; }
.data-label { font-weight: 600; color: #526484; }
.data-value { font-weight: 500; color: #364a63; }
.liq-table th, .liq-table td { padding: .4rem .6rem; text-align: right; vertical-align: middle; font-size: 1rem; }
.liq-table th { background: #f2fbff; color: #0d6797; font-weight:700; text-align: left; }
.info-btn { cursor: pointer; color: #17a2b8; }
.filter-card { background-color: #f8f9fa; border: 1px solid #e9ecef; }
</style>

<div class="container" style="margin-left: 280px; padding-top: 2rem; padding-bottom: 2rem;">
    <div class="row gx-lg-4">
        <div class="col-lg-7 mb-4">
            <div class="liq-card h-100">
                <div class="liq-title"><h4><i class="bi bi-calculator"></i> Calcular Liquidación</h4></div>
                <?php if ($msg): ?><div class="alert alert-<?= $msg_type ?> alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                
                <div class="card filter-card p-3 mb-4">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label for="filtro_depto" class="form-label fw-bold">Departamento</label>
                            <select name="filtro_depto" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach ($departamentos as $depto): ?>
                                <option value="<?= $depto['idDepartamento'] ?>" <?= $filtro_depto == $depto['idDepartamento'] ? 'selected' : ''?>><?= htmlspecialchars($depto['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="colaborador" class="form-label fw-bold">Colaborador</label>
                            <select name="colaborador" id="colaborador" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Seleccione...</option>
                                <?php foreach ($colaboradores as $col): ?>
                                    <option value="<?= $col['idColaborador'] ?>" 
                                        <?= (isset($_GET['colaborador']) && $_GET['colaborador'] == $col['idColaborador']) ? 'selected' : '' ?>
                                        <?= $col['liquidado'] ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($col['Nombre'].' '.$col['Apellido1']) ?> <?= $col['liquidado'] ? '(Liquidado)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if ($colaborador_seleccionado && !$colaborador_seleccionado['liquidado']): 
                    $motivo = $_GET['motivo'] ?? 'Renuncia';
                    $fecha_salida_form = $_GET['fecha_salida'] ?? date('Y-m-d');
                    $sug = calcular_liquidacion_cr($salario_promedio_ult_6_meses, $colaborador_seleccionado['fecha_ingreso'], $fecha_salida_form, $motivo, $aguinaldo_acumulado);
                    $antiguedad = date_diff(new DateTime($colaborador_seleccionado['fecha_ingreso']), new DateTime($fecha_salida_form));
                ?>
                <form method="post" id="liqForm">
                    <input type="hidden" name="colaborador" value="<?= $colaborador_seleccionado['idColaborador'] ?>">
                    <input type="hidden" name="monto_bruto" value="<?= round(array_sum([$sug['preaviso'], $sug['cesantia'], $sug['vacaciones'], $sug['aguinaldo']]), 2) ?>">
                    <input type="hidden" name="salario_promedio" value="<?= $salario_promedio_ult_6_meses ?>">
                    <input type="hidden" name="dias_laborados" value="<?= $sug['dias_laborados'] ?>">
                    <input type="hidden" name="preaviso" value="<?= $sug['preaviso'] ?>">
                    <input type="hidden" name="cesantia" value="<?= $sug['cesantia'] ?>">
                    <input type="hidden" name="vacaciones" value="<?= $sug['vacaciones'] ?>">
                    <input type="hidden" name="aguinaldo" value="<?= $sug['aguinaldo'] ?>">

                    <div class="p-3 mb-4 rounded" style="background-color: #f6f9fc; border: 1px solid #e1e9f2;">
                        <h5 class="mb-3">Datos para el Cálculo</h5>
                        <div class="row">
                            <div class="col-sm-6 mb-2"><span class="data-label">Fecha de Ingreso:</span> <span class="data-value"><?= date("d/m/Y", strtotime($colaborador_seleccionado['fecha_ingreso'])) ?></span></div>
                            <div class="col-sm-6 mb-2"><span class="data-label">Antigüedad:</span> <span class="data-value"><?= $antiguedad->y ?>a, <?= $antiguedad->m ?>m, <?= $antiguedad->d ?>d</span></div>
                            <div class="col-sm-12 mb-2"><span class="data-label">Salario Promedio (últimos 6 meses):</span> <span class="data-value">CRC <?= number_format($salario_promedio_ult_6_meses, 2) ?></span></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2"><label class="form-label fw-bold">Fecha de Salida:</label><input type="date" name="fecha_salida" id="fecha_salida" class="form-control" value="<?= $fecha_salida_form ?>" required></div>
                        <div class="col-md-6 mb-2"><label class="form-label fw-bold">Motivo:</label>
                            <select name="motivo" id="motivo" class="form-select" required>
                                <option value="Renuncia" <?= $motivo=='Renuncia'?'selected':'' ?>>Renuncia</option>
                                <option value="Despido con responsabilidad patronal" <?= $motivo=='Despido con responsabilidad patronal'?'selected':'' ?>>Despido con responsabilidad patronal</option>
                                <option value="Despido sin responsabilidad patronal" <?= $motivo=='Despido sin responsabilidad patronal'?'selected':'' ?>>Despido sin responsabilidad patronal</option>
                                <option value="Pensión" <?= $motivo=='Pensión'?'selected':'' ?>>Pensión</option>
                            </select>
                        </div>
                    </div>
                    
                    <h5 class="mt-4">Desglose de la Liquidación</h5>
                    <table class="table liq-table table-sm">
                        <tr>
                            <th>Preaviso <i class="bi bi-info-circle-fill info-btn" data-bs-toggle="popover" title="Preaviso (Art. 28)" data-bs-content="Pago que el patrono debe dar si despide sin justa causa y no otorga un plazo de aviso. No aplica en caso de renuncia o despido con justa causa."></i></th>
                            <td class="text-end">CRC <?= number_format($sug['preaviso'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Cesantía Total <i class="bi bi-info-circle-fill info-btn" data-bs-toggle="popover" title="Auxilio de Cesantía (Art. 29)" data-bs-content="Indemnización por los años de servicio. Se calcula por año trabajado con un tope de 8 años. Solo aplica en despidos con responsabilidad patronal."></i></th>
                            <td class="text-end fw-bold">CRC <?= number_format($sug['cesantia'], 2) ?></td>
                        </tr>
                        <?php foreach($sug['desglose_cesantia'] as $desglose): ?>
                            <tr class="table-light"><td class="ps-4"><small>Por <?= htmlspecialchars($desglose['anio']) ?></small></td><td class="text-end"><small>+ CRC <?= number_format($desglose['monto'], 2) ?></small></td></tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Vacaciones <i class="bi bi-info-circle-fill info-btn" data-bs-toggle="popover" title="Vacaciones (Art. 153)" data-bs-content="Pago de los días de vacaciones acumulados y no disfrutados por el colaborador. Corresponde 1 día por cada mes laborado."></i></th>
                            <td class="text-end">CRC <?= number_format($sug['vacaciones'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Aguinaldo <i class="bi bi-info-circle-fill info-btn" data-bs-toggle="popover" title="Aguinaldo (Ley N° 2412)" data-bs-content="Es un derecho anual. Se paga de forma proporcional al tiempo laborado en el período, basado en el promedio de los salarios."></i></th>
                            <td class="text-end">CRC <?= number_format($sug['aguinaldo'], 2) ?></td>
                        </tr>
                    </table>
                     <table class="table liq-table">
                        <tfoot>
                            <tr><th>Total Bruto</th><td class="text-end">CRC <?= number_format(array_sum([$sug['preaviso'], $sug['cesantia'], $sug['vacaciones'], $sug['aguinaldo']]), 2) ?></td></tr>
                            <tr><th>Otras Deducciones</th><td><input type="number" name="deducciones" id="deducciones" class="form-control form-control-sm" step="0.01" value="0" required></td></tr>
                            <tr><th>TOTAL A PAGAR</th><td><input type="number" name="total" id="total" class="form-control" value="<?= round(array_sum([$sug['preaviso'], $sug['cesantia'], $sug['vacaciones'], $sug['aguinaldo']]), 2) ?>" readonly></td></tr>
                        </tfoot>
                    </table>
                    
                    <div class="mb-3"><label>Detalle/Comentario:</label><textarea name="detalle" class="form-control" rows="2"></textarea></div>
                    <div class="text-center"><button type="submit" name="guardar_liq" class="btn btn-success px-4"><i class="bi bi-save"></i> Guardar Liquidación</button></div>
                </form>
                <?php elseif($colaborador_seleccionado && $colaborador_seleccionado['liquidado']): ?>
                    <div class="alert alert-warning text-center">Este colaborador ya tiene una liquidación registrada.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="liq-card h-100">
                <div class="liq-title"><h4><i class="bi bi-archive-fill"></i> Historial</h4></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light"><tr><th>Colaborador</th><th>Fecha</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php if (empty($historial_liquidaciones)): ?>
                                <tr><td colspan="3" class="text-center p-3">No hay liquidaciones guardadas.</td></tr>
                            <?php else: foreach($historial_liquidaciones as $h): ?>
                                <tr>
                                    <td><?= htmlspecialchars($h['Nombre'] . ' ' . $h['Apellido1']) ?><br><small class="text-muted">CRC <?= number_format($h['monto_neto'], 2) ?></small></td>
                                    <td><?= date('d/m/y', strtotime($h['fecha_liquidacion'])) ?></td>
                                    <td class="text-end">
                                        <a href="generar_reporte_liquidacion.php?id_liquidacion=<?= $h['idLiquidacion'] ?>" class="btn btn-danger btn-sm" target="_blank" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                        <button type="button" class="btn btn-outline-danger btn-sm" title="Eliminar" onclick="confirmDelete(<?= $h['idLiquidacion'] ?>)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">¿Estás seguro de que deseas eliminar este registro de liquidación?</div><div class="modal-footer"><form method="POST" id="deleteForm"><input type="hidden" name="delete_id" id="delete_id_input"></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" onclick="document.getElementById('deleteForm').submit();" class="btn btn-danger">Sí, Eliminar</button></div></div></div></div>

<?php include 'footer.php'; ?>
<script>
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl, {
          trigger: 'hover'
      })
    })
    
    function confirmDelete(id) {
        document.getElementById('delete_id_input').value = id;
        new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
    }
    
    function recalcularTotal() {
        let totalBruto = <?= json_encode(round(array_sum([($sug['preaviso'] ?? 0), ($sug['cesantia'] ?? 0), ($sug['vacaciones'] ?? 0), ($sug['aguinaldo'] ?? 0)]), 2)) ?>;
        let deducciones = parseFloat(document.getElementById('deducciones')?.value) || 0;
        document.getElementById('total').value = (totalBruto - deducciones).toFixed(2);
    }
    
    document.querySelectorAll('#motivo, #fecha_salida').forEach(el => {
        el?.addEventListener('change', () => {
            const form = document.querySelector('form[method="get"]');
            
            let motivoInput = form.querySelector('input[name="motivo"]');
            if (!motivoInput) {
                motivoInput = document.createElement('input');
                motivoInput.type = 'hidden';
                motivoInput.name = 'motivo';
                form.appendChild(motivoInput);
            }
            motivoInput.value = document.getElementById('motivo').value;
    
            let fechaInput = form.querySelector('input[name="fecha_salida"]');
            if(!fechaInput) {
                fechaInput = document.createElement('input');
                fechaInput.type = 'hidden';
                fechaInput.name = 'fecha_salida';
                form.appendChild(fechaInput);
            }
            fechaInput.value = document.getElementById('fecha_salida').value;
            
            form.submit();
        });
    });
    
    if(document.getElementById('deducciones')){
        document.getElementById('deducciones').addEventListener('input', recalcularTotal);
    }
</script>