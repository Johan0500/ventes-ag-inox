<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic d'importation</h1>";

// Test 1 : Fichiers requis
echo "<h3>1. Vérification des fichiers :</h3>";
echo "<ul>";

$files = [
    '../config/database.php',
    '../lib/SimpleExcel.php',
    '../vendor/autoload.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<li>✅ $file trouvé</li>";
    } else {
        echo "<li>❌ $file MANQUANT</li>";
    }
}
echo "</ul>";

// Test 2 : Connexion base de données
echo "<h3>2. Connexion base de données :</h3>";
try {
    require_once '../config/database.php';
    echo "<p>✅ Connexion réussie</p>";
    
    // Vérifier les tables
    $tables = ['produits', 'clients', 'ventes_eclatees'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p>✅ Table '$table' : $count enregistrements</p>";
        } catch (Exception $e) {
            echo "<p>❌ Table '$table' : " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Erreur connexion : " . $e->getMessage() . "</p>";
}

// Test 3 : Afficher les erreurs PHP
echo "<h3>3. Configuration PHP :</h3>";
echo "<ul>";
echo "<li>Version PHP : " . phpversion() . "</li>";
echo "<li>display_errors : " . ini_get('display_errors') . "</li>";
echo "<li>error_reporting : " . ini_get('error_reporting') . "</li>";
echo "<li>max_execution_time : " . ini_get('max_execution_time') . "s</li>";
echo "<li>memory_limit : " . ini_get('memory_limit') . "</li>";
echo "<li>upload_max_filesize : " . ini_get('upload_max_filesize') . "</li>";
echo "<li>post_max_size : " . ini_get('post_max_size') . "</li>";
echo "</ul>";

// Test 4 : Vérifier si ZipArchive est disponible
echo "<h3>4. Extensions PHP :</h3>";
echo "<ul>";
echo "<li>ZipArchive : " . (class_exists('ZipArchive') ? '✅ Disponible' : '❌ Manquant') . "</li>";
echo "<li>PDO MySQL : " . (extension_loaded('pdo_mysql') ? '✅ Disponible' : '❌ Manquant') . "</li>";
echo "<li>FileInfo : " . (extension_loaded('fileinfo') ? '✅ Disponible' : '❌ Manquant') . "</li>";
echo "</ul>";

phpinfo();
?>