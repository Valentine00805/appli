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
            'derniersCours' => Database::all(
                'SELECT c.id, c.titre, c.updated_at, m.nom AS matiere_nom, m.couleur AS matiere_couleur
                 FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                 WHERE c.user_id = ?
                 ORDER BY c.updated_at DESC LIMIT 6',
                [$userId]
            ),
            /*
             * Il n'en reste qu'un : le nombre de tâches en attente, que la
             * carte des tâches cite quand aucune n'a d'échéance proche. Les
             * quatre compteurs du haut, eux, sont partis — quatre requêtes de
             * moins à chaque ouverture de l'accueil.
             */
            'stats' => ['taches' => TachesController::resteAFaire($userId)],
        ], 'Accueil');
    }
}
