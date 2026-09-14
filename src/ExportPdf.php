<?php
declare(strict_types=1);

/**
 * Un document du cours, mis en pages en PDF.
 *
 * Il part de la même lecture que l'aperçu : le texte mis en forme paragraphe par
 * paragraphe — gras, italique, souligné, taille, couleur, surlignage —, les
 * titres, les listes, l'alignement et les images. Ce que l'aperçu ne rend pas
 * (tableaux, en-têtes, pagination d'origine) n'y est pas davantage : le PDF est
 * celui de l'aperçu, en pages A4, numérotées.
 *
 * Un fichier texte (« brut ») sort en caractères à chasse fixe, ligne à ligne.
 */
final class ExportPdf
{
    private const MARGE = 56.7;          // 2 cm
    private const PIED = 28.0;           // la bande du numéro de page
    private const TAILLE = 11.0;
    private const TITRES = [1 => 18.0, 2 => 15.0, 3 => 13.0];
    private const RETRAIT = 18.0;        // un niveau de liste
    private const PX_EN_PT = 0.75;       // 96 pixels par pouce, 72 points par pouce
    private const IMAGE_PX_MAX = 1800;

    private PdfSimple $pdf;
    private float $y = 0;

    /** @var array<int, ?array{0: int, 1: int, 2: int}> les images déjà posées, par rang */
    private array $images = [];

    private function __construct(private string $chemin, private string $nom)
    {
        $this->pdf = new PdfSimple();
    }

    /** Ce fichier sait-il sortir en PDF ? */
    public static function possible(string $nomOrigine): bool
    {
        return in_array(ApercuDocument::genre($nomOrigine), ['document', 'brut'], true);
    }

    /** Le PDF, prêt à envoyer. */
    public static function fabriquer(string $chemin, string $nomOrigine): string
    {
        return (new self($chemin, $nomOrigine))->produire();
    }

    private function produire(): string
    {
        $this->nouvellePage();

        if (ApercuDocument::genre($this->nom) === 'brut') {
            $texte = ApercuDocument::texteBrut($this->chemin)['texte'];
            foreach (preg_split('/\r\n|\r|\n/', (string) $texte) ?: [] as $ligne) {
                $this->ligneBrute($ligne);
            }
        } else {
            foreach ($this->blocsDuDocument() as $bloc) {
                $this->bloc($bloc);
            }
        }

        // Les numéros de page, une fois le compte connu.
        $total = $this->pdf->nombreDePages();
        for ($i = 0; $i < $total; $i++) {
            $this->pdf->allerALaPage($i);
            $mention = PdfSimple::encoder('Page ' . ($i + 1) . ' / ' . $total);
            $largeur = $this->pdf->largeur($mention, 'F1', 8.5);
            $this->pdf->texte((PdfSimple::LARGEUR - $largeur) / 2, self::MARGE / 2, $mention, 'F1', 8.5, [0.45, 0.45, 0.5]);
        }

        return $this->pdf->sortie((string) pathinfo($this->nom, PATHINFO_FILENAME));
    }

    /**
     * Les paragraphes, comme l'aperçu les lit : mis en forme quand la lecture
     * riche aboutit et tombe d'accord avec la lecture nue, en texte nu sinon.
     *
     * @return list<array>
     */
    private function blocsDuDocument(): array
    {
        $nus = ApercuDocument::paragraphes($this->chemin, $this->nom);
        $riches = [];
        if (EditionDocument::modifiable($this->nom)) {
            try {
                $riches = EditionDocument::apercuRiche($this->chemin, $this->nom,
                    static fn (int $rang): string => 'mescours-image:' . $rang);
            } catch (Throwable) {
                $riches = [];
            }
            $textuels = count(array_filter($riches,
                static fn (array $e): bool => (bool) ($e['textuel'] ?? (($e['html'] ?? '') !== ''))));
            if ($textuels !== count($nus)) {
                $riches = [];
            }
        }

        if ($riches !== []) {
            return array_values(array_filter($riches, static fn (array $b): bool => !($b['sommaire'] ?? false)));
        }

        return array_map(static fn (string $texte): array => [
            'html' => htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'alignement' => null, 'liste' => '', 'numero' => null, 'niveau' => 0, 'titre' => 0, 'images' => [],
        ], $nus);
    }

