<?php
declare(strict_types=1);

final class CalendrierController
{
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $vue = in_array($_GET['vue'] ?? '', ['semaine', 'liste'], true) ? (string) $_GET['vue'] : 'mois';
        $ancre = $this->dateAncre();

        [$debut, $fin] = match ($vue) {
            'semaine' => $this->bornesSemaine($ancre),
            'liste'   => [(clone $ancre)->modify('first day of this month')->setTime(0, 0),
                          (clone $ancre)->modify('+1 year')->setTime(23, 59, 59)],
            default   => $this->bornesMois($ancre),
        };

        $matiereId = entier_ou_null($_GET['matiere'] ?? null);
        $typeId = $this->typeValide($userId, $_GET['type'] ?? null);

        $evenements = $this->evenements($userId, $debut, $fin, $matiereId, $typeId);

        // Les échéances des tâches s'invitent dans le calendrier sans y être
        // recopiées : elles sont relues à chaque affichage, donc toujours à
        // jour. Un filtre par matière ou par type les écarte, n'en ayant pas.
        if ($matiereId === null && $typeId === null) {
            $evenements = array_merge($evenements, self::echeancesEntre($userId, $debut, $fin));
            usort($evenements, static fn (array $a, array $b): int => $a['debut'] <=> $b['debut']);
        }

