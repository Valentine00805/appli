<?php
declare(strict_types=1);

/** Accès à la configuration de l'application. */
final class Config
{
    private static ?array $valeurs = null;

    /**
     * Charge la configuration : le fichier local s'il existe, le fichier de
     * secours sinon.
     *
     * Le fichier local n'est pas dans le dépôt, puisqu'il porte les identifiants
     * de la base. Il peut donc manquer — sur une installation neuve, ou parce
     * qu'un antivirus l'a emporté, ce qui est arrivé ici. Plutôt que de tomber
     * en panne, l'application repart alors sur l'exemple, qui suffit en local.
     * Sur un serveur il ne suffira pas, et l'échec de connexion le dira.
     */
    public static function charger(string $fichier, ?string $secours = null): void
    {
        $choisi = is_file($fichier) ? $fichier : $secours;

        if ($choisi === null || !is_file($choisi)) {
            throw new RuntimeException(
                'Configuration introuvable : ' . $fichier . '. '
                . 'La recréer en recopiant config/reglages.php.'
            );
        }

        self::$valeurs = require $choisi;
    }

    public static function get(string $section, ?string $cle = null): mixed
    {
        $section = self::$valeurs[$section] ?? [];
        return $cle === null ? $section : ($section[$cle] ?? null);
    }
}