    /* --- Un paragraphe ------------------------------------------------------ */

    private function bloc(array $bloc): void
    {
        $titre = min((int) ($bloc['titre'] ?? 0), 3);
        $liste = $titre > 0 ? '' : (string) ($bloc['liste'] ?? '');
        $niveau = (int) ($bloc['niveau'] ?? 0);
        $gauche = $liste === '' ? 0.0 : ($niveau + 1) * self::RETRAIT;
        $disponible = PdfSimple::LARGEUR - 2 * self::MARGE - $gauche;

        $jetons = $this->jetons((string) ($bloc['html'] ?? ''), $titre);
        foreach ($bloc['images'] ?? [] as $image) {
            $jeton = $this->jetonImage((int) $image['rang'], isset($image['largeur']) ? (int) $image['largeur'] : null, 'centre');
            if ($jeton !== null) {
                $jetons[] = $jeton;
            }
        }
        if ($jetons === []) {
            $this->y -= self::TAILLE * 0.6;
            return;
        }

        if ($titre > 0) {
            $this->y -= 8;
        }

        $premiere = true;
        foreach ($this->lignes($jetons, $disponible) as $ligne) {
            if (isset($ligne['image'])) {
                $this->poserImage($ligne['image'], $gauche, $disponible, (string) ($bloc['alignement'] ?? ''));
                $premiere = false;
                continue;
            }

            // La ligne prend la hauteur de son plus grand caractère ; vide, celle du texte.
            $taille = 0.0;
            foreach ($ligne['jetons'] as $j) {
                $taille = max($taille, $j['taille']);
            }
            $taille = $taille > 0 ? $taille : self::TAILLE;
            $hauteur = $taille * 1.32;
            $this->place($hauteur);
            $base = $this->y - $taille * 1.0;

            if ($premiere && $liste !== '') {
                $this->marqueDeListe($liste, $niveau, $bloc['numero'] ?? null, $gauche, $base, $ligne['jetons'][0]['taille'] ?? self::TAILLE);
            }
            $this->ecrireLigne($ligne, $gauche, $disponible, (string) ($bloc['alignement'] ?? ''), $base);

            $this->y -= $hauteur;
            $premiere = false;
        }

        $this->y -= $titre > 0 ? 4 : ($liste !== '' ? 2 : 6);
    }

    /**
     * Le HTML d'un paragraphe — celui que l'application fabrique pour l'aperçu,
     * aux balises connues — découpé en mots portant leur style.
     *
     * @return list<array>
     */
    private function jetons(string $html, int $titre): array
    {
        if (trim($html) === '') {
            return [];
        }
        $doc = new DOMDocument();
        $avant = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        $jetons = [];
        $style = [
            'gras' => $titre > 0, 'italique' => false, 'souligne' => false,
            'taille' => $titre > 0 ? self::TITRES[$titre] : self::TAILLE,
            'couleur' => [0.0, 0.0, 0.0], 'fond' => null,
        ];
        foreach ($doc->childNodes as $racine) {
            if ($racine instanceof DOMElement) {
                $this->parcourir($racine, $style, $jetons);
            }
        }

        return $jetons;
    }

    private function parcourir(DOMNode $noeud, array $style, array &$jetons): void
    {
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                $this->mots((string) $enfant->nodeValue, $style, $jetons);
                continue;
            }
            if (!$enfant instanceof DOMElement) {
                continue;
            }

