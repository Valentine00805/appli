<?php
declare(strict_types=1);

/**
 * La journée vue comme une grille d'heures, et non comme une liste.
 *
 * Une liste dit ce qu'il y a ; une grille dit quand. Elle montre les trous —
 * deux heures libres entre deux cours se voient d'un coup d'œil, alors qu'il
 * faut soustraire deux horaires pour les déduire d'une liste — et elle montre
 * les chevauchements, qui sont justement ce qu'on cherche à repérer.
 *
 * Ici ne se trouve que le calcul : quelles heures montrer, où poser chaque
 * évènement, comment répartir ceux qui se recouvrent. Le dessin est affaire de
 * CSS, et la vue n'a qu'à traduire des nombres en styles.
 */
final class PlanningJour
{
    /**
     * Les heures montrées quand rien n'oblige à en montrer d'autres.
     *
     * Sept heures à vingt-et-une : la journée où l'on pose des rendez-vous.
     * Afficher les vingt-quatre donnerait une colonne vide sur un tiers de sa
     * hauteur, et l'essentiel serait à faire défiler.
     */
    private const DEBUT_PAR_DEFAUT = 7;
    private const FIN_PAR_DEFAUT = 21;

    /**
     * La durée minimale qu'occupe un évènement, en minutes.
     *
     * Un rendez-vous d'un quart d'heure ne doit pas se réduire à un trait où
     * son titre ne tiendrait pas. Il déborde un peu sur l'heure suivante :
     * c'est le prix d'un libellé lisible, et l'horaire exact reste écrit
     * dedans.
     */
    private const HAUTEUR_MINIMALE = 30;

    /**
     * Dispose une journée.
     *
     * @param array<int, array> $evenements  ceux du jour, déjà triés par début
     * @return array{journee: array<int, array>, debut: int, fin: int,
     *               blocs: array<int, array{evt: array, haut: float, hauteur: float,
     *                                       colonne: int, colonnes: int, court: bool}>,
     *               maintenant: ?float}
     */
    public static function disposer(array $evenements, DateTimeImmutable $jour): array
    {
        $minuit = $jour->setTime(0, 0);
        $minuitSuivant = $minuit->modify('+1 day');

        $journee = [];
        $poses = [];

        foreach ($evenements as $evt) {
            $debut = new DateTimeImmutable((string) $evt['debut']);
            $fin = new DateTimeImmutable((string) $evt['fin']);

            /*
             * Ce qui couvre la journée entière monte dans le bandeau : une
             * journée entière, mais aussi ce qui a commencé avant et finit
             * après — un séjour, une période d'alternance. L'étaler sur toute
             * la hauteur de la grille masquerait tout le reste.
             */
            if ((int) ($evt['journee_entiere'] ?? 0) === 1
                || ($debut < $minuit && $fin >= $minuitSuivant)) {
                $journee[] = $evt;
                continue;
            }

            // Ce qui déborde du jour est coupé à ses bornes : on ne dessine
            // que la part qui s'y trouve.
            $duJour = max($debut, $minuit);
            $finDuJour = min($fin, $minuitSuivant);
            if ($finDuJour <= $duJour) {
                $journee[] = $evt;
                continue;
            }

            $poses[] = [
                'evt'    => $evt,
                'depart' => self::minutes($minuit, $duJour),
                'arrive' => self::minutes($minuit, $finDuJour),
            ];
        }

        [$debutH, $finH] = self::plage($poses);

        return [
            'journee'    => $journee,
            'debut'      => $debutH,
            'fin'        => $finH,
            'blocs'      => self::empiler($poses, $debutH),
            'maintenant' => self::maintenant($jour, $debutH, $finH),
        ];
    }

    /** Les minutes écoulées entre deux instants. */
    private static function minutes(DateTimeImmutable $de, DateTimeImmutable $a): float
    {
        return ($a->getTimestamp() - $de->getTimestamp()) / 60;
    }

