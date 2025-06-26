<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos
$username = $_SESSION['username'];
$mensaje = ""; // Variable para mostrar mensajes de éxito o error

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $colaborador_id = $_POST['colaborador'];
    $calificacion = $_POST['calificacion'];
    $comentarios = $_POST['comentarios'];
    $persona_id = $_SESSION['persona_id']; // ID de la persona que realiza la evaluación

    // Validación básica
    if ($colaborador_id && $calificacion && $comentarios) {
        // Verificar si ya existe una evaluación en la última semana
        $sql_verificar = "SELECT * FROM evaluaciones 
                          WHERE Colaborador_idColaborador = ? 
                          AND Persona_idPersona = ? 
                          AND Fecharealizacion >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        $stmt_verificar = $conn->prepare($sql_verificar);
        $stmt_verificar->bind_param("ii", $colaborador_id, $persona_id);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();

        if ($result_verificar->num_rows > 0) {
            // Ya existe una evaluación en la última semana
            $mensaje = "<div class='alert alert-warning'>Ya has realizado una evaluación para este colaborador en la última semana.</div>";
        } else {
            // Insertar la evaluación en la base de datos
            $sql = "INSERT INTO evaluaciones (PuntajeEvaluaciones, Fecharealizacion, Colaborador_idColaborador, Fecha, Calificacion, Comentarios, Persona_idPersona) 
                    VALUES (?, NOW(), ?, NOW(), ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siisi", $comentarios, $colaborador_id, $calificacion, $comentarios, $persona_id);

            if ($stmt->execute()) {
                $mensaje = "<div class='alert alert-success'>Evaluación guardada exitosamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al guardar la evaluación: " . $stmt->error . "</div>";
            }

            $stmt->close();
        }
        
        $stmt_verificar->close();
    } else {
        $mensaje = "<div class='alert alert-warning'>Por favor, complete todos los campos.</div>";
    }
}

// Obtener colaboradores activos de la base de datos para el selector
$query = "SELECT idColaborador, Nombre FROM colaborador 
          JOIN persona ON colaborador.Persona_idPersona = persona.idPersona 
          WHERE activo = 1";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación de Rendimiento - Nueva Evaluación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; }
        .navbar-custom { background-color: #2c3e50; padding: 15px 20px; }
        .navbar-brand { display: flex; align-items: center; color: #ffffff; font-weight: bold; }
        .navbar-brand img { height: 45px; margin-right: 10px; }
        .container { padding-top: 30px; max-width: 600px; margin: 0 auto; }
        h1 { font-size: 2.5rem; color: #333; text-align: center; margin-bottom: 30px; }
        .rating-stars { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .stars { color: #ffc107; font-size: 1.5rem; }
        .stars .fa-star { margin: 0 5px; cursor: pointer; }
        .rating-value { display: inline-block; font-weight: bold; color: #007bff; margin-left: 10px; }
        .alert { margin-top: 20px; }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

<div class="container">
    <h1>Realizar Nueva Evaluación</h1>

    <!-- Mensaje de éxito o error -->
    <?php echo $mensaje; ?>

    <form action="" method="POST">
        <!-- Selección de colaborador -->
        <div class="mb-3">
            <label for="colaborador" class="form-label">Seleccionar Colaborador</label>
            <select class="form-select" id="colaborador" name="colaborador" required>
                <option value="" disabled selected>Seleccione un colaborador</option>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row['idColaborador'] . "'>" . htmlspecialchars($row['Nombre']) . "</option>";
                    }
                } else {
                    echo "<option value=''>No hay colaboradores disponibles</option>";
                }
                ?>
            </select>
        </div>

        <!-- Calificación con estrellas -->
        <div class="mb-3">
            <label class="form-label">Calificación (1 a 5 Estrellas)</label>
            <div class="rating-stars" id="rating-stars">
                <span class="stars" id="stars">
                    <i class="fa fa-star" data-value="1"></i>
                    <i class="fa fa-star" data-value="2"></i>
                    <i class="fa fa-star" data-value="3"></i>
                    <i class="fa fa-star" data-value="4"></i>
                    <i class="fa fa-star" data-value="5"></i>
                </span>
                <span class="rating-value" id="rating-value">0</span>
            </div>
            <input type="hidden" name="calificacion" id="calificacion" value="0">
        </div>

        <!-- Comentarios -->
        <div class="mb-3">
            <label for="comentarios" class="form-label">Comentarios</label>
            <textarea class="form-control" id="comentarios" name="comentarios" rows="4" placeholder="Escriba sus observaciones sobre el rendimiento del colaborador" required></textarea>
        </div>

        <!-- Botón de enviar -->
        <button type="submit" class="btn btn-success w-100">Enviar Evaluación</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const stars = document.querySelectorAll('#stars i');
    const ratingValue = document.getElementById('rating-value');
    const calificacionInput = document.getElementById('calificacion');

    stars.forEach(star => {
        star.addEventListener('click', () => {
            const rating = star.getAttribute('data-value');
            ratingValue.textContent = rating;
            calificacionInput.value = rating;

            stars.forEach(s => {
                if (s.getAttribute('data-value') <= rating) {
                    s.classList.add('fas');
                    s.classList.remove('far');
                } else {
                    s.classList.add('far');
                    s.classList.remove('fas');
                }
            });
        });
    });
</script>
</body>

</html>
