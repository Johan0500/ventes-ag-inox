<?php
$pageTitle = "Prévisions";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/header.php';

// Récupérer les données mensuelles
$ventes = $pdo->query("
    SELECT DATE_FORMAT(mois, '%Y-%m') as mois, SUM(qte_livree) as total
    FROM ventes_eclatees GROUP BY mois ORDER BY mois ASC
")->fetchAll();

// Calculer la tendance (régression linéaire simple)
$n = count($ventes);
$previsions = [];
$tendance = 0;

if ($n >= 2) {
    $sumX = $sumY = $sumXY = $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $ventes[$i]['total'];
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $pente = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $pente * $sumX) / $n;
    $tendance = $pente;
    
    // Prévisions pour les 3 prochains mois
    for ($i = 0; $i < 3; $i++) {
        $x = $n + $i + 1;
        $prevision = round(max(0, $intercept + $pente * $x));
        $dernierMois = end($ventes)['mois'];
        $moisFutur = date('Y-m', strtotime($dernierMois . " +" . ($i+1) . " months"));
        $previsions[] = ['mois' => $moisFutur, 'prevision' => $prevision];
    }
}

// Top produits avec tendance
$produitsTendance = [];
if ($n >= 3) {
    $dernierMois = end($ventes)['mois'];
    $moisPrecedent = prev($ventes)['mois'];
    reset($ventes);
    
    $produitsTendance = $pdo->query("
        SELECT p.libelle, p.code_cip,
               m1.qte as qte_m1,
               m2.qte as qte_m2
        FROM produits p
        JOIN (
            SELECT code_cip, SUM(qte_livree) as qte FROM ventes_eclatees 
            WHERE DATE_FORMAT(mois, '%Y-%m') = '$dernierMois' GROUP BY code_cip
        ) m1 ON p.code_cip = m1.code_cip
        JOIN (
            SELECT code_cip, SUM(qte_livree) as qte FROM ventes_eclatees 
            WHERE DATE_FORMAT(mois, '%Y-%m') = '$moisPrecedent' GROUP BY code_cip
        ) m2 ON p.code_cip = m2.code_cip
        ORDER BY (m1.qte - m2.qte) DESC
        LIMIT 10
    ")->fetchAll();
}
?>

<h3>🔮 Prévisions et Tendances</h3>

<!-- Résumé -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $n; ?></h3>
                <p>Mois de données</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card <?php echo $tendance >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
            <div class="card-body text-center">
                <h3><?php echo $tendance >= 0 ? '+' : ''; ?><?php echo number_format($tendance, 1); ?></h3>
                <p>Tendance / mois</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo count($previsions); ?></h3>
                <p>Mois prévus</p>
            </div>
        </div>
    </div>
</div>

<!-- Graphique des prévisions -->
<div class="card mb-4">
    <div class="card-header"><h5>📈 Prévisions des ventes</h5></div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="previsionChart"></canvas>
        </div>
    </div>
</div>

<!-- Prévisions détaillées -->
<?php if (!empty($previsions)): ?>
<div class="card mb-4">
    <div class="card-header"><h5>📅 Prévisions pour les 3 prochains mois</h5></div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($previsions as $i => $p): ?>
            <div class="col-md-4">
                <div class="border rounded p-4 text-center">
                    <h4><?php echo $p['mois']; ?></h4>
                    <h2 class="text-primary"><?php echo number_format($p['prevision']); ?></h2>
                    <p class="text-muted">unités estimées</p>
                    <small>Intervalle : ±<?php echo round($p['prevision'] * 0.15); ?> unités</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Produits avec meilleure tendance -->
<?php if (!empty($produitsTendance)): ?>
<div class="card">
    <div class="card-header"><h5>📊 Produits avec la meilleure tendance</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Produit</th><th class="text-end">Mois précédent</th><th class="text-end">Dernier mois</th><th class="text-end">Évolution</th></tr></thead>
                <tbody>
                    <?php foreach ($produitsTendance as $pt): 
                        $evol = $pt['qte_m2'] > 0 ? round((($pt['qte_m1'] - $pt['qte_m2']) / $pt['qte_m2']) * 100, 1) : 100;
                    ?>
                    <tr>
                        <td><a href="/inox-pharma-ventes/produit_detail.php?cip=<?php echo $pt['code_cip']; ?>"><?php echo htmlspecialchars($pt['libelle']); ?></a></td>
                        <td class="text-end"><?php echo number_format($pt['qte_m2']); ?></td>
                        <td class="text-end"><strong><?php echo number_format($pt['qte_m1']); ?></strong></td>
                        <td class="text-end">
                            <span class="badge bg-<?php echo $evol>=0?'success':'danger'; ?>">
                                <?php echo $evol>=0?'+':''; ?><?php echo $evol; ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Graphique avec données réelles + prévisions
    var labels = [];
    var reel = [];
    var prevision = [];
    
    <?php foreach ($ventes as $v): ?>
    labels.push('<?php echo $v['mois']; ?>');
    reel.push(<?php echo $v['total']; ?>);
    prevision.push(null);
    <?php endforeach; ?>
    
    <?php foreach ($previsions as $p): ?>
    labels.push('<?php echo $p['mois']; ?> (prev)');
    reel.push(null);
    prevision.push(<?php echo $p['prevision']; ?>);
    <?php endforeach; ?>
    
    new Chart(document.getElementById('previsionChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ventes réelles',
                data: reel,
                borderColor: '#1a73e8',
                backgroundColor: 'rgba(26,115,232,0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true
            }, {
                label: 'Prévisions',
                data: prevision,
                borderColor: '#ea4335',
                borderDash: [5, 5],
                borderWidth: 3,
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>