<?php
session_start();
include 'db.php';
include 'header.php';

// Asegurarse que el usuario esté logueado
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'] ?? null;

// Consulta principal de salarios
$sql = "SELECT 
            fecha_generacion, 
            salario_bruto, 
            total_horas_extra, 
            total_otros_ingresos, 
            total_deducciones, 
            salario_neto 
        FROM planillas 
        WHERE id_colaborador_fk = ? 
        ORDER BY fecha_generacion DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$result = $stmt->get_result();
$salarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Consulta para deducciones por planilla (modal)
$deducciones = [];
$sqlDed = "SELECT fecha_generacion_planilla, td.Descripcion, monto 
           FROM deducciones_detalle d
           JOIN tipo_deduccion_cat td ON d.id_tipo_deduccion_fk = td.idTipoDeduccion
           WHERE d.id_colaborador_fk = ?
           ORDER BY fecha_generacion_planilla DESC";
$stmtDed = $conn->prepare($sqlDed);
$stmtDed->bind_param("i", $colaborador_id);
$stmtDed->execute();
$resDed = $stmtDed->get_result();
while ($row = $resDed->fetch_assoc()) {
    $deducciones[$row['fecha_generacion_planilla']][] = $row;
}
$stmtDed->close();
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
        .modal-header { background: #23b6ff11; }
        .modal-title { color: #1d6fa5; font-weight: 600;}
        .bi-cash-coin { color: #12a6e8; margin-right: .5rem; }
        .bi-clock-history { color: #1976d2; margin-right: .5rem; }
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
        <!-- Resumen rápido -->
        <div class="row summary text-center mb-4">
            <?php 
            $ultimo = $salarios[0] ?? null;
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
                        <th>Otros Ingresos</th>
                        <th>Deducciones</th>
                        <th>Neto</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($salarios)): foreach($salarios as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['fecha_generacion'])) ?></td>
                            <td>₡<?= number_format($row['salario_bruto'],2) ?></td>
                            <td><?= floatval($row['total_horas_extra']) ?>h</td>
                            <td>₡<?= number_format($row['total_otros_ingresos'],2) ?></td>
                            <td>
                                <span class="badge bg-danger"><?= '₡'.number_format($row['total_deducciones'],2) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?= '₡'.number_format($row['salario_neto'],2) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-info btn-detail btn-sm" data-bs-toggle="modal" data-bs-target="#detalleDeduccionModal"
                                        data-fecha="<?= $row['fecha_generacion'] ?>">
                                    <i class="bi bi-list-ul"></i> Ver Detalle
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center">No tienes pagos registrados aún.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detalle de Deducciones -->
<div class="modal fade" id="detalleDeduccionModal" tabindex="-1" aria-labelledby="detalleDeduccionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content animate__animated animate__zoomIn">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleDeduccionModalLabel">Detalle de Deducciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleDeduccionBody">
                <div class="text-center text-muted">Seleccione un pago para ver el detalle.</div>
            </div>
        </div>
    </div>
</div>

<!-- Animate.css y Bootstrap JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Deducciones en JS (para el modal)
const deducciones = <?php echo json_encode($deducciones); ?>;

const detalleDeduccionModal = document.getElementById('detalleDeduccionModal');
detalleDeduccionModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const fecha = button.getAttribute('data-fecha');
    const body = document.getElementById('detalleDeduccionBody');
    let html = '';
    if(deducciones[fecha]){
        html = `<table class="table table-bordered">
                    <thead><tr><th>Descripción</th><th>Monto</th></tr></thead>
                    <tbody>`;
        let total = 0;
        deducciones[fecha].forEach(d => {
            html += `<tr><td>${d.Descripcion}</td><td>₡${parseFloat(d.monto).toFixed(2)}</td></tr>`;
            total += parseFloat(d.monto);
        });
        html += `<tr><th>Total</th><th>₡${total.toFixed(2)}</th></tr></tbody></table>`;
    } else {
        html = `<div class="text-center text-muted">No hay deducciones para este pago.</div>`;
    }
    body.innerHTML = html;
});
</script>
</body>
</html>
