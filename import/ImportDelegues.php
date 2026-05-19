<?php

require_once __DIR__ . '/../lib/SimpleExcel.php';
require_once __DIR__ . '/../config/database.php';

class ImportDelegues {

    private PDO $pdo;
    private string $mois;
    private string $grossiste_code;
    private int $import_id;
    private array $erreurs = [];
    private array $non_attribues = [];

    private array $cache_secteurs = [];
    private array $cache_delegues = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * POINT D'ENTRÉE PRINCIPAL
     */
    public function importerFichier(string $filepath, string $grossiste_code, string $mois): array {
        $this->grossiste_code = $grossiste_code;
        $this->mois = $mois;

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Fichier introuvable : ' . $filepath];
        }

        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        try {
            $lignes = ($ext === 'csv')
                ? $this->lireCSV($filepath)
                : $this->lireExcelSimple($filepath);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lecture fichier : ' . $e->getMessage()];
        }

        if (empty($lignes)) {
            return ['success' => false, 'message' => 'Fichier vide ou aucune ligne valide trouvée'];
        }

        try {
            $this->import_id = $this->creerLogImport($grossiste_code, basename($filepath), $mois, count($lignes));
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur création log : ' . $e->getMessage()];
        }

        $nb_attribuees = 0;
        $nb_non_attribuees = 0;

        try {
            $this->pdo->beginTransaction();
            
            foreach ($lignes as $index => $ligne) {
                try {
                    $result = $this->traiterLigne($ligne);
                    if ($result === true) {
                        $nb_attribuees++;
                    } else {
                        $nb_non_attribuees++;
                        $this->non_attribues[] = $result;
                    }
                } catch (Exception $e) {
                    $nb_non_attribuees++;
                    $this->non_attribues[] = [
                        'ligne'   => $index + 1,
                        'raison'  => 'Exception : ' . $e->getMessage(),
                        'donnees' => $ligne
                    ];
                }
            }
            
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Erreur transaction : ' . $e->getMessage()];
        }

        try {
            $this->finaliserLogImport($nb_attribuees, $nb_non_attribuees);
        } catch (Exception $e) {
            $this->erreurs[] = 'Erreur mise à jour log : ' . $e->getMessage();
        }

