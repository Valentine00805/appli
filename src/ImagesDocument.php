<?php
declare(strict_types=1);

/**
 * Les images d'un document bureautique.
 *
 * Un .docx est une archive : le texte dans « word/document.xml », les images
 * rangées à côté dans « word/media », et entre les deux un fichier de
 * relations qui dit quelle image porte quel numéro. L'aperçu ne lisait que le
 * texte : un document arrivait donc amputé de ses schémas et de ses captures,
 * sans que rien ne le signale.
 *
 * Rien n'est recopié sur le disque. L'image est relue de l'archive à chaque
 * demande, et l'adresse ne porte qu'un rang dans la liste des images du
 * document — jamais un chemin. Un rang ne peut donc pas désigner un fichier
 * choisi par qui appelle, ce qui serait une porte ouverte sur l'archive
 * entière, et au-delà.
 */
final class ImagesDocument
{
    private const NS_A     = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_R     = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_WP    = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const NS_V     = 'urn:schemas-microsoft-com:vml';
    private const NS_DRAW  = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    private const NS_XLINK = 'http://www.w3.org/1999/xlink';
    private const NS_RELS  = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Où lire le texte, et où lire les relations, selon l'extension. */
    private const PARTIES = [
        'docx' => ['word/document.xml', 'word/_rels/document.xml.rels'],
        'odt'  => ['content.xml',       null],
    ];

