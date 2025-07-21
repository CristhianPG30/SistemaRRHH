<?php
session_start();
include 'db.php';
include 'header.php';

// Asegurarse que el usuario esté logueado y sea colaborador
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;
$persona_id = $_SESSION['persona_id'] ?? null;

// --- INICIO: FUNCIONES DE CÁLCULO (Adaptadas de nóminas.php) ---

function calcularDeduccionesDeLey($salario_bruto, $conn) {
    $deducciones = ['total' => 0, 'monto_ccss' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            $nombre_limpio = trim($nombre);
            $porcentaje_float = floatval(trim($porcentaje));
            
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * ($porcentaje_float / 100);
                $deducciones['total'] += $monto;
                if (stripos($nombre_limpio, 'CCSS') !== false) {
                    $deducciones['monto_ccss'] = $monto;
                }
                $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => $nombre_limpio, 'monto' => $monto, 'porcentaje' => $porcentaje_float];
            }
        }
    }
    return $deducciones;
}

function calcularImpuestoRenta($salario_imponible, $cantidad_hijos, $es_casado) {
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    $default_return = ['total' => 0, 'bruto' => 0, 'credito_hijos' => 0, 'credito_conyuge' => 0, 'tramo_aplicado' => 'Exento', 'desglose_bruto' => []];
    if (!file_exists($tax_config_path)) { return $default_return; }
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    $current_year = date('Y');
    // Corregido para usar la clave correcta del JSON
    $tramos_key = 'tramos_salariales_2025';
    $creditos_key = 'creditos_fiscales_2025';
    
    $tramos_renta = $config_data[$tramos_key] ?? [];
    $creditos_fiscales = $config_data[$creditos_key] ?? ['hijo' => 0, 'conyuge' => 0];

    $impuesto_bruto = 0;
    $tramo_aplicado_final = 'Exento';
    $desglose_bruto = [];
    
    // Cálculo de impuesto bruto progresivo correcto
    foreach (array_reverse($tramos_renta) as $tramo) {
        if ($salario_imponible > $tramo['min']) {
            $excedente = $salario_imponible - $tramo['min'];
            $impuesto_bruto = $tramo['impuesto_sobre_exceso_de'] + ($excedente * ($tramo['tasa'] / 100));
            $tramo_aplicado_final = $tramo['tasa'] > 0 ? $tramo['tasa'] . '%' : 'Exento';
            $desglose_bruto[] = ['descripcion' => 'Sobre exceso de ₡' . number_format($tramo['min'], 0), 'monto' => $impuesto_bruto];
            break; 
        }
    }

    $credito_hijos = $cantidad_hijos * ($creditos_fiscales['hijo'] ?? 0);
    $credito_conyuge = $es_casado ? ($creditos_fiscales['conyuge'] ?? 0) : 0;
    $impuesto_final = $impuesto_bruto - $credito_hijos - $credito_conyuge;
    
    return ['total' => max(0, $impuesto_final), 'bruto' => $impuesto_bruto, 'credito_hijos' => $credito_hijos, 'credito_conyuge' => $credito_conyuge, 'tramo_aplicado' => $tramo_aplicado_final, 'desglose_bruto' => $desglose_bruto];
}

// --- FIN: FUNCIONES DE CÁLCULO ---

