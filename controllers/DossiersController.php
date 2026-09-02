<?php
declare(strict_types=1);

/**
 * Dossiers de rangement des cours.
 *
 * C'est un axe différent des matières : un cours a une matière (ce dont il
 * parle) et peut être rangé dans un dossier (où vous le classez) — « Semestre 1 »,
 * « Stage », « Archives ». Supprimer un dossier ne supprime aucun cours : ils
 * redeviennent simplement sans dossier.
 */
final class DossiersController
{
    /** Palette proposée dans le formulaire. */
    public const PALETTE = [
        '#4f46e5', '#0ea5e9', '#059669', '#65a30d', '#ca8a04',
        '#ea580c', '#dc2626', '#db2777', '#7c3aed', '#475569',
    ];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        Vue::afficher('dossiers/index', [
            'dossiers' => self::pourUtilisateur($userId, true),
            'palette'  => self::PALETTE,
            'icones'   => icones_dossiers(),
            'sansDossier' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IS NULL',
                [$userId]
            ),
        ], 'Mes dossiers');
    }

    /**
     * Les dossiers d'un compte, dans l'ordre choisi.
     * @return array<int, array<string, mixed>>
     */
    public static function pourUtilisateur(int $userId, bool $avecComptes = false): array
    {
        $compte = $avecComptes
            ? ', (SELECT COUNT(*) FROM cours c WHERE c.dossier_id = d.id) AS nb_cours'
            : '';

        return Database::all(
            "SELECT d.*$compte FROM dossiers d WHERE d.user_id = ? ORDER BY d.position, d.id",
            [$userId]
        );
    }

    /** Vérifie qu'un dossier appartient bien au compte. */
    public static function valide(int $userId, mixed $dossierId): ?int
    {
        $id = entier_ou_null($dossierId);
        if ($id === null) {
            return null;
        }
        $existe = Database::valeur('SELECT id FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);
        return $existe === null ? null : $id;
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', 'Donnez un nom à votre dossier.');
            redirect('organisation/dossiers');
        }
        if (Database::valeur('SELECT id FROM dossiers WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
            Session::flash('erreur', 'Vous avez déjà un dossier nommé « ' . $nom . ' ».');
            redirect('organisation/dossiers');
        }

        // Un nouveau dossier se range à la fin.
        $rang = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers WHERE user_id = ?',
            [$userId]
        );

        Database::run(
            'INSERT INTO dossiers (user_id, nom, couleur, icone, position) VALUES (?, ?, ?, ?, ?)',
            [$userId, $nom, $this->couleurValide(post('couleur')), $this->iconeValide(post('icone')), $rang]
        );

        Session::flash('succes', 'Dossier « ' . $nom . ' » créé.');
        redirect('organisation/dossiers');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', 'Le nom du dossier est obligatoire.');
            redirect('organisation/dossiers');
        }
        $doublon = Database::valeur(
            'SELECT id FROM dossiers WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', 'Un autre dossier porte déjà ce nom.');
            redirect('organisation/dossiers');
        }

        Database::run(
            'UPDATE dossiers SET nom = ?, couleur = ?, icone = ? WHERE id = ? AND user_id = ?',
            [$nom, $this->couleurValide(post('couleur')), $this->iconeValide(post('icone')), $id, $userId]
        );

        Session::flash('succes', 'Dossier mis à jour.');
        redirect('organisation/dossiers');
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $dossier = Database::one('SELECT nom FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($dossier === null) {
            $this->introuvable();
        }

        $nb = (int) Database::valeur('SELECT COUNT(*) FROM cours WHERE dossier_id = ?', [$id]);

        // Les cours restent : la contrainte les remet simplement sans dossier.
        Database::run('DELETE FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $nb === 0
            ? 'Dossier « ' . $dossier['nom'] . ' » supprimé.'
            : 'Dossier « ' . $dossier['nom'] . ' » supprimé. Ses ' . $nb . ' cours ont été conservés, sans dossier.');
        redirect('organisation/dossiers');
    }

    /** Monte ou descend un dossier d'un cran. */
    public function deplacer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $sens = post('sens') === 'bas' ? 'bas' : 'haut';
        $ids = array_map(
            static fn (array $d): int => (int) $d['id'],
            Database::all('SELECT id FROM dossiers WHERE user_id = ? ORDER BY position, id', [$userId])
        );
        $index = array_search($id, $ids, true);
        if ($index === false) {
            $this->introuvable();
        }

        $cible = $sens === 'haut' ? $index - 1 : $index + 1;
        if ($cible >= 0 && $cible < count($ids)) {
            [$ids[$index], $ids[$cible]] = [$ids[$cible], $ids[$index]];
            foreach ($ids as $rang => $dossierId) {
                Database::run(
                    'UPDATE dossiers SET position = ? WHERE id = ? AND user_id = ?',
                    [$rang + 1, $dossierId, $userId]
                );
            }
        }

        redirect('organisation/dossiers');
    }

    private function couleurValide(string $couleur): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#4f46e5';
    }

    private function iconeValide(string $icone): string
    {
        return in_array($icone, icones_dossiers(), true) ? $icone : '📁';
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
