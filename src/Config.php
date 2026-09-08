<?php
declare(strict_types=1);

/** Accès à la configuration de l'application. */
final class Config
{
    private static ?array $valeurs = null;

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
            $locaux = [];
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
