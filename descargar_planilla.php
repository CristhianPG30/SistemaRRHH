<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['rol'], [1, 4])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

if (!isset($_GET['anio']) || !isset($_GET['mes'])) die('Error: Período no especificado.');

$anio = intval($_GET['anio']);
$mes = intval($_GET['mes']);

// --------------------- FUNCIONES (copiadas de nominas.php) ---------------------
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
    $deducciones = ['total' => 0, 'monto_ccss' => 0, 'detalles' => []];
    $result = $conn->query("SELECT idTipoDeduccion, Descripcion FROM tipo_deduccion_cat");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            @list($nombre, $porcentaje) = explode(':', $row['Descripcion']);
            $nombre_limpio = trim($nombre);
            $porcentaje_float = floatval(trim($porcentaje));
            
            if (is_numeric(trim($porcentaje))) {
                $monto = $salario_bruto * ($porcentaje_float / 100);
                $deducciones['total'] += $monto;
                
                if (stripos($nombre_limpio, 'CCSS') !== false) {
                    $deducciones['monto_ccss'] = $monto;
                }

                $deducciones['detalles'][] = ['id' => $row['idTipoDeduccion'], 'descripcion' => $nombre_limpio, 'monto' => $monto, 'porcentaje' => $porcentaje_float];
            }
        }
    }
    return $deducciones;
}

function calcularImpuestoRenta($salario_imponible, $cantidad_hijos, $es_casado) {
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    if (!file_exists($tax_config_path)) return 0;
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    // Utiliza la configuración para el año 2025, ya que es la que se proporciona en el archivo JSON.
    $tax_brackets = $config_data['tramos_salariales_2025'] ?? [];
    $creditos_fiscales = $config_data['creditos_fiscales_2025'] ?? ['hijo' => 0, 'conyuge' => 0];
    $credito_por_hijo = $creditos_fiscales['hijo'] ?? 0;
    $credito_conyuge = $creditos_fiscales['conyuge'] ?? 0;

    $impuesto_calculado = 0;

    // Itera hacia atrás para encontrar el tramo más alto aplicable primero.
    for ($i = count($tax_brackets) - 1; $i >= 0; $i--) {
        $tramo = $tax_brackets[$i];
        if ($salario_imponible > $tramo['min']) {
            $excedente = $salario_imponible - $tramo['min'];
            $impuesto_calculado = ($excedente * ($tramo['tasa'] / 100)) + $tramo['impuesto_sobre_exceso_de'];
            break;
        }
    }
    
    $credito_total_hijos = $cantidad_hijos * $credito_por_hijo;
    $credito_total_conyuge = $es_casado ? $credito_conyuge : 0;
    
    $impuesto_final = $impuesto_calculado - $credito_total_hijos - $credito_total_conyuge;
    
    return max(0, $impuesto_final);
}

