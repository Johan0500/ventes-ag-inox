<?php
$pageTitle = "Accès Interdit";
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';
?>

<div class="text-center py-5">
    <div style="font-size:6rem;">🚫</div>
    <h1 class="mt-3">403 - Accès Interdit</h1>
    <p class="text-muted">Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
    <a href="index.php" class="btn btn-primary mt-3">Retour au Dashboard</a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>