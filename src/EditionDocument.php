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
     * Réécrit le corps du document avec les paragraphes fournis, dans l'ordre.
     *
     * Chaque entrée porte le rang du paragraphe d'origine dont elle reprend la
     * mise en forme, ou null pour un paragraphe neuf. Un rang absent de la
     * liste correspond à un paragraphe supprimé ; un rang cité deux fois donne
     * deux paragraphes de même allure, ce qui arrive quand on coupe un
     * paragraphe en deux.
     *
     * @param array<int, array{origine: ?int, texte: string}> $entrees
     * @throws RuntimeException si le document ne peut être ni lu ni réécrit
     */
    public static function enregistrer(string $chemin, string $nomOrigine, array $entrees): void
    {
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

            self::remplacerTexte($doc, $noeud, $entree['texte'], $gabarit);
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
        ?DOMElement $gabarit
    ): void {
        if ($paragraphe->namespaceURI === self::NS_W) {
            self::remplacerTexteWord($doc, $paragraphe, $texte, $gabarit);
            return;
        }

        // En ODF, le style du paragraphe est porté par ses attributs : vider
        // son contenu suffit, la forme reste.
        while ($paragraphe->firstChild !== null) {
            $paragraphe->removeChild($paragraphe->firstChild);
        }
        if ($texte !== '') {
            $paragraphe->appendChild($doc->createTextNode($texte));
        }
    }

    private static function remplacerTexteWord(
        DOMDocument $doc,
        DOMElement $paragraphe,
        string $texte,
        ?DOMElement $gabarit
    ): void {
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
}
