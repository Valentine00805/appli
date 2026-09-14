<?php
declare(strict_types=1);

/**
 * Un PDF écrit à la main : des pages A4, du texte, des rectangles, des images.
 *
 * Aucune bibliothèque : le format tient en quelques objets qu'on assemble. Le
 * texte utilise les polices que tout lecteur de PDF possède sans qu'on les lui
 * donne — Helvetica en quatre styles et Courier —, codées en Windows-1252 : les
 * accents français y sont, les emojis non. Les largeurs de caractères sont
 * celles des métriques Adobe, pour que les lignes se coupent au bon endroit.
 *
 * Les coordonnées suivent le PDF : en points, l'origine en bas à gauche.
 */
final class PdfSimple
{
    public const LARGEUR = 595.28;
    public const HAUTEUR = 841.89;

    /** Les polices, et le nom sous lequel les pages les appellent. */
    private const POLICES = [
        'F1' => 'Helvetica',
        'F2' => 'Helvetica-Bold',
        'F3' => 'Helvetica-Oblique',
        'F4' => 'Helvetica-BoldOblique',
        'F5' => 'Courier',
    ];

    /** @var list<string> le contenu de chaque page, dans l'ordre */
    private array $pages = [];

    /** @var list<array{largeur: int, hauteur: int, octets: string}> */
    private array $images = [];

    /** La page où l'on écrit : la dernière ouverte, ou une déjà faite. */
    private int $courante = -1;

    private ?array $largeursNormal = null;
    private ?array $largeursGras = null;

    public function nouvellePage(): void
    {
        $this->pages[] = '';
        $this->courante = count($this->pages) - 1;
    }

    /** Revenir écrire sur une page déjà faite : les numéros de page, une fois le compte connu. */
    public function allerALaPage(int $index): void
    {
        if (isset($this->pages[$index])) {
            $this->courante = $index;
        }
    }

    public function nombreDePages(): int
    {
        return count($this->pages);
    }

    /** UTF-8 vers ce que les polices standard savent écrire. */
    public static function encoder(string $texte): string
    {
        $texte = strtr($texte, [
            "\u{2192}" => '->', "\u{2190}" => '<-', "\u{21D2}" => '=>', "\u{2194}" => '<->',
            "\u{2264}" => '<=', "\u{2265}" => '>=', "\u{2260}" => '!=', "\u{2248}" => '~',
            "\u{202F}" => ' ', "\u{2009}" => ' ', "\u{2007}" => ' ', "\u{200B}" => '',
            "\u{2011}" => '-', "\u{2212}" => '-', "\u{2713}" => 'v', "\u{2714}" => 'v',
            // Les lettres en exposant des ordinaux : 1ᵉʳ, 2ᵉ, 1ʳᵉ, 2ⁿᵈ.
            "\u{1D49}" => 'e', "\u{02B3}" => 'r', "\u{1D48}" => 'd', "\u{207F}" => 'n', "\u{02E2}" => 's',
            "\u{1D52}" => 'o', "\u{1D57}" => 't', "\u{2070}" => '0', "\u{2074}" => '4', "\u{2075}" => '5',
            "\u{2076}" => '6', "\u{2077}" => '7', "\u{2078}" => '8', "\u{2079}" => '9',
            "\u{2081}" => '1', "\u{2082}" => '2', "\u{2083}" => '3', "\u{2080}" => '0',
            "\u{25CF}" => "\u{2022}", "\u{25AA}" => "\u{2022}", "\u{25E6}" => "\u{2022}", "\u{2043}" => '-',
            "\u{2605}" => '*', "\u{2610}" => '[ ]', "\u{2611}" => '[x]', "\u{2612}" => '[x]',
            "\t" => ' ',
        ]);
        // Emojis et pictogrammes : aucune police standard ne les dessine.
        $texte = (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{E000}-\x{F8FF}]/u', '', $texte);

        $avant = mb_substitute_character();
        mb_substitute_character(0x3F);
        $code = (string) mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
        mb_substitute_character($avant);

        return $code;
    }

