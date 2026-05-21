<?php
$pageTitle = "Import Sectorisation";
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

$message = '';
$type = '';
$stats = ['provinces' => 0, 'clients' => 0, 'associations' => 0];

// Détection du grossiste selon la colonne
$grossisteColumns = [
    1 => 'copharmed',  // Colonne B
    3 => 'dpci',       // Colonne D
    5 => 'laborex',    // Colonne F
    7 => 'tedis'       // Colonne H
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sector_file'])) {
    $uploadFile = __DIR__ . '/../data/temp_sector.xlsx';
    
    if (move_uploaded_file($_FILES['sector_file']['tmp_name'], $uploadFile)) {
        try {
            // Récupérer toutes les feuilles du fichier
            $zip = new ZipArchive();
            if ($zip->open($uploadFile) !== TRUE) {
                throw new Exception("Impossible d'ouvrir le fichier");
            }
            
            // Lire les noms des feuilles
            $workbookXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
            $feuilles = [];
            if (isset($workbookXml->sheets->sheet)) {
                foreach ($workbookXml->sheets->sheet as $sheet) {
                    $feuilles[] = (string)$sheet['name'];
                }
            }
            $zip->close();
            
            $pdo->beginTransaction();
            
            // Vider les tables si demandé
            if (isset($_POST['vider_tables'])) {
                $pdo->exec("TRUNCATE TABLE grossiste_clients");
                $pdo->exec("TRUNCATE TABLE provinces");
            }
            
            // Parcourir chaque feuille (province)
            foreach ($feuilles as $index => $provinceNom) {
                $provinceCode = strtoupper(trim($provinceNom));
                $provinceCode = preg_replace('/[^A-Z0-9_]/', '_', $provinceCode);
                
                // Insérer la province
                $stmt = $pdo->prepare("INSERT IGNORE INTO provinces (code, nom) VALUES (?, ?)");
                $stmt->execute([$provinceCode, $provinceNom]);
                if ($stmt->rowCount() > 0) $stats['provinces']++;
                
                // Lire la feuille (index + 1 car SimpleExcel commence à 1)
                $data = SimpleExcel::readXLSX($uploadFile, $index + 1);
                
                if (count($data) < 4) continue;
                
                // Parcourir les 4 colonnes de grossistes (B, D, F, H)
                foreach ($grossisteColumns as $colIndex => $grossisteCode) {
                    // Commencer à la ligne 4 (après les en-têtes)
                    for ($row = 3; $row < count($data); $row++) {
                        $clientNom = trim((string)($data[$row][$colIndex] ?? ''));
                        
                        if (!empty($clientNom) && strlen($clientNom) > 2) {
                            // Insérer l'association
                            $stmt = $pdo->prepare("INSERT IGNORE INTO grossiste_clients (grossiste_code, province_code, client_nom) VALUES (?, ?, ?)");
                            $stmt->execute([$grossisteCode, $provinceCode, $clientNom]);
                            if ($stmt->rowCount() > 0) $stats['associations']++;
                            $stats['clients']++;
                        }
                    }
                }
            }
            
            $pdo->commit();
            $message = "✅ Import réussi ! {$stats['provinces']} provinces, {$stats['associations']} associations créées";
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

// Stats actuelles
$nbProvinces = $pdo->query("SELECT COUNT(*) FROM provinces")->fetchColumn();
$nbAssociations = $pdo->query("SELECT COUNT(*) FROM grossiste_clients")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import Sectorisation - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 900px; }
        .card { border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); border: none; margin-bottom: 1.5rem; }
        .card-header { background: white; border-bottom: 1px solid #e2e8f0; font-weight: 600; border-radius: 16px 16px 0 0 !important; }
        .btn { border-radius: 10px; font-weight: 600; padding: 0.6rem 1.5rem; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #3730a3); border: none; color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; }
        .upload-zone { border: 3px dashed #cbd5e1; border-radius: 16px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
        .stat-mini { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; text-align: center; }
        .stat-mini .number { font-size: 1.8rem; font-weight: 700; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background:linear-gradient(135deg,#1e1b4b,#4f46e5);">
        <div class="container">
            <a class="navbar-brand" href="../index.php" style="font-weight:800;">🏥 INOX PHARMA</a>
            <a href="../index.php" class="btn btn-sm btn-light">← Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>🗺️ Import de la Sectorisation</h3>
        <p class="text-muted">Importez le fichier Excel contenant la liste des pharmacies par province et par grossiste</p>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Stats actuelles -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="number text-primary"><?php echo $nbProvinces; ?></div>
                    <div class="text-muted small">Provinces</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="number text-success"><?php echo $nbAssociations; ?></div>
                    <div class="text-muted small">Associations</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-mini">
                    <div class="number text-info">4</div>
                    <div class="text-muted small">Grossistes</div>
                </div>
            </div>
        </div>
        
        <!-- Formulaire -->
        <div class="card">
            <div class="card-header">📥 Importer le fichier Excel</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone mb-4" onclick="document.getElementById('fileInput').click()">
                        <div style="font-size:3rem;">📁</div>
                        <h5>Cliquez ou glissez-déposez le fichier</h5>
                        <p class="text-muted">Fichier Excel (.xlsx) contenant les feuilles par province</p>
                        <input type="file" name="sector_file" id="fileInput" class="d-none" accept=".xlsx" required onchange="this.form.submit()">
                        <span id="fileName" class="badge bg-primary" style="display:none;"></span>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" name="vider_tables" id="vider_tables" class="form-check-input">
                        <label for="vider_tables" class="form-check-label text-danger fw-bold">⚠️ Vider les tables existantes avant import</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">🚀 Importer la sectorisation</button>
                </form>
            </div>
        </div>
        
        <!-- Guide -->
        <div class="card">
            <div class="card-header">📖 Structure du fichier attendu</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Élément</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Feuilles</td><td>Chaque feuille = une province (ABOBO, COCODY, etc.)</td></tr>
                            <tr><td>Colonne B</td><td>Liste des pharmacies COPHARMED</td></tr>
                            <tr><td>Colonne D</td><td>Liste des pharmacies DPCI</td></tr>
                            <tr><td>Colonne F</td><td>Liste des pharmacies LABOREX</td></tr>
                            <tr><td>Colonne H</td><td>Liste des pharmacies TEDIS</td></tr>
                            <tr><td>Ligne 1-3</td><td>En-têtes (ignorés automatiquement)</td></tr>
                            <tr><td>Ligne 4+</td><td>Noms des pharmacies</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showFileName(input) {
            const span = document.getElementById('fileName');
            if (input.files[0]) {
                span.textContent = '📄 ' + input.files[0].name;
                span.style.display = 'inline-block';
            }
        }
        document.getElementById('fileInput').addEventListener('change', function(){showFileName(this);});
    </script>
</body>
</html>