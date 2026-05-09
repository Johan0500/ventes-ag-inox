<?php
require_once __DIR__ . '/../auth.php';

/**
 * Ajoute la clause WHERE pour filtrer par labo
 */
function addLaboFilter(string $tableAlias = ''): array {
    $labo = getCurrentLabo();
    
    // Admin voit tout
    if ($labo === 'admin' || empty($labo)) {
        return ['sql' => '', 'params' => []];
    }
    
    $prefix = $tableAlias ? "$tableAlias." : "";
    return [
        'sql' => " AND {$prefix}labo = :_labo_filter",
        'params' => ['_labo_filter' => $labo]
    ];
}

/**
 * Ajoute WHERE labo = ? pour les requêtes directes
 */
function getLaboCondition(): string {
    $labo = getCurrentLabo();
    if ($labo === 'admin' || empty($labo)) {
        return "1=1";
    }
    return "labo = '" . addslashes($labo) . "'";
}
?>