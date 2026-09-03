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
     * @param array $defauts       réglages utilisés faute de fichier local
     * @param string|null $fichier chemin du fichier local, prioritaire
     */
    public static function charger(array $defauts, ?string $fichier = null): void
    {
        self::$valeurs = $fichier !== null && is_file($fichier)
            ? require $fichier
            : $defauts;
    }

    public static function get(string $section, ?string $cle = null): mixed
    {
        $section = self::$valeurs[$section] ?? [];
        return $cle === null ? $section : ($section[$cle] ?? null);
    }
}
