<?php
declare(strict_types=1);

/**
 * Un texte mis en forme : gras, italique, souligné, listes, couleur, surlignage.
 *
 * La fiche de révision, le contenu d'un cours et les notes d'un évènement
 * s'écrivent dans un petit traitement de texte. Ce qu'il envoie est du HTML,
 * rangé en base derrière une marque — un commentaire que rien d'autre n'écrit —
 * pour le distinguer des textes d'avant, qui restent du texte brut et
 * s'affichent comme avant.
 *
 * La marque ne fait jamais foi : un agenda importé pourrait très bien commencer
 * sa description par elle. Tout ce qui sort d'ici en HTML est donc repassé au
 * crible à chaque fois — une liste blanche de balises, aucun attribut sinon deux
 * couleurs vérifiées —, qu'il vienne du formulaire ou de la base.
 */
final class TexteRiche
{
    public const MARQUE = '<!--riche-->';

    /** Les balises gardées, et ce qu'elles deviennent. */
    private const BALISES = [
        'b' => 'b', 'strong' => 'b',
        'i' => 'i', 'em' => 'i',
        'u' => 'u', 'ins' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'div' => 'div', 'p' => 'div',
        'span' => 'span', 'font' => 'span',
        'br' => 'br',
    ];

    /** Celles dont le contenu même est jeté. */
    private const JETEES = ['script', 'style', 'head', 'title', 'template', 'iframe', 'object',
                            'embed', 'svg', 'math', 'noscript', 'textarea', 'select'];

    /** Au-delà, une imbrication n'a plus rien d'un texte écrit à la main. */
    private const PROFONDEUR_MAX = 40;

    public static function estRiche(?string $valeur): bool
    {
        return $valeur !== null && str_starts_with($valeur, self::MARQUE);
    }

    /**
     * Ce qu'un formulaire envoie, prêt à ranger : du HTML nettoyé derrière la
     * marque, ou le texte brut tel quel (sans script, la zone reste une zone de
     * texte). Une mise en forme sans aucun texte ne vaut rien : chaîne vide.
     */
    public static function depuisFormulaire(string $valeur): string
    {
        if (!self::estRiche($valeur)) {
            return trim($valeur);
        }

        $html = self::nettoyer(substr($valeur, strlen(self::MARQUE)));

        // Le texte seul, sans les puces qu'ajouterait la conversion en lignes.
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $texte)) === '' ? '' : self::MARQUE . $html;
    }

    /** Pour l'affichage : du HTML sûr, sans saut de ligne brut (les conteneurs gardent « pre-line »). */
    public static function versHtml(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (self::estRiche($valeur)) {
            return self::nettoyer(substr($valeur, strlen(self::MARQUE)));
        }

        $texte = str_replace(["\r\n", "\r"], "\n", trim($valeur));

        return str_replace("\n", '<br>', htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Pour l'éditeur : la valeur que reçoit la zone de texte. Un texte riche y
     * arrive nettoyé, marque comprise ; un texte brut, tel quel.
     */
    public static function pourEditeur(?string $valeur): string
    {
        $valeur = (string) $valeur;

        return self::estRiche($valeur) ? self::MARQUE . self::versHtml($valeur) : $valeur;
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

        return trim(self::texteDe(self::nettoyer(substr($valeur, strlen(self::MARQUE)))));
    }

    /* --- Interne ---------------------------------------------------------- */

    private static function nettoyer(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        $avant = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
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

        $interieur = self::enfants($noeud, $profondeur + 1);
        $balise = self::BALISES[$nom] ?? null;

        if ($balise === null) {
            // Une balise inconnue s'efface, son texte reste.
            return $interieur;
        }
        if ($balise === 'br') {
            return '<br>';
        }
        if ($balise === 'span') {
            $style = self::style($noeud);

            return $style === '' ? $interieur : '<span style="' . $style . '">' . $interieur . '</span>';
        }

        return '<' . $balise . '>' . $interieur . '</' . $balise . '>';
    }

    /** Deux propriétés, et des couleurs écrites comme le navigateur les écrit — rien d'autre. */
    private static function style(DOMElement $element): string
    {
        $gardees = [];

        if (strtolower($element->nodeName) === 'font') {
            $couleur = self::couleur($element->getAttribute('color'));
            if ($couleur !== null) {
                $gardees['color'] = $couleur;
            }
        }

        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$propriete, $valeur] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $propriete = strtolower($propriete);
            if (!in_array($propriete, ['color', 'background-color'], true)) {
                continue;
            }
            $couleur = self::couleur($valeur);
            if ($couleur !== null) {
                $gardees[$propriete] = $couleur;
            }
        }

        $style = [];
        foreach ($gardees as $propriete => $couleur) {
            $style[] = $propriete . ': ' . $couleur;
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

    /** Le texte d'un HTML déjà nettoyé : les blocs et les « br » deviennent des lignes. */
    private static function texteDe(string $html): string
    {
        // Une ligne commence à chaque bloc et à chaque « br » ; un point, à chaque élément de liste.
        $html = preg_replace('~<li>~', "\n• ", $html) ?? $html;
        $html = preg_replace('~<br>|<div>|</?ul>|</?ol>~', "\n", $html) ?? $html;
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = preg_replace("/[ \t]+\n/", "\n", $texte) ?? $texte;

        return preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte;
    }
}
