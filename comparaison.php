<?php
$pageTitle = "Comparaison Périodes";
require_once __DIR__ . '/config/database.php';

// Initialiser TOUTES les variables
$dataLoaded = false;
$moisDisponibles = [];
$mois1 = '';
$mois2 = '';
$mode = $_GET['mode'] ?? 'produits';
$resultats = [];
$totaux = ['m1' => 0, 'm2' => 0, 'evolution' => 0];
$evolutionGlobale = [];
$count = 0;

try {
    $count = $pdo->query("SELECT COUNT(*) FROM ventes_eclatees")->fetchColumn();
    
    if ($count > 0) {
        $dataLoaded = true;
        
        // Mois disponibles
        $stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as mois FROM ventes_eclatees ORDER BY mois ASC");
        $tmp = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tmp as $t) {
            $moisDisponibles[] = $t['mois'];
        }
        
        // Mois par défaut
        $mois1 = isset($_GET['mois1']) ? $_GET['mois1'] : (isset($moisDisponibles[0]) ? $moisDisponibles[0] : '');
        $mois2 = isset($_GET['mois2']) ? $_GET['mois2'] : (isset($moisDisponibles[count($moisDisponibles)-1]) ? $moisDisponibles[count($moisDisponibles)-1] : '');
        
        // Données évolution globale
        $stmt = $pdo->query("SELECT DATE_FORMAT(mois, '%Y-%m') as m, SUM(qte_livree) as t FROM ventes_eclatees GROUP BY mois ORDER BY mois ASC");
        $evolutionGlobale = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Comparaison si les 2 mois sont sélectionnés
        if (!empty($mois1) && !empty($mois2)) {
            if ($mode == 'produits') {
                $sql = "
                    SELECT p.libelle, p.code_cip,
                           COALESCE(m1.qte, 0) as qte_m1,
                           COALESCE(m2.qte, 0) as qte_m2
                    FROM produits p
                    LEFT JOIN (
                        SELECT code_cip, SUM(qte_livree) as qte 
                        FROM ventes_eclatees 
                        WHERE DATE_FORMAT(mois, '%Y-%m') = :mois1 
                        GROUP BY code_cip
                    ) m1 ON p.code_cip = m1.code_cip
                    LEFT JOIN (
                        SELECT code_cip, SUM(qte_livree) as qte 
                        FROM ventes_eclatees 
                        WHERE DATE_FORMAT(mois, '%Y-%m') = :mois2 
                        GROUP BY code_cip
                    ) m2 ON p.code_cip = m2.code_cip
                    WHERE m1.qte > 0 OR m2.qte > 0
                    ORDER BY (COALESCE(m2.qte,0) - COALESCE(m1.qte,0)) DESC
                    LIMIT 50
                ";
            } else {
                $sql = "
                    SELECT c.designation, c.code_client, c.province,
                           COALESCE(m1.qte, 0) as qte_m1,
                           COALESCE(m2.qte, 0) as qte_m2
                    FROM clients c
                    LEFT JOIN (
                        SELECT code_client, SUM(qte_livree) as qte 
                        FROM ventes_eclatees 
                        WHERE DATE_FORMAT(mois, '%Y-%m') = :mois1 
                        GROUP BY code_client
                    ) m1 ON c.code_client = m1.code_client
                    LEFT JOIN (
                        SELECT code_client, SUM(qte_livree) as qte 
                        FROM ventes_eclatees 
                        WHERE DATE_FORMAT(mois, '%Y-%m') = :mois2 
                        GROUP BY code_client
                    ) m2 ON c.code_client = m2.code_client
                    WHERE m1.qte > 0 OR m2.qte > 0
                    ORDER BY (COALESCE(m2.qte,0) - COALESCE(m1.qte,0)) DESC
                    LIMIT 50
                ";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['mois1' => $mois1, 'mois2' => $mois2]);
            $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer les totaux
            foreach ($resultats as $r) {
                $totaux['m1'] += $r['qte_m1'];
                $totaux['m2'] += $r['qte_m2'];
            }
            $totaux['evolution'] = $totaux['m1'] > 0 ? round((($totaux['m2'] - $totaux['m1']) / $totaux['m1']) * 100, 1) : 0;
        }
    }
} catch(Exception $e) {
    $dataLoaded = false;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparaison - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .nav-link { color: white !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/inox-pharma-ventes/index.php">🏥 INOX PHARMA</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/inox-pharma-ventes/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inox-pharma-ventes/produits.php">Produits</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inox-pharma-ventes/clients.php">Clients</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/inox-pharma-ventes/comparaison.php">Comparaison</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inox-pharma-ventes/regions.php">Régions</a></li>
                    <li class="nav-item"><a class="nav-link" href="/inox-pharma-ventes/export.php">Export</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>📊 Comparaison de périodes</h3>

        <?php if (!$dataLoaded): ?>
            <div class="alert alert-warning">
                <h4>⚠️ Aucune donnée disponible</h4>
                <p>Importez d'abord vos données via <a href="/inox-pharma-ventes/import/import.php">la page d'importation</a>.</p>
            </div>
        <?php else: ?>

        <!-- Graphique évolution -->
        <?php if (!empty($evolutionGlobale)): ?>
        <div class="card mb-4">
            <div class="card-header"><h5>📈 Évolution globale</h5></div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="evoChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Période 1</label>
                        <select name="mois1" class="form-select">
                            <?php foreach ($moisDisponibles as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $mois1 ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 text-center"><h4 class="mt-4">VS</h4></div>
                    <div class="col-md-3">
                        <label class="form-label">Période 2</label>
                        <select name="mois2" class="form-select">
                            <?php foreach ($moisDisponibles as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $mois2 ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="mode" class="form-select">
                            <option value="produits" <?php echo $mode == 'produits' ? 'selected' : ''; ?>>Produits</option>
                            <option value="clients" <?php echo $mode == 'clients' ? 'selected' : ''; ?>>Clients</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">🔍 Comparer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Résultats -->
        <?php if (!empty($resultats)): ?>
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="card text-center"><div class="card-body">
                        <h6><?php echo $mois1; ?></h6>
                        <h2><?php echo number_format($totaux['m1']); ?></h2>
                    </div></div>
                </div>
                <div class="col-4">
                    <div class="card text-center <?php echo $totaux['evolution'] >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <div class="card-body">
                            <h6>Évolution</h6>
                            <h2><?php echo $totaux['evolution'] >= 0 ? '+' : ''; ?><?php echo $totaux['evolution']; ?>%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card text-center"><div class="card-body">
                        <h6><?php echo $mois2; ?></h6>
                        <h2><?php echo number_format($totaux['m2']); ?></h2>
                    </div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo $mode == 'produits' ? 'Produit' : 'Client'; ?></th>
                                    <th class="text-end"><?php echo $mois1; ?></th>
                                    <th class="text-end"><?php echo $mois2; ?></th>
                                    <th class="text-end">Écart</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultats as $r): 
                                    $ecart = $r['qte_m2'] - $r['qte_m1'];
                                    $pct = $r['qte_m1'] > 0 ? round(($ecart/$r['qte_m1'])*100,1) : ($r['qte_m2']>0?100:0);
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($mode == 'produits'): ?>
                                        <a href="/inox-pharma-ventes/produit_detail.php?cip=<?php echo $r['code_cip']; ?>"><?php echo htmlspecialchars($r['libelle']); ?></a>
                                        <?php else: ?>
                                        <a href="/inox-pharma-ventes/client_detail.php?client=<?php echo $r['code_client']; ?>"><?php echo htmlspecialchars($r['designation']); ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($r['qte_m1']); ?></td>
                                    <td class="text-end"><strong><?php echo number_format($r['qte_m2']); ?></strong></td>
                                    <td class="text-end <?php echo $ecart>=0?'text-success':'text-danger'; ?>"><?php echo $ecart>=0?'+':''; ?><?php echo $ecart; ?></td>
                                    <td class="text-end"><span class="badge bg-<?php echo $ecart>=0?'success':'danger'; ?>"><?php echo $pct>=0?'+':''; ?><?php echo $pct; ?>%</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <?php if (!empty($evolutionGlobale)): ?>
    <script>
        var labels = [];
        var data = [];
        <?php foreach ($evolutionGlobale as $e): ?>
        labels.push('<?php echo $e['m']; ?>');
        data.push(<?php echo $e['t']; ?>);
        <?php endforeach; ?>
        
        new Chart(document.getElementById('evoChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventes totales',
                    data: data,
                    borderColor: '#1a73e8',
                    backgroundColor: 'rgba(26,115,232,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>