<?php
$pageTitle = "Réinitialisation des données";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if ($_POST['confirm'] === 'SUPPRIMER') {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("TRUNCATE TABLE ventes_eclatees");
            $pdo->exec("TRUNCATE TABLE produits");
            $pdo->exec("TRUNCATE TABLE clients");
            $pdo->exec("TRUNCATE TABLE provinces");
            $pdo->exec("TRUNCATE TABLE grossiste_clients");
            // Ne pas vider : utilisateurs, grossistes
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $done = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Veuillez taper SUPPRIMER en majuscules pour confirmer.';
    }
}

// Statistiques actuelles
$nbP = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$nbC = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$nbV = $pdo->query("SELECT COUNT(*) FROM ventes_eclatees")->fetchColumn();
$nbProvinces = $pdo->query("SELECT COUNT(*) FROM provinces")->fetchColumn();
$nbAssoc = $pdo->query("SELECT COUNT(*) FROM grossiste_clients")->fetchColumn();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            
            <?php if ($done): ?>
            <!-- SUCCÈS -->
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">✅ Données réinitialisées</h5>
                </div>
                <div class="card-body text-center py-4">
                    <div style="font-size:4rem;">🗑️</div>
                    <h4 class="text-success mt-3">Toutes les données ont été effacées !</h4>
                    <p class="text-muted">Produits, clients, ventes, provinces et associations ont été supprimés.</p>
                    <p class="text-muted small">✅ Utilisateurs et grossistes conservés.</p>
                    <div class="mt-4 d-flex justify-content-center gap-2">
                        <a href="index.php" class="btn btn-primary">📊 Dashboard</a>
                        <a href="import/import.php" class="btn btn-success">📥 Importer ventes</a>
                        <a href="import/sectorisation.php" class="btn btn-outline-primary">🗺️ Importer sectorisation</a>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- CONFIRMATION -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">⚠️ Réinitialisation des données</h5>
                </div>
                <div class="card-body">
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-4">
                        <div style="font-size:4rem;">⚠️</div>
                        <h4 class="text-danger mt-2">Attention ! Cette action est irréversible</h4>
                    </div>
                    
                    <!-- Données actuelles -->
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="fw-bold text-primary"><?php echo number_format($nbP); ?></div>
                                <small class="text-muted">Produits</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="fw-bold text-success"><?php echo number_format($nbC); ?></div>
                                <small class="text-muted">Clients</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="fw-bold text-info"><?php echo number_format($nbV); ?></div>
                                <small class="text-muted">Ventes</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="fw-bold text-warning"><?php echo number_format($nbAssoc); ?></div>
                                <small class="text-muted">Associations</small>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-muted small">
                        Cette action supprimera :
                    </p>
                    <ul class="small text-muted">
                        <li>📦 Tous les produits (<?php echo $nbP; ?>)</li>
                        <li>🏪 Tous les clients (<?php echo $nbC; ?>)</li>
                        <li>💰 Toutes les ventes (<?php echo $nbV; ?>)</li>
                        <li>🗺️ Toutes les provinces (<?php echo $nbProvinces; ?>)</li>
                        <li>🔗 Toutes les associations grossiste-client (<?php echo $nbAssoc; ?>)</li>
                    </ul>
                    
                    <p class="text-success small">
                        ✅ <strong>Conservés :</strong> Utilisateurs, Grossistes (TEDIS, DPCI, LABOREX, COPHARMED)
                    </p>
                    
                    <form method="POST" onsubmit="return confirm('ÊTES-VOUS SÛR ? Cette action est IRRÉVERSIBLE !')">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tapez <code>SUPPRIMER</code> pour confirmer</label>
                            <input type="text" name="confirm" class="form-control form-control-lg text-center" 
                                   placeholder="SUPPRIMER" required 
                                   pattern="SUPPRIMER"
                                   title="Vous devez taper SUPPRIMER en majuscules">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger btn-lg flex-grow-1">
                                🗑️ Réinitialiser toutes les données
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>