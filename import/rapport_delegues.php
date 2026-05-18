<?php
/**
 * RAPPORT DES VENTES PAR DÉLÉGUÉ
 * Fichier : import/rapport_delegues.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Rapport Délégués";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

$import_id  = intval($_GET['import_id'] ?? 0);
$delegue_id = intval($_GET['delegue_id'] ?? 0);
$mois       = $_GET['mois'] ?? '';

// Requête principale : ventes par délégué
$where = ['1=1'];
$params = [];
if ($import_id)  { $where[] = 'v.import_id = :import_id';  $params[':import_id']  = $import_id; }
if ($delegue_id) { $where[] = 'v.delegue_id = :delegue_id'; $params[':delegue_id'] = $delegue_id; }
if ($mois)       { $where[] = 'v.mois = :mois';             $params[':mois']       = $mois.'-01'; }

$where_sql = implode(' AND ', $where);

// Résumé par délégué
$resume = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            d.id                            AS delegue_id_val,
            d.nom                           AS delegue_nom,
            s.nom_secteur                   AS secteur,
            COUNT(DISTINCT v.designation_client) AS nb_clients,
            COUNT(DISTINCT v.libelle_article)    AS nb_produits,
            SUM(v.qte_livree)                    AS total_boites,
            SUM(v.qte_livree * v.prix_cession)   AS ca_estime,
            v.mois
        FROM ventes_delegues v
        JOIN delegues d  ON d.id = v.delegue_id
        JOIN secteurs s  ON s.id = v.secteur_id
        WHERE $where_sql
        GROUP BY v.delegue_id, v.mois
        ORDER BY total_boites DESC
    ");
    $stmt->execute($params);
    $resume = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $resume = [];
}

// Détail produits pour un délégué sélectionné
$detail_produits = [];
if ($delegue_id) {
    try {
        $stmt2 = $pdo->prepare("
            SELECT
                v.libelle_article,
                v.province,
                COUNT(DISTINCT v.designation_client) AS nb_clients,
                SUM(v.qte_livree) AS total_boites
            FROM ventes_delegues v
            WHERE v.delegue_id = :did AND $where_sql
            GROUP BY v.libelle_article, v.province
            ORDER BY total_boites DESC
        ");
        $params2 = $params;
        $params2[':did'] = $delegue_id;
        $stmt2->execute($params2);
        $detail_produits = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $detail_produits = [];
    }
}
?>

<div class="container-fluid">
    
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="bi bi-file-earmark-bar-graph me-2"></i>Rapport Ventes par Délégué</h3>
            <?php if ($import_id): ?>
                <small class="text-muted">Import #<?php echo $import_id; ?></small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="import_ventes_delegues.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour Import
            </a>
            <a href="mapping_provinces.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-geo-alt me-1"></i>Mapping
            </a>
            <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <?php if (empty($resume)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size:4rem;color:#cbd5e1;"></i>
            <h4 class="mt-3">Aucune donnée disponible</h4>
            <p class="text-muted">Effectuez d'abord un import de ventes éclatées.</p>
            <a href="import_ventes_delegues.php" class="btn btn-primary mt-2">
                <i class="bi bi-upload me-1"></i>Importer des ventes
            </a>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- Graphique boites par délégué -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart me-2"></i>Boîtes vendues par délégué
                </div>
                <div class="card-body">
                    <canvas id="chartDelegues" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Liste délégués -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-people me-2"></i><?php echo count($resume); ?> Délégués
                </div>
                <div class="card-body" style="overflow-y:auto;max-height:500px">
                    <?php foreach ($resume as $r): 
                        $currentParams = $_GET;
                        $currentParams['delegue_id'] = $r['delegue_id_val'];
                        $link = '?' . http_build_query($currentParams);
                    ?>
                    <a href="<?php echo $link; ?>" class="text-decoration-none">
                        <div class="border rounded p-3 mb-2 delegue-card" style="border-left:4px solid #4f46e5;cursor:pointer;transition:all 0.2s;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($r['delegue_nom']); ?></strong>
                                    <div class="text-muted small">
                                        <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($r['secteur']); ?>
                                    </div>
                                    <div class="small mt-1">
                                        <span class="badge bg-light text-dark me-1">
                                            <i class="bi bi-shop me-1"></i><?php echo $r['nb_clients']; ?> pharmacies
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-box me-1"></i><?php echo $r['nb_produits']; ?> produits
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fs-4 fw-bold text-primary"><?php echo number_format($r['total_boites']); ?></div>
                                    <div class="text-muted small">boîtes</div>
                                    <?php if ($r['ca_estime'] > 0): ?>
                                    <div class="text-muted small"><?php echo number_format($r['ca_estime'], 0, ',', ' '); ?> F</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Détail produits -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-box me-2"></i>Détail par produit & province
                    <?php if ($delegue_id): ?>
                        <?php 
                        $nomDelegue = '';
                        foreach ($resume as $r) {
                            if ($r['delegue_id_val'] == $delegue_id) {
                                $nomDelegue = $r['delegue_nom'];
                                break;
                            }
                        }
                        ?>
                        — <?php echo htmlspecialchars($nomDelegue); ?>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($detail_produits)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Produit</th>
                                    <th>Province</th>
                                    <th>Pharmacies</th>
                                    <th class="text-end">Boîtes</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($detail_produits as $dp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dp['libelle_article']); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($dp['province']); ?></span></td>
                                    <td><?php echo $dp['nb_clients']; ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($dp['total_boites']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-hand-index" style="font-size:2rem;"></i>
                            <p class="mt-2">Cliquez sur un délégué pour voir le détail</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($resume)): ?>
<script>
// Graphique délégués
var chartData = <?php echo json_encode(array_map(function($r) {
    return [
        'label'  => $r['delegue_nom'],
        'boites' => (int)$r['total_boites'],
    ];
}, $resume)); ?>;

new Chart(document.getElementById('chartDelegues'), {
    type: 'bar',
    data: {
        labels: chartData.map(function(d) { 
            var parts = d.label.split(' ');
            return parts.length > 1 ? parts.slice(0, 2).join(' ') : d.label;
        }),
        datasets: [{
            label: 'Boîtes vendues',
            data: chartData.map(function(d) { return d.boites; }),
            backgroundColor: 'rgba(79, 70, 229, 0.7)',
            borderColor: 'rgba(55, 48, 163, 1)',
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../footer.php'; ?>