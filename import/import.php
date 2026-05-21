<?php
$pageTitle = "Import Ventes";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../lib/SimpleExcel.php';
require_once '../auth.php';
requireLogin();

if (!isAdmin() && !isSuperAdmin()) {
    header('Location: ../403.php');
    exit;
}

// Statistiques actuelles
$nbP = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$nbC = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$nbV = $pdo->query("SELECT COUNT(*) FROM ventes_eclatees")->fetchColumn();
$moisDispo = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
$nbAssoc = $pdo->query("SELECT COUNT(*) FROM grossiste_clients")->fetchColumn();
$nbProvinces = $pdo->query("SELECT COUNT(*) FROM provinces")->fetchColumn();

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadFile = __DIR__ . '/../data/temp_import.xlsx';
    $viderTables = isset($_POST['vider_tables']);
    $anneeImport = intval($_POST['annee'] ?? 2026);
    $laboForce = $_POST['labo'] ?? 'auto';
    $grossisteForce = $_POST['grossiste'] ?? 'auto';
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
        try {
            $data = SimpleExcel::readXLSX($uploadFile, 2);
            
            // Détection des mois
            $moisMap = ['janvier'=>'01','fevrier'=>'02','février'=>'02','mars'=>'03','avril'=>'04','mai'=>'05','juin'=>'06','juillet'=>'07','aout'=>'08','août'=>'08','septembre'=>'09','octobre'=>'10','novembre'=>'11','decembre'=>'12','décembre'=>'12'];
            $colonnesMois = [];
            
            if (count($data) >= 3) {
                $ligne2 = $data[1] ?? [];
                for ($col = 12; $col < min(count($ligne2), 24); $col += 2) {
                    $nomMois = strtolower(trim((string)($ligne2[$col] ?? '')));
                    if (!empty($nomMois) && isset($moisMap[$nomMois])) {
                        $colonnesMois[] = ['colonne' => $col, 'date' => "$anneeImport-{$moisMap[$nomMois]}-01", 'nom' => ucfirst($nomMois)];
                    }
                }
            }
            
            if (empty($colonnesMois)) {
                $colonnesMois = [
                    ['colonne'=>12, 'date'=>"$anneeImport-02-01", 'nom'=>'Février'],
                    ['colonne'=>14, 'date'=>"$anneeImport-03-01", 'nom'=>'Mars'],
                    ['colonne'=>16, 'date'=>"$anneeImport-04-01", 'nom'=>'Avril']
                ];
            }
            
            // Vider si demandé
            if ($viderTables) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                $pdo->exec("TRUNCATE TABLE ventes_eclatees");
                $pdo->exec("TRUNCATE TABLE produits");
                $pdo->exec("TRUNCATE TABLE clients");
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            }
            
            $pdo->beginTransaction();
            
            $nbProduits = $nbClients = $nbVentes = 0;
            $stats = ['croient'=>0, 'licpharma'=>0, 'copharmed'=>0, 'dpci'=>0, 'laborex'=>0, 'tedis'=>0, 'inconnu'=>0];
            $produits = $clients = [];
            
            for ($i = 3; $i < count($data); $i++) {
                $row = $data[$i];
                if (count($row) < 12) continue;
                
                $nomFournisseur = trim((string)($row[1] ?? ''));
                $codeClient = trim((string)($row[4] ?? ''));
                $designationClient = trim((string)($row[5] ?? ''));
                $codeCip = trim((string)($row[6] ?? ''));
                $libelle = trim((string)($row[7] ?? ''));
                $prixCession = floatval($row[8] ?? 0);
                $prixPublic = floatval($row[9] ?? 0);
                $province = trim((string)($row[10] ?? ''));
                $agence = trim((string)($row[11] ?? ''));
                
                if (empty($codeCip) || empty($codeClient)) continue;
                
                // DÉTECTION LABO
                $labo = '';
                if ($laboForce !== 'auto') {
                    $labo = $laboForce;
                } else {
                    $nomUpper = strtoupper($nomFournisseur);
                    if (strpos($nomUpper, 'CROIENT') !== false) $labo = 'croient';
                    elseif (strpos($nomUpper, 'MEDISURE') !== false || strpos($nomUpper, 'LIC') !== false) $labo = 'licpharma';
                }
                
                // DÉTECTION GROSSISTE
                $grossisteCode = '';
                if ($grossisteForce !== 'auto') {
                    $grossisteCode = $grossisteForce;
                } else {
                    // Chercher dans la table grossiste_clients
                    $stmt = $pdo->prepare("SELECT grossiste_code FROM grossiste_clients WHERE client_nom LIKE ? OR client_nom LIKE ? LIMIT 1");
                    $search1 = '%' . $designationClient . '%';
                    $search2 = '%' . substr($designationClient, 0, 15) . '%';
                    $stmt->execute([$search1, $search2]);
                    $result = $stmt->fetch();
                    if ($result) $grossisteCode = $result['grossiste_code'];
                }
                
                // Stats
                $stats[$labo ?: 'inconnu']++;
                $stats[$grossisteCode ?: 'inconnu']++;
                
                // INSERT PRODUIT
                if (!isset($produits[$codeCip])) {
                    $pdo->prepare("INSERT INTO produits (code_cip, labo, libelle, prix_cession, prix_public) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE libelle=VALUES(libelle), labo=VALUES(labo)")->execute([$codeCip, $labo, $libelle, $prixCession, $prixPublic]);
                    $produits[$codeCip] = true; $nbProduits++;
                }
                
                // INSERT CLIENT
                if (!isset($clients[$codeClient])) {
                    $pdo->prepare("INSERT INTO clients (code_client, labo, grossiste_code, designation, province, agence) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE designation=VALUES(designation), grossiste_code=VALUES(grossiste_code), labo=VALUES(labo)")->execute([$codeClient, $labo, $grossisteCode, $designationClient, $province, $agence]);
                    $clients[$codeClient] = true; $nbClients++;
                }
                
                // INSERT VENTES
                foreach ($colonnesMois as $cm) {
                    $qte = intval($row[$cm['colonne']] ?? 0);
                    if ($qte > 0) {
                        $pdo->prepare("INSERT INTO ventes_eclatees (code_cip, code_client, labo, grossiste_code, mois, qte_livree) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE qte_livree=qte_livree+VALUES(qte_livree)")->execute([$codeCip, $codeClient, $labo, $grossisteCode, $cm['date'], $qte]);
                        $nbVentes++;
                    }
                }
            }
            
            $pdo->commit();
            
            $moisTrouves = array_map(function($c){return $c['nom'].' '.date('Y',strtotime($c['date']));}, $colonnesMois);
            $message = "✅ Import réussi !<br>📦 $nbProduits produits | 🏪 $nbClients clients | 💰 $nbVentes ventes<br>📅 Mois : " . implode(', ', $moisTrouves) . "<br>🔬 Croient: {$stats['croient']} | 💊 LIC: {$stats['licpharma']} | 🏢 TEDIS: {$stats['tedis']} | DPCI: {$stats['dpci']} | LABOREX: {$stats['laborex']} | COPHARMED: {$stats['copharmed']}";
            $type = 'success';
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }
            $message = "❌ Erreur : " . $e->getMessage();
            $type = 'danger';
        }
        @unlink($uploadFile);
    }
}

