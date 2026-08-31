<?php
declare(strict_types=1);

/**
 * Récapitulatif des dépenses à se faire rembourser.
 *
 * Reprend la logique des relevés tenus au tableur : les lignes cochées sont
 * regroupées par rubrique avec un sous-total, et un total pour le mois affiché.
 * Chaque mois se lit seul, sans cumul avec les autres. Une ligne peut être mise
 * « hors total » pour la
 * garder sous les yeux sans la réclamer, et une part différente du montant
 * payé peut être demandée (une essence partagée en deux, par exemple).
 */
final class RemboursementsController
{
    public const STATUTS = [
        'a_reclamer' => 'À réclamer',
        'hors_total' => 'Hors total',
        'rembourse'  => 'Remboursé',
    ];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        [$debut, $fin, $mois] = $this->mois();
        $personne = trim((string) ($_GET['personne'] ?? ''));
        $statut = array_key_exists($_GET['statut'] ?? '', self::STATUTS) ? (string) $_GET['statut'] : null;

        $lignes = $this->lignes($userId, $debut, $fin, $personne, $statut);

        Vue::afficher('budget/remboursements', [
            'lignes'      => $lignes,
            'rubriques'   => $this->grouperParRubrique($lignes),
            'totaux'      => $this->totaux($lignes),
            'mois'        => $mois,
            'moisRenseignes' => $this->moisRenseignes($userId),
            'reglement'   => $this->reglementDuMois($userId, $mois->format('Y-m'), $personne),
            'recettes'    => Database::all(
                "SELECT id, nom, icone FROM categories_budget
                  WHERE user_id = ? AND sens = 'recette' ORDER BY position, nom",
                [$userId]
            ),
            'personne'    => $personne,
            'personnes'   => self::personnes($userId),
            'statut'      => $statut,
            'statuts'     => self::STATUTS,
            'aReclamerGlobal' => (float) Database::valeur(
                "SELECT COALESCE(SUM(COALESCE(part_rembourser, montant)), 0)
                 FROM operations
                 WHERE user_id = ? AND a_rembourser = 1 AND statut_remb = 'a_reclamer'",
                [$userId]
            ),
        ], 'Remboursements — ' . nom_mois((int) $mois->format('n')) . ' ' . $mois->format('Y'));
    }

    /**
     * Exporte le récapitulatif en classeur Excel, dans la présentation des
     * relevés tenus à la main : une rubrique par bloc, un sous-total, puis le
     * total du mois. Un classeur par mois, comme les fichiers d'origine.
     */
    public function exporter(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        [$debut, $fin, $mois] = $this->mois();
        $personne = trim((string) ($_GET['personne'] ?? ''));
        $statut = array_key_exists($_GET['statut'] ?? '', self::STATUTS) ? (string) $_GET['statut'] : null;

        $lignes = $this->lignes($userId, $debut, $fin, $personne, $statut);
        if ($lignes === []) {
            Session::flash('erreur', 'Rien à exporter pour ce mois.');
            redirect('budget/remboursements', $this->parametresRetour());
        }

        $rubriques = $this->grouperParRubrique($lignes);
        $totaux = $this->totaux($lignes);
        $intitulePeriode = strtolower(nom_mois((int) $mois->format('n'))) . ' ' . $mois->format('Y');

        $classeur = new ClasseurXlsx('Remboursements');
        $classeur->largeurs([13, 44, 14, 14, 15]);

        $titre = $personne !== '' ? 'Compte pour ' . $personne : 'Dépenses à rembourser';
        $classeur->ligne([['valeur' => $titre . ' — ' . $intitulePeriode, 'style' => ClasseurXlsx::TITRE]]);
        $classeur->ligne([['valeur' => 'Édité le ' . date('d/m/Y'), 'style' => ClasseurXlsx::DISCRET]]);
        $classeur->ligne();

        foreach ($rubriques as $rubrique) {
            $classeur->ligne([
                ['valeur' => 'Date',     'style' => ClasseurXlsx::ENTETE],
                ['valeur' => $rubrique['nom'], 'style' => ClasseurXlsx::ENTETE],
                ['valeur' => 'Payé',     'style' => ClasseurXlsx::ENTETE],
                ['valeur' => 'Réclamé',  'style' => ClasseurXlsx::ENTETE],
                ['valeur' => 'Statut',   'style' => ClasseurXlsx::ENTETE],
            ]);

            foreach ($rubrique['lignes'] as $l) {
                $horsTotal = $l['statut_remb'] === 'hors_total';
                $classeur->ligne([
                    ['valeur' => $l['date_operation'], 'type' => 'date', 'style' => ClasseurXlsx::DATE],
                    ['valeur' => $l['libelle']],
                    ['valeur' => $l['montant'], 'type' => 'nombre', 'style' => ClasseurXlsx::MONTANT],
                    $horsTotal
                        ? ['valeur' => '—', 'style' => ClasseurXlsx::DISCRET]
                        : ['valeur' => $l['montant_reclame'], 'type' => 'nombre', 'style' => ClasseurXlsx::MONTANT],
                    ['valeur' => self::STATUTS[$l['statut_remb']], 'style' => ClasseurXlsx::DISCRET],
                ]);
            }

            $classeur->ligne([
                ['valeur' => '', 'style' => ClasseurXlsx::TOTAL],
                ['valeur' => 'Total ' . mb_strtolower($rubrique['nom']), 'style' => ClasseurXlsx::TOTAL],
                ['valeur' => $rubrique['paye'], 'type' => 'nombre', 'style' => ClasseurXlsx::TOTAL_MONTANT],
                ['valeur' => $rubrique['total'], 'type' => 'nombre', 'style' => ClasseurXlsx::TOTAL_MONTANT],
                ['valeur' => '', 'style' => ClasseurXlsx::TOTAL],
            ]);
            $classeur->ligne();
        }

        $classeur->ligne([
            ['valeur' => '', 'style' => ClasseurXlsx::TOTAL],
            ['valeur' => 'TOTAL ' . mb_strtoupper($intitulePeriode), 'style' => ClasseurXlsx::TOTAL],
            ['valeur' => $totaux['paye'], 'type' => 'nombre', 'style' => ClasseurXlsx::TOTAL_MONTANT],
            ['valeur' => $totaux['reclame'], 'type' => 'nombre', 'style' => ClasseurXlsx::TOTAL_MONTANT],
            ['valeur' => '', 'style' => ClasseurXlsx::TOTAL],
        ]);

        if ($totaux['hors_total'] > 0) {
            $classeur->ligne();
            $classeur->ligne([
                '',
                ['valeur' => 'Pas dans le total', 'style' => ClasseurXlsx::GRAS],
                '',
                ['valeur' => $totaux['hors_total'], 'type' => 'nombre', 'style' => ClasseurXlsx::MONTANT_GRAS],
            ]);
        }

        if ($totaux['regle'] > 0) {
            $classeur->ligne();
            $classeur->ligne([
                '',
                ['valeur' => 'Dont déjà remboursé', 'style' => ClasseurXlsx::DISCRET],
                '',
                ['valeur' => $totaux['regle'], 'type' => 'nombre', 'style' => ClasseurXlsx::MONTANT],
            ]);
            $classeur->ligne([
                '',
                ['valeur' => 'Reste à rembourser', 'style' => ClasseurXlsx::GRAS],
                '',
                ['valeur' => $totaux['attente'], 'type' => 'nombre', 'style' => ClasseurXlsx::MONTANT_GRAS],
            ]);
        }

        $nom = ($personne !== '' ? 'Compte pour ' . $personne : 'Remboursements')
            . ' ' . $intitulePeriode . '.xlsx';
        $classeur->telecharger($nom);
    }

    /**
     * Déclare un mois remboursé : ses lignes passent au statut « remboursé » et
     * une recette du même montant est ajoutée aux opérations du mois suivant,
     * puisque l'argent revient sur le compte à ce moment-là.
     */
    public function reglerMois(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $periode = $this->periodeValide(post('periode'));
        if ($periode === null) {
            Session::flash('erreur', 'Mois invalide.');
            redirect('budget/remboursements');
        }

        $personne = mb_substr(post('personne'), 0, 80);
        $retour = array_filter(['mois' => $periode, 'personne' => $personne ?: null]);

        if ($this->reglementDuMois($userId, $periode, $personne) !== null) {
            Session::flash('info', 'Ce mois était déjà réglé.');
            redirect('budget/remboursements', $retour);
        }

        $lignes = $this->lignesAReclamer($userId, $periode, $personne);
        if ($lignes === []) {
            Session::flash('erreur', 'Aucune dépense à réclamer pour ce mois.');
            redirect('budget/remboursements', $retour);
        }

        $montant = round(array_sum(array_map(
            static fn (array $l): float => (float) $l['montant_reclame'],
            $lignes
        )), 2);
        $ids = array_map(static fn (array $l): int => (int) $l['id'], $lignes);

        // Par défaut, l'argent arrive le 1er du mois suivant.
        $dateSaisie = post('date_recette');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateSaisie) === 1
            ? $dateSaisie
            : (new DateTimeImmutable($periode . '-01'))->modify('+1 month')->format('Y-m-d');

        $categorieId = entier_ou_null($_POST['categorie_id'] ?? null);
        if ($categorieId !== null && Database::valeur(
            "SELECT id FROM categories_budget WHERE id = ? AND user_id = ? AND sens = 'recette'",
            [$categorieId, $userId]
        ) === null) {
            $categorieId = null;
        }

        $intitule = sprintf(
            'Remboursement %s %s%s',
            strtolower(nom_mois((int) substr($periode, 5, 2))),
            substr($periode, 0, 4),
            $personne !== '' ? ' — ' . $personne : ''
        );

        Database::run(
            "INSERT INTO operations (user_id, categorie_id, libelle, montant, sens, date_operation, moyen)
             VALUES (?, ?, ?, ?, 'recette', ?, 'Virement')",
            [$userId, $categorieId, $intitule, number_format($montant, 2, '.', ''), $date]
        );
        $operationId = Database::dernierId();

        $trous = implode(',', array_fill(0, count($ids), '?'));
        Database::run(
            "UPDATE operations SET statut_remb = 'rembourse', date_remboursement = ?
             WHERE user_id = ? AND id IN ($trous)",
            array_merge([$date, $userId], $ids)
        );

        Database::run(
            'INSERT INTO reglements (user_id, periode, personne, montant, date_reglement, operation_id, lignes)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $periode,
                $personne !== '' ? $personne : null,
                number_format($montant, 2, '.', ''),
                $date,
                $operationId,
                json_encode($ids),
            ]
        );

        Session::flash('succes', sprintf(
            '%s réglés pour %s. La recette a été ajoutée aux opérations du %s.',
            montant_fr($montant),
            strtolower(nom_mois((int) substr($periode, 5, 2))) . ' ' . substr($periode, 0, 4),
            date_fr($date . ' 00:00:00', false)
        ));
        redirect('budget/remboursements', $retour);
    }

    /** Revient sur un règlement : les lignes redeviennent à réclamer. */
    public function annulerReglement(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $reglement = Database::one('SELECT * FROM reglements WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($reglement === null) {
            $this->introuvable();
        }

        $ids = json_decode((string) $reglement['lignes'], true);
        if (is_array($ids) && $ids !== []) {
            $ids = array_map('intval', $ids);
            $trous = implode(',', array_fill(0, count($ids), '?'));
            Database::run(
                "UPDATE operations SET statut_remb = 'a_reclamer', date_remboursement = NULL
                 WHERE user_id = ? AND statut_remb = 'rembourse' AND id IN ($trous)",
                array_merge([$userId], $ids)
            );
        }

        // La recette produite disparaît avec le règlement, sauf si elle a déjà
        // été supprimée à la main.
        if ($reglement['operation_id'] !== null) {
            Database::run(
                'DELETE FROM operations WHERE id = ? AND user_id = ?',
                [$reglement['operation_id'], $userId]
            );
        }

        Database::run('DELETE FROM reglements WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', 'Règlement annulé : les dépenses sont de nouveau à réclamer.');
        redirect('budget/remboursements', array_filter([
            'mois'     => $reglement['periode'],
            'personne' => $reglement['personne'],
        ]));
    }

    /** Le règlement enregistré pour ce mois, s'il existe. */
    private function reglementDuMois(int $userId, string $periode, string $personne): ?array
    {
        return Database::one(
            'SELECT r.*, o.date_operation AS date_recette
             FROM reglements r
             LEFT JOIN operations o ON o.id = r.operation_id
             WHERE r.user_id = ? AND r.periode = ?
               AND (r.personne <=> ?)',
            [$userId, $periode, $personne !== '' ? $personne : null]
        );
    }

    /** Les lignes encore à réclamer d'un mois. */
    private function lignesAReclamer(int $userId, string $periode, string $personne): array
    {
        $sql = "SELECT id, COALESCE(part_rembourser, montant) AS montant_reclame
                FROM operations
                WHERE user_id = ? AND a_rembourser = 1 AND statut_remb = 'a_reclamer'
                  AND date_operation >= ? AND date_operation <= LAST_DAY(?)";
        $params = [$userId, $periode . '-01', $periode . '-01'];
        if ($personne !== '') {
            $sql .= ' AND rembourse_par = ?';
            $params[] = $personne;
        }
        return Database::all($sql, $params);
    }

    private function periodeValide(string $periode): ?string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) === 1 ? $periode : null;
    }

    /** Coche ou décoche une opération depuis la liste des opérations. */
    public function basculer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $operation = Database::one(
            'SELECT libelle, a_rembourser FROM operations WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($operation === null) {
            $this->introuvable();
        }

        $nouvel = (int) $operation['a_rembourser'] === 1 ? 0 : 1;
        Database::run(
            "UPDATE operations
             SET a_rembourser = ?,
                 statut_remb = IF(? = 1, statut_remb, 'a_reclamer'),
                 rembourse_par = IF(? = 1, COALESCE(NULLIF(rembourse_par, ''), ?), rembourse_par)
             WHERE id = ? AND user_id = ?",
            [$nouvel, $nouvel, $nouvel, $this->personneParDefaut($userId), $id, $userId]
        );

        Session::flash('succes', $nouvel === 1
            ? '« ' . $operation['libelle'] .' » ajoutée aux remboursements.'
            : '« ' . $operation['libelle'] . ' » retirée des remboursements.');

        $retour = (string) ($_POST['retour'] ?? 'budget');
        redirect(ltrim($retour, '/') ?: 'budget', $this->parametresRetour());
    }

    /** Modifie le détail d'une ligne : part réclamée, personne, statut. */
    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $operation = Database::one('SELECT * FROM operations WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($operation === null) {
            $this->introuvable();
        }

        $part = montant_depuis_saisie(post('part_rembourser'));
        if ($part !== null && ($part <= 0 || $part > (float) $operation['montant'] + 0.001)) {
            Session::flash('erreur',
                'La part réclamée doit être comprise entre 0 et le montant payé ('
                . montant_fr($operation['montant']) . ').');
            redirect('budget/remboursements', $this->parametresRetour());
        }

        $statut = array_key_exists(post('statut_remb'), self::STATUTS) ? post('statut_remb') : 'a_reclamer';
        $date = post('date_remboursement');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;

        Database::run(
            'UPDATE operations
             SET part_rembourser = ?, rembourse_par = ?, statut_remb = ?, date_remboursement = ?
             WHERE id = ? AND user_id = ?',
            [
                $part === null ? null : number_format($part, 2, '.', ''),
                mb_substr(post('rembourse_par'), 0, 80) ?: null,
                $statut,
                $statut === 'rembourse' ? ($date ?? date('Y-m-d')) : null,
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Ligne mise à jour.');
        redirect('budget/remboursements', $this->parametresRetour());
    }

    /** Marque comme remboursées toutes les lignes actuellement affichées. */
    public function reglerLot(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $ids = array_map('intval', (array) ($_POST['ligne'] ?? []));
        if ($ids === []) {
            Session::flash('erreur', 'Aucune ligne sélectionnée.');
            redirect('budget/remboursements', $this->parametresRetour());
        }

        $date = post('date_remboursement');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : date('Y-m-d');

        $trous = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::run(
            "UPDATE operations
             SET statut_remb = 'rembourse', date_remboursement = ?
             WHERE user_id = ? AND a_rembourser = 1 AND id IN ($trous)",
            array_merge([$date, $userId], $ids)
        );

        $nb = $stmt->rowCount();
        Session::flash('succes', $nb === 0
            ? 'Aucune ligne modifiée.'
            : $nb . ' ligne' . ($nb > 1 ? 's marquées remboursées' : ' marquée remboursée') . '.');
        redirect('budget/remboursements', $this->parametresRetour());
    }

    // --- Lecture -------------------------------------------------------------

    /** Les lignes cochées d'une période, éventuellement filtrées. */
    private function lignes(int $userId, string $debut, string $fin, string $personne, ?string $statut): array
    {
        $sql = "SELECT o.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                       COALESCE(o.part_rembourser, o.montant) AS montant_reclame
                FROM operations o
                LEFT JOIN categories_budget c ON c.id = o.categorie_id
                WHERE o.user_id = ? AND o.a_rembourser = 1
                  AND o.date_operation BETWEEN ? AND ?";
        $params = [$userId, $debut, $fin];

        if ($personne !== '') {
            $sql .= ' AND o.rembourse_par = ?';
            $params[] = $personne;
        }
        if ($statut !== null) {
            $sql .= ' AND o.statut_remb = ?';
            $params[] = $statut;
        }
        $sql .= ' ORDER BY c.position, c.nom, o.date_operation, o.id';

        return Database::all($sql, $params);
    }

    // --- Regroupements -------------------------------------------------------

    /** Par rubrique, comme les sous-totaux d'un relevé tenu à la main. */
    private function grouperParRubrique(array $lignes): array
    {
        $rubriques = [];
        foreach ($lignes as $l) {
            $cle = $l['categorie_nom'] ?? '(sans catégorie)';
            if (!isset($rubriques[$cle])) {
                $rubriques[$cle] = [
                    'nom'     => $cle,
                    'icone'   => $l['categorie_icone'] ?? '💶',
                    'couleur' => $l['categorie_couleur'] ?? '#94a3b8',
                    'lignes'  => [],
                    'total'   => 0.0,
                    'paye'    => 0.0,
                ];
            }
            $rubriques[$cle]['lignes'][] = $l;
            $rubriques[$cle]['paye'] += (float) $l['montant'];
            if ($l['statut_remb'] !== 'hors_total') {
                $rubriques[$cle]['total'] += (float) $l['montant_reclame'];
            }
        }
        return $rubriques;
    }

    private function totaux(array $lignes): array
    {
        $t = ['paye' => 0.0, 'reclame' => 0.0, 'attente' => 0.0, 'regle' => 0.0, 'hors_total' => 0.0, 'nb' => count($lignes)];
        foreach ($lignes as $l) {
            $t['paye'] += (float) $l['montant'];
            $montant = (float) $l['montant_reclame'];
            match ($l['statut_remb']) {
                'rembourse'  => $t['regle'] += $montant,
                'hors_total' => $t['hors_total'] += $montant,
                default      => $t['attente'] += $montant,
            };
            if ($l['statut_remb'] !== 'hors_total') {
                $t['reclame'] += $montant;
            }
        }
        return $t;
    }

    /** Noms déjà utilisés, pour les suggestions et le filtre. */
    public static function personnes(int $userId): array
    {
        return array_column(Database::all(
            "SELECT DISTINCT rembourse_par FROM operations
             WHERE user_id = ? AND rembourse_par IS NOT NULL AND rembourse_par <> ''
             ORDER BY rembourse_par",
            [$userId]
        ), 'rembourse_par');
    }

    private function personneParDefaut(int $userId): ?string
    {
        $valeur = Database::valeur(
            "SELECT rembourse_par FROM operations
             WHERE user_id = ? AND rembourse_par IS NOT NULL AND rembourse_par <> ''
             ORDER BY id DESC LIMIT 1",
            [$userId]
        );
        return $valeur === null ? null : (string) $valeur;
    }

    /** Période affichée : un mois, une année, ou une plage libre. */
    /**
     * Le mois affiché. Chaque mois se lit seul : rien n'est cumulé d'un mois
     * sur l'autre, comme dans un relevé mensuel tenu à la main.
     *
     * @return array{0:string, 1:string, 2:DateTimeImmutable} début, fin, mois
     */
    private function mois(): array
    {
        $demande = (string) ($_GET['mois'] ?? '');
        $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $demande) === 1
            ? new DateTimeImmutable($demande . '-01')
            : (new DateTimeImmutable('today'))->modify('first day of this month');

        return [$mois->format('Y-m-01'), $mois->format('Y-m-t'), $mois];
    }

    /** Les mois qui contiennent au moins une ligne cochée, pour naviguer. */
    private function moisRenseignes(int $userId): array
    {
        return array_column(Database::all(
            "SELECT DATE_FORMAT(date_operation, '%Y-%m') AS mois,
                    COALESCE(SUM(CASE WHEN statut_remb <> 'hors_total'
                        THEN COALESCE(part_rembourser, montant) END), 0) AS total
             FROM operations
             WHERE user_id = ? AND a_rembourser = 1
             GROUP BY mois
             ORDER BY mois DESC",
            [$userId]
        ), null, 'mois');
    }

    /** Conserve les filtres au retour d'une action. */
    private function parametresRetour(): array
    {
        $params = [];
        foreach (['mois', 'personne', 'statut'] as $cle) {
            $valeur = $_POST[$cle] ?? $_GET[$cle] ?? '';
            if (is_string($valeur) && $valeur !== '') {
                $params[$cle] = $valeur;
            }
        }
        return $params;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
