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

function isActive($pageNames) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }
    return in_array($currentPage, $pageNames) ? 'active' : '';
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
            --sidebar-glass-blur: 10px;
            --link-color: #aeb1be;
            --link-hover-color: #ffffff;
            --link-active-color: #ffffff;
            /* CAMBIO DE COLOR: De fucsia a azul neón */
            --accent-color: #00bfff;
            --topbar-height: 70px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f3f6;
            padding-left: var(--sidebar-width);
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar-bg);
            backdrop-filter: blur(var(--sidebar-glass-blur));
            -webkit-backdrop-filter: blur(var(--sidebar-glass-blur));
            border-right: 1px solid rgba(255, 255, 255, 0.1);
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
        /* ESTILO PARA EL LOGO: Fondo blanco para que resalte */
        .sidebar-brand .sidebar-logo {
            max-width: 70px;
            margin-bottom: 0.75rem;
            border-radius: 50%;
            background-color: #fff; /* Fondo blanco */
            padding: 5px; /* Pequeño padding interno */
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
            text-shadow: 0 0 10px var(--accent-color);
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

        .sidebar .nav-link[data-bs-toggle="collapse"] .bi-chevron-down {
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .collapse .nav-link, .sidebar .collapsing .nav-link {
            padding-left: 3.5rem;
            font-size: 0.95em;
            background: rgba(0,0,0,0.2);
            border-left: none;
        }
        
        .sidebar-footer {
            padding: 1rem;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-profile .dropdown-toggle::after {
            display: none;
        }
        .user-profile .user-name {
            font-weight: 600;
            color: #fff;
        }
        .user-profile .user-role {
            font-size: 0.8em;
            color: var(--link-color);
        }
        .sidebar-footer .dropdown-menu {
            background-color: #2a2e3f;
            width: calc(var(--sidebar-width) - 2rem);
        }
        .sidebar-footer .dropdown-item {
            color: var(--link-color);
        }
        .sidebar-footer .dropdown-item:hover {
            background-color: var(--accent-color);
            color: #fff;
        }
        
        .main-content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease-in-out;
        }
        
        .topbar-toggler {
            color: #555;
        }

        @media (max-width: 992px) {
            body { padding-left: 0; }
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
            .sidebar.active { transform: translateX(0); }
            .main-content-wrapper { margin-left: 0; }
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
                <a class="nav-link collapsed" href="#procesos-submenu" data-bs-toggle="collapse" role="button">
                    <i class="bi bi-gear-fill"></i>Procesos RRHH <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActive(['nóminas.php', 'permisos.php', 'horasextra.php']) ? 'show' : ''; ?>" id="procesos-submenu">
                    <a class="nav-link" href="nóminas.php">Generar Planilla</a>
                    <a class="nav-link" href="permisos.php">Aprobar Permisos</a>
                    <a class="nav-link" href="horasextra.php">Aprobar Horas Extra</a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isActive('configuración.php'); ?>" href="configuración.php"><i class="bi bi-sliders"></i>Mantenimientos</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle user-profile" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i>
            <div class="ms-3">
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
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<div class="main-content-wrapper">
    <nav class="topbar sticky-top d-lg-none bg-light shadow-sm">
        <button class="btn topbar-toggler" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list fs-2"></i>
        </button>
    </nav>
    <main class="content-area p-md-4 p-3">