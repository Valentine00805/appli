<?php
declare(strict_types=1);

/**
 * La langue de l'interface.
 *
 * Chaque langue est un fichier de « lang/ » : un tableau de clés (« nav.accueil »)
 * et de phrases. Le français est la langue de référence — une clé qu'une autre
 * langue n'a pas encore s'y lit en français plutôt que de laisser un trou.
 *
 * Ce qu'on écrit soi-même — cours, notes, messages, noms de matières — n'est
 * jamais traduit : seule l'application parle.
 */
final class Langue
{
    /** Les langues proposées, dans l'ordre du menu. */
    public const LANGUES = [
        'fr' => ['nom' => 'Français', 'drapeau' => '🇫🇷'],
        'en' => ['nom' => 'English',  'drapeau' => '🇬🇧'],
        'es' => ['nom' => 'Español',  'drapeau' => '🇪🇸'],
        'de' => ['nom' => 'Deutsch',  'drapeau' => '🇩🇪'],
    ];

    public const PAR_DEFAUT = 'fr';

    private static ?string $courante = null;

    /** @var array<string, array<string, string|array>> les fichiers déjà lus */
    private static array $chargees = [];

    /**
     * Le cookie où un visiteur sans compte garde sa langue. Il est purement fonctionnel :
     * il ne sert qu'à retrouver ce choix, et à rien d'autre.
     */
    public const COOKIE = 'MESCOURS_LANGUE';

    /**
     * La langue en cours : celle du compte ; à défaut, celle du visiteur — son
     * choix, puis son navigateur — ; à défaut, le français.
     */
    public static function courante(): string
    {
        if (self::$courante !== null) {
            return self::$courante;
        }
        $dite = (string) (Auth::utilisateur()['langue'] ?? self::duVisiteur());

        return self::$courante = isset(self::LANGUES[$dite]) ? $dite : self::PAR_DEFAUT;
    }

    /**
     * La langue de quelqu'un qui n'a pas de compte : celle qu'il a choisie sur une
     * page publique, sinon la première que son navigateur demande et que
     * l'application parle.
     */
    public static function duVisiteur(): string
    {
        // La réponse dépend de ces deux en-têtes : un cache ne doit pas la resservir à un autre.
        if (!headers_sent()) {
            header('Vary: Accept-Language, Cookie', false);
        }
        $choisie = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($choisie) && isset(self::LANGUES[$choisie])) {
            return $choisie;
        }

        return self::duNavigateur((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /**
     * La première langue d'un en-tête Accept-Language que l'application parle.
     *
     * « en-GB,en;q=0.9,fr;q=0.8 » donne « en » ; « ja,zh;q=0.9 », que nous ne parlons
     * pas, donne le français. Les préférences se trient par qualité (q), à égalité
     * dans l'ordre d'écriture ; q=0 veut dire « surtout pas ».
     */
    public static function duNavigateur(string $entete): string
    {
        $demandees = [];
        foreach (explode(',', $entete) as $morceau) {
            $parties = explode(';', $morceau);
            $code = strtolower(explode('-', trim($parties[0]))[0]);
            $qualite = 1.0;
            foreach (array_slice($parties, 1) as $parametre) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parametre, $m) === 1) {
                    $qualite = (float) $m[1];
                }
            }
            if ($qualite > 0 && isset(self::LANGUES[$code])) {
                $demandees[] = [$code, $qualite];
            }
        }
        // Le tri de PHP est stable : à qualité égale, l'ordre d'écriture reste.
        usort($demandees, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return $demandees[0][0] ?? self::PAR_DEFAUT;
    }

