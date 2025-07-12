<?php
// Inicia la sesión si no está iniciada. Esto es crucial para acceder a variables de sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica si el usuario ha iniciado sesión y tiene el rol permitido (Administrador o Recursos Humanos).
// Si no cumple las condiciones, redirige al usuario a la página de inicio de sesión.
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

// Incluye el archivo de conexión a la base de datos.
include 'db.php';

// --- Validación de Parámetros de Entrada ---
// Verifica que se hayan pasado el año y el mes como parámetros GET.
// Si no están presentes, detiene la ejecución y muestra un mensaje de error.
if (!isset($_GET['anio']) || !isset($_GET['mes'])) {
    die('Error: Período no especificado (año o mes).');
}

// Convierte el año y el mes a enteros para asegurar su tipo de dato y prevenir inyecciones.
$anio = intval($_GET['anio']);
$mes = intval($_GET['mes']);

// --- FUNCIONES DE CÁLCULO (Copiadas de nóminas.php para asegurar consistencia) ---

/**
 * Obtiene las fechas de los días feriados configurados para un año específico.
 * Estos días no se consideran laborables.
 * @param int $anio El año para el cual se buscan los días feriados.
 * @return array Un array de strings con las fechas de los días feriados ('YYYY-MM-DD').
 */
function obtenerDiasFeriados($anio) {
    $feriadosFilePath = 'js/feriados.json'; // Ruta al archivo JSON de feriados.
    // Si el archivo no existe, no hay feriados para devolver.
    if (!file_exists($feriadosFilePath)) {
        return [];
    }
    // Decodifica el contenido JSON del archivo.
    $feriados_data = json_decode(file_get_contents($feriadosFilePath), true);
    // Retorna solo la columna 'fecha' si los datos son un array, de lo contrario, un array vacío.
    return is_array($feriados_data) ? array_column($feriados_data, 'fecha') : [];
}

/**
 * Calcula los días que son considerados laborables dentro de un mes y año dados,
 * excluyendo fines de semana y días feriados.
 * @param int $anio El año del mes a calcular.
 * @param int $mes El número del mes a calcular.
 * @return array Un array de strings con las fechas de los días laborables ('YYYY-MM-DD').
 */
function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = [];
    // Obtiene el número total de días en el mes especificado.
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    // Obtiene la lista de días feriados para el año.
    $feriados = obtenerDiasFeriados($anio);

    // Itera sobre cada día del mes.
    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia); // Formatea la fecha.
        // date('N') retorna el día de la semana (1 para Lunes, 7 para Domingo).
        // Si no es Sábado (6) ni Domingo (7) y no es un día feriado, se considera laborable.
        if (date('N', strtotime($fecha)) < 6 && !in_array($fecha, $feriados)) {
            $dias_laborales[] = $fecha; // Añade la fecha a la lista de días laborables.
        }
    }
    return $dias_laborales;
}

/**
 * Calcula el monto total y el detalle de las deducciones de ley aplicables a un salario bruto.
 * Las deducciones se obtienen de la base de datos.
 * @param float $salario_bruto El salario bruto sobre el cual calcular las deducciones.
 * @param mysqli $conn Objeto de conexión a la base de datos.
 * @return array Un array que contiene el total de deducciones y el detalle de cada una.
 */
function calcularDeduccionesDeLey($salario_bruto, $conn) {
    $deducciones = ['total' => 0, 'detalles' => []];
    // Consulta todos los tipos de deducción desde la tabla `tipo_deduccion_cat`.
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");

    if ($result) {
        // Itera sobre cada tipo de deducción encontrado.
        while ($row = $result->fetch_assoc()) {
            // Divide la descripción (ej: "CCSS Obrero:10.5") para obtener el nombre y el porcentaje.
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            // Si el porcentaje es un valor numérico válido, calcula el monto de la deducción.
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * (floatval(trim($porcentaje)) / 100);
                $deducciones['total'] += $monto; // Suma al total de deducciones.
                // Almacena los detalles de la deducción.
                $deducciones['detalles'][] = [
                    'id' => $row['idTipoDeduccion'],
                    'descripcion' => trim($nombre),
                    'monto' => $monto,
                    'porcentaje' => floatval(trim($porcentaje))
                ];
            }
        }
    }
    return $deducciones;
}

