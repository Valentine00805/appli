<?php
declare(strict_types=1);

/**
 * Les travaux de groupe.
 *
 * Un projet réunit des membres : des amis invités, qui acceptent ou refusent,
 * et des personnes sans compte, qui ne sont qu'un nom — on leur confie des
 * tâches, et elles suivent le projet par le lien public, en lecture seule.
 *
 * Tous les membres qui ont un compte font vivre le projet : les tâches (qui
 * fait quoi), les fichiers, le document écrit ensemble, les échéances. Les
 * administrateurs, en plus, invitent, retirent, ouvrent le lien public et
 * suppriment le projet ; il en reste toujours un.
 *
 * Chaque échéance a sa copie dans le calendrier de chaque membre, avec ses
 * rappels : le projet la tient à jour quand elle change, et la retire à qui
 * quitte le groupe.
 */
final class Travaux
{
    public const NOM_MAX = 120;
    public const MEMBRES_MAX = 30;

    /** Versions du document gardées pour revenir en arrière. */
    public const VERSIONS_MAX = 30;

    public const STATUTS = [
        'a_faire'  => ['nom' => 'À faire',  'icone' => '⬜'],
        'en_cours' => ['nom' => 'En cours', 'icone' => '⏳'],
        'fait'     => ['nom' => 'Fait',     'icone' => '✅'],
    ];

    public static function dossier(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'travaux';
    }

    // --- Le projet et ses membres ----------------------------------------------

    /** Le projet, si la personne en est membre (invitation acceptée). */
    public static function projet(int $projet, int $moi): ?array
    {
        return Database::one(
            "SELECT p.*, pm.id AS mon_membre_id, pm.role
               FROM projets p
               JOIN projet_membres pm ON pm.projet_id = p.id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE p.id = ?",
            [$moi, $projet]);
    }

    public static function estAdmin(int $projet, int $moi): bool
    {
        return (self::projet($projet, $moi)['role'] ?? '') === 'admin';
    }

    /**
     * Les membres : administrateurs d'abord, puis les comptes, puis les noms
     * sans compte ; les invités en attente à la fin.
     */
    public static function membres(int $projet): array
    {
        return Database::all(
            "SELECT pm.*, COALESCE(u.pseudo, u.nom, pm.nom) AS nom_affiche,
                    (SELECT COUNT(*) FROM projet_taches t WHERE t.membre_id = pm.id AND t.statut <> 'fait') AS a_faire,
                    (SELECT COUNT(*) FROM projet_taches t WHERE t.membre_id = pm.id AND t.statut = 'fait') AS faites
               FROM projet_membres pm
               LEFT JOIN users u ON u.id = pm.user_id
              WHERE pm.projet_id = ?
              ORDER BY pm.statut = 'invite', pm.role = 'admin' DESC, pm.user_id IS NULL, nom_affiche",
            [$projet]);
    }

    /** Les membres à qui l'on peut confier une tâche : ceux qui sont là. */
    public static function membresActifs(int $projet): array
    {
        return array_values(array_filter(self::membres($projet),
            static fn (array $m): bool => $m['statut'] === 'membre'));
    }

