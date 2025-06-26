<?php 
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'db.php'; // Conexión a la base de datos
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación de Rendimiento - Edginton S.A.</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
        }

        /* Estilos para el Contenedor Principal */
        .container {
            padding-top: 40px;
            padding-bottom: 40px;
        }

        h1 {
            font-size: 2.5rem;
            color: #343a40;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }

        p.text-center {
            color: #6c757d;
            margin-bottom: 30px;
        }

        /* Estilos para la Tabla */
        .table {
            background-color: #ffffff;
            border-radius: 5px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #343a40;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
            font-weight: 500;
            white-space: nowrap;
        }

        .table tbody td {
            text-align: center;
            vertical-align: middle;
            font-size: 0.95rem;
            padding: 12px;
            white-space: nowrap;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }

        .table-hover tbody tr:hover {
            background-color: #e9ecef;
        }

        /* Estilos para las Estrellas de Calificación */
        .stars {
            color: #ffc107;
            font-size: 1.5rem;
        }

        .stars .fa-star {
            margin: 0 2px;
        }

        /* Botón de Evaluación */
        .btn-evaluate {
            background-color: #28a745;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            padding: 12px 25px;
            font-size: 1rem;
            transition: background-color 0.3s, transform 0.3s;
        }

        .btn-evaluate:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        /* Estilos para los botones de acción */
        .btn-action {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn-action .btn {
            margin: 0 2px;
            padding: 6px 8px;
            font-size: 0.85rem;
            border-radius: 4px;
        }

        /* Responsive tables */
        .table-responsive {
            overflow-x: auto;
        }

        /* Ajustes para dispositivos móviles */
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .btn-evaluate {
                width: 100%;
                padding: 10px;
            }

            .stars {
                font-size: 1.2rem;
            }

            .table thead th,
            .table tbody td {
                font-size: 0.85rem;
                padding: 8px;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <h1>Evaluación de Rendimiento del Equipo</h1>
        <p class="text-center">Revisa el desempeño de los colaboradores a continuación.</p>

        <!-- Tabla de Evaluación de Rendimiento -->
        <div class="card mb-5">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Calificación (Promedio 1-5)</th>
                                <th>Último Comentario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Consultar el promedio de calificación y el último comentario de cada colaborador
                            $sql = "SELECT persona.Nombre AS colaborador, 
                                           ROUND(AVG(evaluaciones.Calificacion), 1) AS promedio_calificacion, 
                                           (SELECT evaluaciones.Comentarios 
                                            FROM evaluaciones 
                                            WHERE evaluaciones.Colaborador_idColaborador = colaborador.idColaborador 
                                            ORDER BY evaluaciones.Fecharealizacion DESC 
                                            LIMIT 1) AS ultimo_comentario
                                    FROM evaluaciones 
                                    JOIN colaborador ON evaluaciones.Colaborador_idColaborador = colaborador.idColaborador
                                    JOIN persona ON colaborador.Persona_idPersona = persona.idPersona
                                    GROUP BY colaborador.idColaborador";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                // Recorrer los resultados y mostrarlos en la tabla
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['colaborador']) . "</td>";
                                    echo "<td class='stars'>";
                                    $promedio = round($row['promedio_calificacion']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $promedio ? "<i class='fas fa-star'></i>" : "<i class='far fa-star'></i>";
                                    }
                                    echo " (" . htmlspecialchars($row['promedio_calificacion']) . ")";
                                    echo "</td>";
                                    echo "<td>" . htmlspecialchars($row['ultimo_comentario']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3'>No hay evaluaciones registradas.</td></tr>";
                            }

                            $conn->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Botón para Realizar una Nueva Evaluación -->
        <div class="text-center">
            <button class="btn-evaluate" id="btnNuevaEvaluacion">
                <i class="fas fa-plus-circle me-2"></i> Realizar Nueva Evaluación
            </button>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome Kit (si es necesario) -->
    <!-- <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> -->
    <script>
        // Redirigir a nueva_evaluacion.php cuando se haga clic en el botón
        document.getElementById('btnNuevaEvaluacion').addEventListener('click', function() {
            window.location.href = 'nueva_evaluación.php';
        });
    </script>
</body>

</html>
