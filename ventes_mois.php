<?php
$pageTitle = "Ventes par Mois";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

// Récupérer tous les mois disponibles
$moisDisponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);

// Mois sélectionné (par défaut le plus récent)
$moisSelectionne = $_GET['mois'] ?? ($moisDisponibles[0] ?? '');
$recherche = $_GET['recherche'] ?? '';
$tri = $_GET['tri'] ?? 'qte_desc';
$page = max(1, intval($_GET['page'] ?? 1));
$parPage = 50;

$ventes = [];
$totalVentes = 0;
$stats = ['total_unites' => 0, 'total_clients' => 0, 'total_produits' => 0, 'ca_total' => 0];
$agences_stats = [];
$produits_stats = [];

if ($moisSelectionne) {
    // Filtre de recherche
    $whereSQL = "WHERE DATE_FORMAT(v.mois, '%Y-%m') = :mois";
    $params = ['mois' => $moisSelectionne];
    
    if (!empty($recherche)) {
        $whereSQL .= " AND (p.libelle LIKE :r OR p.code_cip LIKE :r2 OR c.designation LIKE :r3 OR c.province LIKE :r4 OR c.agence LIKE :r5)";
        $params['r'] = "%$recherche%";
        $params['r2'] = "%$recherche%";
        $params['r3'] = "%$recherche%";
        $params['r4'] = "%$recherche%";
        $params['r5'] = "%$recherche%";
    }
    
    // Tri
    $orderSQL = "ORDER BY v.qte_livree DESC";
    switch ($tri) {
        case 'qte_asc': $orderSQL = "ORDER BY v.qte_livree ASC"; break;
        case 'qte_desc': $orderSQL = "ORDER BY v.qte_livree DESC"; break;
        case 'produit_asc': $orderSQL = "ORDER BY p.libelle ASC"; break;
        case 'produit_desc': $orderSQL = "ORDER BY p.libelle DESC"; break;
        case 'client_asc': $orderSQL = "ORDER BY c.designation ASC"; break;
        case 'client_desc': $orderSQL = "ORDER BY c.designation DESC"; break;
        case 'montant_desc': $orderSQL = "ORDER BY (v.qte_livree * p.prix_cession) DESC"; break;
        case 'montant_asc': $orderSQL = "ORDER BY (v.qte_livree * p.prix_cession) ASC"; break;
        case 'province': $orderSQL = "ORDER BY c.province ASC, v.qte_livree DESC"; break;
        case 'agence': $orderSQL = "ORDER BY c.agence ASC, v.qte_livree DESC"; break;
    }
    
    // Compter le total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes_eclatees v JOIN produits p ON v.code_cip=p.code_cip JOIN clients c ON v.code_client=c.code_client $whereSQL");
    $stmt->execute($params);
    $totalVentes = $stmt->fetchColumn();
    
    // Récupérer les ventes avec pagination
    $offset = ($page - 1) * $parPage;
    $stmt = $pdo->prepare("
        SELECT v.*, p.libelle as produit, p.prix_cession, p.prix_public,
               c.designation as client, c.province, c.agence,
               (v.qte_livree * p.prix_cession) as montant
        FROM ventes_eclatees v 
        JOIN produits p ON v.code_cip = p.code_cip 
        JOIN clients c ON v.code_client = c.code_client 
        $whereSQL 
        $orderSQL 
        LIMIT $parPage OFFSET $offset
    ");
    $stmt->execute($params);
    $ventes = $stmt->fetchAll();
    
    // Statistiques du mois
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(v.qte_livree), 0) as total_unites,
            COUNT(DISTINCT v.code_client) as total_clients,
            COUNT(DISTINCT v.code_cip) as total_produits,
            COALESCE(SUM(v.qte_livree * p.prix_cession), 0) as ca_total
        FROM ventes_eclatees v
        JOIN produits p ON v.code_cip = p.code_cip
        WHERE DATE_FORMAT(v.mois, '%Y-%m') = :mois
    ");
    $stmt->execute(['mois' => $moisSelectionne]);
    $stats = $stmt->fetch();
    
    // Par agence
    $stmt = $pdo->prepare("
        SELECT c.agence, 
               COUNT(*) as nb_ventes,
               SUM(v.qte_livree) as total_qte,
               SUM(v.qte_livree * p.prix_cession) as ca,
               COUNT(DISTINCT v.code_client) as clients,
               COUNT(DISTINCT v.code_cip) as produits
        FROM ventes_eclatees v
        JOIN clients c ON v.code_client = c.code_client
        JOIN produits p ON v.code_cip = p.code_cip
        WHERE DATE_FORMAT(v.mois, '%Y-%m') = :mois
        GROUP BY c.agence
        ORDER BY total_qte DESC
    ");
    $stmt->execute(['mois' => $moisSelectionne]);
    $agences_stats = $stmt->fetchAll();
    
    // Top produits du mois
    $stmt = $pdo->prepare("
        SELECT p.libelle, p.code_cip,
               SUM(v.qte_livree) as total_qte,
               SUM(v.qte_livree * p.prix_cession) as ca,
               COUNT(DISTINCT v.code_client) as clients
        FROM ventes_eclatees v
        JOIN produits p ON v.code_cip = p.code_cip
        WHERE DATE_FORMAT(v.mois, '%Y-%m') = :mois
        GROUP BY v.code_cip
        ORDER BY total_qte DESC
        LIMIT 10
    ");
    $stmt->execute(['mois' => $moisSelectionne]);
    $produits_stats = $stmt->fetchAll();
}

$totalPages = ceil($totalVentes / $parPage);
?>

<style>
.mois-selector .btn { border-radius: 20px; margin: 2px; }
.mois-selector .btn.active { background: #1a73e8; color: white; font-weight: bold; }
.stat-badge { 
    display: inline-block; 
    padding: 1rem 1.5rem; 
    border-radius: 12px; 
    text-align: center; 
    min-width: 120px;
}
.stat-badge .big { font-size: 1.5rem; font-weight: 700; }
.stat-badge .small { font-size: 0.75rem; text-transform: uppercase; opacity: 0.8; }
.table-fixed-header { max-height: 600px; overflow-y: auto; }
.table-fixed-header thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
.highlight-row:hover { background-color: #f0f4ff !important; }
</style>

<h3>📋 Données éclatées par mois</h3>

<!-- Sélecteur de mois -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">📅 Sélectionnez un mois</h5>
            <a href="ajout_mois.php" class="btn btn-sm btn-outline-success">➕ Ajouter un mois</a>
        </div>
        <div class="mois-selector">
            <?php foreach ($moisDisponibles as $m): 
                $date = DateTime::createFromFormat('Y-m', $m);
                $label = $date ? $date->format('F Y') : $m;
            ?>
            <a href="?mois=<?php echo $m; ?><?php echo $recherche?'&recherche='.urlencode($recherche):''; ?>" 
               class="btn btn-outline-primary btn-sm <?php echo $m==$moisSelectionne?'active':''; ?>">
                <?php echo ucfirst($label); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($moisSelectionne): ?>

<!-- Statistiques du mois -->
<div class="row g-2 mb-4">
    <div class="col-md-3">
        <div class="stat-badge bg-primary text-white w-100">
            <div class="big"><?php echo number_format($stats['total_unites']); ?></div>
            <div class="small">Unités vendues</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-badge bg-success text-white w-100">
            <div class="big"><?php echo number_format($stats['ca_total'], 0, ',', ' '); ?> F</div>
            <div class="small">Chiffre d'affaires</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-badge bg-info text-white w-100">
            <div class="big"><?php echo $stats['total_clients']; ?></div>
            <div class="small">Clients actifs</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-badge bg-warning text-white w-100">
            <div class="big"><?php echo $stats['total_produits']; ?></div>
            <div class="small">Produits vendus</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-badge bg-danger text-white w-100">
            <div class="big"><?php echo number_format($totalVentes); ?></div>
            <div class="small">Lignes de vente</div>
        </div>
    </div>
</div>

<!-- Par agence -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h6>🏢 Répartition par Agence - <?php echo $moisSelectionne; ?></h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Agence</th><th class="text-end">Qté</th><th class="text-end">CA</th><th class="text-center">Clients</th><th class="text-center">Produits</th><th>Performance</th></tr>
                        </thead>
                        <tbody>
                            <?php $maxAg = max(array_column($agences_stats, 'total_qte')) ?: 1;
                            foreach ($agences_stats as $ag): $pct = ($ag['total_qte']/$maxAg)*100; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ag['agence']); ?></strong></td>
                                <td class="text-end"><?php echo number_format($ag['total_qte']); ?></td>
                                <td class="text-end"><?php echo number_format($ag['ca'], 0, ',', ' '); ?> F</td>
                                <td class="text-center"><?php echo $ag['clients']; ?></td>
                                <td class="text-center"><?php echo $ag['produits']; ?></td>
                                <td>
                                    <div class="progress" style="height:6px;"><div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6>🏆 Top 10 Produits du mois</h6></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($produits_stats as $i => $ps): ?>
                    <a href="produit_detail.php?cip=<?php echo $ps['code_cip']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-<?php echo ['warning','secondary','danger'][$i]??'primary'; ?> me-2"><?php echo $i+1; ?></span>
                            <span class="small"><?php echo htmlspecialchars($ps['libelle']); ?></span>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo number_format($ps['total_qte']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recherche et tri -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="mois" value="<?php echo $moisSelectionne; ?>">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">🔍</span>
                    <input type="text" name="recherche" class="form-control" placeholder="Rechercher produit, client, province..." value="<?php echo htmlspecialchars($recherche); ?>">
                    <?php if ($recherche): ?><a href="?mois=<?php echo $moisSelectionne; ?>" class="btn btn-outline-secondary">✕</a><?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <select name="tri" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="qte_desc" <?php echo $tri=='qte_desc'?'selected':''; ?>>Quantité ↓</option>
                    <option value="qte_asc" <?php echo $tri=='qte_asc'?'selected':''; ?>>Quantité ↑</option>
                    <option value="montant_desc" <?php echo $tri=='montant_desc'?'selected':''; ?>>Montant ↓</option>
                    <option value="montant_asc" <?php echo $tri=='montant_asc'?'selected':''; ?>>Montant ↑</option>
                    <option value="produit_asc" <?php echo $tri=='produit_asc'?'selected':''; ?>>Produit A-Z</option>
                    <option value="produit_desc" <?php echo $tri=='produit_desc'?'selected':''; ?>>Produit Z-A</option>
                    <option value="client_asc" <?php echo $tri=='client_asc'?'selected':''; ?>>Client A-Z</option>
                    <option value="client_desc" <?php echo $tri=='client_desc'?'selected':''; ?>>Client Z-A</option>
                    <option value="province" <?php echo $tri=='province'?'selected':''; ?>>Par Province</option>
                    <option value="agence" <?php echo $tri=='agence'?'selected':''; ?>>Par Agence</option>
                </select>
            </div>
            <div class="col-md-3">
                <span class="text-muted small"><?php echo number_format($totalVentes); ?> lignes trouvées</span>
            </div>
            <div class="col-md-2 text-end">
                <a href="export.php?format=excel&type=ventes_mois&mois=<?php echo $moisSelectionne; ?>" class="btn btn-sm btn-outline-success">📥 Export Excel</a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau des ventes -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive table-fixed-header">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Produit</th>
                        <th>Code CIP</th>
                        <th>Client</th>
                        <th>Province</th>
                        <th>Agence</th>
                        <th class="text-end">Qté</th>
                        <th class="text-end">Prix Unit.</th>
                        <th class="text-end">Montant</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventes)): ?>
                    <tr><td colspan="10" class="text-center py-4 text-muted">Aucune vente trouvée pour ce mois</td></tr>
                    <?php else: ?>
                    <?php foreach ($ventes as $i => $v): 
                        $numLigne = ($page - 1) * $parPage + $i + 1;
                    ?>
                    <tr class="highlight-row">
                        <td><small class="text-muted"><?php echo $numLigne; ?></small></td>
                        <td>
                            <a href="produit_detail.php?cip=<?php echo $v['code_cip']; ?>" class="text-decoration-none small fw-bold">
                                <?php echo htmlspecialchars($v['produit']); ?>
                            </a>
                        </td>
                        <td><code class="small"><?php echo htmlspecialchars($v['code_cip']); ?></code></td>
                        <td>
                            <a href="client_detail.php?client=<?php echo $v['code_client']; ?>" class="text-decoration-none small">
                                <?php echo htmlspecialchars($v['client']); ?>
                            </a>
                        </td>
                        <td><small><?php echo htmlspecialchars($v['province']); ?></small></td>
                        <td><small><?php echo htmlspecialchars($v['agence']); ?></small></td>
                        <td class="text-end"><strong><?php echo number_format($v['qte_livree']); ?></strong></td>
                        <td class="text-end"><small><?php echo number_format($v['prix_cession'], 0, ',', ' '); ?> F</small></td>
                        <td class="text-end"><strong><?php echo number_format($v['montant'], 0, ',', ' '); ?> F</strong></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="produit_detail.php?cip=<?php echo $v['code_cip']; ?>" class="btn btn-outline-primary btn-sm" title="Voir produit">📦</a>
                                <a href="client_detail.php?client=<?php echo $v['code_client']; ?>" class="btn btn-outline-success btn-sm" title="Voir client">🏪</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?mois=<?php echo $moisSelectionne; ?>&page=<?php echo $page-1; ?>&tri=<?php echo $tri; ?>&recherche=<?php echo urlencode($recherche); ?>">← Précédent</a></li>
        <?php endif; ?>
        
        <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
            <a class="page-link" href="?mois=<?php echo $moisSelectionne; ?>&page=<?php echo $p; ?>&tri=<?php echo $tri; ?>&recherche=<?php echo urlencode($recherche); ?>"><?php echo $p; ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <li class="page-item"><a class="page-link" href="?mois=<?php echo $moisSelectionne; ?>&page=<?php echo $page+1; ?>&tri=<?php echo $tri; ?>&recherche=<?php echo urlencode($recherche); ?>">Suivant →</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="text-center py-5">
    <div style="font-size:5rem;">📅</div>
    <h4>Sélectionnez un mois ci-dessus</h4>
    <p class="text-muted">Choisissez un mois pour voir toutes les ventes éclatées</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>