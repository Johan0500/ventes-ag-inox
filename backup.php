<?php
$pageTitle = "Sauvegarde";
require_once __DIR__ . '/config/database.php';

$action = $_GET['action'] ?? '';

if ($action == 'export') {
    // Exporter toute la base en SQL
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="inox_backup_' . date('Y-m-d') . '.sql"');
    
    $tables = ['produits', 'clients', 'ventes_eclatees'];
    foreach ($tables as $table) {
        $rows = $pdo->query("SELECT * FROM $table")->fetchAll();
        foreach ($rows as $row) {
            $values = array_map(function($v) use ($pdo) { return $pdo->quote($v); }, array_values($row));
            echo "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
        }
    }
    exit;
}

if ($action == 'reset' && isset($_POST['confirm'])) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE ventes_eclatees");
    $pdo->exec("TRUNCATE TABLE produits");
    $pdo->exec("TRUNCATE TABLE clients");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $message = "✅ Base de données réinitialisée";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Sauvegarde - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h3>💾 Sauvegarde et Restauration</h3>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h4>📥 Exporter</h4>
                        <p>Téléchargez une sauvegarde SQL de la base</p>
                        <a href="?action=export" class="btn btn-primary">Télécharger la sauvegarde</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h4>⚠️ Réinitialiser</h4>
                        <p>Supprime TOUTES les données</p>
                        <form method="POST" action="?action=reset" onsubmit="return confirm('Vider TOUTES les données ?')">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-danger">Réinitialiser la base</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>