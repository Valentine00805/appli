<?php
declare(strict_types=1);

/**
 * Écriture d'un classeur Excel (.xlsx) sans bibliothèque externe.
 *
 * Un .xlsx est une archive zip contenant du XML. On n'en génère ici que le
 * strict nécessaire : une feuille, des chaînes en ligne (pas de table de
 * chaînes partagées), et une poignée de styles — gras, dates, montants en
 * euros, lignes de total.
 */
final class ClasseurXlsx
{
    /** Styles disponibles, dans l'ordre où ils sont déclarés dans styles.xml. */
    public const NORMAL      = 0;
    public const GRAS        = 1;
    public const TITRE       = 2;
    public const DATE        = 3;
    public const MONTANT     = 4;
    public const ENTETE      = 5;
    public const MONTANT_GRAS = 6;
    public const TOTAL       = 7;
    public const TOTAL_MONTANT = 8;
    public const DISCRET     = 9;

    /** Date de référence d'Excel : le 0 correspond au 30 décembre 1899. */
    private const EPOQUE = '1899-12-30';

    private array $lignes = [];

    /** @var array<int, float> largeur des colonnes, en caractères */
    private array $largeurs = [];

    public function __construct(private string $nomFeuille = 'Feuille1')
    {
        $this->nomFeuille = mb_substr(str_replace(['\\', '/', '*', '[', ']', ':', '?'], '', $nomFeuille), 0, 31);
        if ($this->nomFeuille === '') {
            $this->nomFeuille = 'Feuille1';
        }
    }

    /**
     * Ajoute une ligne. Chaque cellule est soit une valeur simple, soit
     * ['valeur' => …, 'type' => 'texte|nombre|date', 'style' => self::…].
     */
    public function ligne(array $cellules = []): void
    {
        $this->lignes[] = $cellules;
    }

    public function largeurs(array $largeurs): void
    {
        $this->largeurs = $largeurs;
    }

    /** Construit le fichier et renvoie son contenu binaire. */
    public function contenu(): string
    {
        $temporaire = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($temporaire === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire du classeur.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporaire, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible d\'ouvrir l\'archive du classeur.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsRacine());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->feuille());
        $zip->close();

        $contenu = file_get_contents($temporaire);
        @unlink($temporaire);

        if ($contenu === false) {
            throw new RuntimeException('Lecture du classeur impossible.');
        }
        return $contenu;
    }

    /** Envoie le classeur au navigateur en téléchargement. */
    public function telecharger(string $nomFichier): never
    {
        $contenu = $this->contenu();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Length: ' . strlen($contenu));
        header('X-Content-Type-Options: nosniff');
        header(sprintf(
            "Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
            // Repli ASCII pour les navigateurs anciens, nom complet en UTF-8 ensuite.
            preg_replace('/[^A-Za-z0-9._-]+/', '_', $nomFichier) ?? 'export.xlsx',
            rawurlencode($nomFichier)
        ));
        echo $contenu;
        exit;
    }

    /** Convertit une date AAAA-MM-JJ en numéro de série Excel. */
    public static function serialDate(string $date): int
    {
        $utc = new DateTimeZone('UTC');
        $base = new DateTimeImmutable(self::EPOQUE . ' 00:00:00', $utc);
        $jour = new DateTimeImmutable(substr($date, 0, 10) . ' 00:00:00', $utc);
        return (int) $base->diff($jour)->days;
    }

    // --- Génération du XML ---------------------------------------------------

    private function feuille(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($this->largeurs !== []) {
            $xml .= '<cols>';
            foreach ($this->largeurs as $i => $largeur) {
                $n = (int) $i + 1;
                $xml .= sprintf('<col min="%d" max="%d" width="%s" customWidth="1"/>',
                    $n, $n, number_format((float) $largeur, 2, '.', ''));
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($this->lignes as $numero => $cellules) {
            $ligne = $numero + 1;
            $xml .= '<row r="' . $ligne . '">';
            foreach ($cellules as $colonne => $cellule) {
                $xml .= $this->cellule($this->reference((int) $colonne, $ligne), $cellule);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function cellule(string $reference, mixed $cellule): string
    {
        if (!is_array($cellule)) {
            $cellule = ['valeur' => $cellule];
        }
        $valeur = $cellule['valeur'] ?? '';
        $style = (int) ($cellule['style'] ?? self::NORMAL);
        $type = (string) ($cellule['type'] ?? (is_numeric($valeur) && !is_string($valeur) ? 'nombre' : 'texte'));

        if ($valeur === '' || $valeur === null) {
            return sprintf('<c r="%s" s="%d"/>', $reference, $style);
        }

        if ($type === 'date') {
            return sprintf('<c r="%s" s="%d"><v>%d</v></c>',
                $reference, $style, self::serialDate((string) $valeur));
        }
        if ($type === 'nombre') {
            return sprintf('<c r="%s" s="%d"><v>%s</v></c>',
                $reference, $style, number_format((float) $valeur, 2, '.', ''));
        }

        return sprintf('<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
            $reference, $style, $this->echapper((string) $valeur));
    }

    /** Référence de cellule : 0,1 → A1 ; 26,1 → AA1. */
    private function reference(int $colonne, int $ligne): string
    {
        $lettres = '';
        $n = $colonne;
        do {
            $lettres = chr(65 + ($n % 26)) . $lettres;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        return $lettres . $ligne;
    }

    private function echapper(string $texte): string
    {
        // Excel refuse les caractères de contrôle ; on les retire avant d'échapper.
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texte) ?? $texte;
        return htmlspecialchars($texte, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function relsRacine(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->echapper($this->nomFeuille) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function relsWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="dd/mm/yyyy"/>'
            . '<numFmt numFmtId="165" formatCode="#,##0.00&quot; €&quot;"/>'
            . '</numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF767676"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF0F7"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"><color rgb="FF999999"/></top><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                   // 0 normal
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                     // 1 gras
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                     // 2 titre
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'           // 3 date
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'           // 4 montant
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'       // 5 entete
            . '<xf numFmtId="165" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>' // 6 montant gras
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'     // 7 total
            . '<xf numFmtId="165" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>' // 8 total montant
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                     // 9 discret
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
