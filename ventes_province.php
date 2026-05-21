<?php
$pageTitle = "Ventes par Province";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

// Récupérer TOUS les produits groupés par nom normalisé
$allProduits = $pdo->query("
    SELECT 
        p.libelle,
        GROUP_CONCAT(DISTINCT p.code_cip ORDER BY p.code_cip SEPARATOR '||') as codes_cip,
        COALESCE(SUM(v.qte_livree), 0) as total_vendu,
        COUNT(DISTINCT CASE WHEN v.grossiste_code IS NOT NULL AND v.grossiste_code != '' THEN v.grossiste_code END) as nb_grossistes,
        COUNT(DISTINCT c.province) as nb_provinces
    FROM produits p
    LEFT JOIN ventes_eclatees v ON p.code_cip = v.code_cip
    LEFT JOIN clients c ON v.code_client = c.code_client
    WHERE v.qte_livree > 0 OR v.qte_livree IS NULL
    GROUP BY p.libelle
    ORDER BY total_vendu DESC
")->fetchAll();

// Recherche
$search = trim($_GET['search'] ?? '');
$produitNom = $_GET['nom'] ?? '';
$moisFiltre = $_GET['mois'] ?? 'all';
$grossisteFiltre = $_GET['grossiste'] ?? 'all';
$cipFiltre = $_GET['cip'] ?? ''; // Code CIP spécifique

// Filtrer par recherche
if (!empty($search)) {
    $filtered = array_filter($allProduits, function($p) use ($search) {
        $codes = explode('||', $p['codes_cip']);
        foreach ($codes as $code) {
            if (stripos($code, $search) !== false) return true;
        }
        return stripos($p['libelle'], $search) !== false;
    });
    $allProduits = array_values($filtered);
    
    if (count($allProduits) === 1) {
        $produitNom = $allProduits[0]['libelle'];
    }
}

// Mois et grossistes
$moisDisponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois,'%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
$grossistes = $pdo->query("SELECT code, nom FROM grossistes WHERE actif=1")->fetchAll();

// Données du produit sélectionné
$ventesParProvince = [];
$totalProduit = 0;
$codesCIP = [];
$grossistesDuProduit = [];
$ventesParCIP = [];

if ($produitNom) {
    // Récupérer tous les codes CIP pour ce produit
    $stmt = $pdo->prepare("SELECT DISTINCT code_cip FROM produits WHERE libelle = ?");
    $stmt->execute([$produitNom]);
    $codesCIP = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($codesCIP)) {
        // Si un CIP spécifique est filtré
        $cipCondition = '';
        $cipParams = [];
        if ($cipFiltre && in_array($cipFiltre, $codesCIP)) {
            $cipCondition = " AND v.code_cip = :cip";
            $cipParams['cip'] = $cipFiltre;
            $codesCIP = [$cipFiltre];
        }
        
        // Ventes par province ET grossiste ET code CIP (vraies valeurs, pas divisées !)
        $sql = "SELECT 
                    v.code_cip,
                    p.libelle,
                    c.province, 
                    c.agence, 
                    v.grossiste_code, 
                    SUM(v.qte_livree) as total,
                    COUNT(DISTINCT v.code_client) as nb_clients
                FROM ventes_eclatees v
                JOIN clients c ON v.code_client = c.code_client
                JOIN produits p ON v.code_cip = p.code_cip
                WHERE p.libelle = :nom";
        $params = ['nom' => $produitNom];
        
        if ($cipCondition) {
            $sql .= $cipCondition;
            $params = array_merge($params, $cipParams);
        }
        if ($moisFiltre !== 'all') {
            $sql .= " AND DATE_FORMAT(v.mois, '%Y-%m') = :mois";
            $params['mois'] = $moisFiltre;
        }
        if ($grossisteFiltre !== 'all') {
            $sql .= " AND v.grossiste_code = :gross";
            $params['gross'] = $grossisteFiltre;
        }
        
        $sql .= " GROUP BY v.code_cip, p.libelle, c.province, c.agence, v.grossiste_code ORDER BY total DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ventesParProvince = $stmt->fetchAll();
        $totalProduit = array_sum(array_column($ventesParProvince, 'total'));
        
        // Grossistes qui vendent ce produit (avec leurs codes CIP)
        $stmt = $pdo->prepare("
            SELECT v.grossiste_code, v.code_cip, SUM(v.qte_livree) as total
            FROM ventes_eclatees v
            JOIN produits p ON v.code_cip = p.code_cip
            WHERE p.libelle = ?
            GROUP BY v.grossiste_code, v.code_cip
            ORDER BY total DESC
        ");
        $stmt->execute([$produitNom]);
        $grossistesDuProduit = $stmt->fetchAll();
        
        // Ventes par code CIP
        $stmt = $pdo->prepare("
            SELECT v.code_cip, v.grossiste_code, SUM(v.qte_livree) as total
            FROM ventes_eclatees v
            JOIN produits p ON v.code_cip = p.code_cip
            WHERE p.libelle = ?
            GROUP BY v.code_cip, v.grossiste_code
            ORDER BY total DESC
        ");
        $stmt->execute([$produitNom]);
        $ventesParCIP = $stmt->fetchAll();
    }
}

$grossNames = ['tedis'=>'TEDIS', 'dpci'=>'DPCI', 'laborex'=>'LABOREX', 'copharmed'=>'COPHARMED', ''=>'Non défini'];
?>

<style>
.province-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.2rem;
    margin-bottom: 0.7rem;
    transition: all 0.2s;
}
.province-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.province-card .province-name { font-weight: 700; font-size: 1rem; color: #1e293b; }
.stat-badge {
    display: inline-block;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.25rem 0.6rem;
    margin: 2px;
    font-size: 0.75rem;
}
.grossiste-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 600;
    margin: 1px;
}
.grossiste-tedis { background: #dbeafe; color: #1e40af; }
.grossiste-dpci { background: #d1fae5; color: #065f46; }
.grossiste-laborex { background: #fef3c7; color: #92400e; }
.grossiste-copharmed { background: #ede9fe; color: #5b21b6; }
.product-item {
    display: flex;
    align-items: center;
    padding: 0.55rem 0.8rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
    color: inherit;
    font-size: 0.9rem;
}
.product-item:hover { background: #f8fafc; }
.product-item.active { background: #eef2ff; border-left: 4px solid #4f46e5; }
.cip-tag {
    display: inline-block;
    background: #f1f5f9;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.8rem;
    margin: 1px;
    cursor: pointer;
    transition: all 0.2s;
}
.cip-tag:hover { background: #4f46e5; color: white; }
.cip-tag.active { background: #4f46e5; color: white; font-weight: bold; }
</style>

<h3>📍 Ventes par Province - Détail par Code CIP</h3>

<!-- Recherche et filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold small">🔍 Recherche</label>
                <div class="input-group">
                    <span class="input-group-text">🔍</span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Nom du produit ou code CIP..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">📅 Mois</label>
                <select name="mois" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $moisFiltre=='all'?'selected':''; ?>>Tous</option>
                    <?php foreach($moisDisponibles as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $moisFiltre==$m?'selected':''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">🏢 Grossiste</label>
                <select name="grossiste" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $grossisteFiltre=='all'?'selected':''; ?>>Tous</option>
                    <?php foreach($grossistes as $g): ?>
                    <option value="<?php echo $g['code']; ?>" <?php echo $grossisteFiltre==$g['code']?'selected':''; ?>><?php echo $g['nom']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="nom" value="<?php echo htmlspecialchars($produitNom); ?>">
            <input type="hidden" name="cip" value="<?php echo htmlspecialchars($cipFiltre); ?>">
            <div class="col-md-2">
                <a href="/inox-pharma-ventes/ventes_province.php" class="btn btn-outline-secondary w-100">✕ Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- Liste des produits -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>📦 Produits (<?php echo count($allProduits); ?>)</span>
                <?php if ($search): ?><small>Recherche : "<?php echo htmlspecialchars($search); ?>"</small><?php endif; ?>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <?php if (empty($allProduits)): ?>
                <div class="text-center py-4 text-muted">Aucun produit trouvé</div>
                <?php else: ?>
                <?php foreach ($allProduits as $p): 
                    $active = $produitNom == $p['libelle'];
                ?>
                <a href="?nom=<?php echo urlencode($p['libelle']); ?>&mois=<?php echo $moisFiltre; ?>&grossiste=<?php echo $grossisteFiltre; ?>&search=<?php echo urlencode($search); ?>" 
                   class="product-item <?php echo $active ? 'active' : ''; ?>">
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($p['libelle']); ?></div>
                        <div>
                            <?php foreach (explode('||', $p['codes_cip']) as $code): ?>
                            <span class="cip-tag" title="Code CIP"><?php echo htmlspecialchars($code); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <span class="badge bg-primary ms-auto"><?php echo number_format($p['total_vendu']); ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Détail du produit -->
    <div class="col-lg-8">
        <?php if ($produitNom): ?>
        
        <!-- En-tête avec tous les codes CIP -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-1">📊 <?php echo htmlspecialchars($produitNom); ?></h5>
            </div>
            <div class="card-body py-2">
                <!-- Tous les codes CIP de ce produit avec leurs grossistes -->
                <div class="mb-2">
                    <span class="fw-bold small">🔢 Codes CIP par grossiste :</span>
                    <div class="mt-1">
                        <?php
                        $cipParGrossiste = [];
                        foreach ($ventesParCIP as $vc) {
                            $gr = $vc['grossiste_code'] ?: 'inconnu';
                            if (!isset($cipParGrossiste[$gr])) $cipParGrossiste[$gr] = [];
                            $cipParGrossiste[$gr][] = $vc;
                        }
                        
                        foreach ($cipParGrossiste as $gr => $cips):
                            $nomGr = $grossNames[$gr] ?? ($gr ?: 'N/D');
                            $class = 'grossiste-' . ($gr ?: 'inconnu');
                        ?>
                        <div class="mb-1">
                            <span class="grossiste-badge <?php echo $class; ?>"><?php echo $nomGr; ?></span>
                            <?php foreach ($cips as $cip): 
                                $activeCIP = $cipFiltre == $cip['code_cip'];
                            ?>
                            <a href="?nom=<?php echo urlencode($produitNom); ?>&cip=<?php echo $cip['code_cip']; ?>&mois=<?php echo $moisFiltre; ?>&grossiste=<?php echo $grossisteFiltre; ?>" 
                               class="cip-tag <?php echo $activeCIP ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cip['code_cip']); ?> 
                                <small>(<?php echo number_format($cip['total']); ?> u.)</small>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <span class="fw-bold small">Total : <?php echo number_format($totalProduit); ?> unités</span>
                    <?php if ($cipFiltre): ?>
                    <a href="?nom=<?php echo urlencode($produitNom); ?>&mois=<?php echo $moisFiltre; ?>&grossiste=<?php echo $grossisteFiltre; ?>" class="btn btn-sm btn-outline-secondary">✕ Voir tous les CIP</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ventes par province -->
        <?php if (empty($ventesParProvince)): ?>
        <div class="card">
            <div class="card-body text-center py-4 text-muted">Aucune vente trouvée</div>
        </div>
        <?php else: ?>
        
        <?php
        $parProvince = [];
        foreach ($ventesParProvince as $v) {
            $prov = $v['province'] ?: 'Inconnue';
            if (!isset($parProvince[$prov])) {
                $parProvince[$prov] = ['total' => 0, 'agences' => [], 'grossistes' => [], 'clients' => 0];
            }
            $parProvince[$prov]['total'] += $v['total'];
            $parProvince[$prov]['clients'] += $v['nb_clients'];
            
            $ag = $v['agence'] ?: 'N/D';
            if (!isset($parProvince[$prov]['agences'][$ag])) $parProvince[$prov]['agences'][$ag] = 0;
            $parProvince[$prov]['agences'][$ag] += $v['total'];
            
            $gr = $v['grossiste_code'] ?: '';
            $key = $v['code_cip'] . '|' . $gr;
            if (!isset($parProvince[$prov]['grossistes'][$key])) $parProvince[$prov]['grossistes'][$key] = ['total' => 0, 'cip' => $v['code_cip'], 'grossiste' => $gr];
            $parProvince[$prov]['grossistes'][$key]['total'] += $v['total'];
        }
        
        $maxTotal = max(array_column($parProvince, 'total')) ?: 1;
        ?>
        
        <div class="row g-2">
            <?php foreach ($parProvince as $province => $data): 
                $pct = ($data['total'] / $maxTotal) * 100;
            ?>
            <div class="col-12">
                <div class="province-card" style="border-left: 4px solid <?php echo $pct>66?'#10b981':($pct>33?'#f59e0b':'#ef4444'); ?>;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="province-name">📍 <?php echo htmlspecialchars($province); ?></span>
                            <small class="text-muted ms-2">(<?php echo $data['clients']; ?> clients)</small>
                        </div>
                        <span class="badge bg-dark"><?php echo number_format($data['total']); ?> u.</span>
                    </div>
                    
                    <div class="progress mb-2" style="height:6px;border-radius:10px;">
                        <div class="progress-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $pct>66?'#10b981':($pct>33?'#f59e0b':'#ef4444'); ?>;"></div>
                    </div>
                    
                    <div class="mb-1">
                        <small class="text-muted fw-bold">🏢 Agences :</small>
                        <?php foreach ($data['agences'] as $ag => $qte): ?>
                        <span class="stat-badge"><?php echo htmlspecialchars($ag); ?> : <strong><?php echo $qte; ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div>
                        <small class="text-muted fw-bold">🏭 Grossistes (CIP) :</small>
                        <?php foreach ($data['grossistes'] as $grData): 
                            $nomGr = $grossNames[$grData['grossiste']] ?? ($grData['grossiste'] ?: 'N/D');
                            $class = 'grossiste-' . ($grData['grossiste'] ?: 'inconnu');
                        ?>
                        <span class="grossiste-badge <?php echo $class; ?>">
                            <?php echo $nomGr; ?> 
                            <small style="opacity:0.8;">(<?php echo htmlspecialchars($grData['cip']); ?>)</small>
                            : <strong><?php echo number_format($grData['total']); ?></strong>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Tableau détaillé -->
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">📋 Détail complet</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th>Province</th>
                                <th>Agence</th>
                                <th>Code CIP</th>
                                <th>Grossiste</th>
                                <th class="text-end">Quantité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventesParProvince as $v): 
                                $nomGr = $grossNames[$v['grossiste_code']] ?? ($v['grossiste_code'] ?: 'N/D');
                                $class = 'grossiste-' . ($v['grossiste_code'] ?: 'inconnu');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($v['province'] ?: 'Inconnue'); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['agence'] ?: 'N/D'); ?></td>
                                <td><code style="font-size:0.8rem;"><?php echo htmlspecialchars($v['code_cip']); ?></code></td>
                                <td><span class="grossiste-badge <?php echo $class; ?>"><?php echo $nomGr; ?></span></td>
                                <td class="text-end fw-bold"><?php echo number_format($v['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr><td colspan="4">TOTAL</td><td class="text-end"><?php echo number_format($totalProduit); ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <div style="font-size:5rem;">👈</div>
                <h4>Sélectionnez un produit</h4>
                <p class="text-muted">Cliquez sur un produit ou utilisez la recherche par code CIP</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>