<?php
$pageTitle = "Recherche Produits";
require_once __DIR__ . '/config/database.php';

$search = $_GET['search'] ?? '';
$all = isset($_GET['all']);
$produits = [];
$totalProduits = 0;

try {
    $totalProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    
    if ($search || $all) {
        $sql = "
            SELECT p.*, 
                   COALESCE(SUM(v.qte_livree), 0) as total_vendus,
                   COUNT(DISTINCT v.code_client) as nb_clients,
                   COUNT(DISTINCT DATE_FORMAT(v.mois, '%Y-%m')) as nb_mois_ventes
            FROM produits p
            LEFT JOIN ventes_eclatees v ON p.code_cip = v.code_cip
        ";
        
        if ($search) {
            $sql .= " WHERE p.libelle LIKE :search OR p.code_cip LIKE :search2";
        }
        
        $sql .= " GROUP BY p.code_cip ORDER BY total_vendus DESC LIMIT 200";
        
        $stmt = $pdo->prepare($sql);
        if ($search) {
            $stmt->execute(['search' => "%$search%", 'search2' => "%$search%"]);
        } else {
            $stmt->execute();
        }
        $produits = $stmt->fetchAll();
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .nav-link { color: white !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; margin: 0 2px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
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
                    <li class="nav-item"><a class="nav-link active" href="produits.php"><i class="bi bi-box"></i> Produits</a></li>
                    <li class="nav-item"><a class="nav-link" href="clients.php"><i class="bi bi-shop"></i> Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="comparaison.php"><i class="bi bi-bar-chart"></i> Comparaison</a></li>
                    <li class="nav-item"><a class="nav-link" href="regions.php"><i class="bi bi-map"></i> Régions</a></li>
                    <li class="nav-item"><a class="nav-link" href="export.php"><i class="bi bi-download"></i> Export</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-box"></i> Produits <small class="text-muted">(<?php echo $totalProduits; ?> au total)</small></h3>
            <a href="produits.php?all=1" class="btn btn-outline-primary">📋 Voir tous les produits</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-10">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Rechercher par nom ou code CIP..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($search || $all): ?>
            <div class="alert alert-info">
                <?php echo count($produits); ?> produit(s) trouvé(s)
                <?php if ($search): ?>
                    pour "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code CIP</th>
                                    <th>Libellé</th>
                                    <th class="text-end">Prix Cession</th>
                                    <th class="text-end">Prix Public</th>
                                    <th class="text-center">Total Vendus</th>
                                    <th class="text-center">Clients</th>
                                    <th class="text-center">Mois d'activité</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $p): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($p['code_cip']); ?></code></td>
                                    <td><?php echo htmlspecialchars($p['libelle']); ?></td>
                                    <td class="text-end"><?php echo number_format($p['prix_cession'], 0, ',', ' '); ?> F</td>
                                    <td class="text-end"><?php echo number_format($p['prix_public'], 0, ',', ' '); ?> F</td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($p['total_vendus']); ?></strong>
                                    </td>
                                    <td class="text-center"><?php echo $p['nb_clients']; ?></td>
                                    <td class="text-center"><?php echo $p['nb_mois_ventes']; ?></td>
                                    <td>
                                        <a href="produit_detail.php?cip=<?php echo $p['code_cip']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            📊 Détail
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif (!$search && !$all): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">Recherchez un produit</h4>
                    <p class="text-muted">Utilisez la barre de recherche ci-dessus ou cliquez sur "Voir tous les produits"</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>