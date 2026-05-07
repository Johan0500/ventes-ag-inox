<?php
$pageTitle = "Ventes par Région";
require_once __DIR__ . '/config/database.php';

// Ventes par province
$provinces = $pdo->query("
    SELECT c.province, c.agence,
           SUM(v.qte_livree) as total,
           COUNT(DISTINCT v.code_client) as nb_clients,
           COUNT(DISTINCT v.code_cip) as nb_produits
    FROM ventes_eclatees v
    JOIN clients c ON v.code_client = c.code_client
    GROUP BY c.province, c.agence
    ORDER BY total DESC
")->fetchAll();

// Ventes par agence (regroupées)
$agences = $pdo->query("
    SELECT c.agence,
           SUM(v.qte_livree) as total,
           COUNT(DISTINCT v.code_client) as nb_clients,
           COUNT(DISTINCT v.code_cip) as nb_produits,
           GROUP_CONCAT(DISTINCT c.province ORDER BY c.province SEPARATOR ', ') as provinces
    FROM ventes_eclatees v
    JOIN clients c ON v.code_client = c.code_client
    GROUP BY c.agence
    ORDER BY total DESC
")->fetchAll();

$maxTotal = max(array_column($agences, 'total'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .nav-link { color: white !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; margin: 0 2px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .chart-container { position: relative; height: 400px; }
        .region-card { transition: transform 0.3s; }
        .region-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏥 INOX PHARMA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="produits.php"><i class="bi bi-box"></i> Produits</a></li>
                    <li class="nav-item"><a class="nav-link" href="clients.php"><i class="bi bi-shop"></i> Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="comparaison.php"><i class="bi bi-bar-chart"></i> Comparaison</a></li>
                    <li class="nav-item"><a class="nav-link active" href="regions.php"><i class="bi bi-map"></i> Régions</a></li>
                    <li class="nav-item"><a class="nav-link" href="export.php"><i class="bi bi-download"></i> Export</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3><i class="bi bi-map"></i> Ventes par Région et Agence</h3>
        
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5>📊 Répartition par Agence</h5></div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="agencesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5>🏢 Top Agences</h5></div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($agences as $ag): 
                            $pct = $maxTotal > 0 ? ($ag['total'] / $maxTotal) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($ag['agence']); ?></strong>
                                <span><?php echo number_format($ag['total']); ?> unités</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo $pct; ?>%">
                                    <small><?php echo $ag['nb_clients']; ?> clients | <?php echo $ag['nb_produits']; ?> produits</small>
                                </div>
                            </div>
                            <small class="text-muted">Provinces : <?php echo htmlspecialchars($ag['provinces']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5>📍 Détail par Province</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Agence</th>
                                <th>Province</th>
                                <th class="text-end">Total Ventes</th>
                                <th class="text-center">Clients</th>
                                <th class="text-center">Produits</th>
                                <th>Part de marché</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalGlobal = array_sum(array_column($provinces, 'total'));
                            foreach ($provinces as $p): 
                                $part = $totalGlobal > 0 ? ($p['total'] / $totalGlobal) * 100 : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['agence']); ?></td>
                                <td><?php echo htmlspecialchars($p['province']); ?></td>
                                <td class="text-end"><strong><?php echo number_format($p['total']); ?></strong></td>
                                <td class="text-center"><?php echo $p['nb_clients']; ?></td>
                                <td class="text-center"><?php echo $p['nb_produits']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo $part; ?>%"></div>
                                        </div>
                                        <small><?php echo round($part, 1); ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('agencesChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($agences, 'agence')) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($agences, 'total')); ?>],
                    backgroundColor: [
                        '#1a73e8', '#0d904f', '#f9ab00', '#ea4335', '#9334e6',
                        '#07a0c3', '#f25c54', '#7b2d8e', '#2d6a4f', '#b7b7a4'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>