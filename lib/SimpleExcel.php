<?php
// lib/SimpleExcel.php

class SimpleExcel {
    
    /**
     * Lit un fichier Excel .xlsx
     * 
     * @param string $filePath Chemin du fichier Excel
     * @param int $sheetIndex Index de la feuille (1 = première)
     * @return array Données de la feuille
     * @throws Exception Si le fichier ne peut pas être lu
     */
    public static function readXLSX(string $filePath, int $sheetIndex = 1): array {
        if (!file_exists($filePath)) {
            throw new Exception("Fichier non trouvé : $filePath");
        }
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception("Impossible d'ouvrir le fichier Excel");
        }
        
        // Lire les chaînes partagées
        $sharedStrings = [];
        $sharedStringsPath = 'xl/sharedStrings.xml';
        if ($zip->locateName($sharedStringsPath) !== false) {
            $xml = simplexml_load_string($zip->getFromName($sharedStringsPath));
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
        
        // Déterminer le fichier de feuille à lire
        $sheetFile = "xl/worksheets/sheet" . $sheetIndex . ".xml";
        if ($zip->locateName($sheetFile) === false) {
            $zip->close();
            throw new Exception("Feuille $sheetIndex non trouvée");
        }
        
        // Lire la feuille de calcul
        $xmlContent = $zip->getFromName($sheetFile);
        if ($xmlContent === false) {
            $zip->close();
            throw new Exception("Impossible de lire la feuille $sheetIndex");
        }
        
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            $zip->close();
            throw new Exception("XML invalide dans la feuille $sheetIndex");
        }
        
        $data = [];
        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                if (isset($row->c)) {
                    foreach ($row->c as $cell) {
                        $cellType = (string)$cell['t'];
                        $value = (string)$cell->v;
                        
                        // Convertir les références de chaînes partagées
                        if ($cellType === 's' && isset($sharedStrings[(int)$value])) {
                            $value = $sharedStrings[(int)$value];
                        }
                        // Convertir les nombres
                        elseif ($cellType === 'n' || $cellType === '') {
                            if (is_numeric($value)) {
                                $value = $value + 0; // Convertir en nombre
                            }
                        }
                        
                        $rowData[] = $value;
                    }
                }
                
                if (!empty($rowData)) {
                    $data[] = $rowData;
                }
            }
        }
        
        $zip->close();
        return $data;
    }
    
    /**
     * Détecte l'index d'une feuille par son nom
     * 
     * @param string $filePath Chemin du fichier Excel
     * @param string $sheetName Nom de la feuille
     * @return int Index de la feuille
     */
    public static function getSheetIndex(string $filePath, string $sheetName): int {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception("Impossible d'ouvrir le fichier");
        }
        
        // Lire le classeur
        $workbookContent = $zip->getFromName('xl/workbook.xml');
        if ($workbookContent === false) {
            $zip->close();
            return 1;
        }
        
        $workbookXml = simplexml_load_string($workbookContent);
        if ($workbookXml === false) {
            $zip->close();
            return 1;
        }
        
        $index = 1;
        if (isset($workbookXml->sheets->sheet)) {
            foreach ($workbookXml->sheets->sheet as $sheet) {
                if ((string)$sheet['name'] === $sheetName) {
                    $zip->close();
                    return $index;
                }
                $index++;
            }
        }
        
        $zip->close();
        return 1; // Retourne la première feuille par défaut
    }
}
?>