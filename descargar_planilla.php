<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php'; // Conexión a la base de datos

// Obtén el año y mes de la URL
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');

// Función para calcular el número de días laborales en un mes
function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = 0;
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $timestamp = strtotime("$anio-$mes-$dia");
        $dia_semana = date("N", $timestamp); // 1 (lunes) a 7 (domingo)

        if ($dia_semana >= 1 && $dia_semana <= 5) { // Días de lunes a viernes
            $dias_laborales++;
        }
    }
    return $dias_laborales;
}

$dias_laborales_mes = calcularDiasLaborales($anio, $mes);

// Consulta para obtener las planillas del mes y año seleccionados junto con el nombre del colaborador y monto de incapacidades
$sql = "SELECT p.idPlanillas, p.Fecha, p.Salario_bruto, p.Horas_extra, p.Deducciones, p.Vacaciones, p.Salario_neto,
               CONCAT(pe.Nombre, ' ', pe.Apellido1, ' ', pe.Apellido2) AS Nombre_colaborador,
               (SELECT IFNULL(SUM(i.Cantidad * (p.Salario_bruto / ?)), 0) 
                FROM incapacidades i 
                WHERE i.Colaborador_idColaborador = c.idColaborador) AS Incapacidades
        FROM planillas p
        JOIN colaborador c ON p.Persona_idPersona = c.Persona_idPersona
        JOIN persona pe ON c.Persona_idPersona = pe.idPersona
        WHERE YEAR(p.Fecha_generacion) = ? AND MONTH(p.Fecha_generacion) = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("dii", $dias_laborales_mes, $anio, $mes);
$stmt->execute();
$result = $stmt->get_result();
$planillas = $result->fetch_all(MYSQLI_ASSOC);

// Establece las cabeceras para la descarga del archivo Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=planilla_{$anio}_{$mes}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Crea el contenido del archivo Excel con estilos básicos
echo "<table border='1' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
echo "<thead style='background-color: #f2f2f2;'>
        <tr>
            <th style='padding: 8px; text-align: left;'>ID</th>
            <th style='padding: 8px; text-align: left;'>Fecha</th>
            <th style='padding: 8px; text-align: left;'>Nombre Colaborador</th>
            <th style='padding: 8px; text-align: left;'>Salario Bruto</th>
            <th style='padding: 8px; text-align: left;'>Horas Extra</th>
            <th style='padding: 8px; text-align: left;'>Deducciones</th>
            <th style='padding: 8px; text-align: left;'>Vacaciones</th>
            <th style='padding: 8px; text-align: left;'>Incapacidades</th>
            <th style='padding: 8px; text-align: left;'>Salario Neto</th>
        </tr>
      </thead>";
echo "<tbody>";

foreach ($planillas as $planilla) {
    echo "<tr>
            <td style='padding: 8px;'>{$planilla['idPlanillas']}</td>
            <td style='padding: 8px;'>{$planilla['Fecha']}</td>
            <td style='padding: 8px;'>{$planilla['Nombre_colaborador']}</td>
            <td style='padding: 8px;'>" . number_format($planilla['Salario_bruto'], 2) . "</td>
            <td style='padding: 8px;'>" . number_format($planilla['Horas_extra'], 2) . "</td>
            <td style='padding: 8px;'>" . number_format($planilla['Deducciones'], 2) . "</td>
            <td style='padding: 8px;'>" . number_format($planilla['Vacaciones'], 2) . "</td>
            <td style='padding: 8px;'>" . number_format($planilla['Incapacidades'], 2) . "</td>
            <td style='padding: 8px;'>" . number_format($planilla['Salario_neto'], 2) . "</td>
          </tr>";
}

echo "</tbody>";
echo "</table>";
?>
