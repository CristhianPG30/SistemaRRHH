<?php
// Inicia la sesión si no está iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica si el usuario ha iniciado sesión y tiene el rol permitido.
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// --- Validación de Parámetros de Entrada ---
if (!isset($_GET['anio']) || !isset($_GET['mes'])) {
    die('Error: Período no especificado (año o mes).');
}

$anio = intval($_GET['anio']);
$mes = intval($_GET['mes']);

// --- FUNCIONES DE CÁLCULO (Sincronizadas con nominas.php) ---

function obtenerDiasFeriados($anio) {
    $feriadosFilePath = 'js/feriados.json';
    if (!file_exists($feriadosFilePath)) return [];
    $feriados_data = json_decode(file_get_contents($feriadosFilePath), true);
    return is_array($feriados_data) ? array_column($feriados_data, 'fecha') : [];
}

function calcularDiasLaborales($anio, $mes) {
    $dias_laborales = [];
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $feriados = obtenerDiasFeriados($anio);
    for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
        $fecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
        if (date('N', strtotime($fecha)) < 6 && !in_array($fecha, $feriados)) {
            $dias_laborales[] = $fecha;
        }
    }
    return $dias_laborales;
}

function calcularDeduccionesDeLey($salario_bruto, $conn) {
    $deducciones = ['total' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * (floatval(trim($porcentaje)) / 100);
                $deducciones['total'] += $monto;
                $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => trim($nombre), 'monto' => $monto, 'porcentaje' => floatval(trim($porcentaje))];
            }
        }
    }
    return $deducciones;
}

// --- CORRECCIÓN: Función de Impuesto sobre la Renta actualizada ---
function calcularImpuestoRenta($salario_imponible, $cantidad_hijos)
{
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    if (!file_exists($tax_config_path)) return 0;
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    $tax_brackets = $config_data['tramos'] ?? [];
    $credito_por_hijo = $config_data['creditos_fiscales']['hijo'] ?? 0;
    
    $impuesto_calculado = 0;
    for ($i = count($tax_brackets) - 1; $i >= 0; $i--) {
        $tramo = $tax_brackets[$i];
        if ($salario_imponible > $tramo['salario_minimo']) {
            $excedente = $salario_imponible - $tramo['salario_minimo'];
            $impuesto_calculado = ($tramo['monto_fijo'] ?? 0) + ($excedente * ($tramo['porcentaje'] / 100));
            break; 
        }
    }
    
    $credito_total_hijos = $cantidad_hijos * $credito_por_hijo;
    $impuesto_final = $impuesto_calculado - $credito_total_hijos;
    
    return max(0, $impuesto_final);
}

function obtenerPlanillaDetallada($anio, $mes, $conn)
{
    $dias_laborales_del_mes = calcularDiasLaborales($anio, $mes);
    
    // --- CORRECCIÓN: Se añade p.cantidad_hijos a la consulta ---
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.cantidad_hijos, c.idColaborador, c.salario_bruto as salario_base 
                          FROM colaborador c 
                          JOIN persona p ON c.id_persona_fk = p.idPersona 
                          WHERE c.activo = 1 AND c.idColaborador NOT IN (SELECT id_colaborador_fk FROM liquidaciones)";
    
    $result_colaboradores = $conn->query($sql_colaboradores);
    if (!$result_colaboradores) {
        error_log("Error al obtener colaboradores: " . $conn->error);
        return [];
    }
    
    $planilla = [];
    $fecha_actual_str = date('Y-m-d');
    
    $dias_laborales_transcurridos = [];
    if ($anio < date('Y') || ($anio == date('Y') && $mes < date('n'))) {
        $dias_laborales_transcurridos = $dias_laborales_del_mes;
    } else {
        foreach ($dias_laborales_del_mes as $dia) {
            if ($dia <= $fecha_actual_str) $dias_laborales_transcurridos[] = $dia;
        }
    }

    while ($colaborador = $result_colaboradores->fetch_assoc()) {
        $idColaborador = $colaborador['idColaborador'];
        $salario_base = floatval($colaborador['salario_base']);
        $salario_diario = $salario_base / 30;
        $salario_hora = $salario_base / 240;

        $asistencias_del_mes = [];
        $stmt_asist = $conn->prepare("SELECT DISTINCT DATE(Fecha) as Fecha FROM control_de_asistencia WHERE Persona_idPersona = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_asist->bind_param("iii", $colaborador['idPersona'], $mes, $anio);
        $stmt_asist->execute();
        $res_asist = $stmt_asist->get_result();
        while($row = $res_asist->fetch_assoc()) { $asistencias_del_mes[] = $row['Fecha']; }
        $stmt_asist->close();

        $permisos_pagados = [];
        $deduccion_permisos_sin_goce = 0;
        
        $sql_permisos = "SELECT p.fecha_inicio, p.fecha_fin, p.hora_inicio, p.hora_fin, tpc.Descripcion AS tipo_permiso 
                         FROM permisos p
                         JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
                         WHERE p.id_colaborador_fk = ? AND p.id_estado_fk = 4";
        $stmt_perm = $conn->prepare($sql_permisos);
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        while($row = $res_perm->fetch_assoc()) {
            $tipo = $row['tipo_permiso'];
            $es_pagado = in_array(strtolower($tipo), ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad']);
            
            if ($row['hora_inicio'] && $row['hora_fin']) {
                if (!$es_pagado) {
                    $horas = (strtotime($row['hora_fin']) - strtotime($row['hora_inicio'])) / 3600;
                    $deduccion_permisos_sin_goce += $horas * $salario_hora;
                }
            } else {
                $inicio = new DateTime($row['fecha_inicio']); $fin = new DateTime($row['fecha_fin']); $fin->modify('+1 day');
                $rango = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
                foreach($rango as $fecha) {
                    if ($fecha->format('n') == $mes && $es_pagado) {
                        $permisos_pagados[] = $fecha->format('Y-m-d');
                    }
                }
            }
        }
        $stmt_perm->close();

        $dias_asistencia_efectivos = array_intersect($asistencias_del_mes, $dias_laborales_del_mes);
        $dias_pagables_raw = array_unique(array_merge($dias_asistencia_efectivos, $permisos_pagados));
        $numero_dias_a_pagar = count($dias_pagables_raw);
        $dias_ausencia = count($dias_laborales_transcurridos) - $numero_dias_a_pagar;
        
        $deduccion_por_ausencia = $dias_ausencia > 0 ? $dias_ausencia * $salario_diario : 0;
        $pago_ordinario = $salario_base - $deduccion_por_ausencia;

        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio); $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0); $stmt_he->close();
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5);

        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;
        
        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $ccss_deduction_amount = 0;
        foreach($deducciones_ley['detalles'] as $ded) {
            if (stripos($ded['descripcion'], 'CCSS') !== false) {
                $ccss_deduction_amount = $ded['monto'];
                break;
            }
        }
        
        $salario_imponible_renta = $salario_bruto_calculado - $ccss_deduction_amount;
        
        // Se llama a la función de renta con la cantidad de hijos
        $impuesto_renta = calcularImpuestoRenta($salario_imponible_renta, $colaborador['cantidad_hijos']);

        $deducciones_ley['detalles'][] = ['id' => 99, 'descripcion' => 'Impuesto Renta', 'monto' => $impuesto_renta];
        if($deduccion_permisos_sin_goce > 0){
            $deducciones_ley['detalles'][] = ['id' => 100, 'descripcion' => 'Deducción por permisos sin goce', 'monto' => $deduccion_permisos_sin_goce];
        }

        $total_deducciones_final = $deducciones_ley['total'] + $impuesto_renta + $deduccion_permisos_sin_goce;
        $salario_neto = $salario_bruto_calculado - $total_deducciones_final;
        
        $colaborador['salario_bruto_calculado'] = $salario_bruto_calculado;
        $colaborador['pago_horas_extra'] = $pago_horas_extra;
        $colaborador['deduccion_ausencia'] = $deduccion_por_ausencia;
        $colaborador['deducciones_detalles'] = $deducciones_ley['detalles'];
        $colaborador['total_deducciones'] = $total_deducciones_final;
        $colaborador['salario_neto'] = $salario_neto;
        
        $planilla[] = $colaborador;
    }
    return $planilla;
}

