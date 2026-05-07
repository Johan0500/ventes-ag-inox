<?php
require_once __DIR__ . '/lib/SimpleExcel.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Excel - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>📊 Test de lecture du fichier Excel</h1>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">1. Uploadez votre fichier Excel</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">
                            Sélectionnez votre fichier Excel (.xlsx)
                        </label>
                        <input type="file" class="form-control" id="excel_file" 
                               name="excel_file" accept=".xlsx" required>
                    </div>
                    <div class="mb-3">
                        <label for="sheet_index" class="form-label">
                            Numéro de la feuille (1 = première, 2 = deuxième)
                        </label>
                        <input type="number" class="form-control" id="sheet_index" 
                               name="sheet_index" value="2" min="1" max="10" required>
                        <small class="text-muted">Pour la feuille "éclatée", utilisez 2</small>
                    </div>
                    <button type="submit" name="action" value="test" class="btn btn-primary btn-lg">
                        🔍 Analyser le fichier
                    </button>
                </form>
            </div>
        </div>

        <?php
        // Traitement de l'upload
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
            $uploadDir = __DIR__ . '/data/';
            
            // Créer le dossier s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . 'test_upload.xlsx';
            
            echo '<div class="card mb-4">';
            echo '<div class="card-header bg-info text-white">';
            echo '<h5 class="mb-0">2. Résultat de l\'analyse</h5>';
            echo '</div>';
            echo '<div class="card-body">';
            
            if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
                $sheetIndex = intval($_POST['sheet_index'] ?? 2);
                
                echo "<div class='alert alert-success'>";
                echo "✅ Fichier uploadé avec succès !<br>";
                echo "📁 Nom : <strong>" . htmlspecialchars($_FILES['excel_file']['name']) . "</strong><br>";
                echo "📏 Taille : <strong>" . number_format($_FILES['excel_file']['size'] / 1024, 2) . " Ko</strong>";
                echo "</div>";
                
                try {
                    echo "<h4>🔍 Analyse du contenu...</h4>";
                    flush();
                    
                    $data = SimpleExcel::readXLSX($uploadFile, $sheetIndex);
                    
                    echo "<div class='alert alert-success'>";
                    echo "✅ Lecture réussie ! <strong>" . count($data) . "</strong> lignes trouvées.";
                    echo "</div>";
                    
                    // Afficher les en-têtes (3 premières lignes)
                    echo "<h5>📋 Structure détectée :</h5>";
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-bordered table-striped table-sm'>";
                    
                    for ($i = 0; $i < min(4, count($data)); $i++) {
                        echo "<tr>";
                        echo "<td class='fw-bold'>Ligne " . ($i + 1) . "</td>";
                        for ($j = 0; $j < min(19, count($data[$i])); $j++) {
                            $value = $data[$i][$j] ?? '';
                            if (is_string($value) && strlen($value) > 50) {
                                $value = substr($value, 0, 47) . '...';
                            }
                            echo "<td>" . htmlspecialchars((string)$value) . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "</div>";
                    
                    // Afficher quelques données
                    echo "<h5>📊 Aperçu des données (5 premières lignes après les en-têtes) :</h5>";
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-bordered table-hover table-sm'>";
                    echo "<thead class='table-dark'>";
                    echo "<tr>";
                    echo "<th>#</th>";
                    echo "<th>Code Client</th>";
                    echo "<th>Client</th>";
                    echo "<th>Code CIP</th>";
                    echo "<th>Produit</th>";
                    echo "<th>Prix Cess.</th>";
                    echo "<th>Qte Fév</th>";
                    echo "<th>Qte Mar</th>";
                    echo "<th>Qte Avr</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    $count = 0;
                    for ($i = 3; $i < count($data) && $count < 5; $i++) {
                        $row = $data[$i];
                        
                        // Vérifier si la ligne a des données
                        if (count($row) < 7 || empty($row[6])) continue;
                        
                        echo "<tr>";
                        echo "<td>" . ($i + 1) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row[4] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row[5] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row[6] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row[7] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row[8] ?? '')) . "</td>";
                        echo "<td class='text-center'>" . intval($row[12] ?? 0) . "</td>";
                        echo "<td class='text-center'>" . intval($row[15] ?? 0) . "</td>";
                        echo "<td class='text-center'>" . intval($row[18] ?? 0) . "</td>";
                        echo "</tr>";
                        $count++;
                    }
                    echo "</tbody>";
                    echo "</table>";
                    echo "</div>";
                    
                    echo "<div class='alert alert-info mt-3'>";
                    echo "<strong>ℹ️ Prochaine étape :</strong> ";
                    echo "Si les données sont correctes, utilisez ";
                    echo "<a href='import/import.php' class='btn btn-sm btn-success ms-2'>";
                    echo "📥 Importer dans la base de données</a>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h5>❌ Erreur de lecture</h5>";
                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<hr>";
                    echo "<h6>🔧 Solutions :</h6>";
                    echo "<ol>";
                    echo "<li>Vérifiez que le fichier est au format <strong>.xlsx</strong></li>";
                    echo "<li>Vérifiez le numéro de la feuille (essayez 1, 2 ou 3)</li>";
                    echo "<li>Activez l'extension ZIP dans php.ini : <code>extension=zip</code></li>";
                    echo "<li>Le fichier ne doit pas être protégé par mot de passe</li>";
                    echo "</ol>";
                    echo "</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>";
                echo "❌ Erreur lors de l'upload du fichier";
                echo "</div>";
            }
            
            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>