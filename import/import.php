<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../lib/SimpleExcel.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Import - Inox Pharma</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f5f7fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class='container mt-4'>";

echo "<h1>📥 Importation des données</h1>";

// Récupérer les statistiques actuelles
try {
    $nbP = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    $nbC = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $nbV = $pdo->query("SELECT COUNT(*) FROM ventes_eclatees")->fetchColumn();
    $moisDispo = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
    $dernierImport = $pdo->query("SELECT MAX(created_at) FROM ventes_eclatees")->fetchColumn();
} catch(Exception $e) {
    $nbP = $nbC = $nbV = 0;
    $moisDispo = [];
    $dernierImport = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    
    $uploadFile = __DIR__ . '/../data/temp_import.xlsx';
    $viderTables = isset($_POST['vider_tables']);
    $anneeImport = intval($_POST['annee'] ?? 2026);
    
    echo "<div class='card'><div class='card-body'>";
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
        echo "<div class='alert alert-success'>✅ Fichier uploadé : " . htmlspecialchars($_FILES['excel_file']['name']) . "</div>";
        
        try {
            $data = SimpleExcel::readXLSX($uploadFile, 2);
            echo "<div class='alert alert-info'>📊 " . count($data) . " lignes lues</div>";
            
            // Détecter les colonnes de mois depuis les en-têtes (ligne 3)
            $colonnesMois = [];
            if (count($data) >= 3) {
                $ligne3 = $data[2]; // Index 2 = 3ème ligne
                
                // Parcourir les colonnes pour trouver les en-têtes de mois
                for ($col = 12; $col < count($ligne3); $col += 2) {
                    $entete = trim((string)($ligne3[$col] ?? ''));
                    if (!empty($entete) && stripos($entete, 'Qte') !== false) {
                        // Le nom du mois est dans la ligne 2 (index 1)
                        $nomMois = trim((string)($data[1][$col] ?? $data[1][$col-1] ?? ''));
                        $colonnesMois[] = [
                            'colonne' => $col,
                            'nom' => $nomMois
                        ];
                    }
                }
            }
            
            // Si pas de colonnes détectées, utiliser les colonnes par défaut
            if (empty($colonnesMois)) {
                $colonnesMois = [
                    ['colonne' => 12, 'nom' => 'Février'],
                    ['colonne' => 14, 'nom' => 'Mars'],
                    ['colonne' => 16, 'nom' => 'Avril']
                ];
            }
            
            // Convertir les noms de mois en dates
            $moisDates = [];
            $moisMap = [
                'janvier' => '01', 'février' => '02', 'fevrier' => '02',
                'mars' => '03', 'avril' => '04', 'mai' => '05',
                'juin' => '06', 'juillet' => '07', 'août' => '08', 'aout' => '08',
                'septembre' => '09', 'octobre' => '10',
                'novembre' => '11', 'décembre' => '12', 'decembre' => '12'
            ];
            
            foreach ($colonnesMois as $cm) {
                $nomLower = strtolower($cm['nom']);
                $numMois = $moisMap[$nomLower] ?? null;
                if ($numMois) {
                    $moisDates[$cm['colonne']] = "$anneeImport-$numMois-01";
                }
            }
            
            echo "<div class='alert alert-info'>📅 Mois détectés : " . implode(', ', array_map(function($d) { 
                return date('F Y', strtotime($d)); 
            }, array_values($moisDates))) . " (Année : $anneeImport)</div>";
            
            // Option vider ou ajouter
            if ($viderTables) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("TRUNCATE TABLE ventes_eclatees");
                $pdo->exec("TRUNCATE TABLE produits");
                $pdo->exec("TRUNCATE TABLE clients");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                echo "<div class='alert alert-warning'>🗑️ Tables vidées avant import</div>";
            } else {
                echo "<div class='alert alert-info'>📌 Ajout aux données existantes</div>";
            }
            
            $pdo->beginTransaction();
            
            $nbProduits = 0;
            $nbClients = 0;
            $nbVentes = 0;
            $produits = [];
            $clients = [];
            
            for ($i = 3; $i < count($data); $i++) {
                $row = $data[$i];
                if (count($row) < 12) continue;
                
                $codeClient = trim((string)($row[4] ?? ''));
                $designationClient = trim((string)($row[5] ?? ''));
                $codeCip = trim((string)($row[6] ?? ''));
                $libelle = trim((string)($row[7] ?? ''));
                $prixCession = floatval($row[8] ?? 0);
                $prixPublic = floatval($row[9] ?? 0);
                $province = trim((string)($row[10] ?? ''));
                $agence = trim((string)($row[11] ?? ''));
                
                if (empty($codeCip) && empty($codeClient)) continue;
                if (empty($codeCip) || empty($codeClient)) continue;
                
                // Produit
                if (!isset($produits[$codeCip])) {
                    $sql = "INSERT INTO produits (code_cip, libelle, prix_cession, prix_public) 
                            VALUES (:c, :l, :pc, :pp) ON DUPLICATE KEY UPDATE libelle=VALUES(libelle)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['c'=>$codeCip, 'l'=>$libelle, 'pc'=>$prixCession, 'pp'=>$prixPublic]);
                    $produits[$codeCip] = true;
                    $nbProduits++;
                }
                
                // Client
                if (!isset($clients[$codeClient])) {
                    $sql = "INSERT INTO clients (code_client, designation, province, agence) 
                            VALUES (:c, :d, :p, :a) ON DUPLICATE KEY UPDATE designation=VALUES(designation)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['c'=>$codeClient, 'd'=>$designationClient, 'p'=>$province, 'a'=>$agence]);
                    $clients[$codeClient] = true;
                    $nbClients++;
                }
                
                // Ventes pour chaque mois détecté
                foreach ($moisDates as $colonne => $dateMois) {
                    $qte = intval($row[$colonne] ?? 0);
                    if ($qte > 0) {
                        $sql = "INSERT INTO ventes_eclatees (code_cip, code_client, mois, qte_livree) 
                                VALUES (:cip, :cli, :m, :q)
                                ON DUPLICATE KEY UPDATE qte_livree = qte_livree + VALUES(qte_livree)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['cip'=>$codeCip, 'cli'=>$codeClient, 'm'=>$dateMois, 'q'=>$qte]);
                        $nbVentes++;
                    }
                }
            }
            
            $pdo->commit();
            
            echo "<div class='alert alert-success'>
                <h4>✅ Importation réussie !</h4>
                <ul>
                    <li>📦 Nouveaux produits : <strong>$nbProduits</strong></li>
                    <li>🏪 Nouveaux clients : <strong>$nbClients</strong></li>
                    <li>💰 Ventes enregistrées : <strong>$nbVentes</strong></li>
                </ul>
            </div>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>❌ Erreur : " . $e->getMessage() . "</div>";
        }
        
        @unlink($uploadFile);
    }
    echo "</div></div>";
}
?>

