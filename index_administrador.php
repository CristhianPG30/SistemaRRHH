<?php
session_start();
date_default_timezone_set('America/Costa_Rica');

// Verificar si el usuario ha iniciado sesión y tiene el rol de administrador (rol 1)
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 1) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexión a la base de datos

$username = $_SESSION['username'];

// --- INICIO: Consultas para el Dashboard ---

// 1. Contar colaboradores activos
$query_colaboradores = "SELECT COUNT(*) as total_activos FROM colaborador WHERE activo = 1";
$result_colaboradores = $conn->query($query_colaboradores);
$total_colaboradores_activos = $result_colaboradores->fetch_assoc()['total_activos'];

// 2. Contar solicitudes de permisos pendientes
$query_permisos = "SELECT COUNT(*) AS total_pendientes 
                   FROM permisos p
                   JOIN estado_cat e ON p.id_estado_fk = e.idEstado
                   WHERE e.Descripcion = 'Pendiente'";
$result_permisos = $conn->query($query_permisos);
$total_permisos_pendientes = $result_permisos->fetch_assoc()['total_pendientes'];

// 3. Contar solicitudes de horas extra pendientes
$total_horas_extra_pendientes = 0;
if ($conn->query("SHOW TABLES LIKE 'horas_extra'")->num_rows > 0) {
    $query_horas_extra = "SELECT COUNT(*) as total_pendientes FROM horas_extra WHERE estado = 'Pendiente'";
    $result_horas_extra = $conn->query($query_horas_extra);
    $total_horas_extra_pendientes = $result_horas_extra->fetch_assoc()['total_pendientes'];
}

$total_solicitudes_pendientes = $total_permisos_pendientes + $total_horas_extra_pendientes;

// --- FIN: Consultas para el Dashboard ---

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }

        /* Tarjetas de Estadísticas con Colores */
        .card-stat {
            border: none;
            border-radius: 0.75rem;
            color: #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card-stat:hover {
            transform: translateY(-5px);
        }
        .card-stat .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-stat i {
            font-size: 3.5rem;
            opacity: 0.3;
        }
        .card-stat-title {
            font-weight: 500;
            font-size: 1rem;
        }
        .card-stat-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        /* Colores específicos para las tarjetas de estadísticas */
        .bg-gradient-blue {
             background: linear-gradient(45deg, #3a7bd5, #00d2ff);
        }
        .bg-gradient-orange {
            background: linear-gradient(45deg, #f5af19, #f12711);
        }

        /* Tarjetas de Módulos */
        .card-link {
            text-decoration: none;
            color: inherit;
        }
        .card-link .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        .card-link .card:hover:not(.disabled) {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border: 1px solid var(--icon-color);
        }
        .card-link .card.disabled {
            background-color: #e9ecef;
            opacity: 0.7;
            cursor: not-allowed;
        }
        .card-link .card-body {
            text-align: center;
        }
        .card-link i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--icon-color);
        }
        .card-link h5 {
            font-weight: 500;
        }

        .main-header h1 {
            font-weight: 700;
            color: #343a40;
        }
        .main-header .lead {
            color: #6c757d;
        }
    </style>
</head>

<body>

<?php include 'header.php'; ?>

    <main class="container py-5">
        <div class="main-header text-center mb-5">
            <h1>Panel de Administración</h1>
            <p class="lead">Bienvenido, <?php echo htmlspecialchars($username); ?>. Gestiona el sistema desde aquí.</p>
        </div>

        <!-- Tarjetas de Estadísticas Clave (KPIs) -->
        <div class="row mb-5">
            <div class="col-lg-6 mb-4">
                <div class="card card-stat bg-gradient-blue">
                    <div class="card-body p-4">
                        <div>
                            <h5 class="card-stat-title">COLABORADORES ACTIVOS</h5>
                            <p class="card-stat-value"><?php echo $total_colaboradores_activos; ?></p>
                        </div>
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card card-stat bg-gradient-orange">
                    <div class="card-body p-4">
                        <div>
                            <h5 class="card-stat-title">SOLICITUDES PENDIENTES</h5>
                            <p class="card-stat-value"><?php echo $total_solicitudes_pendientes; ?></p>
                        </div>
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Módulos de Gestión -->
        <h3 class="text-center mb-4">Módulos de Gestión</h3>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <a href="personas.php" class="card-link" style="--icon-color: #0d6efd;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-person-lines-fill"></i><h5 class="card-title mt-2">Gestión de Personas</h5><p class="card-text text-muted">Añade, edita y administra la información de los colaboradores.</p></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="usuarios.php" class="card-link" style="--icon-color: #6f42c1;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-person-lock"></i><h5 class="card-title mt-2">Gestión de Usuarios</h5><p class="card-text text-muted">Crea y administra las cuentas de acceso al sistema y sus roles.</p></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="configuración.php" class="card-link" style="--icon-color: #6c757d;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-sliders"></i><h5 class="card-title mt-2">Mantenimientos</h5><p class="card-text text-muted">Configura parámetros del sistema, deducciones y jerarquías.</p></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="nóminas.php" class="card-link" style="--icon-color: #198754;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-cash-stack"></i><h5 class="card-title mt-2">Generar Planilla</h5><p class="card-text text-muted">Calcula y genera la nómina mensual de todos los colaboradores.</p></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="permisos.php" class="card-link" style="--icon-color: #ffc107;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-calendar-check"></i><h5 class="card-title mt-2">Aprobar Permisos</h5><p class="card-text text-muted">Revisa y gestiona las solicitudes de permisos y vacaciones.</p></div></div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="horasextra.php" class="card-link" style="--icon-color: #dc3545;">
                    <div class="card"><div class="card-body p-4"><i class="bi bi-clock-history"></i><h5 class="card-title mt-2">Aprobar Horas Extra</h5><p class="card-text text-muted">Valida las solicitudes de horas extra enviadas por los colaboradores.</p></div></div>
                </a>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
