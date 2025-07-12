<?php

// Para asegurar que los números se muestren bien formateados
header('Content-Type: text/html; charset=utf-8');

// Esta es la función de cálculo de impuesto sobre la renta correcta y final.
function calcularImpuestoRenta($salario_imponible, $cantidad_hijos) {
    $tax_config_path = __DIR__ . "/js/tramos_impuesto_renta.json";
    if (!file_exists($tax_config_path)) {
        return "Error: No se encontró el archivo tramos_impuesto_renta.json";
    }
    
    $config_data = json_decode(file_get_contents($tax_config_path), true);
    $tax_brackets = $config_data['tramos'] ?? [];
    $credito_por_hijo = $config_data['creditos_fiscales']['hijo'] ?? 0;
    
    $impuesto_calculado = 0;

    // Recorre los tramos en orden inverso para encontrar el correcto
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

// DEFINE LOS CASOS DE PRUEBA
$casos_de_prueba = [
    [
        'descripcion' => 'Caso 1: Salario de ₡1,500,000 sin hijos',
        'salario_imponible' => 1500000,
        'cantidad_hijos' => 0
    ],
    [
        'descripcion' => 'Caso 2: Salario de ₡2,500,000 sin hijos',
        'salario_imponible' => 2500000,
        'cantidad_hijos' => 0
    ],
    [
        'descripcion' => 'Caso 3: Salario de ₡1,500,000 con 2 hijos (para probar créditos)',
        'salario_imponible' => 1500000,
        'cantidad_hijos' => 2
    ],
    [
        'descripcion' => 'Caso 4: Salario de ₡2,500,000 con 1 hijo',
        'salario_imponible' => 2500000,
        'cantidad_hijos' => 1
    ]
];

// EJECUTA LAS PRUEBAS Y MUESTRA LOS RESULTADOS
echo "<pre style='font-family: monospace; line-height: 1.6;'>";
echo "<h1><span style='color: #2dce89;'>✔</span> Pruebas de Cálculo de Impuesto sobre la Renta (Según Ley)</h1>";

foreach ($casos_de_prueba as $caso) {
    echo "--------------------------------------------------<br>";
    echo "<b>" . $caso['descripcion'] . "</b><br>";
    echo "   - Salario Imponible: ₡" . number_format($caso['salario_imponible'], 2) . "<br>";
    echo "   - Cantidad de Hijos: " . $caso['cantidad_hijos'] . "<br>";

    $impuesto = calcularImpuestoRenta($caso['salario_imponible'], $caso['cantidad_hijos']);

    echo "<b>=> Impuesto Calculado: <span style='color: #f5365c;'>₡" . number_format($impuesto, 2) . "</span></b><br>";
    echo "--------------------------------------------------<br><br>";
}

echo "</pre>";

?>