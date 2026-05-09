<?php
require_once __DIR__ . '/config/database.php';

// Supprimer les anciens utilisateurs labo
$pdo->exec("DELETE FROM utilisateurs WHERE username IN ('croient', 'licpharma')");

// Créer croient (mot de passe : croient123)
$hash1 = password_hash('croient123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO utilisateurs (username, password, nom, role, labo, active) VALUES (?, ?, ?, ?, ?, 1)")->execute(['croient', $hash1, 'Utilisateur Croient', 'user', 'croient']);

// Créer licpharma (mot de passe : lic123)
$hash2 = password_hash('lic123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO utilisateurs (username, password, nom, role, labo, active) VALUES (?, ?, ?, ?, ?, 1)")->execute(['licpharma', $hash2, 'Utilisateur LIC Pharma', 'user', 'licpharma']);

echo "<h2>✅ Utilisateurs créés !</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Utilisateur</th><th>Mot de passe</th><th>Labo</th></tr>";
echo "<tr><td>admin</td><td>admin123</td><td>Tous (admin)</td></tr>";
echo "<tr><td>croient</td><td>croient123</td><td>Croient Pharma</td></tr>";
echo "<tr><td>licpharma</td><td>lic123</td><td>LIC Pharma</td></tr>";
echo "</table>";
echo "<p><strong>⚠️ Supprimez ce fichier après usage !</strong></p>";