// --- PREPARACIÓN Y GENERACIÓN DEL REPORTE EXCEL ---
$planilla_detallada = obtenerPlanillaDetallada($anio, $mes, $conn);

if (empty($planilla_detallada)) {
    die('No se encontraron datos de planilla para el período seleccionado.');
}

$all_deductions_q = $conn->query("SELECT Descripcion FROM tipo_deduccion_cat");
$deduction_headers = [];
while ($row = $all_deductions_q->fetch_assoc()) {
    $parts = explode(':', $row['Descripcion']);
    $deduction_headers[] = trim($parts[0]);
}
$deduction_headers[] = 'Impuesto Renta';
$deduction_headers[] = 'Deducción por permisos sin goce';
$deduction_headers[] = 'Ausencias';
$deduction_headers = array_unique($deduction_headers);

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=planilla_detallada_{$anio}_{$mes}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";

$output = "<html><head><meta charset='UTF-8'></head><body>";
$output .= "<table border='1'>";

$output .= "<thead><tr>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Colaborador</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Base</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Pago Horas Extra</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Bruto Calculado</th>";
foreach ($deduction_headers as $header) {
    $output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>" . htmlspecialchars($header) . "</th>";
}
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Total Deducciones</th>";
$output .= "<th style='background-color:#4F81BD; color:#FFFFFF; font-weight:bold;'>Salario Neto</th>";
$output .= "</tr></thead>";

$output .= "<tbody>";
$is_odd_row = true;
foreach ($planilla_detallada as $col) {
    $row_style = $is_odd_row ? "background-color:#DCE6F1;" : "background-color:#FFFFFF;";
    $output .= "<tr style='{$row_style}'>";

    $output .= "<td>" . htmlspecialchars($col['Nombre'] . ' ' . $col['Apellido1']) . "</td>";
    $output .= "<td>" . number_format($col['salario_base'], 2) . "</td>";
    $output .= "<td>" . number_format($col['pago_horas_extra'], 2) . "</td>";
    $output .= "<td style='font-weight:bold;'>" . number_format($col['salario_bruto_calculado'], 2) . "</td>";

    $emp_deductions = [];
    $emp_deductions['Ausencias'] = $col['deduccion_ausencia'];
    
    // Corregido para manejar el caso en que la clave no exista
    $emp_deductions['Deducción por permisos sin goce'] = $col['deduccion_permisos_sin_goce'] ?? 0;

    foreach ($col['deducciones_detalles'] as $ded_detail) {
        $emp_deductions[$ded_detail['descripcion']] = $ded_detail['monto'];
    }

    foreach ($deduction_headers as $header) {
        $monto = isset($emp_deductions[$header]) ? $emp_deductions[$header] : 0;
        $output .= "<td>" . number_format($monto, 2) . "</td>";
    }

    $output .= "<td style='font-weight:bold;'>" . number_format($col['total_deducciones'], 2) . "</td>";
    $output .= "<td style='background-color:#C5E0B4; font-weight:bold;'>" . number_format($col['salario_neto'], 2) . "</td>";
    $output .= "</tr>";
    $is_odd_row = !$is_odd_row;
}
$output .= "</tbody>";
$output .= "</table></body></html>";

echo $output;
exit;
?>