// Consulta principal para obtener todos los datos necesarios para recalcular el desglose
$salarios_detallados = [];
if ($colaborador_id) {
    $sql = "SELECT 
                pl.fecha_generacion, pl.salario_bruto, pl.total_horas_extra, pl.total_otros_ingresos, 
                pl.total_deducciones, pl.salario_neto,
                c.salario_bruto as salario_base, p.cantidad_hijos, p.id_estado_civil_fk
            FROM planillas pl
            JOIN colaborador c ON pl.id_colaborador_fk = c.idColaborador
            JOIN persona p ON c.id_persona_fk = p.idPersona
            WHERE pl.id_colaborador_fk = ? 
            ORDER BY pl.fecha_generacion DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $colaborador_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()){
        // Recalculamos todo para el modal
        $salario_bruto_calculado = $row['salario_bruto'];
        $pago_horas_extra = ($row['total_horas_extra'] > 0) ? ($row['salario_base'] / 240) * 1.5 * $row['total_horas_extra'] : 0;
        $pago_ordinario = $salario_bruto_calculado - $pago_horas_extra;

        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $salario_imponible = $salario_bruto_calculado - $deducciones_ley['monto_ccss'];
        $es_casado = ($row['id_estado_civil_fk'] == 2);
        $calculo_renta = calcularImpuestoRenta($salario_imponible, $row['cantidad_hijos'], $es_casado);
        
        // Asignamos los datos desglosados al array
        $row['pago_ordinario'] = $pago_ordinario;
        $row['pago_horas_extra'] = $pago_horas_extra;
        $row['salario_imponible_renta'] = $salario_imponible;
        $row['desglose_deducciones'] = $deducciones_ley['detalles'];
        $row['desglose_renta'] = $calculo_renta;
        
        $salarios_detallados[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Salario - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#f4f9fd 0%,#eaf6ff 100%);}
        .salario-card { background: #fff; border-radius: 2rem; box-shadow: 0 6px 30px #23b6ff19; padding: 2.5rem; margin-top: 2rem; }
        .titulo { font-weight: bold; font-size: 2.1rem; color: #12528c; text-align:center; margin-bottom: 1.8rem; }
        .summary { background: linear-gradient(90deg,#d1f1fd 60%,#f3fafd 100%); border-radius: 1.2rem; padding: 1.5rem 2rem; margin-bottom: 2rem;}
        .summary-title { color: #0099c8; font-weight: 600; font-size: 1.2rem; }
        .summary-value { font-size: 2.4rem; font-weight: 700; color: #09729a; }
        .table-salario th { background: #eaf7fd; color: #2176ae; }
        .table-salario td, .table-salario th { text-align: center; vertical-align: middle;}
        .badge-success { background: #11d073; }
        .btn-detail { font-size: 1.07rem; }
        .modal-header { background: #f6f9fc; border-bottom: 1px solid #dee2e6;}
        .modal-title { color: #32325d; font-weight: 600;}
        .bi-cash-coin { color: #12a6e8; margin-right: .5rem; }
        .bi-clock-history { color: #1976d2; margin-right: .5rem; }
        .calculo-detalle { font-size: 0.85em; }
        @media(max-width:650px) {
            .salario-card {padding:1.1rem;}
            .summary {padding:.8rem;}
            .titulo {font-size:1.3rem;}
        }
    </style>
</head>
<body>
<div class="container">
    <div class="salario-card animate__animated animate__fadeInDown">
        <div class="titulo">
            <i class="bi bi-cash-coin"></i> Mi Salario y Pagos
        </div>
        <div class="row summary text-center mb-4">
            <?php 
            $ultimo = $salarios_detallados[0] ?? null;
            if($ultimo): ?>
                <div class="col-6 col-md-3">
                    <div class="summary-title">Último pago neto</div>
                    <div class="summary-value">₡<?= number_format($ultimo['salario_neto'],2) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-title">Bruto</div>
                    <div class="summary-value">₡<?= number_format($ultimo['salario_bruto'],2) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-title">Deducciones</div>
                    <div class="summary-value text-danger">₡<?= number_format($ultimo['total_deducciones'],2) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-title">Horas extra</div>
                    <div class="summary-value text-success"><?= floatval($ultimo['total_horas_extra']) ?>h</div>
                </div>
            <?php else: ?>
                <div class="col-12"><em>No hay registros de salario.</em></div>
            <?php endif; ?>
        </div>

        <h5 class="mt-4 mb-3 text-primary"><i class="bi bi-clock-history"></i> Historial de Pagos</h5>
        <div class="table-responsive">
            <table class="table table-salario table-bordered">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Bruto</th>
                        <th>Horas Extra</th>
                        <th>Deducciones</th>
                        <th>Neto</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($salarios_detallados)): foreach($salarios_detallados as $index => $row): ?>
                        <tr>
                            <td><?= date('F Y', strtotime($row['fecha_generacion'])) ?></td>
                            <td>₡<?= number_format($row['salario_bruto'],2) ?></td>
                            <td><?= floatval($row['total_horas_extra']) ?>h</td>
                            <td><span class="badge bg-danger"><?= '₡'.number_format($row['total_deducciones'],2) ?></span></td>
                            <td><span class="badge bg-success"><?= '₡'.number_format($row['salario_neto'],2) ?></span></td>
                            <td>
                                <button class="btn btn-info btn-detail btn-sm" data-bs-toggle="modal" data-bs-target="#detalleModal" data-index="<?= $index ?>">
                                    <i class="bi bi-list-ul"></i> Ver Detalle
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center">No tienes pagos registrados aún.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detalleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem;">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle Salarial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detalleModalBody">
                </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const salarios_data = <?php echo json_encode($salarios_detallados); ?>;
const detalleModalEl = document.getElementById('detalleModal');

detalleModalEl.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const index = button.getAttribute('data-index');
    const data = salarios_data[index];
    const body = document.getElementById('detalleModalBody');

    // Construir HTML para las deducciones de ley
    let deduccionesHtml = '';
    data.desglose_deducciones.forEach(d => {
        deduccionesHtml += `<li class="list-group-item d-flex justify-content-between"><span>${d.descripcion} (${d.porcentaje}%)</span><strong>- ₡${parseFloat(d.monto).toFixed(2)}</strong></li>`;
    });

    // Construir HTML para el desglose del impuesto sobre la renta
    let rentaHtml = `<li class="list-group-item d-flex justify-content-between"><span>Impuesto sobre la Renta (${data.desglose_renta.tramo_aplicado})</span><strong class="text-danger">- ₡${parseFloat(data.desglose_renta.total).toFixed(2)}</strong></li>`;
    if (data.desglose_renta.bruto > 0) {
        rentaHtml += `<li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle" style="background-color: #f8f9fa;"><span>↳ Impuesto Bruto</span><span>- ₡${parseFloat(data.desglose_renta.bruto).toFixed(2)}</span></li>`;
        if (data.desglose_renta.credito_conyuge > 0) {
            rentaHtml += `<li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle text-success" style="background-color: #f8f9fa;"><span>↳ Crédito por Cónyuge</span><span>+ ₡${parseFloat(data.desglose_renta.credito_conyuge).toFixed(2)}</span></li>`;
        }
        if (data.desglose_renta.credito_hijos > 0) {
            rentaHtml += `<li class="list-group-item d-flex justify-content-between ps-4 calculo-detalle text-success" style="background-color: #f8f9fa;"><span>↳ Crédito por Hijos (${data.cantidad_hijos})</span><span>+ ₡${parseFloat(data.desglose_renta.credito_hijos).toFixed(2)}</span></li>`;
        }
    }

    // Inyectar todo el HTML en el cuerpo del modal
    body.innerHTML = `
        <div class="p-3 mb-3" style="background-color: #f8f9fa; border-radius: .5rem;">
            <div class="row text-center">
                <div class="col-4"><small class="text-muted d-block">SALARIO BASE</small><div class="fs-5 fw-bold">₡${parseFloat(data.salario_base).toFixed(2)}</div></div>
                <div class="col-4"><small class="text-muted d-block">SALARIO BRUTO</small><div class="fs-5 fw-bold">₡${parseFloat(data.salario_bruto).toFixed(2)}</div></div>
                <div class="col-4"><small class="text-muted d-block">SALARIO IMPONIBLE (RENTA)</small><div class="fs-5 fw-bold">₡${parseFloat(data.salario_imponible_renta).toFixed(2)}</div></div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="text-success fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>INGRESOS Y PERMISOS CON GOCE</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Pago Ordinario</span><strong>+ ₡${parseFloat(data.pago_ordinario).toFixed(2)}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Pago Horas Extra (${parseFloat(data.total_horas_extra).toFixed(2)}h)</span><strong>+ ₡${parseFloat(data.pago_horas_extra).toFixed(2)}</strong></li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger fw-bold"><i class="bi bi-dash-circle-fill me-2"></i>AJUSTES Y DEDUCCIONES</h6>
                 <ul class="list-group list-group-flush">
                    <li class="list-group-item disabled"><small>DEDUCCIONES DE LEY</small></li>
                    ${deduccionesHtml}
                    ${rentaHtml}
                </ul>
            </div>
        </div>
        <hr class="my-4">
        <div class="bg-light p-3 rounded">
            <div class="d-flex justify-content-between text-danger"><h5>Total Deducciones:</h5><h5>- ₡${parseFloat(data.total_deducciones).toFixed(2)}</h5></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bolder text-success">SALARIO NETO A PAGAR:</h4>
                <h4 class="mb-0 fw-bolder text-success">₡${parseFloat(data.salario_neto).toFixed(2)}</h4>
            </div>
        </div>
    `;
});
</script>
</body>
</html>