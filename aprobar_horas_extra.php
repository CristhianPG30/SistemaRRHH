<?php
include 'conexion.php';

$id = $_POST['id'];
$accion = $_POST['accion'];
$aprobado_por = $_SESSION['usuario_id'];

$estado = ($accion == 'Aprobar') ? 'Aprobado' : 'Rechazado';

$sql = "UPDATE horas_extra SET estado = ?, aprobado_por = ?, fecha_resolucion = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $estado, $aprobado_por, $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Solicitud $accion con éxito.";
} else {
    echo "Error al procesar la solicitud.";
}
?>
