<?php
declare(strict_types=1);

/**
 * Fabrique des propositions de cartes à partir d'un texte.
 *
 * Aucune intelligence là-dedans : des règles, et seulement des règles. On
 * reconnaît les formes qu'une fiche de révision prend naturellement — un terme,
 * un séparateur, sa définition — et on refuse tout le reste plutôt que
 * d'inventer. Une prose au fil de la plume ne donnera donc presque rien, et
 * c'est voulu : mieux vaut trois bonnes cartes que trente à jeter.
 *
 * Rien n'est enregistré ici : ce sont des propositions, que l'utilisateur
 * retient ou écarte une par une.
 */
final class GenerateurCartes
{
    /** Les séparateurs reconnus entre un terme et sa définition. */
    private const SEPARATEURS = [':', '=', '—', '–', ' - '];

    /** En deçà, ce n'est pas une question ; au-delà, ce n'est plus une carte. */
    private const TERME_MIN = 2;
    // Au-delà, ce n'est plus un terme mais une phrase : la ligne était de la
    // prose, coupée par hasard sur un deux-points.
    private const TERME_MAX = 80;
    private const REPONSE_MIN = 2;
    private const REPONSE_MAX = 600;

    /** Ce qui remplace l'élément masqué dans un texte à trous. */
    private const TROU = '……';

    /** Au-delà, on arrête de proposer : une fiche n'est pas un dictionnaire. */
    public const PROPOSITIONS_MAX = 120;

    /** Les textes à trous se comptent à part : ils viennent par centaines. */
    private const TROUS_MAX = 40;

    /**
     * Les cartes qu'on peut tirer d'un texte.
     *
     * @return list<array{question: string, reponse: string, origine: string, source: string}>
     */
    public static function depuisTexte(string $texte, string $origine, string $source = ''): array
    {
        $cartes = [];

        foreach (self::lignes($texte) as $ligne) {
            $carte = self::depuisLigne($ligne);
            if ($carte !== null) {
                $cartes[] = $carte + ['origine' => $origine, 'source' => $source];
            }
        }

        return $cartes;
    }

    /**
     * Les cartes qu'on peut tirer d'un tableau à deux colonnes ou plus.
     *
     * Un tableur de révision est presque toujours fait ainsi : le terme à
     * gauche, sa définition à droite. La première ligne est sautée quand elle
     * ressemble à un en-tête.
     *
     * @param list<list<string>> $lignes
     * @return list<array{question: string, reponse: string, origine: string, source: string}>
     */
    public static function depuisTableau(array $lignes, string $source = ''): array
    {
        $cartes = [];
        $premiere = true;

        foreach ($lignes as $ligne) {
            $colonnes = array_values(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $ligne),
                static fn (string $v): bool => $v !== ''
            ));
            if (count($colonnes) < 2) {
                $premiere = false;
                continue;
            }

            // « Terme | Définition » en tête de tableau nomme les colonnes ;
            // ce n'est pas une carte.
            if ($premiere && self::ressembleAUnEntete($colonnes)) {
                $premiere = false;
                continue;
            }
            $premiere = false;

