<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Buscar el idColaborador asociado al usuario logueado
$username = $_SESSION['username'];
$id_colaborador = null;

$sql = "SELECT c.idColaborador
        FROM usuario u
        INNER JOIN persona p ON u.id_persona_fk = p.idPersona
        INNER JOIN colaborador c ON c.id_persona_fk = p.idPersona
        WHERE u.username = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $id_colaborador = $row['idColaborador'];
$stmt->close();

if (!$id_colaborador) {
    include 'header.php';
    echo "<div class='alert alert-danger text-center' style='margin-left:280px;margin-top:40px;max-width:600px;'>
            No se pudo encontrar tu perfil de colaborador. Contacte al administrador.
          </div>";
    include 'footer.php';
    exit;
}

$evaluaciones = [];
$sql = "SELECT e.Fecharealizacion, e.Calificacion, e.Comentarios
        FROM evaluaciones e
        WHERE e.Colaborador_idColaborador = ?
        ORDER BY e.Fecharealizacion DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_colaborador);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $evaluaciones[] = $row;
$stmt->close();

// Promedio general
$promedio = count($evaluaciones) ? round(array_sum(array_column($evaluaciones,'Calificacion'))/count($evaluaciones),2) : 0;
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
.eval-glass-dashboard {
    max-width: 1100px;
    margin: 3.2rem auto 2.5rem auto;
    padding: 2.8rem 2.3rem 2.3rem 2.3rem;
    background: rgba(255,255,255,0.79);
    border-radius: 2.5rem;
    box-shadow: 0 10px 38px #13c6f15c, 0 2px 7px #6de5e91c;
    min-height: 660px;
    backdrop-filter: blur(16px);
    border: 1.5px solid #e4f5fa;
    position: relative;
    animation: fadeInPerfil .7s;
}
@keyframes fadeInPerfil { 0%{opacity:0;transform:translateY(35px);} 100%{opacity:1;transform:none;}}

.eval-glass-title {
    font-weight: 900; font-size: 2.45rem; color: #1886c4; letter-spacing: 1px;
    margin-bottom: 1.1rem; text-align:center;
    text-shadow: 0 4px 20px #16cdf0a0;
}

