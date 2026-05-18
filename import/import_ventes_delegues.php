<?php
/**
 * PAGE WEB : Import des ventes éclatées avec attribution aux délégués
 * Fichier : import/import_ventes_delegues.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Import Ventes Délégués";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/ImportDelegues.php';

// Vérifier admin uniquement
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$result = null;
$erreur = null;

// Liste des grossistes connus
$grossistes = [
    'copharmed' => 'COPHARMED',
    'tedis'     => 'TEDIS',
    'dpci'      => 'DPCI',
    'laborex'   => 'LABOREX',
];

// Mois disponibles (12 derniers mois)
$mois_options = [];
for ($i = 0; $i < 12; $i++) {
    $d = new DateTime("first day of -$i month");
    $mois_options[$d->format('Y-m')] = $d->format('F Y');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
    $grossiste = $_POST['grossiste'] ?? '';
    $mois      = $_POST['mois'] ?? '';

    if (empty($grossiste) || empty($mois)) {
        $erreur = "Veuillez sélectionner le grossiste et le mois.";
    } elseif ($_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $erreur = "Erreur lors de l'upload du fichier (code : " . $_FILES['fichier']['error'] . ")";
    } else {
        $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $erreur = "Format non supporté. Utilisez Excel (.xlsx) ou CSV.";
        } else {
            // Créer le dossier d'upload si nécessaire
            $upload_dir = __DIR__ . '/../uploads/ventes_eclatees/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename   = $grossiste . '_' . $mois . '_' . time() . '.' . $ext;
            $filepath   = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filepath)) {
                try {
                    $importer = new ImportDelegues($pdo);
                    $result   = $importer->importerFichier($filepath, $grossiste, $mois);
                } catch (Exception $e) {
                    $erreur = "Erreur technique : " . $e->getMessage();
                }
            } else {
                $erreur = "Impossible de sauvegarder le fichier.";
            }
        }
    }
}

// Récupérer l'historique des imports
try {
    $stmt = $pdo->query("
        SELECT i.*, COUNT(v.id) as nb_ventes_enregistrees
        FROM imports_delegues i
        LEFT JOIN ventes_delegues v ON v.import_id = i.id
        GROUP BY i.id
        ORDER BY i.created_at DESC
        LIMIT 20
    ");
    $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $historique = [];
}
?>

<div class="container-fluid">
    
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="bi bi-person-badge me-2"></i>Import Ventes Délégués</h3>
            <p class="text-muted mb-0">Attribution automatique des ventes aux délégués</p>
        </div>
        <a href="../index.php" class="btn btn-outline-primary">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>

    <!-- Messages -->
    <?php if ($erreur): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($erreur); ?>
    </div>
    <?php endif; ?>

    <?php if ($result && $result['success']): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        Import réussi ! <?php echo number_format($result['nb_attribuees']); ?> ventes attribuées sur <?php echo number_format($result['nb_total']); ?> lignes.
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Formulaire d'import -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-upload me-2"></i>Nouvel Import
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="importForm">
                        <!-- Grossiste -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">🏢 Grossiste</label>
                            <select name="grossiste" class="form-select" required>
                                <option value="">-- Sélectionner le grossiste --</option>
                                <?php foreach ($grossistes as $code => $nom): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $nom; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Mois -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">📅 Mois des ventes</label>
                            <select name="mois" class="form-select" required>
                                <option value="">-- Sélectionner le mois --</option>
                                <?php foreach ($mois_options as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Zone de dépôt fichier -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">📁 Fichier des ventes éclatées</label>
                            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fichier').click()" style="border:2px dashed #cbd5e1;border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:all 0.3s;background:#f8fafc;">
                                <i class="bi bi-cloud-upload" style="font-size:2.5rem;color:#94a3b8;"></i>
                                <p class="mb-1 fw-semibold" id="dropText">Glisser-déposer ou cliquer</p>
                                <small class="text-muted">Formats acceptés : .xlsx, .xls, .csv</small>
                            </div>
                            <input type="file" name="fichier" id="fichier" class="d-none" accept=".xlsx,.xls,.csv" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100" id="btnImport">
                            <i class="bi bi-cogs me-2"></i>Lancer l'import & attribution
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Résultats + Historique -->
        <div class="col-lg-7">
            <?php if ($result): ?>
            <div class="card mb-4">
                <div class="card-header <?php echo $result['success'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <i class="bi <?php echo $result['success'] ? 'bi-check-circle' : 'bi-x-circle'; ?> me-2"></i>
                    Résultats de l'import
                </div>
                <div class="card-body">
                    <?php if ($result['success']): ?>
                        <!-- Stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <div class="border rounded p-3 text-center bg-light">
                                    <div class="fs-3 fw-bold text-primary"><?php echo number_format($result['nb_total']); ?></div>
                                    <small class="text-muted">Lignes lues</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 text-center bg-light">
                                    <div class="fs-3 fw-bold text-success"><?php echo number_format($result['nb_attribuees']); ?></div>
                                    <small class="text-muted">Attribuées</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 text-center bg-light">
                                    <div class="fs-3 fw-bold text-warning"><?php echo number_format($result['nb_non_attribuees']); ?></div>
                                    <small class="text-muted">Non attribuées</small>
                                </div>
                            </div>
                        </div>

                        <!-- Barre de progression -->
                        <?php $pct = $result['nb_total'] > 0 ? round($result['nb_attribuees'] / $result['nb_total'] * 100) : 0; ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Taux d'attribution</small>
                                <strong><?php echo $pct; ?>%</strong>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                        </div>

                        <!-- Lignes non attribuées -->
                        <?php if (!empty($result['non_attribues'])): ?>
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle me-1"></i>Lignes non attribuées</strong>
                            <p class="mb-2 mt-1 small">Ces lignes nécessitent un mapping manuel :</p>
                            <div style="max-height:200px;overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-warning">
                                        <tr><th>Raison</th><th>Province</th><th>Produit</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach (array_slice($result['non_attribues'], 0, 30) as $na): ?>
                                        <tr>
                                            <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($na['raison']); ?></span></td>
                                            <td><?php echo htmlspecialchars($na['province'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($na['produit'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2">
                                <a href="mapping_provinces.php" class="btn btn-sm btn-warning">
                                    <i class="bi bi-geo-alt me-1"></i>Gérer le mapping
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($result['message']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historique imports -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i>Historique des imports
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Grossiste</th>
                                    <th>Mois</th>
                                    <th>Total</th>
                                    <th>Attrib.</th>
                                    <th>Non att.</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historique as $h): ?>
                                <tr>
                                    <td><small><?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?></small></td>
                                    <td><strong><?php echo strtoupper(htmlspecialchars($h['grossiste_code'])); ?></strong></td>
                                    <td><?php echo htmlspecialchars($h['mois_import']); ?></td>
                                    <td><?php echo number_format($h['nb_lignes_total']); ?></td>
                                    <td><span class="badge bg-success"><?php echo number_format($h['nb_attribuees']); ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo number_format($h['nb_non_attribuees']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($historique)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Aucun import effectué</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher le nom du fichier
document.getElementById('fichier').addEventListener('change', function() {
    const text = document.getElementById('dropText');
    if (this.files[0]) text.textContent = '📄 ' + this.files[0].name;
});

// Drag & Drop
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = '#4f46e5';
    this.style.background = '#eef2ff';
});
dropZone.addEventListener('dragleave', function() {
    this.style.borderColor = '#cbd5e1';
    this.style.background = '#f8fafc';
});
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#cbd5e1';
    this.style.background = '#f8fafc';
    const input = document.getElementById('fichier');
    input.files = e.dataTransfer.files;
    document.getElementById('dropText').textContent = '📄 ' + input.files[0].name;
});

// Indicateur chargement
document.getElementById('importForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnImport');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement en cours...';
    btn.disabled = true;
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>