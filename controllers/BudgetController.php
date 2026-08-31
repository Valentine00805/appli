<?php
declare(strict_types=1);

/** Suivi des recettes et des dépenses, mois par mois. */
final class BudgetController
{
    public const PALETTE = [
        '#059669', '#0ea5e9', '#4f46e5', '#7c3aed', '#db2777',
        '#dc2626', '#ea580c', '#ca8a04', '#65a30d', '#64748b',
    ];

    public const ICONES = ['🛒', '🚌', '🏠', '🎉', '✏️', '💊', '📱', '💶', '🍽️', '👕',
        '🎓', '💼', '👪', '💰', '🎁', '⚽', '📚', '☕', '🚗', '✈️', '🐾', '🔧'];

    public const MOYENS = ['Carte', 'Espèces', 'Virement', 'Prélèvement', 'Chèque'];

    // --- Vue principale : le mois -------------------------------------------

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $mois = $this->moisAncre();
        $debut = $mois->format('Y-m-01');
        $fin = $mois->format('Y-m-t');

        $categorieId = entier_ou_null($_GET['categorie'] ?? null);
        $sens = in_array($_GET['sens'] ?? '', ['depense', 'recette'], true) ? (string) $_GET['sens'] : null;

        $sql = 'SELECT o.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur
                FROM operations o
                LEFT JOIN categories_budget c ON c.id = o.categorie_id
                WHERE o.user_id = ? AND o.date_operation BETWEEN ? AND ?';
        $params = [$userId, $debut, $fin];
        if ($categorieId !== null) {
            $sql .= ' AND o.categorie_id = ?';
            $params[] = $categorieId;
        }
        if ($sens !== null) {
            $sql .= ' AND o.sens = ?';
            $params[] = $sens;
        }
        $sql .= ' ORDER BY o.date_operation DESC, o.id DESC';

        $operations = Database::all($sql, $params);

