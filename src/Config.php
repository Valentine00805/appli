<?php
declare(strict_types=1);

/** Accès à la configuration de l'application. */
final class Config
{
    private static ?array $valeurs = null;

    /** Le nom du fichier de réglages rangé hors du dossier publié (au-dessus de l'application). */
    public const FICHIER_HORS_PUBLIC = 'mes-cours-parametres.php';

    /** Combien de dossiers au-dessus de l'application on cherche ce fichier : le parent, le grand-parent, l'arrière-grand-parent. */
    private const NIVEAUX_HORS_PUBLIC = 3;

    /**
     * Où lire les réglages locaux : « mes-cours-parametres.php » dans un dossier AU-DESSUS de l'application s'il existe — le plus
     * proche d'abord —, sinon « config/parametres.php ».
     *
     * Sur un hébergeur, seul « public_html » est publié ; ses dossiers parents ne le sont pas. Application à la racine de
     * public_html : le fichier va dans le parent (domains/<site>/). Application dans un sous-dossier (public_html/appli) : le
     * parent est public_html, publié — on le range donc un cran plus haut, et c'est pourquoi la recherche remonte de trois niveaux.
     * Les clés secrètes (Google, Outlook, mot de passe de la base, clé de chiffrement) y sont hors de portée d'une adresse, même si
     * la protection du dossier config/ venait à manquer. En local, ce fichier n'existe pas : rien ne change.
     */
    public static function fichierLocal(string $racine): string
    {
        $dossier = $racine;
        for ($niveau = 0; $niveau < self::NIVEAUX_HORS_PUBLIC; $niveau++) {
            $parent = dirname($dossier);
            if ($parent === $dossier) {
                break;
            }
            $candidat = $parent . DIRECTORY_SEPARATOR . self::FICHIER_HORS_PUBLIC;
            if (is_file($candidat)) {
                return $candidat;
            }
            $dossier = $parent;
        }

        return $racine . '/config/parametres.php';
    }

    /**
     * Charge la configuration : le fichier local s'il existe, les réglages par
     * défaut sinon.
     *
     * Les défauts sont passés en tableau plutôt que lus dans un fichier à part.
     * Ce fichier-là a été mis en quarantaine quatre fois par l'antivirus du
     * poste, sous quatre noms, et l'application tombait avec lui. Un tableau
     * écrit dans index.php ne peut pas disparaître tout seul.
     *
     * Le fichier local, lui, reste facultatif : il n'existe que sur les
     * installations qui ont d'autres identifiants que ceux d'un WAMP ordinaire,
     * et il n'entre pas dans le dépôt.
     *
     * Le fichier local complète les défauts au lieu de les effacer : il n'y
     * écrit que ce qu'il change. Sans quoi une installation qui n'y aurait mis
     * que ses identifiants de base perdrait, en silence, toute section ajoutée
     * depuis — la liaison Outlook, par exemple, se contenterait de ne pas
     * exister.
     *
     * @param array $defauts       réglages utilisés faute d'indication locale
     * @param string|null $fichier chemin du fichier local, prioritaire
     */
    public static function charger(array $defauts, ?string $fichier = null): void
    {
        $locaux = $fichier !== null && is_file($fichier) ? require $fichier : [];
        if (!is_array($locaux)) {
            // Un fichier qui ne « return » pas un tableau (le « return » oublié) serait ignoré sans un mot : on le dit au journal d'erreurs.
            error_log('Config : le fichier de réglages ' . basename((string) $fichier) . ' ne renvoie pas un tableau (il manque « return [ … ]; » ?) : il est ignoré.');
            $locaux = [];
        }

        /*
         * Un second fichier facultatif, « parametres.test.php », que les suites d'essais posent le temps
         * d'un essai (rediriger Gemini vers un faux serveur local, par exemple) puis retirent. Il ne va
         * pas au dépôt et ne porte aucun secret ; ses sections l'emportent sur celles du fichier local. Il n'est lu que depuis le poste lui-même
         * (ligne de commande ou 127.0.0.1) : oublié sur un serveur en ligne, il ne peut rien rediriger.
         */
        $essai = $fichier !== null ? dirname($fichier) . DIRECTORY_SEPARATOR . 'parametres.test.php' : null;
        $locale = PHP_SAPI === 'cli' || in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
        if ($essai !== null && $locale && is_file($essai)) {
            $surcharge = require $essai;
            foreach (is_array($surcharge) ? $surcharge : [] as $section => $reglages) {
                $locaux[$section] = is_array($reglages) && is_array($locaux[$section] ?? null)
                    ? $reglages + $locaux[$section]
                    : $reglages;
            }
        }

        foreach ($locaux as $section => $reglages) {
            $defauts[$section] = is_array($reglages) && is_array($defauts[$section] ?? null)
                ? $reglages + $defauts[$section]
                : $reglages;
        }

        self::$valeurs = $defauts;
    }

    public static function get(string $section, ?string $cle = null): mixed
    {
        $section = self::$valeurs[$section] ?? [];
        return $cle === null ? $section : ($section[$cle] ?? null);
    }
}
