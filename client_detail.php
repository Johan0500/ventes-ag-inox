<?php
$pageTitle = "Détail Client";
require_once __DIR__ . '/config/database.php';

$codeClient = $_GET['client'] ?? '';

if (!$codeClient) {
    header('Location: clients.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE code_client = ?");
$stmt->execute([$codeClient]);
$client = $stmt->fetch();

if (!$client) {
    echo "<script>alert('Client non trouvé'); window.location.href='clients.php';</script>";
    exit;
}

// Achats par mois
$achatsMois = $pdo->prepare("
    SELECT DATE_FORMAT(mois, '%Y-%m') as mois, SUM(qte_livree) as total
    FROM ventes_eclatees WHERE code_client = ?
    GROUP BY mois ORDER BY mois ASC
");
$achatsMois->execute([$codeClient]);
$am = $achatsMois->fetchAll();

// Top produits achetés
$topProduits = $pdo->prepare("
    SELECT p.libelle, p.code_cip, SUM(v.qte_livree) as total
    FROM ventes_eclatees v
    JOIN produits p ON v.code_cip = p.code_cip
    WHERE v.code_client = ?
    GROUP BY v.code_cip ORDER BY total DESC LIMIT 20
");
$topProduits->execute([$codeClient]);
$produits = $topProduits->fetchAll();

// Dernières ventes
$dernieresVentes = $pdo->prepare("
    SELECT v.mois, p.libelle, v.qte_livree, p.prix_cession
    FROM ventes_eclatees v
    JOIN produits p ON v.code_cip = p.code_cip
    WHERE v.code_client = ?
    ORDER BY v.mois DESC, v.qte_livree DESC
    LIMIT 50
");
$dernieresVentes->execute([$codeClient]);
$ventes = $dernieresVentes->fetchAll();

$totalAchete = array_sum(array_column($am, 'total'));
$totalCA = 0;
foreach ($ventes as $v) {
    $totalCA += $v['qte_livree'] * $v['prix_cession'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($client['designation']); ?> - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .nav-link { color: white !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; margin: 0 2px; }
        .nav-link:hover { background: rgba(255,255,255,0.2); }
        .chart-container { position: relative; height: 350px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏥 INOX PHARMA</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="produits.php"><i class="bi bi-box"></i> Produits</a></li>
                    <li class="nav-item"><a class="nav-link" href="clients.php"><i class="bi bi-shop"></i> Clients</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item"><a href="clients.php">Clients</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($client['designation']); ?></li>
            </ol>
        </nav>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h1><?php echo number_format($totalAchete); ?></h1>
                        <p>Unités achetées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h1><?php echo count($produits); ?></h1>
                        <p>Produits différents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h1><?php echo count($am); ?></h1>
                        <p>Mois d'activité</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5>🏪 <?php echo htmlspecialchars($client['designation']); ?></h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Code Client :</strong> <?php echo htmlspecialchars($client['code_client']); ?></p>
                        <p><strong>Province :</strong> <?php echo htmlspecialchars($client['province']); ?></p>
                        <p><strong>Agence :</strong> <?php echo htmlspecialchars($client['agence']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total commandé :</strong> <?php echo number_format($totalAchete); ?> unités</p>
                        <p><strong>CA estimé :</strong> <?php echo number_format($totalCA, 0, ',', ' '); ?> F CFA</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>📈 Évolution des achats</h5></div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="clientChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>📦 Top Produits Achetés</h5></div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <ol class="list-group list-group-numbered">
                            <?php foreach ($produits as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <a href="produit_detail.php?cip=<?php echo $p['code_cip']; ?>" class="text-decoration-none small">
                                        <?php echo htmlspecialchars($p['libelle']); ?>
                                    </a>
                                </div>
                                <span class="badge bg-primary"><?php echo number_format($p['total']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5>📋 Dernières ventes</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th class="text-end">Quantité</th>
                                <th class="text-end">Prix Unit.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($ventes, 0, 50) as $v): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($v['mois'])); ?></td>
                                <td><?php echo htmlspecialchars($v['libelle']); ?></td>
                                <td class="text-end"><?php echo $v['qte_livree']; ?></td>
                                <td class="text-end"><?php echo number_format($v['prix_cession'], 0, ',', ' '); ?> F</td>
                                <td class="text-end"><strong><?php echo number_format($v['qte_livree'] * $v['prix_cession'], 0, ',', ' '); ?> F</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('clientChart'), {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($am, 'mois')) . "'"; ?>],
                datasets: [{
                    label: 'Quantités achetées',
                    data: [<?php echo implode(',', array_column($am, 'total')); ?>],
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>