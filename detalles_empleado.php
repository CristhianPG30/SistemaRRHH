<?php
session_start();
include 'db.php';

// Verificar si se pasó el ID del empleado
if (isset($_GET['id'])) {
    $idPersona = intval($_GET['id']);

    // Obtener las horas extra y deducciones del empleado
    $query_horas_extra = "SELECT he.cantidad_horas, he.fecha FROM horas_extra he WHERE he.Colaborador_idColaborador = $idPersona";
    $result_horas_extra = $conn->query($query_horas_extra);

    $query_deducciones = "SELECT d.monto, d.descripcion FROM deducciones d WHERE d.Persona_idPersona = $idPersona";
    $result_deducciones = $conn->query($query_deducciones);
} else {
    echo "<p>ID de empleado no proporcionado.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Empleado - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            font-family: 'Roboto', sans-serif;
        }

        .wrapper {
            display: flex;
            flex: 1;
        }

        #sidebarMenu {
            min-width: 250px;
            max-width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
            background-color: #004085;
            padding-top: 20px;
            transition: all 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        #sidebarMenu a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: block;
            padding: 10px 15px;
        }

        #sidebarMenu a:hover {
            background-color: #0d6efd;
            color: white;
            transition: background-color 0.3s ease;
        }

        .submenu {
            padding-left: 20px;
            margin-top: 5px;
            display: none;
            transition: height 0.3s ease;
        }

        .submenu.show {
            display: block;
        }

        .nav-item a span.arrow {
            float: right;
            font-size: 12px;
        }

        .nav-item a .rotate {
            transform: rotate(90deg);
        }

        .divider {
            height: 1px;
            margin: 10px 0;
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Main content */
        main {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            transition: margin-left 0.3s ease-in-out;
        }

        .navbar {
            margin-left: 250px;
            width: calc(100% - 250px);
            background: linear-gradient(90deg, #0d6efd 0%, #4e73df 100%);
        }

        .navbar-toggler-sidebar {
            color: white;
            border: none;
            background-color: transparent;
            font-size: 1.5rem;
        }

        .nav-link {
            font-weight: bold;
            color: white;
        }

        .btn {
            border-radius: 20px;
            padding: 10px 20px;
            font-size: 1rem;
        }

        h1 {
            font-size: 2.5rem;
            color: #004085;
            text-align: center;
            margin-bottom: 20px;
        }

        .table {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #004085;
            color: white;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <!-- Botón para ocultar/mostrar sidebar -->
            <button class="navbar-toggler-sidebar" onclick="toggleSidebar()">☰</button>

            <!-- Logo -->
            <a class="navbar-brand logo" href="dashboard.php">
                <img src="img/edginton.png" alt="Logo Edginton" style="max-height: 50px;">
                Edginton S.A.
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Bienvenido, <?php echo $_SESSION['username']; ?></span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebarMenu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Recursos Humanos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="toggleSubmenu(event)">
                        Mantenimiento <span class="arrow">&#9654;</span>
                    </a>
                    <ul class="nav flex-column submenu">
                        <li class="nav-item">
                            <a class="nav-link" href="personas.php">Personal</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="usuarios.php">Usuarios</a>
                        </li>
                    </ul>
                </li>
                <div class="divider"></div>
                <li class="nav-item">
                    <a class="nav-link" href="calcularplanilla.php">Calcular Planilla</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="horasextra.php">Gestión de horas extras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalogo_horas_extras.php">Gestión de vacaciones</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalogo_liquidaciones.php">Gestión de Permisos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalogo_permisos.php">Gestión de liquidaciones</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalogo_incapacidades.php">Gestión de incapacidades</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pagos_colaborador.php">Evaluación de rendimiento de empleados</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="solicitudes_rendimiento.php">Aguinaldos</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main>
            <h1 class="h2">Detalles de Horas Extra y Deducciones</h1>

            <div class="container">
                <h3>Horas Extra</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Cantidad de Horas</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_horas_extra->num_rows > 0) : ?>
                            <?php while ($hora_extra = $result_horas_extra->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo $hora_extra['cantidad_horas']; ?></td>
                                    <td><?php echo $hora_extra['fecha']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2">No se encontraron horas extra.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h3>Deducciones</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Monto</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_deducciones->num_rows > 0) : ?>
                            <?php while ($deduccion = $result_deducciones->fetch_assoc()) : ?>
                                <tr>
                                    <td>₡<?php echo number_format($deduccion['monto'], 2); ?></td>
                                    <td><?php echo $deduccion['descripcion']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2">No se encontraron deducciones.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <a href="calcularplanilla.php" class="btn btn-primary">Volver</a>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarMenu');
            const main = document.querySelector('main');
            sidebar.classList.toggle('hide');
        }

        function toggleSubmenu(event) {
            event.preventDefault();
            const submenu = event.target.nextElementSibling;
            submenu.classList.toggle('show');
            const arrow = event.target.querySelector('.arrow');
            arrow.classList.toggle('rotate');
        }
    </script>
</body>
</html>
