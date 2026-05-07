<?php
$pageTitle = "Recherche Clients";
require_once __DIR__ . '/config/database.php';

$search = $_GET['search'] ?? '';
$all = isset($_GET['all']);
$clients = [];
$totalClients = 0;

try {
    $totalClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    
    if ($search || $all) {
        $sql = "
            SELECT c.*, 
                   COALESCE(SUM(v.qte_livree), 0) as total_achete,
                   COUNT(DISTINCT v.code_cip) as nb_produits,
                   MAX(v.mois) as dernier_achat
            FROM clients c
            LEFT JOIN ventes_eclatees v ON c.code_client = v.code_client
        ";
        
        if ($search) {
            $sql .= " WHERE c.designation LIKE :search OR c.code_client LIKE :search2 OR c.province LIKE :search3";
        }
        
        $sql .= " GROUP BY c.code_client ORDER BY total_achete DESC LIMIT 200";
        
        $stmt = $pdo->prepare($sql);
        if ($search) {
            $stmt->execute(['search' => "%$search%", 'search2' => "%$search%", 'search3' => "%$search%"]);
        } else {
            $stmt->execute();
        }
        $clients = $stmt->fetchAll();
    }
} catch(Exception $e) {}
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
                    <li class="nav-item"><a class="nav-link" href="produits.php"><i class="bi bi-box"></i> Produits</a></li>
                    <li class="nav-item"><a class="nav-link active" href="clients.php"><i class="bi bi-shop"></i> Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="comparaison.php"><i class="bi bi-bar-chart"></i> Comparaison</a></li>
                    <li class="nav-item"><a class="nav-link" href="regions.php"><i class="bi bi-map"></i> Régions</a></li>
                    <li class="nav-item"><a class="nav-link" href="export.php"><i class="bi bi-download"></i> Export</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-shop"></i> Clients <small class="text-muted">(<?php echo $totalClients; ?> au total)</small></h3>
            <a href="clients.php?all=1" class="btn btn-outline-primary">📋 Voir tous les clients</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-10">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Rechercher par nom, code client ou province..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100">Rechercher</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($search || $all): ?>
            <div class="alert alert-info">
                <?php echo count($clients); ?> client(s) trouvé(s)
            </div>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Client</th>
                                    <th>Province</th>
                                    <th>Agence</th>
                                    <th class="text-end">Total Acheté</th>
                                    <th class="text-center">Nb Produits</th>
                                    <th>Dernier Achat</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($c['code_client']); ?></code></td>
                                    <td><?php echo htmlspecialchars($c['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($c['province']); ?></td>
                                    <td><?php echo htmlspecialchars($c['agence']); ?></td>
                                    <td class="text-end"><strong><?php echo number_format($c['total_achete']); ?></strong></td>
                                    <td class="text-center"><?php echo $c['nb_produits']; ?></td>
                                    <td><?php echo $c['dernier_achat'] ? date('d/m/Y', strtotime($c['dernier_achat'])) : '-'; ?></td>
                                    <td>
                                        <a href="client_detail.php?client=<?php echo $c['code_client']; ?>" 
                                           class="btn btn-sm btn-outline-primary">📊 Détail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>