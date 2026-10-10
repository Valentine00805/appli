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
            'matieres' => Database::all('SELECT id, nom, couleur FROM matieres WHERE user_id = ? ORDER BY nom', [$userId]),
            'dossiers' => $dossiers,
            'descendants' => $descendants,
            'palette'  => self::PALETTE,
            'icones'   => icones_dossiers(),
            'sansDossier' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IS NULL',
                [$userId]
            ),
        ], t('dos.titre'));
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

    /** Une matière du compte, ou null (jamais celle d'un autre). */
    public static function matiereValide(int $userId, mixed $matiereId): ?int
    {
        $id = entier_ou_null($matiereId);
        if ($id === null) {
            return null;
        }

        return Database::valeur('SELECT id FROM matieres WHERE id = ? AND user_id = ?', [$id, $userId]) === null ? null : $id;
    }

    /**
     * La matière d'un dossier, ou null s'il n'en a pas (ou s'il n'y a pas de dossier).
     *
     * C'est elle que reçoit un cours qu'on y range, quand il n'a pas encore de matière. Un dossier garde la sienne : celle de son dossier parent ne
     * lui est donnée qu'à sa création, pas ensuite.
     */
    public static function matiereDu(int $userId, ?int $dossierId): ?int
    {
        if ($dossierId === null) {
            return null;
        }
        $m = Database::valeur('SELECT matiere_id FROM dossiers WHERE id = ? AND user_id = ?', [$dossierId, $userId]);

        return $m === null || $m === false ? null : (int) $m;
    }

    /**
     * Donne cette matière aux cours de la branche (le dossier et ses sous-dossiers) qui n'en ont pas, et aux sous-dossiers qui n'en ont pas
     * non plus. Une matière déjà posée n'est jamais remplacée.
     *
     * @return int  combien de cours l'ont reçue
     */
    public static function appliquerMatiere(int $userId, int $dossierId, int $matiereId): int
    {
        $branche = self::avecDescendants($userId, $dossierId);
        $trous = implode(', ', array_fill(0, count($branche), '?'));
        $cours = Database::run(
            'UPDATE cours SET matiere_id = ? WHERE user_id = ? AND matiere_id IS NULL AND dossier_id IN (' . $trous . ')',
            array_merge([$matiereId, $userId], $branche)
        )->rowCount();
        Database::run(
            'UPDATE dossiers SET matiere_id = ? WHERE user_id = ? AND matiere_id IS NULL AND id IN (' . $trous . ')',
            array_merge([$matiereId, $userId], $branche)
        );

        return $cours;
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
            Session::flash('erreur', t('dos.fl.nom_manquant'));
            repartir_vers('organisation/dossiers');
        }
        if (Database::valeur('SELECT id FROM dossiers WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
            Session::flash('erreur', t('dos.fl.deja', ['nom' => $nom]));
            repartir_vers('organisation/dossiers');
        }

        $parent = self::valide($userId, $_POST['parent_id'] ?? null);
        // La matière choisie ; sans choix, celle du dossier où il se range : un sous-dossier reste dans la matière de son parent.
        $matiere = self::matiereValide($userId, $_POST['matiere_id'] ?? null) ?? self::matiereDu($userId, $parent);

        // Un nouveau dossier se range à la fin de ses frères.
        $rang = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers
             WHERE user_id = ? AND parent_id ' . ($parent === null ? 'IS NULL' : '= ?'),
            $parent === null ? [$userId] : [$userId, $parent]
        );

        Database::run(
            'INSERT INTO dossiers (user_id, parent_id, matiere_id, nom, couleur, icone, position)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $parent, $matiere, $nom, $this->couleurValide(post('couleur')),
             $this->iconeValide(post('icone')), $rang]
        );

        Session::flash('succes', t('dos.fl.cree', ['nom' => $nom]));
        // Créer depuis la colonne des cours ne doit pas déporter ailleurs.
        repartir_vers('organisation/dossiers');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $avant = Database::one('SELECT nom, parent_id, matiere_id FROM dossiers WHERE id = ? AND user_id = ?',
            [$id, $userId]);
        if ($avant === null) {
            $this->introuvable();
        }

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', t('dos.fl.nom_obligatoire'));
            redirect('organisation/dossiers');
        }
        $doublon = Database::valeur(
            'SELECT id FROM dossiers WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', t('dos.fl.autre'));
            redirect('organisation/dossiers');
        }

        // Un dossier ne peut pas être rangé dans lui-même ni dans l'un de ses
        // propres sous-dossiers : l'arborescence se refermerait sur elle-même.
        $parent = self::valide($userId, $_POST['parent_id'] ?? null);
        if ($parent !== null && in_array($parent, self::avecDescendants($userId, $id), true)) {
            Session::flash('erreur', t('dos.fl.boucle'));
            redirect('organisation/dossiers');
        }

        Database::run(
            'UPDATE dossiers SET nom = ?, parent_id = ?, couleur = ?, icone = ? WHERE id = ? AND user_id = ?',
            [$nom, $parent, $this->couleurValide(post('couleur')),
             $this->iconeValide(post('icone')), $id, $userId]
        );

        // La matière : seulement si le formulaire en parle (celui de la colonne des cours, plus ancien, n'y touche pas).
        $matiereDonnee = 0;
        $matiereChangee = false;
        if (array_key_exists('matiere_id', $_POST)) {
            $matiere = self::matiereValide($userId, $_POST['matiere_id']);
            $matiereChangee = ($avant['matiere_id'] === null ? null : (int) $avant['matiere_id']) !== $matiere;
            Database::run('UPDATE dossiers SET matiere_id = ? WHERE id = ? AND user_id = ?', [$matiere, $id, $userId]);
            // Les cours du dossier (et de ses sous-dossiers) qui n'ont pas de matière la reçoivent, si on le demande.
            if ($matiere !== null && (string) ($_POST['appliquer_matiere'] ?? '') === '1') {
                $matiereDonnee = self::appliquerMatiere($userId, $id, $matiere);
            }
        }

        /*
         * Un même formulaire sert à renommer et à déplacer : le message dit ce
         * qui a bougé, plutôt que d'annoncer un renommage qui n'a pas eu lieu.
         */
        $renomme = (string) $avant['nom'] !== $nom;
        $deplace = ($avant['parent_id'] === null ? null : (int) $avant['parent_id']) !== $parent;
        $ou = $parent === null
            ? t('dos.fl.racine')
            : t('dos.fl.dans', ['nom' => (string) Database::valeur('SELECT nom FROM dossiers WHERE id = ?', [$parent])]);

        $message = match (true) {
            $renomme && $deplace => t('dos.fl.renomme_range', ['nom' => $nom, 'ou' => $ou]),
            $deplace             => t('dos.fl.range', ['nom' => $nom, 'ou' => $ou]),
            $renomme             => t('dos.fl.renomme', ['nom' => $nom]),
            $matiereChangee      => t('dos.fl.matiere_changee', ['nom' => $nom]),
            default              => t('dos.fl.inchange', ['nom' => $nom]),
        };
        if ($matiereDonnee > 0) {
            $message .= ' ' . tn('dos.fl.matiere_donnee', $matiereDonnee);
        }
        Session::flash('succes', $message);
        // Modifier depuis la colonne des cours ne doit pas déporter ailleurs.
        repartir_vers('organisation/dossiers');
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
        Partages::oublier('dossier', $id);

        $details = [];
        if ($nb > 0) {
            $details[] = tn('dos.fl.cours_gardes', $nb);
        }
        if ($nbEnfants > 0) {
            $details[] = tn('dos.fl.enfants', $nbEnfants);
        }

        Session::flash('succes', $details === []
            ? t('dos.fl.supprime', ['nom' => (string) $dossier['nom']])
            : t('dos.fl.supprime_details', [
                'nom' => (string) $dossier['nom'], 'details' => implode(', ', $details),
            ]));
        // Supprimer depuis la colonne des cours ne doit pas déporter ailleurs.
        repartir_vers('organisation/dossiers');
    }

    /**
     * Range un dossier dans un autre, sans passer par le formulaire.
     * C'est ce qu'appelle le glisser-déposer.
     */
    public function ranger(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $id = self::valide($userId, $_POST['dossier'] ?? null);
        if ($id === null) {
            $this->introuvable();
        }

        // Un parent vide signifie « à la racine ».
        $parent = self::valide($userId, $_POST['parent'] ?? null);

        // Même garde-fou que dans le formulaire : on ne referme pas la branche.
        if ($parent !== null && in_array($parent, self::avecDescendants($userId, $id), true)) {
            Session::flash('erreur', t('dos.fl.boucle'));
            redirect('organisation/dossiers');
        }

        // Il arrive en fin de ses nouveaux frères.
        $rang = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers
             WHERE user_id = ? AND parent_id ' . ($parent === null ? 'IS NULL' : '= ?'),
            $parent === null ? [$userId] : [$userId, $parent]
        );

        Database::run(
            'UPDATE dossiers SET parent_id = ?, position = ? WHERE id = ? AND user_id = ?',
            [$parent, $rang, $id, $userId]
        );

        $nom = Database::valeur('SELECT nom FROM dossiers WHERE id = ? AND user_id = ?', [$id, $userId]);
        Session::flash('succes', $parent === null
            ? t('dos.fl.remonte', ['nom' => (string) $nom])
            : t('dos.fl.range_dans', [
                'nom' => (string) $nom,
                'parent' => (string) Database::valeur(
                    'SELECT nom FROM dossiers WHERE id = ? AND user_id = ?', [$parent, $userId]),
            ]));

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

    /**
     * Ouvre ou ferme la colonne des dossiers de « Mes cours ».
     *
     * Un geste d'affichage, comme le volet des agendas : rien n'est rangé ni
     * déplacé. Il est retenu, pour que la liste revienne comme on l'a laissée
     * après un filtre ou une recherche.
     */
    public function fermerColonne(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        self::fermerLaColonne(Auth::id(), ($_POST['ferme'] ?? '') === '1');

        if (veut_du_json()) {
            repondre_json(['fait' => true]);
        }
        repartir_vers('cours');
    }

    /** La colonne des dossiers est-elle fermée ? */
    public static function colonneFermee(int $userId): bool
    {
        return (int) Database::valeur('SELECT dossiers_ferme FROM users WHERE id = ?', [$userId]) === 1;
    }

    private static function fermerLaColonne(int $userId, bool $ferme): void
    {
        Database::run('UPDATE users SET dossiers_ferme = ? WHERE id = ?', [$ferme ? 1 : 0, $userId]);
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
