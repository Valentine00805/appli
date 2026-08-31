<?php
declare(strict_types=1);

/**
 * Lecture d'un classeur Excel (.xlsx) sans bibliothèque externe.
 *
 * Renvoie les lignes non vides de la première feuille, chaque ligne étant un
 * tableau indexé par lettre de colonne (A, B, C…). Les dates restent des
 * numéros de série Excel : c'est à l'appelant de décider si une valeur en est
 * une, le format n'étant pas fiable d'un tableur à l'autre.
 */
final class ClasseurLecteur
{
    /** Au-delà, on considère que ce n'est pas un relevé personnel. */
    public const LIGNES_MAX = 5000;

    /** Date de référence d'Excel. */
    private const EPOQUE = '1899-12-30';

    /**
     * @return array<int, array<string, string>> indexé par numéro de ligne
     * @throws RuntimeException si le fichier n'est pas un classeur exploitable
     */
    public static function lire(string $chemin): array
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Ce fichier n\'est pas un classeur Excel lisible.');
        }

        try {
            $chaines = self::chainesPartagees($zip);
            $feuille = self::premiereFeuille($zip);
            if ($feuille === null) {
                throw new RuntimeException('Aucune feuille trouvée dans le classeur.');
            }

            $doc = @simplexml_load_string($feuille);
            if ($doc === false) {
                throw new RuntimeException('La feuille du classeur est illisible.');
            }

            $lignes = [];
            foreach ($doc->sheetData->row as $row) {
                $numero = (int) $row['r'];
                $cellules = [];
                foreach ($row->c as $cellule) {
                    $colonne = preg_replace('/\d+/', '', (string) $cellule['r']) ?? '';
                    $valeur = self::valeur($cellule, $chaines);
                    if ($valeur !== '') {
                        $cellules[$colonne] = $valeur;
                    }
                }
                if ($cellules !== []) {
                    $lignes[$numero] = $cellules;
                }
                if (count($lignes) > self::LIGNES_MAX) {
                    break;
                }
            }

            return $lignes;
        } finally {
            $zip->close();
        }
    }

    /** Convertit un numéro de série Excel en date AAAA-MM-JJ. */
    public static function depuisSerial(float $serial): ?string
    {
        // Bornes larges mais raisonnables : de 1990 à 2100.
        if ($serial < 32874 || $serial > 73415) {
            return null;
        }
        $base = new DateTimeImmutable(self::EPOQUE . ' 00:00:00', new DateTimeZone('UTC'));
        return $base->modify('+' . (int) floor($serial) . ' days')->format('Y-m-d');
    }

    /** Une valeur est-elle un nombre exploitable comme montant ? */
    public static function estNombre(string $valeur): bool
    {
        return $valeur !== '' && is_numeric(str_replace(',', '.', trim($valeur)));
    }

    public static function nombre(string $valeur): float
    {
        return (float) str_replace(',', '.', trim($valeur));
    }

    // --- Interne -------------------------------------------------------------

    private static function valeur(SimpleXMLElement $cellule, array $chaines): string
    {
        $type = (string) $cellule['t'];

        if ($type === 'inlineStr') {
            $texte = '';
            foreach ($cellule->xpath('.//*[local-name()="t"]') ?: [] as $noeud) {
                $texte .= (string) $noeud;
            }
            return trim($texte);
        }

        $brut = (string) $cellule->v;
        if ($type === 's') {
            return trim($chaines[(int) $brut] ?? '');
        }
        if ($type === 'e') {
            // Cellule en erreur (#REF!, #DIV/0!…) : on l'ignore.
            return '';
        }
        return trim($brut);
    }

    private static function chainesPartagees(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $chaines = [];
        foreach ($doc->si as $si) {
            // Une chaîne peut être découpée en fragments mis en forme.
            $texte = '';
            foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $noeud) {
                $texte .= (string) $noeud;
            }
            $chaines[] = trim($texte);
        }
        return $chaines;
    }

    /** La feuille référencée en premier dans le classeur. */
    private static function premiereFeuille(ZipArchive $zip): ?string
    {
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $candidat) {
            $xml = $zip->getFromName($candidat);
            if ($xml !== false) {
                return $xml;
            }
        }
        // Repli : la première feuille trouvée dans l'archive.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $zip->getNameIndex($i);
            if (is_string($nom) && str_starts_with($nom, 'xl/worksheets/') && str_ends_with($nom, '.xml')) {
                $xml = $zip->getFromName($nom);
                return $xml === false ? null : $xml;
            }
        }
        return null;
    }
}
