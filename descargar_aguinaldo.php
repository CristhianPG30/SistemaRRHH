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
if (!isset($_GET['anio'])) {
    die('Año no especificado.');
}

$anio = (int)$_GET['anio'];

// Consultar los aguinaldos del año seleccionado
function obtenerAguinaldosParaDescargar($anio) {
    global $conn;
    $sql = "SELECT p.Nombre, p.Apellido1, p.Cedula, a.Monto_aguinaldo
            FROM aguinaldo a
            JOIN colaborador c ON a.Colaborador_idColaborador = c.idColaborador
            JOIN persona p ON c.Persona_idPersona = p.idPersona
            WHERE YEAR(a.Fechainicio) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    $aguinaldos = $result->fetch_all(MYSQLI_ASSOC);
    return $aguinaldos;
}

$aguinaldos = obtenerAguinaldosParaDescargar($anio);

// Verificar si hay datos para descargar
if (!$aguinaldos || count($aguinaldos) === 0) {
    die('No se encontraron aguinaldos para el año especificado.');
}

// Preparar el archivo HTML para descargar como Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="aguinaldos_' . $anio . '.xls"');

// Añadir el BOM para UTF-8
echo "\xEF\xBB\xBF";

// Generar la tabla con bordes
echo '<table border="1">';
echo '<tr>';
echo '<th>Nombre</th>';
echo '<th>Apellido</th>';
echo '<th>Cédula</th>';
echo '<th>Monto Aguinaldo</th>';
echo '</tr>';

// Escribir los datos de los aguinaldos
foreach ($aguinaldos as $aguinaldo) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($aguinaldo['Nombre']) . '</td>';
    echo '<td>' . htmlspecialchars($aguinaldo['Apellido1']) . '</td>';
    echo '<td>' . htmlspecialchars($aguinaldo['Cedula']) . '</td>';
    echo '<td>' . number_format($aguinaldo['Monto_aguinaldo'], 2, '.', '') . '</td>';
    echo '</tr>';
}

echo '</table>';
exit;
?>
