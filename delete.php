<?php
// Iniciar sesión y verificar si el usuario ha iniciado sesión
session_start();
include 'db.php'; // Conexión a la base de datos

// Verificar si se ha pasado un ID en la URL
if (isset($_GET['id'])) {
    $idUsuario = intval($_GET['id']); // Convertir el ID en un entero para mayor seguridad

    // Eliminar el usuario de la base de datos
    $stmt = $conn->prepare("DELETE FROM usuario WHERE idUsuario = ?");
    $stmt->bind_param("i", $idUsuario);

    if ($stmt->execute()) {
        // Redirigir de vuelta a la página de usuarios con un mensaje de éxito
        header("Location: usuarios.php?success=deleted");
        exit();
    } else {
        // En caso de error, redirigir con un mensaje de error
        header("Location: usuarios.php?error=deletion_failed");
        exit();
    }
} else {
    // Si no se pasa un ID, redirigir de vuelta a la página de usuarios
    header("Location: usuarios.php");
    exit();
}
