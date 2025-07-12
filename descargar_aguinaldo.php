<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// --- VALIDACIÓN DE PARÁMETROS ---
if (!isset($_GET['anio'])) {
    die('Error: El año para el reporte no ha sido especificado.');
}

$anio = intval($_GET['anio']);
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// --- OBTENCIÓN DE DATOS ---
$sql_colaboradores = "SELECT 
                        c.idColaborador,
                        CONCAT(p.Nombre, ' ', p.Apellido1) AS nombre_completo,
                        p.Cedula
                      FROM aguinaldo a
                      JOIN colaborador c ON a.id_colaborador_fk = c.idColaborador
                      JOIN persona p ON c.id_persona_fk = p.idPersona
                      WHERE a.periodo = ?
                      ORDER BY p.Nombre, p.Apellido1";

$stmt_colaboradores = $conn->prepare($sql_colaboradores);
$stmt_colaboradores->bind_param("i", $anio);
$stmt_colaboradores->execute();
$result_colaboradores = $stmt_colaboradores->get_result();
$datos_reporte = [];

$fecha_inicio_periodo = ($anio - 1) . '-12-01';
$fecha_fin_periodo = $anio . '-11-30';

while ($colaborador = $result_colaboradores->fetch_assoc()) {
    $salarios_mensuales = [];
    $total_salarios = 0;

    $stmt_planillas = $conn->prepare(
        "SELECT fecha_generacion, salario_bruto 
         FROM planillas 
         WHERE id_colaborador_fk = ? AND fecha_generacion BETWEEN ? AND ?
         ORDER BY fecha_generacion ASC"
    );
    $stmt_planillas->bind_param("iss", $colaborador['idColaborador'], $fecha_inicio_periodo, $fecha_fin_periodo);
    $stmt_planillas->execute();
    $result_planillas = $stmt_planillas->get_result();
    
    while ($planilla = $result_planillas->fetch_assoc()) {
        $mes_num = (int)date('n', strtotime($planilla['fecha_generacion']));
        $ano_mes = date('Y', strtotime($planilla['fecha_generacion']));
        
        $salarios_mensuales[] = [
            'mes' => $meses_espanol[$mes_num] . " " . $ano_mes,
            'salario' => $planilla['salario_bruto']
        ];
        $total_salarios += $planilla['salario_bruto'];
    }
    $stmt_planillas->close();
    
    $aguinaldo_calculado = $total_salarios / 12;

    $datos_reporte[] = [
        'nombre' => $colaborador['nombre_completo'],
        'cedula' => $colaborador['Cedula'],
        'salarios_desglose' => $salarios_mensuales,
        'total_salarios' => $total_salarios,
        'aguinaldo' => $aguinaldo_calculado
    ];
}
$stmt_colaboradores->close();
$conn->close();

if (empty($datos_reporte)) {
    die('No se encontraron datos de aguinaldos para generar el reporte del año ' . $anio);
}

// --- GENERACIÓN DEL ARCHIVO EXCEL CON ESTILOS Y DETALLES ---
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=reporte_aguinaldo_{$anio}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // BOM para UTF-8

?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; margin-bottom: 20px; width: 600px; }
        th, td { border: 1px solid #b2b2b2; padding: 8px; }
        .header-main { background-color: #002060; color: white; font-weight: bold; font-size: 16pt; text-align: center; }
        .header-colaborador { background-color: #1f4e78; color: white; font-weight: bold; font-size: 12pt; }
        .subheader-desglose { background-color: #ddebf7; font-weight: bold; text-align: center; }
        .row-total { background-color: #fde9d9; font-weight: bold; }
        .row-aguinaldo { background-color: #c6e0b4; font-weight: bold; font-size: 12pt; }
        .text-right { text-align: right; }
        .cell-cedula { mso-number-format:"\@"; /* Formato de texto para Excel */ }
    </style>
</head>
<body>

<table>
    <tr>
        <th colspan="2" class="header-main">Reporte de Aguinaldo <?php echo $anio; ?></th>
    </tr>
</table>

<?php foreach ($datos_reporte as $data): ?>
    <table>
        <tr>
            <th class="header-colaborador" style="width: 200px;">Colaborador:</th>
            <td><b><?php echo htmlspecialchars($data['nombre']); ?></b></td>
        </tr>
        <tr>
            <th class="header-colaborador">Cédula:</th>
            <td class="cell-cedula"><?php echo htmlspecialchars($data['cedula']); ?></td>
        </tr>
        
        <tr>
            <td colspan="2" style="padding: 10px 0;">
                <table style="width: 100%;">
                    <thead>
                        <tr class="subheader-desglose">
                            <th>Mes del Salario</th>
                            <th class="text-right">Monto Bruto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['salarios_desglose'])): ?>
                            <tr><td colspan="2" style="text-align:center; font-style:italic;">No hay registros de planilla en este período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data['salarios_desglose'] as $salario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($salario['mes']); ?></td>
                                <td class="text-right"><?php echo '₡ ' . number_format($salario['salario'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </td>
        </tr>
        
        <tr class="row-total">
            <td><b>Suma Total de Salarios</b></td>
            <td class="text-right"><b><?php echo '₡ ' . number_format($data['total_salarios'], 2); ?></b></td>
        </tr>
        <tr class="row-aguinaldo">
            <td><b>Aguinaldo Calculado (Total / 12)</b></td>
            <td class="text-right"><b><?php echo '₡ ' . number_format($data['aguinaldo'], 2); ?></b></td>
        </tr>
    </table>
<?php endforeach; ?>

</body>
</html>