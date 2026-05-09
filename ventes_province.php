<?php
$pageTitle = "Ventes par Province";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

$produitSearch = $_GET['produit'] ?? '';
$grossisteFiltre = $_GET['grossiste'] ?? 'all';
$moisFiltre = $_GET['mois'] ?? 'all';

// Liste des produits pour la recherche
$produits = [];
if ($produitSearch) {
    $stmt = $pdo->prepare("SELECT code_cip, libelle FROM produits WHERE libelle LIKE ? OR code_cip LIKE ? LIMIT 20");
    $stmt->execute(["%$produitSearch%", "%$produitSearch%"]);
    $produits = $stmt->fetchAll();
}

// Si un produit est sélectionné, afficher ses ventes par province
$produitSelect = $_GET['cip'] ?? '';
$ventesParProvince = [];
$totalProduit = 0;

if ($produitSelect) {
    $sql = "SELECT c.province, c.agence, v.grossiste_code, SUM(v.qte_livree) as total
            FROM ventes_eclatees v
            JOIN clients c ON v.code_client = c.code_client
            WHERE v.code_cip = :cip";
    $params = ['cip' => $produitSelect];
    
    if ($moisFiltre !== 'all') {
        $sql .= " AND DATE_FORMAT(v.mois, '%Y-%m') = :mois";
        $params['mois'] = $moisFiltre;
    }
    if ($grossisteFiltre !== 'all') {
        $sql .= " AND v.grossiste_code = :gross";
        $params['gross'] = $grossisteFiltre;
    }
    
    $sql .= " GROUP BY c.province, c.agence, v.grossiste_code ORDER BY total DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ventesParProvince = $stmt->fetchAll();
    $totalProduit = array_sum(array_column($ventesParProvince, 'total'));
    
    // Info produit
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE code_cip = ?");
    $stmt->execute([$produitSelect]);
    $infoProduit = $stmt->fetch();
}

// Mois disponibles
$moisDisponibles = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois,'%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
$grossistes = $pdo->query("SELECT code, nom FROM grossistes WHERE actif=1")->fetchAll();
?>

