<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    exit('Acceso denegado.');
}

require_once 'db.php';
require('fpdf/fpdf/fpdf.php');

$formato = $_GET['formato'] ?? 'excel';

// --- (La misma lógica de consulta dinámica de la página principal va aquí) ---
// ... (copiar y pegar toda la sección de "Lógica de Consulta Dinámica" de consultas_dinamicas.php) ...

if ($formato == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=reporte.xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    $output = "<table border='1'><thead><tr>";
    foreach ($columnas as $col) {
        $output .= "<th>" . htmlspecialchars($col) . "</th>";
    }
    $output .= "</tr></thead><tbody>";

    foreach ($resultados as $fila) {
        $output .= "<tr>";
        foreach ($columnas as $col) {
            $output .= "<td>" . htmlspecialchars($fila[$col]) . "</td>";
        }
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
    echo $output;

} elseif ($formato == 'pdf') {
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, 'Reporte del Sistema RRHH', 0, 1, 'C');
            $this->Ln(5);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AddPage('L', 'A4');
    $pdf->SetFont('Arial', 'B', 10);

    // Cabecera
    foreach ($columnas as $col) {
        $pdf->Cell(40, 7, utf8_decode($col), 1);
    }
    $pdf->Ln();

    // Datos
    $pdf->SetFont('Arial', '', 10);
    foreach ($resultados as $fila) {
        foreach ($columnas as $col) {
            $pdf->Cell(40, 6, utf8_decode($fila[$col]), 1);
        }
        $pdf->Ln();
    }
    $pdf->Output('D', 'reporte.pdf');
}
?>