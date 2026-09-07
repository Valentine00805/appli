<?php
declare(strict_types=1);

/**
 * Réécriture du texte d'un document bureautique.
 *
 * Un .docx est une archive ZIP contenant du XML, où chaque paragraphe est un
 * élément <w:p>. On peut donc remplacer le texte d'un paragraphe sans toucher
 * au reste de l'archive : styles, images, tableaux, en-têtes et mise en page
 * restent en place, et le fichier reste un vrai document Word.
 *
 * Ce n'est pas un traitement de texte. La mise en forme qui varie à
 * l'intérieur d'un paragraphe modifié — un mot en gras au milieu d'une phrase —
 * est ramenée à celle de son début, faute de savoir à quels mots la rattacher
 * une fois le texte réécrit. Les paragraphes non modifiés ne bougent pas.
 *
 * Seuls les paragraphes du corps sont proposés : ceux d'un tableau sont laissés
 * tranquilles, en supprimer un casserait le tableau.
 */
final class EditionDocument
{
    private const NS_W      = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_XML    = 'http://www.w3.org/XML/1998/namespace';
    private const NS_OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    private const NS_TEXT   = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const NS_STYLE  = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const NS_FO     = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

    /**
     * L'ordre imposé aux enfants de <w:rPr> par le schéma d'OOXML.
     *
     * Word lit un rPr désordonné, mais le document devient invalide et
     * d'autres logiciels le refusent. On ne cite que ce qu'on touche, plus les
     * voisins immédiats qui servent de repères.
     */
    private const ORDRE_RPR = [
        'rStyle' => 0, 'rFonts' => 1, 'b' => 2, 'bCs' => 3, 'i' => 4, 'iCs' => 5,
        'caps' => 6, 'smallCaps' => 7, 'strike' => 8, 'dstrike' => 9, 'outline' => 10,
        'shadow' => 11, 'emboss' => 12, 'imprint' => 13, 'noProof' => 14,
        'snapToGrid' => 15, 'vanish' => 16, 'webHidden' => 17, 'color' => 18,
        'spacing' => 19, 'w' => 20, 'kern' => 21, 'position' => 22,
        'sz' => 23, 'szCs' => 24, 'highlight' => 25, 'u' => 26, 'effect' => 27,
        'bdr' => 28, 'shd' => 29, 'fitText' => 30, 'vertAlign' => 31, 'rtl' => 32,
        'cs' => 33, 'em' => 34, 'lang' => 35, 'eastAsianLayout' => 36,
    ];

