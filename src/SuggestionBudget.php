<?php
declare(strict_types=1);

/**
 * Propose un budget mensuel par catégorie, à partir des dépenses déjà saisies.
 *
 * Le principe : on regarde ce qui a réellement été dépensé les mois écoulés, on
 * en prend la médiane — moins sensible qu'une moyenne à un mois exceptionnel —
 * et on l'assortit d'une marge tirée de la variabilité observée. Une catégorie
 * régulière donne une fourchette serrée, une catégorie en dents de scie une
 * fourchette large. C'est un ordre de grandeur, pas une consigne.
 */
final class SuggestionBudget
{
    /** En dessous, l'historique est trop court pour proposer quoi que ce soit. */
    public const MOIS_MINIMUM = 3;

    /** En dessous, la proposition est affichée mais signalée comme fragile. */
    public const MOIS_CONFIANCE = 5;

    /** Profondeur d'historique prise en compte. */
    public const MOIS_OBSERVES = 6;

    /**
     * @return array{
     *   mois_disponibles:int, suffisant:bool, fiable:bool,
     *   periode:?string, categories:array, total_bas:float, total_haut:float, total_conseille:float
     * }
     */
    public static function calculer(int $userId, ?DateTimeImmutable $aujourdhui = null): array
    {
        $aujourdhui ??= new DateTimeImmutable('today');

        // On s'arrête au dernier mois complet : le mois en cours fausserait tout.
        $dernier = $aujourdhui->modify('first day of this month')->modify('-1 month');
        $premier = $dernier->modify('-' . (self::MOIS_OBSERVES - 1) . ' months');

        $lignes = Database::all(
            "SELECT o.categorie_id,
                    DATE_FORMAT(o.date_operation, '%Y-%m') AS periode,
                    SUM(o.montant) AS total
             FROM operations o
             WHERE o.user_id = ? AND o.sens = 'depense' AND o.categorie_id IS NOT NULL
               AND o.date_operation >= ? AND o.date_operation <= ?
             GROUP BY o.categorie_id, periode",
            [$userId, $premier->format('Y-m-01'), $dernier->format('Y-m-t')]
        );

        // Mois de la fenêtre, du plus ancien au plus récent.
        $fenetre = [];
        for ($i = self::MOIS_OBSERVES - 1; $i >= 0; $i--) {
            $fenetre[] = $dernier->modify("-$i months")->format('Y-m');
        }

        $parCategorie = [];
        foreach ($lignes as $l) {
            $parCategorie[(int) $l['categorie_id']][$l['periode']] = (float) $l['total'];
        }

        $moisAvecDonnees = array_values(array_unique(array_column($lignes, 'periode')));
        $moisDisponibles = count($moisAvecDonnees);

        $categories = Database::all(
            "SELECT id, nom, icone, couleur, plafond_mensuel
             FROM categories_budget
             WHERE user_id = ? AND sens = 'depense'
             ORDER BY position, nom",
            [$userId]
        );

        $resultat = [];
        $totalBas = 0.0;
        $totalHaut = 0.0;
        $totalConseille = 0.0;

        foreach ($categories as $categorie) {
            $releves = $parCategorie[(int) $categorie['id']] ?? [];
            if ($releves === []) {
                continue;
            }

            // On part du premier mois où la catégorie a servi : avant, elle
            // n'existait pas dans les habitudes, et compter des zéros fausserait.
            $debut = min(array_keys($releves));
            $montants = [];
            foreach ($fenetre as $mois) {
                if ($mois >= $debut) {
                    $montants[] = $releves[$mois] ?? 0.0;
                }
            }
            if (count($montants) < self::MOIS_MINIMUM) {
                continue;
            }

            $mediane = self::mediane($montants);
            $marge = self::marge($montants, $mediane);

            $bas  = max(0.0, $mediane - $marge);
            $haut = $mediane + $marge;

            $resultat[] = [
                'id'        => (int) $categorie['id'],
                'nom'       => $categorie['nom'],
                'icone'     => $categorie['icone'],
                'couleur'   => $categorie['couleur'],
                'plafond'   => $categorie['plafond_mensuel'] === null ? null : (float) $categorie['plafond_mensuel'],
                'mois'      => count($montants),
                'montants'  => $montants,
                'mediane'   => $mediane,
                'bas'       => self::arrondir($bas, false),
                'haut'      => self::arrondir($haut, true),
                'conseille' => self::arrondir($haut, true),
                'mini'      => min($montants),
                'maxi'      => max($montants),
                'regulier'  => $mediane > 0 && ($marge / $mediane) < 0.25,
            ];

            $totalBas += self::arrondir($bas, false);
            $totalHaut += self::arrondir($haut, true);
            $totalConseille += self::arrondir($haut, true);
        }

        return [
            'mois_disponibles' => $moisDisponibles,
            'suffisant'        => $moisDisponibles >= self::MOIS_MINIMUM && $resultat !== [],
            'fiable'           => $moisDisponibles >= self::MOIS_CONFIANCE,
            'periode'          => $moisDisponibles > 0
                ? self::intitule(min($moisAvecDonnees)) . ' à ' . self::intitule(max($moisAvecDonnees))
                : null,
            'categories'       => $resultat,
            'total_bas'        => $totalBas,
            'total_haut'       => $totalHaut,
            'total_conseille'  => $totalConseille,
        ];
    }

    /** Valeur centrale, insensible à un mois exceptionnel. */
    private static function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = count($valeurs);
        $milieu = intdiv($n, 2);
        return $n % 2 === 1
            ? $valeurs[$milieu]
            : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }

    /**
     * Marge tirée de l'écart moyen à la médiane : elle suit la régularité réelle
     * de la catégorie plutôt qu'un pourcentage décidé à l'avance.
     */
    private static function marge(array $montants, float $mediane): float
    {
        $ecarts = array_map(static fn (float $m): float => abs($m - $mediane), $montants);
        $moyenne = array_sum($ecarts) / count($ecarts);

        // Une catégorie parfaitement stable garderait une marge nulle, ce qui
        // donnerait une valeur fixe : on assure un minimum de 10 %.
        return max($moyenne, $mediane * 0.10);
    }

    /** Arrondit à un pas lisible : au franc près en dessous de 20 €, sinon à 5 €. */
    private static function arrondir(float $valeur, bool $versLeHaut): float
    {
        $pas = $valeur < 20 ? 1.0 : 5.0;
        return $versLeHaut
            ? ceil($valeur / $pas) * $pas
            : floor($valeur / $pas) * $pas;
    }

    private static function intitule(string $periode): string
    {
        return strtolower(nom_mois((int) substr($periode, 5, 2))) . ' ' . substr($periode, 0, 4);
    }
}
