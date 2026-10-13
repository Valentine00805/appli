<?php
declare(strict_types=1);

/**
 * Le récapitulatif d'un travail de groupe : qui a fait quoi, combien de tâches par personne, et le « burn down » — les tâches qu'il restait à
 * faire, jour après jour, de la création du projet à sa fin.
 *
 * Ici, que du calcul (aucune base, aucun texte) : la mise en pages est celle de RecapProjetPdf. Les dates sont des jours (« 2026-10-13 ») :
 *
 *   — le projet commence le jour de sa création ;
 *   — il est terminé quand toutes ses tâches (une au moins) sont faites, et il s'arrête le jour de la dernière ; sinon il court
 *     jusqu'à aujourd'hui ;
 *   — « il restait N tâches à la fin du jour J » : celles créées au plus tard ce jour-là et pas encore faites à la fin du jour J.
 *     Une tâche faite sans date (avant que les dates soient gardées) est comptée faite le jour de sa création.
 */
final class RecapProjet
{
    /** Au-delà, la courbe est échantillonnée : un point tous les n jours. */
    public const POINTS_MAX = 400;

    /**
     * @param array{created_at?: ?string} $projet
     * @param list<array{id: int|string, nom_affiche: string, user_id: mixed}> $membres  ceux qui sont là (membres actifs)
     * @param list<array{id: int|string, titre: string, membre_id: mixed, statut: string, created_at: ?string, fait_le: ?string, echeance?: ?string}> $taches
     * @param string $aujourdhui un jour (« Y-m-d »)
     * @param array<int, list<array{texte: string, fait: bool, fait_le: ?string, par_user_id: ?int}>> $points les points à cocher de chaque tâche (par tâche)
     * @param array<int, string> $phases le nom des colonnes en plus du tableau, par identifiant
     *
     * @return array{
     *     debut: string, fin: string, termine: bool, jours: int, total: int, faites: int, part: int,
     *     personnes: list<array{id: int, nom: string, sans_compte: bool, faites: int, total: int, points_coches: int, taches: list<array{titre: string, statut: string, fait_le: ?string, points: ?array{0: int, 1: int}}>}>,
     *     sans_responsable: ?array{faites: int, total: int, taches: list<array{titre: string, statut: string, fait_le: ?string, points: ?array{0: int, 1: int}}>},
     *     courbe: list<array{jour: string, indice: int, restant: int, ajoutees: int, ideal: float}>,
     *     axe: int, maximum: int
     * }
     */
    public static function calculer(array $projet, array $membres, array $taches, string $aujourdhui, array $points = [], array $phases = []): array
    {
        $jour = static fn (mixed $dateHeure): ?string => $dateHeure === null || $dateHeure === '' ? null : substr((string) $dateHeure, 0, 10);
        $debut = $jour($projet['created_at'] ?? null) ?? $aujourdhui;

        $total = count($taches);
        $faites = 0;
        $creee = [];
        $faiteLe = [];
        $dernier = $debut;
        foreach ($taches as $i => $t) {
            $c = max($jour($t['created_at'] ?? null) ?? $debut, $debut);   // jamais avant le début du projet
            $creee[$i] = $c;
            $dernier = max($dernier, $c);
            if ($t['statut'] === 'fait') {
                $faites++;
                $f = max($jour($t['fait_le'] ?? null) ?? $c, $c);
                $faiteLe[$i] = $f;
                $dernier = max($dernier, $f);
            }
        }
        $termine = $total > 0 && $faites === $total;
        $fin = $termine ? $dernier : max($aujourdhui, $dernier);

        $debutJour = new DateTimeImmutable($debut);
        $nbJours = (int) $debutJour->diff(new DateTimeImmutable($fin))->days;
        $axe = max(1, $nbJours);

        // Un point par jour — ou tous les n jours, quand le projet est très long.
        $pas = max(1, (int) ceil($axe / self::POINTS_MAX));
        $indices = [];
        for ($d = 0; $d <= $axe; $d += $pas) {
            $indices[] = $d;
        }
        if (end($indices) !== $axe) {
            $indices[] = $axe;
        }

        $courbe = [];
        $precedent = null;
        foreach ($indices as $d) {
            $j = $debutJour->modify('+' . $d . ' days')->format('Y-m-d');
            $restant = 0;
            $ajoutees = 0;
            foreach ($creee as $i => $c) {
                if ($c <= $j && (!isset($faiteLe[$i]) || $faiteLe[$i] > $j)) {
                    $restant++;
                }
                if ($c <= $j && ($precedent === null || $c > $precedent)) {
                    $ajoutees++;
                }
            }
            $courbe[] = ['jour' => $j, 'indice' => $d, 'restant' => $restant, 'ajoutees' => $ajoutees, 'ideal' => round($total * (1 - $d / $axe), 3)];
            $precedent = $j;
        }

        // Qui a fait quoi : les tâches rangées par la personne qui en est chargée.
        $parMembre = [];
        $sans = [];
        $cochesParCompte = [];   // les points cochés, par compte
        $ids = [];
        foreach ($membres as $m) {
            $ids[(int) $m['id']] = true;
        }
        foreach ($taches as $i => $t) {
            $deLaTache = $points[(int) $t['id']] ?? [];
            $ligne = ['titre' => (string) $t['titre'], 'statut' => (string) $t['statut'], 'fait_le' => $faiteLe[$i] ?? null,
                       'phase' => $t['statut'] !== 'fait' && ($t['colonne_id'] ?? null) !== null ? ($phases[(int) $t['colonne_id']] ?? null) : null,
                       'points' => $deLaTache === [] ? null : [count(array_filter($deLaTache, static fn (array $p): bool => $p['fait'])), count($deLaTache)]];
            foreach ($deLaTache as $p) {
                if ($p['fait'] && $p['par_user_id'] !== null) {
                    $cochesParCompte[$p['par_user_id']] = ($cochesParCompte[$p['par_user_id']] ?? 0) + 1;
                }
            }
            if ($t['membre_id'] !== null && isset($ids[(int) $t['membre_id']])) {
                $parMembre[(int) $t['membre_id']][] = $ligne;
            } else {
                $sans[] = $ligne;
            }
        }
        $rang = static function (array $lignes): array {
            // Les faites d'abord, de la plus ancienne à la plus récente ; puis ce qui est en cours ; puis ce qui reste à faire.
            $ordre = ['fait' => 0, 'en_cours' => 1, 'a_faire' => 2];
            usort($lignes, static fn (array $a, array $b): int => [$ordre[$a['statut']] ?? 3, $a['fait_le'] ?? '', $a['titre']] <=> [$ordre[$b['statut']] ?? 3, $b['fait_le'] ?? '', $b['titre']]);

            return $lignes;
        };
        $compter = static fn (array $lignes): int => count(array_filter($lignes, static fn (array $l): bool => $l['statut'] === 'fait'));

        $personnes = [];
        foreach ($membres as $m) {
            $lignes = $rang($parMembre[(int) $m['id']] ?? []);
            $personnes[] = ['id' => (int) $m['id'], 'nom' => (string) $m['nom_affiche'], 'sans_compte' => $m['user_id'] === null,
                            'faites' => $compter($lignes), 'total' => count($lignes), 'taches' => $lignes,
                            'points_coches' => $m['user_id'] === null ? 0 : ($cochesParCompte[(int) $m['user_id']] ?? 0)];
        }
        usort($personnes, static fn (array $a, array $b): int => [$b['faites'], $b['total'], $a['nom']] <=> [$a['faites'], $a['total'], $b['nom']]);

        $sansResponsable = null;
        if ($sans !== []) {
            $sans = $rang($sans);
            $sansResponsable = ['faites' => $compter($sans), 'total' => count($sans), 'taches' => $sans];
        }

        $maximum = max(1, $total, ...array_map(static fn (array $p): int => max($p['restant'], $p['ajoutees']), $courbe));

        return [
            'debut' => $debut, 'fin' => $fin, 'termine' => $termine, 'jours' => $nbJours + 1,
            'total' => $total, 'faites' => $faites, 'part' => $total === 0 ? 0 : (int) round($faites * 100 / $total),
            'personnes' => $personnes, 'sans_responsable' => $sansResponsable,
            'courbe' => $courbe, 'axe' => $axe, 'maximum' => $maximum,
        ];
    }
}
