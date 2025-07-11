<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];
$rol = $_SESSION['rol'] ?? 0; // 1=Admin, 2=Colaborador, 3=Jefatura, 4=Recursos Humanos

// Función para determinar si un enlace está activo
function isActive($pageNames) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }
    return in_array($currentPage, $pageNames);
}

// Función para obtener el nombre del rol
function rolName($rol) {
    return [1 => 'Administrador', 2 => 'Colaborador', 3 => 'Jefatura', 4 => 'Recursos Humanos'][$rol] ?? 'Usuario';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edginton S.A. - Sistema RRHH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f4f7fc;
        }
        #mainSidebar {
            width: 280px; 
            min-height: 100vh; 
            background: #ffffff;
            box-shadow: 0 0 2rem 0 rgba(0,0,0,0.05); 
            position: fixed;
            top: 0; 
            left: 0; 
            z-index: 1030; 
            display: flex; 
            flex-direction: column;
            transition: all .3s;
        }
        .sidebar-header {
            padding: 1.5rem; 
            text-align: center; 
            border-bottom: 1px solid #e9ecef;
        }
        .sidebar-logo { 
            height: 60px; 
            margin-bottom: 0.5rem; 
        }
        .sidebar-company-name { 
            font-weight: 600; 
            font-size: 1.1rem; 
            color: #32325d; 
        }
        .sidebar-role { 
            font-size: 0.8rem; 
            color: #8898aa; 
            font-weight: 500;
        }
        .sidebar-nav { 
            flex-grow: 1; 
            padding: 1rem; 
            overflow-y: auto; /* Permite scroll si hay muchos enlaces */
        }
        .nav-item .nav-link {
            color: #525f7f; 
            display: flex; 
            align-items: center;
            padding: 0.75rem 1rem; 
            margin-bottom: 0.25rem; 
            border-radius: 0.5rem;
            font-weight: 500; 
            transition: all 0.2s ease;
        }
        .nav-item .nav-link:hover { 
            background-color: #f6f9fc; 
            color: #5e72e4; 
        }
        .nav-item .nav-link.active { 
            background-color: #5e72e4; 
            color: #fff; 
            box-shadow: 0 4px 6px rgba(94,114,228,0.15);
        }
        .nav-item .nav-link i { 
            font-size: 1.1rem; 
            margin-right: 1rem; 
            width: 24px; 
            text-align: center; 
        }
        .nav-heading {
            font-size: 0.75rem; 
            color: #8898aa; 
            text-transform: uppercase;
            font-weight: 700;
            padding: 1.5rem 1rem 0.5rem; 
            letter-spacing: .5px;
        }
        .sidebar-footer { 
            padding: 1rem; 
            border-top: 1px solid #e9ecef; 
        }
        .btn-logout {
            background-color: #f6f9fc;
            color: #525f7f;
            font-weight: 600;
            border: none;
        }
        .btn-logout:hover {
            background-color: #e9ecef;
            color: #32325d;
        }
    </style>
</head>
<body>

