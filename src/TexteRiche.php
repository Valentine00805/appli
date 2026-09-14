<?php
declare(strict_types=1);

/**
 * Un texte mis en forme : gras, italique, souligné, listes, couleur, surlignage,
 * et pour le contenu d'un cours tout ce que propose l'éditeur de documents —
 * alignement, retraits, titres, taille, images, sommaire.
 *
 * La fiche de révision, le contenu d'un cours et les notes d'un évènement
 * s'écrivent dans un petit traitement de texte. Ce qu'il envoie est du HTML,
 * rangé en base derrière une marque — un commentaire que rien d'autre n'écrit —
 * pour le distinguer des textes d'avant, qui restent du texte brut et
 * s'affichent comme avant. Un second commentaire, juste après, dit jusqu'à quel
 * niveau de titre le sommaire descend.
 *
 * La marque ne fait jamais foi : un agenda importé pourrait très bien commencer
 * sa description par elle. Tout ce qui sort d'ici en HTML est donc repassé au
 * crible à chaque fois — une liste blanche de balises, et pour attributs
 * seulement quelques propriétés de style aux valeurs vérifiées et des images
 * embarquées —, qu'il vienne du formulaire ou de la base.
 */
final class TexteRiche
{
    public const MARQUE = '<!--riche-->';

