<?php
/**
 * PAGE DE GESTION DU MAPPING PROVINCES <-> SECTEURS
 * Permet de corriger les provinces non attribuées après un import
 * Fichier : import/mapping_provinces.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Mapping Provinces";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

// Vérifier admin uniquement
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$msg_succes = '';
$msg_erreur = '';

// ── ACTIONS AJAX ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');

    // Sauvegarder un mapping province -> secteur
    if ($_POST['action'] === 'sauvegarder_mapping') {
        $province_code = strtoupper(trim($_POST['province_code'] ?? ''));
        $secteur_id    = intval($_POST['secteur_id'] ?? 0);

        if (!$province_code || !$secteur_id) {
            echo json_encode(['ok' => false, 'msg' => 'Données manquantes']); exit;
        }

        // S'assurer que la province existe dans la table provinces
        try {
            $pdo->prepare("INSERT IGNORE INTO provinces (code, nom) VALUES (:c, :n)")
                ->execute([':c' => $province_code, ':n' => $province_code]);
        } catch (Exception $e) {}

        // Insérer ou mettre à jour le mapping
        try {
            $stmt = $pdo->prepare("
                INSERT INTO province_secteur (province_code, secteur_id)
                VALUES (:p, :s)
                ON DUPLICATE KEY UPDATE secteur_id = :s2
            ");
            $stmt->execute([':p' => $province_code, ':s' => $secteur_id, ':s2' => $secteur_id]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Erreur SQL : ' . $e->getMessage()]); exit;
        }

        echo json_encode(['ok' => true, 'msg' => "Mapping sauvegardé pour $province_code"]); exit;
    }

    // Supprimer un mapping
    if ($_POST['action'] === 'supprimer_mapping') {
        $province_code = strtoupper(trim($_POST['province_code'] ?? ''));
        try {
            $pdo->prepare("DELETE FROM province_secteur WHERE province_code = :p")
                ->execute([':p' => $province_code]);
        } catch (Exception $e) {}
        echo json_encode(['ok' => true]); exit;
    }

    // Ajouter une nouvelle province manuellement
    if ($_POST['action'] === 'ajouter_province') {
        $nom  = strtoupper(trim($_POST['nom'] ?? ''));
        $code = strtoupper(trim($_POST['code'] ?? $nom));
        if (!$nom) { echo json_encode(['ok'=>false,'msg'=>'Nom vide']); exit; }
        try {
            $pdo->prepare("INSERT IGNORE INTO provinces (code, nom) VALUES (:c, :n)")
                ->execute([':c' => $code, ':n' => $nom]);
        } catch (Exception $e) {}
        echo json_encode(['ok' => true]); exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Action inconnue']); exit;
}

// ── DONNÉES POUR L'AFFICHAGE ────────────────────────────────

// Liste des secteurs
$secteurs = [];
try {
    $secteurs = $pdo->query("SELECT id, nom_secteur FROM secteurs ORDER BY nom_secteur")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $secteurs = [];
}

// Provinces déjà mappées
$mappees = [];
try {
    $mappees = $pdo->query("
        SELECT ps.province_code, ps.secteur_id, s.nom_secteur,
               COUNT(ve.id) AS nb_ventes
        FROM province_secteur ps
        JOIN secteurs s ON s.id = ps.secteur_id
        LEFT JOIN ventes_eclatees ve ON UPPER(ve.province) = ps.province_code
        GROUP BY ps.province_code, ps.secteur_id, s.nom_secteur
        ORDER BY ps.province_code
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mappees = [];
}

// Provinces NON mappées
$non_mappees = [];
try {
    $non_mappees = $pdo->query("
        SELECT UPPER(ve.province) AS province_code,
               COUNT(DISTINCT ve.id) AS nb_ventes,
               COUNT(DISTINCT ve.designation_client) AS nb_clients,
               GROUP_CONCAT(DISTINCT ve.grossiste_code ORDER BY ve.grossiste_code SEPARATOR ', ') AS grossistes
        FROM ventes_eclatees ve
        WHERE ve.province IS NOT NULL
          AND ve.province != ''
          AND UPPER(ve.province) NOT IN (SELECT province_code FROM province_secteur)
        GROUP BY UPPER(ve.province)
        ORDER BY nb_ventes DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $non_mappees = [];
}

// Statistiques globales
$stats = ['total_provinces' => 0, 'attribuees' => 0, 'non_attribuees' => 0];
try {
    $s = $pdo->query("
        SELECT
            COUNT(DISTINCT UPPER(province)) AS total_provinces
        FROM ventes_eclatees WHERE province IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);
    if ($s) $stats = array_merge($stats, $s);
} catch (Exception $e) {}
?>

<div class="container-fluid">
    
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="bi bi-geo-alt me-2"></i>Mapping Provinces → Secteurs</h3>
            <p class="text-muted mb-0">Associer chaque province à la zone d'un délégué</p>
        </div>
        <div class="d-flex gap-2">
            <a href="import_ventes_delegues.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Import
            </a>
            <a href="rapport_delegues.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Rapport
            </a>
            <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Stats rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold"><?php echo $stats['total_provinces'] ?? 0; ?></div>
                    <small>Provinces au total</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold"><?php echo count($non_mappees); ?></div>
                    <small>Provinces non mappées</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold"><?php echo count($mappees); ?></div>
                    <small>Provinces mappées</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLONNE GAUCHE : Provinces non mappées -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-triangle me-2"></i>Provinces non mappées</span>
                    <input type="text" class="form-control form-control-sm" style="max-width:250px;"
                           id="searchNonMappees" placeholder="Rechercher...">
                </div>
                <div class="card-body p-0">
                    <?php if (empty($non_mappees)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
                            <h5 class="text-success mt-3">Toutes les provinces sont mappées !</h5>
                        </div>
                    <?php else: ?>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover mb-0" id="tableNonMappees">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Province</th>
                                    <th>Ventes</th>
                                    <th>Secteur</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($non_mappees as $p): ?>
                            <tr class="province-row" data-province="<?php echo htmlspecialchars($p['province_code']); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($p['province_code']); ?></strong>
                                    <div class="text-muted small">
                                        <?php echo $p['nb_clients']; ?> client(s) · <?php echo htmlspecialchars($p['grossistes']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark px-2"><?php echo $p['nb_ventes']; ?></span>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm select-secteur" style="min-width:200px;"
                                            data-province="<?php echo htmlspecialchars($p['province_code']); ?>">
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($secteurs as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nom_secteur']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-mapper"
                                            data-province="<?php echo htmlspecialchars($p['province_code']); ?>"
                                            title="Sauvegarder">
                                        <i class="bi bi-save"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLONNE DROITE : Provinces déjà mappées -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check-circle me-2"></i>Provinces mappées</span>
                    <input type="text" class="form-control form-control-sm" style="max-width:250px;"
                           id="searchMappees" placeholder="Rechercher...">
                </div>
                <div class="card-body p-0">
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover mb-0" id="tableMappees">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Province</th>
                                    <th>Secteur</th>
                                    <th>Ventes</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($mappees as $m): ?>
                            <tr class="province-row">
                                <td><strong><?php echo htmlspecialchars($m['province_code']); ?></strong></td>
                                <td>
                                    <select class="form-select form-select-sm select-secteur" style="min-width:200px;"
                                            data-province="<?php echo htmlspecialchars($m['province_code']); ?>">
                                        <?php foreach ($secteurs as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"
                                                <?php echo $s['id'] == $m['secteur_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['nom_secteur']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><span class="badge bg-success px-2"><?php echo $m['nb_ventes']; ?></span></td>
                                <td class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-mapper"
                                            data-province="<?php echo htmlspecialchars($m['province_code']); ?>"
                                            title="Modifier">
                                        <i class="bi bi-save"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-supprimer"
                                            data-province="<?php echo htmlspecialchars($m['province_code']); ?>"
                                            title="Supprimer le mapping">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function post(data) {
    const r = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data) });
    return r.json();
}

// Sauvegarder un mapping
document.querySelectorAll('.btn-mapper').forEach(btn => {
    btn.addEventListener('click', async () => {
        const province = btn.dataset.province;
        const row      = btn.closest('tr');
        const select   = row.querySelector('.select-secteur');
        const secteur_id = select?.value;
        if (!secteur_id) { alert('Sélectionnez un secteur'); return; }

        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const res = await post({ action:'sauvegarder_mapping', province_code: province, secteur_id });
        if (res.ok) {
            alert(res.msg);
            location.reload();
        } else {
            alert(res.msg || 'Erreur');
            btn.innerHTML = '<i class="bi bi-save"></i>';
        }
    });
});

// Supprimer un mapping
document.querySelectorAll('.btn-supprimer').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Supprimer le mapping pour "' + btn.dataset.province + '" ?')) return;
        const res = await post({ action:'supprimer_mapping', province_code: btn.dataset.province });
        if (res.ok) { location.reload(); }
    });
});

// Recherche dans les tableaux
function filtrerTable(inputId, tableId) {
    document.getElementById(inputId)?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#' + tableId + ' tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
filtrerTable('searchNonMappees', 'tableNonMappees');
filtrerTable('searchMappees', 'tableMappees');
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>