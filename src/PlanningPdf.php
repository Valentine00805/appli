<?php
declare(strict_types=1);

/**
 * Un planning d'alternance en PDF, relu case par case.
 *
 * Ces plannings disent rarement en toutes lettres où l'on est : c'est la
 * couleur de la case qui le dit, et une légende, quelque part, explique les
 * couleurs. On rend donc ici ce qu'on sait lire — quel jour porte quelle
 * couleur —, et c'est la personne qui dit ensuite ce que chaque couleur veut
 * dire. Deviner à sa place ferait un planning faux, et un planning faux est
 * pire que pas de planning du tout.
 *
 * Deux façons de retrouver les dates, essayées dans cet ordre :
 *   — le tableau écrit ses dates en clair (« 05/10/2026 ») : on les lit ;
 *   — le tableau est une grille, les mois d'un côté, les numéros de jour de
 *     l'autre : on recoupe la position de chaque case avec les en-têtes.
 */
final class PlanningPdf
{
    /** Au-delà, ce n'est plus un planning : on refuse plutôt que de ramer. */
    private const JOURS_MAX = 800;

    private const MOIS = [
        'janvier' => 1, 'janv' => 1, 'jan' => 1,
        'fevrier' => 2, 'février' => 2, 'fevr' => 2, 'fev' => 2, 'fév' => 2,
        'mars' => 3, 'mar' => 3,
        'avril' => 4, 'avr' => 4,
        'mai' => 5,
        'juin' => 6,
        'juillet' => 7, 'juil' => 7, 'jui' => 7,
        'aout' => 8, 'août' => 8,
        'septembre' => 9, 'sept' => 9, 'sep' => 9,
        'octobre' => 10, 'oct' => 10,
        'novembre' => 11, 'nov' => 11,
        'decembre' => 12, 'décembre' => 12, 'dec' => 12, 'déc' => 12,
    ];

    /**
     * Ce que le PDF laisse lire.
     *
     * @return array{jours: array<string, string>, couleurs: array<string, int>,
     *               methode: string, pages: int}
     */
    public static function lire(string $chemin, ?int $anneeDite = null): array
    {
        $vide = ['jours' => [], 'couleurs' => [], 'methode' => 'rien', 'pages' => 0];

        $lu = TextePdf::elements($chemin);
        if ($lu === null) {
            return $vide;
        }

        $jours = [];
        $methode = 'rien';
        foreach ($lu['pages'] as $page) {
            $parDates = self::parDatesEcrites($page);
            $trouves = $parDates !== [] ? $parDates : self::parGrille($page, $anneeDite);
            if ($trouves !== []) {
                $methode = $parDates !== [] ? 'dates' : 'grille';
                // Une même date lue deux fois garde la première couleur vue.
                $jours += $trouves;
            }
            if (count($jours) > self::JOURS_MAX) {
                break;
            }
        }
        ksort($jours);
        $jours = array_slice($jours, 0, self::JOURS_MAX, true);

        $couleurs = [];
        foreach ($jours as $couleur) {
            $couleurs[$couleur] = ($couleurs[$couleur] ?? 0) + 1;
        }
        arsort($couleurs);

        return ['jours' => $jours, 'couleurs' => $couleurs, 'methode' => $methode,
                'pages' => count($lu['pages'])];
    }

    /**
     * Les périodes qu'on tire des jours colorés, une fois dit ce que chaque
     * couleur veut dire. Les jours qui se suivent et disent la même chose ne
     * font qu'une période ; un week-end au milieu ne la coupe pas.
     *
     * @param array<string, string> $jours    date => couleur
     * @param array<string, string> $legende  couleur => lieu (ou '' pour laisser de côté)
     * @return list<array{lieu: string, debut: string, fin: string}>
     */
    public static function periodes(array $jours, array $legende): array
    {
        ksort($jours);
        $periodes = [];
        $encours = null;

        foreach ($jours as $jour => $couleur) {
            $lieu = $legende[$couleur] ?? '';
            if ($lieu === '' || !isset(Alternance::LIEUX[$lieu]) || Alternance::dateValide($jour) === null) {
                continue;
            }
            if ($encours !== null && $encours['lieu'] === $lieu && self::seSuivent($encours['fin'], $jour)) {
                $encours['fin'] = $jour;
                continue;
            }
            if ($encours !== null) {
                $periodes[] = $encours;
            }
            $encours = ['lieu' => $lieu, 'debut' => $jour, 'fin' => $jour];
        }
        if ($encours !== null) {
            $periodes[] = $encours;
        }

        return $periodes;
    }