    /** Les tailles de texte proposées, en points — celles de l'éditeur de documents. */
    public const TAILLES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 40];

    /** Une image embarquée : pas plus lourde que ceci, une fois encodée. */
    public const IMAGE_MAX = 6 * 1024 * 1024;

    /** Les balises gardées, et ce qu'elles deviennent. */
    private const BALISES = [
        'b' => 'b', 'strong' => 'b',
        'i' => 'i', 'em' => 'i',
        'u' => 'u', 'ins' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'div' => 'div', 'p' => 'div',
        'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h4', 'h6' => 'h4',
        'blockquote' => 'blockquote',
        'span' => 'span', 'font' => 'span',
        'br' => 'br',
        'img' => 'img',
    ];

    /** Les blocs, qui peuvent être alignés. */
    private const BLOCS = ['div', 'h2', 'h3', 'h4', 'li', 'blockquote'];

    /** Celles dont le contenu même est jeté. */
    private const JETEES = ['script', 'style', 'head', 'title', 'template', 'iframe', 'object',
                            'embed', 'svg', 'math', 'noscript', 'textarea', 'select', 'nav'];

    /** Au-delà, une imbrication n'a plus rien d'un texte écrit à la main. */
    private const PROFONDEUR_MAX = 40;

    public static function estRiche(?string $valeur): bool
    {
        return $valeur !== null && str_starts_with($valeur, self::MARQUE);
    }

    /**
     * Ce qu'un formulaire envoie, prêt à ranger : du HTML nettoyé derrière la
     * marque, ou le texte brut tel quel (sans script, la zone reste une zone de
     * texte). Une mise en forme sans texte ni image ne vaut rien : chaîne vide.
     */
    public static function depuisFormulaire(string $valeur): string
    {
        if (!self::estRiche($valeur)) {
            return trim($valeur);
        }

        [$sommaire, $html] = self::decouper($valeur);
        $html = self::nettoyer($html);

        return self::estVide($html) ? '' : self::entete($sommaire) . $html;
    }

    /** Pour l'affichage : du HTML sûr, sans saut de ligne brut (les conteneurs gardent « pre-line »). */
    public static function versHtml(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (!self::estRiche($valeur)) {
            $texte = str_replace(["\r\n", "\r"], "\n", trim($valeur));

            return str_replace("\n", '<br>', htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        [$sommaire, $html] = self::decouper($valeur);
        $html = self::nettoyer($html);

        return $sommaire > 0 ? self::avecSommaire($html, $sommaire) : $html;
    }

    /**
     * Pour l'éditeur : la valeur que reçoit la zone de texte. Un texte riche y
     * arrive nettoyé, marques comprises ; un texte brut, tel quel.
     */
    public static function pourEditeur(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (!self::estRiche($valeur)) {
            return $valeur;
        }
        [$sommaire, $html] = self::decouper($valeur);

        return self::entete($sommaire) . self::nettoyer($html);
    }

    /**
     * Le texte seul, sauts de ligne compris : pour les extraits, la recherche,
     * les cartes, et les agendas où l'évènement part, qui ne lisent que du texte.
     */
    public static function versTexte(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (!self::estRiche($valeur)) {
            return $valeur;
        }

        // Les images d'abord : leur contenu encodé n'a rien d'un texte, et pèse.
        [, $html] = self::decouper(preg_replace('~<img\b[^>]*>~i', '', $valeur) ?? '');

        return trim(self::texteDe(self::nettoyer($html)));
    }

    /* --- Interne ---------------------------------------------------------- */

    /** @return array{0: int, 1: string} le niveau du sommaire (0 : aucun) et le HTML */
    private static function decouper(string $valeur): array
    {
        $reste = substr($valeur, strlen(self::MARQUE));
        if (preg_match('/^<!--sommaire:([1-3])-->/', $reste, $m) === 1) {
            return [(int) $m[1], substr($reste, strlen($m[0]))];
        }

        return [0, $reste];
    }

    private static function entete(int $sommaire): string
    {
        return self::MARQUE . ($sommaire > 0 ? '<!--sommaire:' . $sommaire . '-->' : '');
    }

    private static function estVide(string $html): bool
    {
        if (str_contains($html, '<img ')) {
            return false;
        }
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $texte)) === '';
    }

    private static function nettoyer(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        $avant = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        $racine = null;
        foreach ($doc->childNodes as $noeud) {
            if ($noeud instanceof DOMElement) {
                $racine = $noeud;
                break;
            }
        }

        return $racine === null ? '' : self::enfants($racine, 0);
    }

    private static function enfants(DOMNode $parent, int $profondeur): string
    {
        $sortie = '';
        foreach ($parent->childNodes as $noeud) {
            $sortie .= self::noeud($noeud, $profondeur);
        }

        return $sortie;
    }

    private static function noeud(DOMNode $noeud, int $profondeur): string
    {
        if ($noeud instanceof DOMText) {
            // Un saut de ligne brut dans le texte ne dit rien en HTML : une espace.
            $texte = str_replace(["\r\n", "\r", "\n"], ' ', $noeud->nodeValue ?? '');

            return htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$noeud instanceof DOMElement || $profondeur > self::PROFONDEUR_MAX) {
            return '';
        }

        $nom = strtolower($noeud->nodeName);
        if (in_array($nom, self::JETEES, true)) {
            return '';
        }

        $balise = self::BALISES[$nom] ?? null;
        if ($balise === 'img') {
            return self::image($noeud);
        }

        $interieur = self::enfants($noeud, $profondeur + 1);

        if ($balise === null) {
            // Une balise inconnue s'efface, son texte reste.
            return $interieur;
        }
        if ($balise === 'br') {
            return '<br>';
        }
        if ($balise === 'span') {
            $style = self::style($noeud, false);

            return $style === '' ? $interieur : '<span style="' . $style . '">' . $interieur . '</span>';
        }

        $style = in_array($balise, self::BLOCS, true) ? self::style($noeud, true) : '';

        return '<' . $balise . ($style === '' ? '' : ' style="' . $style . '"') . '>'
            . $interieur . '</' . $balise . '>';
    }

    /**
     * Une image embarquée dans le texte, et rien d'autre : un PNG, un JPEG, un
     * GIF ou un WebP encodé dans la page — jamais une adresse, qui irait
     * chercher ailleurs —, sa largeur, et sa place par rapport au texte.
     */
    private static function image(DOMElement $img): string
    {
        $source = trim($img->getAttribute('src'));
        if (strlen($source) > self::IMAGE_MAX
            || preg_match('~^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/]+={0,2}$~', $source) !== 1) {
            return '';
        }

        $declarations = self::declarations($img->getAttribute('style'));
        $style = [];

        $largeur = $declarations['width'] ?? '';
        if (preg_match('/^(\d{1,3})%$/', $largeur, $m) === 1 && (int) $m[1] >= 3 && (int) $m[1] <= 100) {
            $style[] = 'width: ' . (int) $m[1] . '%';
        }

        // La place : trois habillages, écrits toujours de la même façon.
        $flottant = strtolower($declarations['float'] ?? '');
        if ($flottant === 'left') {
            $style[] = 'float: left; margin: 0 1em .5em 0';
        } elseif ($flottant === 'right') {
            $style[] = 'float: right; margin: 0 0 .5em 1em';
        } elseif (strtolower($declarations['display'] ?? '') === 'block') {
            $style[] = 'display: block; margin: .5em auto';
        }

        $alt = htmlspecialchars(mb_substr($img->getAttribute('alt'), 0, 120), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<img src="' . $source . '" alt="' . $alt . '"'
            . ($style === [] ? '' : ' style="' . implode('; ', $style) . '"') . '>';
    }

    /** @return array<string, string> les déclarations d'un attribut « style », propriété en minuscules */
    private static function declarations(string $style): array
    {
        $trouvees = [];
        foreach (explode(';', $style) as $declaration) {
            [$propriete, $valeur] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            if ($propriete !== '') {
                $trouvees[strtolower($propriete)] = $valeur;
            }
        }

        return $trouvees;
    }

    /**
     * Quelques propriétés, aux valeurs écrites comme le navigateur les écrit —
     * rien d'autre. Un bloc peut être aligné ; un morceau de texte, coloré,
     * surligné ou grossi.
     */
    private static function style(DOMElement $element, bool $bloc): string
    {
        $declarations = self::declarations($element->getAttribute('style'));
        $gardees = [];

        if ($bloc) {
            $alignement = strtolower($declarations['text-align'] ?? '');
            if (in_array($alignement, ['left', 'center', 'right', 'justify'], true)) {
                $gardees['text-align'] = $alignement;
            }
        } else {
            if (strtolower($element->nodeName) === 'font') {
                $couleur = self::couleur($element->getAttribute('color'));
                if ($couleur !== null) {
                    $gardees['color'] = $couleur;
                }
            }
            foreach (['color', 'background-color'] as $propriete) {
                $couleur = self::couleur($declarations[$propriete] ?? '');
                if ($couleur !== null) {
                    $gardees[$propriete] = $couleur;
                }
            }
            $taille = strtolower($declarations['font-size'] ?? '');
            if (preg_match('/^(\d{1,2})pt$/', $taille, $m) === 1 && in_array((int) $m[1], self::TAILLES, true)) {
                $gardees['font-size'] = (int) $m[1] . 'pt';
            }
        }

        $style = [];
        foreach ($gardees as $propriete => $valeur) {
            $style[] = $propriete . ': ' . $valeur;
        }

        return implode('; ', $style);
    }

    private static function couleur(string $valeur): ?string
    {
        $valeur = strtolower(trim($valeur));
        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/', $valeur) === 1 || $valeur === 'transparent') {
            return $valeur;
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $valeur) === 1) {
            return $valeur;
        }

        return null;
    }

    /**
     * Le sommaire, en tête : un lien par titre jusqu'au niveau voulu. Les titres
     * reçoivent l'ancre où mène leur lien — posée ici, sur du HTML déjà
     * nettoyé et écrit par nous, jamais reprise de ce qu'on a reçu.
     */
    private static function avecSommaire(string $html, int $niveau): string
    {
        $balises = array_slice(['h2', 'h3', 'h4'], 0, $niveau);
        $entrees = [];
        $rang = 0;

        $html = preg_replace_callback('~<(h[234])((?: style="[^"]*")?)>(.*?)</\1>~s',
            static function (array $m) use ($balises, &$entrees, &$rang): string {
                if (!in_array($m[1], $balises, true)) {
                    return $m[0];
                }
                $texte = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($texte === '') {
                    return $m[0];
                }
                $rang++;
                $ancre = 'sommaire-titre-' . $rang;
                $entrees[] = '<li class="sommaire-texte__niveau' . ((int) substr($m[1], 1) - 1) . '">'
                    . '<a href="#' . $ancre . '">' . htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>';

                return '<' . $m[1] . ' id="' . $ancre . '"' . $m[2] . '>' . $m[3] . '</' . $m[1] . '>';
            }, $html) ?? $html;

        if ($entrees === []) {
            return $html;
        }

        return '<nav class="sommaire-texte" data-apercu-sommaire aria-label="Sommaire">'
            . '<p class="sommaire-texte__titre">Sommaire</p><ul>' . implode('', $entrees) . '</ul></nav>' . $html;
    }

    /** Le texte d'un HTML déjà nettoyé : les blocs et les « br » deviennent des lignes. */
    private static function texteDe(string $html): string
    {
        // Une ligne commence à chaque bloc et à chaque « br » ; un point, à chaque élément de liste.
        $html = preg_replace('~<li\b[^>]*>~', "\n• ", $html) ?? $html;
        $html = preg_replace('~<br>|<(div|h[234]|blockquote)\b[^>]*>|</?ul>|</?ol>~', "\n", $html) ?? $html;
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = preg_replace("/[ \t]+\n/", "\n", $texte) ?? $texte;

        return preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte;
    }
}
