<?php
declare(strict_types=1);

/** Gestion des types d'évènement : ajout, modification, suppression, ordre. */
final class TypesEvenementController
{
    /** Palette proposée dans le formulaire. */
    public const PALETTE = [
        '#4f46e5', '#0ea5e9', '#059669', '#65a30d', '#ca8a04',
        '#ea580c', '#dc2626', '#db2777', '#7c3aed', '#64748b',
    ];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $types = Database::all(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM evenements e WHERE e.type_id = t.id) AS nb_evenements
             FROM types_evenement t
             WHERE t.user_id = ?
             ORDER BY t.position, t.nom',
            [$userId]
        );

        Vue::afficher('types/index', [
            'types'    => $types,
            'palette'  => self::PALETTE,
            'icones'   => icones_proposees(),
            'sansType' => (int) Database::valeur(
                'SELECT COUNT(*) FROM evenements WHERE user_id = ? AND type_id IS NULL',
                [$userId]
            ),
        ], "Types d'évènement");
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nom = mb_substr(post('nom'), 0, 60);
        if ($nom === '') {
            Session::flash('erreur', 'Le nom du type est obligatoire.');
            redirect('types');
        }
        if (Database::valeur('SELECT id FROM types_evenement WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
            Session::flash('erreur', 'Vous avez déjà un type nommé « ' . $nom . ' ».');
            redirect('types');
        }

        $position = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM types_evenement WHERE user_id = ?',
            [$userId]
        );

        Database::run(
            'INSERT INTO types_evenement (user_id, nom, icone, couleur, est_echeance, position)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $nom,
                $this->iconeValide(post('icone')),
                $this->couleurValide(post('couleur')),
                isset($_POST['est_echeance']) ? 1 : 0,
                $position,
            ]
        );

        Session::flash('succes', 'Type « ' . $nom . ' » créé.');
        redirect('types');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM types_evenement WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $nom = mb_substr(post('nom'), 0, 60);
        if ($nom === '') {
            Session::flash('erreur', 'Le nom du type est obligatoire.');
            redirect('types');
        }

        $doublon = Database::valeur(
            'SELECT id FROM types_evenement WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', 'Un autre type porte déjà ce nom.');
            redirect('types');
        }

        Database::run(
            'UPDATE types_evenement SET nom = ?, icone = ?, couleur = ?, est_echeance = ?
             WHERE id = ? AND user_id = ?',
            [
                $nom,
                $this->iconeValide(post('icone')),
                $this->couleurValide(post('couleur')),
                isset($_POST['est_echeance']) ? 1 : 0,
                $id,
                $userId,
            ]
        );

        Session::flash('succes', 'Type mis à jour.');
        redirect('types');
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $type = Database::one('SELECT nom FROM types_evenement WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($type === null) {
            $this->introuvable();
        }

        $nbEvenements = (int) Database::valeur('SELECT COUNT(*) FROM evenements WHERE type_id = ?', [$id]);

        // Les évènements liés sont conservés : leur type passe simplement à NULL.
        Database::run('DELETE FROM types_evenement WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $nbEvenements === 0
            ? 'Type « ' . $type['nom'] . ' » supprimé.'
            : sprintf(
                'Type « %s » supprimé. %d évènement%s conservé%s, désormais sans type.',
                $type['nom'],
                $nbEvenements,
                $nbEvenements > 1 ? 's' : '',
                $nbEvenements > 1 ? 's' : ''
            ));
        redirect('types');
    }

    /** Monte ou descend un type dans la liste. */
    public function deplacer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $sens = post('sens') === 'bas' ? 'bas' : 'haut';
        $types = Database::all(
            'SELECT id FROM types_evenement WHERE user_id = ? ORDER BY position, nom',
            [$userId]
        );
        $ids = array_map(static fn (array $t): int => (int) $t['id'], $types);
        $index = array_search($id, $ids, true);

        if ($index === false) {
            $this->introuvable();
        }

        $cible = $sens === 'haut' ? $index - 1 : $index + 1;
        if ($cible >= 0 && $cible < count($ids)) {
            [$ids[$index], $ids[$cible]] = [$ids[$cible], $ids[$index]];
            foreach ($ids as $rang => $typeId) {
                Database::run(
                    'UPDATE types_evenement SET position = ? WHERE id = ? AND user_id = ?',
                    [$rang + 1, $typeId, $userId]
                );
            }
        }

        redirect('types');
    }

    /** Crée les types par défaut pour un nouveau compte. */
    public static function creerParDefaut(int $userId): void
    {
        foreach (types_evenement_par_defaut() as $rang => $type) {
            Database::run(
                'INSERT IGNORE INTO types_evenement (user_id, nom, icone, couleur, est_echeance, position)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $type['nom'], $type['icone'], $type['couleur'], $type['est_echeance'], $rang + 1]
            );
        }
    }

    /** Types d'un utilisateur, indexés par identifiant. */
    public static function pourUtilisateur(int $userId): array
    {
        return Database::all(
            'SELECT * FROM types_evenement WHERE user_id = ? ORDER BY position, nom',
            [$userId]
        );
    }

    private function couleurValide(string $couleur): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#64748b';
    }

    /** Garde un emoji court ; refuse le texte long ou vide. */
    private function iconeValide(string $icone): string
    {
        $icone = trim($icone);
        if ($icone === '' || mb_strlen($icone) > 4) {
            return '📌';
        }
        return $icone;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
