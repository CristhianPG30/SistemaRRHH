<?php
session_start();
include 'db.php';

// Validar acceso solo para Jefatura (rol 3)
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 3) {
    header('Location: login.php');
    exit;
}

$jefatura_id = $_SESSION['colaborador_id'] ?? null;

// Consulta: colaboradores bajo este jefe
$sql = "
SELECT 
    c.idColaborador,
    CONCAT(p.Nombre, ' ', p.Apellido1, ' ', p.Apellido2) AS nombre_completo,
    d.nombre AS departamento,
    c.salario_bruto,
    j.idColaborador AS jefe_id,
    CONCAT(jp.Nombre, ' ', jp.Apellido1, ' ', jp.Apellido2) AS nombre_jefe
FROM colaborador c
INNER JOIN persona p ON c.id_persona_fk = p.idPersona
INNER JOIN departamento d ON c.id_departamento_fk = d.idDepartamento
LEFT JOIN colaborador j ON c.id_jefe_fk = j.idColaborador
LEFT JOIN persona jp ON j.id_persona_fk = jp.idPersona
WHERE c.id_jefe_fk = ?
ORDER BY nombre_completo ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jefatura_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Jefatura - Sistema RRHH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fc; font-family: 'Poppins', sans-serif; }
        .container-main { max-width: 1000px; margin-top: 50px; }
        .jefatura-card {
            border-radius: 1.5rem;
            box-shadow: 0 0.5rem 2rem rgba(60,72,88,0.10);
            background: linear-gradient(135deg, #5e72e4 0%, #f4f7fc 100%);
            border: none;
            overflow: hidden;
        }
        .jefatura-card .card-header {
            background: #fff;
            border-bottom: 1px solid #e4e7ed;
            padding: 1.5rem 2rem 1rem 2rem;
        }
        .jefatura-card .card-body {
            padding: 2rem;
        }
        .table thead th {
            background: #5e72e4;
            color: #fff;
            font-weight: 600;
            border: none;
        }
        .search-box {
            max-width: 300px;
            margin-bottom: 1.2rem;
        }
        .rounded-avatar {
            width: 40px; height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #5e72e4;
        }
        .no-result {
            text-align: center;
            padding: 2.5rem 0 2rem 0;
            color: #b0b3c3;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container container-main">
        <div class="card jefatura-card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0" style="font-weight: 700; color:#5e72e4;"><i class="bi bi-person-badge-fill me-2"></i>Panel de Jefatura</h3>
                    <small class="text-muted">Gestión de colaboradores a tu cargo</small>
                </div>
                <div>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
                    </a>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control search-box" id="searchInput" placeholder="Buscar colaborador...">

                <div class="table-responsive">
                    <table class="table align-middle table-hover" id="collabTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre completo</th>
                                <th>Departamento</th>
                                <th>Salario bruto</th>
                                <th>Jefe directo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $i=1; while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= htmlspecialchars($row['nombre_completo']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['departamento']) ?></td>
                                    <td>₡<?= number_format($row['salario_bruto'], 2) ?></td>
                                    <td><?= $row['nombre_jefe'] ? htmlspecialchars($row['nombre_jefe']) : '<span class="badge bg-secondary">Sin jefe</span>' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="no-result">
                                <i class="bi bi-people-slash" style="font-size:2rem"></i><br>
                                No tienes colaboradores a tu cargo.
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Buscador interactivo -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('keyup', function() {
                let filter = this.value.toLowerCase();
                let rows = document.querySelectorAll("#collabTable tbody tr");
                rows.forEach(function(row) {
                    let name = row.children[1].innerText.toLowerCase();
                    row.style.display = name.includes(filter) ? "" : "none";
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

