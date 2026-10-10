<?php
declare(strict_types=1);

/**
 * Une présentation PowerPoint (.pptx) lue pour être montrée en diapositives, avec sa mise en page.
 *
 * Un navigateur ne sait pas afficher un .pptx, et l'application n'a ni PowerPoint ni LibreOffice à lui demander (un hébergement
 * mutualisé n'en offre pas). Mais un .pptx est une archive de XML : chaque diapositive y dit où est chaque forme, son texte, ses
 * couleurs, ses images. On les relit ici pour redessiner la diapositive en HTML — des boîtes posées en pourcentages de la page, des
 * tailles de texte en unités de conteneur (« cqw ») —, qui se met à l'échelle de n'importe quelle largeur.
 *
 * C'est un aperçu fidèle pour l'essentiel — textes, titres, puces, couleurs, fonds, images, tableaux, formes simples, ce que la mise en
 * page et le masque apportent —, pas une copie : les animations, les graphiques, les SmartArt, les polices spéciales, les dégradés
 * complexes et les formes libres ne sont pas reproduits. Le fichier d'origine reste téléchargeable pour l'ouvrir dans PowerPoint.
 *
 * Rien n'est écrit sur le disque et rien n'est exécuté : l'archive est lue, son XML relu sans accès au réseau.
 */
final class ApercuPresentation
{
    /** Au-delà, une présentation est tronquée : l'aperçu ne doit pas noyer la page. */
    public const DIAPOS_MAX = 300;

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Les images qu'un navigateur sait montrer ; le reste (EMF, WMF, TIFF…) est laissé de côté. */
    private const IMAGES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private ZipArchive $zip;
    /** @var callable(string): ?string  l'adresse où lire une image de l'archive (« ppt/media/… »), ou null pour l'omettre */
    private $adresseMedia;
    private int $cx = 9144000;
    private int $cy = 6858000;
    /** @var array<string, ?DOMDocument> */
    private array $docs = [];
    /** @var array<string, array<string, string>> */
    private array $themes = [];
    private int $compteurPuces = 0;

    private function __construct(ZipArchive $zip, callable $adresseMedia)
    {
        $this->zip = $zip;
        $this->adresseMedia = $adresseMedia;
    }

    /** Ce fichier peut-il être montré en diapositives ? */
    public static function possible(string $nomOrigine): bool
    {
        return strtolower(pathinfo($nomOrigine, PATHINFO_EXTENSION)) === 'pptx';
    }