<!-- Statistiques actuelles -->
<div class="card">
    <div class="card-header bg-primary text-white"><h5>📊 Base de données actuelle</h5></div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h2 class="text-primary"><?php echo $nbP; ?></h2>
                    <p class="mb-0">Produits</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h2 class="text-success"><?php echo $nbC; ?></h2>
                    <p class="mb-0">Clients</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h2 class="text-info"><?php echo $nbV; ?></h2>
                    <p class="mb-0">Transactions</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h2 class="text-warning"><?php echo count($moisDispo); ?></h2>
                    <p class="mb-0">Mois de données</p>
                </div>
            </div>
        </div>
        <?php if (!empty($moisDispo)): ?>
        <div class="mt-3 text-center">
            <span class="badge bg-secondary me-2">Données disponibles :</span>
            <?php foreach ($moisDispo as $m): ?>
            <span class="badge bg-info me-1"><?php echo $m; ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($dernierImport): ?>
        <div class="mt-2 text-center text-muted small">
            Dernier import : <?php echo date('d/m/Y H:i', strtotime($dernierImport)); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Formulaire d'importation -->
<div class='card mt-4'>
    <div class='card-header bg-success text-white'><h5>📥 Importer un nouveau fichier Excel</h5></div>
    <div class='card-body'>
        <form method='POST' enctype='multipart/form-data'>
            <div class='mb-3'>
                <label class='form-label fw-bold'>Fichier Excel (.xlsx)</label>
                <input type='file' name='excel_file' class='form-control' accept='.xlsx' required>
                <small class='text-muted'>Le fichier doit contenir une feuille "éclatée"</small>
            </div>
            
            <div class='row mb-3'>
                <div class='col-md-4'>
                    <label class='form-label'>Année des données</label>
                    <select name='annee' class='form-select'>
                        <?php for ($a = 2024; $a <= 2030; $a++): ?>
                        <option value='<?php echo $a; ?>' <?php echo $a == 2026 ? 'selected' : ''; ?>>
                            <?php echo $a; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class='col-md-8'>
                    <label class='form-label'>&nbsp;</label>
                    <div class='form-check mt-2'>
                        <input type='checkbox' class='form-check-input' id='vider_tables' name='vider_tables'>
                        <label class='form-check-label' for='vider_tables'>
                            ⚠️ <strong>Vider les tables</strong> avant importation 
                            <small class='text-danger'>(décochez pour ajouter sans effacer)</small>
                        </label>
                    </div>
                </div>
            </div>
            
            <button type='submit' class='btn btn-success btn-lg'>
                <i class='bi bi-upload'></i> 🚀 Importer les données
            </button>
            
            <a href='../index.php' class='btn btn-outline-primary btn-lg ms-2'>
                📊 Voir le tableau de bord
            </a>
        </form>
    </div>
</div>

<!-- Instructions -->
<div class='card mt-4'>
    <div class='card-header bg-info text-white'><h5>📖 Guide d'importation</h5></div>
    <div class='card-body'>
        <ul>
            <li><strong>Premier import :</strong> Cochez "Vider les tables" pour repartir de zéro</li>
            <li><strong>Ajout de données :</strong> Décochez "Vider les tables" pour ajouter sans effacer</li>
            <li><strong>Doublons :</strong> Les quantités s'additionnent automatiquement pour les mêmes produit/client/mois</li>
            <li><strong>Année :</strong> Sélectionnez l'année correspondant aux données du fichier</li>
            <li><strong>Détection :</strong> Les mois sont détectés automatiquement depuis les en-têtes du fichier</li>
        </ul>
    </div>
</div>

</div></body></html>";