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

    /* --- Les listes à puces ---------------------------------------------- */

    /** Où Word range la définition de ses listes, et comment on l'y annonce. */
    private const PART_NUM   = 'word/numbering.xml';
    private const PART_TYPES = '[Content_Types].xml';
    private const PART_RELS  = 'word/_rels/document.xml.rels';

    private const NS_TYPES = 'http://schemas.openxmlformats.org/package/2006/content-types';
    private const NS_RELS  = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const TYPE_NUM = 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml';
    private const REL_NUM  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';

    /**
     * Les deux sortes de liste, et ce qui les nomme dans chaque format.
     *
     * Le nom sert de repère : on retrouve ainsi d'un enregistrement à l'autre
     * la définition que l'application a déjà posée, au lieu d'en accumuler.
     */
    private const LISTES = [
        'puce'   => ['nom' => 'MesCoursPuce',   'style' => 'LMesCoursPuce'],
        'numero' => ['nom' => 'MesCoursNumero', 'style' => 'LMesCoursNumero'],
    ];

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

    /**
     * Les couleurs du surligneur de Word, qui n'en connaît pas d'autres.
     *
     * Quand la couleur choisie tombe sur l'une d'elles, on écrit un vrai
     * surlignage — celui que Word retire d'un clic sur son propre bouton. Sinon
     * on écrit une trame de fond, qui accepte n'importe quelle teinte.
     */
    private const SURLIGNAGES = [
        '000000' => 'black',    '0000FF' => 'blue',        '00FFFF' => 'cyan',
        '000080' => 'darkBlue', '008080' => 'darkCyan',    '808080' => 'darkGray',
        '008000' => 'darkGreen','800080' => 'darkMagenta', '800000' => 'darkRed',
        '808000' => 'darkYellow','00FF00' => 'green',      'C0C0C0' => 'lightGray',
        'FF00FF' => 'magenta',  'FF0000' => 'red',         'FFFFFF' => 'white',
        'FFFF00' => 'yellow',
    ];

    /**
     * Les alignements, du nom que l'application leur donne à ceux des formats.
     *
     * « justifie » ne se choisit pas dans la barre d'outils, mais se lit et se
     * réécrit : un paragraphe déjà justifié dans le document ne doit pas perdre
     * sa justification en passant par ici.
     */
    private const ALIGNEMENTS = [
        'gauche'   => ['both' => 'left',   'odf' => 'start'],
        'centre'   => ['both' => 'center', 'odf' => 'center'],
        'droite'   => ['both' => 'right',  'odf' => 'end'],
        'justifie' => ['both' => 'both',   'odf' => 'justify'],
    ];

    /**
     * L'ordre imposé aux enfants de <w:pPr>, autour de <w:jc>.
     *
     * Même règle que pour <w:rPr> : Word tolère le désordre, le schéma non.
     */
    private const ORDRE_PPR = [
        'pStyle' => 0, 'keepNext' => 1, 'keepLines' => 2, 'pageBreakBefore' => 3,
        'framePr' => 4, 'widowControl' => 5, 'numPr' => 6, 'suppressLineNumbers' => 7,
        'pBdr' => 8, 'shd' => 9, 'tabs' => 10, 'suppressAutoHyphens' => 11,
        'kinsoku' => 12, 'wordWrap' => 13, 'overflowPunct' => 14, 'topLinePunct' => 15,
        'autoSpaceDE' => 16, 'autoSpaceDN' => 17, 'bidi' => 18, 'adjustRightInd' => 19,
        'snapToGrid' => 20, 'spacing' => 21, 'ind' => 22, 'contextualSpacing' => 23,
        'mirrorIndents' => 24, 'suppressOverlap' => 25, 'jc' => 26, 'textDirection' => 27,
        'textAlignment' => 28, 'textboxTightWrap' => 29, 'outlineLvl' => 30,
        'divId' => 31, 'cnfStyle' => 32, 'rPr' => 33, 'sectPr' => 34, 'pPrChange' => 35,
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
     * @return list<array{html: string, alignement: ?string, liste: string}>
     * @throws RuntimeException si le fichier est illisible
     */
    public static function lireRiche(string $chemin, string $nomOrigine): array
    {
        [$doc, , $paragraphes] = self::ouvrir($chemin, $nomOrigine);
        $stylesOdf = self::stylesDeTexteOdf($doc);
        $alignementsOdf = self::stylesDeParagrapheOdf($doc);
        $listesOdf = self::listesOdf($doc);
        $numsWord = self::numsWord($chemin);

        return array_map(
            static fn (DOMElement $p): array => [
                'html' => self::html($p->namespaceURI === self::NS_W
                    ? self::passagesWord($p)
                    : self::passagesOdf($p, $stylesOdf)),
                'alignement' => self::alignement($p, $alignementsOdf),
                'liste' => (string) self::sorteDeListe($p, $numsWord, $listesOdf),
            ],
            $paragraphes
        );
    }

    /**
     * Les paragraphes tels que l'aperçu les compte, mise en forme comprise.
     *
     * L'éditeur ne travaille que sur les paragraphes du corps, et garde les
     * vides — ce sont les lignes blanches du document. L'aperçu, lui, montre
     * aussi ceux des tableaux et laisse les vides de côté. Les deux lectures
     * ne rendent donc pas la même liste, et confondre leurs rangs afficherait
     * un paragraphe à la place d'un autre : celle-ci suit la règle de l'aperçu,
     * pour que les deux listes se correspondent une à une.
     *
     * @return list<array{html: string, alignement: ?string, liste: string}>
     * @throws RuntimeException si le fichier est illisible
     */
    public static function apercuRiche(string $chemin, string $nomOrigine): array
    {
        [$doc, , , $format] = self::ouvrir($chemin, $nomOrigine);
        $stylesOdf = self::stylesDeTexteOdf($doc);
        $alignementsOdf = self::stylesDeParagrapheOdf($doc);
        $listesOdf = self::listesOdf($doc);
        $numsWord = self::numsWord($chemin);

        $rendus = [];
        foreach ($doc->getElementsByTagName('*') as $noeud) {
            if (!self::estParagraphe($noeud, $format)) {
                continue;
            }
            /** @var DOMElement $noeud */
            $passages = self::resserrer($noeud->namespaceURI === self::NS_W
                ? self::passagesWord($noeud)
                : self::passagesOdf($noeud, $stylesOdf));

            if ($passages !== []) {
                $rendus[] = [
                    'html' => self::html($passages),
                    'alignement' => self::alignement($noeud, $alignementsOdf),
                    'liste' => (string) self::sorteDeListe($noeud, $numsWord, $listesOdf),
                ];
            }
        }

        return $rendus;
    }

    /**
     * Ramène les blancs d'un paragraphe à ce que l'aperçu en fait : les suites
     * d'espaces réduites à une, et rien qui dépasse aux deux bouts.
     *
     * @param  list<array> $passages
     * @return list<array>  vide quand il ne reste rien à montrer
     */
    private static function resserrer(array $passages): array
    {
        foreach ($passages as $rang => $passage) {
            $passages[$rang]['texte'] = (string) preg_replace('/\s+/u', ' ', $passage['texte']);
        }
        $passages = array_values(array_filter(
            $passages,
            static fn (array $p): bool => $p['texte'] !== ''
        ));
        if ($passages === []) {
            return [];
        }

        $passages[0]['texte'] = ltrim($passages[0]['texte']);
        $dernier = count($passages) - 1;
        $passages[$dernier]['texte'] = rtrim($passages[$dernier]['texte']);

        return array_values(array_filter(
            $passages,
            static fn (array $p): bool => $p['texte'] !== ''
        ));
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
     * @param array<int, array{origine: ?int, texte: string, alignement?: ?string, liste?: string}> $entrees
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
        $parties = [];

        $word = $doc->documentElement?->namespaceURI === self::NS_W;
        $numsWord = $word ? self::numsWord($chemin) : [];
        $listesOdf = $word ? [] : self::listesOdf($doc);

        /*
         * Word range la définition de ses listes dans une autre partie de
         * l'archive : il faut l'y écrire, et l'annoncer, avant de pouvoir
         * accrocher un paragraphe dessus. On ne le fait que pour les sortes
         * réellement demandées.
         */
        $numeros = [];
        if ($word) {
            foreach (array_keys(self::LISTES) as $sorte) {
                foreach ($entrees as $entree) {
                    if (($entree['liste'] ?? '') === $sorte) {
                        $numeros[$sorte] = self::numerotationWord($chemin, $parties, $sorte);
                        break;
                    }
                }
            }
        }

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
            self::alignerParagraphe($doc, $noeud, $entree['alignement'] ?? null);

            /*
             * La sorte de liste ne se réécrit que si elle change : une
             * numérotation en « a) », une liste à plusieurs niveaux, tout ce
             * que l'éditeur ne sait pas dire garde ainsi sa définition
             * d'origine tant qu'on n'y touche pas.
             */
            $sorte = (string) ($entree['liste'] ?? '');
            $avant = $existant === null
                ? ''
                : (string) self::sorteDeListe($existant, $numsWord, $listesOdf);
            $inchangee = $sorte === $avant;

            if ($word && !$inchangee) {
                self::listerWord($doc, $noeud, $numeros[$sorte] ?? null);
            }

            $nouveaux[] = [
                'noeud' => $noeud,
                'sorte' => $sorte,
                // Le style d'origine, pour remballer à l'identique en ODF.
                'style' => $inchangee && $existant !== null ? self::styleDeSaListe($existant) : null,
            ];
            $modele = $noeud;
        }

        self::replacer($doc, $corps, $paragraphes, $nouveaux, $format);

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Le document n’a pas pu être réécrit.');
        }

        $parties[(string) $format['partie']] = $xml;
        self::ecrire($chemin, $nomOrigine, $parties);
    }

    /* --- Écriture ------------------------------------------------------- */

    /**
     * Remplace une partie de l'archive, sans jamais toucher à l'original tant
     * que la nouvelle version n'a pas été relue sans erreur.
     */
    /** @param array<string, string> $parties  le contenu de chacune, par nom */
    private static function ecrire(string $chemin, string $nomOrigine, array $parties): void
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
            $ajoute = true;
            foreach ($parties as $nom => $xml) {
                $ajoute = $zip->addFromString($nom, $xml) && $ajoute;
            }
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
     * En ODF, une puce n'est pas une marque posée sur le paragraphe mais un
     * emballage autour : les paragraphes à puce qui se suivent forment une
     * liste, les autres restent nus. On reconstruit donc cet emballage à
     * chaque enregistrement, ce qui évite d'avoir à réparer celui d'avant.
     *
     * @param array<int, DOMElement> $anciens
     * @param array<int, array{noeud: DOMElement, puce: bool}> $nouveaux
     */
    private static function replacer(
        DOMDocument $doc,
        DOMElement $corps,
        array $anciens,
        array $nouveaux,
        array $format
    ): void {
        $odf = ($format['corps'][0] ?? null) === self::NS_OFFICE;

        /*
         * L'ordre des trois gestes compte. Le repère se pose d'abord, sur les
         * emplacements d'origine. Les anciens paragraphes sont ensuite retirés,
         * emballages vidés compris — et non après, car les nouveaux sont bien
         * souvent les mêmes noeuds, et on les reprendrait à la liste qu'on
         * vient de leur donner.
         */
        $repere = $doc->createComment(' texte ');
        if ($anciens !== []) {
            $corps->insertBefore($repere, self::enfantDu($corps, $anciens[0]));
        } else {
            $fin = $corps->lastChild;
            if ($fin instanceof DOMElement && $fin->namespaceURI === self::NS_W
                && $fin->localName === 'sectPr') {
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
        foreach (iterator_to_array($corps->childNodes) as $enfant) {
            if ($enfant instanceof DOMElement
                && $enfant->namespaceURI === self::NS_TEXT
                && $enfant->localName === 'list'
                && $enfant->getElementsByTagNameNS(self::NS_TEXT, 'p')->length === 0
            ) {
                $corps->removeChild($enfant);
            }
        }

        $aPoser = [];
        $liste = null;
        $styleEnCours = null;
        $sortesPosees = [];

        foreach ($nouveaux as $entree) {
            if (!$odf || $entree['sorte'] === '') {
                $liste = null;
                $styleEnCours = null;
                $aPoser[] = $entree['noeud'];
                continue;
            }

            // Le style d'origine quand rien n'a changé, le nôtre sinon.
            $style = $entree['style'] ?? self::LISTES[$entree['sorte']]['style'];
            if ($entree['style'] === null) {
                $sortesPosees[$entree['sorte']] = true;
            }

            // Deux listes voisines de styles différents ne se mélangent pas :
            // une numérotation reprendrait à un.
            if ($liste === null || $styleEnCours !== $style) {
                $liste = $doc->createElementNS(self::NS_TEXT, 'text:list');
                $liste->setAttributeNS(self::NS_TEXT, 'text:style-name', $style);
                $styleEnCours = $style;
                $aPoser[] = $liste;
            }
            $item = $doc->createElementNS(self::NS_TEXT, 'text:list-item');
            $item->appendChild($entree['noeud']);
            $liste->appendChild($item);
        }

        foreach (array_keys($sortesPosees) as $sorte) {
            self::styleDeListeOdf($doc, $sorte);
        }

        foreach ($aPoser as $noeud) {
            $corps->insertBefore($noeud, $repere);
        }
        $corps->removeChild($repere);
    }

    /** L'ancêtre de ce noeud qui est enfant direct du corps, ou lui-même. */
    private static function enfantDu(DOMElement $corps, DOMNode $noeud): DOMNode
    {
        while ($noeud->parentNode !== null && $noeud->parentNode !== $corps) {
            $noeud = $noeud->parentNode;
        }

        return $noeud;
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
        self::recueillir($corps, $format, $paragraphes);

        return [$doc, $corps, $paragraphes, $format];
    }

    /**
     * Les paragraphes du corps, dans l'ordre, emballages de liste compris.
     *
     * On ne descend que dans les listes : un paragraphe de tableau appartient
     * à sa cellule, et le sortir de là défigurerait le document.
     *
     * @param array<int, DOMElement> $paragraphes
     */
    private static function recueillir(DOMNode $parent, array $format, array &$paragraphes): void
    {
        foreach ($parent->childNodes as $enfant) {
            if (self::estParagraphe($enfant, $format)) {
                $paragraphes[] = $enfant;
                continue;
            }
            if ($enfant instanceof DOMElement
                && $enfant->namespaceURI === self::NS_TEXT
                && in_array($enfant->localName, ['list', 'list-item', 'list-header'], true)
            ) {
                self::recueillir($enfant, $format, $paragraphes);
            }
        }
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
            if ($passage['fond'] !== null) {
                $morceau = '<span data-fond="' . $passage['fond']
                    . '" style="background-color:#' . $passage['fond'] . '">' . $morceau . '</span>';
            }
            if ($passage['couleur'] !== null) {
                $morceau = '<span data-couleur="' . $passage['couleur']
                    . '" style="color:#' . $passage['couleur'] . '">' . $morceau . '</span>';
            }
            if ($passage['taille'] !== null) {
                // Le style accompagne l'attribut : c'est lui qui fait voir la
                // taille, dans l'éditeur comme dans l'aperçu. À la relecture,
                // c'est l'attribut qui fait foi.
                $morceau = '<span data-taille="' . $passage['taille']
                    . '" style="font-size:' . $passage['taille'] . 'pt">' . $morceau . '</span>';
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
                'souligne' => false, 'taille' => null, 'couleur' => null,
                'fond' => null], $passages);
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
            // « auto » veut dire « celle du document » : c'est un choix, pas
            // une absence, et il efface donc la couleur d'un cadre englobant.
            $couleur = self::couleurDemandee($enfant);
            if ($couleur === 'auto') {
                $sien['couleur'] = null;
            } elseif ($couleur !== null) {
                $sien['couleur'] = $couleur;
            }
            $fond = self::fondDemande($enfant);
            if ($fond === 'auto') {
                $sien['fond'] = null;
            } elseif ($fond !== null) {
                $sien['fond'] = $fond;
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

    /**
     * La couleur écrite sur cette balise.
     *
     * @return ?string  six chiffres hexadécimaux, « auto », ou null
     */
    private static function couleurDemandee(DOMElement $element): ?string
    {
        return self::hexa(
            $element->getAttribute('data-couleur'),
            $element->getAttribute('style'),
            '/(?<![-\w])color\s*:\s*([^;]+)/i'
        );
    }

    /**
     * Le fond écrit sur cette balise.
     *
     * @return ?string  six chiffres hexadécimaux, « auto », ou null
     */
    private static function fondDemande(DOMElement $element): ?string
    {
        return self::hexa(
            $element->getAttribute('data-fond'),
            $element->getAttribute('style'),
            '/background(?:-color)?\s*:\s*([^;]+)/i'
        );
    }

    /**
     * Une couleur, lue d'abord dans notre attribut puis dans le style CSS.
     *
     * @return ?string  six chiffres hexadécimaux, « auto », ou null
     */
    private static function hexa(string $attribut, string $style, string $motif): ?string
    {
        $brut = trim($attribut);

        if ($brut === '' && preg_match($motif, $style, $m) === 1) {
            $brut = trim($m[1]);
        }
        if ($brut === '') {
            return null;
        }
        // « auto » est le mot que l'éditeur envoie pour dire « celle du
        // document » ; les deux autres sont ceux qu'un navigateur écrit.
        if (in_array(strtolower($brut), ['auto', 'inherit', 'transparent'], true)) {
            return 'auto';
        }

        // Un navigateur écrit volontiers « rgb(220, 38, 38) ».
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $brut, $m) === 1) {
            return strtoupper(sprintf('%02X%02X%02X',
                min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3])));
        }

        $hexa = ltrim($brut, '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $hexa) === 1) {
            $hexa = $hexa[0] . $hexa[0] . $hexa[1] . $hexa[1] . $hexa[2] . $hexa[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $hexa) === 1 ? strtoupper($hexa) : null;
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
                && $fondus[$dernier]['couleur'] === $passage['couleur']
                && $fondus[$dernier]['fond'] === $passage['fond']
            ) {
                $fondus[$dernier]['texte'] .= $passage['texte'];
                continue;
            }
            $fondus[] = ['texte' => $passage['texte'], 'gras' => $passage['gras'],
                'italique' => $passage['italique'], 'souligne' => $passage['souligne'],
                'taille' => $passage['taille'], 'couleur' => $passage['couleur'],
                'fond' => $passage['fond']];
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
                'couleur'  => self::couleurWord($rPr),
                'fond'     => self::fondWord($rPr),
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

    /** La couleur du passage. « auto » veut dire celle du style, donc aucune. */
    private static function couleurWord(?DOMElement $rPr): ?string
    {
        $color = self::enfantWord($rPr, 'color');
        if ($color === null) {
            return null;
        }
        $val = strtoupper(ltrim($color->getAttributeNS(self::NS_W, 'val'), '#'));

        return preg_match('/^[0-9A-F]{6}$/', $val) === 1 ? $val : null;
    }

    /**
     * Le fond du passage : un surlignage nommé, ou une trame de fond.
     *
     * Word écrit l'un ou l'autre selon l'outil employé ; l'éditeur les montre
     * de la même façon.
     */
    private static function fondWord(?DOMElement $rPr): ?string
    {
        $surlignage = self::enfantWord($rPr, 'highlight');
        if ($surlignage !== null) {
            $nom = $surlignage->getAttributeNS(self::NS_W, 'val');
            $hexa = array_search($nom, self::SURLIGNAGES, true);
            if ($hexa !== false) {
                return $hexa;
            }
        }

        $trame = self::enfantWord($rPr, 'shd');
        if ($trame !== null) {
            $fill = strtoupper(ltrim($trame->getAttributeNS(self::NS_W, 'fill'), '#'));
            if (preg_match('/^[0-9A-F]{6}$/', $fill) === 1) {
                return $fill;
            }
        }

        return null;
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
        return $passage['gras'] || $passage['italique'] || $passage['souligne']
            || $passage['taille'] !== null || $passage['couleur'] !== null
            || $passage['fond'] !== null;
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
        foreach (['b', 'bCs', 'i', 'iCs', 'u', 'sz', 'szCs', 'color', 'highlight', 'shd'] as $nom) {
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
        if ($passage['couleur'] !== null) {
            self::poserDansRpr($doc, $rPr, 'color', $passage['couleur']);
        }
        if ($passage['fond'] !== null) {
            $nomme = self::SURLIGNAGES[$passage['fond']] ?? null;
            if ($nomme !== null) {
                self::poserDansRpr($doc, $rPr, 'highlight', $nomme);
            } else {
                // Le surligneur de Word ne connaît que ses seize couleurs : pour
                // les autres, une trame de fond, qui accepte tout.
                $trame = $doc->createElementNS(self::NS_W, 'w:shd');
                $trame->setAttributeNS(self::NS_W, 'w:val', 'clear');
                $trame->setAttributeNS(self::NS_W, 'w:color', 'auto');
                $trame->setAttributeNS(self::NS_W, 'w:fill', $passage['fond']);
                self::ranger($rPr, $trame, self::ORDRE_RPR);
            }
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
        self::ranger($rPr, $element, self::ORDRE_RPR);
    }

    /**
     * Glisse un élément déjà bâti à la place que le schéma lui réserve.
     *
     * @param array<string, int> $ordre  la suite imposée aux enfants du parent
     */
    private static function ranger(DOMElement $parent, DOMElement $element, array $ordre): void
    {
        $rang = $ordre[$element->localName] ?? PHP_INT_MAX;
        foreach ($parent->childNodes as $enfant) {
            if (!$enfant instanceof DOMElement) {
                continue;
            }
            $sien = $ordre[$enfant->localName] ?? PHP_INT_MAX;
            if ($sien > $rang) {
                $parent->insertBefore($element, $enfant);
                return;
            }
        }
        $parent->appendChild($element);
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

            $couleur = strtoupper(ltrim($proprietes->getAttributeNS(self::NS_FO, 'color'), '#'));
            $fond = strtoupper(ltrim($proprietes->getAttributeNS(self::NS_FO, 'background-color'), '#'));
            $souligne = $proprietes->getAttributeNS(self::NS_STYLE, 'text-underline-style');
            $styles[$nom] = [
                'gras'     => $proprietes->getAttributeNS(self::NS_FO, 'font-weight') === 'bold',
                'italique' => $proprietes->getAttributeNS(self::NS_FO, 'font-style') === 'italic',
                'souligne' => $souligne !== '' && $souligne !== 'none',
                'taille'   => $taille,
                'couleur'  => preg_match('/^[0-9A-F]{6}$/', $couleur) === 1 ? $couleur : null,
                'fond'     => preg_match('/^[0-9A-F]{6}$/', $fond) === 1 ? $fond : null,
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
            ['gras' => false, 'italique' => false, 'souligne' => false,
             'taille' => null, 'couleur' => null, 'fond' => null],
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
                        'couleur'  => $styles[$nom]['couleur'] ?? $etat['couleur'],
                        'fond'     => $styles[$nom]['fond'] ?? $etat['fond'],
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
        $nom = sprintf('MesCours_%s%s%s%s%s%s',
            $passage['gras'] ? 'g' : '',
            $passage['italique'] ? 'i' : '',
            $passage['souligne'] ? 's' : '',
            $passage['taille'] !== null ? 't' . $passage['taille'] : '',
            $passage['couleur'] !== null ? 'c' . $passage['couleur'] : '',
            $passage['fond'] !== null ? 'f' . $passage['fond'] : '');

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
        if ($passage['couleur'] !== null) {
            $proprietes->setAttributeNS(self::NS_FO, 'fo:color', '#' . $passage['couleur']);
        }
        if ($passage['fond'] !== null) {
            $proprietes->setAttributeNS(self::NS_FO, 'fo:background-color', '#' . $passage['fond']);
        }

        $style->appendChild($proprietes);
        $automatiques->appendChild($style);

        return $nom;
    }

    /* --- L'alignement d'un paragraphe ------------------------------------ */

    /**
     * Comment ce paragraphe est aligné.
     *
     * @param array<string, string> $stylesOdf  les styles de paragraphe, par nom
     * @return ?string  null quand le document n'en dit rien
     */
    private static function alignement(DOMElement $paragraphe, array $stylesOdf): ?string
    {
        if ($paragraphe->namespaceURI === self::NS_W) {
            $pPr = self::enfantWord($paragraphe, 'pPr');
            $jc = self::enfantWord($pPr, 'jc');
            if ($jc === null) {
                return null;
            }
            $val = $jc->getAttributeNS(self::NS_W, 'val');
            foreach (self::ALIGNEMENTS as $nom => $formats) {
                // « start » et « end » sont les noms récents de « left » et « right ».
                if ($val === $formats['both']
                    || ($val === 'start' && $nom === 'gauche')
                    || ($val === 'end' && $nom === 'droite')) {
                    return $nom;
                }
            }

            return null;
        }

        $nom = $paragraphe->getAttributeNS(self::NS_TEXT, 'style-name');

        return $stylesOdf[$nom] ?? null;
    }

    /**
     * Les styles de paragraphe d'un document ODF, réduits à leur alignement.
     *
     * @return array<string, string>
     */
    private static function stylesDeParagrapheOdf(DOMDocument $doc): array
    {
        $styles = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_STYLE, 'style') as $style) {
            if ($style->getAttributeNS(self::NS_STYLE, 'family') !== 'paragraph') {
                continue;
            }
            $nom = $style->getAttributeNS(self::NS_STYLE, 'name');
            $proprietes = $style->getElementsByTagNameNS(self::NS_STYLE, 'paragraph-properties')->item(0);
            if ($nom === '' || !$proprietes instanceof DOMElement) {
                continue;
            }

            $aligne = $proprietes->getAttributeNS(self::NS_FO, 'text-align');
            foreach (self::ALIGNEMENTS as $cle => $formats) {
                if ($aligne === $formats['odf']
                    || ($aligne === 'left' && $cle === 'gauche')
                    || ($aligne === 'right' && $cle === 'droite')) {
                    $styles[$nom] = $cle;
                    break;
                }
            }
        }

        return $styles;
    }

    /**
     * Pose l'alignement demandé sur un paragraphe.
     *
     * Rien n'est écrit quand l'éditeur n'en dit rien : c'est le cas des envois
     * sans JavaScript, et le paragraphe garde alors l'alignement qu'il avait.
     */
    private static function alignerParagraphe(
        DOMDocument $doc,
        DOMElement $paragraphe,
        ?string $alignement
    ): void {
        if ($alignement === null || !isset(self::ALIGNEMENTS[$alignement])) {
            return;
        }

        if ($paragraphe->namespaceURI === self::NS_W) {
            self::alignerWord($doc, $paragraphe, $alignement);
            return;
        }
        self::alignerOdf($doc, $paragraphe, $alignement);
    }

    private static function alignerWord(
        DOMDocument $doc,
        DOMElement $paragraphe,
        string $alignement
    ): void {
        $pPr = self::enfantWord($paragraphe, 'pPr');
        if ($pPr === null) {
            // <w:pPr> ouvre toujours le paragraphe : le schéma l'exige avant
            // le moindre passage.
            $pPr = $doc->createElementNS(self::NS_W, 'w:pPr');
            $paragraphe->insertBefore($pPr, $paragraphe->firstChild);
        }

        $ancien = self::enfantWord($pPr, 'jc');
        if ($ancien !== null) {
            $pPr->removeChild($ancien);
        }

        $jc = $doc->createElementNS(self::NS_W, 'w:jc');
        $jc->setAttributeNS(self::NS_W, 'w:val', self::ALIGNEMENTS[$alignement]['both']);
        self::ranger($pPr, $jc, self::ORDRE_PPR);
    }

    /**
     * Aligne un paragraphe ODF, sans lui faire perdre son style.
     *
     * Le style qu'il porte décrit aussi ses marges, son interligne, sa police :
     * on ne le remplace pas, on en fabrique une variante qui ajoute
     * l'alignement — copie du style automatique s'il en avait un, sinon
     * héritage du style commun qu'il nommait.
     */
    private static function alignerOdf(
        DOMDocument $doc,
        DOMElement $paragraphe,
        string $alignement
    ): void {
        $ancien = $paragraphe->getAttributeNS(self::NS_TEXT, 'style-name');
        $nom = 'MesCoursP_' . $alignement . ($ancien === '' ? '' : '_' . $ancien);

        $existe = false;
        foreach ($doc->getElementsByTagNameNS(self::NS_STYLE, 'style') as $style) {
            if ($style->getAttributeNS(self::NS_STYLE, 'name') === $nom) {
                $existe = true;
                break;
            }
        }

        if (!$existe) {
            $automatiques = self::automatiquesOdf($doc);
            if ($automatiques === null) {
                return;
            }

            $source = null;
            foreach ($automatiques->getElementsByTagNameNS(self::NS_STYLE, 'style') as $style) {
                if ($style->getAttributeNS(self::NS_STYLE, 'name') === $ancien
                    && $style->getAttributeNS(self::NS_STYLE, 'family') === 'paragraph') {
                    $source = $style;
                    break;
                }
            }

            if ($source !== null) {
                /** @var DOMElement $nouveau */
                $nouveau = $source->cloneNode(true);
            } else {
                $nouveau = $doc->createElementNS(self::NS_STYLE, 'style:style');
                $nouveau->setAttributeNS(self::NS_STYLE, 'style:family', 'paragraph');
                if ($ancien !== '') {
                    $nouveau->setAttributeNS(self::NS_STYLE, 'style:parent-style-name', $ancien);
                }
            }
            $nouveau->setAttributeNS(self::NS_STYLE, 'style:name', $nom);

            $proprietes = $nouveau->getElementsByTagNameNS(self::NS_STYLE, 'paragraph-properties')->item(0);
            if (!$proprietes instanceof DOMElement) {
                $proprietes = $doc->createElementNS(self::NS_STYLE, 'style:paragraph-properties');
                $nouveau->insertBefore($proprietes, $nouveau->firstChild);
            }
            $proprietes->setAttributeNS(self::NS_FO, 'fo:text-align',
                self::ALIGNEMENTS[$alignement]['odf']);
            // Sans cela, LibreOffice aligne le texte mais laisse la dernière
            // ligne d'un paragraphe justifié là où elle était.
            $proprietes->setAttributeNS(self::NS_STYLE, 'style:justify-single-word', 'false');

            $automatiques->appendChild($nouveau);
        }

        $paragraphe->setAttributeNS(self::NS_TEXT, 'text:style-name', $nom);
    }

    /** Le bloc des styles automatiques, créé au besoin. */
    private static function automatiquesOdf(DOMDocument $doc): ?DOMElement
    {
        $automatiques = $doc->getElementsByTagNameNS(self::NS_OFFICE, 'automatic-styles')->item(0);
        if ($automatiques instanceof DOMElement) {
            return $automatiques;
        }

        $automatiques = $doc->createElementNS(self::NS_OFFICE, 'office:automatic-styles');
        $corps = $doc->getElementsByTagNameNS(self::NS_OFFICE, 'body')->item(0);
        if ($corps instanceof DOMElement && $corps->parentNode !== null) {
            $corps->parentNode->insertBefore($automatiques, $corps);
            return $automatiques;
        }
        if ($doc->documentElement !== null) {
            $doc->documentElement->appendChild($automatiques);
            return $automatiques;
        }

        return null;
    }

    /* --- Les puces -------------------------------------------------------- */

    /**
     * Ce paragraphe est-il une puce ?
     *
    /**
     * De quelle liste ce paragraphe fait partie : « puce », « numero », ou
     * rien du tout.
     */
    private static function sorteDeListe(DOMElement $paragraphe, array $numsWord, array $listesOdf): ?string
    {
        if ($paragraphe->namespaceURI === self::NS_W) {
            $pPr = self::enfantWord($paragraphe, 'pPr');
            $numPr = self::enfantWord($pPr, 'numPr');
            $numId = self::enfantWord($numPr, 'numId');
            if ($numId === null) {
                return null;
            }
            $numero = $numId->getAttributeNS(self::NS_W, 'val');

            // Un renvoi vers une liste que le document ne définit pas : mieux
            // vaut le tenir pour une numérotation et n'y pas toucher.
            return $numsWord[$numero] ?? 'numero';
        }

        $liste = $paragraphe->parentNode;
        while ($liste instanceof DOMElement) {
            if ($liste->namespaceURI === self::NS_TEXT && $liste->localName === 'list') {
                $nom = $liste->getAttributeNS(self::NS_TEXT, 'style-name');

                return $listesOdf[$nom] ?? 'numero';
            }
            $liste = $liste->parentNode;
        }

        return null;
    }

    /**
     * Les listes d'un document Word, par numéro : « puce » ou « numero ».
     *
     * @return array<string, string>
     */
    private static function numsWord(string $chemin): array
    {
        $xml = self::partie($chemin, self::PART_NUM);
        if ($xml === null) {
            return [];
        }
        $doc = self::analyser($xml);
        if ($doc === null) {
            return [];
        }

        $abstraits = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_W, 'abstractNum') as $abstrait) {
            $id = $abstrait->getAttributeNS(self::NS_W, 'abstractNumId');
            foreach ($abstrait->getElementsByTagNameNS(self::NS_W, 'lvl') as $niveau) {
                if ($niveau->getAttributeNS(self::NS_W, 'ilvl') !== '0') {
                    continue;
                }
                $format = self::enfantWord($niveau, 'numFmt');
                $abstraits[$id] = $format !== null
                    && $format->getAttributeNS(self::NS_W, 'val') === 'bullet'
                        ? 'puce' : 'numero';
                break;
            }
        }

        $nums = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_W, 'num') as $num) {
            $renvoi = self::enfantWord($num, 'abstractNumId');
            if ($renvoi === null) {
                continue;
            }
            $id = $renvoi->getAttributeNS(self::NS_W, 'val');
            $nums[$num->getAttributeNS(self::NS_W, 'numId')] = $abstraits[$id] ?? 'numero';
        }

        return $nums;
    }

    /**
     * Les styles de liste d'un document ODF : « puce » ou « numero ».
     *
     * @return array<string, string>
     */
    private static function listesOdf(DOMDocument $doc): array
    {
        $listes = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_TEXT, 'list-style') as $style) {
            $nom = $style->getAttributeNS(self::NS_STYLE, 'name');
            if ($nom === '') {
                continue;
            }
            $listes[$nom] = $style->getElementsByTagNameNS(self::NS_TEXT, 'list-level-style-bullet')->length > 0
                || $style->getElementsByTagNameNS(self::NS_TEXT, 'list-level-style-image')->length > 0
                    ? 'puce' : 'numero';
        }

        return $listes;
    }

    /** Le style de la liste ODF qui enveloppe ce paragraphe, s'il y en a une. */
    private static function styleDeSaListe(DOMElement $paragraphe): ?string
    {
        $liste = $paragraphe->parentNode;
        while ($liste instanceof DOMElement) {
            if ($liste->namespaceURI === self::NS_TEXT && $liste->localName === 'list') {
                $nom = $liste->getAttributeNS(self::NS_TEXT, 'style-name');

                return $nom === '' ? null : $nom;
            }
            $liste = $liste->parentNode;
        }

        return null;
    }

    /** Accroche ou décroche un paragraphe Word d'une de nos listes. */
    private static function listerWord(DOMDocument $doc, DOMElement $paragraphe, ?int $numero): void
    {
        $pPr = self::enfantWord($paragraphe, 'pPr');

        if ($numero === null) {
            $numPr = self::enfantWord($pPr, 'numPr');
            if ($pPr !== null && $numPr !== null) {
                $pPr->removeChild($numPr);
            }
            return;
        }

        if ($pPr === null) {
            $pPr = $doc->createElementNS(self::NS_W, 'w:pPr');
            $paragraphe->insertBefore($pPr, $paragraphe->firstChild);
        }
        $ancien = self::enfantWord($pPr, 'numPr');
        if ($ancien !== null) {
            $pPr->removeChild($ancien);
        }

        $numPr = $doc->createElementNS(self::NS_W, 'w:numPr');
        $ilvl = $doc->createElementNS(self::NS_W, 'w:ilvl');
        $ilvl->setAttributeNS(self::NS_W, 'w:val', '0');
        $numId = $doc->createElementNS(self::NS_W, 'w:numId');
        $numId->setAttributeNS(self::NS_W, 'w:val', (string) $numero);
        $numPr->appendChild($ilvl);
        $numPr->appendChild($numId);

        self::ranger($pPr, $numPr, self::ORDRE_PPR);
    }

    /**
     * Le numéro de notre liste à puces dans le document Word, créée au besoin.
     *
     * Word garde ses listes dans une partie à part, qu'il faut aussi déclarer
     * dans les types du paquet et dans les liens du document. Les trois sont
     * préparées ici, et écrites en même temps que le texte.
     *
     * @param array<string, string> $parties  ce qu'il faudra écrire, complété ici
     */
    private static function numerotationWord(string $chemin, array &$parties, string $sorte): ?int
    {
        $reglage = self::LISTES[$sorte] ?? null;
        if ($reglage === null) {
            return null;
        }
        // La partie déjà réécrite au tour précédent, s'il y en a eu un : sans
        // cela, la deuxième sorte effacerait la première.
        $xml = $parties[self::PART_NUM] ?? self::partie($chemin, self::PART_NUM);
        $doc = $xml === null ? null : self::analyser($xml);

        if ($doc === null) {
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->createElementNS(self::NS_W, 'w:numbering'));
        }
        $racine = $doc->documentElement;
        if ($racine === null) {
            return null;
        }

        // Déjà posée lors d'un enregistrement précédent : on la retrouve.
        foreach ($racine->getElementsByTagNameNS(self::NS_W, 'abstractNum') as $abstrait) {
            $nom = self::enfantWord($abstrait, 'name');
            if ($nom === null || $nom->getAttributeNS(self::NS_W, 'val') !== $reglage['nom']) {
                continue;
            }
            $id = $abstrait->getAttributeNS(self::NS_W, 'abstractNumId');
            foreach ($racine->getElementsByTagNameNS(self::NS_W, 'num') as $num) {
                $renvoi = self::enfantWord($num, 'abstractNumId');
                if ($renvoi !== null && $renvoi->getAttributeNS(self::NS_W, 'val') === $id) {
                    return (int) $num->getAttributeNS(self::NS_W, 'numId');
                }
            }
        }

        // Des numéros que le document n'emploie pas encore.
        $libre = static function (DOMElement $racine, string $balise, string $attribut): int {
            $pris = [];
            foreach ($racine->getElementsByTagNameNS(self::NS_W, $balise) as $element) {
                $pris[] = (int) $element->getAttributeNS(self::NS_W, $attribut);
            }
            return $pris === [] ? 1 : max($pris) + 1;
        };
        $abstraitId = $libre($racine, 'abstractNum', 'abstractNumId');
        $numeroId = $libre($racine, 'num', 'numId');

        $abstrait = $doc->createElementNS(self::NS_W, 'w:abstractNum');
        $abstrait->setAttributeNS(self::NS_W, 'w:abstractNumId', (string) $abstraitId);

        $type = $doc->createElementNS(self::NS_W, 'w:multiLevelType');
        $type->setAttributeNS(self::NS_W, 'w:val', 'hybridMultilevel');
        $abstrait->appendChild($type);

        $nom = $doc->createElementNS(self::NS_W, 'w:name');
        $nom->setAttributeNS(self::NS_W, 'w:val', $reglage['nom']);
        $abstrait->appendChild($nom);

        $niveau = $doc->createElementNS(self::NS_W, 'w:lvl');
        $niveau->setAttributeNS(self::NS_W, 'w:ilvl', '0');
        foreach ([
            'start'   => '1',
            'numFmt'  => $sorte === 'puce' ? 'bullet' : 'decimal',
            // Le rond plein de la police Symbol pour les puces ; « %1. », soit
            // le numéro du premier niveau suivi d'un point, pour les autres.
            'lvlText' => $sorte === 'puce' ? "\u{F0B7}" : '%1.',
            'lvlJc'   => 'left',
        ] as $balise => $valeur) {
            $element = $doc->createElementNS(self::NS_W, 'w:' . $balise);
            $element->setAttributeNS(self::NS_W, 'w:val', $valeur);
            $niveau->appendChild($element);
        }

        $pPr = $doc->createElementNS(self::NS_W, 'w:pPr');
        $ind = $doc->createElementNS(self::NS_W, 'w:ind');
        $ind->setAttributeNS(self::NS_W, 'w:left', '720');
        $ind->setAttributeNS(self::NS_W, 'w:hanging', '360');
        $pPr->appendChild($ind);
        $niveau->appendChild($pPr);

        if ($sorte === 'puce') {
            // Le rond plein n'existe que dans la police Symbol.
            $rPr = $doc->createElementNS(self::NS_W, 'w:rPr');
            $polices = $doc->createElementNS(self::NS_W, 'w:rFonts');
            $polices->setAttributeNS(self::NS_W, 'w:ascii', 'Symbol');
            $polices->setAttributeNS(self::NS_W, 'w:hAnsi', 'Symbol');
            $polices->setAttributeNS(self::NS_W, 'w:hint', 'default');
            $rPr->appendChild($polices);
            $niveau->appendChild($rPr);
        }

        $abstrait->appendChild($niveau);

        $num = $doc->createElementNS(self::NS_W, 'w:num');
        $num->setAttributeNS(self::NS_W, 'w:numId', (string) $numeroId);
        $renvoi = $doc->createElementNS(self::NS_W, 'w:abstractNumId');
        $renvoi->setAttributeNS(self::NS_W, 'w:val', (string) $abstraitId);
        $num->appendChild($renvoi);

        // Le schéma veut tous les <w:abstractNum> avant tous les <w:num>.
        $premierNum = $racine->getElementsByTagNameNS(self::NS_W, 'num')->item(0);
        if ($premierNum instanceof DOMElement) {
            $racine->insertBefore($abstrait, $premierNum);
        } else {
            $racine->appendChild($abstrait);
        }
        $racine->appendChild($num);

        $ecrit = $doc->saveXML();
        if ($ecrit === false) {
            return null;
        }
        $parties[self::PART_NUM] = $ecrit;

        self::declarerNumerotation($chemin, $parties);

        return $numeroId;
    }

    /**
     * Annonce la partie des listes : son type dans le paquet, son lien dans le
     * document. Sans ces deux lignes, Word tient l'archive pour abîmée.
     *
     * @param array<string, string> $parties  complété au besoin
     */
    private static function declarerNumerotation(string $chemin, array &$parties): void
    {
        $types = self::partie($chemin, self::PART_TYPES);
        $doc = $types === null ? null : self::analyser($types);
        if ($doc !== null && $doc->documentElement !== null) {
            $deja = false;
            foreach ($doc->getElementsByTagNameNS(self::NS_TYPES, 'Override') as $entree) {
                if ($entree->getAttribute('PartName') === '/' . self::PART_NUM) {
                    $deja = true;
                    break;
                }
            }
            if (!$deja) {
                $entree = $doc->createElementNS(self::NS_TYPES, 'Override');
                $entree->setAttribute('PartName', '/' . self::PART_NUM);
                $entree->setAttribute('ContentType', self::TYPE_NUM);
                $doc->documentElement->appendChild($entree);
                $ecrit = $doc->saveXML();
                if ($ecrit !== false) {
                    $parties[self::PART_TYPES] = $ecrit;
                }
            }
        }

        $rels = self::partie($chemin, self::PART_RELS);
        $doc = $rels === null ? null : self::analyser($rels);
        if ($doc === null || $doc->documentElement === null) {
            return;
        }
        $identifiants = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_RELS, 'Relationship') as $lien) {
            if ($lien->getAttribute('Type') === self::REL_NUM) {
                return;
            }
            $identifiants[] = (int) ltrim($lien->getAttribute('Id'), 'rId');
        }

        $lien = $doc->createElementNS(self::NS_RELS, 'Relationship');
        $lien->setAttribute('Id', 'rId' . ($identifiants === [] ? 1 : max($identifiants) + 1));
        $lien->setAttribute('Type', self::REL_NUM);
        $lien->setAttribute('Target', 'numbering.xml');
        $doc->documentElement->appendChild($lien);

        $ecrit = $doc->saveXML();
        if ($ecrit !== false) {
            $parties[self::PART_RELS] = $ecrit;
        }
    }

    /** Le style de liste que l'application pose sur les siennes, en ODF. */
    private static function styleDeListeOdf(DOMDocument $doc, string $sorte): void
    {
        $reglage = self::LISTES[$sorte] ?? null;
        if ($reglage === null) {
            return;
        }
        foreach ($doc->getElementsByTagNameNS(self::NS_TEXT, 'list-style') as $style) {
            if ($style->getAttributeNS(self::NS_STYLE, 'name') === $reglage['style']) {
                return;
            }
        }
        $automatiques = self::automatiquesOdf($doc);
        if ($automatiques === null) {
            return;
        }

        $style = $doc->createElementNS(self::NS_TEXT, 'text:list-style');
        $style->setAttributeNS(self::NS_STYLE, 'style:name', $reglage['style']);

        if ($sorte === 'puce') {
            $niveau = $doc->createElementNS(self::NS_TEXT, 'text:list-level-style-bullet');
            $niveau->setAttributeNS(self::NS_TEXT, 'text:level', '1');
            $niveau->setAttributeNS(self::NS_TEXT, 'text:bullet-char', '•');
        } else {
            $niveau = $doc->createElementNS(self::NS_TEXT, 'text:list-level-style-number');
            $niveau->setAttributeNS(self::NS_TEXT, 'text:level', '1');
            $niveau->setAttributeNS(self::NS_STYLE, 'style:num-format', '1');
            $niveau->setAttributeNS(self::NS_STYLE, 'style:num-suffix', '.');
            $niveau->setAttributeNS(self::NS_TEXT, 'text:start-value', '1');
        }

        $proprietes = $doc->createElementNS(self::NS_STYLE, 'style:list-level-properties');
        $proprietes->setAttributeNS(self::NS_TEXT, 'text:space-before', '0.25in');
        $proprietes->setAttributeNS(self::NS_TEXT, 'text:min-label-width', '0.25in');
        $niveau->appendChild($proprietes);

        $style->appendChild($niveau);
        $automatiques->appendChild($style);
    }

    /** Le contenu d'une partie de l'archive, ou null si elle n'y est pas. */
    private static function partie(string $chemin, string $nom): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $xml = $zip->getFromName($nom);
        $zip->close();

        return $xml === false ? null : $xml;
    }

    /** Un XML relu sans jamais aller sur le réseau, ou null s'il est abîmé. */
    private static function analyser(string $xml): ?DOMDocument
    {
        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        return $ok ? $doc : null;
    }
}
