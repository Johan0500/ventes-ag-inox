<?php
$pageTitle = "Détail Produit";
require_once __DIR__ . '/config/database.php';

$cip = $_GET['cip'] ?? '';

if (!$cip) {
    header('Location: produits.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM produits WHERE code_cip = ?");
$stmt->execute([$cip]);
$produit = $stmt->fetch();

if (!$produit) {
    echo "<script>alert('Produit non trouvé'); window.location.href='produits.php';</script>";
    exit;
}

// Ventes par mois
$ventesMois = $pdo->prepare("
    SELECT DATE_FORMAT(mois, '%Y-%m') as mois, SUM(qte_livree) as total
    FROM ventes_eclatees WHERE code_cip = ?
    GROUP BY mois ORDER BY mois ASC
");
$ventesMois->execute([$cip]);
$vm = $ventesMois->fetchAll();

// Top clients
$topClients = $pdo->prepare("
    SELECT c.designation, c.code_client, c.province, SUM(v.qte_livree) as total
    FROM ventes_eclatees v
    JOIN clients c ON v.code_client = c.code_client
    WHERE v.code_cip = ?
    GROUP BY v.code_client ORDER BY total DESC LIMIT 20
");
$topClients->execute([$cip]);
$clients = $topClients->fetchAll();

// Ventes par agence
$ventesAgence = $pdo->prepare("
    SELECT c.agence, SUM(v.qte_livree) as total
    FROM ventes_eclatees v
    JOIN clients c ON v.code_client = c.code_client
    WHERE v.code_cip = ?
    GROUP BY c.agence ORDER BY total DESC
");
$ventesAgence->execute([$cip]);
$agences = $ventesAgence->fetchAll();

$totalVendu = array_sum(array_column($vm, 'total'));
$nbClientsTotal = count($clients);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($produit['libelle']); ?> - Inox Pharma</title>
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
                <li class="breadcrumb-item"><a href="produits.php">Produits</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($produit['libelle']); ?></li>
            </ol>
        </nav>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h1><?php echo number_format($totalVendu); ?></h1>
                        <p>Unités vendues</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h1><?php echo $nbClientsTotal; ?></h1>
                        <p>Clients</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h1><?php echo count($vm); ?></h1>
                        <p>Mois d'activité</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5>📦 <?php echo htmlspecialchars($produit['libelle']); ?></h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Code CIP :</strong> <?php echo htmlspecialchars($produit['code_cip']); ?></p>
                        <p><strong>Prix Cession :</strong> <?php echo number_format($produit['prix_cession'], 0, ',', ' '); ?> F CFA</p>
                        <p><strong>Prix Public :</strong> <?php echo number_format($produit['prix_public'], 0, ',', ' '); ?> F CFA</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>CA estimé :</strong> <?php echo number_format($totalVendu * $produit['prix_cession'], 0, ',', ' '); ?> F CFA</p>
                        <p><strong>Marge totale :</strong> <?php echo number_format($totalVendu * ($produit['prix_public'] - $produit['prix_cession']), 0, ',', ' '); ?> F CFA</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>📈 Évolution des ventes</h5></div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="produitChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>🏪 Top Clients</h5></div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <ol class="list-group list-group-numbered">
                            <?php foreach ($clients as $c): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <a href="client_detail.php?client=<?php echo $c['code_client']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($c['designation']); ?>
                                    </a>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($c['province']); ?></small>
                                </div>
                                <span class="badge bg-primary"><?php echo number_format($c['total']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5>🏢 Ventes par Agence</h5></div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($agences as $ag): ?>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 text-center">
                            <h6><?php echo htmlspecialchars($ag['agence']); ?></h6>
                            <h4 class="text-primary"><?php echo number_format($ag['total']); ?></h4>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('produitChart'), {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($vm, 'mois')) . "'"; ?>],
                datasets: [{
                    label: 'Quantités vendues',
                    data: [<?php echo implode(',', array_column($vm, 'total')); ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
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