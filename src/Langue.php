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

    /** La langue en cours : celle du compte, ou le français. */
    public static function courante(): string
    {
        if (self::$courante !== null) {
            return self::$courante;
        }
        $dite = (string) (Auth::utilisateur()['langue'] ?? self::PAR_DEFAUT);

        return self::$courante = isset(self::LANGUES[$dite]) ? $dite : self::PAR_DEFAUT;
    }

    /** Impose une langue (au changement de réglage, et dans les essais). */
    public static function imposer(string $langue): void
    {
        self::$courante = isset(self::LANGUES[$langue]) ? $langue : self::PAR_DEFAUT;
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
