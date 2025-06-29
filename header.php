<?php
// header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$rol = $_SESSION['rol']; 

function isActive($pageNames) {
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (in_array($currentPage, $pageNames)) {
        return 'active';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema RRHH - Edginton S.A.</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background: #2c3e50; padding: 0.5rem 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar-brand img { height: 50px; background-color: #ffffff; border-radius: 50%; padding: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: transform 0.3s ease; }
        .navbar-brand:hover img { transform: scale(1.1); }
        .navbar-brand .brand-text { font-weight: 500; color: #ffffff; font-size: 1.25rem; }
        .navbar-nav .nav-item { margin-left: 0.5rem; }
        .navbar-nav .nav-link { color: #ecf0f1; font-weight: 500; padding: 0.75rem 1.25rem; border-radius: 0.5rem; transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out; }
        .navbar-nav .nav-link:hover { background-color: #34495e; color: #ffffff; }
        .navbar-nav .nav-link.active { background-color: #e74c3c; color: #ffffff; font-weight: 700; }
        .navbar-nav .nav-link i { margin-right: 0.5rem; }
        .dropdown-item.active { background-color: #e9ecef; color: #0d6efd; font-weight: 600; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="index_administrador.php">
            <img src="img/edginton.png" alt="Logo Edginton" class="me-2">
            <span class="brand-text">Edginton S.A.</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                 <?php if ($rol == 1): // Administrador ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('index_administrador.php'); ?>" href="index_administrador.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActive(['personas.php', 'form_persona.php', 'usuarios.php', 'form_usuario.php']); ?>" href="#" id="navbarDropdownGestion" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people-fill"></i> Gestión
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownGestion">
                            <li><a class="dropdown-item <?php echo isActive(['personas.php', 'form_persona.php']); ?>" href="personas.php"><i class="bi bi-person-lines-fill"></i>Personas</a></li>
                            <li><a class="dropdown-item <?php echo isActive(['usuarios.php', 'form_usuario.php']); ?>" href="usuarios.php"><i class="bi bi-person-lock"></i>Usuarios</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActive(['nóminas.php', 'permisos.php', 'horasextra.php']); ?>" href="#" id="navbarDropdownProcesos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear-fill"></i> Procesos RRHH
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownProcesos">
                            <li><a class="dropdown-item <?php echo isActive('nóminas.php'); ?>" href="nóminas.php"><i class="bi bi-cash-stack"></i>Generar Planilla</a></li>
                            <li><a class="dropdown-item <?php echo isActive('permisos.php'); ?>" href="permisos.php"><i class="bi bi-calendar-check"></i>Aprobar Permisos</a></li>
                            <li><a class="dropdown-item <?php echo isActive('horasextra.php'); ?>" href="horasextra.php"><i class="bi bi-clock-history"></i>Aprobar Horas Extra</a></li>
                        </ul>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link <?php echo isActive('configuración.php'); ?>" href="configuración.php"><i class="bi bi-sliders"></i>Mantenimientos</a>
                    </li>
                 <?php endif; ?>
                 </ul>
            <div class="d-flex align-items-center">
                <span class="navbar-text text-white me-3">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <a href="logout.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</nav>