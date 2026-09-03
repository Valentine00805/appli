<?php
declare(strict_types=1);

/**
 * La requête HTTP en cours : où l'application est installée, quelle page est
 * demandée, et avec quelle méthode.
 *
 * L'application peut vivre à la racine d'un domaine comme dans un sous-dossier.
 * C'est ici qu'on démêle les deux, une fois pour toutes, plutôt que de laisser
 * ce calcul traîner au milieu du fichier d'amorçage.
 */
final class Requete
{
    /** Le sous-dossier d'installation, tel quel : '' à la racine, '/appli' sinon. */
    public static function baseBrute(): string
    {
        $dossier = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

        return $dossier === '.' ? '' : $dossier;
    }

    /** Le même chemin, prêt à entrer dans une URL. */
    public static function base(): string
    {
        $dossier = self::baseBrute();
        if ($dossier === '') {
            return '';
        }

        return '/' . implode('/', array_map('rawurlencode', explode('/', trim($dossier, '/'))));
    }

    /** La page demandée, débarrassée du sous-dossier d'installation et des slashs. */
    public static function route(): string
    {
        $chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $chemin = rawurldecode($chemin);

        $base = self::baseBrute();
        if ($base !== '' && str_starts_with($chemin, $base)) {
            $chemin = substr($chemin, strlen($base));
        }

        return trim($chemin, '/');
    }

    /**
     * HEAD est traité comme GET : navigateurs et outils s'en servent pour
     * vérifier qu'une page existe sans en télécharger le contenu.
     */
    public static function methode(): string
    {
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return $methode === 'HEAD' ? 'GET' : $methode;
    }
}