function obtenerPlanillaDetallada($anio, $mes, $conn) {
    $dias_laborales_del_mes = calcularDiasLaborales($anio, $mes);
    $sql_colaboradores = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.cantidad_hijos, p.id_estado_civil_fk, c.idColaborador, c.salario_bruto as salario_base 
        FROM colaborador c 
        JOIN persona p ON c.id_persona_fk = p.idPersona 
        WHERE c.activo = 1 AND c.idColaborador NOT IN (SELECT id_colaborador_fk FROM liquidaciones)";
    $result_colaboradores = $conn->query($sql_colaboradores);
    if (!$result_colaboradores) return [];

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

        // Permisos pagados y sin goce (cuenta días por tipo)
        $desglose_permisos_pagados = [];
        $dias_permiso_con_goce = [];
        $dias_permiso_sin_goce = [];
        $sql_permisos = "SELECT p.fecha_inicio, p.fecha_fin, tpc.Descripcion AS tipo_permiso 
            FROM permisos p
            JOIN tipo_permiso_cat tpc ON p.id_tipo_permiso_fk = tpc.idTipoPermiso
            WHERE p.id_colaborador_fk = ? AND p.id_estado_fk = 4";
        $stmt_perm = $conn->prepare($sql_permisos);
        $stmt_perm->bind_param("i", $idColaborador);
        $stmt_perm->execute();
        $res_perm = $stmt_perm->get_result();
        while($row_perm = $res_perm->fetch_assoc()) {
            $inicio = new DateTime($row_perm['fecha_inicio']);
            $fin = new DateTime($row_perm['fecha_fin']);
            $fin->modify('+1 day');
            $rango = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
            foreach($rango as $fecha) {
                if ($fecha->format('n') == $mes && date('N', strtotime($fecha->format('Y-m-d'))) < 6) {
                    $tipo = strtolower($row_perm['tipo_permiso']);
                    $desglose_permisos_pagados[$row_perm['tipo_permiso']] = ($desglose_permisos_pagados[$row_perm['tipo_permiso']] ?? 0) + 1;
                    if (in_array($tipo, ['vacaciones', 'luto', 'maternidad', 'paternidad', 'día libre', 'incapacidad', 'médico'])) {
                        $dias_permiso_con_goce[] = $fecha->format('Y-m-d');
                    } else {
                        $dias_permiso_sin_goce[] = $fecha->format('Y-m-d');
                    }
                }
            }
        }
        $stmt_perm->close();

        $dias_cubiertos = array_unique(array_merge($asistencias_del_mes, $dias_permiso_con_goce, $dias_permiso_sin_goce));
        $dias_ausencia_injustificada = count(array_diff($dias_laborales_transcurridos, $dias_cubiertos));
        $deduccion_por_ausencia = $dias_ausencia_injustificada * $salario_diario;

        $deduccion_permisos_sin_goce = count($dias_permiso_sin_goce) * $salario_diario;

        $pago_ordinario = $salario_base - $deduccion_por_ausencia - $deduccion_permisos_sin_goce;

        $stmt_he = $conn->prepare("SELECT SUM(cantidad_horas) AS total_horas FROM horas_extra WHERE estado = 'Aprobada' AND Colaborador_idColaborador = ? AND MONTH(Fecha) = ? AND YEAR(Fecha) = ?");
        $stmt_he->bind_param("iii", $idColaborador, $mes, $anio); $stmt_he->execute();
        $total_horas_extra = floatval($stmt_he->get_result()->fetch_assoc()['total_horas'] ?? 0); $stmt_he->close();
        $pago_horas_extra = $total_horas_extra * (($salario_base / 240) * 1.5);

        $salario_bruto_calculado = $pago_ordinario + $pago_horas_extra;

        $deducciones_ley = calcularDeduccionesDeLey($salario_bruto_calculado, $conn);
        $salario_imponible_renta = $salario_bruto_calculado - $deducciones_ley['monto_ccss'];
        $es_casado = ($colaborador['id_estado_civil_fk'] == 2);
        $impuesto_renta = calcularImpuestoRenta($salario_imponible_renta, $colaborador['cantidad_hijos'], $es_casado);

        $deducciones_ley['detalles'][] = ['id' => 99, 'descripcion' => 'Impuesto sobre la Renta', 'monto' => $impuesto_renta, 'porcentaje' => 'N/A'];
        $total_deducciones_final = $deducciones_ley['total'] + $impuesto_renta;
        $salario_neto = $salario_bruto_calculado - $total_deducciones_final;

        $planilla[] = [
            'Nombre' => $colaborador['Nombre'],
            'Apellido1' => $colaborador['Apellido1'],
            'salario_base' => $salario_base,
            'pago_horas_extra' => $pago_horas_extra,
            'desglose_permisos_pagados' => $desglose_permisos_pagados,
            'dias_vacaciones' => $desglose_permisos_pagados['Vacaciones'] ?? 0,
            'dias_permiso_sg' => $desglose_permisos_pagados['Permiso personal'] ?? 0,
            'deduccion_ausencia' => $deduccion_por_ausencia,
            'deduccion_permisos_sin_goce' => $deduccion_permisos_sin_goce,
            'deducciones_detalles' => $deducciones_ley['detalles'],
            'total_deducciones' => $total_deducciones_final,
            'salario_bruto_calculado' => $salario_bruto_calculado,
            'salario_neto' => $salario_neto
        ];
    }
    return $planilla;
}

