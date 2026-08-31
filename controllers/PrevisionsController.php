<?php
declare(strict_types=1);

/**
 * Prévisions budgétaires.
 *
 * Principe : un solde saisi à la main sert de point de départ, puis chaque mois
 * ajoute son mouvement prévu (opérations réelles + charges fixes pas encore
 * saisies). Le solde prévisionnel d'un mois devient le solde de départ du
 * suivant. Rien n'est figé en base : tout se recalcule, donc corriger une vieille
 * opération met à jour toute la chaîne.
 */
final class PrevisionsController
{
    /** Nombre de mois affichés dans la projection. */
    private const MOIS_PROJETES = 6;

    /** Garde-fou : on ne remonte pas une chaîne plus longue que cela. */
    private const CHAINE_MAX = 60;

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $mois = $this->moisAncre();
        $periode = $mois->format('Y-m');

        $recurrences = $this->recurrences($userId);
        $chaine = $this->chaine($userId, $periode, self::MOIS_PROJETES);
        $courant = $chaine[$periode] ?? null;

        Vue::afficher('budget/previsions', [
            'mois'          => $mois,
            'periode'       => $periode,
            'courant'       => $courant,
            'chaine'        => $chaine,
            'recurrences'   => $recurrences,
            'aVenir'        => $courant['recurrences_a_venir'] ?? [],
            'soldeSaisi'    => $this->soldeSaisi($userId, $periode),
            'ancrage'       => $this->dernierAncrage($userId, $periode),
            'categories'    => Database::all(
                'SELECT * FROM categories_budget WHERE user_id = ? ORDER BY sens DESC, position, nom',
                [$userId]
            ),
            'moyens'        => BudgetController::MOYENS,
        ], 'Prévisions — ' . nom_mois((int) $mois->format('n')) . ' ' . $mois->format('Y'));
    }

    // --- Solde de départ -----------------------------------------------------

    public function enregistrerSolde(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $periode = $this->periodeValide(post('periode'));
        if ($periode === null) {
            Session::flash('erreur', 'Mois invalide.');
            redirect('budget/previsions');
        }

        $montant = montant_depuis_saisie(post('montant'));
        if ($montant === null) {
            Session::flash('erreur', 'Le solde est invalide. Exemples acceptés : 1250,40 ou -80.');
            redirect('budget/previsions', ['mois' => $periode]);
        }

        Database::run(
            'INSERT INTO soldes_saisis (user_id, periode, montant, note) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE montant = VALUES(montant), note = VALUES(note)',
            [$userId, $periode, number_format($montant, 2, '.', ''), mb_substr(post('note'), 0, 160) ?: null]
        );

        Session::flash('succes', 'Solde de départ fixé à ' . montant_fr($montant) . '.');
        redirect('budget/previsions', ['mois' => $periode]);
    }

    public function supprimerSolde(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $periode = $this->periodeValide(post('periode'));
        if ($periode === null) {
            redirect('budget/previsions');
        }

        Database::run('DELETE FROM soldes_saisis WHERE user_id = ? AND periode = ?', [$userId, $periode]);
        Session::flash('succes', 'Solde saisi supprimé : ce mois repart du solde reporté.');
        redirect('budget/previsions', ['mois' => $periode]);
    }

    // --- Charges fixes et revenus réguliers ----------------------------------

    public function creerRecurrence(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $donnees = $this->lireRecurrence($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('budget/previsions', ['mois' => $this->periodeValide(post('periode')) ?? date('Y-m')]);
        }

        Database::run(
            'INSERT INTO recurrences (user_id, categorie_id, libelle, montant, sens, jour_du_mois, moyen)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $donnees['categorie_id'],
                $donnees['libelle'],
                $donnees['montant'],
                $donnees['sens'],
                $donnees['jour_du_mois'],
                $donnees['moyen'],
            ]
        );

        Session::flash('succes', sprintf(
            '%s « %s » de %s ajoutée au prévisionnel.',
            $donnees['sens'] === 'recette' ? 'Recette régulière' : 'Charge fixe',
            $donnees['libelle'],
            montant_fr($donnees['montant'])
        ));
        redirect('budget/previsions', ['mois' => $this->periodeValide(post('periode')) ?? date('Y-m')]);
    }

    public function modifierRecurrence(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM recurrences WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $donnees = $this->lireRecurrence($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('budget/previsions');
        }

        Database::run(
            'UPDATE recurrences
             SET categorie_id = ?, libelle = ?, montant = ?, sens = ?, jour_du_mois = ?, moyen = ?, actif = ?
             WHERE id = ? AND user_id = ?',
            [
                $donnees['categorie_id'],
                $donnees['libelle'],
                $donnees['montant'],
                $donnees['sens'],
                $donnees['jour_du_mois'],
                $donnees['moyen'],
                isset($_POST['actif']) ? 1 : 0,
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Ligne mise à jour.');
        redirect('budget/previsions', ['mois' => $this->periodeValide(post('periode')) ?? date('Y-m')]);
    }

    public function supprimerRecurrence(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $rec = Database::one('SELECT libelle FROM recurrences WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($rec === null) {
            $this->introuvable();
        }

        // Les opérations déjà créées à partir d'elle sont conservées.
        Database::run('DELETE FROM recurrences WHERE id = ? AND user_id = ?', [$id, $userId]);
        Session::flash('succes', '« ' . $rec['libelle'] . ' » retirée du prévisionnel. Les opérations déjà saisies sont conservées.');
        redirect('budget/previsions', ['mois' => $this->periodeValide(post('periode')) ?? date('Y-m')]);
    }

    /** Transforme une charge fixe en opération réelle pour le mois affiché. */
    public function pointer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $periode = $this->periodeValide(post('periode')) ?? date('Y-m');
        $rec = Database::one('SELECT * FROM recurrences WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($rec === null) {
            $this->introuvable();
        }

        if ($this->dejaPointee($userId, $id, $periode)) {
            Session::flash('info', '« ' . $rec['libelle'] . ' » était déjà saisie pour ce mois.');
            redirect('budget/previsions', ['mois' => $periode]);
        }

        $this->creerOperationDepuisRecurrence($userId, $rec, $periode);

        Session::flash('succes', '« ' . $rec['libelle'] . ' » ajoutée aux opérations du mois.');
        redirect('budget/previsions', ['mois' => $periode]);
    }

    /** Transforme toutes les charges fixes restantes du mois en opérations. */
    public function pointerTout(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $periode = $this->periodeValide(post('periode')) ?? date('Y-m');
        $nb = 0;
        foreach ($this->recurrences($userId, true) as $rec) {
            if (!$this->dejaPointee($userId, (int) $rec['id'], $periode)) {
                $this->creerOperationDepuisRecurrence($userId, $rec, $periode);
                $nb++;
            }
        }

        Session::flash('succes', $nb === 0
            ? 'Toutes les lignes fixes étaient déjà saisies pour ce mois.'
            : $nb . ' ligne' . ($nb > 1 ? 's ajoutées' : ' ajoutée') . ' aux opérations du mois.');
        redirect('budget/previsions', ['mois' => $periode]);
    }

    // --- Calcul de la chaîne -------------------------------------------------

    /**
     * Calcule le prévisionnel du mois demandé et des mois suivants.
     * @return array<string, array> indexé par période AAAA-MM
     */
    private function chaine(int $userId, string $periode, int $moisSuivants): array
    {
        $ancrage = $this->dernierAncrage($userId, $periode);
        $debut = $ancrage['periode'] ?? $periode;
        $solde = $ancrage['montant'] ?? null;

        $fin = $this->ajouterMois($periode, $moisSuivants);

        // Bornes de calcul, en refusant une chaîne déraisonnablement longue.
        $periodes = $this->listePeriodes($debut, $fin, self::CHAINE_MAX);
        $reels = $this->mouvementsReels($userId, $periodes[0], end($periodes));
        $recurrences = $this->recurrences($userId, true);
        $pointees = $this->pointees($userId, $periodes[0], end($periodes));
        $saisis = $this->soldesSaisis($userId, $periodes[0], end($periodes));

        $resultat = [];
        foreach ($periodes as $p) {
            // Un solde saisi pour ce mois écrase le report.
            if (isset($saisis[$p])) {
                $solde = (float) $saisis[$p]['montant'];
                $origine = 'saisi';
            } elseif ($solde === null) {
                $origine = 'inconnu';
            } else {
                $origine = 'reporte';
            }

            $reel = $reels[$p] ?? ['recettes' => 0.0, 'depenses' => 0.0];
            $mouvementReel = $reel['recettes'] - $reel['depenses'];

            $aVenir = [];
            $prevuRecettes = 0.0;
            $prevuDepenses = 0.0;
            foreach ($recurrences as $rec) {
                if (in_array((int) $rec['id'], $pointees[$p] ?? [], true)) {
                    continue;
                }
                $aVenir[] = $rec;
                if ($rec['sens'] === 'recette') {
                    $prevuRecettes += (float) $rec['montant'];
                } else {
                    $prevuDepenses += (float) $rec['montant'];
                }
            }

            $mouvementPrevu = $prevuRecettes - $prevuDepenses;
            $soldeDepart = $solde;
            $soldeFin = $solde === null ? null : $solde + $mouvementReel + $mouvementPrevu;

            $resultat[$p] = [
                'periode'             => $p,
                'mois'                => new DateTimeImmutable($p . '-01'),
                'solde_depart'        => $soldeDepart,
                'origine'             => $origine,
                'reel_recettes'       => $reel['recettes'],
                'reel_depenses'       => $reel['depenses'],
                'prevu_recettes'      => $prevuRecettes,
                'prevu_depenses'      => $prevuDepenses,
                'mouvement'           => $mouvementReel + $mouvementPrevu,
                'solde_previsionnel'  => $soldeFin,
                'recurrences_a_venir' => $aVenir,
            ];

            $solde = $soldeFin;
        }

        return $resultat;
    }

    /** Dernier solde saisi à la date demandée ou avant. */
    private function dernierAncrage(int $userId, string $periode): ?array
    {
        $ligne = Database::one(
            'SELECT periode, montant, note FROM soldes_saisis
             WHERE user_id = ? AND periode <= ?
             ORDER BY periode DESC LIMIT 1',
            [$userId, $periode]
        );
        return $ligne === null ? null : $ligne + ['montant' => (float) $ligne['montant']];
    }

    private function soldeSaisi(int $userId, string $periode): ?array
    {
        return Database::one(
            'SELECT * FROM soldes_saisis WHERE user_id = ? AND periode = ?',
            [$userId, $periode]
        );
    }

    /** @return array<string, array{montant: string}> */
    private function soldesSaisis(int $userId, string $debut, string $fin): array
    {
        $lignes = Database::all(
            'SELECT periode, montant FROM soldes_saisis WHERE user_id = ? AND periode BETWEEN ? AND ?',
            [$userId, $debut, $fin]
        );
        return array_column($lignes, null, 'periode');
    }

    /** Recettes et dépenses réellement saisies, par mois. */
    private function mouvementsReels(int $userId, string $debut, string $fin): array
    {
        $lignes = Database::all(
            "SELECT DATE_FORMAT(date_operation, '%Y-%m') AS periode,
                    COALESCE(SUM(CASE WHEN sens = 'recette' THEN montant END), 0) AS recettes,
                    COALESCE(SUM(CASE WHEN sens = 'depense' THEN montant END), 0) AS depenses
             FROM operations
             WHERE user_id = ?
               AND date_operation >= ? AND date_operation <= LAST_DAY(?)
             GROUP BY periode",
            [$userId, $debut . '-01', $fin . '-01']
        );

        $parMois = [];
        foreach ($lignes as $l) {
            $parMois[$l['periode']] = [
                'recettes' => (float) $l['recettes'],
                'depenses' => (float) $l['depenses'],
            ];
        }
        return $parMois;
    }

    /** Récurrences déjà transformées en opération, par mois. */
    private function pointees(int $userId, string $debut, string $fin): array
    {
        $lignes = Database::all(
            "SELECT DATE_FORMAT(date_operation, '%Y-%m') AS periode, recurrence_id
             FROM operations
             WHERE user_id = ? AND recurrence_id IS NOT NULL
               AND date_operation >= ? AND date_operation <= LAST_DAY(?)",
            [$userId, $debut . '-01', $fin . '-01']
        );

        $parMois = [];
        foreach ($lignes as $l) {
            $parMois[$l['periode']][] = (int) $l['recurrence_id'];
        }
        return $parMois;
    }

    private function dejaPointee(int $userId, int $recurrenceId, string $periode): bool
    {
        return Database::valeur(
            "SELECT id FROM operations
             WHERE user_id = ? AND recurrence_id = ?
               AND date_operation >= ? AND date_operation <= LAST_DAY(?)
             LIMIT 1",
            [$userId, $recurrenceId, $periode . '-01', $periode . '-01']
        ) !== null;
    }

    private function creerOperationDepuisRecurrence(int $userId, array $rec, string $periode): void
    {
        Database::run(
            'INSERT INTO operations (user_id, categorie_id, recurrence_id, libelle, montant, sens, date_operation, moyen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $rec['categorie_id'],
                $rec['id'],
                $rec['libelle'],
                $rec['montant'],
                $rec['sens'],
                $this->dateDansLeMois($periode, (int) $rec['jour_du_mois']),
                $rec['moyen'],
            ]
        );
    }

    private function recurrences(int $userId, bool $actives = false): array
    {
        return Database::all(
            'SELECT r.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur
             FROM recurrences r
             LEFT JOIN categories_budget c ON c.id = r.categorie_id
             WHERE r.user_id = ?' . ($actives ? ' AND r.actif = 1' : '') . '
             ORDER BY r.sens DESC, r.jour_du_mois, r.libelle',
            [$userId]
        );
    }

    // --- Petits outils -------------------------------------------------------

    /** Ramène un jour du mois dans les bornes réelles du mois (31 février → 28). */
    private function dateDansLeMois(string $periode, int $jour): string
    {
        $premier = new DateTimeImmutable($periode . '-01');
        $dernier = (int) $premier->format('t');
        $jour = max(1, min($jour, $dernier));
        return sprintf('%s-%02d', $periode, $jour);
    }

    private function ajouterMois(string $periode, int $n): string
    {
        return (new DateTimeImmutable($periode . '-01'))->modify("+$n months")->format('Y-m');
    }

    /** @return string[] liste continue de périodes, bornée par $max */
    private function listePeriodes(string $debut, string $fin, int $max): array
    {
        $courant = new DateTimeImmutable($debut . '-01');
        $borne = new DateTimeImmutable($fin . '-01');
        $periodes = [];
        while ($courant <= $borne && count($periodes) < $max) {
            $periodes[] = $courant->format('Y-m');
            $courant = $courant->modify('+1 month');
        }
        return $periodes === [] ? [$debut] : $periodes;
    }

    private function periodeValide(string $periode): ?string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) === 1 ? $periode : null;
    }

    private function moisAncre(): DateTimeImmutable
    {
        $mois = $this->periodeValide((string) ($_GET['mois'] ?? ''));
        if ($mois !== null) {
            return new DateTimeImmutable($mois . '-01');
        }
        return (new DateTimeImmutable('today'))->modify('first day of this month');
    }

    /** Valide le formulaire d'une charge fixe. */
    private function lireRecurrence(int $userId): array|string
    {
        $libelle = post('libelle');
        if ($libelle === '') {
            return 'Indiquez le nom de la charge ou du revenu.';
        }

        $montant = montant_depuis_saisie(post('montant'));
        if ($montant === null || $montant <= 0) {
            return 'Le montant doit être un nombre supérieur à zéro.';
        }
        if ($montant > 99999999.99) {
            return 'Le montant est trop élevé.';
        }

        $jour = entier_ou_null($_POST['jour_du_mois'] ?? null) ?? 1;
        $jour = max(1, min(31, $jour));

        $sens = post('sens') === 'recette' ? 'recette' : 'depense';

        $categorieId = entier_ou_null($_POST['categorie_id'] ?? null);
        if ($categorieId !== null) {
            $ok = Database::valeur(
                'SELECT id FROM categories_budget WHERE id = ? AND user_id = ? AND sens = ?',
                [$categorieId, $userId, $sens]
            );
            if ($ok === null) {
                $categorieId = null;
            }
        }

        $moyen = post('moyen');
        if (!in_array($moyen, BudgetController::MOYENS, true)) {
            $moyen = null;
        }

        return [
            'categorie_id' => $categorieId,
            'libelle'      => mb_substr($libelle, 0, 160),
            'montant'      => number_format($montant, 2, '.', ''),
            'sens'         => $sens,
            'jour_du_mois' => $jour,
            'moyen'        => $moyen,
        ];
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
