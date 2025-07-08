<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['rol'] != 2) {
    header('Location: login.php');
    exit;
}
include 'db.php';
$colaborador_id = $_SESSION['colaborador_id'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Evaluación - Edginton S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f4faff 0%, #e3f0fd 100%) !important; font-family: 'Poppins',sans-serif; }
        .card-evaluacion { background: #fff; border-radius: 2.2rem; box-shadow: 0 8px 32px 0 rgba(44,62,80,.10); padding: 2.2rem 2.5rem 1.7rem 2.5rem; max-width: 750px; margin: 44px auto 0; }
        .titulo { font-weight: 900; font-size: 2.1rem; color: #185197; letter-spacing: .5px; text-align: center; display: flex; align-items: center; justify-content: center; gap: .8rem;}
        .promedio-box { display: flex; flex-direction:column; align-items:center; margin: 2.3rem 0 2rem 0;}
        .promedio-num { font-size: 3.2rem; font-weight: 800; color: #2c92e2;}
        .promedio-stars { font-size: 2.2rem; color: #ffd700; margin-bottom: 10px;}
        .promedio-label { font-size: 1.12rem; color: #1b5880; margin-top: .2rem;}
        .progress { height: 18px; border-radius: 15px; background: #e4f0ff;}
        .progress-bar { background: linear-gradient(90deg, #23b6ff 70%, #8de1fd 100%);}
        .table-historial th { background: #f2faff; color: #329cd4;}
        .badge-valor { font-size:1rem; background: #ffec9f; color:#a68b0a; border-radius:.7rem; font-weight:600; padding: .6em 1em;}
        .comentario-chat {background: #eaf8fe; border-radius: 1rem 1rem 1rem .2rem; box-shadow: 0 1px 7px #37b1ff15; padding: .8em 1.1em; font-size: 1.08em; color: #206288; margin-bottom:.6em;}
        .historial-titulo {font-size: 1.25rem; font-weight:700; color: #1b7bb2; margin-top: 2rem; margin-bottom:1.1rem;}
        @media (max-width:700px){ .card-evaluacion{padding:1rem;} .titulo{font-size:1.15rem;} .table-historial td,.table-historial th{font-size:.97rem;} }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="card-evaluacion animate__animated animate__fadeInDown">
    <div class="titulo mb-3">
        <i class="bi bi-star-fill"></i> Mi Evaluación General
    </div>

    <?php
    // 1. Promedio
    $sql_promedio = "SELECT ROUND(AVG(Calificacion),1) as promedio FROM evaluaciones WHERE Colaborador_idColaborador = ?";
    $stmt = $conn->prepare($sql_promedio);
    $stmt->bind_param("i", $colaborador_id);
    $stmt->execute();
    $stmt->bind_result($promedio);
    $stmt->fetch();
    $stmt->close();
    $promedio = $promedio ?: 0;
    ?>
    <div class="promedio-box">
        <div class="promedio-num animate__animated animate__fadeInDown"><?= $promedio ?></div>
        <div class="promedio-stars">
            <?php for($i=1;$i<=5;$i++): ?>
                <?php if($i <= round($promedio)): ?>
                    <i class="bi bi-star-fill"></i>
                <?php else: ?>
                    <i class="bi bi-star"></i>
                <?php endif; ?>
            <?php endfor ?>
        </div>
        <div class="progress w-50 mb-2">
            <div class="progress-bar" role="progressbar" style="width: <?= ($promedio/5)*100 ?>%" aria-valuenow="<?= $promedio ?>" aria-valuemin="0" aria-valuemax="5"></div>
        </div>
        <div class="promedio-label">Promedio general de todas tus evaluaciones (máx. 5)</div>
    </div>

    <!-- 2. Historial -->
    <div class="historial-titulo"><i class="bi bi-clock-history"></i> Historial de Evaluaciones</div>
    <div class="table-responsive">
    <table class="table table-historial table-bordered align-middle">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Calificación</th>
                <th>Comentario</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sql_hist = "SELECT Fecharealizacion, Calificacion, Comentarios FROM evaluaciones WHERE Colaborador_idColaborador = ? ORDER BY Fecharealizacion DESC";
        $stmt = $conn->prepare($sql_hist);
        $stmt->bind_param("i", $colaborador_id);
        $stmt->execute();
        $stmt->bind_result($fecha, $calif, $coment);
        $hayDatos = false;
        while ($stmt->fetch()):
            $hayDatos = true;
        ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($fecha)) ?></td>
                <td>
                    <span class="badge badge-valor"><?= $calif ?> / 5</span>
                    <span style="margin-left:7px;">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <?= $i <= $calif ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>'; ?>
                        <?php endfor ?>
                    </span>
                </td>
                <td>
                    <?php if ($coment): ?>
                        <div class="comentario-chat"><?= nl2br(htmlspecialchars($coment)) ?></div>
                    <?php else: ?>
                        <em class="text-muted">Sin comentarios</em>
                    <?php endif ?>
                </td>
            </tr>
        <?php endwhile; $stmt->close(); ?>
        <?php if (!$hayDatos): ?>
            <tr>
                <td colspan="3" class="text-muted text-center">No tienes evaluaciones registradas.</td>
            </tr>
        <?php endif ?>
        </tbody>
    </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</body>
</html>
