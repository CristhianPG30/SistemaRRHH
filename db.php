<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestion_rrhh";

$conn = mysqli_connect($servername, $username, $password, $dbname, 3306);

if (!$conn) {
    die("Conexión fallida: " . mysqli_connect_error());
}
?>
