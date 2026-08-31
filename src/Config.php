<?php
declare(strict_types=1);

/** Accès à la configuration de l'application. */
final class Config
{
    private static ?array $valeurs = null;

    public static function charger(string $fichier): void
    {
        self::$valeurs = require $fichier;
    }

    public static function get(string $section, ?string $cle = null): mixed
    {
        $section = self::$valeurs[$section] ?? [];
        return $cle === null ? $section : ($section[$cle] ?? null);
    }
}
