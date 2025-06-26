<?php
include 'db.php';

$idPermisos = $_POST['idPermisos'];
$accion = $_POST['accion'];

// Determinar el estado según la acción
$estado = ($accion == 'Aprobar') ? 'Aprobado' : 'Rechazado';

// Actualizar el estado de la solicitud en la base de datos
$sql = "UPDATE horas_extra SET estado = ? WHERE idPermisos = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $estado, $idPermisos);

if ($stmt->execute()) {
    echo "Solicitud $accion con éxito.";
} else {
    echo "Error al procesar la solicitud.";
}

$stmt->close();
$conn->close();
?>