    /** Les tailles proposées, en points. Au-delà, on quitte le « basique ». */
    public const TAILLES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 40];

    /**
     * Où trouver le texte modifiable : la partie de l'archive à réécrire,
     * l'élément qui contient le corps, et ce qui compte pour un paragraphe.
     */
    private const FORMATS = [
        'docx' => [
            'partie'     => 'word/document.xml',
            'corps'      => [self::NS_W, 'body'],
            'paragraphe' => [self::NS_W => ['p']],
            'neuf'       => [self::NS_W, 'w:p'],
        ],
        'odt' => [
            'partie'     => 'content.xml',
            'corps'      => [self::NS_OFFICE, 'text'],
            'paragraphe' => [self::NS_TEXT => ['p', 'h']],
            'neuf'       => [self::NS_TEXT, 'text:p'],
        ],
    ];

    /** Le texte de ce fichier peut-il être réécrit depuis l'application ? */
    public static function modifiable(string $nomOrigine): bool
    {
        return isset(self::FORMATS[self::extension($nomOrigine)]);
    }

    /**
     * Les paragraphes du corps, dans l'ordre. Les paragraphes vides sont
     * gardés : ce sont les lignes blanches du document, les retirer
     * décalerait tout le reste.
     *
     * @return array<int, string>
     * @throws RuntimeException si le fichier est illisible
     */
    public static function lire(string $chemin, string $nomOrigine): array
    {
        [, , $paragraphes] = self::ouvrir($chemin, $nomOrigine);

        return array_map(
            static fn (DOMElement $p): string => (string) $p->textContent,
            $paragraphes
        );
    }

    /**
     * Les paragraphes, mise en forme comprise, en HTML restreint.
     *
     * Seuls le gras, l'italique, le souligné et la taille sont rendus : ce que
     * l'éditeur sait remettre dans le document. Le reste — couleurs, polices,
     * styles de titre — reste dans le fichier sans passer par ici, et n'est
     * donc pas perdu.
     *
     * @return array<int, string>
     * @throws RuntimeException si le fichier est illisible
     */
    public static function lireRiche(string $chemin, string $nomOrigine): array
    {
        [$doc, , $paragraphes] = self::ouvrir($chemin, $nomOrigine);
        $stylesOdf = self::stylesDeTexteOdf($doc);

        return array_map(
            static fn (DOMElement $p): string => self::html(
                $p->namespaceURI === self::NS_W
                    ? self::passagesWord($p)
                    : self::passagesOdf($p, $stylesOdf)
            ),
            $paragraphes
        );
    }

    /**
     * Réécrit le corps du document avec les paragraphes fournis, dans l'ordre.
     *
     * Chaque entrée porte le rang du paragraphe d'origine dont elle reprend la
     * mise en forme, ou null pour un paragraphe neuf. Un rang absent de la
     * liste correspond à un paragraphe supprimé ; un rang cité deux fois donne
     * deux paragraphes de même allure, ce qui arrive quand on coupe un
     * paragraphe en deux.
     *
     * Le texte arrive en HTML restreint quand $riche vaut vrai : chaque
     * paragraphe est alors découpé en passages, et l'absence de balise veut
     * dire « pas de gras » plutôt que « on ne sait pas ». Sans JavaScript le
     * formulaire envoie du texte nu, et l'ancienne règle s'applique : le
     * paragraphe garde l'allure de son premier passage.
     *
     * @param array<int, array{origine: ?int, texte: string}> $entrees
     * @throws RuntimeException si le document ne peut être ni lu ni réécrit
     */
    public static function enregistrer(
        string $chemin,
        string $nomOrigine,
        array $entrees,
        bool $riche = false
    ): void {
        foreach ($entrees as $entree) {
            // Un XML n'accepte que de l'UTF-8. Mieux vaut refuser d'écrire que
            // de glisser des caractères abîmés dans le document de quelqu'un.
            if (!mb_check_encoding($entree['texte'], 'UTF-8')) {
                throw new RuntimeException('Le texte envoyé n’est pas dans un encodage valide.');
            }
        }

        [$doc, $corps, $paragraphes, $format] = self::ouvrir($chemin, $nomOrigine);

        // Un paragraphe neuf reprend la forme de celui qui le précède : créé
        // de zéro, il n'aurait ni style ni police et détonnerait dans la page.
        $modele = $paragraphes === [] ? null : end($paragraphes);
        $gabarit = self::gabaritTexte($doc);
        $utilises = [];
        $nouveaux = [];

        foreach ($entrees as $entree) {
            $rang = $entree['origine'];
            $existant = $rang !== null && isset($paragraphes[$rang]) ? $paragraphes[$rang] : null;

            if ($existant !== null && !isset($utilises[$rang])) {
                $noeud = $existant;
                $utilises[$rang] = true;
            } elseif ($existant !== null) {
                $noeud = self::copier($existant);
            } elseif ($modele !== null) {
                $noeud = self::copier($modele);
            } else {
                [$ns, $nom] = $format['neuf'];
                $noeud = $doc->createElementNS($ns, $nom);
            }

            self::remplacerTexte($doc, $noeud, $entree['texte'], $gabarit, $riche);
            $nouveaux[] = $noeud;
            $modele = $noeud;
        }

        self::replacer($doc, $corps, $paragraphes, $nouveaux);

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Le document n’a pas pu être réécrit.');
        }

        self::ecrire($chemin, $nomOrigine, (string) $format['partie'], $xml);
    }

    /* --- Écriture ------------------------------------------------------- */

    /**
     * Remplace une partie de l'archive, sans jamais toucher à l'original tant
     * que la nouvelle version n'a pas été relue sans erreur.
     */
    private static function ecrire(string $chemin, string $nomOrigine, string $partie, string $xml): void
    {
        $temporaire = $chemin . '.edition';
        if (!copy($chemin, $temporaire)) {
            throw new RuntimeException('Impossible de préparer l’enregistrement du document.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporaire) !== true) {
                throw new RuntimeException('Le fichier est illisible : ce n’est pas une archive valide.');
            }
            $ajoute = $zip->addFromString($partie, $xml);
            $ferme = $zip->close();
            if (!$ajoute || !$ferme) {
                throw new RuntimeException('Le document n’a pas pu être réécrit.');
            }

            // Relecture de contrôle : une archive cassée ne doit jamais
            // remplacer un document que l'utilisateur croit en sécurité.
            self::lire($temporaire, $nomOrigine);

            self::garderOriginal($chemin);

            if (!rename($temporaire, $chemin)) {
                throw new RuntimeException('Impossible de remplacer le document sur le disque.');
            }
        } catch (Throwable $e) {
            if (is_file($temporaire)) {
                @unlink($temporaire);
            }
            throw $e;
        }
    }

    /**
     * Garde une copie du document tel qu'il a été déposé, avant la toute
     * première modification. Elle n'est écrite qu'une fois : c'est un filet de
     * sécurité, pas un historique.
     */
    private static function garderOriginal(string $chemin): void
    {
        $dossier = dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'versions';
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return;
        }
        $cible = $dossier . DIRECTORY_SEPARATOR . basename($chemin);
        if (!is_file($cible)) {
            @copy($chemin, $cible);
        }
    }

    /** Le dossier des copies d'origine, pour que la suppression les emporte. */
    public static function dossierVersions(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'versions';
    }

    /* --- Manipulation du XML -------------------------------------------- */

    /**
     * Met la nouvelle suite de paragraphes à la place de l'ancienne.
     *
     * @param array<int, DOMElement> $anciens
     * @param array<int, DOMElement> $nouveaux
     */
    private static function replacer(DOMDocument $doc, DOMElement $corps, array $anciens, array $nouveaux): void
    {
        // Un repère tient la place pendant l'échange : dans un .docx, <w:sectPr>
        // décrit la mise en page et doit rester le dernier enfant du corps.
        $repere = $doc->createComment(' texte ');

        if ($anciens !== []) {
            $corps->insertBefore($repere, $anciens[0]);
        } else {
            $fin = $corps->lastChild;
            if ($fin instanceof DOMElement && $fin->namespaceURI === self::NS_W && $fin->localName === 'sectPr') {
                $corps->insertBefore($repere, $fin);
            } else {
                $corps->appendChild($repere);
            }
        }

        foreach ($anciens as $ancien) {
            if ($ancien->parentNode !== null) {
                $ancien->parentNode->removeChild($ancien);
            }
        }
        foreach ($nouveaux as $noeud) {
            $corps->insertBefore($noeud, $repere);
        }

        $corps->removeChild($repere);
    }

    /**
     * Copie un paragraphe pour en faire un voisin.
     *
     * Word numérote chaque paragraphe pour y accrocher commentaires et
     * révisions : deux paragraphes ne peuvent pas porter le même numéro, la
     * copie repart donc sans. Word lui en attribuera un nouveau.
     */
    private static function copier(DOMElement $paragraphe): DOMElement
    {
        /** @var DOMElement $copie */
        $copie = $paragraphe->cloneNode(true);
        foreach (['paraId', 'textId'] as $attribut) {
            $copie->removeAttributeNS('http://schemas.microsoft.com/office/word/2010/wordml', $attribut);
        }
        return $copie;
    }

    /**
     * Un <w:t> vide emprunté au document. Créer l'élément de toutes pièces
     * ferait redéclarer l'espace de noms sur chaque balise ; le recopier garde
     * le XML aussi propre que Word l'a écrit.
     */
    private static function gabaritTexte(DOMDocument $doc): ?DOMElement
    {
        $t = $doc->getElementsByTagNameNS(self::NS_W, 't')->item(0);
        if (!$t instanceof DOMElement) {
            return null;
        }
        /** @var DOMElement $copie */
        $copie = $t->cloneNode(false);
        return $copie;
    }

    private static function remplacerTexte(
        DOMDocument $doc,
        DOMElement $paragraphe,
        string $texte,
        ?DOMElement $gabarit,
        bool $riche = false
    ): void {
        $passages = $riche ? self::passagesDuHtml($texte) : null;

        if ($paragraphe->namespaceURI === self::NS_W) {
            self::remplacerTexteWord($doc, $paragraphe, $texte, $gabarit, $passages);
            return;
        }

        // En ODF, le style du paragraphe est porté par ses attributs : vider
        // son contenu suffit, la forme reste.
        while ($paragraphe->firstChild !== null) {
            $paragraphe->removeChild($paragraphe->firstChild);
        }

        if ($passages === null) {
            if ($texte !== '') {
                $paragraphe->appendChild($doc->createTextNode($texte));
            }
            return;
        }

        foreach ($passages as $passage) {
            if ($passage['texte'] === '') {
                continue;
            }
            $noeud = $doc->createTextNode($passage['texte']);
            $style = self::styleOdf($doc, $passage);
            if ($style === null) {
                $paragraphe->appendChild($noeud);
                continue;
            }
            $span = $doc->createElementNS(self::NS_TEXT, 'text:span');
            $span->setAttributeNS(self::NS_TEXT, 'text:style-name', $style);
            $span->appendChild($noeud);
            $paragraphe->appendChild($span);
        }
    }

    /**
     * @param ?list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}> $passages
     */
    private static function remplacerTexteWord(
        DOMDocument $doc,
        DOMElement $paragraphe,
        string $texte,
        ?DOMElement $gabarit,
        ?array $passages = null
    ): void {
        if ($passages !== null) {
            self::ecrirePassagesWord($doc, $paragraphe, $passages, $gabarit);
            return;
        }

        /*
         * <w:pPr> porte le style du paragraphe : il reste tel quel. Le premier
         * <w:r> porte la police, la taille et la couleur : on le garde comme
         * moule et on jette les suivants — c'est de là que vient la mise en
         * forme uniforme d'un paragraphe modifié.
         */
        $run = null;
        foreach (iterator_to_array($paragraphe->childNodes) as $enfant) {
            if ($enfant instanceof DOMElement
                && $enfant->namespaceURI === self::NS_W
                && $enfant->localName === 'pPr'
            ) {
                continue;
            }
            if ($run === null && self::estElement($enfant, 'r')) {
                $run = $enfant;
                continue;
            }
            $paragraphe->removeChild($enfant);
        }

        if ($texte === '') {
            // Un paragraphe vidé garde son style mais plus rien à afficher.
            if ($run !== null) {
                $paragraphe->removeChild($run);
            }
            return;
        }

        if ($run === null) {
            $run = $doc->createElementNS(self::NS_W, 'w:r');
            $paragraphe->appendChild($run);
        }

        // Dans le passage retenu, <w:rPr> décrit la police et le premier
        // <w:t> reçoit le texte. Réutiliser la balise déjà présente plutôt
        // que d'en créer une évite à libxml de redéclarer l'espace de noms à
        // chaque ligne.
        $t = null;
        foreach (iterator_to_array($run->childNodes) as $enfant) {
            if ($enfant instanceof DOMElement
                && $enfant->namespaceURI === self::NS_W
                && $enfant->localName === 'rPr'
            ) {
                continue;
            }
            if ($t === null && self::estElement($enfant, 't')) {
                $t = $enfant;
                continue;
            }
            $run->removeChild($enfant);
        }

        if ($t === null) {
            $t = $gabarit !== null ? $gabarit->cloneNode(false) : $doc->createElementNS(self::NS_W, 'w:t');
            /** @var DOMElement $t */
            $run->appendChild($t);
        }

        while ($t->firstChild !== null) {
            $t->removeChild($t->firstChild);
        }
        // Sans cet attribut, Word rogne les espaces de début et de fin.
        $t->setAttributeNS(self::NS_XML, 'xml:space', 'preserve');
        $t->appendChild($doc->createTextNode($texte));
    }

    /** Cet enfant est-il l'élément Word attendu ? */
    private static function estElement(DOMNode $noeud, string $nom): bool
    {
        return $noeud instanceof DOMElement
            && $noeud->namespaceURI === self::NS_W
            && $noeud->localName === $nom;
    }

    /* --- Lecture -------------------------------------------------------- */

    /**
     * @return array{0: DOMDocument, 1: DOMElement, 2: array<int, DOMElement>, 3: array}
     * @throws RuntimeException si le fichier est illisible
     */
    private static function ouvrir(string $chemin, string $nomOrigine): array
    {
        $ext = self::extension($nomOrigine);
        if (!isset(self::FORMATS[$ext])) {
            throw new RuntimeException('Ce format ne se modifie pas dans l’application.');
        }
        $format = self::FORMATS[$ext];

        $zip = new ZipArchive();
        if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Le fichier est illisible : ce n’est pas une archive valide.');
        }
        $xml = $zip->getFromName((string) $format['partie']);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Le contenu du document est introuvable dans le fichier.');
        }

        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // LIBXML_NONET coupe tout accès réseau : un fichier piégé ne peut pas
        // faire lire le serveur à travers une entité externe.
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        if (!$ok) {
            throw new RuntimeException('Le contenu du document est illisible.');
        }

        [$nsCorps, $nomCorps] = $format['corps'];
        $corps = $doc->getElementsByTagNameNS($nsCorps, $nomCorps)->item(0);
        if (!$corps instanceof DOMElement) {
            throw new RuntimeException('Le corps du document est introuvable.');
        }

        $paragraphes = [];
        foreach ($corps->childNodes as $enfant) {
            if (self::estParagraphe($enfant, $format)) {
                $paragraphes[] = $enfant;
            }
        }

        return [$doc, $corps, $paragraphes, $format];
    }

    private static function estParagraphe(DOMNode $noeud, array $format): bool
    {
        if (!$noeud instanceof DOMElement) {
            return false;
        }
        foreach ($format['paragraphe'] as $ns => $noms) {
            if ($noeud->namespaceURI === $ns && in_array($noeud->localName, $noms, true)) {
                return true;
            }
        }
        return false;
    }

    private static function extension(string $nom): string
    {
        return strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    }

    /* --- Le texte enrichi : gras, italique, souligné, taille ------------- */

    /**
     * Le HTML restreint d'une suite de passages.
     *
     * @param list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}> $passages
     */
    private static function html(array $passages): string
    {
        $html = '';
        foreach ($passages as $passage) {
            if ($passage['texte'] === '') {
                continue;
            }
            $morceau = htmlspecialchars($passage['texte'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($passage['taille'] !== null) {
                $morceau = '<span data-taille="' . $passage['taille'] . '">' . $morceau . '</span>';
            }
            if ($passage['souligne']) {
                $morceau = '<u>' . $morceau . '</u>';
            }
            if ($passage['italique']) {
                $morceau = '<i>' . $morceau . '</i>';
            }
            if ($passage['gras']) {
                $morceau = '<b>' . $morceau . '</b>';
            }
            $html .= $morceau;
        }

        return $html;
    }

    /**
     * Découpe le HTML de l'éditeur en passages.
     *
     * Tout ce qui n'est pas reconnu ne laisse que son texte : le document ne
     * peut donc pas recevoir de balise venue d'ailleurs, quoi qu'on envoie.
     *
     * @return list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}>
     */
    private static function passagesDuHtml(string $html): array
    {
        if (trim(strip_tags($html)) === '' && !str_contains($html, '&nbsp;')) {
            return [];
        }

        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="racine">' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        $racine = $doc->getElementById('racine');
        $passages = [];
        if ($racine !== null) {
            self::parcourirHtml($racine, ['gras' => false, 'italique' => false,
                'souligne' => false, 'taille' => null], $passages);
        }

        return self::fondrePassages($passages);
    }

    /** Descend dans le HTML en tenant à jour la mise en forme du moment. */
    private static function parcourirHtml(DOMNode $noeud, array $etat, array &$passages): void
    {
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                $texte = str_replace("\u{00a0}", ' ', $enfant->data);
                if ($texte !== '') {
                    $passages[] = $etat + ['texte' => $texte];
                }
                continue;
            }
            if (!$enfant instanceof DOMElement) {
                continue;
            }

            $sien = $etat;
            $nom = strtolower($enfant->localName);
            if (in_array($nom, ['b', 'strong'], true)) {
                $sien['gras'] = true;
            }
            if (in_array($nom, ['i', 'em'], true)) {
                $sien['italique'] = true;
            }
            if ($nom === 'u') {
                $sien['souligne'] = true;
            }
            if ($nom === 'br') {
                $passages[] = $etat + ['texte' => ' '];
                continue;
            }
            // La taille voyage soit dans notre attribut, soit dans le style que
            // le navigateur a écrit tout seul.
            $taille = self::tailleDemandee($enfant);
            if ($taille !== null) {
                $sien['taille'] = $taille;
            }
            $sien = self::styleEnLigne($enfant, $sien);

            self::parcourirHtml($enfant, $sien, $passages);
        }
    }

    /** La taille écrite sur cette balise, ramenée aux valeurs proposées. */
    private static function tailleDemandee(DOMElement $element): ?int
    {
        $brut = $element->getAttribute('data-taille');

        if ($brut === '' && preg_match('/font-size\s*:\s*([\d.]+)\s*(pt|px)?/i',
            $element->getAttribute('style'), $m) === 1) {
            // Un navigateur écrit volontiers des pixels : 1 point en vaut 4/3.
            $points = strtolower($m[2] ?? 'pt') === 'px' ? (float) $m[1] * 0.75 : (float) $m[1];
            $brut = (string) (int) round($points);
        }

        if ($brut === '' || !ctype_digit($brut)) {
            return null;
        }

        $taille = (int) $brut;

        return in_array($taille, self::TAILLES, true) ? $taille : null;
    }

    /** Le gras, l'italique et le souligné écrits en CSS plutôt qu'en balise. */
    private static function styleEnLigne(DOMElement $element, array $etat): array
    {
        $style = strtolower($element->getAttribute('style'));
        if ($style === '') {
            return $etat;
        }
        if (preg_match('/font-weight\s*:\s*(bold|[6-9]00)/', $style) === 1) {
            $etat['gras'] = true;
        }
        if (preg_match('/font-style\s*:\s*italic/', $style) === 1) {
            $etat['italique'] = true;
        }
        if (preg_match('/text-decoration[^:]*:\s*[^;]*underline/', $style) === 1) {
            $etat['souligne'] = true;
        }

        return $etat;
    }

    /**
     * Recolle les passages voisins de même allure.
     *
     * Un navigateur découpe volontiers un mot en trois nœuds ; sans cela, le
     * document se remplirait de passages d'une lettre.
     *
     * @return list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}>
     */
    private static function fondrePassages(array $passages): array
    {
        $fondus = [];
        foreach ($passages as $passage) {
            $dernier = $fondus === [] ? null : count($fondus) - 1;
            if ($dernier !== null
                && $fondus[$dernier]['gras'] === $passage['gras']
                && $fondus[$dernier]['italique'] === $passage['italique']
                && $fondus[$dernier]['souligne'] === $passage['souligne']
                && $fondus[$dernier]['taille'] === $passage['taille']
            ) {
                $fondus[$dernier]['texte'] .= $passage['texte'];
                continue;
            }
            $fondus[] = ['texte' => $passage['texte'], 'gras' => $passage['gras'],
                'italique' => $passage['italique'], 'souligne' => $passage['souligne'],
                'taille' => $passage['taille']];
        }

        return array_values(array_filter($fondus, static fn (array $p): bool => $p['texte'] !== ''));
    }

    /* --- Côté Word ------------------------------------------------------- */

    /**
     * Les passages d'un paragraphe Word, lus dans ses <w:r>.
     *
     * @return list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}>
     */
    private static function passagesWord(DOMElement $paragraphe): array
    {
        $passages = [];
        foreach ($paragraphe->getElementsByTagNameNS(self::NS_W, 'r') as $run) {
            $texte = '';
            foreach ($run->childNodes as $enfant) {
                if (self::estElement($enfant, 't')) {
                    $texte .= $enfant->textContent;
                } elseif (self::estElement($enfant, 'tab')) {
                    $texte .= "\t";
                }
            }
            if ($texte === '') {
                continue;
            }

            $rPr = null;
            foreach ($run->childNodes as $enfant) {
                if (self::estElement($enfant, 'rPr')) {
                    $rPr = $enfant;
                    break;
                }
            }

            $passages[] = [
                'texte'    => $texte,
                'gras'     => self::marqueWord($rPr, 'b'),
                'italique' => self::marqueWord($rPr, 'i'),
                'souligne' => self::souligneWord($rPr),
                'taille'   => self::tailleWord($rPr),
            ];
        }

        return self::fondrePassages($passages);
    }

    /** Un <w:b/> sans valeur veut dire « oui » ; « 0 » et « false » disent non. */
    private static function marqueWord(?DOMElement $rPr, string $nom): bool
    {
        $element = self::enfantWord($rPr, $nom);
        if ($element === null) {
            return false;
        }
        $val = $element->getAttributeNS(self::NS_W, 'val');

        return !in_array($val, ['0', 'false', 'off'], true);
    }

    private static function souligneWord(?DOMElement $rPr): bool
    {
        $u = self::enfantWord($rPr, 'u');

        return $u !== null && $u->getAttributeNS(self::NS_W, 'val') !== 'none';
    }

    /** La taille de Word est en demi-points. */
    private static function tailleWord(?DOMElement $rPr): ?int
    {
        $sz = self::enfantWord($rPr, 'sz');
        if ($sz === null) {
            return null;
        }
        $val = $sz->getAttributeNS(self::NS_W, 'val');
        if (!ctype_digit($val)) {
            return null;
        }
        $points = (int) round(((int) $val) / 2);

        return in_array($points, self::TAILLES, true) ? $points : null;
    }

    private static function enfantWord(?DOMElement $parent, string $nom): ?DOMElement
    {
        if ($parent === null) {
            return null;
        }
        foreach ($parent->childNodes as $enfant) {
            if (self::estElement($enfant, $nom)) {
                /** @var DOMElement $enfant */
                return $enfant;
            }
        }

        return null;
    }

    /**
     * Réécrit un paragraphe Word en un <w:r> par passage.
     *
     * Le premier passage existant sert de moule : sa police, sa couleur et son
     * style restent, seules les quatre marques que l'éditeur connaît sont
     * posées ou retirées.
     *
     * @param list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}> $passages
     */
    private static function ecrirePassagesWord(
        DOMDocument $doc,
        DOMElement $paragraphe,
        array $passages,
        ?DOMElement $gabarit
    ): void {
        $moule = null;
        foreach (iterator_to_array($paragraphe->childNodes) as $enfant) {
            if (self::estElement($enfant, 'pPr')) {
                continue;
            }
            if ($moule === null && self::estElement($enfant, 'r')) {
                /** @var DOMElement $enfant */
                $moule = $enfant->cloneNode(true);
            }
            $paragraphe->removeChild($enfant);
        }

        foreach ($passages as $passage) {
            $run = $moule !== null
                ? $moule->cloneNode(true)
                : $doc->createElementNS(self::NS_W, 'w:r');
            /** @var DOMElement $run */

            $rPr = null;
            foreach (iterator_to_array($run->childNodes) as $enfant) {
                if (self::estElement($enfant, 'rPr')) {
                    /** @var DOMElement $enfant */
                    $rPr = $enfant;
                    continue;
                }
                $run->removeChild($enfant);
            }
            if ($rPr === null && self::marquesUtiles($passage)) {
                $rPr = $doc->createElementNS(self::NS_W, 'w:rPr');
                $run->insertBefore($rPr, $run->firstChild);
            }
            if ($rPr !== null) {
                self::marquerRpr($doc, $rPr, $passage);
            }

            $t = $gabarit !== null
                ? $gabarit->cloneNode(false)
                : $doc->createElementNS(self::NS_W, 'w:t');
            /** @var DOMElement $t */
            // Sans cet attribut, Word rogne les espaces de début et de fin.
            $t->setAttributeNS(self::NS_XML, 'xml:space', 'preserve');
            $t->appendChild($doc->createTextNode($passage['texte']));
            $run->appendChild($t);

            $paragraphe->appendChild($run);
        }
    }

    private static function marquesUtiles(array $passage): bool
    {
        return $passage['gras'] || $passage['italique']
            || $passage['souligne'] || $passage['taille'] !== null;
    }

    /**
     * Pose les marques du passage dans un <w:rPr>, sans toucher au reste.
     *
     * Rien n'est écrit pour ce qui est absent : forcer « pas de gras » sur un
     * paragraphe dont le style le met en gras changerait l'allure du document
     * alors que l'éditeur n'a jamais montré ce gras-là.
     */
    private static function marquerRpr(DOMDocument $doc, DOMElement $rPr, array $passage): void
    {
        foreach (['b', 'bCs', 'i', 'iCs', 'u', 'sz', 'szCs'] as $nom) {
            $ancien = self::enfantWord($rPr, $nom);
            if ($ancien !== null) {
                $rPr->removeChild($ancien);
            }
        }

        if ($passage['gras']) {
            self::poserDansRpr($doc, $rPr, 'b', null);
            self::poserDansRpr($doc, $rPr, 'bCs', null);
        }
        if ($passage['italique']) {
            self::poserDansRpr($doc, $rPr, 'i', null);
            self::poserDansRpr($doc, $rPr, 'iCs', null);
        }
        if ($passage['souligne']) {
            self::poserDansRpr($doc, $rPr, 'u', 'single');
        }
        if ($passage['taille'] !== null) {
            $demi = (string) ($passage['taille'] * 2);
            self::poserDansRpr($doc, $rPr, 'sz', $demi);
            self::poserDansRpr($doc, $rPr, 'szCs', $demi);
        }
    }

    /** Insère un enfant de <w:rPr> à la place que le schéma lui réserve. */
    private static function poserDansRpr(
        DOMDocument $doc,
        DOMElement $rPr,
        string $nom,
        ?string $valeur
    ): void {
        $element = $doc->createElementNS(self::NS_W, 'w:' . $nom);
        if ($valeur !== null) {
            $element->setAttributeNS(self::NS_W, 'w:val', $valeur);
        }

        $rang = self::ORDRE_RPR[$nom] ?? PHP_INT_MAX;
        foreach ($rPr->childNodes as $enfant) {
            if (!$enfant instanceof DOMElement) {
                continue;
            }
            $sien = self::ORDRE_RPR[$enfant->localName] ?? PHP_INT_MAX;
            if ($sien > $rang) {
                $rPr->insertBefore($element, $enfant);
                return;
            }
        }
        $rPr->appendChild($element);
    }

    /* --- Côté LibreOffice ------------------------------------------------ */

    /**
     * Les styles de texte du document, par nom.
     *
     * @return array<string, array{gras: bool, italique: bool, souligne: bool, taille: ?int}>
     */
    private static function stylesDeTexteOdf(DOMDocument $doc): array
    {
        $styles = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_STYLE, 'style') as $style) {
            if ($style->getAttributeNS(self::NS_STYLE, 'family') !== 'text') {
                continue;
            }
            $nom = $style->getAttributeNS(self::NS_STYLE, 'name');
            $proprietes = $style->getElementsByTagNameNS(self::NS_STYLE, 'text-properties')->item(0);
            if ($nom === '' || !$proprietes instanceof DOMElement) {
                continue;
            }

            $taille = null;
            if (preg_match('/^([\d.]+)pt$/', $proprietes->getAttributeNS(self::NS_FO, 'font-size'), $m) === 1
                && in_array((int) round((float) $m[1]), self::TAILLES, true)) {
                $taille = (int) round((float) $m[1]);
            }

            $souligne = $proprietes->getAttributeNS(self::NS_STYLE, 'text-underline-style');
            $styles[$nom] = [
                'gras'     => $proprietes->getAttributeNS(self::NS_FO, 'font-weight') === 'bold',
                'italique' => $proprietes->getAttributeNS(self::NS_FO, 'font-style') === 'italic',
                'souligne' => $souligne !== '' && $souligne !== 'none',
                'taille'   => $taille,
            ];
        }

        return $styles;
    }

    /**
     * Les passages d'un paragraphe ODF.
     *
     * @return list<array{texte: string, gras: bool, italique: bool, souligne: bool, taille: ?int}>
     */
    private static function passagesOdf(DOMElement $paragraphe, array $styles): array
    {
        $passages = [];
        self::parcourirOdf($paragraphe,
            ['gras' => false, 'italique' => false, 'souligne' => false, 'taille' => null],
            $styles, $passages);

        return self::fondrePassages($passages);
    }

    private static function parcourirOdf(DOMNode $noeud, array $etat, array $styles, array &$passages): void
    {
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                if ($enfant->data !== '') {
                    $passages[] = $etat + ['texte' => $enfant->data];
                }
                continue;
            }
            if (!$enfant instanceof DOMElement || $enfant->namespaceURI !== self::NS_TEXT) {
                continue;
            }

            // Les espaces répétés et les tabulations ont leur propre balise.
            if ($enfant->localName === 's') {
                $combien = (int) ($enfant->getAttributeNS(self::NS_TEXT, 'c') ?: '1');
                $passages[] = $etat + ['texte' => str_repeat(' ', max(1, $combien))];
                continue;
            }
            if ($enfant->localName === 'tab') {
                $passages[] = $etat + ['texte' => "\t"];
                continue;
            }

            $sien = $etat;
            if ($enfant->localName === 'span') {
                $nom = $enfant->getAttributeNS(self::NS_TEXT, 'style-name');
                if (isset($styles[$nom])) {
                    $sien = [
                        'gras'     => $etat['gras'] || $styles[$nom]['gras'],
                        'italique' => $etat['italique'] || $styles[$nom]['italique'],
                        'souligne' => $etat['souligne'] || $styles[$nom]['souligne'],
                        'taille'   => $styles[$nom]['taille'] ?? $etat['taille'],
                    ];
                }
            }

            self::parcourirOdf($enfant, $sien, $styles, $passages);
        }
    }

    /**
     * Le nom du style automatique qui porte cette mise en forme, créé au besoin.
     *
     * @return ?string  null quand le passage n'a rien de particulier
     */
    private static function styleOdf(DOMDocument $doc, array $passage): ?string
    {
        if (!self::marquesUtiles($passage)) {
            return null;
        }

        // Un nom qui décrit ce qu'il porte : deux passages identiques
        // retrouvent le même style, sans en accumuler des centaines.
        $nom = sprintf('MesCours_%s%s%s%s',
            $passage['gras'] ? 'g' : '',
            $passage['italique'] ? 'i' : '',
            $passage['souligne'] ? 's' : '',
            $passage['taille'] !== null ? 't' . $passage['taille'] : '');

        foreach ($doc->getElementsByTagNameNS(self::NS_STYLE, 'style') as $style) {
            if ($style->getAttributeNS(self::NS_STYLE, 'name') === $nom) {
                return $nom;
            }
        }

        $automatiques = $doc->getElementsByTagNameNS(self::NS_OFFICE, 'automatic-styles')->item(0);
        if (!$automatiques instanceof DOMElement) {
            $automatiques = $doc->createElementNS(self::NS_OFFICE, 'office:automatic-styles');
            $corps = $doc->getElementsByTagNameNS(self::NS_OFFICE, 'body')->item(0);
            if ($corps instanceof DOMElement && $corps->parentNode !== null) {
                $corps->parentNode->insertBefore($automatiques, $corps);
            } elseif ($doc->documentElement !== null) {
                $doc->documentElement->appendChild($automatiques);
            } else {
                return null;
            }
        }

        $style = $doc->createElementNS(self::NS_STYLE, 'style:style');
        $style->setAttributeNS(self::NS_STYLE, 'style:name', $nom);
        $style->setAttributeNS(self::NS_STYLE, 'style:family', 'text');

        $proprietes = $doc->createElementNS(self::NS_STYLE, 'style:text-properties');
        if ($passage['gras']) {
            $proprietes->setAttributeNS(self::NS_FO, 'fo:font-weight', 'bold');
        }
        if ($passage['italique']) {
            $proprietes->setAttributeNS(self::NS_FO, 'fo:font-style', 'italic');
        }
        if ($passage['souligne']) {
            $proprietes->setAttributeNS(self::NS_STYLE, 'style:text-underline-style', 'solid');
            $proprietes->setAttributeNS(self::NS_STYLE, 'style:text-underline-width', 'auto');
            $proprietes->setAttributeNS(self::NS_STYLE, 'style:text-underline-color', 'font-color');
        }
        if ($passage['taille'] !== null) {
            $proprietes->setAttributeNS(self::NS_FO, 'fo:font-size', $passage['taille'] . 'pt');
        }

        $style->appendChild($proprietes);
        $automatiques->appendChild($style);

        return $nom;
    }
}
