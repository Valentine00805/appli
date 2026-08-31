<?php
declare(strict_types=1);

/**
 * Récapitulatif des dépenses à se faire rembourser.
 *
 * Reprend la logique des relevés tenus au tableur : les lignes cochées sont
 * regroupées par rubrique avec un sous-total, un total par mois, et un cumul
 * sur la période choisie. Une ligne peut être mise « hors total » pour la
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

        [$debut, $fin, $periode] = $this->periode();
        $personne = trim((string) ($_GET['personne'] ?? ''));
        $statut = array_key_exists($_GET['statut'] ?? '', self::STATUTS) ? (string) $_GET['statut'] : null;

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

        $lignes = Database::all($sql, $params);

        Vue::afficher('budget/remboursements', [
            'lignes'      => $lignes,
            'rubriques'   => $this->grouperParRubrique($lignes),
            'parMois'     => $this->grouperParMois($lignes),
            'totaux'      => $this->totaux($lignes),
            'debut'       => $debut,
            'fin'         => $fin,
            'periode'     => $periode,
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
        ], 'Remboursements');
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

    /** Un total par mois, plus le cumul de la période. */
    private function grouperParMois(array $lignes): array
    {
        $mois = [];
        foreach ($lignes as $l) {
            if ($l['statut_remb'] === 'hors_total') {
                continue;
            }
            $cle = substr((string) $l['date_operation'], 0, 7);
            $mois[$cle] = ($mois[$cle] ?? 0.0) + (float) $l['montant_reclame'];
        }
        ksort($mois);
        return $mois;
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
    private function periode(): array
    {
        $depuis = (string) ($_GET['depuis'] ?? '');
        $jusqu  = (string) ($_GET['jusqu'] ?? '');

        if (preg_match('/^\d{4}-\d{2}$/', $depuis) === 1 && preg_match('/^\d{4}-\d{2}$/', $jusqu) === 1) {
            if ($jusqu < $depuis) {
                [$depuis, $jusqu] = [$jusqu, $depuis];
            }
            return [
                $depuis . '-01',
                (new DateTimeImmutable($jusqu . '-01'))->format('Y-m-t'),
                ['depuis' => $depuis, 'jusqu' => $jusqu],
            ];
        }

        // Par défaut : les douze derniers mois, ce qui couvre une année scolaire.
        $fin = new DateTimeImmutable('today');
        $debut = $fin->modify('-11 months')->modify('first day of this month');
        return [
            $debut->format('Y-m-d'),
            $fin->format('Y-m-t'),
            ['depuis' => $debut->format('Y-m'), 'jusqu' => $fin->format('Y-m')],
        ];
    }

    /** Conserve les filtres au retour d'une action. */
    private function parametresRetour(): array
    {
        $params = [];
        foreach (['depuis', 'jusqu', 'personne', 'statut', 'mois'] as $cle) {
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
