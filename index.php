<?php
$pageTitle = "Tableau de Bord";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

// Initialiser toutes les variables
$nbProduits = 0;
$nbClients = 0;
$nbVentes = 0;
$nbAgences = 0;
$totalUnites = 0;
$caTotal = 0;
$ventesParMois = [];
$topProduits = [];
$topClients = [];
$ventesParAgence = [];
$ventesParGrossiste = [];
$produitsEnHausse = [];
$produitsEnBaisse = [];
$alertes = [];
$moisDisponibles = [];
$grossistesList = [];
$evolutionGlobale = 0;
$derniereImportation = '';
$dataLoaded = false;

// Filtres
$moisFiltre = $_GET['mois_filtre'] ?? 'all';
$grossisteFiltre = $_GET['grossiste'] ?? 'all';

try {
    $nbProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    
    if ($nbProduits > 0) {
        $dataLoaded = true;
        
        // Listes pour les filtres
        $moisDisponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
        $grossistesList = $pdo->query("SELECT code, nom FROM grossistes WHERE actif = 1")->fetchAll();
        
        // Construction du filtre SQL
        $filtreSQL = '';
        $filtreParams = [];
        $conditions = [];
        
        if ($moisFiltre != 'all' && in_array($moisFiltre, $moisDisponibles)) {
            $conditions[] = "DATE_FORMAT(v.mois, '%Y-%m') = :mois";
            $filtreParams['mois'] = $moisFiltre;
        }
        
        if ($grossisteFiltre != 'all') {
            $conditions[] = "v.grossiste_code = :gross";
            $filtreParams['gross'] = $grossisteFiltre;
        }
        
        // Filtre labo automatique
        $labo = getCurrentLabo();
        if ($labo !== 'admin' && !empty($labo)) {
            $conditions[] = "v.labo = :labo";
            $filtreParams['labo'] = $labo;
        }
        
        if (!empty($conditions)) {
            $filtreSQL = " WHERE " . implode(" AND ", $conditions);
        }
        
        // Compteurs généraux
        $nbClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $nbAgences = $pdo->query("SELECT COUNT(DISTINCT agence) FROM clients")->fetchColumn();
        $derniereImportation = $pdo->query("SELECT MAX(created_at) FROM ventes_eclatees")->fetchColumn();
        
        // Total unités et transactions
        $sqlTotaux = "SELECT COALESCE(SUM(qte_livree), 0), COUNT(*) FROM ventes_eclatees v" . $filtreSQL;
        $stmt = $pdo->prepare($sqlTotaux);
        $stmt->execute($filtreParams);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $totalUnites = $row[0];
        $nbVentes = $row[1];
        
        // CA total
        $sqlCA = "SELECT COALESCE(SUM(v.qte_livree * p.prix_cession), 0) FROM ventes_eclatees v JOIN produits p ON v.code_cip = p.code_cip" . str_replace('v.', 'v.', $filtreSQL);
        $stmt = $pdo->prepare($sqlCA);
        $stmt->execute($filtreParams);
        $caTotal = $stmt->fetchColumn();
        
        // Ventes par mois (tous les mois, sans filtre)
        $ventesParMois = $pdo->query("SELECT DATE_FORMAT(mois, '%Y-%m') as mois, SUM(qte_livree) as total FROM ventes_eclatees GROUP BY mois ORDER BY mois ASC")->fetchAll();
        
        // Top 10 produits
        $sqlP = "SELECT p.libelle, p.code_cip, SUM(v.qte_livree) as total FROM ventes_eclatees v JOIN produits p ON v.code_cip = p.code_cip" . $filtreSQL . " GROUP BY v.code_cip, p.libelle ORDER BY total DESC LIMIT 10";
        $stmt = $pdo->prepare($sqlP);
        $stmt->execute($filtreParams);
        $topProduits = $stmt->fetchAll();
        
        // Top 10 clients
        $sqlC = "SELECT c.designation, c.code_client, c.province, SUM(v.qte_livree) as total FROM ventes_eclatees v JOIN clients c ON v.code_client = c.code_client" . $filtreSQL . " GROUP BY v.code_client, c.designation, c.province ORDER BY total DESC LIMIT 10";
        $stmt = $pdo->prepare($sqlC);
        $stmt->execute($filtreParams);
        $topClients = $stmt->fetchAll();
        
        // Ventes par agence
        $sqlA = "SELECT c.agence, SUM(v.qte_livree) as total FROM ventes_eclatees v JOIN clients c ON v.code_client = c.code_client" . $filtreSQL . " GROUP BY c.agence ORDER BY total DESC";
        $stmt = $pdo->prepare($sqlA);
        $stmt->execute($filtreParams);
        $ventesParAgence = $stmt->fetchAll();
        
        // Ventes par grossiste
        $sqlG = "SELECT COALESCE(v.grossiste_code, 'inconnu') as code, SUM(v.qte_livree) as total FROM ventes_eclatees v" . $filtreSQL . " GROUP BY v.grossiste_code ORDER BY total DESC";
        $stmt = $pdo->prepare($sqlG);
        $stmt->execute($filtreParams);
        $ventesParGrossiste = $stmt->fetchAll();
        
        // Produits en hausse/baisse (2 derniers mois, sans filtre)
        $mois = array_column($ventesParMois, 'mois');
        if (count($mois) >= 2) {
            $dernierMois = end($mois);
            $moisPrecedent = prev($mois);
            reset($mois);
            
            $stmt = $pdo->prepare("SELECT p.libelle, p.code_cip, COALESCE(m2.qte,0) as qte_dernier, COALESCE(m1.qte,0) as qte_precedent, CASE WHEN COALESCE(m1.qte,0)>0 THEN ROUND(((COALESCE(m2.qte,0)-COALESCE(m1.qte,0))/m1.qte)*100,1) WHEN COALESCE(m2.qte,0)>0 THEN 100 ELSE 0 END as evolution FROM produits p LEFT JOIN (SELECT code_cip,SUM(qte_livree) as qte FROM ventes_eclatees WHERE DATE_FORMAT(mois,'%Y-%m')=:m2 GROUP BY code_cip) m2 ON p.code_cip=m2.code_cip LEFT JOIN (SELECT code_cip,SUM(qte_livree) as qte FROM ventes_eclatees WHERE DATE_FORMAT(mois,'%Y-%m')=:m1 GROUP BY code_cip) m1 ON p.code_cip=m1.code_cip WHERE m2.qte>0 ORDER BY evolution DESC");
            $stmt->execute(['m1'=>$moisPrecedent, 'm2'=>$dernierMois]);
            $all = $stmt->fetchAll();
            
            $produitsEnHausse = array_slice(array_filter($all, fn($a)=>$a['evolution']>0), 0, 5);
            $produitsEnBaisse = array_slice(array_filter($all, fn($a)=>$a['evolution']<0), 0, 5);
            usort($produitsEnBaisse, fn($a,$b)=>$a['evolution']<=>$b['evolution']);
        }
        
        // Évolution globale
        if (count($ventesParMois) >= 2) {
            $dernier = end($ventesParMois)['total'];
            $precedent = prev($ventesParMois)['total'];
            reset($ventesParMois);
            $evolutionGlobale = $precedent > 0 ? round((($dernier-$precedent)/$precedent)*100, 1) : 0;
        }
        
        // Alertes (max 3)
        foreach ($produitsEnBaisse as $pb) {
            if ($pb['evolution'] < -30 && count($alertes) < 3) {
                $alertes[] = ['type'=>'danger', 'icone'=>'📉', 'message'=>'<b>'.htmlspecialchars($pb['libelle']).'</b> : <span style="color:#ef4444;">'.$pb['evolution'].'%</span> ce mois'];
            }
        }
        foreach ($produitsEnHausse as $ph) {
            if ($ph['evolution'] > 50 && count($alertes) < 3) {
                $alertes[] = ['type'=>'success', 'icone'=>'📈', 'message'=>'<b>'.htmlspecialchars($ph['libelle']).'</b> : <span style="color:#10b981;">+'.$ph['evolution'].'%</span> ce mois'];
            }
        }
    }
} catch(Exception $e) {
    $dataLoaded = false;
}