<style>
.search-results { position: absolute; z-index: 1000; width: 100%; max-height: 300px; overflow-y: auto; background: white; border: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; display: none; }
.search-results .item { padding: 0.7rem 1rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
.search-results .item:hover { background: #eef2ff; }
.province-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
</style>

<h3>📍 Ventes par Province</h3>
<p class="text-muted">Recherchez un produit pour voir ses ventes par province, agence et grossiste</p>

<!-- Recherche produit -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5 position-relative">
                <label class="form-label fw-bold small">🔍 Rechercher un produit</label>
                <input type="text" name="produit" id="produitSearch" class="form-control" 
                       placeholder="Nom ou code CIP..." value="<?php echo htmlspecialchars($produitSearch); ?>"
                       autocomplete="off" onkeyup="searchProduits()">
                <div class="search-results" id="searchResults"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">📅 Mois</label>
                <select name="mois" class="form-select">
                    <option value="all" <?php echo $moisFiltre=='all'?'selected':''; ?>>Tous</option>
                    <?php foreach($moisDisponibles as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $moisFiltre==$m?'selected':''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">🏢 Grossiste</label>
                <select name="grossiste" class="form-select">
                    <option value="all" <?php echo $grossisteFiltre=='all'?'selected':''; ?>>Tous</option>
                    <?php foreach($grossistes as $g): ?>
                    <option value="<?php echo $g['code']; ?>" <?php echo $grossisteFiltre==$g['code']?'selected':''; ?>><?php echo $g['nom']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="cip" id="cipInput" value="<?php echo $produitSelect; ?>">
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">🔍 Afficher</button>
            </div>
        </form>
    </div>
</div>

<?php if ($produitSelect && $infoProduit): ?>
<!-- Résultat -->
<div class="card mb-4">
    <div class="card-header">
        <span>📊 <?php echo htmlspecialchars($infoProduit['libelle']); ?></span>
        <span class="badge bg-primary"><?php echo number_format($totalProduit); ?> unités au total</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            // Grouper par province
            $parProvince = [];
            foreach ($ventesParProvince as $v) {
                $prov = $v['province'];
                if (!isset($parProvince[$prov])) $parProvince[$prov] = ['total' => 0, 'agences' => [], 'grossistes' => []];
                $parProvince[$prov]['total'] += $v['total'];
                if (!isset($parProvince[$prov]['agences'][$v['agence']])) $parProvince[$prov]['agences'][$v['agence']] = 0;
                $parProvince[$prov]['agences'][$v['agence']] += $v['total'];
                if (!isset($parProvince[$prov]['grossistes'][$v['grossiste_code']])) $parProvince[$prov]['grossistes'][$v['grossiste_code']] = 0;
                $parProvince[$prov]['grossistes'][$v['grossiste_code']] += $v['total'];
            }
            
            $maxTotal = max(array_column($parProvince, 'total')) ?: 1;
            
            foreach ($parProvince as $province => $data):
                $pct = ($data['total'] / $maxTotal) * 100;
            ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background:#f8fafc;">
                        <strong><?php echo htmlspecialchars($province); ?></strong>
                        <span class="badge bg-success"><?php echo number_format($data['total']); ?> u.</span>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-2" style="height:6px;">
                            <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <div class="small text-muted mb-2">
                            <strong>Par agence :</strong>
                            <?php foreach ($data['agences'] as $ag => $qte): ?>
                            <span class="badge bg-light text-dark me-1"><?php echo $ag; ?>: <?php echo $qte; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted">
                            <strong>Par grossiste :</strong>
                            <?php 
                            $grossNames = ['tedis'=>'TEDIS','dpci'=>'DPCI','laborex'=>'LABOREX','copharmed'=>'COPHARMED'];
                            foreach ($data['grossistes'] as $gr => $qte): 
                                $nom = $grossNames[$gr] ?? $gr;
                            ?>
                            <span class="badge bg-info me-1"><?php echo $nom; ?>: <?php echo $qte; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($parProvince)): ?>
            <div class="col-12 text-center text-muted py-4">Aucune vente trouvée pour ce produit</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tableau détaillé -->
<div class="card">
    <div class="card-header">📋 Détail complet</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Province</th><th>Agence</th><th>Grossiste</th><th class="text-end">Quantité</th></tr></thead>
                <tbody>
                    <?php foreach ($ventesParProvince as $v): 
                        $grossNames = ['tedis'=>'TEDIS','dpci'=>'DPCI','laborex'=>'LABOREX','copharmed'=>'COPHARMED'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($v['province']); ?></strong></td>
                        <td><?php echo htmlspecialchars($v['agence']); ?></td>
                        <td><?php echo $grossNames[$v['grossiste_code']] ?? $v['grossiste_code']; ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($v['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function searchProduits() {
    const q = document.getElementById('produitSearch').value;
    if (q.length < 2) { document.getElementById('searchResults').style.display = 'none'; return; }
    
    fetch('produits.php?search=' + encodeURIComponent(q) + '&format=json')
        .then(r => r.json())
        .then(data => {
            const div = document.getElementById('searchResults');
            div.innerHTML = '';
            if (data.length === 0) { div.style.display = 'none'; return; }
            data.forEach(p => {
                const item = document.createElement('div');
                item.className = 'item';
                item.innerHTML = '<strong>' + p.code_cip + '</strong> - ' + p.libelle;
                item.onclick = function() {
                    document.getElementById('cipInput').value = p.code_cip;
                    document.getElementById('produitSearch').value = p.libelle;
                    div.style.display = 'none';
                    this.closest('form').submit();
                };
                div.appendChild(item);
            });
            div.style.display = 'block';
        });
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#produitSearch')) document.getElementById('searchResults').style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>