<?php 
// Iniciar sesión si no está ya iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Obtener variables de sesión
$username = $_SESSION['username'];
$rol = $_SESSION['rol']; // Rol del usuario actual
$idColaborador = isset($_SESSION['idColaborador']) ? $_SESSION['idColaborador'] : null;
$jefe_id = isset($_SESSION['Jefe_idColaborador']) ? $_SESSION['Jefe_idColaborador'] : null;
$departamento_id = isset($_SESSION['Departamento_idDepartamento']) ? $_SESSION['Departamento_idDepartamento'] : null;
$nombre_departamento = isset($_SESSION['NombreDepartamento']) ? $_SESSION['NombreDepartamento'] : 'Sin Departamento';

// Determinar si el usuario es jefe (su propio ID es el Jefe_idColaborador)
$esJefe = false;
if ($idColaborador && $jefe_id && $jefe_id == $idColaborador) {
    $esJefe = true;
}
?>

<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="img/edginton.png" alt="Logo Edginton">
            Edginton S.A.
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <!-- Opciones de menú según el rol -->
                <?php if ($rol == 1): // Administrador ?>
                    <li class="nav-item"><a class="nav-link" href="index_administrador.php">INICIO</a></li>
                    <li class="nav-item"><a class="nav-link" href="personas.php">Gestión de Personas</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php">Gestión de Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="configuración.php">Mantenimientos</a></li>
                
                <?php elseif ($rol == 2): // Colaborador ?>
                    <li class="nav-item"><a class="nav-link" href="index_colaborador.php">INICIO</a></li>
                    <li class="nav-item"><a class="nav-link" href="horas_extra.php">Solicitar Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link" href="evaluacion.php">Visualizar Evaluaciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="control_asistencia.php">Control de Asistencia</a></li>
                    <li class="nav-item"><a class="nav-link" href="salario.php">Visualizar Salario</a></li>
                    <li class="nav-item"><a class="nav-link" href="solicitud_permisos.php">Solicitar Permisos</a></li>
                   
                <?php elseif ($rol == 3): // Jefatura ?>
                    <li class="nav-item"><a class="nav-link" href="index_jefatura.php">INICIO</a></li>
                    <li class="nav-item"><a class="nav-link" href="permisos.php">Aprobar/Rechazar Permisos</a></li>
                    <li class="nav-item"><a class="nav-link" href="horasextra.php">Aprobar/Rechazar Horas Extra</a></li>
                    <li class="nav-item"><a class="nav-link" href="rendimiento_equipo.php">Realizar Evaluación</a></li>
    
                <?php elseif ($rol == 4): // Recursos Humanos ?>
                    <li class="nav-item"><a class="nav-link" href="index_rrhh.php">INICIO</a></li>
                    <li class="nav-item"><a class="nav-link" href="nóminas.php">Generar Planilla</a></li>
                    <li class="nav-item"><a class="nav-link" href="aguinaldo.php">Calcular Aguinaldo</a></li>
                    <li class="nav-item"><a class="nav-link" href="liquidación.php">Calcular Liquidación</a></li>
                    <li class="nav-item"><a class="nav-link" href="rendimiento_equipo.php">Observar Rendimiento</a></li>
                <?php endif; ?>

                <!-- Opciones comunes a todos los roles -->
                <li class="nav-item">
                    <span class="welcome-text">Bienvenido, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm btn-logout">Cerrar Sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
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
</style>
