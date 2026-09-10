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
     * La page demandée en fenêtre ?
     *
     * Le script ajoute « fenetre=1 » à l'adresse pour n'avoir que le contenu,
     * sans le gabarit qu'il a déjà autour. Sans lui, la page répond entière.
     */
    public static function enFenetre(): bool
    {
        return ($_GET['fenetre'] ?? '') === '1';
    }

    /**
     * Rend une vue seule, pour que le script la pose dans une fenêtre.
     *
     * L'adresse courante est débarrassée de « fenetre=1 » le temps du rendu.
     * Les formulaires y glissent un champ « retour » qui vaut l'adresse
     * d'où l'on vient : sans ce nettoyage, valider l'un d'eux renverrait sur
     * le fragment, et l'on verrait une carte nue sur fond blanc, sans bandeau
     * ni menu.
     */
    public static function fragment(string $vue, array $donnees = []): void
    {
        $avant = $_SERVER['REQUEST_URI'] ?? '';
        $_SERVER['REQUEST_URI'] = (string) preg_replace(
            '/([?&])fenetre=1(&|$)/', '$1', (string) $avant);
        $_SERVER['REQUEST_URI'] = rtrim((string) $_SERVER['REQUEST_URI'], '?&');

        try {
            echo self::rendre($vue, $donnees + ['dansUneFenetre' => true]);
        } finally {
            $_SERVER['REQUEST_URI'] = $avant;
        }
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
