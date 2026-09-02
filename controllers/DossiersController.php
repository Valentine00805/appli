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

        $dossiers = self::pourUtilisateur($userId, true);

        // Pour chaque dossier, sa propre branche : la liste des parents qu'on
        // ne peut pas lui donner sans refermer l'arborescence sur elle-même.
        $descendants = [];
        foreach ($dossiers as $d) {
            $descendants[(int) $d['id']] = self::avecDescendants($userId, (int) $d['id']);
        }

        Vue::afficher('dossiers/index', [
            'dossiers' => $dossiers,
            'descendants' => $descendants,
            'palette'  => self::PALETTE,
            'icones'   => icones_dossiers(),
            'sansDossier' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IS NULL',
                [$userId]
            ),
        ], 'Mes dossiers');
    }

    /**
     * Les dossiers d'un compte, à plat mais dans l'ordre de l'arborescence :
     * chaque dossier suivi de ses enfants. Chaque ligne reçoit sa
     * « profondeur » (0 à la racine) et le chemin complet de son nom.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pourUtilisateur(int $userId, bool $avecComptes = false): array
    {
        $compte = $avecComptes
            ? ', (SELECT COUNT(*) FROM cours c WHERE c.dossier_id = d.id) AS nb_cours'
            : '';

        $lignes = Database::all(
            "SELECT d.*$compte FROM dossiers d WHERE d.user_id = ? ORDER BY d.position, d.id",
            [$userId]
        );

        // Regroupement par parent, puis parcours en profondeur.
        $enfants = [];
        foreach ($lignes as $d) {
            $enfants[(int) ($d['parent_id'] ?? 0)][] = $d;
        }

        $arbre = [];
        $descendre = static function (int $parent, int $profondeur, string $chemin) use (
            &$descendre, &$arbre, $enfants
        ): void {
            foreach ($enfants[$parent] ?? [] as $dossier) {
                $dossier['profondeur'] = $profondeur;
                $dossier['chemin'] = $chemin === ''
                    ? (string) $dossier['nom']
                    : $chemin . ' / ' . (string) $dossier['nom'];
                $arbre[] = $dossier;
                // La profondeur est bornée : une arborescence saine n'y arrive
                // jamais, mais une donnée abîmée ne doit pas boucler sans fin.
                if ($profondeur < 20) {
                    $descendre((int) $dossier['id'], $profondeur + 1, $dossier['chemin']);
                }
            }
        };
        $descendre(0, 0, '');

        return $arbre;
    }

    /**
     * Un dossier et tous ses descendants, sous forme d'identifiants.
     * Sert à filtrer les cours : ouvrir un dossier montre aussi ce que
     * contiennent ses sous-dossiers.
     *
     * @return array<int, int>
     */
    public static function avecDescendants(int $userId, int $dossierId): array
    {
        $parents = [];
        foreach (Database::all('SELECT id, parent_id FROM dossiers WHERE user_id = ?', [$userId]) as $d) {
            $parents[(int) ($d['parent_id'] ?? 0)][] = (int) $d['id'];
        }

        $ids = [$dossierId];
        $aVisiter = [$dossierId];
        // Parcours en largeur, borné par le nombre de dossiers du compte.
        while ($aVisiter !== []) {
            $courant = array_pop($aVisiter);
            foreach ($parents[$courant] ?? [] as $enfant) {
                if (!in_array($enfant, $ids, true)) {
                    $ids[] = $enfant;
                    $aVisiter[] = $enfant;
                }
            }
        }
        return $ids;
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

        $parent = self::valide($userId, $_POST['parent_id'] ?? null);

        // Un nouveau dossier se range à la fin de ses frères.
        $rang = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers
             WHERE user_id = ? AND parent_id ' . ($parent === null ? 'IS NULL' : '= ?'),
            $parent === null ? [$userId] : [$userId, $parent]
        );

        Database::run(
            'INSERT INTO dossiers (user_id, parent_id, nom, couleur, icone, position)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $parent, $nom, $this->couleurValide(post('couleur')),
             $this->iconeValide(post('icone')), $rang]
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

        // Un dossier ne peut pas être rangé dans lui-même ni dans l'un de ses
        // propres sous-dossiers : l'arborescence se refermerait sur elle-même.
        $parent = self::valide($userId, $_POST['parent_id'] ?? null);
        if ($parent !== null && in_array($parent, self::avecDescendants($userId, $id), true)) {
            Session::flash('erreur', 'Un dossier ne peut pas être rangé dans lui-même ou dans un de ses sous-dossiers.');
            redirect('organisation/dossiers');
        }

        Database::run(
            'UPDATE dossiers SET nom = ?, parent_id = ?, couleur = ?, icone = ? WHERE id = ? AND user_id = ?',
            [$nom, $parent, $this->couleurValide(post('couleur')),
             $this->iconeValide(post('icone')), $id, $userId]
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
        $nbEnfants = (int) Database::valeur(
            'SELECT COUNT(*) FROM dossiers WHERE parent_id = ? AND user_id = ?',
            [$id, $userId]
        );

        // Rien n'est emporté : les contraintes remettent les cours sans dossier
        // et font remonter les sous-dossiers à la racine.
        Database::run('DELETE FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);

        $details = [];
        if ($nb > 0) {
            $details[] = 'ses ' . $nb . ' cours ' . ($nb > 1 ? 'sont conservés' : 'est conservé') . ', sans dossier';
        }
        if ($nbEnfants > 0) {
            $details[] = 'ses ' . $nbEnfants . ' sous-dossier' . ($nbEnfants > 1 ? 's remontent' : ' remonte')
                . ' à la racine';
        }

        Session::flash('succes', 'Dossier « ' . $dossier['nom'] . ' » supprimé'
            . ($details === [] ? '.' : ' : ' . implode(', ', $details) . '.'));
        redirect('organisation/dossiers');
    }

    /** Monte ou descend un dossier d'un cran. */
    public function deplacer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $sens = post('sens') === 'bas' ? 'bas' : 'haut';

        // On ne réordonne qu'entre frères : un dossier ne change pas de parent
        // en montant, il double celui qui le précède au même niveau.
        $parent = Database::valeur('SELECT parent_id FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);
        $ids = array_map(
            static fn (array $d): int => (int) $d['id'],
            Database::all(
                'SELECT id FROM dossiers WHERE user_id = ? AND parent_id '
                . ($parent === null ? 'IS NULL' : '= ?') . ' ORDER BY position, id',
                $parent === null ? [$userId] : [$userId, (int) $parent]
            )
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
