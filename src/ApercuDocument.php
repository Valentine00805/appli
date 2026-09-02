<?php
declare(strict_types=1);

/**
 * Aperçu texte des documents bureautiques.
 *
 * Un navigateur ne sait pas afficher un .docx : il le télécharge. Mais ces
 * fichiers sont des archives contenant du XML, et l'application sait déjà lire
 * des archives. On en extrait donc le texte pour le montrer dans une page.
 *
 * C'est un aperçu, pas le document : la mise en forme, les images et la
 * pagination ne sont pas reproduites. Le fichier d'origine reste téléchargeable
 * pour l'ouvrir dans Word ou LibreOffice.
 */
final class ApercuDocument
{
    /**
     * Où trouver le texte, selon l'extension : le fichier interne à lire dans
     * l'archive, et les balises qui délimitent un paragraphe.
     */
    private const FORMATS = [
        'docx' => ['word/document.xml',       ['w:p']],
        'odt'  => ['content.xml',             ['text:p', 'text:h']],
        'pptx' => [null,                      ['a:p']],   // une partie par diapositive
        'odp'  => ['content.xml',             ['text:p', 'text:h']],
    ];

    /** Ce fichier peut-il être présenté en aperçu ? */
    public static function possible(string $nomOrigine): bool
    {
        return array_key_exists(self::extension($nomOrigine), self::FORMATS);
    }

    /** Nom lisible du format, pour l'expliquer à l'écran. */
    public static function format(string $nomOrigine): string
    {
        return match (self::extension($nomOrigine)) {
            'docx'  => 'document Word',
            'odt'   => 'document LibreOffice',
            'pptx'  => 'présentation PowerPoint',
            'odp'   => 'présentation LibreOffice',
            default => 'document',
        };
    }

    /**
     * Les paragraphes du document, dans l'ordre.
     *
     * @return array<int, string>
     * @throws RuntimeException si l'archive est illisible
     */
    public static function paragraphes(string $chemin, string $nomOrigine): array
    {
        $ext = self::extension($nomOrigine);
        if (!isset(self::FORMATS[$ext])) {
            throw new RuntimeException('Ce format ne se prévisualise pas.');
        }
        [$partie, $balises] = self::FORMATS[$ext];

        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Le fichier est illisible : ce n’est pas une archive valide.');
        }

        try {
            // Une présentation range chaque diapositive dans sa propre partie.
            $parties = $partie !== null ? [$partie] : self::diapositives($zip);
            if ($parties === []) {
                throw new RuntimeException('Le contenu du document est introuvable dans le fichier.');
            }

            $paragraphes = [];
            foreach ($parties as $nom) {
                $xml = $zip->getFromName($nom);
                if ($xml === false) {
                    continue;
                }
                foreach (self::extraire($xml, $balises) as $texte) {
                    $paragraphes[] = $texte;
                }
            }
            return $paragraphes;
        } finally {
            $zip->close();
        }
    }

    /* --- Interne -------------------------------------------------------- */

    private static function extension(string $nom): string
    {
        return strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    }

    /**
     * Les diapositives d'une présentation, dans l'ordre des numéros.
     * @return array<int, string>
     */
    private static function diapositives(ZipArchive $zip): array
    {
        $noms = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $nom, $m) === 1) {
                $noms[(int) $m[1]] = $nom;
            }
        }
        ksort($noms);
        return array_values($noms);
    }

    /**
     * Le texte des paragraphes d'un fragment XML.
     *
     * @param array<int, string> $balises
     * @return array<int, string>
     */
    private static function extraire(string $xml, array $balises): array
    {
        $avant = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // LIBXML_NONET coupe tout accès réseau et les entités externes ne sont
        // pas résolues : un fichier piégé ne peut pas faire lire le serveur.
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        if (!$ok) {
            return [];
        }

        // Un seul parcours, pour garder l'ordre du document : lire les balises
        // l'une après l'autre mélangerait titres et paragraphes.
        $paragraphes = [];
        foreach ($doc->getElementsByTagName('*') as $noeud) {
            if (!in_array($noeud->nodeName, $balises, true)) {
                continue;
            }
            $texte = trim(preg_replace('/\s+/u', ' ', (string) $noeud->textContent) ?? '');
            if ($texte !== '') {
                $paragraphes[] = $texte;
            }
        }
        return $paragraphes;
    }
}
