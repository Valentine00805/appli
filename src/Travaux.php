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
        'a_faire'  => ['icone' => '⬜'],
        'en_cours' => ['icone' => '⏳'],
        'fait'     => ['icone' => '✅'],
    ];

    /** Le nom d'un état : « À faire », « En cours », « Fait ». */
    public static function statutNom(string $statut): string
    {
        return t('tr.statut.' . $statut);
    }

    /**
     * Les états avec leur icône et leur nom, pour une colonne ou un menu.
     *
     * @return array<string, array{icone: string, nom: string}>
     */
    public static function statuts(): array
    {
        $etats = [];
        foreach (self::STATUTS as $cle => $etat) {
            $etats[$cle] = $etat + ['nom' => self::statutNom($cle)];
        }

        return $etats;
    }

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
    public static function creer(int $moi, string $nom, string $description, array $amis, ?array $onglets = null): array
    {
        $nom = self::nettoyer($nom, self::NOM_MAX);
        if ($nom === '') {
            return [null, t('tr.msg.nom_manquant')];
        }
        Database::run('INSERT INTO projets (nom, description, cree_par) VALUES (?, ?, ?)',
            [$nom, trim($description) === '' ? null : mb_substr(trim($description), 0, 2000), $moi]);
        $projet = Database::dernierId();
        Database::run(
            "INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')",
            [$projet, $moi]);
        self::creerTypesDeDepart($projet);
        // Les onglets que le groupe a choisis (null : il n'a pas eu le choix, il a tout).
        if ($onglets !== null) {
            self::ecrireOnglets($projet, $onglets);
        }

        [, $refus] = $amis === [] ? [0, null] : self::inviter($moi, $projet, $amis);

        return [$projet, $refus];
    }

    public static function modifier(int $moi, int $projet, string $nom, string $description): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return t('tr.msg.admins_renommer');
        }
        $nom = self::nettoyer($nom, self::NOM_MAX);
        if ($nom === '') {
            return t('tr.msg.nom_manquant');
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
            return [0, t('tr.msg.admins_inviter')];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids),
            static fn (int $id): bool => $id > 0 && $id !== $moi)));
        foreach ($ids as $id) {
            if (!Amis::sontAmis($moi, $id)) {
                return [0, t('tr.msg.amis_seuls')];
            }
        }
        $deja = array_map('intval', array_column(Database::all(
            'SELECT user_id FROM projet_membres WHERE projet_id = ? AND user_id IS NOT NULL', [$projet]), 'user_id'));
        $ids = array_values(array_diff($ids, $deja));
        if ($ids === []) {
            return [0, t('tr.msg.un_ami_hors')];
        }
        if (self::nbMembres($projet) + count($ids) > self::MEMBRES_MAX) {
            return [0, t('tr.msg.complet', ['max' => self::MEMBRES_MAX])];
        }

        $nomProjet = (string) Database::valeur('SELECT nom FROM projets WHERE id = ?', [$projet]);
        $auteur = Amis::compte($moi)['pseudo'] ?? null;
        foreach ($ids as $id) {
            Database::run(
                "INSERT INTO projet_membres (projet_id, user_id, role, statut, invite_par) VALUES (?, ?, 'membre', 'invite', ?)",
                [$projet, $id, $moi]);
            FileNotifications::ajouter($id, 'projet', fn (): array => [
                'title' => t('tr.notif.travail_groupe'),
                'body'  => t('tr.notif.invitation', [
                    'qui' => (string) ($auteur ?? t('tr.quelquun')), 'nom' => $nomProjet,
                ]),
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
            return t('tr.msg.admins_ajouter');
        }
        $nom = self::nettoyer($nom, 60);
        if ($nom === '') {
            return t('tr.msg.nom_personne');
        }
        if (self::nbMembres($projet) >= self::MEMBRES_MAX) {
            return t('tr.msg.complet', ['max' => self::MEMBRES_MAX]);
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
        CalendriersAmis::synchroniserProjet($projet);

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
        CalendriersAmis::synchroniserProjet($projet);

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
            return t('tr.msg.admins_retirer');
        }
        if ((int) ($membre['user_id'] ?? 0) === $moi) {
            return t('tr.msg.partir');
        }
        if ($membre['user_id'] !== null) {
            self::retirerCopies((int) $membre['projet_id'], (int) $membre['user_id']);
        }
        Database::run('DELETE FROM projet_membres WHERE id = ?', [$membreId]);
        CalendriersAmis::synchroniserProjet((int) $membre['projet_id']);

        return null;
    }

    /** Nomme administrateur, ou le redevient simple membre. */
    public static function changerRole(int $moi, int $membreId, bool $admin): ?string
    {
        $membre = Database::one('SELECT * FROM projet_membres WHERE id = ?', [$membreId]);
        if ($membre === null || !self::estAdmin((int) $membre['projet_id'], $moi)) {
            return t('tr.msg.admins_roles');
        }
        if ($membre['user_id'] === null || $membre['statut'] !== 'membre') {
            return t('tr.msg.membre_rejoint');
        }
        if (!$admin) {
            $admins = (int) Database::valeur(
                "SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND role = 'admin'", [(int) $membre['projet_id']]);
            if ($admins <= 1 && $membre['role'] === 'admin') {
                return t('tr.msg.un_admin_min');
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
            return t('tr.msg.titre_tache');
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
            return t('tr.msg.tache_introuvable');
        }
        $titre = self::nettoyer($titre, 200);
        if ($titre === '') {
            return t('tr.msg.titre_tache');
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
        $tache = self::tache($moi, $tacheId);
        if ($tache === null) {
            return false;
        }
        // Une phase (« col:12 ») : la tâche est « en cours », rangée dans cette colonne-là.
        if (preg_match('/^col:(\d+)$/', $statut, $m) === 1) {
            if (!self::colonnesDisponibles()
                || Database::valeur('SELECT id FROM projet_colonnes WHERE id = ? AND projet_id = ?', [(int) $m[1], (int) $tache['projet_id']]) === null) {
                return false;
            }
            Database::run('UPDATE projet_taches SET statut = \'en_cours\', colonne_id = ?, fait_le = NULL WHERE id = ?', [(int) $m[1], $tacheId]);

            return true;
        }
        if (!isset(self::STATUTS[$statut])) {
            return false;
        }
        Database::run(
            'UPDATE projet_taches SET statut = ?, ' . (self::colonnesDisponibles() ? 'colonne_id = NULL, ' : '') . 'fait_le = IF(statut = \'fait\', COALESCE(fait_le, ?), NULL) WHERE id = ?',
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
        FileNotifications::ajouter((int) $l['user_id'], 'projet', fn (): array => [
            'title' => '👥 ' . $l['nom'],
            'body'  => t('tr.notif.tache_confiee', [
                'qui' => (string) (Amis::compte($moi)['pseudo'] ?? t('tr.quelquun')),
                'titre' => (string) $l['titre'],
            ]),
            'url'   => url('travaux/' . (int) $l['projet_id']),
            'tag'   => 'projet-tache-' . $tacheId,
        ]);
    }

    /**
     * Mes tâches à faire, tous projets confondus — pour l'accueil : celles qu'on m'a confiées, et celles que personne n'a prises
     * (« membre_id » vide : l'affichage le précise).
     */
    public static function mesTaches(int $moi, int $limite = 6): array
    {
        return Database::all(
            "SELECT t.id, t.titre, t.echeance, t.statut, t.membre_id, p.id AS projet_id, p.nom AS projet_nom
               FROM projet_taches t
               JOIN projet_membres pm ON pm.projet_id = t.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
               JOIN projets p ON p.id = t.projet_id
              WHERE t.statut <> 'fait' AND (t.membre_id = pm.id OR t.membre_id IS NULL)
              ORDER BY t.echeance IS NULL, t.echeance, t.id
              LIMIT " . max(1, $limite),
            [$moi]);
    }

    /**
     * Mes tâches de la page Groupes s'ouvrent-elles repliées ? Le réglage de « Mon compte » ; faux (dépliées) par défaut — et tant que la
     * colonne n'existe pas, le temps d'importer la migration.
     */
    public static function tachesRepliees(int $moi): bool
    {
        try {
            return (int) Database::valeur('SELECT taches_groupes_repliees FROM users WHERE id = ?', [$moi]) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /** Où en est le projet, en pourcentage de tâches faites (null sans tâche). */
    public static function avancement(int $total, int $faites): ?int
    {
        return $total === 0 ? null : (int) round($faites * 100 / $total);
    }

    // --- Les types d'échéance, réglables comme les types d'évènement ----------

    /** Les types d'un nouveau projet ; chacun les règle ensuite comme il veut. */
    /*
     * Les types d'échéance créés avec un projet. « cle » sert à écrire le nom
     * dans la langue du compte au moment de l'insertion ; ce nom part en base
     * et chacun peut le renommer ensuite.
     */
    public const TYPES_DEPART = [
        ['cle' => 'rendu',      'icone' => '📦', 'couleur' => '#dc2626', 'rappels' => '2880,1440'],
        ['cle' => 'soutenance', 'icone' => '🎤', 'couleur' => '#7c3aed', 'rappels' => '1440,60'],
        ['cle' => 'reunion',    'icone' => '👥', 'couleur' => '#0ea5e9', 'rappels' => '60,15'],
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
                [$projet, t('tr.type_defaut.' . $t['cle']), $t['icone'], $t['couleur'],
                 $t['rappels'], $rang + 1]);
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
            return t('tr.msg.type_nom');
        }
        if (Database::valeur('SELECT id FROM projet_types WHERE projet_id = ? AND nom = ? AND id <> ?',
                [$projet, $nom, (int) $typeId]) !== null) {
            return t('tr.msg.type_existe', ['nom' => $nom]);
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
            $titre = $type['nom'] ?? t('tr.echeance_sans_titre');
        }
        $jour = self::dateValide(trim((string) ($source['jour'] ?? '')));
        if ($jour === null) {
            return t('tr.msg.jour_echeance');
        }
        $heure = trim((string) ($source['heure'] ?? ''));
        $journee = $heure === '';
        if (!$journee && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure) !== 1) {
            return t('tr.msg.heure_invalide');
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
        $auteur = Amis::compte($moi)['pseudo'] ?? null;
        foreach (self::comptes($projet) as $userId) {
            self::copier($id, $userId);
            if ($userId !== $moi) {
                FileNotifications::ajouter($userId, 'projet', fn (): array => [
                    'title' => $icone . ' ' . $nom,
                    'body'  => t('tr.notif.echeance_posee', [
                        'qui' => (string) ($auteur ?? t('tr.quelquun')),
                        'titre' => (string) $d['titre'],
                        'date' => date_fr($d['debut'], !$d['journee_entiere']),
                    ]),
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
            'description_copie' => t('tr.copie_description', ['nom' => (string) $e['projet_nom']]),
            'rappels_copie' => (string) ($e['type_rappels'] ?? '1440'),
        ];
    }

    /** La copie de l'échéance dans le calendrier d'un membre (une seule). */
    public static function copier(int $echeanceId, int $userId): void
    {
        $e = Langue::pourLeCompte($userId, static fn (): ?array => self::contenuCopie($echeanceId));
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
        // Une copie par membre, chacune dans la langue de son propriétaire.
        foreach (Database::all('SELECT DISTINCT user_id FROM evenements WHERE projet_echeance_id = ?', [$echeanceId]) as $ligne) {
            $membre = (int) $ligne['user_id'];
            $e = Langue::pourLeCompte($membre, static fn (): ?array => self::contenuCopie($echeanceId));
            if ($e === null) {
                return;
            }
            Database::run(
                'UPDATE evenements SET titre = ?, description = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ?
                  WHERE projet_echeance_id = ? AND user_id = ?',
                [$e['titre_copie'], $e['description_copie'], $e['lieu'], $e['debut'], $e['fin'],
                 (int) $e['journee_entiere'], $echeanceId, $membre]);
        }
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
              WHERE f.projet_id = ?' . (self::livrablesDisponibles() ? ' AND f.livrable_id IS NULL' : '') . ' ORDER BY f.created_at DESC, f.id DESC', [$projet]);
    }

    // --- Les livrables : les documents finaux que le groupe doit rendre ---------------------------

    public const LIVRABLES_MAX = 30;
    public const LIVRABLE_TITRE_MAX = 120;
    public const LIVRABLE_NOTE_MAX = 500;
    public const LIVRABLE_FICHIERS_MAX = 20;

    /** Les livrables existent-ils ? (Sinon — migration pas encore importée — l'onglet n'apparaît pas.) */
    public static function livrablesDisponibles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT livrable_id FROM projet_fichiers LIMIT 1');
                Database::valeur('SELECT 1 FROM projet_livrables LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Les livrables du projet, avec leurs documents : ceux qui restent à rendre d'abord (le plus pressé en tête), puis ceux déjà rendus.
     *
     * @return list<array<string, mixed>>  chacun avec « fichiers » et « etat » (a_rendre | pret | rendu)
     */
    public static function livrables(int $projet): array
    {
        if (!self::livrablesDisponibles()) {
            return [];
        }
        $livrables = Database::all(
            'SELECT l.*, COALESCE(u.pseudo, u.nom) AS rendu_par_nom
               FROM projet_livrables l LEFT JOIN users u ON u.id = l.rendu_par
              WHERE l.projet_id = ?
              ORDER BY l.rendu_le IS NOT NULL, l.echeance IS NULL, l.echeance, l.position, l.id',
            [$projet]);
        $fichiers = [];
        foreach (Database::all(
            'SELECT f.*, COALESCE(u.pseudo, u.nom) AS depose_par
               FROM projet_fichiers f LEFT JOIN users u ON u.id = f.user_id
              WHERE f.projet_id = ? AND f.livrable_id IS NOT NULL ORDER BY f.created_at, f.id',
            [$projet]
        ) as $f) {
            $fichiers[(int) $f['livrable_id']][] = $f;
        }
        foreach ($livrables as &$l) {
            $l['fichiers'] = $fichiers[(int) $l['id']] ?? [];
            $l['etat'] = $l['rendu_le'] !== null ? 'rendu' : ($l['fichiers'] !== [] ? 'pret' : 'a_rendre');
        }

        return $livrables;
    }

    /** Un livrable, si je suis membre (avec un compte) de son projet. */
    public static function livrable(int $moi, int $livrableId): ?array
    {
        if (!self::livrablesDisponibles()) {
            return null;
        }

        return Database::one(
            "SELECT l.* FROM projet_livrables l
               JOIN projet_membres pm ON pm.projet_id = l.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE l.id = ?",
            [$moi, $livrableId]);
    }

    public static function creerLivrable(int $moi, int $projet, string $titre, string $echeance, string $note): ?string
    {
        if (!self::livrablesDisponibles() || self::projet($projet, $moi) === null) {
            return t('tr.lv.introuvable');
        }
        $titre = self::nettoyer($titre, self::LIVRABLE_TITRE_MAX);
        if ($titre === '') {
            return t('tr.lv.titre_obligatoire');
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM projet_livrables WHERE projet_id = ?', [$projet]) >= self::LIVRABLES_MAX) {
            return t('tr.lv.trop', ['max' => self::LIVRABLES_MAX]);
        }
        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM projet_livrables WHERE projet_id = ?', [$projet]);
        Database::run(
            'INSERT INTO projet_livrables (projet_id, titre, note, echeance, cree_par, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projet, $titre, self::noteDeLivrable($note), self::dateValide($echeance), $moi, $position, date('Y-m-d H:i:s')]);

        return null;
    }

    private static function noteDeLivrable(string $note): ?string
    {
        $note = trim($note);

        return $note === '' ? null : mb_substr($note, 0, self::LIVRABLE_NOTE_MAX);
    }

    public static function modifierLivrable(int $moi, int $livrableId, string $titre, string $echeance, string $note): ?string
    {
        if (self::livrable($moi, $livrableId) === null) {
            return t('tr.lv.introuvable');
        }
        $titre = self::nettoyer($titre, self::LIVRABLE_TITRE_MAX);
        if ($titre === '') {
            return t('tr.lv.titre_obligatoire');
        }
        Database::run('UPDATE projet_livrables SET titre = ?, echeance = ?, note = ? WHERE id = ?',
            [$titre, self::dateValide($echeance), self::noteDeLivrable($note), $livrableId]);

        return null;
    }

    /** Marque le livrable comme rendu (par moi, maintenant), ou le remet « à rendre ». */
    public static function rendreLivrable(int $moi, int $livrableId, bool $rendu): bool
    {
        if (self::livrable($moi, $livrableId) === null) {
            return false;
        }
        Database::run('UPDATE projet_livrables SET rendu_le = ?, rendu_par = ? WHERE id = ?',
            [$rendu ? date('Y-m-d H:i:s') : null, $rendu ? $moi : null, $livrableId]);

        return true;
    }

    /** Supprime le livrable et ses documents : l'administrateur du groupe seulement. Rend le projet, ou null. */
    public static function supprimerLivrable(int $moi, int $livrableId): ?int
    {
        $l = self::livrable($moi, $livrableId);
        if ($l === null || !self::estAdmin((int) $l['projet_id'], $moi)) {
            return null;
        }
        foreach (Database::all('SELECT nom_stocke FROM projet_fichiers WHERE livrable_id = ?', [$livrableId]) as $f) {
            $chemin = self::dossier() . DIRECTORY_SEPARATOR . basename((string) $f['nom_stocke']);
            if (is_file($chemin)) {
                @unlink($chemin);
            }
        }
        Database::run('DELETE FROM projet_fichiers WHERE livrable_id = ?', [$livrableId]);
        Database::run('DELETE FROM projet_livrables WHERE id = ?', [$livrableId]);

        return (int) $l['projet_id'];
    }

    /**
     * Dépose des documents dans un livrable.
     *
     * @return array{0: int, 1: list<string>} combien ont été reçus, et les erreurs rencontrées
     */
    public static function deposerDansLivrable(array $fichiers, int $moi, int $livrableId): array
    {
        $l = self::livrable($moi, $livrableId);
        if ($l === null) {
            return [0, [t('tr.lv.introuvable')]];
        }
        $projet = (int) $l['projet_id'];
        $deja = (int) Database::valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$livrableId]);
        $annonces = count(array_filter((array) ($fichiers['name'] ?? []), static fn (mixed $n): bool => (string) $n !== ''));
        if ($deja + $annonces > self::LIVRABLE_FICHIERS_MAX) {
            return [0, [t('tr.lv.trop_de_fichiers', ['max' => self::LIVRABLE_FICHIERS_MAX])]];
        }
        $recus = 0;
        $erreurs = Fichiers::recevoir($fichiers, self::dossier(),
            static function (string $nomOrigine, string $nomStocke, string $mime, int $taille) use ($moi, $projet, $livrableId, &$recus): void {
                Database::run(
                    'INSERT INTO projet_fichiers (projet_id, livrable_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$projet, $livrableId, $moi, mb_substr($nomOrigine, 0, 255), $nomStocke, $mime, $taille]);
                $recus++;
            });

        return [$recus, $erreurs];
    }

    // --- L'en-tête du PDF du document commun : ce qu'on y met ----------------------------------------

    public const ENTETE_TITRE_MAX = 120;
    public const ENTETE_AUTRE_LIGNES = 3;
    public const ENTETE_AUTRE_LIGNE_MAX = 150;

    /** Le choix de l'en-tête peut-il être gardé ? (Sinon — migration pas encore importée — le PDF garde l'en-tête de départ.) */
    public static function entetePdfDisponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT pdf_entete FROM projets LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Ce que le groupe a choisi de mettre en tête du PDF — avec ce qu'on met par défaut : le nom du projet, « Document commun », rien d'autre.
     * « titre » vide : le nom du projet. « sous_titre » vide, une fois choisi : pas de sous-titre.
     *
     * @return array{titre: string, sous_titre: string, membres: bool, date: bool, autre: string}
     */
    public static function entetePdf(array $projet): array
    {
        $garde = [];
        if (self::entetePdfDisponible() && (string) ($projet['pdf_entete'] ?? '') !== '') {
            $lu = json_decode((string) $projet['pdf_entete'], true);
            $garde = is_array($lu) ? $lu : [];
        }

        return [
            'titre'      => (string) ($garde['titre'] ?? ''),
            'sous_titre' => array_key_exists('sous_titre', $garde) ? (string) $garde['sous_titre'] : t('tr.do.label'),
            'membres'    => !empty($garde['membres']),
            'date'       => !empty($garde['date']),
            'autre'      => (string) ($garde['autre'] ?? ''),
        ];
    }

    /** Garde le choix d'en-tête du projet (tout membre le règle). Rend la raison du refus. */
    public static function enregistrerEntetePdf(int $moi, int $projetId, array $donnees): ?string
    {
        if (!self::entetePdfDisponible() || self::projet($projetId, $moi) === null) {
            return t('tr.do.entete_indisponible');
        }
        $lignes = array_slice(array_values(array_filter(array_map(
            static fn (string $l): string => self::nettoyer($l, self::ENTETE_AUTRE_LIGNE_MAX),
            preg_split('/\R/u', (string) ($donnees['autre'] ?? '')) ?: []
        ), static fn (string $l): bool => $l !== '')), 0, self::ENTETE_AUTRE_LIGNES);
        $entete = [
            'titre'      => self::nettoyer((string) ($donnees['titre'] ?? ''), self::ENTETE_TITRE_MAX),
            'sous_titre' => self::nettoyer((string) ($donnees['sous_titre'] ?? ''), self::ENTETE_TITRE_MAX),
            'membres'    => !empty($donnees['membres']) ? 1 : 0,
            'date'       => !empty($donnees['date']) ? 1 : 0,
            'autre'      => implode("\n", $lignes),
        ];
        Database::run('UPDATE projets SET pdf_entete = ? WHERE id = ?', [json_encode($entete, JSON_UNESCAPED_UNICODE), $projetId]);

        return null;
    }

    /** Les lignes d'information sous le titre du PDF, selon le choix du groupe : les membres, la date de la version, le texte libre. */
    private static function ligneDInfosPdf(array $projet, array $entete): array
    {
        $infos = [];
        if ($entete['membres']) {
            $noms = array_map(static fn (array $m): string => (string) $m['nom_affiche'], self::membresActifs((int) $projet['id']));
            if ($noms !== []) {
                $infos[] = t('tr.do.pdf_membres', ['noms' => implode(', ', $noms)]);
            }
        }
        if ($entete['date']) {
            $infos[] = t('tr.do.pdf_date', ['date' => date_fr((string) ($projet['document_le'] ?? date('Y-m-d')), false)]);
        }
        foreach (preg_split('/\R/u', $entete['autre']) ?: [] as $ligne) {
            if (trim($ligne) !== '') {
                $infos[] = trim($ligne);
            }
        }

        return $infos;
    }

    // --- Le document commun, en PDF — et dans les livrables ---------------------------------------

    /** Le document commun enregistré, en PDF : son texte mis en pages comme celui d'un cours, sous le nom du projet. */
    public static function documentEnPdf(array $projet): string
    {
        $entete = self::entetePdf($projet);

        return ExportPdf::depuisCours([
            'titre' => $entete['titre'] !== '' ? $entete['titre'] : (string) $projet['nom'],
            'contenu' => $projet['document'] ?? null,
            'matiere_nom' => $entete['sous_titre'],
            'infos' => self::ligneDInfosPdf($projet, $entete),
        ]);
    }

    /** Le nom du fichier PDF : « Document commun - Rapport de labo.pdf ». */
    public static function nomPdfDocument(array $projet): string
    {
        $nom = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', t('tr.do.nom_pdf', ['nom' => (string) $projet['nom']])));

        return mb_substr($nom, 0, 240) . '.pdf';
    }

    /**
     * Met le document commun, en PDF, dans un livrable : « nouveau » (le livrable « Document commun », créé s'il n'existe pas) ou l'identifiant d'un
     * livrable du projet. Un PDF du même nom déjà dans ce livrable est remplacé : le livrable garde le document tel qu'il est à présent.
     *
     * @return array{0: ?array{id: int, titre: string}, 1: ?string} le livrable, ou la raison du refus
     */
    public static function documentVersLivrable(int $moi, int $projetId, string $choix): array
    {
        $projet = self::projet($projetId, $moi);
        if ($projet === null || !self::livrablesDisponibles() || !self::ongletActif($projet, 'livrables')) {
            return [null, t('tr.lv.introuvable')];
        }
        if (trim((string) ($projet['document'] ?? '')) === '') {
            return [null, t('tr.do.vide')];
        }

        if ($choix === 'nouveau') {
            $titre = t('tr.do.livrable_titre');
            $l = Database::one('SELECT id, titre FROM projet_livrables WHERE projet_id = ? AND titre = ? ORDER BY id LIMIT 1', [$projetId, $titre]);
            if ($l === null) {
                $refus = self::creerLivrable($moi, $projetId, $titre, '', '');
                if ($refus !== null) {
                    return [null, $refus];
                }
                $l = Database::one('SELECT id, titre FROM projet_livrables WHERE projet_id = ? AND titre = ? ORDER BY id DESC LIMIT 1', [$projetId, $titre]);
            }
        } elseif (ctype_digit($choix)) {
            $l = self::livrable($moi, (int) $choix);
            if ($l === null || (int) $l['projet_id'] !== $projetId) {
                return [null, t('tr.lv.introuvable')];
            }
        } else {
            return [null, t('tr.lv.introuvable')];
        }
        $livrableId = (int) $l['id'];

        $nom = self::nomPdfDocument($projet);
        $anciens = Database::all('SELECT id, nom_stocke FROM projet_fichiers WHERE livrable_id = ? AND nom_origine = ?', [$livrableId, $nom]);
        $deja = (int) Database::valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$livrableId]);
        if ($deja - count($anciens) >= self::LIVRABLE_FICHIERS_MAX) {
            return [null, t('tr.lv.trop_de_fichiers', ['max' => self::LIVRABLE_FICHIERS_MAX])];
        }
        try {
            $pdf = self::documentEnPdf($projet);
        } catch (Throwable) {
            return [null, t('tr.do.pdf_echec')];
        }
        $dossier = self::dossier();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return [null, t('tr.do.pdf_echec')];
        }
        $nomStocke = bin2hex(random_bytes(16)) . '.pdf';
        if (file_put_contents($dossier . DIRECTORY_SEPARATOR . $nomStocke, $pdf) === false) {
            return [null, t('tr.do.pdf_echec')];
        }
        Database::run(
            'INSERT INTO projet_fichiers (projet_id, livrable_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projetId, $livrableId, $moi, $nom, $nomStocke, 'application/pdf', strlen($pdf)]);
        foreach ($anciens as $ancien) {
            $chemin = $dossier . DIRECTORY_SEPARATOR . basename((string) $ancien['nom_stocke']);
            if (is_file($chemin)) {
                @unlink($chemin);
            }
            Database::run('DELETE FROM projet_fichiers WHERE id = ?', [(int) $ancien['id']]);
        }

        return [['id' => $livrableId, 'titre' => (string) $l['titre']], null];
    }

    // --- Les cours et dossiers liés ----------------------------------------------------------------------------------------

    /** Au plus autant d'éléments liés par groupe. */
    public const LIENS_MAX = 60;

    /** Ce cours ou ce dossier est-il lié à un groupe dont la personne est membre ? (L'accès en lecture en découle : voir Partages.) */
    public static function lieAUnGroupeDe(string $type, int $id, int $moi): bool
    {
        return Database::valeur(
            "SELECT 1 FROM projet_liens l
               JOIN projet_membres m ON m.projet_id = l.projet_id AND m.user_id = ? AND m.statut = 'membre'
              WHERE l.type = ? AND l.cible_id = ? LIMIT 1",
            [$moi, $type, $id]
        ) !== null;
    }

    /**
     * Les cours, dossiers et fichiers liés au groupe, avec ce qu'il faut pour les montrer. Une cible effacée n'y figure plus.
     * Un fichier se consulte et se télécharge : il n'a ni copie à soi ni modification.
     *
     * @return list<array{id: int, type: string, cible_id: int, titre: string, icone: string, matiere: ?string, couleur: ?string,
     *                    proprietaire_id: int, ma_copie: ?int, proprietaire: string, ajoute_par: ?int, par: string, created_at: string}>
     */
    public static function liens(int $projet, int $moi = 0): array
    {
        $lignes = Database::all(
            "SELECT l.id, l.type, l.cible_id, l.ajoute_par, l.created_at, COALESCE(u.pseudo, u.nom, '') AS par,
                    c.titre AS cours_titre, c.user_id AS cours_user, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    d.nom AS dossier_nom, d.icone AS dossier_icone, d.user_id AS dossier_user,
                    f.nom_origine AS fichier_nom, f.mime AS fichier_mime, f.user_id AS fichier_user
               FROM projet_liens l
               LEFT JOIN users u ON u.id = l.ajoute_par
               LEFT JOIN cours c ON l.type = 'cours' AND c.id = l.cible_id
               LEFT JOIN matieres m ON m.id = c.matiere_id
               LEFT JOIN dossiers d ON l.type = 'dossier' AND d.id = l.cible_id
               LEFT JOIN fichiers f ON l.type = 'fichier' AND f.id = l.cible_id
              WHERE l.projet_id = ?
              ORDER BY l.created_at DESC, l.id DESC",
            [$projet]
        );
        $liens = [];
        foreach ($lignes as $l) {
            $cours = $l['type'] === 'cours';
            $fichier = $l['type'] === 'fichier';
            if (($fichier ? $l['fichier_nom'] : ($cours ? $l['cours_titre'] : $l['dossier_nom'])) === null) {
                continue;
            }
            $liens[] = [
                'id' => (int) $l['id'],
                'type' => (string) $l['type'],
                'cible_id' => (int) $l['cible_id'],
                'titre' => (string) ($fichier ? $l['fichier_nom'] : ($cours ? $l['cours_titre'] : $l['dossier_nom'])),
                'icone' => $fichier ? Fichiers::icone((string) $l['fichier_mime'], (string) $l['fichier_nom'])
                    : ($cours ? '📘' : ((string) $l['dossier_icone'] !== '' ? (string) $l['dossier_icone'] : '📁')),
                'matiere' => $cours && $l['matiere_nom'] !== null ? (string) $l['matiere_nom'] : null,
                'couleur' => $cours && $l['matiere_couleur'] !== null ? (string) $l['matiere_couleur'] : null,
                'proprietaire_id' => (int) ($fichier ? $l['fichier_user'] : ($cours ? $l['cours_user'] : $l['dossier_user'])),
                // Ce que j'en ai déjà copié chez moi : de quoi ne pas le proposer une seconde fois.
                'ma_copie' => $moi > 0 && !$fichier ? Partages::maCopie($moi, $cours ? 'cours' : 'dossier', (int) $l['cible_id']) : null,
                'ajoute_par' => $l['ajoute_par'] === null ? null : (int) $l['ajoute_par'],
                'par' => (string) $l['par'],
                'created_at' => (string) $l['created_at'],
            ];
        }

        return $liens;
    }

    /**
     * Les cours et dossiers de la personne qu'elle peut encore lier à ce groupe (pas déjà liés).
     *
     * @return array{cours: list<array{id: int, titre: string}>, dossiers: list<array{id: int, nom: string, icone: string, profondeur: int}>}
     */
    public static function aLier(int $projet, int $moi): array
    {
        $deja = static fn (string $type): array => array_map('intval', array_column(
            Database::all('SELECT cible_id FROM projet_liens WHERE projet_id = ? AND type = ?', [$projet, $type]), 'cible_id'));
        $coursLies = $deja('cours');
        $dossiersLies = $deja('dossier');

        return [
            'cours' => array_values(array_filter(
                Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$moi]),
                static fn (array $c): bool => !in_array((int) $c['id'], $coursLies, true))),
            'dossiers' => array_values(array_filter(
                DossiersController::pourUtilisateur($moi),
                static fn (array $d): bool => !in_array((int) $d['id'], $dossiersLies, true))),
        ];
    }

    /**
     * Lie un de ses cours ou dossiers au groupe. Il faut en être membre et que l'élément soit à soi ; une seule fois.
     *
     * @return ?string la raison du refus
     */
    public static function lier(int $moi, int $projet, string $type, int $cibleId): ?string
    {
        $leProjet = self::projet($projet, $moi);
        if ($leProjet === null) {
            return t('tr.err.pas_membre');
        }
        // Un groupe qui n'a pas l'onglet « Cours » (ou « Fichiers ») n'y reçoit rien : on ne le verrait pas.
        if (in_array($type, ['cours', 'dossier', 'fichier'], true) && !self::ongletActif($leProjet, $type === 'fichier' ? 'fichiers' : 'cours')) {
            return t('tr.ot.indisponible');
        }
        if (!in_array($type, ['cours', 'dossier', 'fichier'], true) || Partages::mienne($type, $cibleId, $moi) === null) {
            return t('tr.err.pas_a_vous');
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id = ?', [$projet]) >= self::LIENS_MAX) {
            return t('tr.err.trop_de_liens', ['max' => self::LIENS_MAX]);
        }
        $neuf = Database::run(
            'INSERT IGNORE INTO projet_liens (projet_id, type, cible_id, ajoute_par) VALUES (?, ?, ?, ?)',
            [$projet, $type, $cibleId, $moi]
        )->rowCount() > 0;

        return $neuf ? null : t('tr.err.deja_lie');
    }

    /**
     * Les travaux de groupe dont je suis membre et où ce cours ou dossier est lié.
     *
     * @return list<array{id: int, nom: string}>
     */
    public static function projetsDuDocument(int $moi, string $type, int $cibleId): array
    {
        return array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'nom' => (string) $p['nom']], Database::all(
            "SELECT DISTINCT p.id, p.nom FROM projet_liens l
               JOIN projets p ON p.id = l.projet_id
               JOIN projet_membres pm ON pm.projet_id = p.id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE l.type = ? AND l.cible_id = ? ORDER BY p.nom",
            [$moi, $type, $cibleId]
        ));
    }

    /**
     * Mes travaux de groupe, avec pour chacun le lien de ce cours ou dossier s'il y est déjà (l'identifiant du lien), sinon null. Pour la
     * fenêtre « Partager » : on y met un cours dans un projet, ou on l'en retire.
     *
     * @return list<array{id: int, nom: string, lien_id: ?int}>
     */
    public static function projetsPourPartage(int $moi, string $type, int $cibleId): array
    {
        $lignes = Database::all(
            "SELECT p.id, p.nom, " . (self::ongletsChoisissables() ? 'p.onglets' : 'NULL') . " AS onglets,
                    (SELECT l.id FROM projet_liens l WHERE l.projet_id = p.id AND l.type = ? AND l.cible_id = ?) AS lien_id
               FROM projets p
               JOIN projet_membres pm ON pm.projet_id = p.id AND pm.user_id = ? AND pm.statut = 'membre'
              ORDER BY p.nom, p.id",
            [$type, $cibleId, $moi]
        );

        // Un groupe sans l'onglet « Cours » (ou « Fichiers ») n'est pas proposé, sauf s'il a déjà l'élément (pour pouvoir l'en retirer).
        $onglet = $type === 'fichier' ? 'fichiers' : 'cours';
        $lignes = array_values(array_filter($lignes, static fn (array $l): bool => $l['lien_id'] !== null || self::ongletActif(['onglets' => $l['onglets']], $onglet)));

        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'], 'nom' => (string) $l['nom'], 'lien_id' => $l['lien_id'] === null ? null : (int) $l['lien_id'],
        ], $lignes);
    }

    /**
     * Retire un lien (l'élément reste chez son propriétaire) : qui l'a fait, ou un administrateur du groupe.
     *
     * @return ?int le groupe d'où le lien est retiré, ou null s'il n'est pas à retirer par cette personne
     */
    public static function delier(int $moi, int $lienId): ?int
    {
        $lien = Database::one('SELECT projet_id, ajoute_par FROM projet_liens WHERE id = ?', [$lienId]);
        if ($lien === null) {
            return null;
        }
        $projet = self::projet((int) $lien['projet_id'], $moi);
        if ($projet === null || ((int) ($lien['ajoute_par'] ?? 0) !== $moi && $projet['role'] !== 'admin')) {
            return null;
        }
        Database::run('DELETE FROM projet_liens WHERE id = ?', [$lienId]);

        return (int) $lien['projet_id'];
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
            'SELECT v.id, v.created_at, v.user_id, COALESCE(u.pseudo, u.nom) AS auteur, CHAR_LENGTH(v.contenu) AS longueur
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

    // --- Les onglets d'un projet : ceux que le groupe a choisis ---------------------------------------

    /** Les onglets au choix ; « Qui fait quoi » et « Membres » sont toujours là. */
    public const ONGLETS_AU_CHOIX = ['echeances', 'fichiers', 'cours', 'document', 'livrables', 'listes'];

    /** Le choix des onglets est-il possible ? (Sinon — migration pas encore importée — un projet a tous ses onglets.) */
    public static function ongletsChoisissables(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT onglets FROM projets LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /** Les onglets au choix qui existent sur ce site (« Livrables » et « Listes » demandent leur migration). */
    public static function ongletsProposes(): array
    {
        return array_values(array_filter(self::ONGLETS_AU_CHOIX, static fn (string $o): bool => match ($o) {
            'livrables' => self::livrablesDisponibles(),
            'listes' => self::listesDisponibles(),
            default => true,
        }));
    }

    /**
     * Les onglets au choix que ce projet propose : tous tant que le groupe n'a pas choisi (« onglets » nul), sinon ceux qu'il a gardés.
     *
     * @param array $projet une ligne de « projets » (avec « onglets » quand la migration est passée)
     * @return list<string>
     */
    public static function ongletsDuProjet(array $projet): array
    {
        $gardes = self::ONGLETS_AU_CHOIX;
        if (self::ongletsChoisissables() && ($projet['onglets'] ?? null) !== null) {
            $gardes = array_values(array_intersect(self::ONGLETS_AU_CHOIX, explode(',', (string) $projet['onglets'])));
        }

        return array_values(array_intersect($gardes, self::ongletsProposes()));
    }

    /** Ce projet a-t-il cet onglet ? (« Qui fait quoi » et « Membres » : toujours.) */
    public static function ongletActif(array $projet, string $onglet): bool
    {
        return !in_array($onglet, self::ONGLETS_AU_CHOIX, true) || in_array($onglet, self::ongletsDuProjet($projet), true);
    }

    /** Écrit le choix d'onglets d'un projet : tous cochés, c'est « pas de choix » (les onglets à venir apparaîtront d'eux-mêmes). */
    private static function ecrireOnglets(int $projet, array $cles): void
    {
        if (!self::ongletsChoisissables()) {
            return;
        }
        $proposes = self::ongletsProposes();
        $choisis = array_values(array_intersect($proposes, array_map('strval', $cles)));
        // Ceux que ce site ne propose pas encore (migration manquante) restent gardés : ils apparaîtront quand elle sera passée.
        $gardes = array_values(array_unique(array_merge($choisis, array_diff(self::ONGLETS_AU_CHOIX, $proposes))));
        Database::run('UPDATE projets SET onglets = ? WHERE id = ?', [count($choisis) === count($proposes) ? null : implode(',', $gardes), $projet]);
    }

    /** Les onglets du groupe : l'administrateur seulement. Rend la raison du refus. */
    public static function enregistrerOnglets(int $moi, int $projet, array $cles): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return t('tr.ot.admin');
        }
        if (!self::ongletsChoisissables()) {
            return t('tr.ot.indisponible_reglage');
        }
        self::ecrireOnglets($projet, $cles);

        return null;
    }

    // --- Les colonnes du tableau : celles de base, et les phases que l'administrateur ajoute ---------

    public const COLONNES_MAX = 8;
    public const COLONNE_NOM_MAX = 40;
    public const COLONNE_ICONE_DEFAUT = '🔹';

    /** Les colonnes en plus existent-elles ? (Sinon — migration pas encore importée — le tableau garde ses trois colonnes.) */
    public static function colonnesDisponibles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT colonne_id FROM projet_taches LIMIT 1');
                Database::valeur('SELECT icone, zone FROM projet_colonnes LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /** Les icônes proposées pour une phase. */
    public static function iconesColonnes(): array
    {
        return ['🔹', '💡', '🔍', '📋', '📝', '✏️', '🧠', '🛠️', '🧪', '🔬', '📊', '📚', '🎨', '💻', '🗣️', '🎤', '📦', '👀', '🔒', '⏳', '⭐', '📌', '🚀', '🏁'];
    }

    /**
     * Les phases du projet, de gauche à droite : celles rangées après « À faire » d'abord, puis celles d'après « En cours ».
     *
     * @return list<array{id: int, nom: string, icone: string, zone: string, position: int, nb_taches: int}>
     */
    public static function colonnes(int $projet): array
    {
        if (!self::colonnesDisponibles()) {
            return [];
        }

        return array_map(static fn (array $c): array => [
            'id' => (int) $c['id'], 'nom' => (string) $c['nom'], 'icone' => (string) $c['icone'], 'zone' => (string) $c['zone'],
            'position' => (int) $c['position'], 'nb_taches' => (int) $c['nb_taches'],
        ], Database::all(
            "SELECT c.id, c.nom, c.icone, c.zone, c.position, (SELECT COUNT(*) FROM projet_taches t WHERE t.colonne_id = c.id) AS nb_taches
               FROM projet_colonnes c WHERE c.projet_id = ? ORDER BY c.zone = 'en_cours', c.position, c.id",
            [$projet]
        ));
    }

    /**
     * Toutes les colonnes du tableau, de gauche à droite : « À faire », les phases rangées après lui, « En cours », les phases rangées après lui,
     * « Fait ». La clé est ce que le menu de statut d'une carte envoie : « a_faire », « en_cours », « fait », ou « col:12 » pour une phase.
     *
     * @return list<array{cle: string, nom: string, icone: string, phase: bool, id: ?int, nb_taches: ?int}>
     */
    public static function colonnesDuTableau(int $projet): array
    {
        $statuts = self::statuts();
        $base = static fn (string $cle): array => ['cle' => $cle, 'nom' => $statuts[$cle]['nom'], 'icone' => $statuts[$cle]['icone'], 'phase' => false, 'id' => null, 'nb_taches' => null];
        $phase = static fn (array $c): array => ['cle' => 'col:' . $c['id'], 'nom' => $c['nom'], 'icone' => $c['icone'], 'phase' => true, 'id' => $c['id'], 'nb_taches' => $c['nb_taches']];
        $phases = self::colonnes($projet);

        $colonnes = [$base('a_faire')];
        foreach ($phases as $c) {
            if ($c['zone'] === 'a_faire') {
                $colonnes[] = $phase($c);
            }
        }
        $colonnes[] = $base('en_cours');
        foreach ($phases as $c) {
            if ($c['zone'] !== 'a_faire') {
                $colonnes[] = $phase($c);
            }
        }
        $colonnes[] = $base('fait');

        return $colonnes;
    }

    /** La colonne où se range une tâche : sa phase s'il en a une, sinon son statut. */
    public static function cleDeColonne(array $tache): string
    {
        return ($tache['colonne_id'] ?? null) !== null ? 'col:' . (int) $tache['colonne_id'] : (string) $tache['statut'];
    }

    /** Une phase, si j'administre son projet. */
    public static function colonne(int $moi, int $colonneId): ?array
    {
        if (!self::colonnesDisponibles()) {
            return null;
        }
        $c = Database::one('SELECT * FROM projet_colonnes WHERE id = ?', [$colonneId]);

        return $c !== null && self::estAdmin((int) $c['projet_id'], $moi) ? $c : null;
    }

    /** Une icône parmi celles proposées ; sinon l'icône de départ. */
    private static function iconeValide(mixed $icone): string
    {
        return is_string($icone) && in_array($icone, self::iconesColonnes(), true) ? $icone : self::COLONNE_ICONE_DEFAUT;
    }

    private static function nomDeColonneValide(int $projet, string $nom, int $sauf = 0): ?string
    {
        $nom = self::nettoyer($nom, self::COLONNE_NOM_MAX);
        if ($nom === '') {
            return t('tr.cl.nom_obligatoire');
        }
        foreach (self::colonnesDuTableau($projet) as $c) {
            if ($c['cle'] !== 'col:' . $sauf && mb_strtolower($c['nom']) === mb_strtolower($nom)) {
                return t('tr.cl.nom_pris');
            }
        }

        return null;
    }

    /**
     * La suite des colonnes avant « Fait », de gauche à droite, en clés (« a_faire », « col:3 », « en_cours »…) : « À faire » est toujours la
     * première, « Fait » la dernière et n'y figure pas.
     *
     * @return list<string>
     */
    private static function suiteDesColonnes(int $projet): array
    {
        return array_values(array_filter(
            array_column(self::colonnesDuTableau($projet), 'cle'),
            static fn (string $cle): bool => $cle !== 'fait'
        ));
    }

    /** Écrit cette suite : chaque phase prend la zone (après « À faire » ou après « En cours ») et le rang que la suite lui donne. */
    private static function ecrireLaSuite(int $projet, array $cles): void
    {
        $zone = 'a_faire';
        $rang = 0;
        foreach ($cles as $cle) {
            if ($cle === 'en_cours') {
                $zone = 'en_cours';
            } elseif (preg_match('/^col:(\d+)$/', (string) $cle, $m) === 1) {
                Database::run('UPDATE projet_colonnes SET zone = ?, position = ? WHERE id = ? AND projet_id = ?', [$zone, ++$rang, (int) $m[1], $projet]);
            }
        }
    }

    /**
     * Ajoute une phase (l'administrateur du groupe seulement), rangée après la colonne « $apres » : « a_faire », « en_cours » ou une autre phase
     * (« col:3 ») ; sans rien de valable, tout à droite, avant « Fait ». Rend la raison du refus.
     */
    public static function creerColonne(int $moi, int $projet, string $nom, mixed $icone = null, string $apres = ''): ?string
    {
        if (!self::colonnesDisponibles() || !self::estAdmin($projet, $moi)) {
            return t('tr.cl.introuvable');
        }
        if (count(self::colonnes($projet)) >= self::COLONNES_MAX) {
            return t('tr.cl.trop', ['max' => self::COLONNES_MAX]);
        }
        $refus = self::nomDeColonneValide($projet, $nom);
        if ($refus !== null) {
            return $refus;
        }
        Database::run(
            'INSERT INTO projet_colonnes (projet_id, nom, icone, zone, position, created_at) VALUES (?, ?, ?, ?, 0, ?)',
            [$projet, self::nettoyer($nom, self::COLONNE_NOM_MAX), self::iconeValide($icone), 'en_cours', date('Y-m-d H:i:s')]);
        $cle = 'col:' . Database::dernierId();

        $suite = self::suiteDesColonnes($projet);
        $suite = array_values(array_filter($suite, static fn (string $c): bool => $c !== $cle));
        $rang = array_search($apres, $suite, true);
        array_splice($suite, $rang === false ? count($suite) : $rang + 1, 0, [$cle]);
        self::ecrireLaSuite($projet, $suite);

        return null;
    }

    /** Change le nom et l'icône d'une phase. */
    public static function modifierColonne(int $moi, int $colonneId, string $nom, mixed $icone = null): ?string
    {
        $c = self::colonne($moi, $colonneId);
        if ($c === null) {
            return t('tr.cl.introuvable');
        }
        $refus = self::nomDeColonneValide((int) $c['projet_id'], $nom, $colonneId);
        if ($refus !== null) {
            return $refus;
        }
        Database::run('UPDATE projet_colonnes SET nom = ?, icone = ? WHERE id = ?',
            [self::nettoyer($nom, self::COLONNE_NOM_MAX), is_string($icone) && in_array($icone, self::iconesColonnes(), true) ? $icone : (string) $c['icone'], $colonneId]);

        return null;
    }

    /** Supprime la phase : ses tâches restent « en cours », dans la colonne de base. Rend le projet, ou null. */
    public static function supprimerColonne(int $moi, int $colonneId): ?int
    {
        $c = self::colonne($moi, $colonneId);
        if ($c === null) {
            return null;
        }
        Database::run('UPDATE projet_taches SET colonne_id = NULL WHERE colonne_id = ?', [$colonneId]);
        Database::run('DELETE FROM projet_colonnes WHERE id = ?', [$colonneId]);

        return (int) $c['projet_id'];
    }

    /**
     * Décale la phase d'un cran, vers la gauche ou la droite — par-dessus « En cours » aussi, mais jamais avant « À faire » ni après « Fait ».
     * Rend le projet, ou null.
     */
    public static function deplacerColonne(int $moi, int $colonneId, bool $versLaGauche): ?int
    {
        $c = self::colonne($moi, $colonneId);
        if ($c === null) {
            return null;
        }
        $projet = (int) $c['projet_id'];
        $suite = self::suiteDesColonnes($projet);
        $rang = (int) array_search('col:' . $colonneId, $suite, true);
        $cible = $versLaGauche ? $rang - 1 : $rang + 1;
        if ($cible >= 1 && $cible < count($suite)) {
            [$suite[$rang], $suite[$cible]] = [$suite[$cible], $suite[$rang]];
            self::ecrireLaSuite($projet, $suite);
        }

        return $projet;
    }

    // --- Les listes à points à cocher : dans les tâches, et à part ------------------

    public const POINTS_MAX = 100;
    public const LISTES_MAX = 50;
    public const POINT_TEXTE_MAX = 200;
    public const LISTE_TITRE_MAX = 120;

    /** Les tables des listes existent-elles ? (Sinon — migration pas encore importée — l'onglet et les points n'apparaissent pas.) */
    public static function listesDisponibles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT 1 FROM projet_points LIMIT 1');
                Database::valeur('SELECT 1 FROM projet_listes LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Les points de toutes les tâches du projet, par tâche (identifiant de la tâche => points, dans l'ordre).
     *
     * @return array<int, list<array{id: int, texte: string, fait: bool, fait_par: ?string}>>
     */
    public static function pointsDesTaches(int $projet): array
    {
        if (!self::listesDisponibles()) {
            return [];
        }
        $parTache = [];
        foreach (Database::all(
            'SELECT p.id, p.tache_id, p.texte, p.fait, COALESCE(u.pseudo, u.nom) AS fait_par
               FROM projet_points p
               JOIN projet_taches t ON t.id = p.tache_id AND t.projet_id = ?
               LEFT JOIN users u ON u.id = p.fait_par
              ORDER BY p.tache_id, p.position, p.id',
            [$projet]
        ) as $p) {
            $parTache[(int) $p['tache_id']][] = ['id' => (int) $p['id'], 'texte' => (string) $p['texte'], 'fait' => (int) $p['fait'] === 1, 'fait_par' => $p['fait_par'] === null ? null : (string) $p['fait_par']];
        }

        return $parTache;
    }

    /**
     * Les points d'une tâche ou d'une liste.
     *
     * @return list<array{id: int, texte: string, fait: bool, fait_par: ?string}>
     */
    public static function points(string $parent, int $parentId): array
    {
        if (!self::listesDisponibles()) {
            return [];
        }

        return array_map(static fn (array $p): array => [
            'id' => (int) $p['id'], 'texte' => (string) $p['texte'], 'fait' => (int) $p['fait'] === 1, 'fait_par' => $p['fait_par'] === null ? null : (string) $p['fait_par'],
        ], Database::all(
            'SELECT p.id, p.texte, p.fait, COALESCE(u.pseudo, u.nom) AS fait_par FROM projet_points p LEFT JOIN users u ON u.id = p.fait_par
              WHERE p.' . ($parent === 'liste' ? 'liste_id' : 'tache_id') . ' = ? ORDER BY p.position, p.id',
            [$parentId]
        ));
    }

    /**
     * Les points d'une nouvelle tâche, écrits un par ligne dans le formulaire de création : chaque ligne non vide devient un point (200 caractères
     * au plus), jusqu'à la limite. Sans effet avant la migration.
     *
     * @return int combien de points ont été ajoutés
     */
    public static function ajouterPointsEnLot(int $tacheId, string $texte): int
    {
        if (!self::listesDisponibles()) {
            return 0;
        }
        $ajoutes = 0;
        foreach (preg_split('/\R/u', $texte) ?: [] as $ligne) {
            $ligne = self::nettoyer($ligne, self::POINT_TEXTE_MAX);
            if ($ligne === '') {
                continue;
            }
            if ($ajoutes >= self::POINTS_MAX) {
                break;
            }
            Database::run('INSERT INTO projet_points (tache_id, texte, position, created_at) VALUES (?, ?, ?, ?)', [$tacheId, $ligne, $ajoutes + 1, date('Y-m-d H:i:s')]);
            $ajoutes++;
        }

        return $ajoutes;
    }

    /**
     * Le statut d'une tâche suit ses points à cocher : tous cochés, elle est « Fait » ; quelques-uns, elle est « En cours » ; plus aucun,
     * une tâche « Fait » redevient « À faire ». Ajouter un point à une tâche faite la remet donc « En cours ». Appelé après chaque
     * changement de points ; sans point, le statut reste celui qu'on a choisi à la main.
     */
    private static function suivreLesPoints(int $tacheId): void
    {
        $l = Database::one('SELECT COUNT(*) AS total, COALESCE(SUM(fait), 0) AS faits FROM projet_points WHERE tache_id = ?', [$tacheId]);
        $total = (int) ($l['total'] ?? 0);
        $faits = (int) ($l['faits'] ?? 0);
        $statut = Database::valeur('SELECT statut FROM projet_taches WHERE id = ?', [$tacheId]);
        if ($total === 0 || $statut === null || $statut === false) {
            return;
        }
        $voulu = $faits === $total ? 'fait' : ($faits > 0 ? 'en_cours' : ($statut === 'fait' ? 'a_faire' : $statut));
        if ($voulu !== $statut) {
            Database::run(
                'UPDATE projet_taches SET statut = ?, ' . (self::colonnesDisponibles() ? 'colonne_id = NULL, ' : '') . 'fait_le = IF(statut = \'fait\', COALESCE(fait_le, ?), NULL) WHERE id = ?',
                [$voulu, date('Y-m-d H:i:s'), $tacheId]);
        }
    }

    /**
     * Les points de chaque tâche du projet, avec qui les a cochés et quand : de quoi dire, dans le récapitulatif, ce que chacun a fait.
     *
     * @return array<int, list<array{texte: string, fait: bool, fait_le: ?string, par_user_id: ?int}>>
     */
    public static function pointsPourRecapitulatif(int $projet): array
    {
        if (!self::listesDisponibles()) {
            return [];
        }
        $parTache = [];
        foreach (Database::all(
            'SELECT p.tache_id, p.texte, p.fait, p.fait_le, p.fait_par
               FROM projet_points p JOIN projet_taches t ON t.id = p.tache_id AND t.projet_id = ?
              ORDER BY p.tache_id, p.position, p.id',
            [$projet]
        ) as $p) {
            $parTache[(int) $p['tache_id']][] = ['texte' => (string) $p['texte'], 'fait' => (int) $p['fait'] === 1,
                'fait_le' => $p['fait_le'] === null ? null : (string) $p['fait_le'], 'par_user_id' => $p['fait_par'] === null ? null : (int) $p['fait_par']];
        }

        return $parTache;
    }

    /** « 2/5 » : combien de points faits sur combien. Null s'il n'y en a pas. */
    public static function avancementPoints(array $points): ?array
    {
        return $points === [] ? null : [count(array_filter($points, static fn (array $p): bool => $p['fait'])), count($points)];
    }

    /** Les listes du projet, avec leurs points. */
    public static function listes(int $projet): array
    {
        if (!self::listesDisponibles()) {
            return [];
        }
        $listes = Database::all('SELECT id, titre, created_at FROM projet_listes WHERE projet_id = ? ORDER BY position, id', [$projet]);
        foreach ($listes as &$l) {
            $l['points'] = self::points('liste', (int) $l['id']);
        }

        return $listes;
    }

    /** Une liste, si je suis membre (avec un compte) de son projet. */
    public static function liste(int $moi, int $listeId): ?array
    {
        if (!self::listesDisponibles()) {
            return null;
        }

        return Database::one(
            "SELECT l.* FROM projet_listes l
               JOIN projet_membres pm ON pm.projet_id = l.projet_id AND pm.user_id = ? AND pm.statut = 'membre'
              WHERE l.id = ?",
            [$moi, $listeId]
        );
    }

    public static function creerListe(int $moi, int $projet, string $titre): ?string
    {
        if (!self::listesDisponibles() || self::projet($projet, $moi) === null) {
            return t('tr.li2.introuvable');
        }
        $titre = self::nettoyer($titre, self::LISTE_TITRE_MAX);
        if ($titre === '') {
            return t('tr.li2.titre_obligatoire');
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM projet_listes WHERE projet_id = ?', [$projet]) >= self::LISTES_MAX) {
            return t('tr.li2.trop_de_listes', ['max' => self::LISTES_MAX]);
        }
        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM projet_listes WHERE projet_id = ?', [$projet]);
        Database::run('INSERT INTO projet_listes (projet_id, titre, cree_par, position, created_at) VALUES (?, ?, ?, ?, ?)', [$projet, $titre, $moi, $position, date('Y-m-d H:i:s')]);

        return null;
    }

    public static function renommerListe(int $moi, int $listeId, string $titre): ?string
    {
        if (self::liste($moi, $listeId) === null) {
            return t('tr.li2.introuvable');
        }
        $titre = self::nettoyer($titre, self::LISTE_TITRE_MAX);
        if ($titre === '') {
            return t('tr.li2.titre_obligatoire');
        }
        Database::run('UPDATE projet_listes SET titre = ? WHERE id = ?', [$titre, $listeId]);

        return null;
    }

    /**
     * Fait d'une liste une tâche du tableau : une tâche « à faire » du même nom, qui reprend les points de la liste tels quels (cochés ou non,
     * avec qui les a cochés) ; la liste disparaît. Personne n'en est chargé : on la prend ou on la confie ensuite.
     *
     * @return array{0: ?int, 1: ?int} [la tâche, le projet] — la tâche est nulle si la liste est introuvable
     */
    public static function listeEnTache(int $moi, int $listeId): array
    {
        $liste = self::liste($moi, $listeId);
        if ($liste === null) {
            return [null, null];
        }
        $projet = (int) $liste['projet_id'];
        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM projet_taches WHERE projet_id = ?', [$projet]);
        Database::run(
            'INSERT INTO projet_taches (projet_id, titre, position, cree_par) VALUES (?, ?, ?, ?)',
            [$projet, self::nettoyer((string) $liste['titre'], 200), $position, $moi]);
        $tache = Database::dernierId();
        Database::run('UPDATE projet_points SET tache_id = ?, liste_id = NULL WHERE liste_id = ?', [$tache, $listeId]);
        Database::run('DELETE FROM projet_listes WHERE id = ?', [$listeId]);
        self::suivreLesPoints($tache);   // des points déjà cochés : la tâche est en cours, ou faite

        return [$tache, $projet];
    }

    /** Efface la liste et ses points (n'importe quel membre : le groupe la tient ensemble). */
    public static function supprimerListe(int $moi, int $listeId): ?int
    {
        $liste = self::liste($moi, $listeId);
        if ($liste === null) {
            return null;
        }
        Database::run('DELETE FROM projet_listes WHERE id = ?', [$listeId]);

        return (int) $liste['projet_id'];
    }

    /**
     * Ajoute un point à une tâche (« tache ») ou à une liste (« liste »), à la fin.
     *
     * @return ?string la raison du refus
     */
    public static function ajouterPoint(int $moi, string $parent, int $parentId, string $texte): ?string
    {
        if (!self::listesDisponibles()) {
            return t('tr.li2.introuvable');
        }
        if ($parent === 'liste' ? self::liste($moi, $parentId) === null : self::tache($moi, $parentId) === null) {
            return t('tr.li2.introuvable');
        }
        $texte = self::nettoyer($texte, self::POINT_TEXTE_MAX);
        if ($texte === '') {
            return t('tr.li2.point_vide');
        }
        $colonne = $parent === 'liste' ? 'liste_id' : 'tache_id';
        if ((int) Database::valeur("SELECT COUNT(*) FROM projet_points WHERE $colonne = ?", [$parentId]) >= self::POINTS_MAX) {
            return t('tr.li2.trop_de_points', ['max' => self::POINTS_MAX]);
        }
        $position = (int) Database::valeur("SELECT COALESCE(MAX(position), 0) + 1 FROM projet_points WHERE $colonne = ?", [$parentId]);
        Database::run("INSERT INTO projet_points ($colonne, texte, position, created_at) VALUES (?, ?, ?, ?)", [$parentId, $texte, $position, date('Y-m-d H:i:s')]);
        if ($parent !== 'liste') {
            self::suivreLesPoints($parentId);
        }

        return null;
    }

    /**
     * Un point, si je suis membre du projet dont il dépend, avec ce qu'il faut pour revenir à sa page.
     *
     * @return ?array{id: int, tache_id: ?int, liste_id: ?int, projet_id: int, fait: int}
     */
    public static function point(int $moi, int $pointId): ?array
    {
        if (!self::listesDisponibles()) {
            return null;
        }
        $p = Database::one(
            "SELECT p.id, p.tache_id, p.liste_id, p.fait, COALESCE(t.projet_id, l.projet_id) AS projet_id
               FROM projet_points p
               LEFT JOIN projet_taches t ON t.id = p.tache_id
               LEFT JOIN projet_listes l ON l.id = p.liste_id
              WHERE p.id = ?",
            [$pointId]
        );
        if ($p === null || self::projet((int) $p['projet_id'], $moi) === null) {
            return null;
        }

        return ['id' => (int) $p['id'], 'tache_id' => $p['tache_id'] === null ? null : (int) $p['tache_id'],
                'liste_id' => $p['liste_id'] === null ? null : (int) $p['liste_id'], 'projet_id' => (int) $p['projet_id'], 'fait' => (int) $p['fait']];
    }

    /** Coche ou décoche un point (on garde qui l'a fait et quand). */
    public static function cocherPoint(int $moi, int $pointId, bool $fait): bool
    {
        if (self::point($moi, $pointId) === null) {
            return false;
        }
        Database::run(
            'UPDATE projet_points SET fait = ?, fait_par = ?, fait_le = ? WHERE id = ?',
            [$fait ? 1 : 0, $fait ? $moi : null, $fait ? date('Y-m-d H:i:s') : null, $pointId]
        );
        $point = self::point($moi, $pointId);
        if ($point !== null && $point['tache_id'] !== null) {
            self::suivreLesPoints($point['tache_id']);
        }

        return true;
    }

    public static function supprimerPoint(int $moi, int $pointId): bool
    {
        $point = self::point($moi, $pointId);
        if ($point === null) {
            return false;
        }
        Database::run('DELETE FROM projet_points WHERE id = ?', [$pointId]);
        if ($point['tache_id'] !== null) {
            self::suivreLesPoints($point['tache_id']);
        }

        return true;
    }

    // --- Le lien public, pour qui n'a pas de compte ----------------------------

    public static function ouvrirLien(int $moi, int $projet): ?string
    {
        if (!self::estAdmin($projet, $moi)) {
            return null;
        }
        $jeton = bin2hex(random_bytes(16));
        Database::run('UPDATE projets SET jeton = ? WHERE id = ?', [$jeton, $projet]);
        self::oublierVisiteurs($projet);   // un nouveau lien : ceux d'avant n'y ont plus accès

        return $jeton;
    }

    public static function fermerLien(int $moi, int $projet): bool
    {
        if (!self::estAdmin($projet, $moi)) {
            return false;
        }
        Database::run('UPDATE projets SET jeton = NULL WHERE id = ?', [$projet]);
        self::oublierVisiteurs($projet);

        return true;
    }

    // --- Ceux qui entrent par le lien public : un prénom, puis l'accès --------------

    /** Au plus autant de personnes entrées par lien : au-delà, on fabrique un autre lien. */
    public const VISITEURS_MAX = 300;

    /** Un prénom : des lettres (accents compris), des espaces, des tirets, des apostrophes et des points — rien d'autre. */
    public const PRENOM_MAX = 40;

    /** Le nom du cookie qui garde le jeton du visiteur, un par projet. */
    public static function cookieVisiteur(int $projet): string
    {
        return 'mc_visiteur_' . $projet;
    }

    /** Un prénom lisible, ou null s'il n'en est pas un (vide, trop long, des chiffres, des signes). */
    public static function prenomValide(string $brut): ?string
    {
        $prenom = trim((string) preg_replace('/\s+/u', ' ', $brut));
        if ($prenom === '' || mb_strlen($prenom) > self::PRENOM_MAX) {
            return null;
        }

        return preg_match('/^\p{L}[\p{L}\p{M}\' ’.-]*$/u', $prenom) === 1 ? $prenom : null;
    }

    /**
     * Le visiteur que ce navigateur est, pour ce projet : sa ligne, si son cookie lui donne accès. Sa visite est notée (au plus une fois toutes
     * les cinq minutes). Sans la table (migration pas encore importée), on rend un visiteur sans nom : le lien reste ouvert comme avant.
     *
     * @return ?array{id: int, prenom: string}
     */
    public static function visiteurDuLien(array $projet): ?array
    {
        try {
            $jeton = (string) ($_COOKIE[self::cookieVisiteur((int) $projet['id'])] ?? '');
            if (preg_match('/^[0-9a-f]{32}$/', $jeton) !== 1) {
                return null;
            }
            $ligne = Database::one(
                'SELECT v.id, v.prenom, v.derniere_visite FROM projet_visiteur_appareils a JOIN projet_visiteurs v ON v.id = a.visiteur_id
                  WHERE v.projet_id = ? AND a.empreinte = ?',
                [(int) $projet['id'], hash('sha256', $jeton)]
            );
            if ($ligne === null) {
                return null;
            }
            if (strtotime((string) $ligne['derniere_visite']) < time() - 300) {
                Database::run('UPDATE projet_visiteurs SET derniere_visite = ?, visites = visites + 1 WHERE id = ?', [date('Y-m-d H:i:s'), (int) $ligne['id']]);
            }

            return ['id' => (int) $ligne['id'], 'prenom' => (string) $ligne['prenom']];
        } catch (Throwable) {
            return ['id' => 0, 'prenom' => ''];
        }
    }

    /** Le lien public demande-t-il un prénom ? Oui, sauf tant que les tables (migrations) n'existent pas. */
    public static function lienDemandeUnPrenom(): bool
    {
        try {
            Database::valeur('SELECT 1 FROM projet_visiteurs LIMIT 1');
            Database::valeur('SELECT 1 FROM projet_visiteur_appareils LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Au plus autant d'appareils par personne entrée par le lien : les plus anciens s'effacent. */
    public const APPAREILS_MAX = 5;

    /**
     * Quelqu'un dit son prénom pour entrer par le lien. Un prénom ne se prend qu'une fois par projet (majuscules et accents ne comptent pas) :
     *  - nouveau : on le garde, on prévient les membres, et on rend le jeton à poser dans son cookie ;
     *  - déjà pris : on ne crée rien. « question » demande si c'est bien la même personne ; « moi » (la réponse) lui donne un nouvel appareil
     *    sur cette personne ; « autre » lui demande de se distinguer (l'initiale de son nom, par exemple).
     *
     * @param string $reponse  « moi » ou « autre » quand la personne a répondu à la question, sinon vide
     * @return array{etat: string, jeton: ?string, refus: ?string, prenom: string}
     *         état : « ok » (jeton rendu), « refus » (raison dite), « question » ou « distinguer » (le prénom pris, tel qu'il est écrit)
     */
    public static function entrerParLeLien(array $projet, string $prenomBrut, string $reponse = ''): array
    {
        $prenom = self::prenomValide($prenomBrut);
        if ($prenom === null) {
            return ['etat' => 'refus', 'jeton' => null, 'refus' => t('tr.pu.prenom_invalide', ['max' => self::PRENOM_MAX]), 'prenom' => $prenomBrut];
        }
        $projetId = (int) $projet['id'];
        $pris = static fn (): ?array => Database::one('SELECT id, prenom FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$projetId, $prenom]);

        $existant = $pris();
        if ($existant === null) {
            if ((int) Database::valeur('SELECT COUNT(*) FROM projet_visiteurs WHERE projet_id = ?', [$projetId]) >= self::VISITEURS_MAX) {
                return ['etat' => 'refus', 'jeton' => null, 'refus' => t('tr.pu.trop_de_monde'), 'prenom' => $prenom];
            }
            $maintenant = date('Y-m-d H:i:s');
            try {
                Database::run('INSERT INTO projet_visiteurs (projet_id, prenom, created_at, derniere_visite) VALUES (?, ?, ?, ?)', [$projetId, $prenom, $maintenant, $maintenant]);
                $visiteurId = Database::dernierId();
            } catch (PDOException) {
                // Quelqu'un vient de prendre ce prénom, à l'instant : on retombe sur le cas « déjà pris ».
                $existant = $pris();
                $visiteurId = 0;
            }
            if ($existant === null) {
                // Les membres qui ont un compte voient qui est arrivé, dans la langue de chacun.
                foreach (Database::all("SELECT user_id FROM projet_membres WHERE projet_id = ? AND statut = 'membre' AND user_id IS NOT NULL", [$projetId]) as $m) {
                    FileNotifications::ajouter((int) $m['user_id'], 'projet', fn (): array => [
                        'title' => '👥 ' . (string) $projet['nom'],
                        'body'  => t('tr.notif.visiteur', ['prenom' => $prenom]),
                        'url'   => url('travaux/' . $projetId . '/membres'),
                        'tag'   => 'projet-visiteur-' . $visiteurId,
                    ]);
                }

                self::devenirMembre($projetId, $visiteurId, $prenom);

                return ['etat' => 'ok', 'jeton' => self::nouvelAppareil($visiteurId), 'refus' => null, 'prenom' => $prenom];
            }
        }

        // Le prénom est déjà pris : la même personne qui revient (« moi »), ou une autre qui doit se distinguer.
        if ($reponse === 'moi') {
            Database::run('UPDATE projet_visiteurs SET derniere_visite = ?, visites = visites + 1 WHERE id = ?', [date('Y-m-d H:i:s'), (int) $existant['id']]);

            return ['etat' => 'ok', 'jeton' => self::nouvelAppareil((int) $existant['id']), 'refus' => null, 'prenom' => (string) $existant['prenom']];
        }

        return ['etat' => $reponse === 'autre' ? 'distinguer' : 'question', 'jeton' => null, 'refus' => null, 'prenom' => (string) $existant['prenom']];
    }

    /** La migration des membres sans compte par le lien est-elle passée ? (Sinon, entrer par le lien ne crée pas de membre.) */
    private static function membresParLeLien(): bool
    {
        try {
            Database::valeur('SELECT membre_id, membre_cree FROM projet_visiteurs LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Quelqu'un qui vient d'entrer par le lien devient membre sans compte du projet — un nom à qui confier des tâches, que les administrateurs
     * retirent quand ils veulent. Un nom sans compte déjà là, de même prénom, est celui de cette personne : on le reprend, sans doublon. Si le projet
     * est plein (30 membres), elle entre quand même, sans devenir membre.
     */
    private static function devenirMembre(int $projetId, int $visiteurId, string $prenom): void
    {
        if (!self::membresParLeLien()) {
            return;
        }
        $membre = Database::valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id IS NULL AND nom = ? ORDER BY id LIMIT 1', [$projetId, $prenom]);
        $cree = 0;
        if ($membre === null || $membre === false) {
            if (self::nbMembres($projetId) >= self::MEMBRES_MAX) {
                return;
            }
            Database::run("INSERT INTO projet_membres (projet_id, user_id, nom, role, statut, invite_par) VALUES (?, NULL, ?, 'membre', 'membre', NULL)", [$projetId, $prenom]);
            $membre = Database::dernierId();
            $cree = 1;
        }
        Database::run('UPDATE projet_visiteurs SET membre_id = ?, membre_cree = ? WHERE id = ?', [(int) $membre, $cree, $visiteurId]);
    }

    /**
     * Les membres du projet qui y sont par le lien public (leur id) : de quoi les marquer « Par le lien ». Vide, sans erreur, avant la migration.
     *
     * @return array<int, true>
     */
    public static function membresParLien(int $projet): array
    {
        try {
            $ids = [];
            foreach (Database::all('SELECT membre_id FROM projet_visiteurs WHERE projet_id = ? AND membre_id IS NOT NULL', [$projet]) as $l) {
                $ids[(int) $l['membre_id']] = true;
            }

            return $ids;
        } catch (Throwable) {
            return [];
        }
    }

    /** Un appareil de plus pour cette personne : le jeton à poser dans son cookie (on ne garde que son empreinte). */
    private static function nouvelAppareil(int $visiteurId): string
    {
        $jeton = bin2hex(random_bytes(16));
        Database::run('INSERT INTO projet_visiteur_appareils (visiteur_id, empreinte, created_at) VALUES (?, ?, ?)', [$visiteurId, hash('sha256', $jeton), date('Y-m-d H:i:s')]);
        Database::run(
            'DELETE FROM projet_visiteur_appareils WHERE visiteur_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM projet_visiteur_appareils WHERE visiteur_id = ? ORDER BY id DESC LIMIT ' . self::APPAREILS_MAX . ') gardes)',
            [$visiteurId, $visiteurId]
        );

        return $jeton;
    }
    /**
     * Les personnes entrées par le lien, la dernière arrivée en tête. Vide, sans erreur, tant que la table n'existe pas.
     *
     * @return list<array{id: int, prenom: string, created_at: string, derniere_visite: string, visites: int}>
     */
    public static function visiteurs(int $projet): array
    {
        try {
            return array_map(static fn (array $v): array => [
                'id' => (int) $v['id'], 'prenom' => (string) $v['prenom'], 'created_at' => (string) $v['created_at'],
                'derniere_visite' => (string) $v['derniere_visite'], 'visites' => (int) $v['visites'],
            ], Database::all('SELECT id, prenom, created_at, derniere_visite, visites FROM projet_visiteurs WHERE projet_id = ? ORDER BY created_at DESC, id DESC', [$projet]));
        } catch (Throwable) {
            return [];
        }
    }

    /** Retire quelqu'un du lien (un administrateur) : son cookie ne donne plus accès, il devra redire son prénom. */
    public static function retirerVisiteur(int $moi, int $projet, int $visiteurId): bool
    {
        if (!self::estAdmin($projet, $moi)) {
            return false;
        }
        try {
            // Le membre sans compte que le lien avait créé pour cette personne part avec elle ; un nom sans compte déjà là reste.
            $cree = null;
            if (self::membresParLeLien()) {
                $cree = Database::one('SELECT membre_id, membre_cree FROM projet_visiteurs WHERE id = ? AND projet_id = ?', [$visiteurId, $projet]);
            }
            $retire = Database::run('DELETE FROM projet_visiteurs WHERE id = ? AND projet_id = ?', [$visiteurId, $projet])->rowCount() > 0;
            if ($retire && $cree !== null && (int) $cree['membre_cree'] === 1 && $cree['membre_id'] !== null) {
                Database::run('DELETE FROM projet_membres WHERE id = ? AND projet_id = ? AND user_id IS NULL', [(int) $cree['membre_id'], $projet]);
            }

            return $retire;
        } catch (Throwable) {
            return false;
        }
    }

    private static function oublierVisiteurs(int $projet): void
    {
        try {
            Database::run('DELETE FROM projet_visiteurs WHERE projet_id = ?', [$projet]);
        } catch (Throwable) {
            // pas de table : rien à oublier
        }
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
            return t('tr.msg.projet_introuvable');
        }
        $comptes = self::comptes($projet);
        if ($conversation === null) {
            [$conversation, $refus] = Conversations::creerPourProjet($moi, (string) $p['nom'],
                array_values(array_diff($comptes, [$moi])));
            if ($refus !== null) {
                return $refus;
            }
        } elseif (Conversations::membre($conversation, $moi) === null || Serveurs::dUnSalon($conversation) !== null) {
            return t('tr.msg.pas_dans_discussion');
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
