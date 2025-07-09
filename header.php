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

// Función para determinar si un enlace de navegación está activo
function isActive($pageNames) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }
    return in_array($currentPage, $pageNames) ? 'active' : '';
}

// Función para obtener el nombre del rol (CORREGIDA)
function rolName($rol) {
    return [
        1 => 'Administrador',
        2 => 'Colaborador',
        3 => 'Jefatura',
        4 => 'Recursos Humanos'
    ][$rol] ?? 'Usuario';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        #mainSidebar {
            width: 270px;
            min-height: 100vh;
            background: linear-gradient(135deg, #18c0ff 0%, #0e1228 100%);
            color: #fff;
            box-shadow: 4px 0 20px #13c6f120;
            position: fixed;
            top: 0; left: 0; z-index: 1030;
            display: flex; flex-direction: column;
            border-top-right-radius: 2.3rem;
            border-bottom-right-radius: 2.3rem;
            transition: box-shadow .2s;
        }
        .sidebar-logo-box {
            padding: 2.2rem 1rem 1.2rem 1rem;
            text-align: center;
            background: rgba(255,255,255,.08);
            border-top-right-radius: 2.3rem;
        }
        .sidebar-logo-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #fff;
            object-fit: contain;
            border: 3.5px solid #17b9ee;
            margin-bottom: 10px;
            box-shadow: 0 2px 18px #13c6f144;
        }
        .sidebar-company {
            font-size: 1.23rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: .6px;
            margin-bottom: .15rem;
        }
        .sidebar-role {
            font-size: 0.95rem;
            color: #dcf6ff;
            font-weight: 600;
            text-shadow: 0 1px 5px #0e122828;
        }
        .sidebar-menu {
            flex: 1;
            padding: 2rem 1rem 1rem 1rem;
            overflow-y: auto;
        }
        .sidebar-menu .nav-link {
            color: #e8f4fa;
            font-size: 1.07rem;
            font-weight: 600;
            padding: 1rem 1.2rem;
            margin-bottom: .2rem;
            border-radius: 1.1rem;
            transition: background .17s, color .17s;
            display: flex; align-items: center; gap: 0.9rem;
        }
        .sidebar-menu .nav-link.active, .sidebar-menu .nav-link:hover {
            background: linear-gradient(90deg, #14b9e8 60%, #21aaff 100%);
            color: #fff !important;
        }
        .sidebar-menu .nav-link i {
            font-size: 1.35rem;
        }
        .sidebar-footer {
            padding: 1.3rem 1rem;
            background: rgba(255,255,255,.06);
            border-bottom-right-radius: 2.3rem;
            border-top: 1px solid #26c7fc22;
            margin-top: auto;
        }
        .sidebar-footer .btn {
            width: 100%;
            font-weight: 700;
            letter-spacing: .2px;
            border-radius: 1rem;
            background: linear-gradient(90deg, #21aaff 60%, #14b9e8 100%);
            color: #fff;
            box-shadow: 0 4px 24px #21b2ff19;
            border: none;
        }
        .sidebar-footer .btn:hover {
            background: linear-gradient(90deg, #23b6ff 30%, #47d9fd 100%);
            color: #fff;
        }
        @media (max-width: 1100px) {
            #mainSidebar { width: 100%; min-width:0; max-width:350px; border-radius: 0 0 2rem 2rem;}
        }
    </style>
</head>
<body>

<aside id="mainSidebar">
    <div class="sidebar-logo-box">
        <img src="img/edginton.png" alt="Logo" class="sidebar-logo-img mb-2">
        <div class="sidebar-company">Edginton S.A.</div>
        <div class="sidebar-role"><?= htmlspecialchars(rolName($rol)) ?></div>
    </div>
    <nav class="sidebar-menu nav flex-column">
        <?php if ($rol == 1): // ROL ADMINISTRADOR ?>
            <a class="nav-link <?= isActive('index_administrador.php') ?>" href="index_administrador.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a class="nav-link <?= isActive(['usuarios.php', 'form_usuario.php']) ?>" href="usuarios.php"><i class="bi bi-people"></i> Usuarios</a>
            <a class="nav-link <?= isActive(['personas.php', 'form_persona.php']) ?>" href="personas.php"><i class="bi bi-person-lines-fill"></i> Personas</a>
            <a class="nav-link <?= isActive('departamentos.php') ?>" href="departamentos.php"><i class="bi bi-diagram-3"></i> Departamentos</a>
            <a class="nav-link <?= isActive('nóminas.php') ?>" href="nóminas.php"><i class="bi bi-calculator"></i> Planillas</a>
            <a class="nav-link <?= isActive('horasextra.php') ?>" href="horasextra.php"><i class="bi bi-clock-history"></i> Horas Extra</a>
            <a class="nav-link <?= isActive('permisos.php') ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Permisos</a>
            <a class="nav-link <?= isActive('evaluacion.php') ?>" href="evaluacion.php"><i class="bi bi-star-fill"></i> Evaluaciones</a>
            <a class="nav-link <?= isActive('liquidación.php') ?>" href="liquidación.php"><i class="bi bi-bank"></i> Liquidaciones</a>
            <a class="nav-link <?= isActive('aguinaldo.php') ?>" href="aguinaldo.php"><i class="bi bi-gift"></i> Aguinaldos</a>
            <a class="nav-link <?= isActive('reporte_global.php') ?>" href="reporte_global.php"><i class="bi bi-bar-chart"></i> Reportes Globales</a>
        
        <?php elseif ($rol == 2): // ROL COLABORADOR ?>
            <a class="nav-link <?= isActive('index_colaborador.php') ?>" href="index_colaborador.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a class="nav-link <?= isActive('mi_perfil.php') ?>" href="mi_perfil.php"><i class="bi bi-person"></i> Mi Perfil</a>
            <a class="nav-link <?= isActive('control_asistencia.php') ?>" href="control_asistencia.php"><i class="bi bi-journal-check"></i> Mi Asistencia</a>
            <a class="nav-link <?= isActive('mis_solicitudes.php') ?>" href="mis_solicitudes.php"><i class="bi bi-list-check"></i> Mis Solicitudes</a>
            <a class="nav-link <?= isActive('solicitud_permisos.php') ?>" href="solicitud_permisos.php"><i class="bi bi-calendar-check"></i> Solicitar Permiso</a>
            <a class="nav-link <?= isActive('solicitud_vacaciones.php') ?>" href="solicitud_vacaciones.php"><i class="bi bi-umbrella"></i> Solicitar Vacaciones</a>
            <a class="nav-link <?= isActive('solicitud_incapacidad.php') ?>" href="solicitud_incapacidad.php"><i class="bi bi-file-earmark-medical"></i> Solicitar Incapacidad</a>
            <a class="nav-link <?= isActive('horas_extra.php') ?>" href="horas_extra.php"><i class="bi bi-clock-history"></i> Justificar Horas Extra</a>
            <a class="nav-link <?= isActive('ver_evaluaciones.php') ?>" href="ver_evaluaciones.php"><i class="bi bi-star-fill"></i> Mis Evaluaciones</a>
            <a class="nav-link <?= isActive('salario.php') ?>" href="salario.php"><i class="bi bi-cash-coin"></i> Mi Salario</a>
            <a class="nav-link <?= isActive('ver_liquidacion.php') ?>" href="ver_liquidacion.php"><i class="bi bi-bank"></i> Mi Liquidación (Est.)</a>
            <a class="nav-link <?= isActive('ver_aguinaldo.php') ?>" href="ver_aguinaldo.php"><i class="bi bi-gift"></i> Mi Aguinaldo (Est.)</a>   
        
        <?php elseif ($rol == 3): // ROL JEFATURA ?>
            <a class="nav-link <?= isActive('index_jefatura.php') ?>" href="index_jefatura.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a class="nav-link <?= isActive('equipo.php') ?>" href="equipo.php"><i class="bi bi-people"></i> Mi Equipo</a>
            <a class="nav-link <?= isActive('evaluacion.php') ?>" href="evaluacion.php"><i class="bi bi-star-half"></i> Evaluar Empleados</a>
            <a class="nav-link <?= isActive('horasextra.php') ?>" href="horasextra.php"><i class="bi bi-clock-history"></i> Aprobar Horas Extra</a>
            <a class="nav-link <?= isActive('permisos.php') ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Aprobar Permisos</a>
            <a class="nav-link <?= isActive('reporte_equipo.php') ?>" href="reporte_equipo.php"><i class="bi bi-clipboard-data"></i> Reportes de Equipo</a>

        <?php elseif ($rol == 4): // ROL RECURSOS HUMANOS (MENÚ CORREGIDO) ?>
            <a class="nav-link <?= isActive('index_rrhh.php') ?>" href="index_rrhh.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a class="nav-link <?= isActive(['personas.php', 'form_persona.php']) ?>" href="personas.php"><i class="bi bi-person-lines-fill"></i> Empleados</a>
            <a class="nav-link <?= isActive('nóminas.php') ?>" href="nóminas.php"><i class="bi bi-calculator"></i> Planillas</a>
            <a class="nav-link <?= isActive('horasextra.php') ?>" href="horasextra.php"><i class="bi bi-clock-history"></i> Horas Extra</a>
            <a class="nav-link <?= isActive('permisos.php') ?>" href="permisos.php"><i class="bi bi-calendar-check"></i> Permisos</a>
            <a class="nav-link <?= isActive('evaluacion.php') ?>" href="evaluacion.php"><i class="bi bi-star-fill"></i> Evaluaciones</a>
            <a class="nav-link <?= isActive('liquidación.php') ?>" href="liquidación.php"><i class="bi bi-bank"></i> Liquidaciones</a>
            <a class="nav-link <?= isActive('aguinaldo.php') ?>" href="aguinaldo.php"><i class="bi bi-gift"></i> Aguinaldos</a>
            <a class="nav-link <?= isActive('reporte_global.php') ?>" href="reporte_global.php"><i class="bi bi-bar-chart"></i> Reportes Globales</a>
        
        <?php else: // OTRO USUARIO O FALLBACK ?>
            <a class="nav-link <?= isActive('index_colaborador.php') ?>" href="index_colaborador.php"><i class="bi bi-house-door-fill"></i> Inicio</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <form action="logout.php" method="post">
            <button type="submit" class="btn"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</button>
        </form>
    </div>
</aside>

</body>
</html>