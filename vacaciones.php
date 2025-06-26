<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vacaciones - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
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

        .navbar-nav .nav-link {
            color: #ecf0f1;
            margin-right: 10px;
        }

        .navbar-nav .nav-link:hover {
            color: #1abc9c;
        }

        .welcome-text {
            font-size: 1.1rem;
            color: #f39c12;
            margin-right: 20px;
        }

        .btn-logout {
            border-color: #e74c3c;
            color: #e74c3c;
            padding: 5px 12px;
        }

        .btn-logout:hover {
            background-color: #e74c3c;
            color: #ffffff;
        }

        .container {
            padding-top: 30px;
        }

        h1 {
            font-size: 2.5rem;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .table-responsive {
            margin-top: 30px;
        }

        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            transition: background-color 0.3s ease;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            transition: background-color 0.3s ease;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    
<?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <h1>Gestión de Vacaciones</h1>
        <p class="text-center">A continuación se muestran las solicitudes de vacaciones de los colaboradores.</p>

        <!-- Tabla de solicitudes de vacaciones -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Colaborador</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Motivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Datos ficticios para demostrar el diseño -->
                    <tr>
                        <td>Juan Pérez</td>
                        <td>2024-11-01</td>
                        <td>2024-11-15</td>
                        <td>Vacaciones anuales</td>
                        <td>
                            <button class="btn-approve">Aprobar</button>
                            <button class="btn-reject">Rechazar</button>
                        </td>
                    </tr>
                    <tr>
                        <td>María López</td>
                        <td>2024-12-05</td>
                        <td>2024-12-10</td>
                        <td>Viaje personal</td>
                        <td>
                            <button class="btn-approve">Aprobar</button>
                            <button class="btn-reject">Rechazar</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Carlos Martínez</td>
                        <td>2024-10-15</td>
                        <td>2024-10-20</td>
                        <td>Descanso médico</td>
                        <td>
                            <button class="btn-approve">Aprobar</button>
                            <button class="btn-reject">Rechazar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
