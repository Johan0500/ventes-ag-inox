<?php
require_once 'config/database.php';

try {
    // Test 1 : Vérifier la connexion
    echo "✅ Connexion réussie !<br><br>";
    
    // Test 2 : Vérifier les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Tables trouvées : " . count($tables) . "<br>";
    foreach($tables as $table) {
        echo "- $table<br>";
    }
    
    // Test 3 : Vérifier la structure d'une table
    echo "<br>📊 Structure de la table 'produits' :<br>";
    $columns = $pdo->query("DESCRIBE produits")->fetchAll();
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
    
    echo "<br>🎉 Tout est OK !";
    
} catch(Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
