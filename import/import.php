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
</head>
<body>
<div class='container mt-4'>";

echo "<h1>📥 Importation des données</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    
    $uploadFile = __DIR__ . '/../data/temp_import.xlsx';
    
    echo "<div class='card'><div class='card-body'>";
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
        echo "<div class='alert alert-success'>✅ Fichier uploadé : " . htmlspecialchars($_FILES['excel_file']['name']) . "</div>";
        
        try {
            $data = SimpleExcel::readXLSX($uploadFile, 2);
            echo "<div class='alert alert-info'>📊 " . count($data) . " lignes lues</div>";
            
            // Vider les tables
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("TRUNCATE TABLE ventes_eclatees");
            $pdo->exec("TRUNCATE TABLE produits");
            $pdo->exec("TRUNCATE TABLE clients");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "<div class='alert alert-warning'>🗑️ Tables vidées</div>";
            
            $pdo->beginTransaction();
            
            $nbProduits = 0;
            $nbClients = 0;
            $nbVentes = 0;
            $produits = [];
            $clients = [];
            
            // Parcourir les lignes (ignorer les 3 premières d'en-tête)
            for ($i = 3; $i < count($data); $i++) {
                $row = $data[$i];
                
                // Vérifier qu'il y a assez de colonnes
                if (count($row) < 17) continue;
                
                // COLONNES CORRIGÉES selon la structure réelle
                $codeClient = trim((string)($row[4] ?? ''));
                $designationClient = trim((string)($row[5] ?? ''));
                $codeCip = trim((string)($row[6] ?? ''));
                $libelle = trim((string)($row[7] ?? ''));
                $prixCession = floatval($row[8] ?? 0);
                $prixPublic = floatval($row[9] ?? 0);
                $province = trim((string)($row[10] ?? ''));
                $agence = trim((string)($row[11] ?? ''));
                
                // Ignorer les lignes vides
                if (empty($codeCip) && empty($codeClient)) continue;
                if (empty($codeCip) || empty($codeClient)) continue;
                
                // Insérer produit (si pas déjà fait)
                if (!isset($produits[$codeCip])) {
                    $sql = "INSERT INTO produits (code_cip, libelle, prix_cession, prix_public) 
                            VALUES (:c, :l, :pc, :pp) 
                            ON DUPLICATE KEY UPDATE libelle=VALUES(libelle)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'c' => $codeCip,
                        'l' => $libelle,
                        'pc' => $prixCession,
                        'pp' => $prixPublic
                    ]);
                    $produits[$codeCip] = true;
                    $nbProduits++;
                }
                
                // Insérer client (si pas déjà fait)
                if (!isset($clients[$codeClient])) {
                    $sql = "INSERT INTO clients (code_client, designation, province, agence) 
                            VALUES (:c, :d, :p, :a) 
                            ON DUPLICATE KEY UPDATE designation=VALUES(designation)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'c' => $codeClient,
                        'd' => $designationClient,
                        'p' => $province,
                        'a' => $agence
                    ]);
                    $clients[$codeClient] = true;
                    $nbClients++;
                }
                
                // Insérer ventes - COLONNES CORRIGÉES : 12=Fév, 14=Mars, 16=Avril
                $ventes = [
                    '2026-02-01' => intval($row[12] ?? 0),
                    '2026-03-01' => intval($row[14] ?? 0),
                    '2026-04-01' => intval($row[16] ?? 0)
                ];
                
                foreach ($ventes as $mois => $qte) {
                    if ($qte > 0) {
                        $sql = "INSERT INTO ventes_eclatees (code_cip, code_client, mois, qte_livree) 
                                VALUES (:cip, :cli, :m, :q)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'cip' => $codeCip,
                            'cli' => $codeClient,
                            'm' => $mois,
                            'q' => $qte
                        ]);
                        $nbVentes++;
                    }
                }
            }
            
            $pdo->commit();
            
            echo "<div class='alert alert-success'>";
            echo "<h4>✅ Importation réussie !</h4>";
            echo "<ul>";
            echo "<li>📦 Produits importés : <strong>$nbProduits</strong></li>";
            echo "<li>🏪 Clients importés : <strong>$nbClients</strong></li>";
            echo "<li>💰 Ventes enregistrées : <strong>$nbVentes</strong></li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<a href='../index.php' class='btn btn-primary btn-lg'>📊 Voir le tableau de bord</a>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>❌ Erreur : " . $e->getMessage() . "</div>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        
        @unlink($uploadFile);
        
    } else {
        echo "<div class='alert alert-danger'>❌ Erreur lors de l'upload</div>";
    }
    
    echo "</div></div>";
}
?>

<div class='card mt-4'>
    <div class='card-header bg-primary text-white'><h5>Importer un fichier Excel</h5></div>
    <div class='card-body'>
        <form method='POST' enctype='multipart/form-data'>
            <div class='mb-3'>
                <label class='form-label'>Fichier Excel (.xlsx) - Feuille "éclatée"</label>
                <input type='file' name='excel_file' class='form-control' accept='.xlsx' required>
            </div>
            <button type='submit' class='btn btn-success btn-lg'>🚀 Importer les données</button>
        </form>
    </div>
</div>

</div></body></html>";