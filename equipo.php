<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['colaborador_id'])) {
    header('Location: login.php');
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'];

$sql = "SELECT CONCAT(p.Nombre, ' ', p.Apellido1, ' ', p.Apellido2) AS nombre,
               d.nombre AS departamento,
               c.fecha_ingreso,
               c.salario_bruto,
               CONCAT(jp.Nombre, ' ', jp.Apellido1, ' ', jp.Apellido2) AS jefe
        FROM colaborador c
        JOIN persona p ON c.id_persona_fk = p.idPersona
        JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
        LEFT JOIN colaborador jc ON c.id_jefe_fk = jc.idColaborador
        LEFT JOIN persona jp ON jc.id_persona_fk = jp.idPersona
        WHERE c.id_jefe_fk = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $colaborador_id);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Equipo - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .card { border-radius: 1rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.1); }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Mi Equipo</h2>

    <div class="row">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['nombre']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Departamento: <?php echo htmlspecialchars($row['departamento']); ?></h6>
                            <p class="card-text">
                                <strong>Fecha de ingreso:</strong> <?php echo htmlspecialchars($row['fecha_ingreso']); ?><br>
                                <strong>Salario bruto:</strong> ₡<?php echo number_format($row['salario_bruto'], 2); ?><br>
                                <strong>Jefe directo:</strong> <?php echo htmlspecialchars($row['jefe']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center">No tienes colaboradores asignados a tu equipo.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>