/**
 * Calcula el impuesto sobre la renta basado en los tramos impositivos.
 * @param float $salario_imponible El monto del salario sujeto a impuestos.
 * @return float El monto del impuesto sobre la renta.
 */
function calcularImpuestoRenta($salario_imponible) {
    $tax_brackets_path = __DIR__ . "/js/tramos_impuesto_renta.json"; // Ruta al archivo JSON de tramos de impuesto.
    // Si el archivo no existe, no se puede calcular el impuesto.
    if (!file_exists($tax_brackets_path)) {
        return 0;
    }
    // Decodifica los tramos impositivos.
    $tax_brackets = json_decode(file_get_contents($tax_brackets_path), true);
    $impuesto = 0;

    // Itera sobre cada tramo para calcular el impuesto.
    foreach ($tax_brackets as $tramo) {
        // Si el salario imponible excede el mínimo del tramo.
        if ($salario_imponible > $tramo['salario_minimo']) {
            // Calcula el monto del salario que cae dentro de este tramo.
            $monto_en_tramo = ($tramo['salario_maximo'] === null) ?
                              ($salario_imponible - $tramo['salario_minimo']) :
                              (min($salario_imponible, $tramo['salario_maximo']) - $tramo['salario_minimo']);
            // Si hay monto en el tramo, calcula y suma el impuesto.
            if ($monto_en_tramo > 0) {
                $impuesto += $monto_en_tramo * ($tramo['porcentaje'] / 100);
            }
        }
    }
    return max(0, $impuesto); // Asegura que el impuesto no sea negativo.
}

/**
 * Obtiene la planilla detallada para un mes y año específicos, calculando todos los ingresos y deducciones por colaborador.
 * @param int $anio El año de la planilla.
 * @param int $mes El mes de la planilla.
 * @param mysqli $conn Objeto de conexión a la base de datos.
 * @return array Un array con los datos detallados de la planilla para cada colaborador.
 */
