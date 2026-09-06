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
    private const TERME_MAX = 120;
    private const REPONSE_MIN = 2;
    private const REPONSE_MAX = 600;

    /** Au-delà, on arrête de proposer : une fiche n'est pas un dictionnaire. */
    public const PROPOSITIONS_MAX = 120;

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
                    'origine'  => 'fichier',
                    'source'   => $source,
                ];
            }
        }

        return $cartes;
    }

    /**
     * Écarte les doublons et ce qui existe déjà.
     *
     * @param list<array> $cartes
     * @param list<string> $dejaLa empreintes des cartes du cours
     * @return list<array>
     */
    public static function trier(array $cartes, array $dejaLa = []): array
    {
        $vues = array_fill_keys($dejaLa, true);
        $gardees = [];

        foreach ($cartes as $carte) {
            $empreinte = self::empreinte($carte['question']);
            if (isset($vues[$empreinte])) {
                continue;
            }
            $vues[$empreinte] = true;
            $carte['empreinte'] = $empreinte;
            $gardees[] = $carte;

            if (count($gardees) >= self::PROPOSITIONS_MAX) {
                break;
            }
        }

        return $gardees;
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
            // « Chapitre 1 — L'entreprise » annonce la suite : ce n'est pas
            // une définition, même si la ligne en a la forme.
            if (self::estUnIntitule($terme)) {
                return null;
            }
            return self::acceptable($terme, $reponse)
                ? ['question' => $terme, 'reponse' => $reponse]
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
     * Un mot mis en gras devient un trou à combler.
     *
     * On ne le fait que sur du gras explicite : c'est l'utilisateur qui a
     * désigné le mot important, l'application ne le devine pas.
     */
    private static function texteATrous(string $ligne): ?array
    {
        if (!preg_match('/\*\*(.+?)\*\*/u', $ligne, $trouve)) {
            return null;
        }

        $mot = trim($trouve[1]);
        $phrase = trim(str_replace($trouve[0], '……', $ligne));
        // Le reste du gras de la phrase n'a plus lieu d'être affiché.
        $phrase = str_replace('**', '', $phrase);

        return self::acceptable($mot, $phrase) && mb_strlen($phrase) >= 12
            ? ['question' => $phrase, 'reponse' => $mot]
            : null;
    }

    // --- Le tout-venant ------------------------------------------------------

    /**
     * Les lignes d'un texte, puces et numéros retirés.
     *
     * @return list<string>
     */
    private static function lignes(string $texte): array
    {
        if (!mb_check_encoding($texte, 'UTF-8')) {
            $texte = (string) mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        }

        $lignes = preg_split('/\r\n|\r|\n/', $texte);

        return $lignes === false ? [] : array_values(array_filter(
            array_map('trim', $lignes),
            static fn (string $l): bool => $l !== ''
        ));
    }

    /** Une ligne débarrassée de sa puce, de son numéro et de ses marques de titre. */
    private static function nettoyer(string $ligne): string
    {
        $ligne = preg_replace('/^\s*(?:[-*•▪·–—]|\d{1,2}[.)°]|[a-z][.)])\s+/u', '', $ligne) ?? $ligne;
        $ligne = preg_replace('/^#{1,6}\s+/', '', $ligne) ?? $ligne;

        return trim($ligne);
    }

    /** « Chapitre 3 », « Partie II », « Leçon 2 » : un intitulé, pas un terme. */
    private static function estUnIntitule(string $terme): bool
    {
        $mots = 'chapitre|chap|partie|section|lecon|cours|theme|titre|annexe|module|unite|seance';
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