    /** La largeur, en points, d'un texte déjà encodé. */
    public function largeur(string $code, string $police, float $taille): float
    {
        if ($police === 'F5') {
            return strlen($code) * 0.6 * $taille;
        }
        $table = in_array($police, ['F2', 'F4'], true) ? $this->gras() : $this->normal();
        $total = 0;
        $n = strlen($code);
        for ($i = 0; $i < $n; $i++) {
            $total += $table[ord($code[$i])] ?? 556;
        }

        return $total * $taille / 1000;
    }

    /**
     * @param array{0: float, 1: float, 2: float} $couleur de 0 à 1
     */
    public function texte(float $x, float $y, string $code, string $police, float $taille, array $couleur = [0, 0, 0]): void
    {
        $echappe = strtr($code, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => ' ']);
        $this->ajouter(sprintf('BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET',
            $police, $taille, $couleur[0], $couleur[1], $couleur[2], $x, $y, $echappe));
    }

    /** @param array{0: float, 1: float, 2: float} $couleur */
    public function rectangle(float $x, float $y, float $largeur, float $hauteur, array $couleur): void
    {
        $this->ajouter(sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f',
            $couleur[0], $couleur[1], $couleur[2], $x, $y, $largeur, $hauteur));
    }

    /**
     * Une image, déjà passée par GD : un JPEG en couleurs, que le PDF garde tel
     * quel. Rend son numéro, à poser ensuite où l'on veut.
     */
    public function ajouterImage(string $jpeg, int $largeur, int $hauteur): int
    {
        $this->images[] = ['largeur' => $largeur, 'hauteur' => $hauteur, 'octets' => $jpeg];

        return count($this->images) - 1;
    }

    public function poserImage(int $numero, float $x, float $y, float $largeur, float $hauteur): void
    {
        $this->ajouter(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $largeur, $hauteur, $x, $y, $numero));
    }

    /** Le fichier entier. */
    public function sortie(string $titre): string
    {
        if ($this->pages === []) {
            $this->nouvellePage();
        }

        $objets = [];
        $reserver = static function () use (&$objets): int {
            $objets[] = '';
            return count($objets);
        };

        $catalogue = $reserver();
        $pagesRacine = $reserver();

        $polices = [];
        foreach (self::POLICES as $cle => $nom) {
            $n = $reserver();
            $encodage = $nom === 'Courier' || str_starts_with($nom, 'Helvetica') ? ' /Encoding /WinAnsiEncoding' : '';
            $objets[$n - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . $nom . $encodage . ' >>';
            $polices[] = '/' . $cle . ' ' . $n . ' 0 R';
        }

        $xobjets = [];
        foreach ($this->images as $i => $image) {
            $n = $reserver();
            $objets[$n - 1] = "<< /Type /XObject /Subtype /Image /Width {$image['largeur']} /Height {$image['hauteur']}"
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($image['octets'])
                . " >>\nstream\n" . $image['octets'] . "\nendstream";
            $xobjets[] = '/Im' . $i . ' ' . $n . ' 0 R';
        }

        $ressources = '<< /Font << ' . implode(' ', $polices) . ' >>'
            . ($xobjets === [] ? '' : ' /XObject << ' . implode(' ', $xobjets) . ' >>') . ' >>';

        $enfants = [];
        foreach ($this->pages as $flux) {
            $compresse = (string) gzcompress($flux, 6);
            $contenu = $reserver();
            $objets[$contenu - 1] = '<< /Length ' . strlen($compresse) . " /Filter /FlateDecode >>\nstream\n"
                . $compresse . "\nendstream";
            $page = $reserver();
            $objets[$page - 1] = '<< /Type /Page /Parent ' . $pagesRacine . ' 0 R'
                . sprintf(' /MediaBox [0 0 %.2F %.2F]', self::LARGEUR, self::HAUTEUR)
                . ' /Resources ' . $ressources . ' /Contents ' . $contenu . ' 0 R >>';
            $enfants[] = $page . ' 0 R';
        }

        $objets[$catalogue - 1] = '<< /Type /Catalog /Pages ' . $pagesRacine . ' 0 R >>';
        $objets[$pagesRacine - 1] = '<< /Type /Pages /Kids [' . implode(' ', $enfants) . '] /Count ' . count($enfants) . ' >>';

        $info = $reserver();
        $objets[$info - 1] = '<< /Title ' . self::chaineUnicode($titre) . ' /Producer (Mes Cours)'
            . ' /CreationDate (D:' . date('YmdHis') . ') >>';

        $sortie = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $positions = [];
        foreach ($objets as $i => $corps) {
            $positions[] = strlen($sortie);
            $sortie .= ($i + 1) . " 0 obj\n" . $corps . "\nendobj\n";
        }

        $xref = strlen($sortie);
        $sortie .= "xref\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $sortie .= sprintf("%010d 00000 n \n", $position);
        }

        return $sortie . 'trailer << /Size ' . (count($objets) + 1) . ' /Root ' . $catalogue . ' 0 R /Info ' . $info . " 0 R >>\n"
            . "startxref\n" . $xref . "\n%%EOF\n";
    }

    /* --- Interne ---------------------------------------------------------- */

    private function ajouter(string $operation): void
    {
        if ($this->pages === []) {
            $this->nouvellePage();
        }
        $this->pages[$this->courante] .= $operation . "\n";
    }

    private static function chaineUnicode(string $texte): string
    {
        return '<FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($texte, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    /** @return array<int, int> les largeurs de Helvetica, en millièmes de la taille */
    private function normal(): array
    {
        return $this->largeursNormal ??= self::table(
            ' 278 ! 278 " 355 # 556 $ 556 % 889 & 667 \' 191 ( 333 ) 333 * 389 + 584 , 278 - 333 . 278 / 278'
            . ' 0 556 1 556 2 556 3 556 4 556 5 556 6 556 7 556 8 556 9 556 : 278 ; 278 < 584 = 584 > 584 ? 556 @ 1015'
            . ' A 667 B 667 C 722 D 722 E 667 F 611 G 778 H 722 I 278 J 500 K 667 L 556 M 833 N 722 O 778 P 667 Q 778'
            . ' R 722 S 667 T 611 U 722 V 667 W 944 X 667 Y 667 Z 611 [ 278 \\ 278 ] 278 ^ 469 _ 556 ` 333'
            . ' a 556 b 556 c 500 d 556 e 556 f 278 g 556 h 556 i 222 j 222 k 500 l 222 m 833 n 556 o 556 p 556 q 556'
            . ' r 333 s 500 t 278 u 556 v 500 w 722 x 500 y 500 z 500 { 334 | 260 } 334 ~ 584',
            [128 => 556, 130 => 222, 131 => 556, 132 => 333, 133 => 1000, 134 => 556, 135 => 556, 136 => 333,
             137 => 1000, 138 => 667, 139 => 333, 140 => 1000, 142 => 611, 145 => 222, 146 => 222, 147 => 333,
             148 => 333, 149 => 350, 150 => 556, 151 => 1000, 152 => 333, 153 => 1000, 154 => 500, 155 => 333,
             156 => 944, 158 => 500, 159 => 667, 160 => 278, 161 => 333, 162 => 556, 163 => 556, 164 => 556,
             165 => 556, 166 => 260, 167 => 556, 168 => 333, 169 => 737, 170 => 370, 171 => 556, 172 => 584,
             173 => 333, 174 => 737, 175 => 333, 176 => 400, 177 => 584, 178 => 333, 179 => 333, 180 => 333,
             181 => 556, 182 => 537, 183 => 278, 184 => 333, 185 => 333, 186 => 365, 187 => 556, 188 => 834,
             189 => 834, 190 => 834, 191 => 611, 198 => 1000, 199 => 722, 208 => 722, 209 => 722, 215 => 584,
             216 => 778, 221 => 667, 222 => 667, 223 => 611, 230 => 889, 231 => 500, 240 => 556, 241 => 556,
             247 => 584, 248 => 611, 253 => 500, 254 => 556, 255 => 500]
            + array_fill_keys(range(192, 197), 667) + array_fill_keys(range(200, 203), 667)
            + array_fill_keys(range(204, 207), 278) + array_fill_keys(range(210, 214), 778)
            + array_fill_keys(range(217, 220), 722) + array_fill_keys(range(224, 229), 556)
            + array_fill_keys(range(232, 235), 556) + array_fill_keys(range(236, 239), 278)
            + array_fill_keys(range(242, 246), 556) + array_fill_keys(range(249, 252), 556)
        );
    }

    /** @return array<int, int> les largeurs de Helvetica-Bold */
    private function gras(): array
    {
        return $this->largeursGras ??= self::table(
            ' 278 ! 333 " 474 # 556 $ 556 % 889 & 722 \' 238 ( 333 ) 333 * 389 + 584 , 278 - 333 . 278 / 278'
            . ' 0 556 1 556 2 556 3 556 4 556 5 556 6 556 7 556 8 556 9 556 : 333 ; 333 < 584 = 584 > 584 ? 611 @ 975'
            . ' A 722 B 722 C 722 D 722 E 667 F 611 G 778 H 722 I 278 J 556 K 722 L 611 M 833 N 722 O 778 P 667 Q 778'
            . ' R 722 S 667 T 611 U 722 V 667 W 944 X 667 Y 667 Z 611 [ 333 \\ 278 ] 333 ^ 584 _ 556 ` 333'
            . ' a 556 b 611 c 556 d 611 e 556 f 333 g 611 h 611 i 278 j 278 k 556 l 278 m 889 n 611 o 611 p 611 q 611'
            . ' r 389 s 556 t 333 u 611 v 556 w 778 x 556 y 556 z 500 { 389 | 280 } 389 ~ 584',
            [128 => 556, 130 => 278, 131 => 556, 132 => 500, 133 => 1000, 134 => 556, 135 => 556, 136 => 333,
             137 => 1000, 138 => 667, 139 => 333, 140 => 1000, 142 => 611, 145 => 278, 146 => 278, 147 => 500,
             148 => 500, 149 => 350, 150 => 556, 151 => 1000, 152 => 333, 153 => 1000, 154 => 556, 155 => 333,
             156 => 944, 158 => 500, 159 => 667, 160 => 278, 161 => 333, 162 => 556, 163 => 556, 164 => 556,
             165 => 556, 166 => 280, 167 => 556, 168 => 333, 169 => 737, 170 => 370, 171 => 556, 172 => 584,
             173 => 333, 174 => 737, 175 => 333, 176 => 400, 177 => 584, 178 => 333, 179 => 333, 180 => 333,
             181 => 611, 182 => 556, 183 => 278, 184 => 333, 185 => 333, 186 => 365, 187 => 556, 188 => 834,
             189 => 834, 190 => 834, 191 => 611, 198 => 1000, 199 => 722, 208 => 722, 209 => 722, 215 => 584,
             216 => 778, 221 => 667, 222 => 667, 223 => 611, 230 => 889, 231 => 556, 240 => 611, 241 => 611,
             247 => 584, 248 => 611, 253 => 556, 254 => 611, 255 => 556]
            + array_fill_keys(range(192, 197), 722) + array_fill_keys(range(200, 203), 667)
            + array_fill_keys(range(204, 207), 278) + array_fill_keys(range(210, 214), 778)
            + array_fill_keys(range(217, 220), 722) + array_fill_keys(range(224, 229), 556)
            + array_fill_keys(range(232, 235), 556) + array_fill_keys(range(236, 239), 278)
            + array_fill_keys(range(242, 246), 611) + array_fill_keys(range(249, 252), 611)
        );
    }

    /**
     * @param array<int, int> $hauts les codes au-delà de 127
     * @return array<int, int>
     */
    private static function table(string $bas, array $hauts): array
    {
        $table = [];
        $morceaux = explode(' ', $bas);
        // Le premier caractère est l'espace : la chaîne commence par « ␠278 ».
        $table[32] = (int) $morceaux[1];
        for ($i = 2, $n = count($morceaux); $i + 1 < $n; $i += 2) {
            $table[ord($morceaux[$i])] = (int) $morceaux[$i + 1];
        }

        return $table + $hauts;
    }
}
