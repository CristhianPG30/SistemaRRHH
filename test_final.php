<?php
// test_final.php - Prueba de diagnóstico final

// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // Usamos <pre> para que el texto se vea ordenado y claro

echo "--- INICIO DE PRUEBA DE DIAGNÓSTICO ---\n\n";

// 1. Conexión a la base de datos
echo "PASO 1: Conectando a la base de datos...\n";
include 'db.php';

if (!$conn || $conn->connect_error) {
    die("FALLO EN PASO 1: La conexión a la base de datos falló.\nError: " . ($conn->connect_error ?? 'No se pudo crear el objeto de conexión. Revisa db.php.'));
}
echo "PASO 1: ÉXITO. Conexión a la base de datos establecida.\n\n";


// 2. Definición de credenciales
$username_a_probar = 'admin';
$password_a_probar = 'Admin2025*';
echo "PASO 2: Credenciales a probar:\n";
echo "   - Usuario: '$username_a_probar'\n";
echo "   - Contraseña: '$password_a_probar'\n\n";


// 3. Obtener el hash de la base de datos
echo "PASO 3: Obteniendo el hash de la contraseña para el usuario '{$username_a_probar}'...\n";
$sql = "SELECT password FROM usuario WHERE username = ?";
$stmt = $conn->prepare($sql);
if(!$stmt){
    die("FALLO EN PASO 3: La consulta SQL no se pudo preparar: " . $conn->error);
}

$stmt->bind_param("s", $username_a_probar);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $hash_de_la_bd = $user['password'];
    echo "PASO 3: ÉXITO. Hash obtenido de la BD.\n";
    echo "   - Hash: " . htmlspecialchars($hash_de_la_bd) . "\n\n";
} else {
    die("FALLO EN PASO 3: No se encontró al usuario '{$username_a_probar}' en la base de datos.\n");
}
$stmt->close();


// 4. Verificación final con password_verify()
echo "PASO 4: Ejecutando password_verify()...\n";

$resultado_verificacion = password_verify($password_a_probar, $hash_de_la_bd);

echo "El resultado de la verificación es: ";
var_dump($resultado_verificacion); // var_dump nos da un resultado más detallado (bool(true) o bool(false))
echo "\n";


// 5. Veredicto final
echo "--- VEREDICTO FINAL ---\n";
if ($resultado_verificacion === true) {
    echo "El problema NO está en la base de datos ni en la lógica de encriptación. Debe ser un problema en cómo el formulario envía los datos a login.php.\n";
} else {
    echo "El problema está aquí. A pesar de que todo parece correcto, la función password_verify() está fallando. Esto podría indicar un problema sutil con la configuración de PHP o la codificación de caracteres en la base de datos.\n";
}

echo "\n--- FIN DE PRUEBA ---";
echo "</pre>";

$conn->close();
?>