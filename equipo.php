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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Equipo - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fc;
        }
        .team-container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem 1rem;
        }
        .team-header {
            color: #32325d;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .team-subheader {
            color: #6c757d;
            margin-bottom: 2.5rem;
        }
        .employee-card {
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .employee-card .card-body {
            padding: 1.5rem;
        }
        .employee-card .card-title {
            font-weight: 600;
            color: #5e72e4;
        }
        .employee-card .card-subtitle {
            font-weight: 500;
            color: #8898aa;
        }
        .employee-card .list-group-item {
            border: none;
            padding: 0.5rem 0;
            font-size: 0.9rem;
            color: #525f7f;
        }
        .employee-card .list-group-item i {
            color: #5e72e4;
            margin-right: 0.75rem;
            width: 20px;
        }
        #searchInput {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="team-container">
    <div class="text-center">
        <h1 class="team-header">Mi Equipo</h1>
        <p class="team-subheader">Visualiza y gestiona los miembros de tu equipo.</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
             <div class="input-group">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Buscar por nombre o departamento...">
            </div>
        </div>
    </div>

    <div class="row" id="team-cards-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-xl-4 col-md-6 mb-4 team-member-card">
                    <div class="card employee-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['nombre']); ?></h5>
                            <h6 class="card-subtitle mb-3"><?php echo htmlspecialchars($row['departamento']); ?></h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="bi bi-calendar-check-fill"></i>
                                    <strong>Ingreso:</strong> <?php echo htmlspecialchars($row['fecha_ingreso']); ?>
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-cash-coin"></i>
                                    <strong>Salario:</strong> ₡<?php echo number_format($row['salario_bruto'], 2); ?>
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-person-check-fill"></i>
                                    <strong>Jefe:</strong> <?php echo htmlspecialchars($row['jefe']); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center text-muted mt-5">No tienes colaboradores asignados a tu equipo.</p>
            </div>
        <?php endif; ?>
         <div class="col-12 text-center mt-4" id="no-results" style="display: none;">
            <p class="text-muted">No se encontraron colaboradores con ese nombre.</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const cardsContainer = document.getElementById('team-cards-container');
    const memberCards = cardsContainer.querySelectorAll('.team-member-card');
    const noResultsMessage = document.getElementById('no-results');

    searchInput.addEventListener('keyup', function () {
        const filter = searchInput.value.toLowerCase();
        let visibleCards = 0;

        memberCards.forEach(function (card) {
            const name = card.querySelector('.card-title').textContent.toLowerCase();
            const department = card.querySelector('.card-subtitle').textContent.toLowerCase();

            if (name.includes(filter) || department.includes(filter)) {
                card.style.display = '';
                visibleCards++;
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCards === 0) {
            noResultsMessage.style.display = 'block';
        } else {
            noResultsMessage.style.display = 'none';
        }
    });
});
</script>

</body>
</html>