// Liste des grossistes pour le formulaire
$grossistesList = $pdo->query("SELECT code, nom FROM grossistes WHERE actif=1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import Ventes - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f1f5f9; font-family:'Inter','Segoe UI',sans-serif; }
        .container { max-width:1000px; }
        .card { border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.05); border:1px solid #e2e8f0; margin-bottom:1.5rem; }
        .card-header { background:white; border-bottom:1px solid #e2e8f0; font-weight:600; padding:1rem 1.5rem; border-radius:16px 16px 0 0!important; }
        .card-body { padding:1.5rem; }
        .btn { font-weight:600; border-radius:10px; padding:0.5rem 1.3rem; transition:all 0.2s; }
        .btn-primary { background:linear-gradient(135deg,#4f46e5,#3730a3); border:none; color:white; }
        .btn-success { background:linear-gradient(135deg,#10b981,#059669); border:none; color:white; }
        .btn-outline-primary { border:2px solid #4f46e5; color:#4f46e5; }
        .btn:hover { transform:translateY(-2px); }
        .form-control, .form-select { border-radius:8px; border:2px solid #e2e8f0; padding:0.55rem 0.9rem; }
        .upload-zone { border:3px dashed #cbd5e1; border-radius:16px; padding:2rem; text-align:center; cursor:pointer; transition:all 0.3s; background:#f8fafc; }
        .upload-zone:hover { border-color:#4f46e5; background:#eef2ff; }
        .stat-mini { background:white; border:1px solid #e2e8f0; border-radius:12px; padding:0.8rem; text-align:center; }
        .stat-mini .number { font-size:1.5rem; font-weight:700; }
        .navbar { background:linear-gradient(135deg,#1e1b4b,#4f46e5)!important; padding:0.5rem 0; }
        .navbar-brand { font-weight:800; color:white!important; text-decoration:none; }
        .nav-link { color:rgba(255,255,255,0.85)!important; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🏥 INOX PHARMA</a>
            <div><a href="../index.php" class="nav-link d-inline-block">← Dashboard</a></div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>📥 Importation des ventes</h3>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="row g-2 mb-4">
            <div class="col-3"><div class="stat-mini"><div class="number text-primary"><?php echo $nbP; ?></div><small>Produits</small></div></div>
            <div class="col-3"><div class="stat-mini"><div class="number text-success"><?php echo $nbC; ?></div><small>Clients</small></div></div>
            <div class="col-3"><div class="stat-mini"><div class="number text-info"><?php echo $nbV; ?></div><small>Ventes</small></div></div>
            <div class="col-3"><div class="stat-mini"><div class="number text-warning"><?php echo $nbAssoc; ?></div><small>Assoc. grossistes</small></div></div>
        </div>
        
        <!-- Formulaire -->
        <div class="card">
            <div class="card-header">📤 Importer un fichier Excel</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone mb-4" onclick="document.getElementById('fileInput').click()">
                        <div style="font-size:3rem;">📁</div>
                        <h5>Cliquez ou glissez-déposez</h5>
                        <p class="text-muted">Fichier Excel (.xlsx) - Feuille "éclatée"</p>
                        <input type="file" name="excel_file" id="fileInput" class="d-none" accept=".xlsx" required>
                        <span id="fileName" class="badge bg-primary mt-2" style="display:none;"></span>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">📅 Année</label>
                            <select name="annee" class="form-select">
                                <?php for($a=2024;$a<=2030;$a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo $a==2026?'selected':''; ?>><?php echo $a; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">🔬 Labo</label>
                            <select name="labo" class="form-select">
                                <option value="auto">🤖 Auto</option>
                                <option value="croient">🔬 Croient</option>
                                <option value="licpharma">💊 LIC Pharma</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">🏢 Grossiste</label>
                            <select name="grossiste" class="form-select">
                                <option value="auto">🤖 Auto (via sectorisation)</option>
                                <?php foreach($grossistesList as $g): ?>
                                <option value="<?php echo $g['code']; ?>"><?php echo $g['nom']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="vider_tables" class="form-check-input" id="vider">
                                <label for="vider" class="form-check-label text-danger fw-bold">⚠️ Vider tables</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">🚀 Importer</button>
                    <?php if ($nbAssoc == 0): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        ⚠️ <strong>Aucune sectorisation importée !</strong> 
                        <a href="sectorisation.php">Importez d'abord le fichier des provinces/grossistes</a> 
                        pour activer la détection automatique.
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('fileInput').addEventListener('change',function(){
            const s=document.getElementById('fileName');
            if(this.files[0]){s.textContent='📄 '+this.files[0].name;s.style.display='inline-block';}
        });
    </script>
</body>
</html>