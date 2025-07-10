<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos

// Verificar que se ha pasado un año
if (!isset($_GET['anio'])) {
    die('Año no especificado.');
}

$anio = (int)$_GET['anio'];

// --- CONSULTA PARA OBTENER LOS DATOS DEL AGUINALDO ---
$sql = "SELECT 
            a.periodo,
            a.monto_calculado,
            CONCAT(p.Nombre, ' ', p.Apellido1) AS nombre_colaborador,
            p.Cedula
        FROM aguinaldo a
        JOIN colaborador c ON a.id_colaborador_fk = c.idColaborador
        JOIN persona p ON c.id_persona_fk = p.idPersona
        WHERE a.periodo = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt->bind_param("i", $anio);
$stmt->execute();
$result = $stmt->get_result();
$aguinaldos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Si no hay datos, no generar archivo
if (empty($aguinaldos)) {
    die('No se encontraron aguinaldos para el año especificado.');
}

// Establecer las cabeceras para la descarga del archivo Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=reporte_aguinaldos_{$anio}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Añadir BOM para UTF-8 para compatibilidad con caracteres especiales en Excel
echo "\xEF\xBB\xBF";

// Crear el contenido del archivo Excel
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color: #f2f2f2; font-weight: bold;'>
            <th>Colaborador</th>
            <th>Cédula</th>
            <th>Período</th>
            <th>Monto de Aguinaldo</th>
        </tr>
      </thead>";
echo "<tbody>";

foreach ($aguinaldos as $aguinaldo) {
    echo "<tr>
            <td>" . htmlspecialchars($aguinaldo['nombre_colaborador']) . "</td>
            <td>'" . htmlspecialchars($aguinaldo['Cedula']) . "</td>
            <td>" . htmlspecialchars($aguinaldo['periodo']) . "</td>
            <td>" . number_format($aguinaldo['monto_calculado'], 2, '.', '') . "</td>
          </tr>";
}

echo "</tbody>";
echo "</table>";
exit;
?>