$grossNames = ['tedis'=>'TEDIS', 'dpci'=>'DPCI', 'laborex'=>'LABOREX', 'copharmed'=>'COPHARMED', 'inconnu'=>'Non défini'];
?>

<style>
.executive-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
}
.stat-card-mini {
    border-radius: 14px;
    padding: 1.3rem 0.8rem;
    text-align: center;
    color: white;
    transition: transform 0.2s;
}
.stat-card-mini:hover { transform: translateY(-3px); }
.stat-card-mini .value { font-size: 1.8rem; font-weight: 700; line-height: 1; }
.stat-card-mini .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85; margin-top: 4px; }
.alert-flash {
    border: none;
    border-radius: 12px;
    padding: 0.7rem 1.2rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff3cd;
    border-left: 4px solid #f59e0b;
}
.alert-flash.danger { background: #fee2e2; border-left-color: #ef4444; }
.alert-flash.success { background: #d1fae5; border-left-color: #10b981; }
.top-link {
    display: flex;
    align-items: center;
    padding: 0.6rem 1rem;
    text-decoration: none;
    color: #1e293b;
    transition: background 0.2s;
    border-bottom: 1px solid #f1f5f9;
}
.top-link:last-child { border-bottom: none; }
.top-link:hover { background: #f8fafc; }
.top-link .rank { width: 28px; font-weight: 700; text-align: center; font-size: 0.85rem; }
.top-link .rank.gold { color: #f59e0b; }
.top-link .rank.silver { color: #94a3b8; }
.top-link .rank.bronze { color: #d97706; }
.filter-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 1.5rem; flex-wrap: wrap; }
</style>

<?php if (!$dataLoaded): ?>
<div class="text-center py-5">
    <div style="font-size:5rem;">📭</div>
    <h3 class="mt-3">Aucune donnée</h3>
    <p class="text-muted">Importez vos fichiers pour commencer</p>
    <div class="mt-3">
        <a href="import/import.php" class="btn btn-primary btn-lg me-2">📥 Importer ventes</a>
        <a href="import/sectorisation.php" class="btn btn-outline-primary btn-lg">🗺️ Importer sectorisation</a>
    </div>
</div>
<?php else: ?>

<!-- Résumé exécutif -->
<div class="executive-summary animate-fade">
    <div class="row align-items-center">
        <div class="col-lg-9">
            <h5 class="mb-1">
                📋 <?php echo $moisFiltre!='all' ? $moisFiltre : 'Vue d\'ensemble'; ?>
                <?php if ($grossisteFiltre!='all'): ?>
                <span class="badge bg-white text-dark ms-2"><?php echo $grossNames[$grossisteFiltre] ?? $grossisteFiltre; ?></span>
                <?php endif; ?>
                <?php if (isset($_SESSION['labo']) && $_SESSION['labo']!='admin'): ?>
                <span class="badge bg-white text-dark ms-1">🔬 <?php echo $_SESSION['labo']==='croient'?'Croient':'LIC'; ?></span>
                <?php endif; ?>
            </h5>
            <p class="mb-0 opacity-90 small">
                <?php echo $nbProduits; ?> produits · <?php echo $nbClients; ?> pharmacies · <?php echo $nbAgences; ?> agences · 
                <?php echo number_format($totalUnites,0,',',' '); ?> unités · CA <?php echo number_format($caTotal,0,',',' '); ?> F CFA
            </p>
        </div>
        <div class="col-lg-3 text-lg-end mt-2 mt-lg-0">
            <small class="opacity-75"><?php echo date('d/m/Y H:i', strtotime($derniereImportation)); ?></small>
            <?php if($evolutionGlobale!=0): ?>
            <span class="badge <?php echo $evolutionGlobale>=0?'bg-success':'bg-danger'; ?> ms-2"><?php echo $evolutionGlobale>=0?'+':''; ?><?php echo $evolutionGlobale; ?>%</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alertes -->
<?php if(!empty($alertes)): ?>
<div class="row g-2 mb-4">
    <?php foreach($alertes as $a): ?>
    <div class="col-lg-4">
        <div class="alert-flash <?php echo $a['type']; ?>">
            <span><?php echo $a['icone']; ?></span>
            <span><?php echo $a['message']; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Barre de filtres -->
<div class="filter-bar animate-fade">
    <label class="form-label mb-0 small fw-bold">📅</label>
    <select name="mois_filtre" class="form-select form-select-sm" style="width:auto;" onchange="applyFilter()">
        <option value="all" <?php echo $moisFiltre=='all'?'selected':''; ?>>Tous les mois</option>
        <?php foreach($moisDisponibles as $m): ?>
        <option value="<?php echo $m; ?>" <?php echo $moisFiltre==$m?'selected':''; ?>><?php echo $m; ?></option>
        <?php endforeach; ?>
    </select>
    
    <label class="form-label mb-0 small fw-bold ms-2">🏢</label>
    <select name="grossiste" class="form-select form-select-sm" style="width:auto;" onchange="applyFilter()">
        <option value="all" <?php echo $grossisteFiltre=='all'?'selected':''; ?>>Tous les grossistes</option>
        <?php foreach($grossistesList as $g): ?>
        <option value="<?php echo $g['code']; ?>" <?php echo $grossisteFiltre==$g['code']?'selected':''; ?>><?php echo $g['nom']; ?></option>
        <?php endforeach; ?>
    </select>
    
    <?php if($moisFiltre!='all' || $grossisteFiltre!='all'): ?>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">✕ Réinitialiser</a>
    <?php endif; ?>
</div>

<!-- Mini-cartes statistiques -->
<div class="row g-2 mb-4">
    <?php 
    $stats = [
        ['v'=>$nbProduits, 'l'=>'Produits', 'c'=>'primary', 'i'=>'📦'],
        ['v'=>$nbClients, 'l'=>'Clients', 'c'=>'success', 'i'=>'🏪'],
        ['v'=>number_format($nbVentes), 'l'=>'Transactions', 'c'=>'info', 'i'=>'💰'],
        ['v'=>$nbAgences, 'l'=>'Agences', 'c'=>'warning', 'i'=>'🏢'],
        ['v'=>number_format($totalUnites), 'l'=>'Unités', 'c'=>'danger', 'i'=>'📊'],
        ['v'=>count($moisDisponibles), 'l'=>'Mois', 'c'=>'purple', 'i'=>'📅'],
    ];
    foreach($stats as $s): ?>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stat-card-mini bg-<?php echo $s['c']; ?>" style="<?php echo $s['c']=='purple'?'background:#6f42c1;':''; ?>">
            <div class="value"><?php echo $s['v']; ?></div>
            <div class="label"><?php echo $s['i']; ?> <?php echo $s['l']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Graphique + Top Produits -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>📈 Évolution des ventes</span>
                <a href="comparaison.php" class="text-decoration-none small">Comparer →</a>
            </div>
            <div class="card-body"><div class="chart-container"><canvas id="ventesChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>🏆 Top Produits</span>
                <a href="produits.php?all=1" class="text-decoration-none small">Tous →</a>
            </div>
            <div class="card-body p-0" style="max-height:370px;overflow-y:auto;">
                <?php foreach($topProduits as $i=>$p): ?>
                <a href="produit_detail.php?cip=<?php echo $p['code_cip']; ?>" class="top-link">
                    <span class="rank <?php echo ['gold','silver','bronze'][$i]??''; ?>"><?php echo ['🥇','🥈','🥉'][$i]??($i+1); ?></span>
                    <span class="flex-grow-1 small"><?php echo htmlspecialchars($p['libelle']); ?></span>
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($p['total']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Clients + Agences + Grossistes -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>🏪 Top Clients</span>
                <a href="clients.php?all=1" class="text-decoration-none small">Tous →</a>
            </div>
            <div class="card-body p-0" style="max-height:350px;overflow-y:auto;">
                <?php foreach($topClients as $i=>$c): ?>
                <a href="client_detail.php?client=<?php echo $c['code_client']; ?>" class="top-link">
                    <span class="rank <?php echo ['gold','silver','bronze'][$i]??''; ?>"><?php echo ['🥇','🥈','🥉'][$i]??($i+1); ?></span>
                    <span class="flex-grow-1 small"><?php echo htmlspecialchars($c['designation']); ?></span>
                    <span class="fw-bold small"><?php echo number_format($c['total']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>🏢 Agences</span>
                <a href="regions.php" class="text-decoration-none small">Carte →</a>
            </div>
            <div class="card-body">
                <?php $maxAg = max(array_column($ventesParAgence,'total'))?:1; 
                foreach($ventesParAgence as $ag): $pct=($ag['total']/$maxAg)*100; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small"><strong><?php echo htmlspecialchars($ag['agence']); ?></strong><span><?php echo number_format($ag['total']); ?> u.</span></div>
                    <div class="progress" style="height:16px;"><div class="progress-bar bg-<?php echo $pct>66?'success':($pct>33?'warning':'info'); ?>" style="width:<?php echo $pct; ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span>🏭 Grossistes</span></div>
            <div class="card-body">
                <?php $maxGr = max(array_column($ventesParGrossiste,'total'))?:1;
                foreach($ventesParGrossiste as $gr): $pct=($gr['total']/$maxGr)*100;
                    $nom = $grossNames[$gr['code']] ?? $gr['code'];
                    $color = $gr['code']==='tedis'?'primary':($gr['code']==='dpci'?'success':($gr['code']==='laborex'?'warning':'info'));
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small"><strong><?php echo $nom; ?></strong><span><?php echo number_format($gr['total']); ?> u.</span></div>
                    <div class="progress" style="height:16px;"><div class="progress-bar bg-<?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hausses/Baisses -->
<?php if(!empty($produitsEnHausse)||!empty($produitsEnBaisse)): ?>
<div class="row g-3 mb-4">
    <?php if(!empty($produitsEnHausse)): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white" style="border-radius:12px 12px 0 0!important;"><h6 class="mb-0 small">📈 Plus forte croissance</h6></div>
            <div class="card-body p-0">
                <?php foreach($produitsEnHausse as $ph): ?>
                <a href="produit_detail.php?cip=<?php echo $ph['code_cip']; ?>" class="top-link">
                    <span class="flex-grow-1 small"><?php echo htmlspecialchars($ph['libelle']); ?></span>
                    <span class="badge bg-success">+<?php echo $ph['evolution']; ?>%</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if(!empty($produitsEnBaisse)): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white" style="border-radius:12px 12px 0 0!important;"><h6 class="mb-0 small">📉 Plus forte baisse</h6></div>
            <div class="card-body p-0">
                <?php foreach($produitsEnBaisse as $pb): ?>
                <a href="produit_detail.php?cip=<?php echo $pb['code_cip']; ?>" class="top-link">
                    <span class="flex-grow-1 small"><?php echo htmlspecialchars($pb['libelle']); ?></span>
                    <span class="badge bg-danger"><?php echo $pb['evolution']; ?>%</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function applyFilter() {
    const mois = document.querySelector('select[name="mois_filtre"]').value;
    const grossiste = document.querySelector('select[name="grossiste"]').value;
    window.location.href = 'index.php?mois_filtre=' + mois + '&grossiste=' + grossiste;
}

<?php if($dataLoaded && !empty($ventesParMois)): ?>
var ctx = document.getElementById('ventesChart').getContext('2d');
var grad = ctx.createLinearGradient(0,0,0,400);
grad.addColorStop(0,'rgba(79,70,229,0.25)');grad.addColorStop(1,'rgba(79,70,229,0.0)');
new Chart(ctx,{
    type:'line',
    data:{
        labels:[<?php echo "'".implode("','",array_column($ventesParMois,'mois'))."'"; ?>],
        datasets:[{
            label:'Unités vendues',
            data:[<?php echo implode(',',array_column($ventesParMois,'total')); ?>],
            borderColor:'#4f46e5',
            backgroundColor:grad,
            borderWidth:3,
            tension:0.4,
            fill:true,
            pointRadius:6,
            pointBackgroundColor:'#4f46e5',
            pointBorderColor:'white',
            pointBorderWidth:2,
            pointHoverRadius:10
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{
            y:{beginAtZero:true,grid:{color:'#f1f5f9'}},
            x:{grid:{display:false}}
        }
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/footer.php'; ?>