    /**
     * Les diapositives d'une présentation.
     *
     * @param callable(string): ?string $adresseMedia  l'adresse d'une image de l'archive, d'après son chemin (« ppt/media/image1.png »)
     * @return array{largeur: int, hauteur: int, total: int, tronque: bool, diapos: list<array{numero: int, titre: string, html: string}>}
     * @throws RuntimeException si l'archive est illisible
     */
    public static function lire(string $chemin, callable $adresseMedia): array
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException(t('err.pas_une_archive'));
        }
        try {
            $moi = new self($zip, $adresseMedia);

            return $moi->tout();
        } finally {
            $zip->close();
        }
    }

    /** Les octets d'une image de l'archive, avec son type, si c'en est une qu'on sait montrer (pour la servir). */
    public static function media(string $chemin, string $partie): ?array
    {
        // Seulement le dossier des médias, jamais un chemin qui en sort.
        if (preg_match('#^ppt/media/[A-Za-z0-9_.\- ]+$#', $partie) !== 1 || str_contains($partie, '..')) {
            return null;
        }
        $ext = strtolower(pathinfo($partie, PATHINFO_EXTENSION));
        if (!isset(self::IMAGES[$ext])) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            return null;
        }
        try {
            $octets = $zip->getFromName($partie);
        } finally {
            $zip->close();
        }

        return $octets === false ? null : ['type' => self::IMAGES[$ext], 'octets' => $octets];
    }

    // --- La présentation ----------------------------------------------------------------------------------------------

    private function tout(): array
    {
        $pres = $this->doc('ppt/presentation.xml');
        if ($pres === null) {
            throw new RuntimeException(t('err.contenu_introuvable'));
        }
        $taille = $pres->getElementsByTagNameNS(self::NS_P, 'sldSz')->item(0);
        if ($taille instanceof DOMElement) {
            $this->cx = max(1, (int) $taille->getAttribute('cx'));
            $this->cy = max(1, (int) $taille->getAttribute('cy'));
        }

        $relations = $this->relations('ppt/presentation.xml');
        $parties = [];
        foreach ($pres->getElementsByTagNameNS(self::NS_P, 'sldId') as $id) {
            $rid = $id->getAttributeNS(self::NS_R, 'id');
            if (isset($relations[$rid])) {
                $parties[] = $relations[$rid]['cible'];
            }
        }
        // Sans liste d'ordre, les parties dans l'ordre de leur numéro.
        if ($parties === []) {
            for ($i = 1; $this->zip->locateName('ppt/slides/slide' . $i . '.xml') !== false; $i++) {
                $parties[] = 'ppt/slides/slide' . $i . '.xml';
            }
        }

        $total = count($parties);
        $diapos = [];
        foreach (array_slice($parties, 0, self::DIAPOS_MAX) as $rang => $partie) {
            $diapos[] = $this->diapositive($partie, $rang + 1);
        }

        return ['largeur' => $this->cx, 'hauteur' => $this->cy, 'total' => $total, 'tronque' => $total > self::DIAPOS_MAX, 'diapos' => $diapos];
    }

    /** @return array{numero: int, titre: string, html: string} */
    private function diapositive(string $partie, int $numero): array
    {
        $doc = $this->doc($partie);
        $relations = $this->relations($partie);
        $mise = $masque = null;
        $partieMise = $partieMasque = null;
        foreach ($relations as $r) {
            if (str_ends_with($r['type'], '/slideLayout')) {
                $partieMise = $r['cible'];
                $mise = $this->doc($partieMise);
            }
        }
        if ($partieMise !== null) {
            foreach ($this->relations($partieMise) as $r) {
                if (str_ends_with($r['type'], '/slideMaster')) {
                    $partieMasque = $r['cible'];
                    $masque = $this->doc($partieMasque);
                }
            }
        }
        $theme = $this->themeDe($partieMasque);
        $couleurs = $masque !== null ? $this->tableCouleurs($masque) : [];
        $contexte = [
            'theme' => $theme, 'carte' => $couleurs, 'mise' => $mise, 'masque' => $masque,
            'relations' => $relations, 'partie' => $partie,
        ];

        $fond = $doc !== null ? $this->fond($doc, $contexte) : null;
        $fond ??= $mise !== null ? $this->fond($mise, ['relations' => $this->relations((string) $partieMise), 'partie' => (string) $partieMise] + $contexte) : null;
        $fond ??= $masque !== null ? $this->fond($masque, ['relations' => $this->relations((string) $partieMasque), 'partie' => (string) $partieMasque] + $contexte) : null;

        $html = '';
        $titre = '';
        // Derrière : ce que le masque et la mise en page posent sur toutes les diapositives (barres, logos), sauf s'ils sont masqués.
        $affichePlans = !($doc !== null && $this->attribut($doc->documentElement, 'showMasterSp') === '0');
        if ($affichePlans && $masque !== null && !($mise !== null && $this->attribut($mise->documentElement, 'showMasterSp') === '0')) {
            $html .= $this->arbre($masque, $contexte + [], false, (string) $partieMasque);
        }
        if ($affichePlans && $mise !== null) {
            $html .= $this->arbre($mise, $contexte, false, (string) $partieMise);
        }
        if ($doc !== null) {
            $html .= $this->arbre($doc, $contexte, true, $partie, $titre);
        }

        $style = 'aspect-ratio:' . $this->cx . '/' . $this->cy . ';' . ($fond ?? 'background:#fff;');

        return ['numero' => $numero, 'titre' => $titre, 'html' => '<div class="diapo" style="' . htmlspecialchars($style, ENT_QUOTES) . '">' . $html . '</div>'];
    }

    // --- Les formes ---------------------------------------------------------------------------------------------------

    /**
     * Les formes d'une diapositive, d'une mise en page ou d'un masque. Les places réservées (titre, corps…) ne s'y dessinent que sur la
     * diapositive : sur la mise en page et le masque, ce sont des modèles, pas du contenu.
     */
    private function arbre(DOMDocument $doc, array $contexte, bool $avecPlaces, string $partie, string &$titre = ''): string
    {
        $arbre = $doc->getElementsByTagNameNS(self::NS_P, 'spTree')->item(0);
        if (!$arbre instanceof DOMElement) {
            return '';
        }
        $contexte['relations'] = $this->relations($partie);
        $contexte['partie'] = $partie;
        $contexte['avecPlaces'] = $avecPlaces;

        return $this->formes($arbre, [0.0, 0.0, 1.0, 1.0], $contexte, $titre);
    }

    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $t  la transformation des coordonnées de ce groupe vers la diapositive : décalage x, y
     *        (en EMU) puis échelle x, y
     */
    private function formes(DOMElement $parent, array $t, array $contexte, string &$titre): string
    {
        $html = '';
        foreach ($parent->childNodes as $enfant) {
            if (!$enfant instanceof DOMElement || $enfant->namespaceURI !== self::NS_P) {
                continue;
            }
            switch ($enfant->localName) {
                case 'sp':
                case 'cxnSp':
                    $html .= $this->forme($enfant, $t, $contexte, $titre);
                    break;
                case 'pic':
                    $html .= $this->image($enfant, $t, $contexte);
                    break;
                case 'graphicFrame':
                    $html .= $this->cadre($enfant, $t, $contexte);
                    break;
                case 'grpSp':
                    $html .= $this->groupe($enfant, $t, $contexte, $titre);
                    break;
            }
        }

        return $html;
    }

    private function groupe(DOMElement $groupe, array $t, array $contexte, string &$titre): string
    {
        $xfrm = $this->premier($groupe, 'grpSpPr') ? $this->premier($this->premier($groupe, 'grpSpPr'), 'xfrm', self::NS_A) : null;
        if (!$xfrm instanceof DOMElement) {
            return $this->formes($groupe, $t, $contexte, $titre);
        }
        $off = $this->premier($xfrm, 'off', self::NS_A);
        $ext = $this->premier($xfrm, 'ext', self::NS_A);
        $choff = $this->premier($xfrm, 'chOff', self::NS_A);
        $chext = $this->premier($xfrm, 'chExt', self::NS_A);
        if (!$off || !$ext || !$choff || !$chext) {
            return $this->formes($groupe, $t, $contexte, $titre);
        }
        $ox = (float) $off->getAttribute('x');
        $oy = (float) $off->getAttribute('y');
        $ex = (float) $ext->getAttribute('cx');
        $ey = (float) $ext->getAttribute('cy');
        $cx0 = (float) $choff->getAttribute('x');
        $cy0 = (float) $choff->getAttribute('y');
        $cex = max(1.0, (float) $chext->getAttribute('cx'));
        $cey = max(1.0, (float) $chext->getAttribute('cy'));
        // Un point (x, y) du groupe tombe en  décalage + (x − chOff) × ext / chExt , puis passe par la transformation du parent.
        $sx = $ex / $cex;
        $sy = $ey / $cey;
        $fils = [
            $t[0] + ($ox - $cx0 * $sx) * $t[2],
            $t[1] + ($oy - $cy0 * $sy) * $t[3],
            $t[2] * $sx,
            $t[3] * $sy,
        ];

        return $this->formes($groupe, $fils, $contexte, $titre);
    }

    /** Une forme (ou un trait) : sa boîte, son fond, son contour, son texte. */
    private function forme(DOMElement $sp, array $t, array $contexte, string &$titre): string
    {
        $nv = $this->premier($sp, 'nvSpPr') ?? $this->premier($sp, 'nvCxnSpPr');
        $ph = $nv ? $this->premier($this->premier($nv, 'nvPr') ?? $nv, 'ph') : null;
        $typePlace = $ph ? ($ph->getAttribute('type') ?: 'body') : null;
        $idxPlace = $ph ? $ph->getAttribute('idx') : null;

        // Le numéro, le pied et la date reviennent sur chaque diapositive : on ne les redessine pas.
        if ($typePlace !== null && in_array($typePlace, ['sldNum', 'dt', 'ftr'], true)) {
            return '';
        }
        // Une place réservée d'un modèle (mise en page, masque) n'est pas du contenu.
        if ($ph && empty($contexte['avecPlaces'])) {
            return '';
        }

        $spPr = $this->premier($sp, 'spPr');
        $xfrm = $spPr ? $this->premier($spPr, 'xfrm', self::NS_A) : null;
        // Le modèle de la place (mise en page, puis masque) : il donne la boîte si la forme n'a pas la sienne, et surtout les tailles, les
        // couleurs et les alignements du texte que la forme ne redit pas.
        $hérite = $ph ? $this->placeModele($contexte, $typePlace, $idxPlace) : null;
        if (!$xfrm && $hérite) {
            $xfrm = ($p = $this->premier($hérite, 'spPr')) ? $this->premier($p, 'xfrm', self::NS_A) : null;
        }
        $boite = $xfrm ? $this->boite($xfrm, $t) : null;
        if ($boite === null) {
            return '';
        }

        $css = $boite['css'];
        $libre = $spPr ? $this->premier($spPr, 'custGeom', self::NS_A) : null;
        $dessin = $libre ? $this->dessinLibre($libre, $spPr, $contexte) : '';
        // Une forme libre porte son fond dans son dessin : pas de fond de boîte en plus.
        $css .= $dessin === '' ? $this->styleFond($spPr, $contexte) : '';
        $css .= $this->styleContour($spPr, $contexte);
        $geo = $spPr ? $this->premier($spPr, 'prstGeom', self::NS_A) : null;
        $prst = $geo ? $geo->getAttribute('prst') : 'rect';
        if ($prst === 'ellipse') {
            $css .= 'border-radius:50%;';
        } elseif ($prst === 'roundRect') {
            $css .= 'border-radius:8%;';
        }

        $corps = $this->premier($sp, 'txBody');
        $contenu = '';
        if ($corps instanceof DOMElement) {
            $contenu = $this->texte($corps, $contexte, $typePlace, $idxPlace, $hérite);
            if ($typePlace === 'title' || $typePlace === 'ctrTitle') {
                if ($titre === '') {
                    $titre = trim(preg_replace('/\s+/u', ' ', strip_tags($contenu)) ?? '');
                }
            }
        }
        $contenu = $dessin . $contenu;
        if ($contenu === '' && !str_contains($css, 'background') && !str_contains($css, 'border')) {
            return '';
        }

        return '<div class="diapo__forme" style="' . htmlspecialchars($css, ENT_QUOTES) . '">' . $contenu . '</div>';
    }

    /**
     * Une forme libre (une suite de segments et de courbes) dessinée en SVG : ses chemins sont repris tels quels, et la boîte les étire.
     * Les arcs ne sont pas reproduits.
     */
    private function dessinLibre(DOMElement $geo, ?DOMElement $spPr, array $contexte): string
    {
        $couleur = null;
        foreach ($spPr ? $spPr->childNodes : [] as $n) {
            if ($n instanceof DOMElement && $n->namespaceURI === self::NS_A && $n->localName === 'solidFill') {
                $couleur = $this->couleur($n, $contexte);
            }
        }
        $traits = '';
        $ln = $spPr ? $this->premier($spPr, 'ln', self::NS_A) : null;
        $plein = $ln ? $this->premier($ln, 'solidFill', self::NS_A) : null;
        $contour = $plein ? $this->couleur($plein, $contexte) : null;
        if ($contour !== null) {
            $traits = ' stroke="' . htmlspecialchars($contour, ENT_QUOTES) . '" stroke-width="' . $this->nb(max(1.0, ((float) ($ln->getAttribute('w') ?: 12700)) / 12700)) . '" vector-effect="non-scaling-stroke"';
        }
        if ($couleur === null && $contour === null) {
            return '';
        }

        $svg = '';
        foreach ($geo->getElementsByTagNameNS(self::NS_A, 'path') as $chemin) {
            $w = max(1.0, (float) $chemin->getAttribute('w'));
            $h = max(1.0, (float) $chemin->getAttribute('h'));
            $d = '';
            foreach ($chemin->childNodes as $c) {
                if (!$c instanceof DOMElement) {
                    continue;
                }
                $pts = [];
                foreach ($c->childNodes as $pt) {
                    if ($pt instanceof DOMElement && $pt->localName === 'pt') {
                        $pts[] = $this->nb((float) $pt->getAttribute('x')) . ' ' . $this->nb((float) $pt->getAttribute('y'));
                    }
                }
                $d .= match ($c->localName) {
                    'moveTo' => $pts ? 'M' . $pts[0] . ' ' : '',
                    'lnTo' => $pts ? 'L' . $pts[0] . ' ' : '',
                    'cubicBezTo' => count($pts) === 3 ? 'C' . implode(' ', $pts) . ' ' : '',
                    'quadBezTo' => count($pts) === 2 ? 'Q' . implode(' ', $pts) . ' ' : '',
                    'close' => 'Z ',
                    default => '',
                };
            }
            if ($d !== '') {
                $svg .= '<svg class="diapo__libre" viewBox="0 0 ' . $this->nb($w) . ' ' . $this->nb($h) . '" preserveAspectRatio="none">'
                    . '<path d="' . trim($d) . '" fill="' . ($couleur !== null ? htmlspecialchars($couleur, ENT_QUOTES) : 'none') . '"' . $traits . '/></svg>';
            }
        }

        return $svg;
    }

    /** @return ?array{css: string} */
    private function boite(DOMElement $xfrm, array $t): ?array
    {
        $off = $this->premier($xfrm, 'off', self::NS_A);
        $ext = $this->premier($xfrm, 'ext', self::NS_A);
        if (!$off || !$ext) {
            return null;
        }
        $x = $t[0] + (float) $off->getAttribute('x') * $t[2];
        $y = $t[1] + (float) $off->getAttribute('y') * $t[3];
        $w = (float) $ext->getAttribute('cx') * $t[2];
        $h = (float) $ext->getAttribute('cy') * $t[3];
        $css = sprintf('left:%s%%;top:%s%%;width:%s%%;height:%s%%;', $this->nb($x / $this->cx * 100), $this->nb($y / $this->cy * 100),
            $this->nb($w / $this->cx * 100), $this->nb($h / $this->cy * 100));
        $transform = '';
        $rot = (int) $xfrm->getAttribute('rot');
        if ($rot !== 0) {
            $transform .= 'rotate(' . $this->nb($rot / 60000) . 'deg) ';
        }
        if ($xfrm->getAttribute('flipH') === '1') {
            $transform .= 'scaleX(-1) ';
        }
        if ($xfrm->getAttribute('flipV') === '1') {
            $transform .= 'scaleY(-1) ';
        }
        if ($transform !== '') {
            $css .= 'transform:' . trim($transform) . ';';
        }

        return ['css' => $css, 'w' => $w, 'h' => $h];
    }

    /** Le fond d'une forme : uni, dégradé (de la première à la dernière couleur) ou image ; rien sinon. */
    private function styleFond(?DOMElement $spPr, array $contexte): string
    {
        if (!$spPr) {
            return '';
        }
        foreach ($spPr->childNodes as $n) {
            if (!$n instanceof DOMElement || $n->namespaceURI !== self::NS_A) {
                continue;
            }
            if ($n->localName === 'noFill') {
                return '';
            }
            if ($n->localName === 'solidFill') {
                $c = $this->couleur($n, $contexte);
                return $c !== null ? 'background:' . $c . ';' : '';
            }
            if ($n->localName === 'gradFill') {
                $arrets = [];
                foreach ($n->getElementsByTagNameNS(self::NS_A, 'gs') as $gs) {
                    $c = $this->couleur($gs, $contexte);
                    if ($c !== null) {
                        $arrets[] = $c;
                    }
                }
                if (count($arrets) >= 2) {
                    $lin = $this->premier($n, 'lin', self::NS_A);
                    $angle = $lin ? ((int) $lin->getAttribute('ang')) / 60000 + 90 : 180;
                    return 'background:linear-gradient(' . $this->nb($angle) . 'deg,' . $arrets[0] . ',' . $arrets[count($arrets) - 1] . ');';
                }
                return count($arrets) === 1 ? 'background:' . $arrets[0] . ';' : '';
            }
            if ($n->localName === 'blipFill') {
                $src = $this->srcImage($n, $contexte);
                return $src !== null ? 'background:url(' . $this->urlCss($src) . ') center/cover no-repeat;' : '';
            }
        }

        return '';
    }

    private function styleContour(?DOMElement $spPr, array $contexte): string
    {
        $ln = $spPr ? $this->premier($spPr, 'ln', self::NS_A) : null;
        if (!$ln || $this->premier($ln, 'noFill', self::NS_A)) {
            return '';
        }
        $plein = $this->premier($ln, 'solidFill', self::NS_A);
        $couleur = $plein ? $this->couleur($plein, $contexte) : null;
        if ($couleur === null) {
            return '';
        }
        $epaisseur = max(0.06, ((float) ($ln->getAttribute('w') ?: 12700)) / $this->cx * 100);

        return 'border:' . $this->nb($epaisseur) . 'cqw solid ' . $couleur . ';';
    }

    // --- Le texte -----------------------------------------------------------------------------------------------------

    private function texte(DOMElement $corps, array $contexte, ?string $type, ?string $idx, ?DOMElement $modele): string
    {
        $bodyPr = $this->premier($corps, 'bodyPr', self::NS_A);
        $echelle = 1.0;
        if ($bodyPr) {
            $auto = $this->premier($bodyPr, 'normAutofit', self::NS_A);
            if ($auto && $auto->getAttribute('fontScale') !== '') {
                $echelle = max(0.2, min(1.0, (float) $auto->getAttribute('fontScale') / 100000));
            }
        }
        $ancre = $bodyPr ? $bodyPr->getAttribute('anchor') : '';
        if ($ancre === '' && $modele) {
            $mb = ($m = $this->premier($modele, 'txBody')) ? $this->premier($m, 'bodyPr', self::NS_A) : null;
            $ancre = $mb ? $mb->getAttribute('anchor') : '';
        }
        $marge = static fn (string $nom, int $defaut) => $bodyPr && $bodyPr->getAttribute($nom) !== '' ? (int) $bodyPr->getAttribute($nom) : $defaut;
        $pad = sprintf('padding:%scqw %scqw %scqw %scqw;', $this->nb($marge('tIns', 45720) / $this->cx * 100), $this->nb($marge('rIns', 91440) / $this->cx * 100),
            $this->nb($marge('bIns', 45720) / $this->cx * 100), $this->nb($marge('lIns', 91440) / $this->cx * 100));
        $justify = match ($ancre) { 'ctr' => 'center', 'b' => 'flex-end', default => 'flex-start' };

        // Les listes de styles qui s'appliquent, de la plus proche à la plus lointaine.
        $chaine = [];
        if (($l = $this->premier($corps, 'lstStyle', self::NS_A)) instanceof DOMElement) {
            $chaine[] = $l;
        }
        foreach ([$modele, $this->placeModele($contexte, $type, $idx, true)] as $place) {
            if ($place && ($m = $this->premier($place, 'txBody')) && ($l = $this->premier($m, 'lstStyle', self::NS_A)) instanceof DOMElement) {
                $chaine[] = $l;
            }
        }
        $styleMasque = $this->styleMasque($contexte, $type);
        if ($styleMasque) {
            $chaine[] = $styleMasque;
        }

        $this->compteurPuces = 0;
        $dernierNiveau = -1;
        $html = '';
        foreach ($corps->childNodes as $p) {
            if (!$p instanceof DOMElement || $p->localName !== 'p' || $p->namespaceURI !== self::NS_A) {
                continue;
            }
            $html .= $this->paragraphe($p, $chaine, $contexte, $echelle, $dernierNiveau);
        }
        if (trim(strip_tags($html)) === '' && !str_contains($html, '<img')) {
            return '';
        }

        return '<div class="diapo__texte" style="' . $pad . 'justify-content:' . $justify . ';">' . $html . '</div>';
    }

    private function paragraphe(DOMElement $p, array $chaine, array $contexte, float $echelle, int &$dernierNiveau): string
    {
        $pPr = $this->premier($p, 'pPr', self::NS_A);
        $niveau = $pPr && $pPr->getAttribute('lvl') !== '' ? max(0, min(8, (int) $pPr->getAttribute('lvl'))) : 0;
        $nomNiveau = 'lvl' . ($niveau + 1) . 'pPr';

        // Un attribut du paragraphe : le sien, sinon celui des listes de styles.
        $attr = function (string $nom) use ($pPr, $chaine, $nomNiveau): ?string {
            if ($pPr && $pPr->hasAttribute($nom)) {
                return $pPr->getAttribute($nom);
            }
            foreach ($chaine as $liste) {
                $n = $this->premier($liste, $nomNiveau, self::NS_A);
                if ($n && $n->hasAttribute($nom)) {
                    return $n->getAttribute($nom);
                }
            }

            return null;
        };
        // Un enfant du paragraphe (puce…) : le sien, sinon celui des listes de styles.
        $enfant = function (string $nom) use ($pPr, $chaine, $nomNiveau): ?DOMElement {
            if ($pPr && ($e = $this->premier($pPr, $nom, self::NS_A))) {
                return $e;
            }
            foreach ($chaine as $liste) {
                $n = $this->premier($liste, $nomNiveau, self::NS_A);
                if ($n && ($e = $this->premier($n, $nom, self::NS_A))) {
                    return $e;
                }
            }

            return null;
        };
        // Les réglages de caractère par défaut du niveau (defRPr), du plus proche au plus lointain.
        $defauts = [];
        foreach ($chaine as $liste) {
            $n = $this->premier($liste, $nomNiveau, self::NS_A);
            if ($n && ($d = $this->premier($n, 'defRPr', self::NS_A))) {
                $defauts[] = $d;
            }
        }

        $marL = (int) ($attr('marL') ?? ($niveau > 0 ? 342900 * $niveau : 0));
        $indent = (int) ($attr('indent') ?? 0);
        $align = match ($attr('algn')) { 'ctr' => 'center', 'r' => 'right', 'just' => 'justify', default => 'left' };

        $puce = '';
        if ($enfant('buNone') === null) {
            $char = $enfant('buChar');
            $auto = $enfant('buAutoNum');
            if ($char) {
                $puce = $char->getAttribute('char') ?: '•';
            } elseif ($auto) {
                $this->compteurPuces = $niveau === $dernierNiveau ? $this->compteurPuces + 1 : 1;
                $puce = $this->compteurPuces . '.';
            }
        }
        $dernierNiveau = $niveau;
        if ($puce === '') {
            $this->compteurPuces = 0;
        }

        $runs = '';
        $taillePara = null;
        foreach ($p->childNodes as $n) {
            if (!$n instanceof DOMElement || $n->namespaceURI !== self::NS_A) {
                continue;
            }
            if ($n->localName === 'br') {
                $runs .= '<br>';
                continue;
            }
            if (!in_array($n->localName, ['r', 'fld'], true)) {
                continue;
            }
            $t = $this->premier($n, 't', self::NS_A);
            $texte = $t ? $t->textContent : '';
            if ($texte === '') {
                continue;
            }
            $rPr = $this->premier($n, 'rPr', self::NS_A);
            [$style, $taille] = $this->styleCaracteres($rPr, $defauts, $contexte, $echelle);
            $taillePara ??= $taille;
            $runs .= '<span style="' . $style . '">' . htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
        }

        // Un paragraphe vide garde sa hauteur : c'est un espacement voulu.
        $taille = $taillePara ?? $this->taillePar($defauts, $echelle, $p);
        $espace = '';
        if ($runs === '') {
            return '<p style="font-size:' . $this->nb($taille) . 'cqw;margin:0;">&nbsp;</p>';
        }
        $avant = $enfant('spcBef') ? $this->premier($enfant('spcBef'), 'spcPts', self::NS_A) : null;
        if ($avant && $avant->getAttribute('val') !== '') {
            $espace .= 'margin-top:' . $this->nb(((int) $avant->getAttribute('val')) / 100 * 12700 / $this->cx * 100 * $echelle) . 'cqw;';
        }
        $interligne = $enfant('lnSpc') ? $this->premier($enfant('lnSpc'), 'spcPct', self::NS_A) : null;
        $lh = $interligne && $interligne->getAttribute('val') !== '' ? ((int) $interligne->getAttribute('val')) / 100000 * 1.2 : 1.2;
        $retrait = ($marL + min(0, $indent)) / $this->cx * 100;
        $premiere = $puce !== '' ? $indent / $this->cx * 100 : 0;
        $style = sprintf('margin:0;text-align:%s;line-height:%s;padding-left:%scqw;text-indent:%scqw;%s', $align, $this->nb($lh),
            $this->nb(max(0, $marL / $this->cx * 100)), $this->nb($premiere), $espace);
        $marque = $puce !== '' ? '<span class="diapo__puce">' . htmlspecialchars($puce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span> ' : '';
        unset($retrait);

        return '<p style="' . $style . '">' . $marque . $runs . '</p>';
    }

    /**
     * Le style d'un morceau de texte, et sa taille (en cqw).
     *
     * @param list<DOMElement> $defauts
     * @return array{0: string, 1: float}
     */
    private function styleCaracteres(?DOMElement $rPr, array $defauts, array $contexte, float $echelle): array
    {
        $source = static function (string $attribut) use ($rPr, $defauts): ?string {
            if ($rPr && $rPr->hasAttribute($attribut)) {
                return $rPr->getAttribute($attribut);
            }
            foreach ($defauts as $d) {
                if ($d->hasAttribute($attribut)) {
                    return $d->getAttribute($attribut);
                }
            }

            return null;
        };
        $points = ((float) ($source('sz') ?? 1800)) / 100 * $echelle;
        $taille = $points * 12700 / $this->cx * 100;
        $css = 'font-size:' . $this->nb($taille) . 'cqw;';
        if ($source('b') === '1') {
            $css .= 'font-weight:700;';
        }
        if ($source('i') === '1') {
            $css .= 'font-style:italic;';
        }
        $u = $source('u');
        if ($u !== null && $u !== 'none') {
            $css .= 'text-decoration:underline;';
        }
        $couleur = null;
        foreach (array_merge($rPr ? [$rPr] : [], $defauts) as $porteur) {
            $plein = $this->premier($porteur, 'solidFill', self::NS_A);
            if ($plein && ($couleur = $this->couleur($plein, $contexte)) !== null) {
                break;
            }
        }
        $css .= 'color:' . ($couleur ?? $this->couleurTexteDefaut($contexte)) . ';';

        return [$css, $taille];
    }

    /** La taille d'un paragraphe sans texte, pour lui garder sa hauteur. */
    private function taillePar(array $defauts, float $echelle, DOMElement $p): float
    {
        $fin = $this->premier($p, 'endParaRPr', self::NS_A);
        $sz = $fin && $fin->getAttribute('sz') !== '' ? (float) $fin->getAttribute('sz') : null;
        foreach ($defauts as $d) {
            if ($sz === null && $d->getAttribute('sz') !== '') {
                $sz = (float) $d->getAttribute('sz');
            }
        }

        return ($sz ?? 1800) / 100 * $echelle * 12700 / $this->cx * 100;
    }

    private function couleurTexteDefaut(array $contexte): string
    {
        return $this->couleurSchema('tx1', $contexte) ?? '#000000';
    }

    // --- Images, tableaux ---------------------------------------------------------------------------------------------

    private function image(DOMElement $pic, array $t, array $contexte): string
    {
        $spPr = $this->premier($pic, 'spPr');
        $xfrm = $spPr ? $this->premier($spPr, 'xfrm', self::NS_A) : null;
        $boite = $xfrm ? $this->boite($xfrm, $t) : null;
        $fill = $this->premier($pic, 'blipFill');
        if ($boite === null || !$fill instanceof DOMElement) {
            return '';
        }
        $src = $this->srcImage($fill, $contexte);
        if ($src === null) {
            return '';
        }
        // Un recadrage : l'image est agrandie et décalée dans une boîte qui masque le reste.
        $l = $t0 = $r = $b = 0.0;
        $rect = $this->premier($fill, 'srcRect', self::NS_A);
        if ($rect) {
            $l = (float) $rect->getAttribute('l') / 100000;
            $t0 = (float) $rect->getAttribute('t') / 100000;
            $r = (float) $rect->getAttribute('r') / 100000;
            $b = (float) $rect->getAttribute('b') / 100000;
        }
        $largeur = max(0.05, 1 - $l - $r);
        $hauteur = max(0.05, 1 - $t0 - $b);
        $img = sprintf('<img src="%s" alt="" loading="lazy" style="position:absolute;left:%s%%;top:%s%%;width:%s%%;height:%s%%;">',
            htmlspecialchars($src, ENT_QUOTES), $this->nb(-$l / $largeur * 100), $this->nb(-$t0 / $hauteur * 100),
            $this->nb(100 / $largeur), $this->nb(100 / $hauteur));

        return '<div class="diapo__forme diapo__image" style="' . $boite['css'] . 'overflow:hidden;">' . $img . '</div>';
    }

    /** L'adresse d'une image désignée par un « blip » (relation → partie de l'archive), ou null si on ne sait pas la montrer. */
    private function srcImage(DOMElement $porteur, array $contexte): ?string
    {
        $blip = $this->premier($porteur, 'blip', self::NS_A);
        if (!$blip) {
            return null;
        }
        $rid = $blip->getAttributeNS(self::NS_R, 'embed');
        $relations = $contexte['relations'] ?? [];
        if ($rid === '' || !isset($relations[$rid])) {
            return null;
        }
        $cible = $relations[$rid]['cible'];
        if (!isset(self::IMAGES[strtolower(pathinfo($cible, PATHINFO_EXTENSION))]) || !str_starts_with($cible, 'ppt/media/')) {
            return null;
        }

        return ($this->adresseMedia)($cible);
    }

    /** Un cadre : un tableau est relu ; un graphique ou un schéma est signalé par une case, faute de mieux. */
    private function cadre(DOMElement $cadre, array $t, array $contexte): string
    {
        $xfrm = $this->premier($cadre, 'xfrm');
        $boite = $xfrm ? $this->boite($xfrm, $t) : null;
        if ($boite === null) {
            return '';
        }
        $tbl = $cadre->getElementsByTagNameNS(self::NS_A, 'tbl')->item(0);
        if (!$tbl instanceof DOMElement) {
            return '<div class="diapo__forme diapo__absent" style="' . $boite['css'] . '"><span>' . htmlspecialchars(t('ap.diapo_absent'), ENT_QUOTES) . '</span></div>';
        }
        $colonnes = [];
        $somme = 0.0;
        foreach ($tbl->getElementsByTagNameNS(self::NS_A, 'gridCol') as $g) {
            $w = (float) $g->getAttribute('w');
            $colonnes[] = $w;
            $somme += $w;
        }
        $rangs = '';
        foreach ($tbl->getElementsByTagNameNS(self::NS_A, 'tr') as $tr) {
            $cases = '';
            foreach ($tr->childNodes as $tc) {
                if (!$tc instanceof DOMElement || $tc->localName !== 'tc') {
                    continue;
                }
                $tx = $this->premier($tc, 'txBody', self::NS_A);
                $texte = $tx ? $this->texte($tx, $contexte, null, null, null) : '';
                $fond = ($pr = $this->premier($tc, 'tcPr', self::NS_A)) ? $this->styleFond($pr, $contexte) : '';
                $cases .= '<td style="' . htmlspecialchars($fond, ENT_QUOTES) . '">' . $texte . '</td>';
            }
            $rangs .= '<tr>' . $cases . '</tr>';
        }
        $cols = '';
        foreach ($colonnes as $w) {
            $cols .= '<col style="width:' . $this->nb($somme > 0 ? $w / $somme * 100 : 0) . '%">';
        }

        return '<div class="diapo__forme" style="' . $boite['css'] . '"><table class="diapo__tableau"><colgroup>' . $cols . '</colgroup>' . $rangs . '</table></div>';
    }

    // --- Couleurs, fonds, thème ---------------------------------------------------------------------------------------

    /** La couleur d'un nœud qui porte un srgbClr, un schemeClr…, avec ses modifications (teinte, luminosité). */
    private function couleur(DOMElement $parent, array $contexte): ?string
    {
        foreach ($parent->childNodes as $n) {
            if (!$n instanceof DOMElement || $n->namespaceURI !== self::NS_A) {
                continue;
            }
            $base = match ($n->localName) {
                'srgbClr' => preg_match('/^[0-9a-fA-F]{6}$/', $n->getAttribute('val')) === 1 ? '#' . strtolower($n->getAttribute('val')) : null,
                'schemeClr' => $this->couleurSchema($n->getAttribute('val'), $contexte),
                'sysClr' => preg_match('/^[0-9a-fA-F]{6}$/', $n->getAttribute('lastClr')) === 1 ? '#' . strtolower($n->getAttribute('lastClr')) : '#000000',
                'prstClr' => $this->couleurNommee($n->getAttribute('val')),
                default => false,
            };
            if ($base === false) {
                continue;
            }
            if ($base === null) {
                return null;
            }

            return $this->modifier($base, $n);
        }

        return null;
    }

    private function couleurNommee(string $nom): ?string
    {
        return ['black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000', 'green' => '#008000', 'blue' => '#0000ff', 'yellow' => '#ffff00', 'gray' => '#808080', 'grey' => '#808080'][$nom] ?? null;
    }

    /** « tx1 », « bg1 », « accent2 »… → la couleur du thème, par le tableau de correspondance du masque. */
    private function couleurSchema(string $nom, array $contexte): ?string
    {
        $carte = $contexte['carte'] ?? [];
        $nom = $carte[$nom] ?? $nom;
        $theme = $contexte['theme'] ?? [];
        // Sans masque lisible, les correspondances usuelles.
        $repli = ['tx1' => 'dk1', 'bg1' => 'lt1', 'tx2' => 'dk2', 'bg2' => 'lt2'];
        $nom = $theme[$nom] ?? null ? $nom : ($repli[$nom] ?? $nom);

        return $theme[$nom] ?? null;
    }

    /** lumMod, lumOff, tint, shade : les modifications usuelles d'une couleur de thème. */
    private function modifier(string $hex, DOMElement $couleur): string
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $val = static function (string $nom) use ($couleur): ?float {
            foreach ($couleur->childNodes as $n) {
                if ($n instanceof DOMElement && $n->localName === $nom) {
                    return (float) $n->getAttribute('val') / 100000;
                }
            }

            return null;
        };
        $mod = $val('lumMod');
        $off = $val('lumOff');
        if ($mod !== null || $off !== null) {
            [$h, $s, $l] = $this->enTsl($r, $g, $b);
            $l = max(0.0, min(1.0, $l * ($mod ?? 1.0) + ($off ?? 0.0)));
            [$r, $g, $b] = $this->deTsl($h, $s, $l);
        }
        if (($tint = $val('tint')) !== null) {
            foreach ([&$r, &$g, &$b] as &$c) {
                $c = $c * $tint + 255 * (1 - $tint);
            }
            unset($c);
        }
        if (($shade = $val('shade')) !== null) {
            foreach ([&$r, &$g, &$b] as &$c) {
                $c *= $shade;
            }
            unset($c);
        }
        // Une couleur en partie transparente (alpha) : posée en rgba, pour que ce qui est dessous se voie.
        if (($alpha = $val('alpha')) !== null && $alpha < 1.0) {
            return sprintf('rgba(%d,%d,%d,%s)', (int) round(max(0, min(255, $r))), (int) round(max(0, min(255, $g))), (int) round(max(0, min(255, $b))),
                $this->nb(max(0.0, $alpha)));
        }

        return sprintf('#%02x%02x%02x', (int) round(max(0, min(255, $r))), (int) round(max(0, min(255, $g))), (int) round(max(0, min(255, $b))));
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function enTsl(float $r, float $g, float $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        } / 6;

        return [$h, $s, $l];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function deTsl(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            return [$l * 255, $l * 255, $l * 255];
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = static function (float $t) use ($p, $q): float {
            if ($t < 0) { $t += 1; }
            if ($t > 1) { $t -= 1; }
            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            } * 255;
        };

        return [$f($h + 1 / 3), $f($h), $f($h - 1 / 3)];
    }

    /** Le fond d'une diapositive, d'une mise en page ou d'un masque (« background:… ; »), ou null s'il n'en a pas. */
    private function fond(DOMDocument $doc, array $contexte): ?string
    {
        $bg = $doc->getElementsByTagNameNS(self::NS_P, 'bg')->item(0);
        if (!$bg instanceof DOMElement) {
            return null;
        }
        $pr = $this->premier($bg, 'bgPr');
        if ($pr) {
            $css = $this->styleFond($pr, $contexte);
            return $css !== '' ? $css : null;
        }
        // Un fond désigné par le thème : sa couleur de base, faute du style complet.
        $ref = $this->premier($bg, 'bgRef');
        if ($ref && ($c = $this->couleur($ref, $contexte)) !== null) {
            return 'background:' . $c . ';';
        }

        return null;
    }

    /** @return array<string, string>  nom → #rrggbb, pour le thème désigné par le masque */
    private function themeDe(?string $partieMasque): array
    {
        $partieTheme = null;
        if ($partieMasque !== null) {
            foreach ($this->relations($partieMasque) as $r) {
                if (str_ends_with($r['type'], '/theme')) {
                    $partieTheme = $r['cible'];
                }
            }
        }
        $partieTheme ??= 'ppt/theme/theme1.xml';
        if (isset($this->themes[$partieTheme])) {
            return $this->themes[$partieTheme];
        }
        $couleurs = [];
        $doc = $this->doc($partieTheme);
        $schema = $doc?->getElementsByTagNameNS(self::NS_A, 'clrScheme')->item(0);
        if ($schema instanceof DOMElement) {
            foreach ($schema->childNodes as $n) {
                if (!$n instanceof DOMElement) {
                    continue;
                }
                $c = $this->premier($n, 'srgbClr', self::NS_A);
                $s = $this->premier($n, 'sysClr', self::NS_A);
                $hex = $c ? $c->getAttribute('val') : ($s ? $s->getAttribute('lastClr') : '');
                if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1) {
                    $couleurs[$n->localName] = '#' . strtolower($hex);
                }
            }
        }

        return $this->themes[$partieTheme] = $couleurs;
    }

    /** @return array<string, string> la correspondance du masque : tx1 → dk1, bg1 → lt1… */
    private function tableCouleurs(DOMDocument $masque): array
    {
        $carte = [];
        $n = $masque->getElementsByTagNameNS(self::NS_P, 'clrMap')->item(0);
        if ($n instanceof DOMElement) {
            foreach ($n->attributes ?? [] as $a) {
                $carte[$a->nodeName] = $a->nodeValue;
            }
        }

        return $carte;
    }

    // --- Places réservées : ce que la mise en page et le masque disent à la forme -------------------------------------

    /**
     * La place réservée correspondante, dans la mise en page puis le masque (ou le masque seul avec « $masqueSeul »).
     */
    private function placeModele(array $contexte, ?string $type, ?string $idx, bool $masqueSeul = false): ?DOMElement
    {
        $docs = $masqueSeul ? [$contexte['masque'] ?? null] : [$contexte['mise'] ?? null, $contexte['masque'] ?? null];
        foreach ($docs as $doc) {
            if (!$doc instanceof DOMDocument) {
                continue;
            }
            $trouve = null;
            foreach ($doc->getElementsByTagNameNS(self::NS_P, 'sp') as $sp) {
                $ph = ($nv = $this->premier($sp, 'nvSpPr')) ? $this->premier($this->premier($nv, 'nvPr') ?? $nv, 'ph') : null;
                if (!$ph) {
                    continue;
                }
                $t = $ph->getAttribute('type') ?: 'body';
                $i = $ph->getAttribute('idx');
                $memeType = $this->genre($t) === $this->genre((string) $type);
                if ($idx !== null && $idx !== '' && $i === $idx && ($memeType || $doc === ($contexte['mise'] ?? null))) {
                    return $sp;
                }
                if ($memeType && $trouve === null && ($i === '' || $idx === null || $idx === '')) {
                    $trouve = $sp;
                }
            }
            if ($trouve !== null) {
                return $trouve;
            }
        }

        return null;
    }

    /** Titre, ou corps : les types de places se rangent en deux familles pour retrouver leur modèle. */
    private function genre(string $type): string
    {
        return in_array($type, ['title', 'ctrTitle'], true) ? 'titre' : 'corps';
    }

    /** Le style de texte du masque pour ce type de place : titre, corps ou autre. */
    private function styleMasque(array $contexte, ?string $type): ?DOMElement
    {
        $masque = $contexte['masque'] ?? null;
        if (!$masque instanceof DOMDocument) {
            return null;
        }
        $nom = match (true) {
            in_array($type, ['title', 'ctrTitle'], true) => 'titleStyle',
            in_array($type, ['body', 'subTitle', 'obj', null], true) && $type !== null => 'bodyStyle',
            default => 'otherStyle',
        };
        $n = $masque->getElementsByTagNameNS(self::NS_P, $nom)->item(0);

        return $n instanceof DOMElement ? $n : null;
    }

    // --- Archive -------------------------------------------------------------------------------------------------------

    private function doc(string $partie): ?DOMDocument
    {
        if (array_key_exists($partie, $this->docs)) {
            return $this->docs[$partie];
        }
        $xml = $this->zip->getFromName($partie);
        if ($xml === false) {
            return $this->docs[$partie] = null;
        }
        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // LIBXML_NONET : aucun accès au réseau, et les entités externes ne sont pas résolues — un fichier piégé ne fait rien lire au serveur.
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        return $this->docs[$partie] = $ok ? $doc : null;
    }

    /**
     * Les relations d'une partie : identifiant → type et cible (chemin complet dans l'archive).
     *
     * @return array<string, array{type: string, cible: string}>
     */
    private function relations(string $partie): array
    {
        $dossier = dirname($partie);
        $fichierRel = $dossier . '/_rels/' . basename($partie) . '.rels';
        $doc = $this->doc($fichierRel);
        $relations = [];
        if (!$doc) {
            return $relations;
        }
        foreach ($doc->getElementsByTagName('Relationship') as $r) {
            if ($r->getAttribute('TargetMode') === 'External') {
                continue;
            }
            $cible = $r->getAttribute('Target');
            $chemin = str_starts_with($cible, '/') ? ltrim($cible, '/') : $this->normaliser($dossier . '/' . $cible);
            $relations[$r->getAttribute('Id')] = ['type' => $r->getAttribute('Type'), 'cible' => $chemin];
        }

        return $relations;
    }

    /** « ppt/slides/../media/a.png » → « ppt/media/a.png » ; ce qui remonte au-delà de la racine est écarté. */
    private function normaliser(string $chemin): string
    {
        $sortie = [];
        foreach (explode('/', $chemin) as $bout) {
            if ($bout === '' || $bout === '.') {
                continue;
            }
            if ($bout === '..') {
                array_pop($sortie);
                continue;
            }
            $sortie[] = $bout;
        }

        return implode('/', $sortie);
    }

    // --- Petits outils -------------------------------------------------------------------------------------------------

    /** Le premier enfant direct portant ce nom (dans l'espace « p » par défaut, ou celui qu'on donne). */
    private function premier(?DOMElement $parent, string $nom, string $espace = self::NS_P): ?DOMElement
    {
        if ($parent === null) {
            return null;
        }
        foreach ($parent->childNodes as $n) {
            if ($n instanceof DOMElement && $n->localName === $nom && $n->namespaceURI === $espace) {
                return $n;
            }
        }

        return null;
    }

    private function attribut(?DOMElement $n, string $nom): ?string
    {
        return $n !== null && $n->hasAttribute($nom) ? $n->getAttribute($nom) : null;
    }

    /** Un nombre pour une feuille de style : court, avec un point, jamais en notation scientifique. */
    private function nb(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }

    private function urlCss(string $adresse): string
    {
        return "'" . str_replace(["'", '\\', "\n", "\r"], '', $adresse) . "'";
    }
}