function obtenerPlanillaDetallada($anio, $mes, $conn) {
    // Obtiene los días laborables del mes.
    $dias_laborales_del_mes = calcularDiasLaborales($anio, $mes);
    
    // Consulta los datos básicos de los colaboradores activos que no han sido liquidados.
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, c.idColaborador, c.salario_bruto as salario_base 
                          FROM colaborador c 
                          JOIN persona p ON c.id_persona_fk = p.idPersona 
                          WHERE c.activo = 1 AND c.idColaborador NOT IN (SELECT id_colaborador_fk FROM liquidaciones)";
    $result_colaboradores = $conn->query($sql_colaboradores);
    
    if (!$result_colaboradores) {
        // En caso de error en la consulta, se registra el error y se devuelve un array vacío.
        error_log("Error al obtener colaboradores: " . $conn->error);
        return [];
    }
    
    $planilla = []; // Array para almacenar los datos de la planilla.
    $fecha_actual_str = date('Y-m-d'); // Fecha actual para cálculos de días laborables transcurridos.
    
    $dias_laborales_transcurridos = [];
    // Si el mes o año son anteriores al actual, todos los días laborables del mes son "transcurridos".
    if ($anio < date('Y') || ($anio == date('Y') && $mes < date('n'))) {
        $dias_laborales_transcurridos = $dias_laborales_del_mes;
    } else {
        // De lo contrario, solo se consideran los días laborables hasta la fecha actual.
        foreach ($dias_laborales_del_mes as $dia) {
            if ($dia <= $fecha_actual_str) {
                $dias_laborales_transcurridos[] = $dia;
            }
        }
    }

    // Itera sobre cada colaborador para calcular su salario detallado.
    while ($colaborador = $result_colaboradores->fetch_assoc()) {
        $idColaborador = $colaborador['idColaborador'];
        $salario_base = floatval($colaborador['salario_base']);
        $salario_diario = $salario_base / 30; // Salario diario para calcular ausencias.
        $salario_hora = $salario_base / 240; // Salario por hora para permisos sin goce.

        // Obtiene las fechas de asistencia registradas para el empleado en el mes.
        $asistencias_del_mes = [];
        $stmt_asist = $conn->prepare("SELECT DISTINCT DATE(Fecha) as Fecha FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asist->bind_param("iii", $colaborador['idPersona'], $mes, $anio);
        $stmt_asist->execute();
        $res_asist = $stmt_asist->get_result();
        while($row = $res_asist->fetch_assoc()) {
            $asistencias_del_mes[] = $row['Fecha'];
        }
        $stmt_asist->close();

        $permisos_pagados = []; // Días de permisos que son pagados.
        $permisos_del_mes_por_tipo = []; // Conteo de días de permisos por tipo (para detalles).
        $deduccion_permisos_sin_goce = 0; // Monto a deducir por permisos sin goce de salario.
        
        // Consulta los permisos aprobados del colaborador.
        $sql_permisos = "SELECT p.fecha_inicio, p.fecha_fin, p.hora_inicio, p.hora_fin, tpc.Descripcion AS tipo_permiso 
                         FROM permisos p
                         JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                         WHERE p.id_colaborador_fk = ? AND p.id_estado_fk = 4"; // id_estado_fk = 4 es 'Aprobado'.
        $stmt_perm = $conn->prepare($sql_permisos);
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();

        while($row = $res_perm->fetch_assoc()) {
            $tipo = $row['tipo_permiso'];
            // Define qué tipos de permisos son pagados.
            $es_pagado = in_array(strtolower($tipo), ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad']);
            
            // Si el permiso es por horas, se calcula la deducción si no es pagado.
            if ($row['hora_inicio'] && $row['hora_fin']) {
                if (!$es_pagado) {
                    $horas = (strtotime($row['hora_fin']) - strtotime($row['hora_inicio'])) / 3600; // Calcula las horas del permiso.
                    $deduccion_permisos_sin_goce += $horas * $salario_hora; // Deduce el monto por hora.
                }
            } else {
                // Si el permiso es por días completos, itera sobre el rango de fechas.
                $inicio = new DateTime($row['fecha_inicio']);
                $fin = new DateTime($row['fecha_fin']);
                $fin->modify('+1 day'); // Ajusta para incluir la fecha de fin en el período.
                $rango = new DatePeriod($inicio, new DateInterval('P1D'), $fin);

                foreach($rango as $fecha) {
                    // Si la fecha del permiso cae en el mes actual y es pagado, se añade a los días pagados.
                    if ($fecha->format('n') == $mes) {
                        $fecha_str = $fecha->format('Y-m-d');
                        if ($es_pagado) {
                            $permisos_pagados[] = $fecha_str;
                            $permisos_del_mes_por_tipo[$tipo] = ($permisos_del_mes_por_tipo[$tipo] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        $stmt_perm->close();

        // Calcula los días efectivos de asistencia (intersección de asistencias y días laborables).
        $dias_asistencia_efectivos = array_intersect($asistencias_del_mes, $dias_laborales_del_mes);
        // Combina los días de asistencia efectivos con los permisos pagados, eliminando duplicados.
        $dias_pagables_raw = array_unique(array_merge($dias_asistencia_efectivos, $permisos_pagados));
        $numero_dias_a_pagar = count($dias_pagables_raw); // Total de días a considerar para el pago ordinario.
        // Calcula los días de ausencia restando los días pagables de los días laborables transcurridos.
        $dias_ausencia = count($dias_laborales_transcurridos) - $numero_dias_a_pagar;
        
        // Calcula el monto a deducir por los días de ausencia.
        $deduccion_por_ausencia = $dias_ausencia > 0 ? $dias_ausencia * $salario_diario : 0;
        // Calcula el pago ordinario (salario base menos deducciones por ausencia).
        $pago_ordinario = $salario_base - $deduccion_por_ausencia;

        // Obtiene el total de horas extra aprobadas para el mes.
        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio);
        $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0);
        $stmt_he->close();
        // Calcula el monto a pagar por horas extra.
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5); // Salario por hora * 1.5 (premium) * horas.

        // Calcula el salario bruto final ajustado por ausencias y horas extra.
        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;
        
        // Calcula las deducciones de ley (CCSS, Banco Popular, etc.).
        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $ccss_deduction_amount = 0;
        // Busca el monto de la deducción de CCSS para el cálculo del impuesto sobre la renta.
        foreach($deducciones_ley['detalles'] as $ded) {
            if (stripos($ded['descripcion'], 'CCSS') !== false) {
                $ccss_deduction_amount = $ded['monto'];
                break;
            }
        }
        
        // Calcula el salario imponible para el impuesto sobre la renta.
        $salario_imponible_renta = $salario_bruto_calculado - $ccss_deduction_amount;
        // Calcula el impuesto sobre la renta.
        $impuesto_renta = calcularImpuestoRenta($salario_imponible_renta);

        // Añade el Impuesto sobre la Renta como un detalle más de deducción.
        $deducciones_ley['detalles'][] = ['id' => 99, 'descripcion' => 'Impuesto Renta', 'monto' => $impuesto_renta];
        
        // Si hay deducción por permisos sin goce, se añade como detalle.
        if($deduccion_permisos_sin_goce > 0){
            $deducciones_ley['detalles'][] = ['id' => 100, 'descripcion' => 'Deducción por permisos sin goce', 'monto' => $deduccion_permisos_sin_goce];
        }

        // Calcula el total final de todas las deducciones.
        $total_deducciones_final = $deducciones_ley['total'] + $impuesto_renta + $deduccion_permisos_sin_goce;
        // Calcula el salario neto a pagar.
        $salario_neto = $salario_bruto_calculado - $total_deducciones_final;
        
        // Agrega todos los datos calculados al array del colaborador.
        $colaborador['salario_bruto_calculado'] = $salario_bruto_calculado;
        $colaborador['pago_horas_extra'] = $pago_horas_extra;
        $colaborador['deduccion_ausencia'] = $deduccion_por_ausencia;
        $colaborador['deducciones_detalles'] = $deducciones_ley['detalles'];
        $colaborador['total_deducciones'] = $total_deducciones_final;
        $colaborador['salario_neto'] = $salario_neto;
        
        $planilla[] = $colaborador; // Añade el colaborador con sus datos calculados a la planilla.
    }
    return $planilla; // Retorna la planilla completa.
}

// --- PREPARACIÓN Y GENERACIÓN DEL REPORTE EXCEL ---
// Obtiene los datos detallados de la planilla para el período seleccionado.
$planilla_detallada = obtenerPlanillaDetallada($anio, $mes, $conn);

// Si no se encontraron datos, detiene la ejecución.
if (empty($planilla_detallada)) {
    die('No se encontraron datos de planilla para el período seleccionado.');
}

// Obtener todos los tipos de deducción de la base de datos para crear los encabezados dinámicos de las columnas.
$all_deductions_q = $conn->query("SELECT Descripcion FROM tipo_deduccion_cat");
$deduction_headers = [];
while ($row = $all_deductions_q->fetch_assoc()) {
    $parts = explode(':', $row['Descripcion']);
    $deduction_headers[] = trim($parts[0]);
}
// Añade 'Impuesto Renta' y 'Ausencias' como encabezados de deducción.
$deduction_headers[] = 'Impuesto Renta';
$deduction_headers[] = 'Deducción por permisos sin goce'; // Nuevo encabezado para mayor detalle
$deduction_headers[] = 'Ausencias';
// Elimina duplicados en los encabezados de deducción.
$deduction_headers = array_unique($deduction_headers);

// --- Configuración de Encabezados HTTP para Descarga de Archivo Excel ---
// Define el tipo de contenido como una hoja de cálculo Excel.
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
// Configura el nombre del archivo para la descarga.
header("Content-Disposition: attachment; filename=planilla_detallada_{$anio}_{$mes}.xls");
// Evita el almacenamiento en caché del archivo.
header("Pragma: no-cache");
header("Expires: 0");

// Emite el Byte Order Mark (BOM) para asegurar la correcta interpretación de caracteres UTF-8 en Excel.
echo "\xEF\xBB\xBF";

// --- Inicio del Contenido HTML para el Archivo Excel ---
// Nota: Los estilos se aplican directamente en línea en las etiquetas HTML
// para una mayor compatibilidad con Excel.
$output = "<html><head><meta charset='UTF-8'></head><body>";
$output .= "<table border='1'>"; // Inicia la tabla con un borde.

// --- Encabezado de la Tabla ---
$output .= "<thead><tr>";
// Encabezados con estilos en línea
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Colaborador</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Base</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Pago Horas Extra</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Bruto Calculado</th>";
// Agrega dinámicamente las columnas para cada tipo de deducción con estilos en línea.
foreach ($deduction_headers as $header) {
    $output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>" . htmlspecialchars($header) . "</th>";
}
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Total Deducciones</th>"; // Columna de Total Deducciones.
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Neto</th>"; // Columna de Salario Neto.
$output .= "</tr></thead>";

// --- Cuerpo de la Tabla ---
$output .= "<tbody>";
$is_odd_row = true; // Variable para alternar los estilos de fila.

// Itera sobre cada fila de datos de la planilla.
foreach ($planilla_detallada as $col) {
    // Define el estilo de fondo para la fila (alternando colores).
    $row_style = $is_odd_row ? "background-color:#DCE6F1;" : "background-color:#FFFFFF;";
    $output .= "<tr style='{$row_style}'>"; // Inicia la fila con el estilo de fondo.

    // Columnas principales de ingresos.
    $output .= "<td>" . htmlspecialchars($col['Nombre'] . ' ' . $col['Apellido1']) . "</td>";
    $output .= "<td>" . number_format($col['salario_base'], 2) . "</td>";
    $output .= "<td>" . number_format($col['pago_horas_extra'], 2) . "</td>";
    // Salario Bruto Calculado siempre en negrita.
    $output .= "<td style='font-weight:bold;'>" . number_format($col['salario_bruto_calculado'], 2) . "</td>";

    // Crea un array temporal para acceder fácilmente a las deducciones del empleado.
    $emp_deductions = [];
    $emp_deductions['Ausencias'] = $col['deduccion_ausencia']; // Agrega la deducción por ausencias.
    // Si la deducción por permisos sin goce tiene un valor, se añade al array.
    if ($col['deduccion_permisos_sin_goce'] > 0) {
        $emp_deductions['Deducción por permisos sin goce'] = $col['deduccion_permisos_sin_goce'];
    }
    // Añade el resto de deducciones detalladas al array temporal.
    foreach ($col['deducciones_detalles'] as $ded_detail) {
        $emp_deductions[$ded_detail['descripcion']] = $ded_detail['monto'];
    }

    // Imprime el valor de cada deducción en su columna correspondiente, usando 0 si no existe.
    foreach ($deduction_headers as $header) {
        $monto = isset($emp_deductions[$header]) ? $emp_deductions[$header] : 0;
        $output .= "<td>" . number_format($monto, 2) . "</td>";
    }

    // Columnas de totales y neto, con estilos en línea.
    $output .= "<td style='font-weight:bold;'>" . number_format($col['total_deducciones'], 2) . "</td>";
    $output .= "<td style='background-color:#C5E0B4; font-weight:bold;'>" . number_format($col['salario_neto'], 2) . "</td>";
    $output .= "</tr>"; // Cierra la fila.

    $is_odd_row = !$is_odd_row; // Cambia el estado para la siguiente fila (alterna el color).
}
$output .= "</tbody>"; // Cierra el cuerpo de la tabla.
$output .= "</table></body></html>"; // Cierra la tabla y el documento HTML.

echo $output; // Envía el contenido HTML generado al navegador para ser descargado como Excel.
exit; // Termina la ejecución del script.
?>