    /**
     * Garde la langue d'un visiteur dans un cookie, pour un an. Elle sert aux pages
     * ouvertes sans compte, et à la page de connexion après une déconnexion.
     */
    public static function retenirPourLeVisiteur(string $langue): void
    {
        if (!isset(self::LANGUES[$langue])) {
            return;
        }
        $_COOKIE[self::COOKIE] = $langue;
        if (headers_sent()) {
            return;
        }
        $base = defined('BASE_URL') ? (string) BASE_URL : '';
        setcookie(self::COOKIE, $langue, [
            'expires'  => time() + 365 * 86400,
            'path'     => $base . '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Impose une langue (au changement de réglage, et dans les essais). */
    public static function imposer(string $langue): void
    {
        self::$courante = isset(self::LANGUES[$langue]) ? $langue : self::PAR_DEFAUT;
    }

    /**
     * Fabrique quelque chose dans la langue d'un autre compte.
     *
     * Une notification, un e-mail : le texte part vers quelqu'un d'autre, et
     * c'est sa langue qui compte, pas celle de qui déclenche l'envoi. La
     * langue d'avant revient ensuite, même si la fabrication a échoué.
     */
    public static function pourLeCompte(int $userId, callable $quoi): mixed
    {
        $avant = self::$courante;
        $dite = Database::valeur('SELECT langue FROM users WHERE id = ?', [$userId]);
        self::imposer((string) ($dite ?? self::PAR_DEFAUT));
        try {
            return $quoi();
        } finally {
            self::$courante = $avant;
        }
    }

    /** @return array<string, string|array> les phrases d'une langue */
    private static function phrases(string $langue): array
    {
        if (!isset(self::$chargees[$langue])) {
            $fichier = dirname(__DIR__) . '/lang/' . $langue . '.php';
            self::$chargees[$langue] = is_file($fichier) ? (array) require $fichier : [];
        }

        return self::$chargees[$langue];
    }

    /**
     * La phrase d'une clé, dans la langue en cours.
     *
     * Les valeurs entre accolades sont remplacées : texte('taches.reste', ['n' => 3]).
     * Clé inconnue : la phrase française, et à défaut la clé elle-même — on voit
     * alors ce qui reste à traduire, sans page blanche.
     */
    public static function texte(string $cle, array $valeurs = []): string
    {
        $phrase = self::phrases(self::courante())[$cle] ?? self::phrases(self::PAR_DEFAUT)[$cle] ?? $cle;
        if (is_array($phrase)) {
            $phrase = implode(' ', $phrase);
        }
        foreach ($valeurs as $nom => $valeur) {
            $phrase = str_replace('{' . $nom . '}', (string) $valeur, (string) $phrase);
        }

        return (string) $phrase;
    }

    /**
     * La phrase d'une clé dans une langue précise, sans toucher à la langue en cours.
     *
     * Pour ce qui se lit dans la langue d'un fichier plutôt que dans celle de la page :
     * les séparateurs d'un nombre, quand on lit un relevé de banque.
     */
    public static function texteEn(string $langue, string $cle): string
    {
        $langue = isset(self::LANGUES[$langue]) ? $langue : self::PAR_DEFAUT;
        $phrase = self::phrases($langue)[$cle] ?? self::phrases(self::PAR_DEFAUT)[$cle] ?? $cle;

        return is_array($phrase) ? implode(' ', $phrase) : (string) $phrase;
    }

    /**
     * Une phrase qui s'accorde : « 1 tâche », « 3 tâches ».
     *
     * Deux clés au lieu d'une : « taches.reste.un » et « taches.reste.plusieurs ».
     * Le français garde le singulier à zéro (« 0 tâche »), l'anglais, l'espagnol
     * et l'allemand prennent le pluriel (« 0 tasks ») : chaque langue compte à
     * sa façon, et la vue n'a pas à le savoir.
     */
    public static function nombre(string $cle, int $n, array $valeurs = []): string
    {
        $seul = self::courante() === 'fr' ? abs($n) < 2 : abs($n) === 1;

        return self::texte($cle . ($seul ? '.un' : '.plusieurs'), $valeurs + ['n' => $n]);
    }

    /**
     * Les phrases que le script affiche lui-même.
     *
     * Le navigateur ne lit pas les fichiers de langue : la page lui passe ce
     * dont il a besoin, et lui seul — les clés « js. ». Tout le reste reste
     * au serveur, et le script n'a rien à deviner.
     *
     * @return array<string, string>
     */
    public static function pourLeScript(): array
    {
        $tout = self::phrases(self::PAR_DEFAUT);
        $dites = self::phrases(self::courante());
        // Le script accorde aussi ses pluriels : il lui faut savoir où il est.
        $mots = ['_langue' => self::courante()];
        foreach ($tout as $cle => $phrase) {
            if (is_string($cle) && str_starts_with($cle, 'js.')) {
                $mots[substr($cle, 3)] = (string) ($dites[$cle] ?? $phrase);
            }
        }

        return $mots;
    }

    /**
     * Une liste de la langue en cours : les mois, les jours…
     *
     * @return list<string>
     */
    public static function liste(string $cle): array
    {
        $liste = self::phrases(self::courante())[$cle] ?? self::phrases(self::PAR_DEFAUT)[$cle] ?? [];

        return is_array($liste) ? array_values($liste) : [];
    }
}
