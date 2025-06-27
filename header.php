<?php
// Iniciar sesión si no está ya iniciada para asegurar que las variables de sesión estén disponibles.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado. Si no, redirigir a login.
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Obtener variables de sesión para personalizar el header
$username = $_SESSION['username'];
$rol = $_SESSION['rol']; // Rol del usuario actual

// Función para determinar si un enlace de navegación es el activo
function isActive($pageName) {
    if (basename($_SERVER['PHP_SELF']) == $pageName) {
        return 'active';
    }
    return '';
}
?>

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
    /* Estilo personalizado para el nuevo header con más color */
    .navbar-custom {
        background: #2c3e50; /* Un azul naval oscuro y profesional */
        padding: 0.5rem 1.5rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .navbar-brand img {
        height: 50px; /* Logo más grande */
        background-color: #ffffff; /* Fondo blanco para que resalte */
        border-radius: 50%;       /* Hace el fondo circular */
        padding: 4px;             /* Un pequeño espacio entre el logo y el borde blanco */
        box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Sombra sutil para darle profundidad */
        transition: transform 0.3s ease;
    }

    .navbar-brand:hover img {
        transform: scale(1.1);
    }

    .navbar-brand .brand-text {
        font-weight: 500;
        color: #ffffff;
        font-size: 1.25rem;
    }

    /* --- INICIO DE LA CORRECCIÓN DEL ESPACIADO --- */
    .navbar-nav .nav-item {
        margin-left: 0.5rem; /* Añade espacio a la izquierda de cada elemento del menú */
    }

    .navbar-nav .nav-link {
        color: #ecf0f1;
        font-weight: 500;
        padding: 0.75rem 1.25rem; /* Aumenta el padding horizontal para más espacio interno */
        border-radius: 0.5rem;
        transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
    }
    /* --- FIN DE LA CORRECCIÓN DEL ESPACIADO --- */

    .navbar-nav .nav-link:hover {
        background-color: #34495e;
        color: #ffffff;
    }
    
    .navbar-nav .nav-link.active {
        background-color: #e74c3c;
        color: #ffffff;
        font-weight: 700;
    }
    
    .navbar-nav .nav-link i {
        margin-right: 0.5rem;
    }

    .dropdown-menu {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
    }
    
    .dropdown-item {
        font-weight: 500;
        color: #495057;
        padding: 0.5rem 1.5rem;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    
    .dropdown-item i {
        margin-right: 0.75rem;
        width: 20px;
    }

    .user-section .welcome-text {
        font-weight: 500;
        color: #bdc3c7;
        margin-right: 1.5rem; /* Más espacio antes del botón de salir */
    }

    .user-section .btn-logout {
        color: #ffffff;
        border-color: #e74c3c;
        background-color: #e74c3c;
        border-radius: 20px;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .user-section .btn-logout:hover {
        background-color: #c0392b;
        border-color: #c0392b;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="img/edginton.png" alt="Logo Edginton" class="me-2">
            <span class="brand-text">Edginton S.A.</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Opciones de menú según el rol del usuario -->
                <?php if ($rol == 1): // Administrador ?>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('index_administrador.php'); ?>" href="index_administrador.php"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownGestion" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-people-fill"></i> Gestión</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownGestion">
                            <li><a class="dropdown-item" href="personas.php"><i class="bi bi-person-lines-fill"></i>Personas</a></li>
                            <li><a class="dropdown-item" href="usuarios.php"><i class="bi bi-person-lock"></i>Usuarios</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownProcesos" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear-fill"></i> Procesos RRHH</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownProcesos">
                            <li><a class="dropdown-item" href="nóminas.php"><i class="bi bi-cash-stack"></i>Generar Planilla</a></li>
                            <li><a class="dropdown-item" href="permisos.php"><i class="bi bi-calendar-check"></i>Aprobar Permisos</a></li>
                            <li><a class="dropdown-item" href="horasextra.php"><i class="bi bi-clock-history"></i>Aprobar Horas Extra</a></li>
                        </ul>
                    </li>
                     <li class="nav-item"><a class="nav-link <?php echo isActive('configuración.php'); ?>" href="configuración.php"><i class="bi bi-sliders"></i>Mantenimientos</a></li>
                
                <?php elseif ($rol == 2): // Colaborador ?>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('index_colaborador.php'); ?>" href="index_colaborador.php"><i class="bi bi-house-door-fill"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('solicitud_permisos.php'); ?>" href="solicitud_permisos.php"><i class="bi bi-calendar-plus-fill"></i>Solicitar Permisos</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('horas_extra.php'); ?>" href="horas_extra.php"><i class="bi bi-clock-fill"></i>Solicitar Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('salario.php'); ?>" href="salario.php"><i class="bi bi-wallet-fill"></i>Visualizar Salario</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('evaluacion.php'); ?>" href="evaluacion.php"><i class="bi bi-clipboard-check-fill"></i>Mis Evaluaciones</a></li>
                   
                <?php elseif ($rol == 3): // Jefatura ?>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('index_jefatura.php'); ?>" href="index_jefatura.php"><i class="bi bi-person-workspace"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('permisos.php'); ?>" href="permisos.php"><i class="bi bi-calendar-check-fill"></i>Aprobar Permisos</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('horasextra.php'); ?>" href="horasextra.php"><i class="bi bi-alarm-fill"></i>Aprobar Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('rendimiento_equipo.php'); ?>" href="rendimiento_equipo.php"><i class="bi bi-graph-up-arrow"></i>Evaluar Equipo</a></li>
    
                <?php elseif ($rol == 4): // Recursos Humanos ?>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('index_rrhh.php'); ?>" href="index_rrhh.php"><i class="bi bi-building"></i>Inicio RRHH</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownFinanzas" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calculator-fill"></i> Cálculos Financieros</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownFinanzas">
                            <li><a class="dropdown-item" href="nóminas.php"><i class="bi bi-cash-stack"></i>Generar Planilla</a></li>
                            <li><a class="dropdown-item" href="aguinaldo.php"><i class="bi bi-gift-fill"></i>Calcular Aguinaldo</a></li>
                            <li><a class="dropdown-item" href="liquidación.php"><i class="bi bi-box-arrow-right"></i>Calcular Liquidación</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive('rendimiento_equipo.php'); ?>" href="rendimiento_equipo.php"><i class="bi bi-clipboard-data-fill"></i>Observar Rendimiento</a></li>
                <?php endif; ?>

            </ul>
            
            <div class="d-flex align-items-center user-section">
                <span class="welcome-text d-none d-lg-inline">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                </a>
            </div>

        </div>
    </div>
</nav>
