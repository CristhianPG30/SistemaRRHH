<?php
// Iniciar sesión de manera segura y configurar las opciones de sesión
session_start([
    'cookie_lifetime' => 0,
    'cookie_secure' => true, // Asegúrate de usar HTTPS para esto si tienes SSL
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
]);

// Regenerar el ID de sesión para evitar ataques de fijación de sesión
session_regenerate_id(true);

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];

// Incluir la conexión a la base de datos
include 'db.php';

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener lista de empleados con sus días de vacaciones disponibles y si ya se generó el aguinaldo
function obtenerEmpleadosConVacacionesYAguinaldo() {
    global $conn;
    $anioActual = date('Y');
    $sql = "SELECT p.idPersona, p.Nombre, p.Apellido1, p.Cedula, c.idColaborador, c.Fechadeingreso, p.Salario_bruto,
            COALESCE(SUM(CAST(v.Cantidad_Disponible AS UNSIGNED)), 0) AS Cantidad_Disponible,
            (SELECT COUNT(*) FROM aguinaldo a WHERE a.Colaborador_idColaborador = c.idColaborador AND YEAR(a.Fechafin) = $anioActual) AS aguinaldo_generado,
            (SELECT COUNT(*) FROM liquidaciones l WHERE l.Colaborador_idColaborador = c.idColaborador) AS liquidacion_generada
            FROM persona p
            JOIN colaborador c ON p.idPersona = c.Persona_idPersona
            LEFT JOIN vacaciones v ON c.Persona_idPersona = v.Persona_idPersona
            GROUP BY c.idColaborador";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error al obtener empleados: " . $conn->error);
    }
    $empleados = [];
    while ($row = $result->fetch_assoc()) {
        $empleados[] = $row;
    }
    return $empleados;
}

