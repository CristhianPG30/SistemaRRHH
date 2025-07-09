<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos

// Verificar que se ha pasado un año
if (!isset($_GET['anio']) || !isset($_GET['mes'])) {
    die('Período no especificado.');
}

$anio = (int)$_GET['anio'];
$mes = (int)$_GET['mes'];

// --- CONSULTA CORREGIDA Y SIMPLIFICADA ---
$sql = "SELECT 
            p.salario_bruto,
            p.total_horas_extra,
            p.total_otros_ingresos,
            p.total_deducciones,
            p.salario_neto,
            CONCAT(pe.Nombre, ' ', pe.Apellido1, ' ', pe.Apellido2) AS nombre_colaborador
        FROM planillas p
        JOIN colaborador c ON p.id_colaborador_fk = c.idColaborador
        JOIN persona pe ON c.id_persona_fk = pe.idPersona
        WHERE YEAR(p.fecha_generacion) = ? AND MONTH(p.fecha_generacion) = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt->bind_param("ii", $anio, $mes);
$stmt->execute();
$result = $stmt->get_result();
$planillas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Establecer las cabeceras para la descarga del archivo Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=planilla_{$anio}_{$mes}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Añadir BOM para UTF-8 para compatibilidad con caracteres especiales en Excel
echo "\xEF\xBB\xBF";

// Crear el contenido del archivo Excel
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color: #f2f2f2; font-weight: bold;'>
            <th>Nombre Colaborador</th>
            <th>Salario Bruto</th>
            <th>Pago Horas Extra</th>
            <th>Otros Ingresos</th>
            <th>Total Deducciones</th>
            <th>Salario Neto</th>
        </tr>
      </thead>";
echo "<tbody>";

if (count($planillas) > 0) {
    foreach ($planillas as $planilla) {
        echo "<tr>
                <td>" . htmlspecialchars($planilla['nombre_colaborador']) . "</td>
                <td>" . number_format($planilla['salario_bruto'], 2) . "</td>
                <td>" . number_format($planilla['total_horas_extra'], 2) . "</td>
                <td>" . number_format($planilla['total_otros_ingresos'], 2) . "</td>
                <td>" . number_format($planilla['total_deducciones'], 2) . "</td>
                <td>" . number_format($planilla['salario_neto'], 2) . "</td>
              </tr>";
    } // <- La llave de cierre faltante iba aquí
} else {
    echo "<tr><td colspan='6'>No se encontraron datos para el período seleccionado.</td></tr>";
}

echo "</tbody>";
echo "</table>";
exit;
?>