// ----------------------- DESCARGA CON DESGLOSE -----------------------
$planilla_detallada = obtenerPlanillaDetallada($anio, $mes, $conn);

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=planilla_detallada_{$anio}_{$mes}.xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";

$output = "<html><head><meta charset='UTF-8'></head><body>";
$output .= "<table border='1' style='border-collapse:collapse; font-family:Arial;'>";

// Encabezados agrupados (igual a la imagen)
$output .= "<tr style='background-color:#4F81BD; color:#fff;'>
    <th colspan='4'>INGRESOS Y PERMISOS CON GOCE</th>
    <th colspan='3'>AJUSTES Y DEDUCCIONES</th>
    <th colspan='4'>DEDUCCIONES DE LEY</th>
    <th>Total Deducciones de Ley</th>
    <th style='background-color:#548235;'>SALARIO NETO A PAGAR</th>
</tr>";

$output .= "<tr style='background-color:#8EA9DB; color:#fff;'>";
$output .= "<th>Colaborador</th>
    <th>Salario Base</th>
    <th>Pago Horas Extra</th>
    <th>Vacaciones (días)</th>
    <th>Ausencias Injustificadas</th>
    <th>Permiso s/g (días)</th>
    <th>Permiso s/g (₡)</th>
    <th>Ahorro Obligatorio (1%)</th>
    <th>Banco Popular (1%)</th>
    <th>CCSS Obrero (10.5%)</th>
    <th>Impuesto sobre la Renta</th>
    <th>Total Deducciones de Ley</th>
    <th style='background-color:#548235;'>SALARIO NETO A PAGAR</th>
</tr>";

foreach ($planilla_detallada as $col) {
    $output .= "<tr>";
    $output .= "<td>".htmlspecialchars($col['Nombre'].' '.$col['Apellido1'])."</td>";
    $output .= "<td>₡".number_format($col['salario_base'],2)."</td>";
    $output .= "<td>₡".number_format($col['pago_horas_extra'],2)."</td>";
    $output .= "<td align='center'>".$col['dias_vacaciones']."</td>";
    $output .= "<td>-₡".number_format($col['deduccion_ausencia'],2)."</td>";
    $output .= "<td align='center'>".$col['dias_permiso_sg']."</td>";
    $output .= "<td>-₡".number_format($col['deduccion_permisos_sin_goce'],2)."</td>";

    // Deducciones de ley
    $ahorro = $bp = $ccss = $renta = 0;
    foreach ($col['deducciones_detalles'] as $ded) {
        if (stripos($ded['descripcion'], 'Ahorro') !== false) $ahorro = $ded['monto'];
        if (stripos($ded['descripcion'], 'Banco Popular') !== false) $bp = $ded['monto'];
        if (stripos($ded['descripcion'], 'CCSS') !== false) $ccss = $ded['monto'];
        if (stripos($ded['descripcion'], 'Renta') !== false) $renta = $ded['monto'];
    }
    $output .= "<td>-₡".number_format($ahorro,2)."</td>";
    $output .= "<td>-₡".number_format($bp,2)."</td>";
    $output .= "<td>-₡".number_format($ccss,2)."</td>";
    $output .= "<td>-₡".number_format($renta,2)."</td>";

    $output .= "<td style='color:#c00;'>-₡".number_format($col['total_deducciones'],2)."</td>";
    $output .= "<td style='font-weight:bold; background-color:#C5E0B4;'>₡".number_format($col['salario_neto'],2)."</td>";
    $output .= "</tr>";
}
$output .= "</table></body></html>";

echo $output;
exit;
?>