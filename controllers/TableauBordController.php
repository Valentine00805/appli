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

        Vue::afficher('tableau-bord', [
            'aujourdhui' => $aujourdhui,
            'duJour' => CalendrierController::evenementsEntre(
                $userId,
                $aujourdhui,
                $aujourdhui->setTime(23, 59, 59)
            ),
            'semaine' => CalendrierController::evenementsEntre(
                $userId,
                $aujourdhui->modify('+1 day')->setTime(0, 0),
                $finSemaine
            ),
            'examens' => Database::all(
                'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                        t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
                 FROM evenements e
                 LEFT JOIN matieres m   ON m.id = e.matiere_id
                 JOIN types_evenement t ON t.id = e.type_id
                 WHERE e.user_id = ? AND t.est_echeance = 1 AND e.fin >= NOW() AND e.termine = 0
                 ORDER BY e.debut ASC LIMIT 5',
                [$userId]
            ),
            'derniersCours' => Database::all(
                'SELECT c.id, c.titre, c.updated_at, m.nom AS matiere_nom, m.couleur AS matiere_couleur
                 FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                 WHERE c.user_id = ?
                 ORDER BY c.updated_at DESC LIMIT 6',
                [$userId]
            ),
            'stats' => [
                'cours'    => (int) Database::valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$userId]),
                'matieres' => (int) Database::valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ?', [$userId]),
                'fichiers' => (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE user_id = ?', [$userId]),
                'aVenir'   => (int) Database::valeur(
                    'SELECT COUNT(*) FROM evenements WHERE user_id = ? AND debut >= NOW()',
                    [$userId]
                ),
            ],
        ], 'Accueil');
    }
}
