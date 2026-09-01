<?php
declare(strict_types=1);

/**
 * Listes de tâches : des listes, des tâches datées, des cases à cocher.
 *
 * Une tâche appartient toujours à une liste. Cocher une tâche ne la supprime
 * pas : elle descend dans la partie « terminées » de sa liste, d'où elle peut
 * être décochée. Rien ne disparaît sans une action explicite.
 */
final class TachesController
{
    /** Palette proposée pour les listes. */
    public const PALETTE = [
        '#4f46e5', '#0ea5e9', '#059669', '#65a30d', '#ca8a04',
        '#ea580c', '#dc2626', '#db2777', '#7c3aed', '#475569',
    ];

    /** Filtres disponibles en haut de page. */
    private const VUES = ['tout', 'retard', 'aujourdhui', 'semaine', 'terminees'];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $vue = in_array($_GET['vue'] ?? '', self::VUES, true) ? (string) $_GET['vue'] : 'tout';

        $listes = Database::all(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id AND t.faite = 0) AS reste,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id AND t.faite = 1) AS finies,
                    (SELECT COUNT(*) FROM taches t
                      WHERE t.liste_id = l.id AND t.faite = 0
                        AND t.echeance IS NOT NULL AND t.echeance < CURDATE())    AS en_retard,
                    (SELECT MIN(t.echeance) FROM taches t
                      WHERE t.liste_id = l.id AND t.faite = 0
                        AND t.echeance IS NOT NULL AND t.echeance >= CURDATE())   AS prochaine
             FROM listes_taches l
             WHERE l.user_id = ?
             ORDER BY l.created_at, l.id',
            [$userId]
        );

        // Un seul aller-retour en base : les tâches sont ensuite réparties par liste.
        $taches = Database::all(
            'SELECT * FROM taches
             WHERE user_id = ?' . $this->conditionVue($vue) . '
             ORDER BY faite,
                      echeance IS NULL, echeance,
                      created_at, id',
            [$userId]
        );

        $parListe = [];
        foreach ($taches as $tache) {
            $parListe[(int) $tache['liste_id']][] = $tache;
        }

        Vue::afficher('taches/index', [
            'listes'   => $listes,
            'parListe' => $parListe,
            'vue'      => $vue,
            'compteurs' => $this->compteurs($userId),
            'palette'  => self::PALETTE,
            'icones'   => icones_listes(),
        ], 'Mes tâches');
    }

    /* --- Listes --------------------------------------------------------- */

    public function creerListe(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', 'Donnez un nom à votre liste.');
            redirect('taches');
        }
        if (Database::valeur('SELECT id FROM listes_taches WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
            Session::flash('erreur', 'Vous avez déjà une liste nommée « ' . $nom . ' ».');
            redirect('taches');
        }

        Database::run(
            'INSERT INTO listes_taches (user_id, nom, couleur, icone) VALUES (?, ?, ?, ?)',
            [$userId, $nom, $this->couleurValide(post('couleur')), $this->iconeValide(post('icone'))]
        );

        Session::flash('succes', 'Liste « ' . $nom . ' » créée.');
        redirect('taches');
    }

    public function modifierListe(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM listes_taches WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', 'Le nom de la liste est obligatoire.');
            redirect('taches');
        }
        $doublon = Database::valeur(
            'SELECT id FROM listes_taches WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', 'Une autre liste porte déjà ce nom.');
            redirect('taches');
        }

        Database::run(
            'UPDATE listes_taches SET nom = ?, couleur = ?, icone = ? WHERE id = ? AND user_id = ?',
            [$nom, $this->couleurValide(post('couleur')), $this->iconeValide(post('icone')), $id, $userId]
        );

        Session::flash('succes', 'Liste mise à jour.');
        redirect('taches');
    }

    public function supprimerListe(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $liste = Database::one('SELECT nom FROM listes_taches WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($liste === null) {
            $this->introuvable();
        }

        $nb = (int) Database::valeur('SELECT COUNT(*) FROM taches WHERE liste_id = ?', [$id]);

        // Les tâches partent avec la liste (contrainte ON DELETE CASCADE).
        Database::run('DELETE FROM listes_taches WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $nb === 0
            ? 'Liste « ' . $liste['nom'] . ' » supprimée.'
            : 'Liste « ' . $liste['nom'] . ' » supprimée, avec ses ' . $nb . ' tâche' . ($nb > 1 ? 's' : '') . '.');
        redirect('taches');
    }

    /** Retire les tâches déjà cochées d'une liste. */
    public function viderTerminees(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM listes_taches WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $nb = Database::run(
            'DELETE FROM taches WHERE liste_id = ? AND user_id = ? AND faite = 1',
            [$id, $userId]
        )->rowCount();

        Session::flash('succes', $nb === 0
            ? 'Aucune tâche terminée à retirer.'
            : $nb . ' tâche' . ($nb > 1 ? 's' : '') . ' terminée' . ($nb > 1 ? 's' : '') . ' retirée' . ($nb > 1 ? 's' : '') . '.');
        redirect('taches');
    }

    /* --- Tâches --------------------------------------------------------- */

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $listeId = $this->listeValide($userId, $_POST['liste_id'] ?? null);
        if ($listeId === null) {
            Session::flash('erreur', 'Choisissez la liste dans laquelle ranger cette tâche.');
            redirect('taches');
        }

        $titre = mb_substr(post('titre'), 0, 200);
        if ($titre === '') {
            Session::flash('erreur', 'Écrivez ce qu’il y a à faire.');
            redirect('taches');
        }

        Database::run(
            'INSERT INTO taches (user_id, liste_id, titre, echeance) VALUES (?, ?, ?, ?)',
            [$userId, $listeId, $titre, $this->dateValide(post('echeance'))]
        );

        redirect('taches');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM taches WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $titre = mb_substr(post('titre'), 0, 200);
        if ($titre === '') {
            Session::flash('erreur', 'Le libellé de la tâche ne peut pas être vide.');
            redirect('taches');
        }

        // La liste peut changer : déplacer une tâche d'une liste à l'autre.
        $listeId = $this->listeValide($userId, $_POST['liste_id'] ?? null);
        if ($listeId === null) {
            Session::flash('erreur', 'Liste de destination introuvable.');
            redirect('taches');
        }

        Database::run(
            'UPDATE taches SET titre = ?, echeance = ?, liste_id = ? WHERE id = ? AND user_id = ?',
            [$titre, $this->dateValide(post('echeance')), $listeId, $id, $userId]
        );

        Session::flash('succes', 'Tâche mise à jour.');
        redirect('taches');
    }

    /** Coche ou décoche une tâche. */
    public function basculer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $tache = Database::one('SELECT faite FROM taches WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($tache === null) {
            $this->introuvable();
        }

        $faite = (int) $tache['faite'] === 1 ? 0 : 1;
        Database::run(
            'UPDATE taches SET faite = ?, faite_le = ? WHERE id = ? AND user_id = ?',
            [$faite, $faite === 1 ? date('Y-m-d H:i:s') : null, $id, $userId]
        );

        // Retour à l'endroit exact d'où l'on vient : le filtre en cours est conservé.
        redirect('taches', $this->filtreCourant());
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        Database::run('DELETE FROM taches WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', 'Tâche supprimée.');
        redirect('taches', $this->filtreCourant());
    }

    /* --- Utilisé par le tableau de bord ---------------------------------- */

    /**
     * Tâches en retard ou à faire d'ici quelques jours, listes comprises.
     * @return array<int, array<string, mixed>>
     */
    public static function aVenir(int $userId, int $jours = 7, int $limite = 6): array
    {
        return Database::all(
            'SELECT t.*, l.nom AS liste_nom, l.couleur AS liste_couleur, l.icone AS liste_icone
             FROM taches t
             JOIN listes_taches l ON l.id = t.liste_id
             WHERE t.user_id = ?
               AND t.faite = 0
               AND t.echeance IS NOT NULL
               AND t.echeance <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY t.echeance, t.id
             LIMIT ' . (int) $limite,
            [$userId, $jours]
        );
    }

    /** Nombre de tâches non faites, pour la pastille du tableau de bord. */
    public static function resteAFaire(int $userId): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM taches WHERE user_id = ? AND faite = 0', [$userId]);
    }

    /* --- Interne -------------------------------------------------------- */

    /** Fragment SQL correspondant au filtre choisi. */
    private function conditionVue(string $vue): string
    {
        return match ($vue) {
            'retard'     => ' AND faite = 0 AND echeance IS NOT NULL AND echeance < CURDATE()',
            'aujourdhui' => ' AND faite = 0 AND echeance = CURDATE()',
            'semaine'    => ' AND faite = 0 AND echeance IS NOT NULL'
                          . ' AND echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            'terminees'  => ' AND faite = 1',
            default      => '',
        };
    }

    /** Effectifs affichés sur les onglets de filtre. */
    private function compteurs(int $userId): array
    {
        $ligne = Database::one(
            'SELECT
               SUM(faite = 0)                                                      AS a_faire,
               SUM(faite = 0 AND echeance IS NOT NULL AND echeance <  CURDATE())   AS retard,
               SUM(faite = 0 AND echeance = CURDATE())                             AS aujourdhui,
               SUM(faite = 0 AND echeance IS NOT NULL
                   AND echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS semaine,
               SUM(faite = 1)                                                      AS terminees
             FROM taches WHERE user_id = ?',
            [$userId]
        ) ?? [];

        return [
            'a_faire'    => (int) ($ligne['a_faire'] ?? 0),
            'retard'     => (int) ($ligne['retard'] ?? 0),
            'aujourdhui' => (int) ($ligne['aujourdhui'] ?? 0),
            'semaine'    => (int) ($ligne['semaine'] ?? 0),
            'terminees'  => (int) ($ligne['terminees'] ?? 0),
        ];
    }

    /** Conserve le filtre en cours lors d'une redirection. */
    private function filtreCourant(): array
    {
        $vue = $_POST['vue'] ?? '';
        return in_array($vue, self::VUES, true) && $vue !== 'tout' ? ['vue' => $vue] : [];
    }

    /** Vérifie qu'une liste existe et appartient bien au compte. */
    private function listeValide(int $userId, mixed $listeId): ?int
    {
        $id = entier_ou_null($listeId);
        if ($id === null) {
            return null;
        }
        $existe = Database::valeur('SELECT id FROM listes_taches WHERE id = ? AND user_id = ?', [$id, $userId]);
        return $existe === null ? null : $id;
    }

    /** Une date au format AAAA-MM-JJ, ou null si le champ est vide ou aberrant. */
    private function dateValide(string $saisie): ?string
    {
        if ($saisie === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $saisie);
        return $date !== false && $date->format('Y-m-d') === $saisie ? $saisie : null;
    }

    private function couleurValide(string $couleur): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#4f46e5';
    }

    private function iconeValide(string $icone): string
    {
        return in_array($icone, icones_listes(), true) ? $icone : '📋';
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