    /** Ce qu'un navigateur affiche sans rien installer. */
    private const TYPES = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
    ];

    /**
     * 914 400 unités Word par pouce, 96 pixels par pouce à l'écran : une image
     * garde donc à peu près la taille que le document lui donne.
     */
    private const EMU_PAR_PIXEL = 9525;

    /** Au-delà, la largeur annoncée ne veut plus rien dire : la page décide. */
    private const LARGEUR_MAX = 2000;

    /** Ce format range-t-il ses images là où l'on sait les chercher ? */
    public static function possible(string $nomOrigine): bool
    {
        return isset(self::PARTIES[self::extension($nomOrigine)]);
    }

    /**
     * Les images du document, dans l'ordre où on les rencontre.
     *
     * L'ordre fait foi : c'est lui qui donne à chaque image le rang par lequel
     * l'adresse la demandera. Il est le même ici et à « du() », puisque les
     * deux parcourent le même XML de la même façon.
     *
     * @param  array<string, string> $relations  de « relations() »
     * @return list<array{source: string, type: ?string, alt: string,
     *                    largeur: ?int, hauteur: ?int, noeud: ?DOMElement}>
     */
    public static function trouver(DOMDocument $doc, array $relations): array
    {
        $images = [];

        foreach ($doc->getElementsByTagName('*') as $noeud) {
            if (!$noeud instanceof DOMElement) {
                continue;
            }
            $source = self::sourceDe($noeud, $relations);
            if ($source === null) {
                continue;
            }
            [$largeur, $hauteur] = self::taille($noeud);
            $images[] = [
                'source'  => $source,
                'type'    => self::TYPES[self::extension($source)] ?? null,
                'alt'     => self::description($noeud),
                'largeur' => $largeur,
                'hauteur' => $hauteur,
                'noeud'   => $noeud,
            ];
        }

        return $images;
    }

    /**
     * Les cibles des relations d'un .docx : « rId7 » vers « word/media/x.png ».
     *
     * Une image liée — restée sur le disque de qui a écrit le document — n'y
     * figure pas : le fichier ne la contient pas, et nous n'irons pas la
     * chercher là où elle est.
     *
     * @return array<string, string>
     */
    public static function relations(string $chemin, string $nomOrigine): array
    {
        $ext = self::extension($nomOrigine);
        if (!isset(self::PARTIES[$ext]) || self::PARTIES[$ext][1] === null) {
            return [];
        }
        $xml = self::partie($chemin, (string) self::PARTIES[$ext][1]);
        $doc = $xml === null ? null : self::analyser($xml);
        if ($doc === null) {
            return [];
        }

        $dossier = dirname((string) self::PARTIES[$ext][0]);   // « word »
        $cibles = [];

        foreach ($doc->getElementsByTagNameNS(self::NS_RELS, 'Relationship') as $relation) {
            $cible = trim($relation->getAttribute('Target'));
            $id    = trim($relation->getAttribute('Id'));
            if ($id === '') {
                continue;
            }
            /*
             * Une image liée garde son numéro, mais sans chemin : le document
             * ne la contient pas. On la retient tout de même, pour que
             * l'aperçu puisse dire qu'elle manque plutôt que de laisser un
             * trou muet à sa place.
             */
            if ($cible === ''
                || strtolower($relation->getAttribute('TargetMode')) === 'external'
                || preg_match('#^[a-z]+://#i', $cible) === 1) {
                $cibles[$id] = '';
                continue;
            }
            $cibles[$id] = self::ranger($dossier, $cible);
        }

        return $cibles;
    }

    /**
     * La même liste, en repartant du fichier : ce que sert l'adresse.
     *
     * @return list<array{source: string, type: ?string, alt: string,
     *                    largeur: ?int, hauteur: ?int}>
     */
    public static function du(string $chemin, string $nomOrigine): array
    {
        $ext = self::extension($nomOrigine);
        if (!isset(self::PARTIES[$ext])) {
            return [];
        }
        $xml = self::partie($chemin, (string) self::PARTIES[$ext][0]);
        $doc = $xml === null ? null : self::analyser($xml);
        if ($doc === null) {
            return [];
        }

        return self::sansNoeuds(self::trouver($doc, self::relations($chemin, $nomOrigine)));
    }

    /**
     * Les octets d'une image, ou null si ce rang ne désigne rien.
     *
     * @return ?array{octets: string, type: string}
     */
    public static function octets(string $chemin, string $nomOrigine, int $rang): ?array
    {
        $images = self::du($chemin, $nomOrigine);
        if (!isset($images[$rang]) || $images[$rang]['type'] === null) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            $octets = $zip->getFromName($images[$rang]['source']);
        } finally {
            $zip->close();
        }

        return $octets === false || $octets === ''
            ? null
            : ['octets' => $octets, 'type' => (string) $images[$rang]['type']];
    }

    /**
     * Les images rangées par le paragraphe qui les porte.
     *
     * Une image vit dans un paragraphe, même quand elle flotte à côté du
     * texte : c'est là qu'on la remet. Celle qu'aucun paragraphe ne contient
     * garde son rang — l'adresse reste juste — mais ne trouve pas de place où
     * s'afficher ; Word n'en produit pas dans le corps d'un document.
     *
     * @param  array<int, DOMElement> $paragraphes  indexés par leur rang
     * @param  list<array>            $images       de « trouver() »
     * @return array<int, list<array>>  le rang du paragraphe vers ses images
     */
    public static function parParagraphe(array $paragraphes, array $images): array
    {
        $rangs = [];
        foreach ($paragraphes as $rang => $paragraphe) {
            $rangs[spl_object_id($paragraphe)] = $rang;
        }

        $par = [];
        foreach ($images as $rang => $image) {
            for ($noeud = $image['noeud']; $noeud !== null; $noeud = $noeud->parentNode) {
                $id = spl_object_id($noeud);
                if (isset($rangs[$id])) {
                    $par[$rangs[$id]][] = ['rang' => $rang] + self::sansNoeud($image);
                    break;
                }
            }
        }

        return $par;
    }

    /* --- Interne -------------------------------------------------------- */

    /** L'image que désigne ce noeud, s'il en désigne une. */
    private static function sourceDe(DOMElement $noeud, array $relations): ?string
    {
        // Word moderne : « a:blip » porte le numéro de relation. Quand l'image
        // est aussi présente en vectoriel, « r:embed » nomme la version
        // matricielle — celle que tous les navigateurs savent afficher.
        if ($noeud->namespaceURI === self::NS_A && $noeud->localName === 'blip') {
            $id = $noeud->getAttributeNS(self::NS_R, 'embed');

            return $id !== '' && isset($relations[$id]) ? $relations[$id] : null;
        }

        // Word d'avant : une image posée dans une forme VML.
        if ($noeud->namespaceURI === self::NS_V && $noeud->localName === 'imagedata') {
            $id = $noeud->getAttributeNS(self::NS_R, 'id');

            return $id !== '' && isset($relations[$id]) ? $relations[$id] : null;
        }

        // OpenDocument : le chemin est écrit tel quel dans l'archive.
        if ($noeud->namespaceURI === self::NS_DRAW && $noeud->localName === 'image') {
            $href = trim($noeud->getAttributeNS(self::NS_XLINK, 'href'));
            // Comme chez Word : une image restée dehors garde sa place, sans
            // chemin, pour que l'aperçu puisse dire qu'elle manque.
            if ($href === '' || preg_match('#^[a-z]+://#i', $href) === 1) {
                return '';
            }

            return self::ranger('', $href);
        }

        return null;
    }

    /** Ce que le document dit de l'image, pour qui ne la voit pas. */
    private static function description(DOMElement $noeud): string
    {
        $cadre = self::cadre($noeud);
        if ($cadre === null) {
            return '';
        }

        foreach ([[self::NS_WP, 'docPr', 'descr'], [self::NS_WP, 'docPr', 'name']] as [$ns, $balise, $attribut]) {
            $element = $cadre->getElementsByTagNameNS($ns, $balise)->item(0);
            if ($element instanceof DOMElement) {
                $texte = trim($element->getAttribute($attribut));
                if ($texte !== '') {
                    return $texte;
                }
            }
        }

        // OpenDocument nomme le cadre, à défaut de décrire son contenu.
        $nom = trim($cadre->getAttributeNS(self::NS_DRAW, 'name'));

        return $nom;
    }

    /**
     * La taille que le document donne à l'image, en pixels.
     *
     * L'annoncer évite que la page se réorganise sous les yeux quand chaque
     * image finit d'arriver, et rend à une petite icône sa petite taille.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function taille(DOMElement $noeud): array
    {
        $cadre = self::cadre($noeud);
        $etendue = $cadre?->getElementsByTagNameNS(self::NS_WP, 'extent')->item(0);
        if (!$etendue instanceof DOMElement) {
            return [null, null];
        }

        $largeur = (int) round(((int) $etendue->getAttribute('cx')) / self::EMU_PAR_PIXEL);
        $hauteur = (int) round(((int) $etendue->getAttribute('cy')) / self::EMU_PAR_PIXEL);

        return $largeur > 0 && $hauteur > 0
            && $largeur <= self::LARGEUR_MAX && $hauteur <= self::LARGEUR_MAX
            ? [$largeur, $hauteur]
            : [null, null];
    }

    /** L'enveloppe de l'image : « wp:inline », « wp:anchor » ou « draw:frame ». */
    private static function cadre(DOMElement $noeud): ?DOMElement
    {
        for ($parent = $noeud->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if ($parent->namespaceURI === self::NS_WP
                && in_array($parent->localName, ['inline', 'anchor'], true)) {
                return $parent;
            }
            if ($parent->namespaceURI === self::NS_DRAW && $parent->localName === 'frame') {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Le chemin d'une cible dans l'archive, ramené à sa forme simple.
     *
     * « ../media/x.png » depuis « word » vaut « media/x.png » : on remonte les
     * « .. » à la main plutôt que de les laisser filer, un chemin d'archive
     * n'ayant aucune raison de sortir de l'archive.
     */
    private static function ranger(string $dossier, string $cible): string
    {
        $cible = str_replace('\\', '/', $cible);
        $depart = str_starts_with($cible, '/')
            ? []
            : ($dossier === '' || $dossier === '.' ? [] : explode('/', $dossier));

        $morceaux = $depart;
        foreach (explode('/', ltrim($cible, '/')) as $pas) {
            if ($pas === '' || $pas === '.') {
                continue;
            }
            if ($pas === '..') {
                array_pop($morceaux);
                continue;
            }
            $morceaux[] = $pas;
        }

        return implode('/', $morceaux);
    }

    /** Le contenu d'un fichier de l'archive, ou null. */
    private static function partie(string $chemin, string $nom): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            $contenu = $zip->getFromName($nom);
        } finally {
            $zip->close();
        }

        return $contenu === false ? null : $contenu;
    }

    /** Un fragment XML, sans jamais rien aller chercher au dehors. */
    private static function analyser(string $xml): ?DOMDocument
    {
        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        return $ok ? $doc : null;
    }

    /**
     * @param  list<array> $images
     * @return list<array>
     */
    private static function sansNoeuds(array $images): array
    {
        return array_map(static fn (array $i): array => self::sansNoeud($i), $images);
    }

    private static function sansNoeud(array $image): array
    {
        unset($image['noeud']);

        return $image;
    }

    private static function extension(string $nom): string
    {
        return strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    }
}