    /** Deux jours se suivent si rien d'ouvré ne les sépare. */
    private static function seSuivent(string $fin, string $suivant): bool
    {
        $jour = new DateTimeImmutable($fin);
        for ($pas = 0; $pas < 4; $pas++) {
            $jour = $jour->modify('+1 day');
            if ($jour->format('Y-m-d') === $suivant) {
                return true;
            }
            if ((int) $jour->format('N') < 6) {
                return false;   // un jour ouvré sauté : la période s'arrête là
            }
        }

        return false;
    }

    // --- Retrouver les dates ---------------------------------------------------

    /**
     * Le tableau écrit ses dates : « 05/10/2026 », « 2026-10-05 ».
     *
     * @return array<string, string>
     */
    private static function parDatesEcrites(array $page): array
    {
        $jours = [];
        foreach ($page['textes'] as $texte) {
            $date = self::dateEcrite((string) $texte['texte']);
            if ($date === null) {
                continue;
            }
            $jours[$date] = self::couleurSous($page['aplats'], (float) $texte['x'], (float) $texte['y']);
        }

        return $jours;
    }

    private static function dateEcrite(string $texte): ?string
    {
        $texte = trim($texte);
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $texte, $m) === 1) {
            $annee = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            return Alternance::dateValide(sprintf('%04d-%02d-%02d', $annee, (int) $m[2], (int) $m[1]));
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $texte) === 1) {
            return Alternance::dateValide($texte);
        }

        return null;
    }

    /**
     * La grille : les mois d'un côté, les numéros de jour de l'autre. On prend
     * l'orientation qui donne le plus de dates — un planning se lit aussi bien
     * mois en ligne que mois en colonne.
     *
     * @return array<string, string>
     */
    private static function parGrille(array $page, ?int $anneeDite): array
    {
        $mois = [];
        $numeros = [];
        foreach ($page['textes'] as $texte) {
            $valeur = trim((string) $texte['texte']);
            $numero = self::moisEcrit($valeur);
            if ($numero !== null) {
                $mois[] = ['x' => (float) $texte['x'], 'y' => (float) $texte['y'], 'mois' => $numero,
                           'annee' => self::anneeDans($valeur)];
                continue;
            }
            if (preg_match('/^(\d{1,2})$/', $valeur, $m) === 1 && (int) $m[1] >= 1 && (int) $m[1] <= 31) {
                $numeros[] = ['x' => (float) $texte['x'], 'y' => (float) $texte['y'], 'jour' => (int) $m[1]];
            }
        }
        if ($mois === [] || $numeros === []) {
            return [];
        }

        $annees = self::anneesDeLaPage($page, $anneeDite);
        $enLignes = self::croiser($page, $mois, $numeros, $annees, true);
        $enColonnes = self::croiser($page, $mois, $numeros, $annees, false);

        return count($enLignes) >= count($enColonnes) ? $enLignes : $enColonnes;
    }

    /**
     * Chaque case colorée reçoit le mois de sa ligne et le jour de sa colonne
     * (ou l'inverse). Une case sans mois ni jour en face est laissée de côté.
     *
     * @return array<string, string>
     */
    private static function croiser(array $page, array $mois, array $numeros, array $annees, bool $moisEnLignes): array
    {
        $jours = [];
        foreach ($page['aplats'] as $aplat) {
            // Une case de tableau, pas le fond de la page ni un bandeau.
            if ($aplat['l'] > 120 || $aplat['h'] > 120) {
                continue;
            }
            $cx = $aplat['x'] + $aplat['l'] / 2;
            $cy = $aplat['y'] + $aplat['h'] / 2;

            $leMois = self::plusProche($mois, $moisEnLignes ? 'y' : 'x', $moisEnLignes ? $cy : $cx,
                max($aplat['h'], $aplat['l']) / 2 + 2);
            $leJour = self::plusProche($numeros, $moisEnLignes ? 'x' : 'y', $moisEnLignes ? $cx : $cy,
                max($aplat['h'], $aplat['l']) / 2 + 2);
            if ($leMois === null || $leJour === null) {
                continue;
            }

            $annee = $leMois['annee'] ?? self::anneeDuMois((int) $leMois['mois'], $annees);
            $date = Alternance::dateValide(sprintf('%04d-%02d-%02d', $annee, (int) $leMois['mois'], (int) $leJour['jour']));
            if ($date !== null) {
                $jours[$date] = (string) $aplat['couleur'];
            }
        }

        return $jours;
    }

    /** L'en-tête le plus proche sur un axe, s'il est assez près. */
    private static function plusProche(array $reperes, string $axe, float $valeur, float $tolerance): ?array
    {
        $trouve = null;
        $ecart = $tolerance;
        foreach ($reperes as $repere) {
            $distance = abs((float) $repere[$axe] - $valeur);
            if ($distance <= $ecart) {
                $ecart = $distance;
                $trouve = $repere;
            }
        }

        return $trouve;
    }

    private static function moisEcrit(string $texte): ?int
    {
        $propre = mb_strtolower(trim($texte));
        $propre = (string) preg_replace('/[^a-zà-ÿ]/u', '', $propre);

        return self::MOIS[$propre] ?? null;
    }

    private static function anneeDans(string $texte): ?int
    {
        return preg_match('/\b(20\d{2})\b/', $texte, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Les années du planning : celles écrites sur la page, celle qu'on nous
     * dit, ou celle où l'on est. Une année scolaire en donne deux.
     *
     * @return list<int>
     */
    private static function anneesDeLaPage(array $page, ?int $anneeDite): array
    {
        $vues = [];
        foreach ($page['textes'] as $texte) {
            if (preg_match_all('/\b(20\d{2})\b/', (string) $texte['texte'], $m) >= 1) {
                foreach ($m[1] as $annee) {
                    $vues[(int) $annee] = ($vues[(int) $annee] ?? 0) + 1;
                }
            }
        }
        if ($anneeDite !== null) {
            $vues[$anneeDite] = ($vues[$anneeDite] ?? 0) + 100;
        }
        if ($vues === []) {
            return [(int) date('Y')];
        }
        arsort($vues);
        $annees = array_slice(array_keys($vues), 0, 2);
        sort($annees);

        return $annees;
    }

    /**
     * À quelle année appartient ce mois : sur deux années, une année scolaire
     * part de septembre — les mois de septembre à décembre sont sur la
     * première, ceux de janvier à août sur la seconde.
     *
     * @param list<int> $annees
     */
    private static function anneeDuMois(int $mois, array $annees): int
    {
        if (count($annees) < 2) {
            return $annees[0] ?? (int) date('Y');
        }

        return $mois >= 9 ? $annees[0] : $annees[1];
    }

    /** La couleur de la case qui contient ce point : la plus petite qui l'entoure. */
    private static function couleurSous(array $aplats, float $x, float $y): string
    {
        $couleur = '';
        $aire = INF;
        foreach ($aplats as $aplat) {
            if ($x < $aplat['x'] - 1 || $x > $aplat['x'] + $aplat['l'] + 1
                || $y < $aplat['y'] - 3 || $y > $aplat['y'] + $aplat['h'] + 3) {
                continue;
            }
            $sienne = $aplat['l'] * $aplat['h'];
            if ($sienne < $aire) {
                $aire = $sienne;
                $couleur = (string) $aplat['couleur'];
            }
        }

        return $couleur === '' ? 'sans' : $couleur;
    }
}
