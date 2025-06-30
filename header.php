<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirigir a login si no hay sesión activa
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$rol = $_SESSION['rol'] ?? 0; // Asignar 0 si el rol no está definido

// Función para determinar si un enlace del menú está activo
function isActive($pageNames) {
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }
    $currentPage = basename($_SERVER['PHP_SELF']);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --header-bg: linear-gradient(90deg, #4e73df 0%, #36b9cc 100%);
        }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--light-color);
        }

        .navbar-custom {
            background: var(--header-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 1.5rem;
        }
        .navbar-brand img {
            height: 45px;
            border-radius: 50%;
            padding: 2px;
            background-color: white;
            transition: transform 0.3s ease;
        }
        .navbar-brand:hover img {
            transform: scale(1.1);
        }
        .navbar-brand .brand-text {
            font-weight: 600;
            color: #fff;
            font-size: 1.25rem;
        }
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
        }
        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .navbar-nav .nav-link.active {
            background-color: rgba(0, 0, 0, 0.2);
            color: #fff;
            font-weight: 600;
        }
        .navbar-nav .nav-link i {
            margin-right: 0.5rem;
        }
        .dropdown-menu {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .dropdown-item {
            font-weight: 500;
            color: var(--dark-color);
            transition: all 0.2s ease;
        }
        .dropdown-item.active, .dropdown-item:active {
            background-color: var(--primary-color);
            color: #fff;
        }
        .dropdown-item i {
            margin-right: 0.75rem;
            width: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="img/edginton.png" alt="Logo Edginton" class="me-2">
            <span class="brand-text">Edginton S.A.</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php if ($rol == 1): // --- MENÚ ADMINISTRADOR --- ?>
                    <li class="nav-item"><a class="nav-link <?= isActive('index_administrador.php'); ?>" href="index_administrador.php"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= isActive(['personas.php', 'form_persona.php', 'ver_persona.php', 'usuarios.php', 'form_usuario.php']); ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-people-fill"></i>Gestión</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?= isActive(['personas.php', 'form_persona.php', 'ver_persona.php']); ?>" href="personas.php"><i class="bi bi-person-lines-fill"></i>Personas</a></li>
                            <li><a class="dropdown-item <?= isActive(['usuarios.php', 'form_usuario.php']); ?>" href="usuarios.php"><i class="bi bi-person-lock"></i>Usuarios</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= isActive(['nóminas.php', 'permisos.php', 'horasextra.php']); ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear-fill"></i>Procesos RRHH</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?= isActive('nóminas.php'); ?>" href="nóminas.php"><i class="bi bi-cash-stack"></i>Generar Planilla</a></li>
                            <li><a class="dropdown-item <?= isActive('permisos.php'); ?>" href="permisos.php"><i class="bi bi-calendar-check"></i>Aprobar Permisos</a></li>
                            <li><a class="dropdown-item <?= isActive('horasextra.php'); ?>" href="horasextra.php"><i class="bi bi-clock-history"></i>Aprobar Horas Extra</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link <?= isActive('configuración.php'); ?>" href="configuración.php"><i class="bi bi-sliders"></i>Mantenimientos</a></li>
                
                <?php elseif ($rol == 2): // --- MENÚ COLABORADOR --- ?>
                    <li class="nav-item"><a class="nav-link <?= isActive('index_colaborador.php'); ?>" href="index_colaborador.php"><i class="bi bi-house-door-fill"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('solicitud_permisos.php'); ?>" href="solicitud_permisos.php"><i class="bi bi-calendar-plus-fill"></i>Solicitar Permiso</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('horas_extra.php'); ?>" href="horas_extra.php"><i class="bi bi-clock-fill"></i>Mis Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('salario.php'); ?>" href="salario.php"><i class="bi bi-wallet-fill"></i>Mi Salario</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('evaluacion.php'); ?>" href="evaluacion.php"><i class="bi bi-star-fill"></i>Mi Evaluación</a></li>

                <?php elseif ($rol == 3): // --- MENÚ JEFATURA --- ?>
                    <li class="nav-item"><a class="nav-link <?= isActive('index_jefatura.php'); ?>" href="index_jefatura.php"><i class="bi bi-house-door-fill"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('permisos.php'); ?>" href="permisos.php"><i class="bi bi-calendar-check-fill"></i>Aprobar Permisos</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('horasextra.php'); ?>" href="horasextra.php"><i class="bi bi-clock-history"></i>Aprobar Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('rendimiento_equipo.php'); ?>" href="rendimiento_equipo.php"><i class="bi bi-clipboard-data-fill"></i>Evaluar Equipo</a></li>

                <?php elseif ($rol == 4): // --- MENÚ RRHH --- ?>
                    <li class="nav-item"><a class="nav-link <?= isActive('index_rrhh.php'); ?>" href="index_rrhh.php"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('nóminas.php'); ?>" href="nóminas.php"><i class="bi bi-cash-stack"></i>Planillas</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('aguinaldo.php'); ?>" href="aguinaldo.php"><i class="bi bi-gift-fill"></i>Aguinaldos</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('liquidación.php'); ?>" href="liquidación.php"><i class="bi bi-person-dash-fill"></i>Liquidaciones</a></li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span><?= htmlspecialchars($username); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i>Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

</body>
</html>