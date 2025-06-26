<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) { // Verificar que el usuario tiene rol de colaborador
    header('Location: login.php');
    exit;
}
include 'db.php'; // Conexión a la base de datos
$username = $_SESSION['username'];
$colaborador_id = $_SESSION['colaborador_id'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Evaluación - Edginton S.A.</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Fuente personalizada -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Roboto', sans-serif; 
            background-color: #f8f9fa; 
        }
        h1, h2 { 
            color: #343a40; 
        }
        .navbar-custom { 
            background-color: #2c3e50; 
            padding: 15px 20px; 
        }
        .navbar-brand { 
            display: flex; 
            align-items: center; 
            color: #ffffff; 
            font-weight: bold; 
        }
        .navbar-brand img { 
            height: 45px; 
            margin-right: 10px; 
        }
        .container { 
            padding-top: 30px; 
            max-width: 900px; 
        }
        .average-rating { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 40px; 
        }
        .average-rating .stars { 
            color: #ffc107; 
            font-size: 2rem; 
        }
        .average-rating .rating-number { 
            font-size: 3rem; 
            font-weight: bold; 
            color: #007bff; 
            margin-top: 10px; 
        }
        .progress { 
            height: 20px; 
            background-color: #e9ecef; 
            border-radius: 10px; 
            overflow: hidden; 
            margin-top: 20px; 
        }
        .progress-bar { 
            background-color: #ffc107; 
        }
        .table thead { 
            background-color: #343a40; 
            color: #fff; 
        }
        .table tbody tr:hover { 
            background-color: #f1f1f1; 
        }
        .comment-box { 
            background-color: #fff; 
            border: 1px solid #dee2e6; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 20px; 
        }
        .comment-date { 
            font-size: 0.9rem; 
            color: #6c757d; 
        }
        .comment-text { 
            font-size: 1rem; 
            color: #343a40; 
            margin-top: 10px; 
        }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

<!-- Main Content -->
<div class="container">
    <h1 class="text-center mb-5">Mi Evaluación General</h1>

    <?php
    // Consulta para obtener el promedio de calificación y el último comentario del colaborador
    $sql_promedio = "SELECT ROUND(AVG(Calificacion), 1) AS promedio_calificacion 
                     FROM evaluaciones 
                     WHERE Colaborador_idColaborador = $colaborador_id";
    $result_promedio = $conn->query($sql_promedio);
    $promedio = $result_promedio->fetch_assoc()['promedio_calificacion'] ?? 0;

    // Mostrar el promedio de calificación en estrellas y número
    echo "<div class='average-rating'>";
    echo "<div class='rating-number'>$promedio</div>";
    echo "<div class='stars'>";
    for ($i = 1; $i <= 5; $i++) {
        echo $i <= round($promedio) ? "<i class='fas fa-star'></i>" : "<i class='far fa-star'></i>";
    }
    echo "</div>";
    // Barra de progreso para mostrar el porcentaje
    $porcentaje = ($promedio / 5) * 100;
    echo "<div class='progress w-50'>";
    echo "<div class='progress-bar' role='progressbar' style='width: $porcentaje%;' aria-valuenow='$promedio' aria-valuemin='0' aria-valuemax='5'></div>";
    echo "</div>";
    echo "</div>";
    ?>

    <h2 class="text-center mt-5 mb-4">Historial de Evaluaciones</h2>

    <!-- Tabla de historial de evaluaciones -->
    <div class="table-responsive mb-5">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Calificación</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Consulta para obtener el historial de evaluaciones del colaborador
                $sql_historial = "SELECT Fecharealizacion, Calificacion, Comentarios 
                                  FROM evaluaciones 
                                  WHERE Colaborador_idColaborador = $colaborador_id 
                                  ORDER BY Fecharealizacion DESC";
                $result_historial = $conn->query($sql_historial);

                if ($result_historial->num_rows > 0) {
                    // Mostrar cada evaluación en una fila
                    while ($row = $result_historial->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . date('d/m/Y', strtotime(htmlspecialchars($row['Fecharealizacion']))) . "</td>";
                        echo "<td>";
                        $calificacion = $row['Calificacion'];
                        echo "<span class='badge bg-warning text-dark'>$calificacion / 5</span>";
                        echo "</td>";
                        echo "<td>";
                        if (!empty($row['Comentarios'])) {
                            echo "<div class='comment-box'>";
                            echo "<div class='comment-date'>" . date('d/m/Y', strtotime(htmlspecialchars($row['Fecharealizacion']))) . "</div>";
                            echo "<div class='comment-text'>" . nl2br(htmlspecialchars($row['Comentarios'])) . "</div>";
                            echo "</div>";
                        } else {
                            echo "<em>Sin comentarios</em>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center'>No tienes evaluaciones registradas.</td></tr>";
                }

                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS y Font Awesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
