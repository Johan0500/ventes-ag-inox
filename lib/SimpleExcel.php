<?php
// lib/SimpleExcel.php

class SimpleExcel {
    
    /**
     * Lit un fichier Excel .xlsx
     */
    public static function readXLSX($filePath, $sheetIndex = 1) {
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
            $namespaces = $xml->getNamespaces(true);
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
        
        // Déterminer le fichier de feuille à lire
        $sheetFile = "xl/worksheets/sheet" . $sheetIndex . ".xml";
        if ($zip->locateName($sheetFile) === false) {
            $zip->close();
            throw new Exception("Feuille $sheetIndex non trouvée");
        }
        
        // Lire la feuille de calcul
        $xml = simplexml_load_string($zip->getFromName($sheetFile));
        $namespaces = $xml->getNamespaces(true);
        
        $data = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $cells = $row->c;
            
            foreach ($cells as $cell) {
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
            
            if (!empty($rowData)) {
                $data[] = $rowData;
            }
        }
        
        $zip->close();
        return $data;
    }
    
    /**
     * Détecte l'index d'une feuille par son nom
     */
    public static function getSheetIndex($filePath, $sheetName) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception("Impossible d'ouvrir le fichier");
        }
        
        // Lire le classeur
        $workbookXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $index = 1;
        
        foreach ($workbookXml->sheets->sheet as $sheet) {
            if ((string)$sheet['name'] === $sheetName) {
                $zip->close();
                return $index;
            }
            $index++;
        }
        
        $zip->close();
        return 1; // Retourne la première feuille par défaut
    }
}
?>