        return [
            'success'           => true,
            'import_id'         => $this->import_id,
            'nb_total'          => count($lignes),
            'nb_attribuees'     => $nb_attribuees,
            'nb_non_attribuees' => $nb_non_attribuees,
            'non_attribues'     => $this->non_attribues,
            'erreurs'           => $this->erreurs,
        ];
    }

    /**
     * TRAITEMENT D'UNE LIGNE
     */
    private function traiterLigne(array $ligne): array|bool {
        $province       = strtoupper(trim($ligne['province'] ?? ''));
        $libelle        = trim($ligne['libelle_article'] ?? '');
        $code_cip       = trim($ligne['code_cip'] ?? '');
        $code_client    = $ligne['code_client'] ?? null;
        $designation    = trim($ligne['designation_client'] ?? '');
        $qte            = intval($ligne['qte_livree'] ?? 0);
        $ug             = intval($ligne['ug_livree'] ?? 0);
        $prix           = floatval($ligne['prix_cession'] ?? 0);

        if (empty($province) || empty($libelle) || $qte <= 0) {
            return [
                'raison'   => 'Ligne incomplète',
                'province' => $province,
                'produit'  => $libelle,
                'qte'      => $qte
            ];
        }

        $secteur_id = $this->trouverSecteur($province);
        if (!$secteur_id) {
            return [
                'raison'   => 'Province non mappée à un secteur',
                'province' => $province,
                'produit'  => $libelle,
                'client'   => $designation
            ];
        }

        $delegue_id = $this->trouverDelegue($secteur_id, $libelle);
        if (!$delegue_id) {
            return [
                'raison'   => 'Aucun délégué pour ce produit dans ce secteur',
                'province' => $province,
                'produit'  => $libelle,
                'client'   => $designation,
                'secteur'  => $secteur_id
            ];
        }

        $sql = "INSERT INTO ventes_delegues
                    (delegue_id, secteur_id, code_client, designation_client, province,
                     code_cip, libelle_article, grossiste_code, mois, qte_livree, ug_livree,
                     prix_cession, statut_attribution, import_id)
                VALUES
                    (:delegue_id, :secteur_id, :code_client, :designation_client, :province,
                     :code_cip, :libelle_article, :grossiste_code, :mois, :qte_livree, :ug_livree,
                     :prix_cession, 'auto', :import_id)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':delegue_id'         => $delegue_id,
            ':secteur_id'         => $secteur_id,
            ':code_client'        => $code_client,
            ':designation_client' => $designation,
            ':province'           => $province,
            ':code_cip'           => $code_cip,
            ':libelle_article'    => $libelle,
            ':grossiste_code'     => $this->grossiste_code,
            ':mois'               => $this->mois,
            ':qte_livree'         => $qte,
            ':ug_livree'          => $ug,
            ':prix_cession'       => $prix,
            ':import_id'          => $this->import_id,
        ]);

        return true;
    }

    /**
     * TROUVER LE SECTEUR À PARTIR DE LA PROVINCE
     */
    private function trouverSecteur(string $province): ?int {
        if (isset($this->cache_secteurs[$province])) {
            return $this->cache_secteurs[$province];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT secteur_id FROM province_secteur
                WHERE UPPER(province_code) = :province
                LIMIT 1
            ");
            $stmt->execute([':province' => $province]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->cache_secteurs[$province] = $row ? (int)$row['secteur_id'] : null;
        } catch (Exception $e) {
            $this->cache_secteurs[$province] = null;
        }
        
        return $this->cache_secteurs[$province];
    }

    /**
     * TROUVER LE DÉLÉGUÉ VIA SECTEUR + PRODUIT
     */
    private function trouverDelegue(int $secteur_id, string $libelle): ?int {
        $cache_key = $secteur_id . '|' . strtoupper($libelle);
        if (isset($this->cache_delegues[$cache_key])) {
            return $this->cache_delegues[$cache_key];
        }

        $libelle_norm = $this->normaliserProduit($libelle);

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT sd.delegue_id
                FROM secteur_delegue sd
                JOIN delegue_produit dp ON dp.delegue_id = sd.delegue_id
                WHERE sd.secteur_id = :secteur_id
                  AND (UPPER(:libelle1) LIKE CONCAT('%', UPPER(dp.libelle_produit), '%')
                       OR UPPER(:libelle2) LIKE CONCAT('%', UPPER(dp.libelle_produit), '%'))
                LIMIT 1
            ");
            $stmt->execute([
                ':secteur_id' => $secteur_id,
                ':libelle1'   => $libelle,
                ':libelle2'   => $libelle_norm
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->cache_delegues[$cache_key] = $row ? (int)$row['delegue_id'] : null;
        } catch (Exception $e) {
            $this->cache_delegues[$cache_key] = null;
        }
        
        return $this->cache_delegues[$cache_key];
    }

    /**
     * NORMALISER LE NOM DU PRODUIT
     */
    private function normaliserProduit(string $libelle): string {
        $libelle = strtoupper(trim($libelle));
        $libelle = preg_replace(
            '/\s+(CP|B\/\d+|MG|ML|INJ|SUSP|SYR|SIROP|COMP|TABLET|TAB|GEL|CREME|SACHET|FLACON|FL|AMP|COFFRET|KIT|GELU)\b.*$/i',
            '',
            $libelle
        );
        return trim($libelle);
    }

    /**
     * LECTURE DU FICHIER EXCEL
     */
    private function lireExcelSimple(string $filepath): array {
        if (!class_exists('SimpleExcel')) {
            return [];
        }
        
        $rows = SimpleExcel::readXLSX($filepath, 2);
        if (count($rows) < 4) {
            $rows = SimpleExcel::readXLSX($filepath, 1);
        }
        
        if (count($rows) < 4) {
            return [];
        }
        
        return $this->parserLignesExcel($rows);
    }

    /**
     * PARSER LES LIGNES EXCEL
     */
    private function parserLignesExcel(array $rows): array {
        $lignes = [];

        $mois_dt  = new DateTime($this->mois . '-01');
        $mois_nom = strtolower($mois_dt->format('F'));
        
        $mois_fr = [
            'january'=>'janvier','february'=>'fevrier','march'=>'mars',
            'april'=>'avril','may'=>'mai','june'=>'juin',
            'july'=>'juillet','august'=>'aout','september'=>'septembre',
            'october'=>'octobre','november'=>'novembre','december'=>'decembre'
        ];
        $mois_fr_nom = $mois_fr[$mois_nom] ?? $mois_nom;
        
        $col_qte = 16;
        $col_ug  = 17;

        if (isset($rows[1])) {
            foreach ([12, 14, 16] as $col) {
                if (!isset($rows[1][$col])) continue;
                $val = strtolower(trim((string)($rows[1][$col] ?? '')));
                if ($val && (strpos($val, substr($mois_fr_nom, 0, 3)) !== false || 
                             strpos($val, substr($mois_nom, 0, 3)) !== false)) {
                    $col_qte = $col;
                    $col_ug  = $col + 1;
                    break;
                }
            }
        }

        for ($i = 3; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 12) continue;
            if (empty($row[5]) && empty($row[7])) continue;
            
            $qte = intval($row[$col_qte] ?? 0);
            if ($qte <= 0) continue;

            $lignes[] = [
                'code_client'        => $row[4]  ?? null,
                'designation_client' => $row[5]  ?? '',
                'code_cip'           => $row[6]  ?? '',
                'libelle_article'    => $row[7]  ?? '',
                'prix_cession'       => $row[8]  ?? 0,
                'province'           => $row[10] ?? '',
                'agence'             => $row[11] ?? '',
                'qte_livree'         => $qte,
                'ug_livree'          => intval($row[$col_ug] ?? 0),
            ];
        }
        return $lignes;
    }

    /**
     * LECTURE CSV
     */
    private function lireCSV(string $filepath): array {
        $lignes = [];
        $handle = fopen($filepath, 'r');
        if (!$handle) return [];

        $first_line = fgets($handle);
        if ($first_line === false) { fclose($handle); return []; }
        
        rewind($handle);
        $sep = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';

        $headers = null;
        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (!$headers) {
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }
            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = $row[$i] ?? '';
            }
            $qte = intval($data['qte livree'] ?? $data['qte_livree'] ?? $data['quantite'] ?? 0);
            if ($qte <= 0) continue;
            $lignes[] = [
                'code_client'        => $data['code client']    ?? $data['code_client'] ?? null,
                'designation_client' => $data['designation client'] ?? $data['client'] ?? '',
                'code_cip'           => $data['code cip']       ?? $data['code_cip'] ?? '',
                'libelle_article'    => $data['libelle article'] ?? $data['libelle_article'] ?? '',
                'prix_cession'       => floatval($data['prix cession'] ?? $data['prix'] ?? 0),
                'province'           => $data['province'] ?? '',
                'agence'             => $data['agence'] ?? '',
                'qte_livree'         => $qte,
                'ug_livree'          => intval($data['ug livree'] ?? $data['ug_livree'] ?? 0),
            ];
        }
        fclose($handle);
        return $lignes;
    }

    private function creerLogImport(string $grossiste, string $fichier, string $mois, int $nb_total): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO imports_delegues (grossiste_code, fichier_nom, mois_import, nb_lignes_total, statut)
            VALUES (:grossiste, :fichier, :mois, :nb, 'en_cours')
        ");
        $stmt->execute([
            ':grossiste' => $grossiste, ':fichier' => $fichier,
            ':mois' => $mois . '-01', ':nb' => $nb_total
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function finaliserLogImport(int $nb_att, int $nb_non): void {
        $stmt = $this->pdo->prepare("
            UPDATE imports_delegues
            SET nb_attribuees = :att, nb_non_attribuees = :non, statut = 'termine'
            WHERE id = :id
        ");
        $stmt->execute([':att' => $nb_att, ':non' => $nb_non, ':id' => $this->import_id]);
    }
}