        Vue::afficher('calendrier/index', [
            'vue'           => $vue,
            'ancre'         => $ancre,
            'debut'         => $debut,
            'fin'           => $fin,
            'evenements'    => $evenements,
            'parJour'       => $this->grouperParJour($evenements),
            'matieres'      => $this->matieres($userId),
            'matiereId'     => $matiereId,
            'types'         => TypesEvenementController::pourUtilisateur($userId),
            'typeId'        => $typeId,
            'aVenir'        => $this->aVenir($userId, 6),
        ], 'Calendrier');
    }

    /** Formulaire de création (id null) ou de modification d'un événement. */
    public function formulaire(?int $id = null): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $evenement = null;
        if ($id !== null) {
            $evenement = Database::one('SELECT * FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($evenement === null) {
                $this->introuvable();
            }
        }

        $dateDefaut = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDefaut) !== 1) {
            $dateDefaut = date('Y-m-d');
        }

        Vue::afficher('calendrier/formulaire', [
            'evenement'  => $evenement,
            'matieres'   => $this->matieres($userId),
            'coursListe' => Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$userId]),
            'dateDefaut' => $dateDefaut,
            'types'      => TypesEvenementController::pourUtilisateur($userId),
            'typeDefaut' => $this->typeValide($userId, $_GET['type'] ?? null),
        ], $evenement === null ? 'Nouvel événement' : 'Modifier l\'événement');
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('evenements/nouveau');
        }

        Database::run(
            'INSERT INTO evenements (user_id, matiere_id, cours_id, type_id, titre, description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $donnees['matiere_id'],
                $donnees['cours_id'],
                $donnees['type_id'],
                $donnees['titre'],
                $donnees['description'],
                $donnees['lieu'],
                $donnees['debut'],
                $donnees['fin'],
                $donnees['journee_entiere'],
            ]
        );

        Session::flash('succes', 'Événement ajouté au calendrier.');
        redirect('calendrier', ['date' => substr($donnees['debut'], 0, 10)]);
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('evenements/' . $id . '/modifier');
        }

        Database::run(
            'UPDATE evenements
             SET matiere_id = ?, cours_id = ?, type_id = ?, titre = ?, description = ?, lieu = ?,
                 debut = ?, fin = ?, journee_entiere = ?
             WHERE id = ? AND user_id = ?',
            [
                $donnees['matiere_id'],
                $donnees['cours_id'],
                $donnees['type_id'],
                $donnees['titre'],
                $donnees['description'],
                $donnees['lieu'],
                $donnees['debut'],
                $donnees['fin'],
                $donnees['journee_entiere'],
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Événement mis à jour.');
        redirect('calendrier', ['date' => substr($donnees['debut'], 0, 10)]);
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', 'Événement supprimé.');
        redirect('calendrier');
    }

    public function basculerTermine(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Database::run(
            'UPDATE evenements SET termine = 1 - termine WHERE id = ? AND user_id = ?',
            [$id, Auth::id()]
        );
        $retour = $_POST['retour'] ?? 'calendrier';
        redirect(is_string($retour) && $retour !== '' ? ltrim($retour, '/') : 'calendrier');
    }

    // --- Outils internes ------------------------------------------------

    /** Événements d'un utilisateur qui chevauchent une période. */
    public static function evenementsEntre(
        int $userId,
        DateTimeInterface $debut,
        DateTimeInterface $fin,
        ?int $matiereId = null,
        ?int $typeId = null
    ): array {
        $sql = 'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur, c.titre AS cours_titre,
                       t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur, t.est_echeance
                FROM evenements e
                LEFT JOIN matieres m        ON m.id = e.matiere_id
                LEFT JOIN cours c           ON c.id = e.cours_id
                LEFT JOIN types_evenement t ON t.id = e.type_id
                WHERE e.user_id = ? AND e.debut <= ? AND e.fin >= ?';
        $params = [$userId, $fin->format('Y-m-d H:i:s'), $debut->format('Y-m-d H:i:s')];

        if ($matiereId !== null) {
            $sql .= ' AND e.matiere_id = ?';
            $params[] = $matiereId;
        }
        if ($typeId !== null) {
            $sql .= ' AND e.type_id = ?';
            $params[] = $typeId;
        }
        $sql .= ' ORDER BY e.debut ASC, e.fin ASC';

        return Database::all($sql, $params);
    }

    private function evenements(
        int $userId,
        DateTimeInterface $debut,
        DateTimeInterface $fin,
        ?int $matiereId,
        ?int $typeId
    ): array {
        return self::evenementsEntre($userId, $debut, $fin, $matiereId, $typeId);
    }

    /** Répartit les événements sur chaque jour qu'ils couvrent (clé : Y-m-d). */
    /**
     * Les échéances des tâches, présentées comme des évènements d'une journée.
     * Rien n'est écrit : ces lignes n'existent que le temps de l'affichage.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function echeancesEntre(int $userId, DateTimeInterface $debut, DateTimeInterface $fin): array
    {
        $bornes = [$userId, $debut->format('Y-m-d'), $fin->format('Y-m-d')];
        $lignes = [];

        foreach (Database::all(
            'SELECT t.id, t.titre, t.echeance, t.faite, t.liste_id,
                    l.nom AS liste_nom, l.couleur AS liste_couleur, l.icone AS liste_icone
             FROM taches t
             JOIN listes_taches l ON l.id = t.liste_id
             WHERE t.user_id = ? AND t.echeance BETWEEN ? AND ?',
            $bornes
        ) as $tache) {
            $lignes[] = self::pseudoEvenement(
                (int) $tache['id'],
                (string) $tache['titre'],
                (string) $tache['echeance'],
                (int) $tache['faite'] === 1,
                (int) $tache['liste_id'],
                'Sous-tâche',
                '☑️',
                'taches/' . (int) $tache['id'] . '/cocher',
                (string) $tache['liste_couleur'],
                (string) $tache['liste_icone'] . ' ' . (string) $tache['liste_nom']
            );
        }

        foreach (Database::all(
            'SELECT l.id, l.nom, l.echeance, l.couleur, l.icone,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id)                  AS total,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id AND t.faite = 0)  AS reste
             FROM listes_taches l
             WHERE l.user_id = ? AND l.echeance BETWEEN ? AND ?',
            $bornes
        ) as $liste) {
            $lignes[] = self::pseudoEvenement(
                (int) $liste['id'],
                (string) $liste['nom'],
                (string) $liste['echeance'],
                (int) $liste['total'] > 0 && (int) $liste['reste'] === 0,
                (int) $liste['id'],
                'Tâche principale',
                (string) $liste['icone'],
                'taches/listes/' . (int) $liste['id'] . '/cocher',
                (string) $liste['couleur'],
                (int) $liste['total'] === 0
                    ? 'aucune sous-tâche'
                    : (int) $liste['reste'] . ' sur ' . (int) $liste['total'] . ' à faire'
            );
        }

        return $lignes;
    }

    /** Une échéance mise à la forme d'un évènement, pour que les vues la rendent. */
    private static function pseudoEvenement(
        int $id,
        string $titre,
        string $jour,
        bool $termine,
        int $listeId,
        string $typeNom,
        string $icone,
        string $routeCocher,
        string $couleur,
        string $detail
    ): array {
        return [
            'id'              => $id,
            'titre'           => $titre,
            'debut'           => $jour . ' 00:00:00',
            'fin'             => $jour . ' 23:59:59',
            'journee_entiere' => 1,
            'termine'         => $termine ? 1 : 0,
            'type_nom'        => $typeNom,
            'type_icone'      => $icone !== '' ? $icone : '📋',
            'type_couleur'    => $couleur,
            'matiere_nom'     => null,
            'matiere_couleur' => null,
            'lieu'            => null,
            'cours_titre'     => null,
            'description'     => null,
            // Ce qui distingue une échéance d'un vrai évènement.
            'est_tache'       => true,
            'liste_id'        => $listeId,
            'detail_tache'    => $detail,
            'route_cocher'    => $routeCocher,
        ];
    }

    private function grouperParJour(array $evenements): array
    {
        $parJour = [];
        foreach ($evenements as $evenement) {
            $curseur = new DateTimeImmutable(substr($evenement['debut'], 0, 10));
            $dernier = new DateTimeImmutable(substr($evenement['fin'], 0, 10));
            // Garde-fou : un événement ne s'etale pas sur plus de 60 jours d'affichage.
            for ($i = 0; $i < 60 && $curseur <= $dernier; $i++) {
                $parJour[$curseur->format('Y-m-d')][] = $evenement;
                $curseur = $curseur->modify('+1 day');
            }
        }
        return $parJour;
    }

    private function aVenir(int $userId, int $limite): array
    {
        $lignes = Database::all(
            'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
             FROM evenements e
             LEFT JOIN matieres m        ON m.id = e.matiere_id
             LEFT JOIN types_evenement t ON t.id = e.type_id
             WHERE e.user_id = ? AND e.fin >= NOW() AND e.termine = 0
             ORDER BY e.debut ASC LIMIT ' . $limite,
            [$userId]
        );

        // Les échéances encore ouvertes s'y ajoutent, sur un horizon large.
        $horizon = (new DateTimeImmutable('today'))->modify('+1 year');
        foreach (self::echeancesEntre($userId, new DateTimeImmutable('today'), $horizon) as $echeance) {
            if ((int) $echeance['termine'] === 0) {
                $lignes[] = $echeance;
            }
        }

        usort($lignes, static fn (array $a, array $b): int => $a['debut'] <=> $b['debut']);

        return array_slice($lignes, 0, $limite);
    }

    private function dateAncre(): DateTimeImmutable
    {
        $date = (string) ($_GET['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return new DateTimeImmutable($date);
        }
        $mois = entier_ou_null($_GET['m'] ?? null);
        $annee = entier_ou_null($_GET['a'] ?? null);
        if ($mois !== null && $annee !== null && $mois >= 1 && $mois <= 12 && $annee >= 1970 && $annee <= 2100) {
            return new DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois));
        }
        return new DateTimeImmutable('today');
    }

    /** Premier lundi affiché et dernier dimanche affiché pour une grille mensuelle. */
    private function bornesMois(DateTimeImmutable $ancre): array
    {
        $premier = $ancre->modify('first day of this month')->setTime(0, 0);
        $dernier = $ancre->modify('last day of this month')->setTime(23, 59, 59);
        $debut = $premier->modify('-' . ((int) $premier->format('N') - 1) . ' days');
        $fin = $dernier->modify('+' . (7 - (int) $dernier->format('N')) . ' days')->setTime(23, 59, 59);
        return [$debut, $fin];
    }

    private function bornesSemaine(DateTimeImmutable $ancre): array
    {
        $lundi = $ancre->modify('-' . ((int) $ancre->format('N') - 1) . ' days')->setTime(0, 0);
        return [$lundi, $lundi->modify('+6 days')->setTime(23, 59, 59)];
    }

    private function matieres(int $userId): array
    {
        return Database::all('SELECT * FROM matieres WHERE user_id = ? ORDER BY nom', [$userId]);
    }

    /** Valide le formulaire ; renvoie un tableau de données ou un message d'erreur. */
    private function lireFormulaire(int $userId): array|string
    {
        $titre = post('titre');
        if ($titre === '') {
            return 'Le titre de l\'événement est obligatoire.';
        }

        $typeId = $this->typeValide($userId, $_POST['type_id'] ?? null);

        $journeeEntiere = isset($_POST['journee_entiere']) ? 1 : 0;
        $dateDebut = post('date_debut');
        $dateFin = post('date_fin', $dateDebut);
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        if ($journeeEntiere === 1) {
            $debut = $dateDebut . ' 00:00:00';
            $fin = $dateFin . ' 23:59:59';
        } else {
            $debut = $dateDebut . ' ' . (post('heure_debut', '08:00') ?: '08:00') . ':00';
            $fin = $dateFin . ' ' . (post('heure_fin', '09:00') ?: '09:00') . ':00';
        }

        $tsDebut = strtotime($debut);
        $tsFin = strtotime($fin);
        if ($tsDebut === false || $tsFin === false) {
            return 'Dates ou heures invalides.';
        }
        if ($tsFin < $tsDebut) {
            return 'La fin ne peut pas être antérieure au début.';
        }

        $coursId = entier_ou_null($_POST['cours_id'] ?? null);
        if ($coursId !== null
            && Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]) === null) {
            $coursId = null;
        }
        $matiereId = entier_ou_null($_POST['matiere_id'] ?? null);
        if ($matiereId !== null
            && Database::valeur('SELECT id FROM matieres WHERE id = ? AND user_id = ?', [$matiereId, $userId]) === null) {
            $matiereId = null;
        }

        return [
            'matiere_id'      => $matiereId,
            'cours_id'        => $coursId,
            'type_id'         => $typeId,
            'titre'           => mb_substr($titre, 0, 200),
            'description'     => post('description') ?: null,
            'lieu'            => mb_substr(post('lieu'), 0, 160) ?: null,
            'debut'           => date('Y-m-d H:i:s', $tsDebut),
            'fin'             => date('Y-m-d H:i:s', $tsFin),
            'journee_entiere' => $journeeEntiere,
        ];
    }

    /** Renvoie l'identifiant du type s'il appartient bien à l'utilisateur, sinon null. */
    private function typeValide(int $userId, mixed $valeur): ?int
    {
        $id = entier_ou_null($valeur);
        if ($id === null) {
            return null;
        }
        $existe = Database::valeur(
            'SELECT id FROM types_evenement WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        return $existe === null ? null : $id;
    }
    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