.eval-glass-summary {
    background: linear-gradient(90deg, #19e7e4 0%, #5ce0fa 90%);
    border-radius: 1.6rem;
    margin-bottom: 2.4rem;
    padding: 2rem 2.2rem 1.1rem 2.2rem;
    display: flex; align-items: center; gap: 2.6rem;
    box-shadow: 0 6px 30px #14e3ed23;
    flex-wrap: wrap;
    justify-content: center;
}

.eval-glass-summary .bigicon {
    font-size: 4.1rem;
    color: #17b9ee;
    background: linear-gradient(135deg,#13e9ed 60%,#62e0ff 100%);
    border-radius: 2rem;
    padding: .7rem 1.1rem;
    margin-right: 1rem;
    box-shadow: 0 2px 24px #21b2ff21;
}
.eval-glass-summary .data {
    flex:1; min-width:220px;
}
.eval-glass-summary .avg-label {font-size:1.11rem; color:#17707d; font-weight:600;}
.eval-glass-summary .avg-score {font-size:2.8rem; font-weight:900; color:#0b768a;}
.eval-glass-summary .stars {
    font-size: 2.6rem;
    background: linear-gradient(90deg,#ffd84c,#ffb917 55%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.eval-glass-summary .total-ev {
    font-size:1.13rem;color:#18747b;font-weight:700;margin-top:.85rem;
}

.eval-search-bar {
    max-width:400px;margin:auto 0 1.9rem auto;
}
.eval-search-bar input {
    border-radius: 1.4rem;
    font-size: 1.13rem;
    padding:.7rem 1.1rem;
    box-shadow:0 2px 8px #13c6f119;
}

.eval-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
    gap: 1.5rem;
    margin-top: 1.3rem;
}
.eval-card-glass {
    background: rgba(240,252,255,0.98);
    border-radius: 1.6rem;
    box-shadow: 0 2px 14px #13c6f12d;
    padding: 1.3rem 1.6rem 1rem 1.6rem;
    transition: box-shadow .22s, transform .14s;
    animation: fadeInUp .7s;
    border: 1.5px solid #e6faff;
    position: relative;
    min-height:160px;
}
.eval-card-glass:hover {
    box-shadow: 0 12px 40px #18c0ff3e;
    transform: translateY(-5px) scale(1.017);
    border-color: #c5f5ff;
}
.eval-card-date {
    font-size:.96rem; color:#25b0e7; font-weight:700;margin-bottom:.3rem;
    display:flex; align-items:center; gap:.45rem;
}
.eval-card-stars {
    font-size:1.6rem; letter-spacing:.12rem; margin-bottom:.2rem;
    background: linear-gradient(90deg,#ffd84c,#ffb917 60%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight:900;
}
.eval-card-glass .score {
    font-size:1.17rem; font-weight:700; color:#1498ae; margin-left:.5rem;
}
.eval-card-coment {
    font-size:1.12rem; color:#167fa4; margin-bottom:.1rem; margin-top:.2rem;
    font-style:italic;
    word-break: break-word;
}
.eval-card-glass .no-comment {color:#a4b4bc;font-style:italic;}
@media (max-width:700px) {
    .eval-glass-dashboard {padding:1.4rem .1rem;}
}
</style>

<div class="container" style="margin-left: 280px;">
    <div class="eval-glass-dashboard animate__animated animate__fadeIn">
        <div class="eval-glass-title"><i class="bi bi-stars"></i> Mis Evaluaciones</div>

        <!-- Súper resumen -->
        <div class="eval-glass-summary animate__animated animate__fadeInDown">
            <div class="bigicon"><i class="bi bi-trophy-fill"></i></div>
            <div class="data">
                <div class="avg-label">Promedio general de mis evaluaciones</div>
                <div class="avg-score"><?= $promedio ? $promedio : '--' ?> <span style="font-size:1.25rem;">/ 5</span></div>
                <div class="stars">
                    <?php
                    $full = floor($promedio);
                    $half = ($promedio - $full >= 0.5) ? 1 : 0;
                    for ($i=1; $i<=5; $i++) {
                        if ($i <= $full) echo '★';
                        elseif ($half && $i == $full+1) echo '<span style="color:#ffe17c;">★</span>';
                        else echo '<span style="color:#e0e0e0;">★</span>';
                    }
                    ?>
                </div>
                <div class="total-ev">
                    <i class="bi bi-people"></i> <?= count($evaluaciones) ?> evaluación<?= count($evaluaciones)==1 ? '' : 'es' ?>
                </div>
            </div>
        </div>

        <!-- Buscador -->
        <div class="eval-search-bar mb-2">
            <input type="text" id="filtroEvaluacion" class="form-control" placeholder="Buscar comentario o fecha...">
        </div>

        <!-- Cards de evaluaciones -->
        <div class="eval-cards-grid" id="eval-cards-list">
        <?php if ($evaluaciones): ?>
            <?php foreach ($evaluaciones as $ev): ?>
                <div class="eval-card-glass animate__animated animate__fadeInUp">
                    <div class="eval-card-date"><i class="bi bi-calendar-event"></i>
                        <?= htmlspecialchars(date('d/m/Y', strtotime($ev['Fecharealizacion']))) ?>
                    </div>
                    <div class="eval-card-stars">
                        <?php
                        for ($i=1; $i<=5; $i++) {
                            if ($ev['Calificacion'] >= $i) echo '★';
                            else echo '<span style="color:#e0e0e0;">★</span>';
                        }
                        ?>
                        <span class="score"><?= htmlspecialchars($ev['Calificacion']) ?>/5</span>
                    </div>
                    <div class="eval-card-coment">
                        <?= $ev['Comentarios'] ? htmlspecialchars($ev['Comentarios']) : "<span class='no-comment'>Sin comentarios.</span>" ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info text-center" style="font-size:1.18rem;">
                <i class="bi bi-emoji-smile text-primary" style="font-size:2.4rem;"></i><br>
                Aún no tienes evaluaciones registradas.
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Buscador en vivo por comentario o fecha
document.getElementById('filtroEvaluacion').addEventListener('keyup', function() {
    var filtro = this.value.toLowerCase();
    var cards = document.querySelectorAll('#eval-cards-list .eval-card-glass');
    cards.forEach(function(card){
        let txt = card.textContent.toLowerCase();
        card.style.display = (txt.indexOf(filtro) !== -1) ? '' : 'none';
    });
});
</script>