    /** Mes projets, avec de quoi dire où ils en sont. */
    public static function mesProjets(int $moi): array
    {
        return Database::all(
            "SELECT p.id, p.nom, p.description, pm.role, pm.id AS mon_membre_id,
                    (SELECT COUNT(*) FROM projet_membres x WHERE x.projet_id = p.id AND x.statut = 'membre') AS nb_membres,
                    (SELECT COUNT(*) FROM projet_taches t WHERE t.projet_id = p.id) AS nb_taches,
                    (SELECT COUNT(*) FROM projet_taches t WHERE t.projet_id = p.id AND t.statut = 'fait') AS nb_faites,
                    (SELECT COUNT(*) FROM projet_taches t WHERE t.membre_id = pm.id AND t.statut <> 'fait') AS mes_taches,
                    (SELECT MIN(e.debut) FROM projet_echeances e WHERE e.projet_id = p.id AND e.fin >= ?) AS prochaine,
                    (SELECT e.titre FROM projet_echeances e WHERE e.projet_id = p.id AND e.fin >= ?
                      ORDER BY e.debut LIMIT 1) AS prochaine_titre
               FROM projets p
               JOIN projet_membres pm ON pm.projet_id = p.id AND pm.user_id = ? AND pm.statut = 'membre'
              ORDER BY prochaine IS NULL, prochaine, p.created_at DESC",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $moi]);
    }

    /** Les projets où l'on m'invite. */
    public static function invitations(int $moi): array
    {
        return Database::all(
            "SELECT p.id, p.nom, p.description, COALESCE(u.pseudo, u.nom) AS invite_par_nom,
                    (SELECT COUNT(*) FROM projet_membres x WHERE x.projet_id = p.id AND x.statut = 'membre') AS nb_membres
               FROM projet_membres pm
               JOIN projets p ON p.id = pm.projet_id
               LEFT JOIN users u ON u.id = pm.invite_par
              WHERE pm.user_id = ? AND pm.statut = 'invite'
              ORDER BY pm.created_at DESC",
            [$moi]);
    }

    public static function nbInvitations(int $moi): int
    {
        return (int) Database::valeur(
            "SELECT COUNT(*) FROM projet_membres WHERE user_id = ? AND statut = 'invite'", [$moi]);
    }

    private static function nettoyer(string $texte, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $texte)), 0, $max);
    }

    /**
     * Crée un projet, et invite les amis cochés.
     *
     * @return array{0: ?int, 1: ?string} le projet, ou la raison du refus
     */
    public static function creer(int $moi, string $nom, string $description, array $amis): array
    {
        $nom = self::nettoyer($nom, self::NOM_MAX);
        if ($nom === '') {
            return [null, 'Donnez un nom au travail de groupe.'];
        }
        Database::run('INSERT INTO projets (nom, description, cree_par) VALUES (?, ?, ?)',
            [$nom, trim($description) === '' ? null : mb_substr(trim($description), 0, 2000), $moi]);
        $projet = Database::dernierId();
        Database::run(
            "INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')",
            [$projet, $moi]);
        self::creerTypesDeDepart($projet);

        [, $refus] = $amis === [] ? [0, null] : self::inviter($moi, $projet, $amis);

        return [$projet, $refus];
    }

    public static function modifier(int $moi, int $projet, string $nom, string $description): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return 'Seuls les administrateurs du projet peuvent le renommer.';
        }
        $nom = self::nettoyer($nom, self::NOM_MAX);
        if ($nom === '') {
            return 'Donnez un nom au travail de groupe.';
        }
        Database::run('UPDATE projets SET nom = ?, description = ? WHERE id = ?',
            [$nom, trim($description) === '' ? null : mb_substr(trim($description), 0, 2000), $projet]);
        // Les copies du calendrier portent le nom du projet.
        foreach (Database::all('SELECT id FROM projet_echeances WHERE projet_id = ?', [$projet]) as $e) {
            self::mettreAJourCopies((int) $e['id']);
        }

        return null;
    }

    private static function nbMembres(int $projet): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ?', [$projet]);
    }

    /**
     * Invite des amis (réservé aux administrateurs). Ils rejoignent le projet
     * quand ils acceptent.
     *
     * @return array{0: int, 1: ?string} le nombre d'invités, ou la raison du refus
     */
    public static function inviter(int $moi, int $projet, array $ids): array
    {
        if (!self::estAdmin($projet, $moi)) {
            return [0, 'Seuls les administrateurs du projet peuvent inviter.'];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids),
            static fn (int $id): bool => $id > 0 && $id !== $moi)));
        foreach ($ids as $id) {
            if (!Amis::sontAmis($moi, $id)) {
                return [0, 'Vous ne pouvez inviter que vos amis.'];
            }
        }
        $deja = array_map('intval', array_column(Database::all(
            'SELECT user_id FROM projet_membres WHERE projet_id = ? AND user_id IS NOT NULL', [$projet]), 'user_id'));
        $ids = array_values(array_diff($ids, $deja));
        if ($ids === []) {
            return [0, 'Choisissez au moins un ami qui n’est pas déjà dans le projet.'];
        }
        if (self::nbMembres($projet) + count($ids) > self::MEMBRES_MAX) {
            return [0, 'Un travail de groupe réunit ' . self::MEMBRES_MAX . ' personnes au plus.'];
        }

        $nomProjet = (string) Database::valeur('SELECT nom FROM projets WHERE id = ?', [$projet]);
        $auteur = (string) (Amis::compte($moi)['pseudo'] ?? 'Quelqu’un');
        foreach ($ids as $id) {
            Database::run(
                "INSERT INTO projet_membres (projet_id, user_id, role, statut, invite_par) VALUES (?, ?, 'membre', 'invite', ?)",
                [$projet, $id, $moi]);
            FileNotifications::ajouter($id, 'projet', [
                'title' => '👥 Travail de groupe',
                'body'  => $auteur . ' vous invite dans « ' . $nomProjet . ' ».',
                'url'   => url('travaux'),
                'tag'   => 'projet-invitation-' . $projet,
            ]);
        }

        return [count($ids), null];
    }

    /** Ajoute une personne sans compte : un simple nom, à qui confier des tâches. */
    public static function ajouterSansCompte(int $moi, int $projet, string $nom): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return 'Seuls les administrateurs du projet peuvent ajouter des membres.';
        }
        $nom = self::nettoyer($nom, 60);
        if ($nom === '') {
            return 'Donnez le nom de la personne.';
        }
        if (self::nbMembres($projet) >= self::MEMBRES_MAX) {
            return 'Un travail de groupe réunit ' . self::MEMBRES_MAX . ' personnes au plus.';
        }
        Database::run(
            "INSERT INTO projet_membres (projet_id, user_id, nom, role, statut, invite_par) VALUES (?, NULL, ?, 'membre', 'membre', ?)",
            [$projet, $nom, $moi]);

        return null;
    }

    /** J'accepte l'invitation : les échéances arrivent dans mon calendrier. */
    public static function accepter(int $moi, int $projet): bool
    {
        $fait = Database::run(
            "UPDATE projet_membres SET statut = 'membre' WHERE projet_id = ? AND user_id = ? AND statut = 'invite'",
            [$projet, $moi])->rowCount() > 0;
        if (!$fait) {
            return false;
        }
        foreach (Database::all('SELECT id FROM projet_echeances WHERE projet_id = ? AND fin >= ?', [$projet, date('Y-m-d H:i:s')]) as $e) {
            self::copier((int) $e['id'], $moi);
        }
        $conversation = (int) Database::valeur('SELECT conversation_id FROM projets WHERE id = ?', [$projet]);
        if ($conversation > 0) {
            Conversations::ajouterDepuisProjet($conversation, $moi, null);
        }

        return true;
    }

    public static function refuser(int $moi, int $projet): bool
    {
        return Database::run(
            "DELETE FROM projet_membres WHERE projet_id = ? AND user_id = ? AND statut = 'invite'",
            [$projet, $moi])->rowCount() > 0;
    }

    /** Retire du calendrier d'un compte les copies des échéances du projet. */
    private static function retirerCopies(int $projet, int $userId): void
    {
        Database::run(
            'DELETE e FROM evenements e JOIN projet_echeances pe ON pe.id = e.projet_echeance_id
              WHERE pe.projet_id = ? AND e.user_id = ?',
            [$projet, $userId]);
    }

    /**
     * Quitte le projet. S'il n'y reste plus d'administrateur, le plus ancien
     * membre avec un compte le devient ; s'il n'y reste plus aucun compte, le
     * projet est effacé, ses fichiers compris.
     */
    public static function quitter(int $moi, int $projet): bool
    {
        if (self::projet($projet, $moi) === null) {
            return false;
        }
        self::retirerCopies($projet, $moi);
        Database::run('DELETE FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$projet, $moi]);
        self::garderUnAdmin($projet);

        return true;
    }

    private static function garderUnAdmin(int $projet): void
    {
        $comptes = (int) Database::valeur(
            "SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND user_id IS NOT NULL AND statut = 'membre'", [$projet]);
        if ($comptes === 0) {
            self::effacer($projet);
            return;
        }
        $admins = (int) Database::valeur(
            "SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND role = 'admin' AND statut = 'membre'", [$projet]);
        if ($admins === 0) {
            Database::run(
                "UPDATE projet_membres SET role = 'admin'
                  WHERE projet_id = ? AND user_id IS NOT NULL AND statut = 'membre'
                  ORDER BY created_at, id LIMIT 1", [$projet]);
        }
    }

    /**
     * Retire un membre, un invité ou un nom sans compte (réservé aux
     * administrateurs ; pour soi, c'est « quitter »). Ses tâches restent, sans
     * personne pour les faire.
     */
    public static function retirer(int $moi, int $membreId): ?string
    {
        $membre = Database::one('SELECT * FROM projet_membres WHERE id = ?', [$membreId]);
        if ($membre === null || !self::estAdmin((int) $membre['projet_id'], $moi)) {
            return 'Seuls les administrateurs du projet peuvent retirer des membres.';
        }
        if ((int) ($membre['user_id'] ?? 0) === $moi) {
            return 'Pour partir, quittez le projet.';
        }
        if ($membre['user_id'] !== null) {
            self::retirerCopies((int) $membre['projet_id'], (int) $membre['user_id']);
        }
        Database::run('DELETE FROM projet_membres WHERE id = ?', [$membreId]);

        return null;
    }

    /** Nomme administrateur, ou le redevient simple membre. */
    public static function changerRole(int $moi, int $membreId, bool $admin): ?string
    {
        $membre = Database::one('SELECT * FROM projet_membres WHERE id = ?', [$membreId]);
        if ($membre === null || !self::estAdmin((int) $membre['projet_id'], $moi)) {
            return 'Seuls les administrateurs du projet peuvent changer les rôles.';
        }
        if ($membre['user_id'] === null || $membre['statut'] !== 'membre') {
            return 'Seul un membre qui a rejoint le projet peut en être administrateur.';
        }
        if (!$admin) {
            $admins = (int) Database::valeur(
                "SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND role = 'admin'", [(int) $membre['projet_id']]);
            if ($admins <= 1 && $membre['role'] === 'admin') {
                return 'Il faut au moins un administrateur.';
            }
        }
        Database::run('UPDATE projet_membres SET role = ? WHERE id = ?', [$admin ? 'admin' : 'membre', $membreId]);

        return null;
    }

    /** Supprime le projet (réservé aux administrateurs). */
    public static function supprimer(int $moi, int $projet): bool
    {
        if (!self::estAdmin($projet, $moi)) {
            return false;
        }
        self::effacer($projet);

        return true;
    }

    /** Efface le projet et ses fichiers ; les copies du calendrier partent avec les échéances. */
    private static function effacer(int $projet): void
    {
        foreach (Database::all('SELECT nom_stocke FROM projet_fichiers WHERE projet_id = ?', [$projet]) as $f) {
            $chemin = self::dossier() . DIRECTORY_SEPARATOR . basename((string) $f['nom_stocke']);
            if (is_file($chemin)) {
                @unlink($chemin);
            }
        }
        Database::run('DELETE FROM projets WHERE id = ?', [$projet]);
    }

    // --- Qui fait quoi ---------------------------------------------------------

    public static function taches(int $projet): array
    {
        return Database::all(
            "SELECT t.*, COALESCE(u.pseudo, u.nom, pm.nom) AS membre_nom, pm.user_id AS membre_user_id
               FROM projet_taches t
               LEFT JOIN projet_membres pm ON pm.id = t.membre_id
               LEFT JOIN users u ON u.id = pm.user_id
              WHERE t.projet_id = ?
              ORDER BY t.statut = 'fait', t.echeance IS NULL, t.echeance, t.position, t.id",
            [$projet]);
    }

    /** Le membre choisi, s'il est bien du projet et présent ; null sinon. */
    private static function membreValide(int $projet, mixed $membreId): ?int
    {
        $id = entier_ou_null($membreId);
        if ($id === null) {
            return null;
        }
        $trouve = Database::valeur(
            "SELECT id FROM projet_membres WHERE id = ? AND projet_id = ? AND statut = 'membre'", [$id, $projet]);

        return $trouve === null ? null : (int) $trouve;
    }

    private static function dateValide(string $date): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date ? $date : null;
    }

    /** @return int|string la tâche, ou la raison du refus */
    public static function ajouterTache(int $moi, int $projet, string $titre, mixed $membreId, string $echeance, string $note = ''): int|string
    {
        $titre = self::nettoyer($titre, 200);
        if ($titre === '') {
            return 'Donnez un titre à la tâche.';
        }
        $membre = self::membreValide($projet, $membreId);
        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM projet_taches WHERE projet_id = ?', [$projet]);
        Database::run(
            'INSERT INTO projet_taches (projet_id, titre, note, membre_id, echeance, position, cree_par) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projet, $titre, trim($note) === '' ? null : mb_substr(trim($note), 0, 2000), $membre,
             self::dateValide($echeance), $position, $moi]);
        $id = Database::dernierId();
        self::prevenirConfiee($moi, $id);

        return $id;
    }

    /** La tâche, si elle appartient à un projet dont je suis membre. */
    public static function tache(int $moi, int $tacheId): ?array
    {
        return Database::one(
            "SELECT t.* FROM projet_taches t
               JOIN projet_membres pm ON pm.projet_id = t.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE t.id = ?",
            [$moi, $tacheId]);
    }

    public static function modifierTache(int $moi, int $tacheId, string $titre, mixed $membreId, string $echeance, string $note): ?string
    {
        $tache = self::tache($moi, $tacheId);
        if ($tache === null) {
            return 'Cette tâche est introuvable.';
        }
        $titre = self::nettoyer($titre, 200);
        if ($titre === '') {
            return 'Donnez un titre à la tâche.';
        }
        $membre = self::membreValide((int) $tache['projet_id'], $membreId);
        Database::run('UPDATE projet_taches SET titre = ?, membre_id = ?, echeance = ?, note = ? WHERE id = ?',
            [$titre, $membre, self::dateValide($echeance), trim($note) === '' ? null : mb_substr(trim($note), 0, 2000), $tacheId]);
        if ($membre !== null && $membre !== (int) ($tache['membre_id'] ?? 0)) {
            self::prevenirConfiee($moi, $tacheId);
        }

        return null;
    }

    /** « Je m'en occupe » : la tâche passe à qui clique. */
    public static function prendre(int $moi, int $tacheId): bool
    {
        $tache = self::tache($moi, $tacheId);
        if ($tache === null) {
            return false;
        }
        $monMembre = (int) Database::valeur(
            'SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [(int) $tache['projet_id'], $moi]);
        Database::run('UPDATE projet_taches SET membre_id = ? WHERE id = ?', [$monMembre, $tacheId]);

        return true;
    }

    public static function changerStatut(int $moi, int $tacheId, string $statut): bool
    {
        if (!isset(self::STATUTS[$statut]) || self::tache($moi, $tacheId) === null) {
            return false;
        }
        Database::run(
            'UPDATE projet_taches SET statut = ?, fait_le = IF(statut = \'fait\', COALESCE(fait_le, ?), NULL) WHERE id = ?',
            [$statut, date('Y-m-d H:i:s'), $tacheId]);

        return true;
    }

    public static function supprimerTache(int $moi, int $tacheId): ?array
    {
        $tache = self::tache($moi, $tacheId);
        if ($tache !== null) {
            Database::run('DELETE FROM projet_taches WHERE id = ?', [$tacheId]);
        }

        return $tache;
    }

    /** Prévient qui se voit confier une tâche par quelqu'un d'autre. */
    private static function prevenirConfiee(int $moi, int $tacheId): void
    {
        $l = Database::one(
            'SELECT t.titre, t.projet_id, p.nom, pm.user_id FROM projet_taches t
               JOIN projets p ON p.id = t.projet_id
               JOIN projet_membres pm ON pm.id = t.membre_id
              WHERE t.id = ?', [$tacheId]);
        if ($l === null || $l['user_id'] === null || (int) $l['user_id'] === $moi) {
            return;
        }
        FileNotifications::ajouter((int) $l['user_id'], 'projet', [
            'title' => '👥 ' . $l['nom'],
            'body'  => (Amis::compte($moi)['pseudo'] ?? 'Quelqu’un') . ' vous confie « ' . $l['titre'] . ' ».',
            'url'   => url('travaux/' . (int) $l['projet_id']),
            'tag'   => 'projet-tache-' . $tacheId,
        ]);
    }

    /** Mes tâches à faire, tous projets confondus — pour l'accueil. */
    public static function mesTaches(int $moi, int $limite = 6): array
    {
        return Database::all(
            "SELECT t.id, t.titre, t.echeance, t.statut, p.id AS projet_id, p.nom AS projet_nom
               FROM projet_taches t
               JOIN projet_membres pm ON pm.id = t.membre_id AND pm.user_id = ? AND pm.statut = 'membre'
               JOIN projets p ON p.id = t.projet_id
              WHERE t.statut <> 'fait'
              ORDER BY t.echeance IS NULL, t.echeance, t.id
              LIMIT " . max(1, $limite),
            [$moi]);
    }

    /** Où en est le projet, en pourcentage de tâches faites (null sans tâche). */
    public static function avancement(int $total, int $faites): ?int
    {
        return $total === 0 ? null : (int) round($faites * 100 / $total);
    }

    // --- Les types d'échéance, réglables comme les types d'évènement ----------

    /** Les types d'un nouveau projet ; chacun les règle ensuite comme il veut. */
    public const TYPES_DEPART = [
        ['nom' => 'Rendu',      'icone' => '📦', 'couleur' => '#dc2626', 'rappels' => '2880,1440'],
        ['nom' => 'Soutenance', 'icone' => '🎤', 'couleur' => '#7c3aed', 'rappels' => '1440,60'],
        ['nom' => 'Réunion',    'icone' => '👥', 'couleur' => '#0ea5e9', 'rappels' => '60,15'],
    ];

    /** Une échéance sans type (le sien a été supprimé) garde cette icône. */
    public const ICONE_SANS_TYPE = '📌';

    /** Les icônes proposées : celles des types d'évènement, et celles des types de départ. */
    public static function icones(): array
    {
        return array_values(array_unique(array_merge(['📦', '🎤', '👥'], icones_proposees())));
    }

    public static function creerTypesDeDepart(int $projet): void
    {
        foreach (self::TYPES_DEPART as $rang => $t) {
            Database::run(
                'INSERT IGNORE INTO projet_types (projet_id, nom, icone, couleur, rappels, position) VALUES (?, ?, ?, ?, ?, ?)',
                [$projet, $t['nom'], $t['icone'], $t['couleur'], $t['rappels'], $rang + 1]);
        }
    }

    /** Les types du projet, dans leur ordre, avec le nombre d'échéances de chacun. */
    public static function types(int $projet): array
    {
        return Database::all(
            'SELECT t.*, (SELECT COUNT(*) FROM projet_echeances e WHERE e.type_id = t.id) AS nb_echeances
               FROM projet_types t WHERE t.projet_id = ? ORDER BY t.position, t.nom', [$projet]);
    }

    /** Le type, s'il appartient à un projet dont je suis membre. */
    public static function type(int $moi, int $typeId): ?array
    {
        return Database::one(
            "SELECT t.* FROM projet_types t
               JOIN projet_membres pm ON pm.projet_id = t.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE t.id = ?", [$moi, $typeId]);
    }

    /**
     * Crée un type, ou modifie celui donné, depuis son formulaire.
     *
     * @return ?string la raison du refus
     */
    public static function enregistrerType(int $projet, ?int $typeId, array $source): ?string
    {
        $nom = self::nettoyer((string) ($source['nom'] ?? ''), 40);
        if ($nom === '') {
            return 'Le nom du type est obligatoire.';
        }
        if (Database::valeur('SELECT id FROM projet_types WHERE projet_id = ? AND nom = ? AND id <> ?',
                [$projet, $nom, (int) $typeId]) !== null) {
            return 'Le projet a déjà un type nommé « ' . $nom . ' ».';
        }
        $icone = trim((string) ($source['icone'] ?? ''));
        $icone = $icone === '' || mb_strlen($icone) > 4 ? self::ICONE_SANS_TYPE : $icone;
        $couleur = (string) ($source['couleur'] ?? '');
        $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#64748b';
        $rappels = Rappels::ecrire(Rappels::depuisFormulaire($source['rappels'] ?? []));

        if ($typeId === null) {
            $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM projet_types WHERE projet_id = ?', [$projet]);
            Database::run('INSERT INTO projet_types (projet_id, nom, icone, couleur, rappels, position) VALUES (?, ?, ?, ?, ?, ?)',
                [$projet, $nom, $icone, $couleur, $rappels, $position]);
            return null;
        }
        Database::run('UPDATE projet_types SET nom = ?, icone = ?, couleur = ?, rappels = ? WHERE id = ? AND projet_id = ?',
            [$nom, $icone, $couleur, $rappels, $typeId, $projet]);
        // L'icône est dans le titre des copies du calendrier : elles la suivent.
        foreach (Database::all('SELECT id FROM projet_echeances WHERE type_id = ?', [$typeId]) as $e) {
            self::mettreAJourCopies((int) $e['id']);
        }

        return null;
    }

    /** Supprime le type ; ses échéances restent, sans type. */
    public static function supprimerType(int $moi, int $typeId): ?array
    {
        $type = self::type($moi, $typeId);
        if ($type === null) {
            return null;
        }
        $echeances = array_column(Database::all('SELECT id FROM projet_echeances WHERE type_id = ?', [$typeId]), 'id');
        Database::run('DELETE FROM projet_types WHERE id = ?', [$typeId]);
        foreach ($echeances as $e) {
            self::mettreAJourCopies((int) $e);
        }

        return $type + ['nb_echeances' => count($echeances)];
    }

    /** Monte ou descend un type dans la liste. */
    public static function deplacerType(int $moi, int $typeId, bool $versLeHaut): ?int
    {
        $type = self::type($moi, $typeId);
        if ($type === null) {
            return null;
        }
        $ids = array_map('intval', array_column(Database::all(
            'SELECT id FROM projet_types WHERE projet_id = ? ORDER BY position, nom', [(int) $type['projet_id']]), 'id'));
        $rang = (int) array_search($typeId, $ids, true);
        $cible = $versLeHaut ? $rang - 1 : $rang + 1;
        if ($cible >= 0 && $cible < count($ids)) {
            [$ids[$rang], $ids[$cible]] = [$ids[$cible], $ids[$rang]];
            foreach ($ids as $i => $id) {
                Database::run('UPDATE projet_types SET position = ? WHERE id = ?', [$i + 1, $id]);
            }
        }

        return (int) $type['projet_id'];
    }

    // --- Les échéances, dans le calendrier de chacun ---------------------------

    public static function echeances(int $projet): array
    {
        return Database::all(
            'SELECT e.*, t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
               FROM projet_echeances e LEFT JOIN projet_types t ON t.id = e.type_id
              WHERE e.projet_id = ? ORDER BY e.debut', [$projet]);
    }

    /** L'échéance, si elle appartient à un projet dont je suis membre. */
    public static function echeance(int $moi, int $echeanceId): ?array
    {
        return Database::one(
            "SELECT e.* FROM projet_echeances e
               JOIN projet_membres pm ON pm.projet_id = e.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE e.id = ?",
            [$moi, $echeanceId]);
    }

    /**
     * Lit le formulaire d'une échéance.
     *
     * @return array|string les données, ou la raison du refus
     */
    public static function lireEcheance(array $source, int $projet): array|string
    {
        // Un type du projet, ou aucun.
        $type = null;
        $typeId = entier_ou_null($source['type_id'] ?? null);
        if ($typeId !== null) {
            $type = Database::one('SELECT * FROM projet_types WHERE id = ? AND projet_id = ?', [$typeId, $projet]);
        }
        $titre = self::nettoyer((string) ($source['titre'] ?? ''), 160);
        if ($titre === '') {
            $titre = $type['nom'] ?? 'Échéance';
        }
        $jour = self::dateValide(trim((string) ($source['jour'] ?? '')));
        if ($jour === null) {
            return 'Donnez le jour de l’échéance.';
        }
        $heure = trim((string) ($source['heure'] ?? ''));
        $journee = $heure === '';
        if (!$journee && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure) !== 1) {
            return 'L’heure n’est pas valable.';
        }
        $duree = max(15, min(600, (int) ($source['duree'] ?? 60)));
        $debut = $journee ? $jour . ' 00:00:00' : $jour . ' ' . $heure . ':00';
        $fin = $journee ? $jour . ' 23:59:59'
            : (new DateTimeImmutable($debut))->modify('+' . $duree . ' minutes')->format('Y-m-d H:i:s');

        return [
            'type_id' => $type === null ? null : (int) $type['id'],
            'titre' => $titre,
            'lieu' => self::nettoyer((string) ($source['lieu'] ?? ''), 160) ?: null,
            'debut' => $debut,
            'fin' => $fin,
            'journee_entiere' => $journee ? 1 : 0,
        ];
    }

    /** Pose une échéance, et sa copie chez chaque membre. */
    public static function poserEcheance(int $moi, int $projet, array $d): int
    {
        Database::run(
            'INSERT INTO projet_echeances (projet_id, type_id, titre, lieu, debut, fin, journee_entiere, cree_par)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projet, $d['type_id'], $d['titre'], $d['lieu'], $d['debut'], $d['fin'], $d['journee_entiere'], $moi]);
        $id = Database::dernierId();

        $nom = (string) Database::valeur('SELECT nom FROM projets WHERE id = ?', [$projet]);
        $icone = (string) (Database::valeur('SELECT icone FROM projet_types WHERE id = ?', [(int) $d['type_id']]) ?: self::ICONE_SANS_TYPE);
        $auteur = (string) (Amis::compte($moi)['pseudo'] ?? 'Quelqu’un');
        foreach (self::comptes($projet) as $userId) {
            self::copier($id, $userId);
            if ($userId !== $moi) {
                FileNotifications::ajouter($userId, 'projet', [
                    'title' => $icone . ' ' . $nom,
                    'body'  => $auteur . ' a posé « ' . $d['titre'] . ' » le ' . date_fr($d['debut'], !$d['journee_entiere']) . '.',
                    'url'   => url('travaux/' . $projet),
                    'tag'   => 'projet-echeance-' . $id,
                ]);
            }
        }

        return $id;
    }

    public static function modifierEcheance(int $echeanceId, array $d): void
    {
        Database::run(
            'UPDATE projet_echeances SET type_id = ?, titre = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ? WHERE id = ?',
            [$d['type_id'], $d['titre'], $d['lieu'], $d['debut'], $d['fin'], $d['journee_entiere'], $echeanceId]);
        self::mettreAJourCopies($echeanceId);
    }

    /** Les comptes membres du projet (invitation acceptée). */
    private static function comptes(int $projet): array
    {
        return array_map('intval', array_column(Database::all(
            "SELECT user_id FROM projet_membres WHERE projet_id = ? AND user_id IS NOT NULL AND statut = 'membre'",
            [$projet]), 'user_id'));
    }

    /** Ce que la copie du calendrier doit dire de l'échéance. */
    private static function contenuCopie(int $echeanceId): ?array
    {
        $e = Database::one(
            'SELECT e.*, p.nom AS projet_nom, t.icone AS type_icone, t.rappels AS type_rappels
               FROM projet_echeances e
               JOIN projets p ON p.id = e.projet_id
               LEFT JOIN projet_types t ON t.id = e.type_id
              WHERE e.id = ?',
            [$echeanceId]);
        if ($e === null) {
            return null;
        }

        return $e + [
            'titre_copie' => mb_substr(($e['type_icone'] ?? self::ICONE_SANS_TYPE) . ' ' . $e['titre'] . ' · ' . $e['projet_nom'], 0, 200),
            'description_copie' => 'Travail de groupe « ' . $e['projet_nom'] . ' ».',
            'rappels_copie' => (string) ($e['type_rappels'] ?? '1440'),
        ];
    }

    /** La copie de l'échéance dans le calendrier d'un membre (une seule). */
    public static function copier(int $echeanceId, int $userId): void
    {
        $e = self::contenuCopie($echeanceId);
        if ($e === null || Database::valeur(
                'SELECT id FROM evenements WHERE user_id = ? AND projet_echeance_id = ?', [$userId, $echeanceId]) !== null) {
            return;
        }
        Database::run(
            'INSERT INTO evenements (user_id, titre, description, lieu, debut, fin, journee_entiere, rappels, projet_echeance_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $e['titre_copie'], $e['description_copie'], $e['lieu'], $e['debut'], $e['fin'],
             (int) $e['journee_entiere'], $e['rappels_copie'], $echeanceId]);
    }

    /**
     * Répercute l'échéance sur les copies existantes. Les rappels restent ceux
     * que chacun a choisis ; une copie qu'un membre a supprimée ne revient pas.
     */
    private static function mettreAJourCopies(int $echeanceId): void
    {
        $e = self::contenuCopie($echeanceId);
        if ($e === null) {
            return;
        }
        Database::run(
            'UPDATE evenements SET titre = ?, description = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ?
              WHERE projet_echeance_id = ?',
            [$e['titre_copie'], $e['description_copie'], $e['lieu'], $e['debut'], $e['fin'],
             (int) $e['journee_entiere'], $echeanceId]);
    }

    /** Le projet d'une copie du calendrier, si j'en suis membre. */
    public static function projetDeLEvenement(int $moi, ?int $echeanceId): ?array
    {
        if ($echeanceId === null) {
            return null;
        }

        return Database::one(
            "SELECT p.id, p.nom FROM projet_echeances e
               JOIN projets p ON p.id = e.projet_id
               JOIN projet_membres pm ON pm.projet_id = p.id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE e.id = ?", [$moi, $echeanceId]);
    }

    // --- Les fichiers ----------------------------------------------------------

    public static function fichiers(int $projet): array
    {
        return Database::all(
            'SELECT f.*, COALESCE(u.pseudo, u.nom) AS depose_par
               FROM projet_fichiers f LEFT JOIN users u ON u.id = f.user_id
              WHERE f.projet_id = ? ORDER BY f.created_at DESC, f.id DESC', [$projet]);
    }

    /** @return string[] les erreurs rencontrées */
    public static function deposer(array $fichiers, int $moi, int $projet): array
    {
        return Fichiers::recevoir($fichiers, self::dossier(),
            static function (string $nomOrigine, string $nomStocke, string $mime, int $taille) use ($moi, $projet): void {
                Database::run(
                    'INSERT INTO projet_fichiers (projet_id, user_id, nom_origine, nom_stocke, mime, taille)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$projet, $moi, mb_substr($nomOrigine, 0, 255), $nomStocke, $mime, $taille]);
            });
    }

    /** Le fichier, si j'appartiens à son projet. */
    public static function fichier(int $moi, int $fichierId): ?array
    {
        return Database::one(
            "SELECT f.* FROM projet_fichiers f
               JOIN projet_membres pm ON pm.projet_id = f.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE f.id = ?", [$moi, $fichierId]);
    }

    /** Qui l'a déposé, ou un administrateur, peut le retirer. */
    public static function supprimerFichier(int $moi, int $fichierId): ?array
    {
        $f = self::fichier($moi, $fichierId);
        if ($f === null || ((int) ($f['user_id'] ?? 0) !== $moi && !self::estAdmin((int) $f['projet_id'], $moi))) {
            return null;
        }
        $chemin = self::dossier() . DIRECTORY_SEPARATOR . basename((string) $f['nom_stocke']);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
        Database::run('DELETE FROM projet_fichiers WHERE id = ?', [$fichierId]);

        return $f;
    }

    // --- Le document commun ----------------------------------------------------

    /**
     * Enregistre le document. La version lue à l'ouverture doit être encore la
     * dernière : sinon quelqu'un a écrit entre-temps, et on n'écrase pas son
     * travail — on le dit.
     *
     * @return bool false en cas de conflit
     */
    public static function ecrireDocument(int $moi, int $projet, string $contenu, int $versionLue): bool
    {
        $p = Database::one('SELECT document, document_version, document_par FROM projets WHERE id = ?', [$projet]);
        if ($p === null || (int) $p['document_version'] !== $versionLue) {
            return false;
        }
        $contenu = TexteRiche::depuisFormulaire($contenu);
        if ($contenu === (string) ($p['document'] ?? '')) {
            return true;
        }
        if ((string) ($p['document'] ?? '') !== '') {
            Database::run('INSERT INTO projet_versions (projet_id, user_id, contenu) VALUES (?, ?, ?)',
                [$projet, $p['document_par'], $p['document']]);
            $trop = Database::all('SELECT id FROM projet_versions WHERE projet_id = ? ORDER BY id DESC LIMIT 1000 OFFSET ' . self::VERSIONS_MAX, [$projet]);
            foreach ($trop as $v) {
                Database::run('DELETE FROM projet_versions WHERE id = ?', [(int) $v['id']]);
            }
        }
        Database::run(
            'UPDATE projets SET document = ?, document_version = document_version + 1, document_par = ?, document_le = ?
              WHERE id = ? AND document_version = ?',
            [$contenu === '' ? null : $contenu, $moi, date('Y-m-d H:i:s'), $projet, $versionLue]);

        return true;
    }

    public static function versions(int $projet): array
    {
        return Database::all(
            'SELECT v.id, v.created_at, COALESCE(u.pseudo, u.nom) AS auteur, CHAR_LENGTH(v.contenu) AS longueur
               FROM projet_versions v LEFT JOIN users u ON u.id = v.user_id
              WHERE v.projet_id = ? ORDER BY v.id DESC', [$projet]);
    }

    /** La version, si j'appartiens à son projet. */
    public static function version(int $moi, int $versionId): ?array
    {
        return Database::one(
            "SELECT v.*, COALESCE(u.pseudo, u.nom) AS auteur FROM projet_versions v
               JOIN projet_membres pm ON pm.projet_id = v.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
               LEFT JOIN users u ON u.id = v.user_id
              WHERE v.id = ?", [$moi, $versionId]);
    }

    /** Revenir à une version : l'actuelle devient elle-même une version. */
    public static function restaurer(int $moi, int $versionId): ?int
    {
        $v = self::version($moi, $versionId);
        if ($v === null) {
            return null;
        }
        $projet = (int) $v['projet_id'];
        $version = (int) Database::valeur('SELECT document_version FROM projets WHERE id = ?', [$projet]);
        self::ecrireDocument($moi, $projet, TexteRiche::pourEditeur((string) $v['contenu']), $version);

        return $projet;
    }

    // --- Le lien public, pour qui n'a pas de compte ----------------------------

    public static function ouvrirLien(int $moi, int $projet): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return null;
        }
        $jeton = bin2hex(random_bytes(16));
        Database::run('UPDATE projets SET jeton = ? WHERE id = ?', [$jeton, $projet]);

        return $jeton;
    }

    public static function fermerLien(int $moi, int $projet): bool
    {
        if (!self::estAdmin($projet, $moi)) {
            return false;
        }
        Database::run('UPDATE projets SET jeton = NULL WHERE id = ?', [$projet]);

        return true;
    }

    /** L’adresse complète du lien public, à copier. */
    public static function adresseLien(string $jeton): string
    {
        $site = Reinitialisation::adresseDuSite();
        if ($site === null) {
            $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
            $site = ($https ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return $site . url('g/' . $jeton);
    }

    public static function parJeton(string $jeton): ?array
    {
        return preg_match('/^[0-9a-f]{32}$/', $jeton) === 1
            ? Database::one('SELECT * FROM projets WHERE jeton = ?', [$jeton])
            : null;
    }

    // --- La discussion du groupe -----------------------------------------------

    /**
     * Crée la discussion du groupe avec tous les membres qui ont un compte, ou
     * relie une discussion dont je fais partie (les membres du projet y entrent).
     *
     * @return ?string la raison du refus
     */
    public static function relierDiscussion(int $moi, int $projet, ?int $conversation): ?string
    {
        $p = self::projet($projet, $moi);
        if ($p === null) {
            return 'Ce projet est introuvable.';
        }
        $comptes = self::comptes($projet);
        if ($conversation === null) {
            [$conversation, $refus] = Conversations::creerPourProjet($moi, (string) $p['nom'],
                array_values(array_diff($comptes, [$moi])));
            if ($refus !== null) {
                return $refus;
            }
        } elseif (Conversations::membre($conversation, $moi) === null) {
            return 'Vous ne faites pas partie de cette discussion.';
        } else {
            foreach ($comptes as $userId) {
                Conversations::ajouterDepuisProjet($conversation, $userId, $moi);
            }
        }
        Database::run('UPDATE projets SET conversation_id = ? WHERE id = ?', [$conversation, $projet]);

        return null;
    }

    public static function delierDiscussion(int $moi, int $projet): bool
    {
        if (!self::estAdmin($projet, $moi)) {
            return false;
        }
        Database::run('UPDATE projets SET conversation_id = NULL WHERE id = ?', [$projet]);

        return true;
    }
}