$empleados = obtenerEmpleadosConVacacionesYAguinaldo();

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo de Liquidación - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h1 class="text-center">Cálculo de Liquidación</h1>
    <p class="text-center">Calcula la liquidación de un empleado basado en los años de servicio y vacaciones disponibles.</p>

    <?php
    // Mostrar mensajes si existen
    if (isset($mensajeExito)) {
        echo "<div class='alert alert-success'>$mensajeExito</div>";
    } elseif (isset($mensajeError)) {
        echo "<div class='alert alert-danger'>$mensajeError</div>";
    }
    ?>

    <div class="card p-4 mb-5 shadow">
        <form id="formLiquidacion" method="post" action="">
            <!-- Agregar token CSRF -->
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

            <div class="mb-4">
                <label for="empleado" class="form-label">Empleado:</label>
                <select id="empleado" name="empleado" class="form-select" onchange="llenarCampos()" required>
                    <option value="">Seleccione un empleado</option>
                    <?php foreach ($empleados as $empleado): ?>
                        <option value="<?= htmlspecialchars($empleado['idColaborador'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-fecha-ingreso="<?= htmlspecialchars($empleado['Fechadeingreso'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-salario-bruto="<?= htmlspecialchars($empleado['Salario_bruto'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-vacaciones="<?= htmlspecialchars($empleado['Cantidad_Disponible'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-aguinaldo-generado="<?= htmlspecialchars($empleado['aguinaldo_generado'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-liquidacion-generada="<?= htmlspecialchars($empleado['liquidacion_generada'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($empleado['Nombre'] . " " . $empleado['Apellido1'] . " - " . $empleado['Cedula'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campo de Fecha de Salida -->
            <div class="mb-4">
                <label for="fechaSalida" class="form-label">Fecha de Salida:</label>
                <input type="date" id="fechaSalida" name="fechaSalida" class="form-control" required>
            </div>

            <!-- Campo de Tipo de Moneda (fijo en CRC) -->
            <div class="mb-4">
                <label for="tipoMoneda" class="form-label">Tipo de Moneda:</label>
                <input type="text" id="tipoMoneda" name="tipoMoneda" class="form-control" value="CRC" readonly>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="aniosServicio" class="form-label">Años de Servicio:</label>
                    <input type="number" id="aniosServicio" name="aniosServicio" class="form-control" min="0" readonly required>
                </div>

                <div class="col-md-6 mb-4">
                    <label for="salarioBase" class="form-label">Salario Base Mensual (₡):</label>
                    <input type="number" id="salarioBase" name="salarioBase" class="form-control" step="0.01" readonly required>
                </div>
            </div>

            <!-- Campo de vacaciones disponibles (solo lectura) -->
            <div class="mb-4">
                <label for="diasDisponibles" class="form-label">Días de Vacaciones Disponibles:</label>
                <input type="number" id="diasDisponibles" name="diasDisponibles" class="form-control" min="0" readonly required>
            </div>

            <div class="mb-4">
                <label for="razon" class="form-label">Razón de la Liquidación:</label>
                <select id="razon" name="razon" class="form-select" required>
                    <option value="">Seleccione una razón</option>
                    <option value="1">Despido sin responsabilidad patronal</option>
                    <option value="2">Renuncia voluntaria</option>
                    <option value="3">Mutuo acuerdo</option>
                    <!-- Asigna valores numéricos ya que 'Razon' es decimal -->
                </select>
            </div>

            <!-- Campos calculados -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="cesantia" class="form-label">Cesantía (₡):</label>
                    <input type="number" id="cesantia" name="cesantia" class="form-control" step="0.01" readonly>
                </div>

                <div class="col-md-6 mb-4">
                    <label for="aguinaldoProporcional" class="form-label">Aguinaldo Proporcional (₡):</label>
                    <input type="number" id="aguinaldoProporcional" name="aguinaldoProporcional" class="form-control" step="0.01" readonly>
                </div>
            </div>

            <div class="mb-4">
                <label for="vacacionesPago" class="form-label">Pago de Vacaciones (₡):</label>
                <input type="number" id="vacacionesPago" name="vacacionesPago" class="form-control" step="0.01" readonly>
            </div>

            <div class="mb-4">
                <label for="totalLiquidacion" class="form-label">Total de Liquidación (₡):</label>
                <input type="number" id="totalLiquidacion" name="totalLiquidacion" class="form-control" step="0.01" readonly>
            </div>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary me-2" onclick="mostrarConfirmacion()">Calcular Liquidación</button>
                <button type="reset" class="btn btn-secondary" onclick="limpiarCampos()">Limpiar</button>
            </div>
        </form>
    </div>

    <!-- Modal de Confirmación -->
    <div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="confirmacionModalLabel" class="modal-title">Confirmación de Liquidación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea registrar esta liquidación?</p>
                    <!-- Mostrar resumen -->
                    <ul class="list-group">
                        <li class="list-group-item"><strong>Empleado:</strong> <span id="resumenEmpleado"></span></li>
                        <li class="list-group-item"><strong>Fecha de Salida:</strong> <span id="resumenFechaSalida"></span></li>
                        <li class="list-group-item"><strong>Tipo de Moneda:</strong> <span id="resumenTipoMoneda"></span></li>
                        <li class="list-group-item"><strong>Años de Servicio:</strong> <span id="resumenAniosServicio"></span></li>
                        <li class="list-group-item"><strong>Salario Base Mensual:</strong> <span id="resumenSalarioBase"></span></li>
                        <li class="list-group-item"><strong>Vacaciones Disponibles:</strong> <span id="resumenDiasDisponibles"></span></li>
                        <li class="list-group-item"><strong>Cesantía:</strong> <span id="resumenCesantia"></span></li>
                        <li class="list-group-item"><strong>Aguinaldo Proporcional:</strong> <span id="resumenAguinaldo"></span></li>
                        <li class="list-group-item"><strong>Total de Liquidación:</strong> <span id="resumenTotalLiquidacion"></span></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <form method="post" action="">
                        <!-- Incluir campos ocultos con los datos -->
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="empleado" id="hiddenEmpleado">
                        <input type="hidden" name="fechaSalida" id="hiddenFechaSalida">
                        <input type="hidden" name="tipoMoneda" id="hiddenTipoMoneda">
                        <input type="hidden" name="aniosServicio" id="hiddenAniosServicio">
                        <input type="hidden" name="salarioBase" id="hiddenSalarioBase">
                        <input type="hidden" name="diasDisponibles" id="hiddenDiasDisponibles">
                        <input type="hidden" name="razon" id="hiddenRazon">
                        <input type="hidden" name="cesantia" id="hiddenCesantia">
                        <input type="hidden" name="aguinaldoProporcional" id="hiddenAguinaldoProporcional">
                        <input type="hidden" name="vacacionesPago" id="hiddenVacacionesPago">
                        <input type="hidden" name="totalLiquidacion" id="hiddenTotalLiquidacion">
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Procesar la liquidación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['empleado'])) {
        // Verificar el token CSRF
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $mensajeError = "Acción no autorizada.";
            exit;
        }

        // Sanitizar y validar entradas
        $idColaborador = intval($_POST['empleado']);
        $fechaSalida = $_POST['fechaSalida'];
        $tipoMoneda = $_POST['tipoMoneda']; // Será 'CRC'
        $aniosServicio = intval($_POST['aniosServicio']);
        $salarioBase = floatval($_POST['salarioBase']);
        $diasDisponibles = intval($_POST['diasDisponibles']);
        $razon = floatval($_POST['razon']); // Debe ser numérico porque la columna 'Razon' es decimal
        $cesantia = floatval($_POST['cesantia']);
        $aguinaldoProporcional = floatval($_POST['aguinaldoProporcional']);
        $vacacionesPago = floatval($_POST['vacacionesPago']);
        $totalLiquidacion = floatval($_POST['totalLiquidacion']);

        // Verificar si el colaborador ya fue liquidado
        $sqlVerificar = "SELECT COUNT(*) AS existe FROM liquidaciones WHERE Colaborador_idColaborador = ?";
        $stmtVerificar = $conn->prepare($sqlVerificar);
        $stmtVerificar->bind_param("i", $idColaborador);
        $stmtVerificar->execute();
        $resultVerificar = $stmtVerificar->get_result();
        $rowVerificar = $resultVerificar->fetch_assoc();
        $stmtVerificar->close();

        if ($rowVerificar['existe'] > 0) {
            // El colaborador ya fue liquidado
            $mensajeError = "Error: Este colaborador ya ha sido liquidado anteriormente.";
        } else {
            // Verificar si el aguinaldo ya fue generado para el año actual
            $anioActual = date('Y');
            $sqlAguinaldo = "SELECT COUNT(*) AS existe FROM aguinaldo 
                             WHERE Colaborador_idColaborador = ? AND YEAR(Fechafin) = ?";
            $stmtAguinaldo = $conn->prepare($sqlAguinaldo);
            $stmtAguinaldo->bind_param("ii", $idColaborador, $anioActual);
            $stmtAguinaldo->execute();
            $resultAguinaldo = $stmtAguinaldo->get_result();
            $rowAguinaldo = $resultAguinaldo->fetch_assoc();
            $stmtAguinaldo->close();

            if ($rowAguinaldo['existe'] > 0) {
                // El aguinaldo ya fue generado, no corresponde aguinaldo proporcional
                $aguinaldoProporcional = 0;
                // Recalcular el total de liquidación
                $totalLiquidacion = $cesantia + $vacacionesPago;
            }

            // Insertar la liquidación en la base de datos
            $sql = "INSERT INTO liquidaciones (Cesantia, VacacionesPendientes, Razon, Total_liquidacion, FechadeLiquidacion, FechadeSalida, Moneda, Colaborador_idColaborador) 
                    VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "dddsssi",
                $cesantia,
                $vacacionesPago,
                $razon,
                $totalLiquidacion,
                $fechaSalida,
                $Moneda,
                $idColaborador
            );
            if ($stmt->execute()) {
                $mensajeExito = "Liquidación registrada con éxito.";
            } else {
                $mensajeError = "Error al registrar la liquidación: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    ?>

    <!-- Mostrar resultados si la liquidación fue procesada y no hubo error -->
    <?php if (isset($mensajeExito)): ?>
        <?php
        // Obtener los detalles del empleado
        $empleadoSeleccionado = array_filter($empleados, fn($emp) => $emp['idColaborador'] == $idColaborador);
        $empleadoSeleccionado = reset($empleadoSeleccionado);
        $simboloMoneda = '₡';
        ?>
        <div class="card p-4 shadow">
            <h2 class="mb-4">Resultado de la Liquidación</h2>
            <p><strong>Empleado:</strong> <?= htmlspecialchars($empleadoSeleccionado['Nombre'] . ' ' . $empleadoSeleccionado['Apellido1'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Fecha de Salida:</strong> <?= htmlspecialchars($fechaSalida, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Tipo de Moneda:</strong> <?= htmlspecialchars($tipoMoneda, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Años de Servicio:</strong> <?= $aniosServicio; ?></p>
            <p><strong>Salario Base Mensual:</strong> <?= $simboloMoneda . number_format($salarioBase, 2); ?></p>
            <p><strong>Vacaciones Disponibles:</strong> <?= $diasDisponibles; ?> días</p>
            <p><strong>Cesantía:</strong> <?= $simboloMoneda . number_format($cesantia, 2); ?></p>
            <p><strong>Aguinaldo Proporcional:</strong> <?= $simboloMoneda . number_format($aguinaldoProporcional, 2); ?></p>
            <p><strong>Total de Liquidación:</strong> <?= $simboloMoneda . number_format($totalLiquidacion, 2); ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
// Función para llenar los campos y calcular los valores
function llenarCampos() {
    var empleadoSelect = document.getElementById("empleado");
    var selectedOption = empleadoSelect.options[empleadoSelect.selectedIndex];

    if (selectedOption.value === "") {
        limpiarCampos();
        return;
    }

    var fechaIngreso = new Date(selectedOption.getAttribute("data-fecha-ingreso"));
    var salarioBruto = parseFloat(selectedOption.getAttribute("data-salario-bruto"));
    var diasDisponibles = parseInt(selectedOption.getAttribute("data-vacaciones")) || 0;
    var aguinaldoGenerado = parseInt(selectedOption.getAttribute("data-aguinaldo-generado")) > 0;
    var liquidacionGenerada = parseInt(selectedOption.getAttribute("data-liquidacion-generada")) > 0;

    if (liquidacionGenerada) {
        alert("Este colaborador ya ha sido liquidado anteriormente.");
        limpiarCampos();
        return;
    }

    // Calcular años de servicio
    var hoy = new Date();
    var aniosServicio = hoy.getFullYear() - fechaIngreso.getFullYear();
    var mesDiferencia = hoy.getMonth() - fechaIngreso.getMonth();
    var diaDiferencia = hoy.getDate() - fechaIngreso.getDate();
    if (mesDiferencia < 0 || (mesDiferencia === 0 && diaDiferencia < 0)) {
        aniosServicio--;
    }

    // Calcular cesantía según la legislación de Costa Rica
    var cesantia = 0;
    if (aniosServicio >= 1) {
        if (aniosServicio <= 8) {
            cesantia = salarioBruto * aniosServicio;
        } else {
            cesantia = salarioBruto * 8;
        }
    }

    var aguinaldoProporcional = 0;

    if (!aguinaldoGenerado) {
        // Calcular aguinaldo proporcional
        var fechaInicioAguinaldo = new Date(hoy.getFullYear(), 0, 1); // 1 de enero del año actual
        var mesesTrabajados = (hoy.getMonth() - fechaInicioAguinaldo.getMonth()) + 1;
        if (mesesTrabajados > 0) {
            aguinaldoProporcional = (salarioBruto * mesesTrabajados) / 12;
        }
    }

    // Calcular pago de vacaciones
    var salarioDiario = salarioBruto / 30;
    var vacacionesPago = salarioDiario * diasDisponibles;

    // Calcular total de liquidación
    var totalLiquidacion = cesantia + aguinaldoProporcional + vacacionesPago;

    // Mostrar resultados en los campos correspondientes
    document.getElementById("aniosServicio").value = aniosServicio;
    document.getElementById("salarioBase").value = salarioBruto.toFixed(2);
    document.getElementById("diasDisponibles").value = diasDisponibles;
    document.getElementById("cesantia").value = cesantia.toFixed(2);
    document.getElementById("aguinaldoProporcional").value = aguinaldoProporcional.toFixed(2);
    document.getElementById("vacacionesPago").value = vacacionesPago.toFixed(2);
    document.getElementById("totalLiquidacion").value = totalLiquidacion.toFixed(2);
}

// Función para limpiar los campos
function limpiarCampos() {
    document.getElementById("formLiquidacion").reset();
    document.getElementById("aniosServicio").value = "";
    document.getElementById("salarioBase").value = "";
    document.getElementById("diasDisponibles").value = "";
    document.getElementById("cesantia").value = "";
    document.getElementById("aguinaldoProporcional").value = "";
    document.getElementById("vacacionesPago").value = "";
    document.getElementById("totalLiquidacion").value = "";
}

// Función para mostrar el modal de confirmación
function mostrarConfirmacion() {
    // Validar que todos los campos necesarios estén llenos
    var empleadoSelect = document.getElementById("empleado");
    if (empleadoSelect.value === "") {
        alert("Por favor, seleccione un empleado.");
        return;
    }

    var fechaSalidaInput = document.getElementById("fechaSalida");
    if (fechaSalidaInput.value === "") {
        alert("Por favor, ingrese la fecha de salida.");
        return;
    }

    var razonSelect = document.getElementById("razon");
    if (razonSelect.value === "") {
        alert("Por favor, seleccione la razón de la liquidación.");
        return;
    }

    // Llenar los campos ocultos del formulario en el modal
    document.getElementById("hiddenEmpleado").value = empleadoSelect.value;
    document.getElementById("hiddenFechaSalida").value = fechaSalidaInput.value;
    document.getElementById("hiddenTipoMoneda").value = document.getElementById("tipoMoneda").value;
    document.getElementById("hiddenAniosServicio").value = document.getElementById("aniosServicio").value;
    document.getElementById("hiddenSalarioBase").value = document.getElementById("salarioBase").value;
    document.getElementById("hiddenDiasDisponibles").value = document.getElementById("diasDisponibles").value;
    document.getElementById("hiddenRazon").value = razonSelect.value;
    document.getElementById("hiddenCesantia").value = document.getElementById("cesantia").value;
    document.getElementById("hiddenAguinaldoProporcional").value = document.getElementById("aguinaldoProporcional").value;
    document.getElementById("hiddenVacacionesPago").value = document.getElementById("vacacionesPago").value;
    document.getElementById("hiddenTotalLiquidacion").value = document.getElementById("totalLiquidacion").value;

    // Mostrar resumen en el modal
    var selectedOption = empleadoSelect.options[empleadoSelect.selectedIndex];
    document.getElementById("resumenEmpleado").textContent = selectedOption.textContent;
    document.getElementById("resumenFechaSalida").textContent = fechaSalidaInput.value;
    document.getElementById("resumenTipoMoneda").textContent = document.getElementById("tipoMoneda").value;
    document.getElementById("resumenAniosServicio").textContent = document.getElementById("aniosServicio").value;
    document.getElementById("resumenSalarioBase").textContent = "₡" + parseFloat(document.getElementById("salarioBase").value).toFixed(2);
    document.getElementById("resumenDiasDisponibles").textContent = document.getElementById("diasDisponibles").value;
    document.getElementById("resumenCesantia").textContent = "₡" + parseFloat(document.getElementById("cesantia").value).toFixed(2);
    document.getElementById("resumenAguinaldo").textContent = "₡" + parseFloat(document.getElementById("aguinaldoProporcional").value).toFixed(2);
    document.getElementById("resumenTotalLiquidacion").textContent = "₡" + parseFloat(document.getElementById("totalLiquidacion").value).toFixed(2);

    // Mostrar el modal
    var modal = new bootstrap.Modal(document.getElementById('confirmacionModal'));
    modal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
