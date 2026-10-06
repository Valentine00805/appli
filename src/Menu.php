<?php
declare(strict_types=1);

/**
 * Le menu en grille de la barre (comme celui des applications de Google) : toutes les sections de l'application, en
 * tuiles, avec en tête les favoris que chacun choisit.
 *
 * Le catalogue est ici, une fois pour toutes : l'ordre des tuiles, leur icône, la phrase qui les nomme et la route qui les
 * rend « actives ». Les favoris d'un compte sont une liste de clés de ce catalogue (colonne users.menu_favoris, en JSON) ;
 * tout ce qui arrive du navigateur repasse par nettoyer(), qui ne garde que des clés connues, sans doublon. Chacun peut
 * aussi ranger toutes les tuiles à sa façon (colonne users.menu_ordre, en JSON) : voir ordre().
 */
final class Menu
{
    /**
     * Les sections, dans l'ordre où elles se rangent.
     *
     * @var array<string, array{route: string, icone: string, nom: string, prefixe: string}>
     */
    public const SECTIONS = [
        'accueil'      => ['route' => '',                      'icone' => '🏠', 'nom' => 'nav.accueil',      'prefixe' => ''],
        'calendrier'   => ['route' => 'calendrier',            'icone' => '📅', 'nom' => 'nav.calendrier',   'prefixe' => 'calendrier'],
        'cours'        => ['route' => 'cours',                 'icone' => '📘', 'nom' => 'nav.cours',        'prefixe' => 'cours'],
        'partages'     => ['route' => 'partages',              'icone' => '🤝', 'nom' => 'nav.partages',     'prefixe' => 'partages'],
        'revision'     => ['route' => 'revision',              'icone' => '📝', 'nom' => 'nav.revision',     'prefixe' => 'revision'],
        'cartes'       => ['route' => 'cartes',                'icone' => '🃏', 'nom' => 'nav.cartes',       'prefixe' => 'cartes'],
        'resumes'      => ['route' => 'resumes',               'icone' => '✨', 'nom' => 'nav.resumes',      'prefixe' => 'resumes'],
        'assistant'    => ['route' => 'assistant',             'icone' => '🤖', 'nom' => 'nav.assistant',    'prefixe' => 'assistant'],
        'taches'       => ['route' => 'taches',                'icone' => '✅', 'nom' => 'nav.taches',       'prefixe' => 'taches'],
        'tableau'      => ['route' => 'tableau',               'icone' => '📋', 'nom' => 'nav.tableau',      'prefixe' => 'tableau'],
        'alternance'   => ['route' => 'alternance',            'icone' => '💼', 'nom' => 'nav.alternance',   'prefixe' => 'alternance'],
        'groupes'      => ['route' => 'travaux',               'icone' => '👥', 'nom' => 'nav.groupes',      'prefixe' => 'travaux'],
        'budget'       => ['route' => 'budget',                'icone' => '💰', 'nom' => 'nav.budget',       'prefixe' => 'budget'],
        'organisation' => ['route' => 'organisation/matieres', 'icone' => '🗂️', 'nom' => 'nav.organisation', 'prefixe' => 'organisation'],
        'amis'         => ['route' => 'amis',                  'icone' => '💬', 'nom' => 'nav.amis',         'prefixe' => 'amis'],
        'compte'       => ['route' => 'compte',                'icone' => '⚙️', 'nom' => 'nav.compte',       'prefixe' => 'compte'],
    ];

    /** Les favoris de départ : ce qu'on ouvre le plus souvent pour réviser. */
    public const FAVORIS_PAR_DEFAUT = ['calendrier', 'cours', 'revision', 'cartes', 'resumes', 'taches'];

    /**
     * Les favoris d'un compte, dans leur ordre, d'après ce que la base garde. Une colonne vide (jamais choisi) donne les
     * favoris de départ ; une liste vide, choisie, reste vide.
     *
     * @return list<string>
     */
    public static function favoris(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return self::FAVORIS_PAR_DEFAUT;
        }
        $liste = json_decode($json, true);

        return is_array($liste) ? self::nettoyer($liste) : self::FAVORIS_PAR_DEFAUT;
    }

    /**
     * L'ordre de toutes les sections pour un compte, d'après ce que la base garde : l'ordre choisi, puis les sections qu'il
     * ne cite pas (une nouvelle section, par exemple) dans l'ordre du catalogue. Sans ordre choisi : celui du catalogue.
     *
     * @return list<string>
     */
    public static function ordre(?string $json): array
    {
        $choisi = ($json === null || trim($json) === '') ? [] : self::nettoyer(json_decode($json, true));

        return self::completer($choisi);
    }

    /**
     * Complète une liste de clés avec celles qui manquent, dans l'ordre du catalogue.
     *
     * @param list<string> $cles des clés déjà nettoyées
     * @return list<string>
     */
    public static function completer(array $cles): array
    {
        return array_merge($cles, array_values(array_diff(array_keys(self::SECTIONS), $cles)));
    }

    /**
     * Ne garde d'une liste que des clés du catalogue, une fois chacune, dans l'ordre reçu.
     *
     * @return list<string>
     */
    public static function nettoyer(mixed $liste): array
    {
        $propres = [];
        foreach (is_array($liste) ? $liste : [] as $cle) {
            if (is_string($cle) && isset(self::SECTIONS[$cle]) && !in_array($cle, $propres, true)) {
                $propres[] = $cle;
            }
        }

        return $propres;
    }
}
