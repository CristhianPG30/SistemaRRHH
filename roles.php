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
    <title>Gestión de Roles - Edginton S.A.</title>
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

        .btn-edit, .btn-delete {
            padding: 5px 10px;
            font-size: 0.9rem;
        }

        .btn-edit {
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .btn-edit:hover {
            background-color: #0056b3;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .config-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-control {
            border-radius: 10px;
        }

        .btn-save {
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            padding: 10px 20px;
            width: 100%;
            margin-top: 20px;
        }

        .btn-save:hover {
            background-color: #218838;
        }

    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="img/edginton.png" alt="Logo Edginton">
                Edginton S.A.
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="welcome-text">Bienvenido, <?php echo htmlspecialchars($username); ?></span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-outline-danger btn-sm btn-logout">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1>Gestión de Roles</h1>

        <!-- Tabla de roles -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Ejemplo de datos ficticios para mostrar el diseño -->
                    <tr>
                        <td>1</td>
                        <td>Administrador</td>
                        <td>
                            <button class="btn-edit">Editar</button>
                            <button class="btn-delete">Eliminar</button>
                        </td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Colaborador</td>
                        <td>
                            <button class="btn-edit">Editar</button>
                            <button class="btn-delete">Eliminar</button>
                        </td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Jefatura</td>
                        <td>
                            <button class="btn-edit">Editar</button>
                            <button class="btn-delete">Eliminar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Formulario para agregar nuevo rol -->
        <div class="config-section">
            <h3>Agregar Nuevo Rol</h3>
            <form>
                <div class="mb-3">
                    <label for="nombreRol" class="form-label">Nombre del Rol</label>
                    <input type="text" class="form-control" id="nombreRol" placeholder="Ingrese el nombre del rol">
                </div>
                <button type="submit" class="btn-save">Guardar Rol</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
