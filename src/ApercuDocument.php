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

    /** Formats présentés sous forme de tableau plutôt que de paragraphes. */
    private const TABLEURS = ['xlsx', 'csv'];

    /** Que le navigateur sait afficher lui-même : on l'intègre dans la page. */
    private const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const BRUTS  = ['txt', 'md'];

    /** Lignes affichées au plus : un gros classeur ne doit pas noyer la page. */
    public const LIGNES_MAX = 500;

    /** Au-delà, un fichier texte est tronqué à l'affichage. */
    public const TEXTE_MAX = 200000;

    /** Ce fichier peut-il être présenté en aperçu ? */
    public static function possible(string $nomOrigine): bool
    {
        return self::genre($nomOrigine) !== null;
    }

    /**
     * Comment ce fichier se montre :
     *   'document' → son texte, paragraphe par paragraphe
     *   'tableur'  → un tableau de valeurs
     *   'pdf'      → le document lui-même, intégré dans la page
     *   'image'    → l'image elle-même
     *   'brut'     → le contenu du fichier, tel quel
     */
    public static function genre(string $nomOrigine): ?string
    {
        $ext = self::extension($nomOrigine);

        return match (true) {
            array_key_exists($ext, self::FORMATS)  => 'document',
            in_array($ext, self::TABLEURS, true)   => 'tableur',
            $ext === 'pdf'                          => 'pdf',
            in_array($ext, self::IMAGES, true)      => 'image',
            in_array($ext, self::BRUTS, true)       => 'brut',
            default                                 => null,
        };
    }

    /** Cet aperçu est-il un tableau ? */
    public static function estTableur(string $nomOrigine): bool
    {
        return self::genre($nomOrigine) === 'tableur';
    }

    /**
     * Le contenu d'un fichier texte, remis en UTF-8 et borné.
     * @return array{texte: string, tronque: bool}
     */
    public static function texteBrut(string $chemin): array
    {
        $contenu = file_get_contents($chemin, false, null, 0, self::TEXTE_MAX + 1);
        if ($contenu === false) {
            throw new RuntimeException('Le fichier est illisible.');
        }

        $tronque = strlen($contenu) > self::TEXTE_MAX;
        if ($tronque) {
            $contenu = substr($contenu, 0, self::TEXTE_MAX);
        }

        return ['texte' => self::enUtf8($contenu), 'tronque' => $tronque];
    }

    /**
     * Les lignes d'un tableur, chacune étant un tableau de cellules.
     * Les colonnes absentes d'une ligne sont comblées : le tableau reste droit.
     *
     * @return array{lignes: array<int, array<int, string>>, total: int}
     * @throws RuntimeException si le fichier est illisible
     */
    public static function tableau(string $chemin, string $nomOrigine): array
    {
        $brut = self::extension($nomOrigine) === 'csv'
            ? self::lireCsv($chemin)
            : array_values(ClasseurLecteur::lire($chemin));

        $total = count($brut);

        // Toutes les colonnes rencontrées, pour donner la même largeur à chaque ligne.
        $colonnes = [];
        foreach (array_slice($brut, 0, self::LIGNES_MAX) as $ligne) {
            foreach (array_keys($ligne) as $colonne) {
                $colonnes[(string) $colonne] = true;
            }
        }
        $colonnes = array_keys($colonnes);
        usort($colonnes, static fn (string $a, string $b): int => [strlen($a), $a] <=> [strlen($b), $b]);

        $lignes = [];
        foreach (array_slice($brut, 0, self::LIGNES_MAX) as $ligne) {
            $droite = [];
            foreach ($colonnes as $colonne) {
                $droite[] = (string) ($ligne[$colonne] ?? '');
            }
            // Une ligne entièrement vide n'apporte rien.
            if (implode('', $droite) !== '') {
                $lignes[] = $droite;
            }
        }

        return ['lignes' => $lignes, 'total' => $total];
    }

    /**
     * Les lignes d'un fichier CSV, séparateur deviné entre « ; » et « , ».
     * @return array<int, array<int, string>>
     */
    private static function lireCsv(string $chemin): array
    {
        $flux = fopen($chemin, 'rb');
        if ($flux === false) {
            throw new RuntimeException('Le fichier est illisible.');
        }

        try {
            $premiere = (string) fgets($flux);
            // Le point-virgule est la norme des tableurs français.
            $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';
            rewind($flux);

            $lignes = [];
            while (($cellules = fgetcsv($flux, 0, $separateur, '"', '\\')) !== false) {
                if ($cellules === [null]) {
                    continue;
                }
                $lignes[] = array_map(
                    static fn ($c): string => self::enUtf8((string) ($c ?? '')),
                    $cellules
                );
                if (count($lignes) > self::LIGNES_MAX) {
                    // On continue à compter sans tout garder en mémoire.
                    while (fgetcsv($flux, 0, $separateur, '"', '\\') !== false) {
                        $lignes[] = [];
                    }
                    break;
                }
            }
            return $lignes;
        } finally {
            fclose($flux);
        }
    }

    /** Un CSV exporté depuis Excel est souvent en Windows-1252, pas en UTF-8. */
    private static function enUtf8(string $texte): string
    {
        if ($texte === '' || mb_check_encoding($texte, 'UTF-8')) {
            return $texte;
        }
        return (string) mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
    }

    /** Nom lisible du format, pour l'expliquer à l'écran. */
    public static function format(string $nomOrigine): string
    {
        return match (self::extension($nomOrigine)) {
            'docx'  => 'document Word',
            'odt'   => 'document LibreOffice',
            'pptx'  => 'présentation PowerPoint',
            'odp'   => 'présentation LibreOffice',
            'xlsx'  => 'classeur Excel',
            'csv'   => 'fichier CSV',
            'pdf'   => 'document PDF',
            'txt'   => 'fichier texte',
            'md'    => 'fichier Markdown',
            'png', 'jpg', 'jpeg', 'gif', 'webp' => 'image',
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