    /**
     * Les heures à montrer : celles d'usage, élargies à ce qu'il y a.
     *
     * @param array<int, array{depart: float, arrive: float}> $poses
     * @return array{0: int, 1: int}
     */
    private static function plage(array $poses): array
    {
        $debut = self::DEBUT_PAR_DEFAUT;
        $fin = self::FIN_PAR_DEFAUT;

        foreach ($poses as $pose) {
            $debut = min($debut, (int) floor($pose['depart'] / 60));
            $fin = max($fin, (int) ceil($pose['arrive'] / 60));
        }

        return [max(0, $debut), min(24, max($fin, $debut + 1))];
    }

    /**
     * Répartit en colonnes ce qui se chevauche.
     *
     * Deux rendez-vous à la même heure ne peuvent pas occuper la même place.
     * On les groupe par grappes — un évènement qui commence après la fin de
     * tous les précédents en ouvre une nouvelle — puis, dans chaque grappe,
     * chacun prend la première colonne libre. La largeur se partage à parts
     * égales : c'est ce que font les agendas qu'on a l'habitude de lire.
     *
     * @param array<int, array{evt: array, depart: float, arrive: float}> $poses
     * @return array<int, array>
     */
    private static function empiler(array $poses, int $debutH): array
    {
        usort($poses, static function (array $a, array $b): int {
            return $a['depart'] <=> $b['depart'] ?: $b['arrive'] <=> $a['arrive'];
        });

        $blocs = [];
        $grappe = [];
        $finsDeColonnes = [];
        $finDeGrappe = -1.0;

        $fermer = static function () use (&$blocs, &$grappe, &$finsDeColonnes): void {
            $largeur = max(1, count($finsDeColonnes));
            foreach ($grappe as $bloc) {
                $bloc['colonnes'] = $largeur;
                $blocs[] = $bloc;
            }
            $grappe = [];
            $finsDeColonnes = [];
        };

        foreach ($poses as $pose) {
            if ($grappe !== [] && $pose['depart'] >= $finDeGrappe) {
                $fermer();
                $finDeGrappe = -1.0;
            }

            $colonne = 0;
            while (isset($finsDeColonnes[$colonne]) && $finsDeColonnes[$colonne] > $pose['depart']) {
                $colonne++;
            }
            $finsDeColonnes[$colonne] = $pose['arrive'];
            $finDeGrappe = max($finDeGrappe, $pose['arrive']);

            $hauteur = max(self::HAUTEUR_MINIMALE, $pose['arrive'] - $pose['depart']);

            $grappe[] = [
                'evt'      => $pose['evt'],
                // Trop court pour empiler l'heure, le titre et le lieu :
                // la vue les mettra sur une ligne plutôt que de les rogner.
                'court'    => ($pose['arrive'] - $pose['depart']) < 45,
                'haut'     => $pose['depart'] - $debutH * 60,
                'hauteur'  => $hauteur,
                'colonne'  => $colonne,
                'colonnes' => 1,
            ];
        }
        $fermer();

        return $blocs;
    }

    /**
     * Où poser le trait de l'heure qu'il est, ou null s'il n'a rien à y faire.
     *
     * Seulement aujourd'hui, et seulement si l'heure tombe dans les heures
     * montrées : un trait posé sur le bord dirait « il est plus tôt que tout
     * ceci », ce qui est faux et se lit mal.
     */
    private static function maintenant(DateTimeImmutable $jour, int $debutH, int $finH): ?float
    {
        $maintenant = new DateTimeImmutable('now');
        if ($maintenant->format('Y-m-d') !== $jour->format('Y-m-d')) {
            return null;
        }

        $minutes = (int) $maintenant->format('G') * 60 + (int) $maintenant->format('i');
        if ($minutes < $debutH * 60 || $minutes > $finH * 60) {
            return null;
        }

        return $minutes - $debutH * 60;
    }
}
