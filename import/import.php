<?php
// import/import.php
require_once '../config/database.php';
require_once '../lib/SimpleExcel.php';

class DataImporter {
    private $pdo;
    private $stats = [
        'produits' => 0,
        'clients' => 0,
        'ventes' => 0,
        'errors' => []
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function importFromExcel($filePath) {
        try {
            echo "<h3>📂 Lecture du fichier Excel...</h3>";
            
            // Lire la feuille "éclatée" (2ème feuille, index 2)
            $data = SimpleExcel::readXLSX($filePath, 2);
            
            echo "<p>✅ Fichier lu avec succès. " . count($data) . " lignes trouvées.</p>";
            
            $this->pdo->beginTransaction();
            
            // Vider les tables existantes
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("TRUNCATE TABLE ventes_eclatees");
            $this->pdo->exec("TRUNCATE TABLE produits");
            $this->pdo->exec("TRUNCATE TABLE clients");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            echo "<p>🗑️ Tables vidées avec succès.</p>";
            
            // Ignorer les 3 premières lignes (en-têtes)
            for ($i = 3; $i < count($data); $i++) {
                $row = $data[$i];
                $this->processRow($row, $i + 1);
            }
            
            $this->pdo->commit();
            
            $this->showResults();
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "<div class='alert alert-danger'>";
            echo "<h4>❌ Erreur d'importation</h4>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
    
    private function processRow($row, $lineNumber) {
        // S'assurer que la ligne a assez de colonnes
        if (count($row) < 19) {
            return; // Ligne vide
        }
        
        $codeCip = $row[6] ?? '';
        $libelle = $row[7] ?? '';
        $prixCession = floatval($row[8] ?? 0);
        $prixPublic = floatval($row[9] ?? 0);
        $codeClient = $row[4] ?? '';
        $designationClient = $row[5] ?? '';
        $province = $row[10] ?? '';
        $agence = $row[11] ?? '';
        
        // Vérifier les données minimales
        if (empty($codeCip) || empty($codeClient)) {
            return;
        }
        
        // 1. Insérer/Mettre à jour le produit
        $this->upsertProduit($codeCip, $libelle, $prixCession, $prixPublic);
        
        // 2. Insérer/Mettre à jour le client
        $this->upsertClient($codeClient, $designationClient, $province, $agence);
        
        // 3. Insérer les ventes pour février, mars, avril
        $ventes = [
            '2026-02-01' => intval($row[12] ?? 0),
            '2026-03-01' => intval($row[15] ?? 0),
            '2026-04-01' => intval($row[18] ?? 0)
        ];
        
        foreach ($ventes as $mois => $qte) {
            if ($qte > 0) {
                $this->insertVente($codeCip, $codeClient, $mois, $qte);
                $this->stats['ventes']++;
            }
        }
    }
    
    private function upsertProduit($code, $libelle, $prixCession, $prixPublic) {
        static $produits = [];
        
        if (!isset($produits[$code])) {
            $sql = "INSERT INTO produits (code_cip, libelle, prix_cession, prix_public) 
                    VALUES (:code, :libelle, :cession, :public)
                    ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'code' => $code,
                'libelle' => $libelle,
                'cession' => $prixCession,
                'public' => $prixPublic
            ]);
            
            $produits[$code] = true;
            $this->stats['produits']++;
        }
    }
    
    private function upsertClient($code, $designation, $province, $agence) {
        static $clients = [];
        
        if (!isset($clients[$code])) {
            $sql = "INSERT INTO clients (code_client, designation, province, agence) 
                    VALUES (:code, :designation, :province, :agence)
                    ON DUPLICATE KEY UPDATE designation = VALUES(designation)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'code' => $code,
                'designation' => $designation,
                'province' => $province,
                'agence' => $agence
            ]);
            
            $clients[$code] = true;
            $this->stats['clients']++;
        }
    }
    
    private function insertVente($codeCip, $codeClient, $mois, $qte) {
        $sql = "INSERT INTO ventes_eclatees (code_cip, code_client, mois, qte_livree) 
                VALUES (:cip, :client, :mois, :qte)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'cip' => $codeCip,
            'client' => $codeClient,
            'mois' => $mois,
            'qte' => $qte
        ]);
    }
    
    private function showResults() {
        ?>
        <div class="alert alert-success">
            <h4>✅ Import terminé avec succès !</h4>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body text-center">
                        <h2><?php echo $this->stats['produits']; ?></h2>
                        <p class="card-text">Produits importés</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body text-center">
                        <h2><?php echo $this->stats['clients']; ?></h2>
                        <p class="card-text">Clients importés</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body text-center">
                        <h2><?php echo $this->stats['ventes']; ?></h2>
                        <p class="card-text">Ventes enregistrées</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Interface HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importation - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-zone {
            border: 3px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-zone:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        .upload-zone.dragover {
            background: #d1ecf1;
            border-color: #0c5460;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">📊 Inox Pharma - Importation</span>
            <a href="../index.php" class="btn btn-light">← Accueil</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h5>Importer les données de ventes éclatées</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone mb-4" id="dropZone">
                        <h3>📁 Glissez-déposez votre fichier ici</h3>
                        <p class="text-muted">ou</p>
                        <input type="file" name="excel_file" id="fileInput" 
                               accept=".xlsx" class="form-control" required>
                        <p class="mt-2">
                            <small class="text-muted">Formats acceptés : .xlsx</small>
                        </p>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>ℹ️ Information :</strong> 
                        Le fichier doit contenir une feuille nommée "éclatée" avec les colonnes appropriées.
                        Les données existantes seront remplacées.
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        🚀 Importer les données
                    </button>
                </form>
            </div>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
            $uploadFile = __DIR__ . '/../data/temp_import.xlsx';
            
            if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
                $importer = new DataImporter($pdo);
                $importer->importFromExcel($uploadFile);
                unlink($uploadFile); // Nettoyage
            } else {
                echo "<div class='alert alert-danger mt-3'>Erreur lors de l'upload du fichier</div>";
            }
        }
        ?>
    </div>

    <script>
        // Drag & drop amélioré
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
        });
        
        fileInput.addEventListener('change', () => {
            const fileName = fileInput.files[0]?.name;
            if (fileName) {
                dropZone.querySelector('h3').textContent = '📄 ' + fileName;
            }
        });
    </script>
</body>
</html>