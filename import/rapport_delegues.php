<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Rapport Délégués";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

// Niveau de détail
$niveau = $_GET['niveau'] ?? 'delegues';
$delegue_id = intval($_GET['delegue_id'] ?? 0);
$medicament = $_GET['medicament'] ?? '';
?>

<div class="container-fluid">
    
    <!-- En-tête avec fil d'Ariane -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="bi bi-file-earmark-bar-graph me-2"></i>
                <?php if ($niveau == 'delegues'): ?>
                    Rapport Délégués
                <?php elseif ($niveau == 'medicaments'): ?>
                    Détail Médicaments - <?php echo htmlspecialchars($_GET['delegue_nom'] ?? ''); ?>
                <?php else: ?>
                    Détail Pharmacies - <?php echo htmlspecialchars($medicament); ?>
                <?php endif; ?>
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?niveau=delegues">👥 Délégués</a></li>
                    <?php if ($niveau == 'medicaments' || $niveau == 'pharmacies'): ?>
                    <li class="breadcrumb-item">
                        <a href="?niveau=medicaments&delegue_id=<?php echo $delegue_id; ?>&delegue_nom=<?php echo urlencode($_GET['delegue_nom'] ?? ''); ?>">
                            📦 <?php echo htmlspecialchars($_GET['delegue_nom'] ?? 'Médicaments'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($niveau == 'pharmacies'): ?>
                    <li class="breadcrumb-item active">🏪 <?php echo htmlspecialchars($medicament); ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="import_ventes_delegues.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-upload me-1"></i>Import
            </a>
            <a href="mapping_provinces.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-geo-alt me-1"></i>Mapping
            </a>
            <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <?php if ($niveau == 'delegues'): ?>
    <!-- ==================== VUE DÉLÉGUÉS ==================== -->
    
    <?php
    // Récupérer tous les délégués avec leurs stats
    $stmt = $pdo->query("
        SELECT 
            d.id,
            d.nom,
            GROUP_CONCAT(DISTINCT s.nom_secteur ORDER BY s.nom_secteur SEPARATOR ', ') AS secteurs,
            COALESCE(COUNT(DISTINCT v.designation_client), 0) AS nb_pharmacies,
            COALESCE(COUNT(DISTINCT v.libelle_article), 0) AS nb_medicaments,
            COALESCE(SUM(v.qte_livree), 0) AS total_boites,
            COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca_total
        FROM delegues d
        JOIN secteur_delegue sd ON sd.delegue_id = d.id
        JOIN secteurs s ON s.id = sd.secteur_id
        LEFT JOIN ventes_delegues v ON v.delegue_id = d.id AND v.statut_attribution = 'auto'
        GROUP BY d.id, d.nom
        ORDER BY total_boites DESC
    ");
    $delegues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats globales
    $total_boites_global = array_sum(array_column($delegues, 'total_boites'));
    $total_pharmacies_global = array_sum(array_column($delegues, 'nb_pharmacies'));
    $delegues_actifs = count(array_filter($delegues, fn($d) => $d['total_boites'] > 0));
    ?>
    
    <!-- Résumé -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo count($delegues); ?></div>
                    <small>Délégués</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo $delegues_actifs; ?></div>
                    <small>Délégués Actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo number_format($total_boites_global); ?></div>
                    <small>Total Boîtes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo $total_pharmacies_global; ?></div>
                    <small>Pharmacies touchées</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Graphique -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Performance par Délégué</div>
        <div class="card-body">
            <canvas id="chartDelegues" height="80"></canvas>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>Liste des Délégués</span>
            <input type="text" id="searchDelegues" class="form-control form-control-sm" style="max-width:250px;" placeholder="🔍 Rechercher...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tableDelegues">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nom du Délégué</th>
                            <th>Zone / Secteur</th>
                            <th class="text-center">🏪 Pharmacies</th>
                            <th class="text-center">📦 Médicaments</th>
                            <th class="text-end">📊 Total Boîtes</th>
                            <th class="text-end">💰 CA (F CFA)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($delegues as $index => $del): 
                            $boites = (int)$del['total_boites'];
                            $ca = (float)$del['ca_total'];
                            $rowClass = $boites > 0 ? '' : 'table-secondary';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="text-muted small"><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($del['nom']); ?></strong>
                                <?php if ($boites == 0): ?>
                                    <span class="badge bg-secondary ms-1">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($del['secteurs'] ?: 'Aucun secteur'); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold"><?php echo (int)$del['nb_pharmacies']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold"><?php echo (int)$del['nb_medicaments']; ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($boites > 0): ?>
                                <span class="fw-bold fs-5 text-primary"><?php echo number_format($boites); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($ca > 0): ?>
                                <span class="fw-bold"><?php echo number_format($ca, 0, ',', ' '); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($boites > 0): ?>
                                <a href="?niveau=medicaments&delegue_id=<?php echo $del['id']; ?>&delegue_nom=<?php echo urlencode($del['nom']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Détail
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($niveau == 'medicaments' && $delegue_id): ?>
    <!-- ==================== VUE MÉDICAMENTS ==================== -->
    
    <?php
    $stmt = $pdo->prepare("
        SELECT 
            v.libelle_article,
            COUNT(DISTINCT v.designation_client) AS nb_pharmacies,
            COALESCE(SUM(v.qte_livree), 0) AS total_boites,
            COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca
        FROM ventes_delegues v
        WHERE v.delegue_id = :did AND v.statut_attribution = 'auto'
        GROUP BY v.libelle_article
        ORDER BY total_boites DESC
    ");
    $stmt->execute([':did' => $delegue_id]);
    $medicaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_boites = array_sum(array_column($medicaments, 'total_boites'));
    $total_ca = array_sum(array_column($medicaments, 'ca'));
    ?>
    
    <!-- Résumé -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo count($medicaments); ?></div>
                    <small>Médicaments différents</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo number_format($total_boites); ?></div>
                    <small>Total Boîtes</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo number_format($total_ca, 0, ',', ' '); ?></div>
                    <small>CA Total (F CFA)</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-box me-2"></i>Médicaments vendus par ce délégué</span>
            <input type="text" id="searchMedicaments" class="form-control form-control-sm" style="max-width:250px;" placeholder="🔍 Rechercher...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tableMedicaments">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Médicament</th>
                            <th class="text-center">🏪 Nb Pharmacies</th>
                            <th class="text-end">📊 Nb Boîtes</th>
                            <th class="text-end">💰 CA (F CFA)</th>
                            <th class="text-end">%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicaments as $index => $med): 
                            $pct = $total_boites > 0 ? round(($med['total_boites'] / $total_boites) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="text-muted small"><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($med['libelle_article']); ?></strong>
                            </td>
                            <td class="text-center">
                                <?php echo (int)$med['nb_pharmacies']; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php echo number_format($med['total_boites']); ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php echo number_format($med['ca'], 0, ',', ' '); ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;max-width:80px;">
                                        <div class="progress-bar bg-primary" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                    <small><?php echo $pct; ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="?niveau=pharmacies&delegue_id=<?php echo $delegue_id; ?>&delegue_nom=<?php echo urlencode($_GET['delegue_nom'] ?? ''); ?>&medicament=<?php echo urlencode($med['libelle_article']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-shop me-1"></i>Pharmacies
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">TOTAL</td>
                            <td class="text-end"><?php echo number_format($total_boites); ?></td>
                            <td class="text-end"><?php echo number_format($total_ca, 0, ',', ' '); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($niveau == 'pharmacies' && $delegue_id && $medicament): ?>
    <!-- ==================== VUE PHARMACIES ==================== -->
    
    <?php
    $stmt = $pdo->prepare("
        SELECT 
            v.designation_client,
            v.province,
            COALESCE(SUM(v.qte_livree), 0) AS total_boites,
            COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca
        FROM ventes_delegues v
        WHERE v.delegue_id = :did 
          AND v.libelle_article = :med
          AND v.statut_attribution = 'auto'
        GROUP BY v.designation_client, v.province
        ORDER BY total_boites DESC
    ");
    $stmt->execute([':did' => $delegue_id, ':med' => $medicament]);
    $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_boites = array_sum(array_column($pharmacies, 'total_boites'));
    $total_ca = array_sum(array_column($pharmacies, 'ca'));
    ?>
    
    <!-- Résumé -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo count($pharmacies); ?></div>
                    <small>Pharmacies</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo number_format($total_boites); ?></div>
                    <small>Total Boîtes</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?php echo number_format($total_ca, 0, ',', ' '); ?></div>
                    <small>CA Total (F CFA)</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shop me-2"></i>Pharmacies pour : <?php echo htmlspecialchars($medicament); ?></span>
            <a href="?niveau=medicaments&delegue_id=<?php echo $delegue_id; ?>&delegue_nom=<?php echo urlencode($_GET['delegue_nom'] ?? ''); ?>" 
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Retour aux médicaments
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Pharmacie</th>
                            <th>Province</th>
                            <th class="text-end">📊 Nb Boîtes</th>
                            <th class="text-end">💰 CA (F CFA)</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacies as $index => $ph): 
                            $pct = $total_boites > 0 ? round(($ph['total_boites'] / $total_boites) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="text-muted small"><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($ph['designation_client']); ?></strong></td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($ph['province']); ?></span></td>
                            <td class="text-end fw-bold"><?php echo number_format($ph['total_boites']); ?></td>
                            <td class="text-end fw-bold"><?php echo number_format($ph['ca'], 0, ',', ' '); ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;max-width:80px;">
                                        <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                    <small><?php echo $pct; ?>%</small>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">TOTAL</td>
                            <td class="text-end"><?php echo number_format($total_boites); ?></td>
                            <td class="text-end"><?php echo number_format($total_ca, 0, ',', ' '); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if ($niveau == 'delegues' && !empty($delegues)): ?>
<script>
var chartData = <?php 
$actifs = array_filter($delegues, fn($d) => $d['total_boites'] > 0);
echo json_encode(array_values(array_map(function($d) {
    return ['label' => $d['nom'], 'boites' => (int)$d['total_boites']];
}, $actifs))); 
?>;

if (chartData.length > 0) {
    new Chart(document.getElementById('chartDelegues'), {
        type: 'bar',
        data: {
            labels: chartData.map(function(d) { 
                var parts = d.label.split(' ');
                return parts.slice(0, 2).join(' ');
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
}
</script>
<?php endif; ?>

<script>
// Recherche dans les tableaux
function setupSearch(inputId, tableId) {
    document.getElementById(inputId)?.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function(tr) {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
setupSearch('searchDelegues', 'tableDelegues');
setupSearch('searchMedicaments', 'tableMedicaments');
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>