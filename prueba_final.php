<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de Conexión Completa</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #005a9c; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        ul { list-style-type: none; padding: 0; }
        li { background: #e9ecef; margin-bottom: 5px; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Prueba de Conexión: VS Code -> XAMPP -> Base de Datos</h1>
        <?php
        // --- 1. Datos de Conexión a la Base de Datos de XAMPP ---
        $servername = "localhost";
        $username = "root";
        $password = ""; 
        $dbname = "gestion_rrhh";

        // --- 2. Crear la conexión ---
        $conn = new mysqli($servername, $username, $password, $dbname);

        // --- 3. Verificar la conexión ---
        if ($conn->connect_error) {
            echo "<p class='error'>Conexión fallida: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='success'>¡Conexión a la base de datos '" . $dbname . "' fue exitosa!</p>";

            // --- 4. Preparar y ejecutar la consulta (¡CÓDIGO CORREGIDO!) ---
            $sql = "SELECT idIdRol, descripcion FROM idrol"; // CORRECCIÓN: El nombre de la tabla es `idrol`
            $result = $conn->query($sql);

            echo "<h2>Roles encontrados en la base de datos:</h2>";

            if ($result && $result->num_rows > 0) {
                // Si encontramos resultados, los mostramos en una lista
                echo "<ul>";
                while($row = $result->fetch_assoc()) {
                    // Imprimimos cada fila que encontramos (¡CÓDIGO CORREGIDO!)
                    echo "<li>Rol ID: " . $row["idIdRol"]. " - Descripción: " . $row["descripcion"]. "</li>"; // CORRECCIÓN: El nombre de la columna es `idIdRol`
                }
                echo "</ul>";
            } else {
                echo "<p>No se encontraron roles en la tabla.</p>";
            }
        }

        // --- 5. Cerrar la conexión ---
        $conn->close();
        ?>
    </div>
</body>
</html>