            $sien = $style;
            switch (strtolower($enfant->nodeName)) {
                case 'br':
                    $jetons[] = ['sorte' => 'saut'];
                    continue 2;
                case 'img':
                    $source = $enfant->getAttribute('src');
                    if (preg_match('/^mescours-image:(\d+)$/', $source, $m) === 1) {
                        $largeur = $enfant->getAttribute('data-largeur');
                        $jeton = $this->jetonImage((int) $m[1], $largeur === '' ? null : (int) $largeur,
                            $enfant->getAttribute('data-habillage'));
                        if ($jeton !== null) {
                            $jetons[] = $jeton;
                        }
                    }
                    continue 2;
                case 'b': case 'strong':
                    $sien['gras'] = true;
                    break;
                case 'i': case 'em':
                    $sien['italique'] = true;
                    break;
                case 'u':
                    $sien['souligne'] = true;
                    break;
                case 'span':
                    if (preg_match('/^\d{1,3}(\.\d+)?$/', $enfant->getAttribute('data-taille')) === 1) {
                        $sien['taille'] = max(4.0, min(96.0, (float) $enfant->getAttribute('data-taille')));
                    }
                    $couleur = self::rgb($enfant->getAttribute('data-couleur'));
                    if ($couleur !== null) {
                        $sien['couleur'] = $couleur;
                    }
                    $fond = self::rgb($enfant->getAttribute('data-fond'));
                    if ($fond !== null) {
                        $sien['fond'] = $fond;
                    }
                    break;
                // Les figures « impossibles » de l'aperçu ne portent que des explications.
                case 'figure': case 'figcaption':
                    continue 2;
            }
            $this->parcourir($enfant, $sien, $jetons);
        }
    }

    /** Un texte coupé en mots, chacun avec l'espace qui le suit. */
    private function mots(string $texte, array $style, array &$jetons): void
    {
        $police = $style['gras'] ? ($style['italique'] ? 'F4' : 'F2') : ($style['italique'] ? 'F3' : 'F1');
        preg_match_all('/[^\s]+|\n|[^\S\n]+/u', $texte, $morceaux);

        foreach ($morceaux[0] as $morceau) {
            if ($morceau === "\n") {
                $jetons[] = ['sorte' => 'saut'];
                continue;
            }
            if (trim($morceau) === '') {
                // L'espace appartient au mot d'avant, s'il y en a un.
                $dernier = count($jetons) - 1;
                if ($dernier >= 0 && $jetons[$dernier]['sorte'] === 'mot') {
                    $jetons[$dernier]['espace'] = true;
                }
                continue;
            }
            $code = PdfSimple::encoder($morceau);
            if ($code === '') {
                continue;
            }
            $jetons[] = [
                'sorte' => 'mot', 'code' => $code, 'police' => $police, 'taille' => $style['taille'],
                'couleur' => $style['couleur'], 'fond' => $style['fond'], 'souligne' => $style['souligne'],
                'espace' => false,
                'largeur' => $this->pdf->largeur($code, $police, $style['taille']),
                'largeurEspace' => $this->pdf->largeur(' ', $police, $style['taille']),
            ];
        }
    }

    /**
     * Les jetons rangés en lignes qui tiennent dans la largeur. Une image a sa
     * ligne à elle ; un mot plus large que la ligne se coupe.
     *
     * @return list<array>
     */
    private function lignes(array $jetons, float $largeur): array
    {
        $lignes = [];
        $ligne = [];
        $x = 0.0;
        $fermer = static function (bool $fin) use (&$lignes, &$ligne, &$x): void {
            $lignes[] = ['jetons' => $ligne, 'fin' => $fin];
            $ligne = [];
            $x = 0.0;
        };

        foreach ($jetons as $jeton) {
            if ($jeton['sorte'] === 'saut') {
                $fermer(true);
                continue;
            }
            if ($jeton['sorte'] === 'image') {
                if ($ligne !== []) {
                    $fermer(true);
                }
                $lignes[] = ['image' => $jeton];
                continue;
            }
            foreach ($this->couperSiTropLong($jeton, $largeur) as $mot) {
                $precedent = $ligne === [] ? null : $ligne[count($ligne) - 1];
                $ecart = $precedent !== null && $precedent['espace'] ? $precedent['largeurEspace'] : 0.0;
                if ($ligne !== [] && $x + $ecart + $mot['largeur'] > $largeur + 0.01) {
                    $fermer(false);
                    $ecart = 0.0;
                }
                $x += $ecart + $mot['largeur'];
                $ligne[] = $mot;
            }
        }
        if ($ligne !== []) {
            $fermer(true);
        }

        return $lignes;
    }

    /** @return list<array> */
    private function couperSiTropLong(array $mot, float $largeur): array
    {
        if ($mot['largeur'] <= $largeur) {
            return [$mot];
        }
        $morceaux = [];
        $courant = '';
        $n = strlen($mot['code']);
        for ($i = 0; $i < $n; $i++) {
            $essai = $courant . $mot['code'][$i];
            if ($courant !== '' && $this->pdf->largeur($essai, $mot['police'], $mot['taille']) > $largeur) {
                $morceaux[] = ['code' => $courant, 'espace' => false] + $mot;
                $courant = $mot['code'][$i];
            } else {
                $courant = $essai;
            }
        }
        $morceaux[] = ['code' => $courant] + $mot;

        return array_map(fn (array $m): array =>
            ['largeur' => $this->pdf->largeur($m['code'], $m['police'], $m['taille'])] + $m, $morceaux);
    }

    private function ecrireLigne(array $ligne, float $gauche, float $disponible, string $alignement, float $base): void
    {
        $jetons = $ligne['jetons'];
        $dernier = count($jetons) - 1;
        $contenu = 0.0;
        $ecarts = 0;
        foreach ($jetons as $i => $j) {
            $contenu += $j['largeur'];
            if ($i < $dernier && $j['espace']) {
                $contenu += $j['largeurEspace'];
                $ecarts++;
            }
        }

        $x = self::MARGE + $gauche;
        $supplement = 0.0;
        $reste = max(0.0, $disponible - $contenu);
        if ($alignement === 'centre') {
            $x += $reste / 2;
        } elseif ($alignement === 'droite') {
            $x += $reste;
        } elseif ($alignement === 'justifie' && !$ligne['fin'] && $ecarts > 0) {
            $supplement = $reste / $ecarts;
        }

        // Les fonds d'abord, pour que le texte passe par-dessus.
        $positions = [];
        $curseur = $x;
        foreach ($jetons as $i => $j) {
            $avance = $j['largeur'] + ($i < $dernier && $j['espace'] ? $j['largeurEspace'] + $supplement : 0.0);
            $positions[$i] = [$curseur, $avance];
            if ($j['fond'] !== null) {
                $this->pdf->rectangle($curseur, $base - $j['taille'] * 0.25, $avance, $j['taille'] * 1.18, $j['fond']);
            }
            $curseur += $avance;
        }
        foreach ($jetons as $i => $j) {
            [$xj, $avance] = $positions[$i];
            $this->pdf->texte($xj, $base, $j['code'], $j['police'], $j['taille'], $j['couleur']);
            if ($j['souligne']) {
                // Le soulignement court sous l'espace qui suit, si le mot d'après l'est aussi.
                $suite = $i < $dernier && $jetons[$i + 1]['souligne'] ? $avance : $j['largeur'];
                $this->pdf->rectangle($xj, $base - $j['taille'] * 0.14, $suite, max(0.5, $j['taille'] * 0.055), $j['couleur']);
            }
        }
    }

    private function marqueDeListe(string $liste, int $niveau, mixed $numero, float $gauche, float $base, float $taille): void
    {
        $marque = $liste === 'numero' && $numero !== null
            ? (string) $numero . '.'
            : ($niveau % 2 === 0 ? "\x95" : "\x96");
        $largeur = $this->pdf->largeur($marque, 'F1', $taille);
        $this->pdf->texte(self::MARGE + $gauche - 6 - $largeur, $base, $marque, 'F1', $taille);
    }

    /* --- Les images ---------------------------------------------------------- */

    /** Une image du document, prête à poser, ou rien si elle ne se lit pas. */
    private function jetonImage(int $rang, ?int $largeurPx, string $habillage): ?array
    {
        if (!array_key_exists($rang, $this->images)) {
            $this->images[$rang] = $this->preparerImage($rang);
        }
        $image = $this->images[$rang];
        if ($image === null) {
            return null;
        }
        [$numero, $px, $py] = $image;
        $largeur = ($largeurPx !== null && $largeurPx > 0 ? $largeurPx : $px) * self::PX_EN_PT;

        return [
            'sorte' => 'image', 'numero' => $numero,
            'largeur' => $largeur, 'hauteur' => $largeur * $py / max(1, $px),
            'habillage' => $habillage,
        ];
    }

    /** @return ?array{0: int, 1: int, 2: int} le numéro dans le PDF, et la taille en pixels */
    private function preparerImage(int $rang): ?array
    {
        $trouvee = ImagesDocument::octets($this->chemin, $this->nom, $rang);
        if ($trouvee === null || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring($trouvee['octets']);
        if ($source === false) {
            // Un format que GD ne lit pas (EMF, WMF…) : l'aperçu ne le montre pas non plus.
            return null;
        }

        $px = imagesx($source);
        $py = imagesy($source);
        $echelle = min(1, self::IMAGE_PX_MAX / max($px, $py));
        $lx = max(1, (int) round($px * $echelle));
        $ly = max(1, (int) round($py * $echelle));

        // Sur fond blanc : un JPEG n'a pas de transparence.
        $toile = imagecreatetruecolor($lx, $ly);
        imagefill($toile, 0, 0, (int) imagecolorallocate($toile, 255, 255, 255));
        imagecopyresampled($toile, $source, 0, 0, 0, 0, $lx, $ly, $px, $py);

        ob_start();
        imagejpeg($toile, null, 88);
        $jpeg = (string) ob_get_clean();

        return [$this->pdf->ajouterImage($jpeg, $lx, $ly), $px, $py];
    }

    private function poserImage(array $image, float $gauche, float $disponible, string $alignement): void
    {
        $largeur = min($image['largeur'], $disponible);
        $hauteur = $image['hauteur'] * $largeur / max(0.01, $image['largeur']);
        // Plus haute qu'une page : réduite pour tenir.
        $hauteurMax = PdfSimple::HAUTEUR - 2 * self::MARGE - self::PIED;
        if ($hauteur > $hauteurMax) {
            $largeur *= $hauteurMax / $hauteur;
            $hauteur = $hauteurMax;
        }

        $this->place($hauteur + 6);
        $cote = match ($image['habillage']) {
            'gauche' => 'gauche',
            'droite' => 'droite',
            'centre' => 'centre',
            default  => $alignement,
        };
        $x = self::MARGE + $gauche + match ($cote) {
            'centre' => ($disponible - $largeur) / 2,
            'droite' => $disponible - $largeur,
            default  => 0.0,
        };
        $this->pdf->poserImage($image['numero'], $x, $this->y - 3 - $hauteur, $largeur, $hauteur);
        $this->y -= $hauteur + 6;
    }

    /* --- Le texte brut ------------------------------------------------------- */

    private function ligneBrute(string $ligne): void
    {
        $taille = 9.5;
        $code = PdfSimple::encoder(rtrim($ligne));
        $parLigne = max(1, (int) floor((PdfSimple::LARGEUR - 2 * self::MARGE) / ($taille * 0.6)));
        // Coupée aux espaces quand c'est possible, au caractère sinon.
        $morceaux = $code === '' ? [''] : explode("\n", wordwrap($code, $parLigne, "\n", true));
        foreach ($morceaux as $morceau) {
            $this->place($taille * 1.35);
            $this->pdf->texte(self::MARGE, $this->y - $taille, $morceau, 'F5', $taille);
            $this->y -= $taille * 1.35;
        }
    }

    /* --- Les pages ----------------------------------------------------------- */

    /** Une nouvelle page si ce qui vient ne tient plus dans celle-ci. */
    private function place(float $hauteur): void
    {
        if ($this->y - $hauteur < self::MARGE + self::PIED) {
            $this->nouvellePage();
        }
    }

    private function nouvellePage(): void
    {
        $this->pdf->nouvellePage();
        $this->y = PdfSimple::HAUTEUR - self::MARGE;
    }

    /** @return ?array{0: float, 1: float, 2: float} */
    private static function rgb(string $hexa): ?array
    {
        if (preg_match('/^#?([0-9a-fA-F]{6})$/', trim($hexa), $m) !== 1) {
            return null;
        }
        $v = hexdec($m[1]);

        return [(($v >> 16) & 255) / 255, (($v >> 8) & 255) / 255, ($v & 255) / 255];
    }
}
