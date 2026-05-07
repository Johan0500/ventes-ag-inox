<?php
$pageTitle = "Ajouter un nouveau mois";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/SimpleExcel.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadFile = __DIR__ . '/data/temp_nouveau_mois.xlsx';
    $annee = intval($_POST['annee'] ?? date('Y'));
    $viderTables = isset($_POST['vider_tables']);
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
        try {
            $data = SimpleExcel::readXLSX($uploadFile, 2);
            
            // Détection des mois
            $colonnesMois = [];
            $moisMap = [
                'janvier'=>'01','fevrier'=>'02','février'=>'02','mars'=>'03',
                'avril'=>'04','mai'=>'05','juin'=>'06','juillet'=>'07',
                'aout'=>'08','août'=>'08','septembre'=>'09','octobre'=>'10',
                'novembre'=>'11','decembre'=>'12','décembre'=>'12'
            ];
            
            if (count($data) >= 3) {
                $ligne2 = $data[1] ?? [];
                $ligne3 = $data[2] ?? [];
                
                for ($col = 12; $col < min(count($ligne3), 20); $col += 2) {
                    $nomMois = strtolower(trim((string)($ligne2[$col] ?? '')));
                    if (!empty($nomMois) && isset($moisMap[$nomMois])) {
                        $colonnesMois[] = [
                            'colonne' => $col,
                            'date' => "$annee-{$moisMap[$nomMois]}-01"
                        ];
                    }
                }
            }
            
            if (empty($colonnesMois)) {
                // Fallback : colonnes par défaut
                $colonnesMois = [
                    ['colonne'=>12, 'date'=>"$annee-02-01"],
                    ['colonne'=>14, 'date'=>"$annee-03-01"],
                    ['colonne'=>16, 'date'=>"$annee-04-01"]
                ];
            }
            
            if ($viderTables) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                $pdo->exec("TRUNCATE TABLE ventes_eclatees");
                $pdo->exec("TRUNCATE TABLE produits");
                $pdo->exec("TRUNCATE TABLE clients");
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            }
            
            $pdo->beginTransaction();
            
            $nbP = $nbC = $nbV = 0;
            $produits = $clients = [];
            
            for ($i = 3; $i < count($data); $i++) {
                $row = $data[$i];
                if (count($row) < 12) continue;
                
                $codeCip = trim((string)($row[6] ?? ''));
                $libelle = trim((string)($row[7] ?? ''));
                $prixCession = floatval($row[8] ?? 0);
                $prixPublic = floatval($row[9] ?? 0);
                $codeClient = trim((string)($row[4] ?? ''));
                $designation = trim((string)($row[5] ?? ''));
                $province = trim((string)($row[10] ?? ''));
                $agence = trim((string)($row[11] ?? ''));
                
                if (empty($codeCip) || empty($codeClient)) continue;
                
                if (!isset($produits[$codeCip])) {
                    $pdo->prepare("INSERT INTO produits (code_cip,libelle,prix_cession,prix_public) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE libelle=VALUES(libelle)")->execute([$codeCip,$libelle,$prixCession,$prixPublic]);
                    $produits[$codeCip]=true; $nbP++;
                }
                if (!isset($clients[$codeClient])) {
                    $pdo->prepare("INSERT INTO clients (code_client,designation,province,agence) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE designation=VALUES(designation)")->execute([$codeClient,$designation,$province,$agence]);
                    $clients[$codeClient]=true; $nbC++;
                }
                
                foreach ($colonnesMois as $cm) {
                    $qte = intval($row[$cm['colonne']] ?? 0);
                    if ($qte > 0) {
                        $pdo->prepare("INSERT INTO ventes_eclatees (code_cip,code_client,mois,qte_livree) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE qte_livree=qte_livree+VALUES(qte_livree)")->execute([$codeCip,$codeClient,$cm['date'],$qte]);
                        $nbV++;
                    }
                }
            }
            
            $pdo->commit();
            $moisStr = implode(', ', array_map(fn($c)=>date('F Y',strtotime($c['date'])), $colonnesMois));
            $message = "✅ Import réussi ! $nbP produits, $nbC clients, $nbV ventes. Mois importés : $moisStr";
            $type = 'success';
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = "❌ Erreur : " . $e->getMessage();
            $type = 'danger';
        }
        @unlink($uploadFile);
    }
}

// Mois déjà présents
$moisExistants = $pdo->query("SELECT DISTINCT DATE_FORMAT(mois,'%Y-%m') as m FROM ventes_eclatees ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<h3>➕ Ajouter un nouveau mois de données</h3>

<?php if($message): ?>
<div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5>📥 Importer le fichier du mois suivant</h5></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fichier Excel (.xlsx)</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <small class="text-muted">Le fichier doit avoir la même structure que "Stats des ventes ag inox - Avril 2026.xlsx"</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Année des données</label>
                            <select name="annee" class="form-select">
                                <?php for($a=2024;$a<=2030;$a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo $a==date('Y')?'selected':''; ?>><?php echo $a; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="vider_tables" id="vider_tables">
                                <label class="form-check-label" for="vider_tables">⚠️ Vider les tables avant</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">🚀 Ajouter les données du mois</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5>📅 Mois déjà présents</h5></div>
            <div class="card-body">
                <?php if(!empty($moisExistants)): ?>
                <div class="list-group">
                    <?php foreach($moisExistants as $m): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <?php 
                            $date = DateTime::createFromFormat('Y-m', $m);
                            echo $date ? $date->format('F Y') : $m; 
                            ?>
                        </span>
                        <?php 
                        $nb = $pdo->prepare("SELECT COUNT(*) FROM ventes_eclatees WHERE DATE_FORMAT(mois,'%Y-%m')=?");
                        $nb->execute([$m]);
                        ?>
                        <span class="badge bg-primary rounded-pill"><?php echo number_format($nb->fetchColumn()); ?> ventes</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Aucune donnée importée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>