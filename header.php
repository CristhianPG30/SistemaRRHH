<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$rol = $_SESSION['rol'] ?? 0;

// ✅ CORRECCIÓN: Se envuelve la función en un "if" para evitar que se declare dos veces.
if (!function_exists('isActive')) {
    function isActive($pageNames) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!is_array($pageNames)) {
            $pageNames = [$pageNames];
        }
        return in_array($currentPage, $pageNames) ? 'active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema RRHH - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #191c24;
            --link-color: #aeb1be;
            --link-hover-color: #ffffff;
            --accent-color: #00bfff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fc;
            padding-left: var(--sidebar-width);
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1100;
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            text-align: center;
            color: #fff;
        }
        
        .sidebar-brand .sidebar-logo {
            max-width: 70px;
            margin-bottom: 0.75rem;
            border-radius: 50%;
            background-color: #fff;
            padding: 5px;
            transition: transform 0.3s ease;
        }
        .sidebar-brand:hover .sidebar-logo {
            transform: scale(1.1);
        }
        .sidebar-brand h5 {
            font-weight: 700;
            letter-spacing: 1px;
        }

        .sidebar-nav {
            padding: 1rem;
            flex-grow: 1;
        }
        .sidebar .nav-link {
            color: var(--link-color);
            font-weight: 500;
            padding: 0.9rem 1.2rem;
            margin-bottom: 0.5rem;
            border-radius: 0.6rem;
            display: flex;
            align-items: center;
            transition: all 0.2s ease-in-out;
            border-left: 4px solid transparent;
        }
        .sidebar .nav-link i {
            font-size: 1.3rem;
            margin-right: 1rem;
            width: 25px;
            transition: all 0.2s ease-in-out;
        }
        .sidebar .nav-link:hover {
            color: var(--link-hover-color);
        }
        .sidebar .nav-link:hover i {
            color: var(--accent-color);
        }
        .sidebar .nav-link.active {
            color: var(--link-hover-color);
            font-weight: 600;
            background: linear-gradient(90deg, rgba(0, 191, 255, 0.15), rgba(0, 191, 255, 0.01));
            border-left-color: var(--accent-color);
        }
        .sidebar .nav-link.active i {
            color: var(--accent-color);
        }
        .sidebar .collapse .nav-link {
            padding-left: 3.5rem;
        }
        
        .sidebar-footer {
            padding: 1rem;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-profile .dropdown-toggle::after { display: none; }
        .user-profile .user-name { font-weight: 600; color: #fff; }
        .user-profile .user-role { font-size: 0.8em; color: var(--link-color); }
        
        .main-content-wrapper {
            padding-left: var(--sidebar-width);
            transition: margin-left 0.3s ease-in-out;
        }
        
        @media (max-width: 992px) {
            body { padding-left: 0; }
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
            .sidebar.active { transform: translateX(0); }
            .main-content-wrapper { padding-left: 0; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a class="sidebar-brand text-decoration-none" href="#">
        <img src="img/edginton.png" alt="Logo" class="sidebar-logo">
        <h5>Edginton S.A.</h5>
    </a>
    
    <ul class="nav flex-column sidebar-nav">
        <?php if ($rol == 1): // --- MENÚ ADMINISTRADOR --- ?>
            <li class="nav-item">
                <a class="nav-link <?= isActive(['index_administrador.php']); ?>" href="index_administrador.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="#gestion-submenu" data-bs-toggle="collapse" role="button">
                    <i class="bi bi-people-fill"></i>Gestión <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActive(['personas.php', 'form_persona.php', 'usuarios.php', 'form_usuario.php']) ? 'show' : ''; ?>" id="gestion-submenu">
                    <a class="nav-link <?= isActive(['personas.php', 'form_persona.php']); ?>" href="personas.php">Personal</a>
                    <a class="nav-link <?= isActive(['usuarios.php', 'form_usuario.php']); ?>" href="usuarios.php">Usuarios</a>
                </div>
            </li>
             <li class="nav-item">
                <a class="nav-link" href="nóminas.php"><i class="bi bi-cash-stack"></i>Generar Planilla</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="configuración.php"><i class="bi bi-sliders"></i>Mantenimientos</a>
            </li>
        <?php elseif ($rol == 2): // --- MENÚ COLABORADOR --- ?>
            <li class="nav-item">
                <a class="nav-link <?= isActive(['index_colaborador.php']); ?>" href="index_colaborador.php"><i class="bi bi-house-door-fill"></i>Inicio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isActive(['solicitud_permisos.php']); ?>" href="solicitud_permisos.php"><i class="bi bi-calendar-check-fill"></i>Permisos y Vacaciones</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isActive(['horas_extra.php']); ?>" href="horas_extra.php"><i class="bi bi-clock-history"></i>Horas Extra</a>
            </li>
             <li class="nav-item">
                <a class="nav-link <?= isActive(['evaluacion.php']); ?>" href="evaluacion.php"><i class="bi bi-star-fill"></i>Mis Evaluaciones</a>
            </li>
             <li class="nav-item">
                <a class="nav-link <?= isActive(['salario.php']); ?>" href="salario.php"><i class="bi bi-cash-coin"></i>Mi Salario</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle user-profile" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="d-flex align-items-center">
                 <i class="bi bi-person-circle fs-2 me-2"></i>
                <div>
                    <strong class="user-name"><?= htmlspecialchars($username); ?></strong>
                    <small class="d-block user-role">
                    <?php
                        switch($rol) {
                            case 1: echo 'Administrador'; break;
                            case 2: echo 'Colaborador'; break;
                            case 3: echo 'Jefatura'; break;
                            case 4: echo 'RRHH'; break;
                        }
                    ?>
                    </small>
                </div>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<div class="main-content-wrapper">