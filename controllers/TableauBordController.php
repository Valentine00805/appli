<?php
declare(strict_types=1);

final class TableauBordController
{
    public function index(): void
    {
        if (!Auth::connecte()) {
            redirect('connexion');
        }
        $userId = Auth::id();

        $aujourdhui = new DateTimeImmutable('today');
        $finSemaine = $aujourdhui->modify('+7 days')->setTime(23, 59, 59);

        // Évènements et échéances se mélangent, rangés par date : ce qui compte
        // sur cette page, c'est ce qui arrive, quelle qu'en soit la nature.
        $melange = static function (int $userId, DateTimeImmutable $de, DateTimeImmutable $a): array {
            $lignes = array_merge(
                CalendrierController::evenementsEntre($userId, $de, $a),
                CalendrierController::echeancesEntre($userId, $de, $a)
            );
            usort($lignes, static fn (array $x, array $y): int => $x['debut'] <=> $y['debut']);
            return $lignes;
        };

        /*
         * La journée en grille, comme dans le calendrier : on ouvre l'accueil
         * pour savoir ce qu'on fait aujourd'hui, et une grille le dit plus vite
         * qu'une liste — les trous s'y voient.
         */
        $duJour = $melange($userId, $aujourdhui, $aujourdhui->setTime(23, 59, 59));

        Vue::afficher('tableau-bord', [
            'aujourdhui' => $aujourdhui,
            'planning'   => PlanningJour::disposer($duJour, $aujourdhui),
            'examens' => Database::all(
                'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                        c.titre AS cours_titre,
                        t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
                 FROM evenements e
                 LEFT JOIN matieres m   ON m.id = e.matiere_id
                 LEFT JOIN cours c      ON c.id = e.cours_id
                 JOIN types_evenement t ON t.id = e.type_id
                 WHERE e.user_id = ? AND t.est_echeance = 1 AND e.fin >= NOW() AND e.termine = 0
                 ORDER BY e.debut ASC LIMIT 5',
                [$userId]
            ),
            'taches' => TachesController::aVenir($userId),
            /*
             * Il n'en reste qu'un : le nombre de tâches en attente, que la
             * carte des tâches cite quand aucune n'a d'échéance proche. Les
             * quatre compteurs du haut, eux, sont partis — quatre requêtes de
             * moins à chaque ouverture de l'accueil.
             */
            'stats' => ['taches' => TachesController::resteAFaire($userId)],
            // L'alternance, si l'on en fait une : où l'on est, ce qui reste à
            // écrire, la prochaine date du contrat.
            'alternance' => self::resumeAlternance($userId),
            // Le temps de révision du jour : une ligne, sous la date.
            'focus' => Focus::bilan($userId),
            // Les travaux de groupe : ce qu'on m'a confié, et les invitations.
            'travaux' => ['taches' => Travaux::mesTaches($userId, 4), 'invitations' => Travaux::nbInvitations($userId)],
        ], 'Accueil');
    }

    /**
     * Ce que l'accueil dit de l'alternance : où l'on est aujourd'hui, la
     * semaine du journal restée blanche, la prochaine date du contrat. Null
     * pour qui n'est pas en alternance — la carte n'apparaît alors pas.
     *
     * @return array{situation: ?array, aEcrire: ?string, prochaine: ?array, entreprise: string}|null
     */
    private static function resumeAlternance(int $userId): ?array
    {
        $situation = Alternance::situation($userId);
        $aEcrire = Alternance::semainesAEcrire($userId)[0] ?? null;

        $contrat = Alternance::contrat($userId);
        $auj = date('Y-m-d');
        $prochaine = null;
        foreach (Alternance::ECHEANCES as $champ => $echeance) {
            $jour = (string) ($contrat[$champ] ?? '');
            if ($jour >= $auj && $jour !== '' && ($prochaine === null || $jour < $prochaine['jour'])) {
                $prochaine = ['jour' => $jour, 'libelle' => $echeance['libelle']];
            }
        }

        if ($situation === null && $aEcrire === null && $prochaine === null) {
            return null;
        }

        return ['situation' => $situation, 'aEcrire' => $aEcrire, 'prochaine' => $prochaine,
                'entreprise' => trim((string) $contrat['entreprise'])];
    }
}
