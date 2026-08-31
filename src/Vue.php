<?php
declare(strict_types=1);

/** Rendu des vues avec le gabarit commun. */
final class Vue
{
    /** Affiche une vue dans le gabarit principal. */
    public static function afficher(string $vue, array $donnees = [], string $titre = ''): void
    {
        $contenu = self::rendre($vue, $donnees);
        $utilisateur = Auth::utilisateur();
        $flashs = Session::flashs();
        $titrePage = $titre !== '' ? $titre . ' · ' . Config::get('app', 'nom') : (string) Config::get('app', 'nom');
        require dirname(__DIR__) . '/views/layout.php';
    }

    /** Affiche une vue sans gabarit (pages de connexion, fragments). */
    public static function afficherNu(string $vue, array $donnees = [], string $titre = ''): void
    {
        $contenu = self::rendre($vue, $donnees);
        $utilisateur = Auth::utilisateur();
        $flashs = Session::flashs();
        $titrePage = $titre !== '' ? $titre . ' · ' . Config::get('app', 'nom') : (string) Config::get('app', 'nom');
        require dirname(__DIR__) . '/views/layout_nu.php';
    }

    /**
     * Capture le rendu d'un fichier de vue.
     * Les variables locales sont préfixées par « __ » pour ne jamais entrer en
     * collision avec les clés extraites de $donnees (« vue », « titre »…).
     */
    public static function rendre(string $__vue, array $__donnees = []): string
    {
        $__chemin = dirname(__DIR__) . '/views/' . $__vue . '.php';
        if (!is_file($__chemin)) {
            throw new RuntimeException('Vue introuvable : ' . $__vue);
        }

        extract($__donnees, EXTR_OVERWRITE);
        unset($__donnees);

        ob_start();
        require $__chemin;
        return (string) ob_get_clean();
    }
}