            $terme = $colonnes[0];
            $reponse = implode(' — ', array_slice($colonnes, 1));
            if (self::acceptable($terme, $reponse)) {
                $cartes[] = [
                    'question' => $terme,
                    'reponse'  => $reponse,
                    'genre'    => 'definition',
                    'origine'  => 'fichier',
                    'source'   => $source,
                ];
            }
        }

        return $cartes;
    }

    /**
     * Écarte les doublons, range par intérêt, et s'arrête à temps.
     *
     * Une définition vaut mieux qu'une question de devoir sans réponse, qui
     * vaut mieux qu'un texte à trous : les premières se lisent telles quelles,
     * les derniers demandent un coup d'œil. Les trous sont en outre plafonnés,
     * pour qu'un long document n'en produise pas des centaines qui noieraient
     * les bonnes cartes.
     *
     * @param list<array> $cartes
     * @param list<string> $dejaLa empreintes des cartes du cours
     * @return list<array>
     */
    public static function trier(array $cartes, array $dejaLa = []): array
    {
        $rang = ['definition' => 0, 'devoir' => 1, 'trou' => 2];
        $vues = array_fill_keys($dejaLa, true);
        $paniers = ['definition' => [], 'devoir' => [], 'trou' => []];

        foreach ($cartes as $carte) {
            $genre = $carte['genre'] ?? 'definition';
            $genre = isset($rang[$genre]) ? $genre : 'definition';

            $empreinte = self::empreinte($carte['question']);
            if (isset($vues[$empreinte])) {
                continue;
            }
            $vues[$empreinte] = true;

            if ($genre === 'trou' && count($paniers['trou']) >= self::TROUS_MAX) {
                continue;
            }

            $carte['empreinte'] = $empreinte;
            $carte['genre'] = $genre;
            $paniers[$genre][] = $carte;
        }

        return array_slice(
            array_merge($paniers['definition'], $paniers['devoir'], $paniers['trou']),
            0,
            self::PROPOSITIONS_MAX
        );
    }

    /**
     * Ce qui identifie une carte : sa question, réduite à sa substance.
     *
     * Accents, casse et ponctuation mis de côté, « La Révolution » et
     * « révolution ! » sont la même question, et ne seront pas proposées deux
     * fois.
     */
    public static function empreinte(string $question): string
    {
        $nu = self::sansAccents(mb_strtolower(trim($question)));
        $nu = preg_replace('/[^a-z0-9]+/', ' ', $nu) ?? $nu;

        return md5(trim($nu));
    }

    /**
     * Un texte sans ses accents.
     *
     * iconv() ne convient pas : selon la machine, « Définition » en ressort
     * « D'efinition ». Une table explicite donne le même résultat partout.
     */
    private static function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);
    }

    // --- Les règles ----------------------------------------------------------

    /** Une carte tirée d'une ligne, ou null si la ligne n'en donne pas. */
    private static function depuisLigne(string $ligne): ?array
    {
        // Un titre annonce la suite, il ne définit rien : « # Chapitre 3 —
        // L'entreprise » n'est pas une carte.
        if (preg_match('/^\s*#{1,6}\s/', $ligne)) {
            return null;
        }

        $ligne = self::nettoyer($ligne);
        if ($ligne === '') {
            return null;
        }

        // Une adresse contient « : » et « - » sans rien définir du tout.
        if (preg_match('#https?://|www\.#i', $ligne)) {
            return null;
        }

        $coupe = self::couper($ligne);
        if ($coupe !== null) {
            [$terme, $reponse] = $coupe;
            // « Question 3 : … » n'est pas une définition, mais ce qui suit
            // est souvent la vraie question du devoir : elle fait une carte,
            // dont la réponse reste à écrire.
            if (self::estUnIntitule($terme)) {
                return self::questionDuDevoir($terme, $reponse);
            }
            return self::acceptable($terme, $reponse)
                ? ['question' => $terme, 'reponse' => $reponse, 'genre' => 'definition']
                : null;
        }

        return self::texteATrous($ligne);
    }

    /**
     * Coupe une ligne en terme et définition, au premier séparateur utile.
     *
     * L'heure « 14:30 » et les deux-points d'une phrase ne comptent pas : on
     * exige un séparateur entouré d'espaces, ou suivi d'une espace.
     */
    private static function couper(string $ligne): ?array
    {
        $meilleur = null;

        foreach (self::SEPARATEURS as $separateur) {
            $motif = $separateur === ' - '
                ? '/ - /u'
                : '/\s*' . preg_quote($separateur, '/') . '\s+/u';

            if (preg_match($motif, $ligne, $trouve, PREG_OFFSET_CAPTURE)) {
                $position = $trouve[0][1];
                if ($meilleur === null || $position < $meilleur[0]) {
                    $meilleur = [$position, strlen($trouve[0][0])];
                }
            }
        }

        if ($meilleur === null) {
            return null;
        }

        [$position, $longueur] = $meilleur;
        $terme = trim(substr($ligne, 0, $position));
        $reponse = trim(substr($ligne, $position + $longueur));

        return $terme === '' || $reponse === '' ? null : [$terme, $reponse];
    }

    /**
     * Une phrase dont on masque l'élément saillant.
     *
     * Trois pistes, dans cet ordre : ce que l'utilisateur a mis en gras, puis
     * un nombre — une date, un pourcentage, une quantité — enfin un nom propre.
     * Ce sont les trois choses qu'on retient mal et qu'une carte fait réviser ;
     * masquer un mot ordinaire ne demanderait rien.
     *
     * La phrase doit être assez longue pour que le trou garde un sens : sans
     * contexte, la question n'a pas de réponse.
     */
    private static function texteATrous(string $ligne): ?array
    {
        // Le gras d'abord : c'est l'utilisateur qui a désigné le mot important.
        if (preg_match('/\*\*(.+?)\*\*/u', $ligne, $trouve)) {
            $mot = trim($trouve[1]);
            $phrase = str_replace('**', '', trim(str_replace($trouve[0], self::TROU, $ligne)));

            return self::acceptable($mot, $phrase) && mb_strlen($phrase) >= 12
                ? ['question' => $phrase, 'reponse' => $mot, 'genre' => 'trou']
                : null;
        }

        // Une phrase, et non une ligne d'en-tête ou de tableau : au moins huit
        // vrais mots, et une nette majorité de lettres.
        $mots = preg_match_all('/\p{L}{2,}/u', $ligne);
        $lettres = preg_match_all('/[\p{L}\s]/u', $ligne);
        if ($mots < 8 || mb_strlen($ligne) < 40 || mb_strlen($ligne) > 300
            || $lettres / max(1, mb_strlen($ligne)) < 0.75) {
            return null;
        }

        $cache = self::elementSaillant($ligne);
        if ($cache === null) {
            return null;
        }

        [$element, $position] = $cache;
        $phrase = mb_substr($ligne, 0, $position) . self::TROU
            . mb_substr($ligne, $position + mb_strlen($element));

        return ['question' => trim($phrase), 'reponse' => $element, 'genre' => 'trou'];
    }

    /**
     * L'élément d'une phrase qui mérite d'être masqué, et où il se trouve.
     *
     * @return array{0: string, 1: int}|null
     */
    private static function elementSaillant(string $ligne): ?array
    {
        // Un nombre d'au moins deux chiffres : une année, une quantité, un taux.
        if (preg_match('/\d[\d  ]*(?:[.,]\d+)?\s?%?/u', $ligne, $m, PREG_OFFSET_CAPTURE)) {
            $element = trim($m[0][0]);
            if (mb_strlen(preg_replace('/\D/u', '', $element) ?? '') >= 2) {
                return [$element, mb_strlen(substr($ligne, 0, $m[0][1]))];
            }
        }

        // Un nom propre : une majuscule au milieu de la phrase, pas en tête ni
        // après un point, où toute phrase commence par une majuscule.
        if (preg_match_all('/(?<=[a-zà-ÿ,;] )(\p{Lu}[\p{L}\'’-]{2,})/u', $ligne, $noms, PREG_OFFSET_CAPTURE)) {
            foreach ($noms[1] as $nom) {
                return [$nom[0], mb_strlen(substr($ligne, 0, $nom[1]))];
            }
        }

        return null;
    }

    /**
     * La question d'un devoir, repérée derrière son intitulé.
     *
     * « Question 3 : quelles sont les deux catégories ? » vaut une carte. La
     * réponse, elle, n'est écrite nulle part dans l'énoncé : elle reste vide,
     * à remplir par celui qui révise — c'est d'ailleurs tout l'exercice.
     */
    private static function questionDuDevoir(string $intitule, string $suite): ?array
    {
        // « Activité 2 : … » annonce un titre ; « Question 3 : … » pose une
        // question. Seule la seconde a une réponse à chercher.
        $nu = self::sansAccents(mb_strtolower($intitule));
        if (!preg_match('/^(?:question|q|consigne)\b/', $nu)) {
            return null;
        }

        $suite = trim($suite);
        $mots = preg_match_all('/\S+/u', $suite);
        if ($mots < 4 || mb_strlen($suite) > 400 || !preg_match('/\p{L}/u', $suite)) {
            return null;
        }

        return ['question' => $suite, 'reponse' => '', 'genre' => 'devoir'];
    }

    // --- Le tout-venant ------------------------------------------------------

    /**
     * Les lignes d'un texte, débarrassées de ce qui se répète.
     *
     * L'en-tête et le pied d'un document reviennent à chaque page : « CEJM |
     * Chapitre 3 | 2024-2025 ». Ce n'est pas du contenu, et une carte fabriquée
     * dessus ne demande rien. Une ligne vue trois fois est donc écartée.
     *
     * @return list<string>
     */
    private static function lignes(string $texte): array
    {
        if (!mb_check_encoding($texte, 'UTF-8')) {
            $texte = (string) mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        }

        $brutes = preg_split('/\r\n|\r|\n/', $texte);
        if ($brutes === false) {
            return [];
        }

        $lignes = array_values(array_filter(
            array_map('trim', $brutes),
            static fn (string $l): bool => $l !== ''
        ));

        $vues = [];
        foreach ($lignes as $ligne) {
            $cle = self::empreinte($ligne);
            $vues[$cle] = ($vues[$cle] ?? 0) + 1;
        }

        return array_values(array_filter(
            $lignes,
            static fn (string $l): bool => ($vues[self::empreinte($l)] ?? 0) < 3
        ));
    }
    /** Une ligne débarrassée de sa puce, de son numéro et de ses marques de titre. */
    private static function nettoyer(string $ligne): string
    {
        $ligne = preg_replace('/^\s*(?:[-*•▪·–—]|\d{1,2}[.)°]|[a-z][.)])\s+/u', '', $ligne) ?? $ligne;
        $ligne = preg_replace('/^#{1,6}\s+/', '', $ligne) ?? $ligne;

        return trim($ligne);
    }

    /**
     * « Chapitre 3 », « Activité 1 », « Source » : un intitulé, pas un terme.
     *
     * Un document scolaire est jalonné de ces étiquettes. Elles ont la forme
     * d'une définition — un mot, deux points, du texte — mais ne définissent
     * rien : « Source » n'est pas une notion à réviser.
     */
    private static function estUnIntitule(string $terme): bool
    {
        $mots = 'chapitre|chap|partie|section|lecon|cours|theme|titre|annexe|module|unite|seance'
            . '|activite|exercice|document|doc|source|sources|question|consigne|objectif|objectifs'
            . '|remarque|exemple|exemples|methode|bilan|correction|bareme|figure|tableau|schema';
        $nu = self::sansAccents(mb_strtolower(trim($terme)));

        return (bool) preg_match('/^(?:' . $mots . ')\.?\s*[0-9ivx]*$/', $nu)
            || in_array($nu, ['introduction', 'conclusion', 'sommaire', 'plan', 'definitions'], true);
    }

    /** Ce couple tient-il debout comme carte ? */
    private static function acceptable(string $terme, string $reponse): bool
    {
        $t = mb_strlen($terme);
        $r = mb_strlen($reponse);

        if ($t < self::TERME_MIN || $t > self::TERME_MAX) {
            return false;
        }
        if ($r < self::REPONSE_MIN || $r > self::REPONSE_MAX) {
            return false;
        }

        // La réponse doit dire quelque chose ; la question peut être une
        // date ou une année, qui n'ont pas de lettre mais interrogent bien.
        if (!preg_match('/\p{L}/u', $reponse)) {
            return false;
        }

        // Un terme est court et d'un seul tenant. Une virgule ou une longue
        // enfilade de mots trahissent une phrase coupée par hasard sur un
        // séparateur — « Sa population, tout d'abord, est constituée de… ».
        if (str_contains($terme, ',') || preg_match_all('/\S+/u', $terme) > 8) {
            return false;
        }

        return (bool) preg_match('/\p{L}/u', $terme)
            || (bool) preg_match('#^\d{3,4}$|^\d{1,2}[/.-]\d{1,2}(?:[/.-]\d{2,4})?$#', $terme);
    }

    /** Cette première ligne nomme-t-elle les colonnes plutôt qu'un terme ? */
    private static function ressembleAUnEntete(array $colonnes): bool
    {
        $entetes = ['terme', 'termes', 'mot', 'mots', 'question', 'questions', 'notion', 'notions',
                    'definition', 'definitions', 'reponse', 'reponses', 'sens', 'traduction'];

        foreach (array_slice($colonnes, 0, 2) as $colonne) {
            $nu = self::sansAccents(mb_strtolower(trim($colonne)));
            if (!in_array(trim($nu), $entetes, true)) {
                return false;
            }
        }

        return true;
    }
}
