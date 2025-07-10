<?php
session_start();
// --- RUTA CORREGIDA ---
// Se ajusta la ruta para que PHP encuentre la librería FPDF correctamente.
require('fpdf/fpdf/fpdf.php'); 
require_once 'db.php';

// --- Validación de Seguridad y Permisos ---
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) { // Solo Admin y RRHH
    header("Location: login.php");
    exit;
}
if (!isset($_GET['id_liquidacion'])) {
    die("Error: No se ha especificado un ID de liquidación.");
}

$id_liquidacion = intval($_GET['id_liquidacion']);

// --- Obtener datos de la liquidación y del colaborador ---
$sql = "SELECT 
            l.*,
            p.Nombre, p.Apellido1, p.Apellido2, p.Cedula,
            c.fecha_ingreso, c.salario_bruto
        FROM liquidaciones l
        JOIN colaborador c ON l.id_colaborador_fk = c.idColaborador
        JOIN persona p ON c.id_persona_fk = p.idPersona
        WHERE l.idLiquidacion = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_liquidacion);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Error: Liquidación no encontrada.");
}
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

// --- Función para recalcular el desglose (para el PDF) ---
function recalcular_liquidacion_cr($salario_promedio, $fecha_ingreso, $fecha_salida, $motivo) {
    if (!$salario_promedio || empty($fecha_ingreso) || empty($fecha_salida)) {
        return ['preaviso' => 0, 'cesantia' => 0, 'vacaciones' => 0, 'aguinaldo' => 0];
    }
    $meses_laborados = ((strtotime($fecha_salida) - strtotime($fecha_ingreso)) / 86400) / 30.417;
    $salario_diario = $salario_promedio / 30;
    
    $preaviso = 0;
    if ($motivo === "Despido con responsabilidad patronal") {
        if ($meses_laborados >= 3 && $meses_laborados < 6) $preaviso = $salario_diario * 7;
        elseif ($meses_laborados >= 6 && $meses_laborados < 12) $preaviso = $salario_diario * 15;
        elseif ($meses_laborados >= 12) $preaviso = $salario_promedio;
    }

    $cesantia = 0;
    if ($motivo === "Despido con responsabilidad patronal") {
        $anos_completos = floor($meses_laborados / 12);
        $dias_cesantia_total = 0;
        $tabla_dias_cesantia = [ 1 => 19.5, 2 => 20, 3 => 20.5, 4 => 21, 5 => 21.25, 6 => 21.5, 7 => 22, 8 => 22 ];
        if ($meses_laborados >= 3 && $meses_laborados < 6) $dias_cesantia_total = 7;
        elseif ($meses_laborados >= 6 && $meses_laborados < 12) $dias_cesantia_total = 14;
        elseif ($meses_laborados >= 12) {
            for ($i = 1; $i <= min($anos_completos, 8); $i++) {
                $dias_cesantia_total += $tabla_dias_cesantia[$i] ?? 22;
            }
        }
        $cesantia = $dias_cesantia_total * $salario_diario;
    }
    
    $vacaciones = ($meses_laborados * 1) * $salario_diario;
    $aguinaldo = ($salario_promedio * $meses_laborados) / 12;

    return ['preaviso' => round($preaviso, 2), 'cesantia' => round($cesantia, 2), 'vacaciones' => round($vacaciones, 2), 'aguinaldo' => round($aguinaldo, 2)];
}

// Recalculamos el desglose usando los datos guardados
$desglose = recalcular_liquidacion_cr($data['salario_base'] ?? $data['salario_bruto'], $data['fecha_ingreso'], $data['fecha_liquidacion'], $data['motivo']);

// --- Creación del PDF ---
class PDF extends FPDF
{
    function Header() {
        if (file_exists('img/edginton.png')) {
            $this->Image('img/edginton.png', 15, 8, 25);
        }
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, utf8_decode('Comprobante de Liquidación Laboral'), 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 7, utf8_decode('Estructuras Metálicas Edginton S.A.'), 0, 1, 'C');
        $this->Ln(15);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Información del Colaborador
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Información del Colaborador'), 0, 1);
$info_colaborador = [
    "Nombre Completo:" => $data['Nombre'] . ' ' . $data['Apellido1'],
    "Cédula:" => $data['Cedula'],
    "Fecha de Ingreso:" => date("d/m/Y", strtotime($data['fecha_ingreso'])),
    "Fecha de Salida:" => date("d/m/Y", strtotime($data['fecha_liquidacion']))
];
foreach($info_colaborador as $label => $value) {
    $pdf->SetFont('Arial','B', 12);
    $pdf->Cell(50, 8, utf8_decode($label), 0);
    $pdf->SetFont('Arial','', 12);
    $pdf->Cell(0, 8, utf8_decode($value), 0, 1);
}
$pdf->Ln(10);

// Detalles de la Liquidación
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Desglose de la Liquidación'), 0, 1);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(95, 8, 'Concepto', 1, 0, 'C', true);
$pdf->Cell(0, 8, 'Monto', 1, 1, 'C', true);

function DataRow($pdf, $label, $value){
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(95, 8, utf8_decode($label), 'LRB', 0, 'L');
    $pdf->Cell(0, 8, 'CRC ' . number_format($value, 2), 'RB', 1, 'R');
}

DataRow($pdf, 'Preaviso', $desglose['preaviso']);
DataRow($pdf, 'Cesantía', $desglose['cesantia']);
DataRow($pdf, 'Vacaciones Proporcionales', $desglose['vacaciones']);
DataRow($pdf, 'Aguinaldo Proporcional', $desglose['aguinaldo']);

$monto_bruto = array_sum($desglose);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 8, 'SUBTOTAL BRUTO', 'LRBT', 0, 'R');
$pdf->Cell(0, 8, 'CRC ' . number_format($monto_bruto, 2), 'RBT', 1, 'R');
$pdf->Cell(95, 8, 'Otras Deducciones', 'LRB', 0, 'R');
$pdf->Cell(0, 8, '- CRC ' . number_format($data['total_deducciones'], 2), 'RB', 1, 'R');
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(95, 10, 'TOTAL NETO A PAGAR', 1, 0, 'R', true);
$pdf->Cell(0, 10, 'CRC ' . number_format($data['monto_neto'], 2), 1, 1, 'R', true);
$pdf->Ln(10);

// Comentarios y Firma
if (!empty($data['observaciones'])) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Observaciones:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 6, utf8_decode($data['observaciones']));
    $pdf->Ln(10);
}

$pdf->Ln(25);
$pdf->Cell(80, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(30, 10, '', 0, 0);
$pdf->Cell(80, 10, '_________________________', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 5, 'Firma del Colaborador', 0, 0, 'C');
$pdf->Cell(30, 5, '', 0, 0);
$pdf->Cell(80, 5, 'Recursos Humanos', 0, 1, 'C');

$pdf->Output('D', 'Liquidacion_' . preg_replace('/[^A-Za-z0-9]/', '', $data['Cedula']) . '.pdf');
?>