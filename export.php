<?php
$pageTitle = "Export Données";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

$format = $_GET['format'] ?? '';
$type = $_GET['type'] ?? '';

// Si export demandé
if ($format == 'excel' && $type) {
    $filename = "inox_pharma_{$type}_" . date('Y-m-d') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style>td{border:1px solid #ccc;padding:5px;}th{background:#1a73e8;color:white;padding:8px;}</style></head><body>';
    
    if ($type == 'produits') {
        echo '<h2>Liste des Produits - INOX PHARMA</h2>';
        echo '<table><tr><th>Code CIP</th><th>Libellé</th><th>Prix Cession</th><th>Prix Public</th><th>Total Vendus</th><th>Nb Clients</th><th>CA Estimé</th></tr>';
        
        $data = $pdo->query("SELECT p.*, COALESCE(SUM(v.qte_livree),0) as total, COUNT(DISTINCT v.code_client) as clients, COALESCE(SUM(v.qte_livree*p.prix_cession),0) as ca FROM produits p LEFT JOIN ventes_eclatees v ON p.code_cip=v.code_cip GROUP BY p.code_cip ORDER BY total DESC")->fetchAll();
        
        foreach ($data as $row) {
            echo "<tr>
                <td>{$row['code_cip']}</td>
                <td>{$row['libelle']}</td>
                <td align='right'>".number_format($row['prix_cession'],0,',',' ')."</td>
                <td align='right'>".number_format($row['prix_public'],0,',',' ')."</td>
                <td align='right'><b>".number_format($row['total'])."</b></td>
                <td align='center'>{$row['clients']}</td>
                <td align='right'>".number_format($row['ca'],0,',',' ')."</td>
            </tr>";
        }
        echo '</table>';
        
        // Top 10 + graphique
        $top10 = array_slice($data, 0, 10);
        echo '<br><h3>Top 10 Produits</h3>';
        echo '<table><tr><th>#</th><th>Produit</th><th>Ventes</th><th>Barre</th></tr>';
        $max = max(array_column($top10, 'total')) ?: 1;
        foreach ($top10 as $i => $p) {
            $pct = ($p['total']/$max)*100;
            $bar = str_repeat('█', round($pct/2));
            echo "<tr><td>".($i+1)."</td><td>{$p['libelle']}</td><td align='right'>".number_format($p['total'])."</td><td style='color:#1a73e8;'>{$bar} {$pct}%</td></tr>";
        }
        echo '</table>';
        
    } elseif ($type == 'clients') {
        echo '<h2>Liste des Clients - INOX PHARMA</h2>';
        echo '<table><tr><th>Code</th><th>Client</th><th>Province</th><th>Agence</th><th>Total Acheté</th><th>Nb Produits</th></tr>';
        
        $data = $pdo->query("SELECT c.*, COALESCE(SUM(v.qte_livree),0) as total, COUNT(DISTINCT v.code_cip) as nb_produits FROM clients c LEFT JOIN ventes_eclatees v ON c.code_client=v.code_client GROUP BY c.code_client ORDER BY total DESC")->fetchAll();
        
        foreach ($data as $row) {
            echo "<tr>
                <td>{$row['code_client']}</td>
                <td>{$row['designation']}</td>
                <td>{$row['province']}</td>
                <td>{$row['agence']}</td>
                <td align='right'><b>".number_format($row['total'])."</b></td>
                <td align='center'>{$row['nb_produits']}</td>
            </tr>";
        }
        echo '</table>';
        
    } elseif ($type == 'ventes') {
        echo '<h2>Détail des Ventes - INOX PHARMA</h2>';
        echo '<table><tr><th>Date</th><th>Code CIP</th><th>Produit</th><th>Client</th><th>Province</th><th>Agence</th><th>Quantité</th><th>Prix Unit.</th><th>Total</th></tr>';
        
        $data = $pdo->query("SELECT v.mois, v.code_cip, p.libelle as produit, v.code_client, c.designation as client, c.province, c.agence, v.qte_livree, p.prix_cession FROM ventes_eclatees v JOIN produits p ON v.code_cip=p.code_cip JOIN clients c ON v.code_client=c.code_client ORDER BY v.mois DESC, v.qte_livree DESC LIMIT 5000")->fetchAll();
        
        foreach ($data as $row) {
            $total = $row['qte_livree'] * $row['prix_cession'];
            echo "<tr>
                <td>".date('d/m/Y', strtotime($row['mois']))."</td>
                <td>{$row['code_cip']}</td>
                <td>{$row['produit']}</td>
                <td>{$row['client']}</td>
                <td>{$row['province']}</td>
                <td>{$row['agence']}</td>
                <td align='right'>{$row['qte_livree']}</td>
                <td align='right'>".number_format($row['prix_cession'],0,',',' ')."</td>
                <td align='right'><b>".number_format($total,0,',',' ')."</b></td>
            </tr>";
        }
        echo '</table>';
        
    } elseif ($type == 'agences') {
        echo '<h2>Rapport par Agence - INOX PHARMA</h2>';
        
        $agences = $pdo->query("SELECT c.agence, SUM(v.qte_livree) as total, COUNT(DISTINCT v.code_client) as clients, COUNT(DISTINCT v.code_cip) as produits, SUM(v.qte_livree*p.prix_cession) as ca FROM ventes_eclatees v JOIN clients c ON v.code_client=c.code_client JOIN produits p ON v.code_cip=p.code_cip GROUP BY c.agence ORDER BY total DESC")->fetchAll();
        
        echo '<table><tr><th>Agence</th><th>Total Ventes</th><th>Clients</th><th>Produits</th><th>CA (F CFA)</th><th>Performance</th></tr>';
        
        $max = max(array_column($agences, 'total')) ?: 1;
        foreach ($agences as $ag) {
            $pct = ($ag['total']/$max)*100;
            $bar = str_repeat('█', round($pct/5));
            echo "<tr>
                <td><b>{$ag['agence']}</b></td>
                <td align='right'>".number_format($ag['total'])."</td>
                <td align='center'>{$ag['clients']}</td>
                <td align='center'>{$ag['produits']}</td>
                <td align='right'>".number_format($ag['ca'],0,',',' ')."</td>
                <td style='color:#0d904f;'>{$bar} ".round($pct,1)."%</td>
            </tr>";
        }
        echo '</table>';
        
        // Top 5 provinces par agence
        echo '<br><h3>Top 5 Provinces par Agence</h3>';
        $agencesList = $pdo->query("SELECT DISTINCT agence FROM clients")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($agencesList as $agence) {
            echo "<h4>$agence</h4>";
            $provinces = $pdo->prepare("SELECT c.province, SUM(v.qte_livree) as total FROM ventes_eclatees v JOIN clients c ON v.code_client=c.code_client WHERE c.agence=? GROUP BY c.province ORDER BY total DESC LIMIT 5");
            $provinces->execute([$agence]);
            
            echo '<table><tr><th>Province</th><th>Total</th></tr>';
            foreach ($provinces->fetchAll() as $prov) {
                echo "<tr><td>{$prov['province']}</td><td align='right'>".number_format($prov['total'])."</td></tr>";
            }
            echo '</table><br>';
        }
    }
    
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/header.php';
?>

<h3>📥 Export des données</h3>
<p class="text-muted">Téléchargez vos rapports au format Excel avec graphiques intégrés</p>

<div class="row g-4 mt-2">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center h-100">
            <div class="card-body">
                <div style="font-size:3rem;">📦</div>
                <h5>Catalogue Produits</h5>
                <p class="text-muted small">Liste complète avec ventes, CA et graphique Top 10</p>
                <a href="/inox-pharma-ventes/export.php?format=excel&type=produits" class="btn btn-primary">📥 Télécharger</a>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center h-100">
            <div class="card-body">
                <div style="font-size:3rem;">🏪</div>
                <h5>Fichier Clients</h5>
                <p class="text-muted small">Liste de tous les clients avec statistiques d'achats</p>
                <a href="/inox-pharma-ventes/export.php?format=excel&type=clients" class="btn btn-success">📥 Télécharger</a>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center h-100">
            <div class="card-body">
                <div style="font-size:3rem;">💰</div>
                <h5>Ventes Détaillées</h5>
                <p class="text-muted small">Toutes les transactions avec montants (max 5000 lignes)</p>
                <a href="/inox-pharma-ventes/export.php?format=excel&type=ventes" class="btn btn-info text-white">📥 Télécharger</a>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center h-100">
            <div class="card-body">
                <div style="font-size:3rem;">🏢</div>
                <h5>Rapport Agences</h5>
                <p class="text-muted small">Performance par agence avec top provinces</p>
                <a href="/inox-pharma-ventes/export.php?format=excel&type=agences" class="btn btn-warning">📥 Télécharger</a>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5>📊 Aperçu des données exportables</h5></div>
    <div class="card-body">
        <div class="row text-center">
            <?php
            $nbP = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
            $nbC = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $nbV = $pdo->query("SELECT COUNT(*) FROM ventes_eclatees")->fetchColumn();
            $nbA = $pdo->query("SELECT COUNT(DISTINCT agence) FROM clients")->fetchColumn();
            ?>
            <div class="col-md-3"><h3><?php echo $nbP; ?></h3><p class="text-muted">Produits</p></div>
            <div class="col-md-3"><h3><?php echo $nbC; ?></h3><p class="text-muted">Clients</p></div>
            <div class="col-md-3"><h3><?php echo $nbV; ?></h3><p class="text-muted">Ventes</p></div>
            <div class="col-md-3"><h3><?php echo $nbA; ?></h3><p class="text-muted">Agences</p></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>