        Vue::afficher('budget/index', [
            'mois'        => $mois,
            'operations'  => $operations,
            'totaux'      => $this->totauxDuMois($userId, $debut, $fin),
            'parCategorie' => $this->depensesParCategorie($userId, $debut, $fin),
            'categories'  => $this->categories($userId),
            'categorieId' => $categorieId,
            'sens'        => $sens,
            'moyens'      => self::MOYENS,
            'historique'  => $this->douzeDerniersMois($userId, $mois),
        ], 'Budget — ' . nom_mois((int) $mois->format('n')) . ' ' . $mois->format('Y'));
    }

    // --- Opérations ----------------------------------------------------------

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('budget', ['mois' => substr((string) ($_POST['date_operation'] ?? ''), 0, 7)]);
        }

        Database::run(
            'INSERT INTO operations (user_id, categorie_id, libelle, montant, sens, date_operation, moyen, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $donnees['categorie_id'],
                $donnees['libelle'],
                $donnees['montant'],
                $donnees['sens'],
                $donnees['date_operation'],
                $donnees['moyen'],
                $donnees['note'],
            ]
        );

        Session::flash('succes', sprintf(
            '%s de %s enregistrée.',
            $donnees['sens'] === 'recette' ? 'Recette' : 'Dépense',
            montant_fr($donnees['montant'])
        ));
        redirect('budget', ['mois' => substr($donnees['date_operation'], 0, 7)]);
    }

    public function formulaire(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $operation = Database::one('SELECT * FROM operations WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($operation === null) {
            $this->introuvable();
        }

        Vue::afficher('budget/formulaire', [
            'operation'  => $operation,
            'categories' => $this->categories($userId),
            'moyens'     => self::MOYENS,
        ], "Modifier l'opération");
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM operations WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('budget/operations/' . $id . '/modifier');
        }

        Database::run(
            'UPDATE operations
             SET categorie_id = ?, libelle = ?, montant = ?, sens = ?, date_operation = ?, moyen = ?, note = ?
             WHERE id = ? AND user_id = ?',
            [
                $donnees['categorie_id'],
                $donnees['libelle'],
                $donnees['montant'],
                $donnees['sens'],
                $donnees['date_operation'],
                $donnees['moyen'],
                $donnees['note'],
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Opération mise à jour.');
        redirect('budget', ['mois' => substr($donnees['date_operation'], 0, 7)]);
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $operation = Database::one(
            'SELECT libelle, date_operation FROM operations WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($operation === null) {
            $this->introuvable();
        }

        Database::run('DELETE FROM operations WHERE id = ? AND user_id = ?', [$id, $userId]);
        Session::flash('succes', 'Opération « ' . $operation['libelle'] . ' » supprimée.');
        redirect('budget', ['mois' => substr((string) $operation['date_operation'], 0, 7)]);
    }

    // --- Catégories ----------------------------------------------------------

    public function categoriesIndex(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $categories = Database::all(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM operations o WHERE o.categorie_id = c.id) AS nb_operations,
                    (SELECT COALESCE(SUM(o.montant), 0) FROM operations o WHERE o.categorie_id = c.id) AS total
             FROM categories_budget c
             WHERE c.user_id = ?
             ORDER BY c.sens DESC, c.position, c.nom',
            [$userId]
        );

        Vue::afficher('budget/categories', [
            'depenses'  => array_values(array_filter($categories, static fn (array $c): bool => $c['sens'] === 'depense')),
            'recettes'  => array_values(array_filter($categories, static fn (array $c): bool => $c['sens'] === 'recette')),
            'palette'   => self::PALETTE,
            'icones'    => self::ICONES,
            'sansCategorie' => (int) Database::valeur(
                'SELECT COUNT(*) FROM operations WHERE user_id = ? AND categorie_id IS NULL',
                [$userId]
            ),
        ], 'Catégories de budget');
    }

    public function categorieCreer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nom = mb_substr(post('nom'), 0, 60);
        $sens = post('sens') === 'recette' ? 'recette' : 'depense';

        if ($nom === '') {
            Session::flash('erreur', 'Le nom de la catégorie est obligatoire.');
            redirect('budget/categories');
        }
        if (Database::valeur(
            'SELECT id FROM categories_budget WHERE user_id = ? AND nom = ? AND sens = ?',
            [$userId, $nom, $sens]
        ) !== null) {
            Session::flash('erreur', 'Vous avez déjà une catégorie « ' . $nom . ' » de ce type.');
            redirect('budget/categories');
        }

        $position = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM categories_budget WHERE user_id = ? AND sens = ?',
            [$userId, $sens]
        );

        Database::run(
            'INSERT INTO categories_budget (user_id, nom, icone, couleur, sens, plafond_mensuel, position)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $nom,
                $this->iconeValide(post('icone')),
                $this->couleurValide(post('couleur')),
                $sens,
                $sens === 'depense' ? montant_depuis_saisie(post('plafond_mensuel')) : null,
                $position,
            ]
        );

        Session::flash('succes', 'Catégorie « ' . $nom . ' » créée.');
        redirect('budget/categories');
    }

    public function categorieModifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $categorie = Database::one(
            'SELECT sens FROM categories_budget WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($categorie === null) {
            $this->introuvable();
        }

        $nom = mb_substr(post('nom'), 0, 60);
        if ($nom === '') {
            Session::flash('erreur', 'Le nom de la catégorie est obligatoire.');
            redirect('budget/categories');
        }

        $doublon = Database::valeur(
            'SELECT id FROM categories_budget WHERE user_id = ? AND nom = ? AND sens = ? AND id <> ?',
            [$userId, $nom, $categorie['sens'], $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', 'Une autre catégorie de ce type porte déjà ce nom.');
            redirect('budget/categories');
        }

        Database::run(
            'UPDATE categories_budget SET nom = ?, icone = ?, couleur = ?, plafond_mensuel = ?
             WHERE id = ? AND user_id = ?',
            [
                $nom,
                $this->iconeValide(post('icone')),
                $this->couleurValide(post('couleur')),
                $categorie['sens'] === 'depense' ? montant_depuis_saisie(post('plafond_mensuel')) : null,
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Catégorie mise à jour.');
        redirect('budget/categories');
    }

    public function categorieSupprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $categorie = Database::one('SELECT nom FROM categories_budget WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($categorie === null) {
            $this->introuvable();
        }

        $nb = (int) Database::valeur('SELECT COUNT(*) FROM operations WHERE categorie_id = ?', [$id]);

        // Les opérations sont conservées : leur catégorie passe à NULL.
        Database::run('DELETE FROM categories_budget WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $nb === 0
            ? 'Catégorie « ' . $categorie['nom'] . ' » supprimée.'
            : sprintf(
                'Catégorie « %s » supprimée. %d opération%s conservée%s, désormais sans catégorie.',
                $categorie['nom'],
                $nb,
                $nb > 1 ? 's' : '',
                $nb > 1 ? 's' : ''
            ));
        redirect('budget/categories');
    }

    /** Catégories de départ pour un nouveau compte. */
    public static function creerCategoriesParDefaut(int $userId): void
    {
        $defauts = [
            ['Courses', '🛒', '#059669', 'depense'],
            ['Transport', '🚌', '#0ea5e9', 'depense'],
            ['Logement', '🏠', '#7c3aed', 'depense'],
            ['Sorties', '🎉', '#db2777', 'depense'],
            ['Fournitures', '✏️', '#ca8a04', 'depense'],
            ['Santé', '💊', '#dc2626', 'depense'],
            ['Abonnements', '📱', '#ea580c', 'depense'],
            ['Divers', '💶', '#64748b', 'depense'],
            ['Bourse', '🎓', '#059669', 'recette'],
            ['Salaire', '💼', '#4f46e5', 'recette'],
            ['Aide famille', '👪', '#0ea5e9', 'recette'],
            ['Autre', '💰', '#64748b', 'recette'],
        ];
        $rangs = ['depense' => 0, 'recette' => 0];
        foreach ($defauts as [$nom, $icone, $couleur, $sens]) {
            $rangs[$sens]++;
            Database::run(
                'INSERT IGNORE INTO categories_budget (user_id, nom, icone, couleur, sens, position)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $nom, $icone, $couleur, $sens, $rangs[$sens]]
            );
        }
    }

    // --- Outils internes -----------------------------------------------------

    private function categories(int $userId): array
    {
        return Database::all(
            'SELECT * FROM categories_budget WHERE user_id = ? ORDER BY sens DESC, position, nom',
            [$userId]
        );
    }

    private function totauxDuMois(int $userId, string $debut, string $fin): array
    {
        $ligne = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN sens = 'recette' THEN montant END), 0) AS recettes,
                COALESCE(SUM(CASE WHEN sens = 'depense' THEN montant END), 0) AS depenses,
                COUNT(*) AS nb
             FROM operations
             WHERE user_id = ? AND date_operation BETWEEN ? AND ?",
            [$userId, $debut, $fin]
        ) ?? ['recettes' => 0, 'depenses' => 0, 'nb' => 0];

        $ligne['solde'] = (float) $ligne['recettes'] - (float) $ligne['depenses'];
        return $ligne;
    }

    /** Répartition des dépenses du mois, par catégorie, avec le plafond éventuel. */
    private function depensesParCategorie(int $userId, string $debut, string $fin): array
    {
        return Database::all(
            "SELECT c.id, c.nom, c.icone, c.couleur, c.plafond_mensuel,
                    COALESCE(SUM(o.montant), 0) AS total,
                    COUNT(o.id) AS nb
             FROM categories_budget c
             LEFT JOIN operations o
                    ON o.categorie_id = c.id
                   AND o.date_operation BETWEEN ? AND ?
             WHERE c.user_id = ? AND c.sens = 'depense'
             GROUP BY c.id, c.nom, c.icone, c.couleur, c.plafond_mensuel, c.position
             HAVING total > 0 OR c.plafond_mensuel IS NOT NULL
             ORDER BY total DESC, c.position",
            [$debut, $fin, $userId]
        );
    }

    /** Recettes et dépenses des douze mois précédents, pour la tendance. */
    private function douzeDerniersMois(int $userId, DateTimeImmutable $mois): array
    {
        $premier = $mois->modify('-11 months')->format('Y-m-01');
        $dernier = $mois->format('Y-m-t');

        $lignes = Database::all(
            "SELECT DATE_FORMAT(date_operation, '%Y-%m') AS periode,
                    COALESCE(SUM(CASE WHEN sens = 'recette' THEN montant END), 0) AS recettes,
                    COALESCE(SUM(CASE WHEN sens = 'depense' THEN montant END), 0) AS depenses
             FROM operations
             WHERE user_id = ? AND date_operation BETWEEN ? AND ?
             GROUP BY periode
             ORDER BY periode",
            [$userId, $premier, $dernier]
        );

        $parPeriode = array_column($lignes, null, 'periode');
        $resultat = [];
        for ($i = 11; $i >= 0; $i--) {
            $p = $mois->modify("-$i months");
            $cle = $p->format('Y-m');
            $resultat[] = [
                'periode'  => $cle,
                'mois'     => $p,
                'recettes' => (float) ($parPeriode[$cle]['recettes'] ?? 0),
                'depenses' => (float) ($parPeriode[$cle]['depenses'] ?? 0),
            ];
        }
        return $resultat;
    }

    private function moisAncre(): DateTimeImmutable
    {
        $mois = (string) ($_GET['mois'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $mois) === 1) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $mois . '-01');
            if ($date instanceof DateTimeImmutable) {
                return $date->setTime(0, 0);
            }
        }
        return (new DateTimeImmutable('today'))->modify('first day of this month');
    }

    /** Valide le formulaire d'opération ; renvoie les données ou un message d'erreur. */
    private function lireFormulaire(int $userId): array|string
    {
        $libelle = post('libelle');
        if ($libelle === '') {
            return 'Indiquez à quoi correspond cette opération.';
        }

        $montant = montant_depuis_saisie(post('montant'));
        if ($montant === null) {
            return 'Le montant est invalide. Exemples acceptés : 12,50 ou 12.50.';
        }
        if ($montant <= 0) {
            return 'Le montant doit être supérieur à zéro.';
        }
        if ($montant > 99999999.99) {
            return 'Le montant est trop élevé.';
        }

        $date = post('date_operation');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || strtotime($date) === false) {
            return 'La date est invalide.';
        }

        $sens = post('sens') === 'recette' ? 'recette' : 'depense';

        // La catégorie doit appartenir à l'utilisateur et correspondre au sens choisi.
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
        if (!in_array($moyen, self::MOYENS, true)) {
            $moyen = null;
        }

        return [
            'categorie_id'   => $categorieId,
            'libelle'        => mb_substr($libelle, 0, 160),
            'montant'        => number_format($montant, 2, '.', ''),
            'sens'           => $sens,
            'date_operation' => $date,
            'moyen'          => $moyen,
            'note'           => post('note') ?: null,
        ];
    }

    private function couleurValide(string $couleur): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#64748b';
    }

    private function iconeValide(string $icone): string
    {
        $icone = trim($icone);
        return ($icone === '' || mb_strlen($icone) > 4) ? '💶' : $icone;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