<aside id="mainSidebar">
    <div class="sidebar-header">
        <img src="img/edginton.png" alt="Logo Edginton S.A." class="sidebar-logo">
        <h5 class="sidebar-company-name">Edginton S.A.</h5>
        <div class="sidebar-role"><?= htmlspecialchars(rolName($rol)) ?></div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            
            <?php if ($rol == 1): ?>
                <li class="nav-item"><a class="nav-link <?= isActive(['index_administrador.php']) ? 'active' : '' ?>" href="index_administrador.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                
                <li class="nav-heading">Gestión</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['personas.php', 'form_persona.php']) ? 'active' : '' ?>" href="personas.php"><i class="bi bi-people"></i> Personas</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['usuarios.php', 'form_usuario.php']) ? 'active' : '' ?>" href="usuarios.php"><i class="bi bi-person-lock"></i> Usuarios</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['departamentos.php']) ? 'active' : '' ?>" href="departamentos.php"><i class="bi bi-building"></i> Departamentos</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['jerarquias.php']) ? 'active' : '' ?>" href="jerarquias.php"><i class="bi bi-diagram-3"></i> Jerarquías</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['configuración.php']) ? 'active' : '' ?>" href="configuración.php"><i class="bi bi-sliders"></i> Mantenimientos</a></li>
                
                <li class="nav-heading">Procesos RRHH</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['nóminas.php']) ? 'active' : '' ?>" href="nóminas.php"><i class="bi bi-calculator"></i> Generar Planilla</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['horasextra.php']) ? 'active' : '' ?>" href="horasextra.php"><i class="bi bi-clock-history"></i> Aprobar Horas Extra</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['permisos.php']) ? 'active' : '' ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Aprobar Permisos</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['evaluacion.php']) ? 'active' : '' ?>" href="evaluacion.php"><i class="bi bi-star"></i> Evaluaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['liquidación.php']) ? 'active' : '' ?>" href="liquidación.php"><i class="bi bi-box-arrow-left"></i> Liquidaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['generar_aguinaldo.php']) ? 'active' : '' ?>" href="generar_aguinaldo.php"><i class="bi bi-gift"></i> Generar Aguinaldo</a></li>
                
                <li class="nav-heading">Reportes</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['consultas_dinamicas.php']) ? 'active' : '' ?>" href="consultas_dinamicas.php"><i class="bi bi-funnel"></i> Consultas Dinámicas</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['reporte_asistencia.php']) ? 'active' : '' ?>" href="reporte_asistencia.php"><i class="bi bi-calendar3-week"></i> Reporte de Asistencia</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['reporte_global.php']) ? 'active' : '' ?>" href="reporte_global.php"><i class="bi bi-bar-chart-line"></i> Reporte Global</a></li>
            <?php endif; ?>
            
            <?php if ($rol == 2): // Menú Colaborador CORREGIDO ?>
                <li class="nav-item"><a class="nav-link <?= isActive(['index_colaborador.php']) ? 'active' : '' ?>" href="index_colaborador.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                
                <li class="nav-heading">Mi Información</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['mi_perfil.php']) ? 'active' : '' ?>" href="mi_perfil.php"><i class="bi bi-person-circle"></i> Mi Perfil</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['control_asistencia.php']) ? 'active' : '' ?>" href="control_asistencia.php"><i class="bi bi-calendar-check"></i> Mi Asistencia</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['salario.php']) ? 'active' : '' ?>" href="salario.php"><i class="bi bi-cash-coin"></i> Mis Pagos</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['ver_evaluaciones.php']) ? 'active' : '' ?>" href="ver_evaluaciones.php"><i class="bi bi-star"></i> Mis Evaluaciones</a></li>

                <li class="nav-heading">Mis Solicitudes</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['solicitud_vacaciones.php']) ? 'active' : '' ?>" href="solicitud_vacaciones.php"><i class="bi-sun-fill"></i> Solicitar Vacaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['solicitud_permisos.php']) ? 'active' : '' ?>" href="solicitud_permisos.php"><i class="bi bi-calendar-plus"></i> Solicitar Permiso</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['horas_extra.php']) ? 'active' : '' ?>" href="horas_extra.php"><i class="bi bi-clock-history"></i> Justificar Horas Extra</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['solicitud_incapacidad.php']) ? 'active' : '' ?>" href="solicitud_incapacidad.php"><i class="bi bi-bandaid"></i> Registrar Incapacidad</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['mis_solicitudes.php']) ? 'active' : '' ?>" href="mis_solicitudes.php"><i class="bi bi-journal-text"></i> Ver Mis Solicitudes</a></li>
            <?php endif; ?>

            <?php if ($rol == 3): ?>
                <li class="nav-item"><a class="nav-link <?= isActive(['index_jefatura.php']) ? 'active' : '' ?>" href="index_jefatura.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                <li class="nav-heading">Mi Equipo</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['equipo.php']) ? 'active' : '' ?>" href="equipo.php"><i class="bi bi-people"></i> Ver Equipo</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['permisos.php', 'horasextra.php']) ? 'active' : '' ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Aprobaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['evaluacion.php']) ? 'active' : '' ?>" href="evaluacion.php"><i class="bi bi-star"></i> Evaluar Equipo</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['reporte_equipo.php']) ? 'active' : '' ?>" href="reporte_equipo.php"><i class="bi bi-bar-chart-line"></i> Reporte de Equipo</a></li>
            <?php endif; ?>
            
             <?php if ($rol == 4): // Menú RRHH ?>
                <li class="nav-item"><a class="nav-link <?= isActive('index_rrhh.php') ? 'active' : '' ?>" href="index_rrhh.php"><i class="bi bi-house-door-fill"></i> Inicio</a></li>
                <li class="nav-heading">Gestión</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['personas.php', 'form_persona.php']) ? 'active' : '' ?>" href="personas.php"><i class="bi bi-people"></i> Empleados</a></li>
                <li class="nav-heading">Procesos</li>
                <li class="nav-item"><a class="nav-link <?= isActive(['nóminas.php']) ? 'active' : '' ?>" href="nóminas.php"><i class="bi bi-calculator"></i> Planillas</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['permisos.php', 'horasextra.php']) ? 'active' : '' ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Solicitudes</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['liquidación.php']) ? 'active' : '' ?>" href="liquidación.php"><i class="bi bi-box-arrow-left"></i> Liquidaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['generar_aguinaldo.php']) ? 'active' : '' ?>" href="generar_aguinaldo.php"><i class="bi bi-gift"></i> Aguinaldos</a></li>
                <li class="nav-heading">Análisis</li>
                 <li class="nav-item"><a class="nav-link <?= isActive(['evaluacion.php']) ? 'active' : '' ?>" href="evaluacion.php"><i class="bi bi-star"></i> Evaluaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['consultas_dinamicas.php']) ? 'active' : '' ?>" href="consultas_dinamicas.php"><i class="bi bi-funnel"></i> Consultas Dinámicas</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive(['reporte_global.php']) ? 'active' : '' ?>" href="reporte_global.php"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="btn btn-light w-100 text-start">
            <i class="bi bi-box-arrow-right me-2"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>

</body>
</html>