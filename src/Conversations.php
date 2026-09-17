<?php
declare(strict_types=1);

/**
 * Les discussions de groupe.
 *
 * Une conversation réunit plusieurs comptes. On y ajoute ses amis, et
 * seulement eux ; ensuite, chaque membre voit les autres, qu'ils soient ses
 * amis ou non — c'est le principe d'un groupe. Tout ce qu'on sait faire à
 * deux s'y retrouve (photos, fichiers, vocaux, réponses, réactions, épingles,
 * recherche, fond d'écran), avec les mêmes contrôles, empruntés à Amis.
 *
 * Qui crée la conversation en est administrateur : les administrateurs
 * ajoutent leurs amis, invitent d'autres comptes par leur pseudo (qui
 * acceptent ou refusent), retirent des membres, et nomment ou retirent
 * d'autres administrateurs — il en reste toujours un. Chacun peut la
 * renommer, changer son fond d'écran, ou la quitter. Un nouveau membre ne
 * voit que ce qui s'écrit après son arrivée ; qui la quitte n'y voit plus rien.
 */
final class Conversations
{
    public const NOM_MAX = 60;

    /** Membres au plus, créateur compris. */
    public const MEMBRES_MAX = 50;

    /** L'adhésion d'un compte à une conversation, ou null. */
    public static function membre(int $conversation, int $userId): ?array
    {
        return Database::one(
            'SELECT * FROM conversation_membres WHERE conversation_id = ? AND user_id = ?',
            [$conversation, $userId]
        );
    }

    /** La conversation, si la personne en fait partie. */
    public static function conversation(int $conversation, int $moi): ?array
    {
        return Database::one(
            'SELECT c.*, mb.role, mb.depuis_message, mb.lu_jusqua
               FROM conversations c JOIN conversation_membres mb ON mb.conversation_id = c.id AND mb.user_id = ?
              WHERE c.id = ?',
            [$moi, $conversation]
        );
    }

    /**
     * Les membres, administrateurs d'abord, puis par pseudo.
     *
     * @return list<array{id: int, pseudo: string, role: string, rejoint_le: string}>
     */
    public static function membres(int $conversation): array
    {
        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'pseudo' => (string) $l['pseudo'],
            'role' => (string) $l['role'],
            'rejoint_le' => (string) $l['rejoint_le'],
        ], Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo, mb.role, mb.rejoint_le
               FROM conversation_membres mb JOIN users u ON u.id = mb.user_id
              WHERE mb.conversation_id = ?
              ORDER BY mb.role = 'admin' DESC, u.pseudo",
            [$conversation]
        ));
    }

    public static function estAdmin(int $conversation, int $moi): bool
    {
        return (self::membre($conversation, $moi)['role'] ?? '') === 'admin';
    }

    /** Un nom de conversation propre : sur une ligne, sans caractères invisibles. */
    private static function nettoyerNom(string $nom): string
    {
        return trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $nom));
    }

    private static function problemeNom(string $nom): ?string
    {
        if ($nom === '') {
            return 'Donnez un nom au groupe.';
        }
        if (mb_strlen($nom) > self::NOM_MAX) {
            return 'Le nom du groupe tient en ' . self::NOM_MAX . ' caractères.';
        }

        return null;
    }

    /**
     * Des comptes à ajouter : des identifiants distincts, autres que soi, tous
     * des amis de qui les ajoute.
     *
     * @return array{0: list<int>, 1: ?string}
     */
    private static function amisChoisis(int $moi, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0 && $id !== $moi)));
        foreach ($ids as $id) {
            if (!Amis::sontAmis($moi, $id)) {
                return [[], 'Vous ne pouvez ajouter que vos amis.'];
            }
        }

        return [$ids, null];
    }

    /**
     * Crée une conversation avec des amis.
     *
     * @return array{0: ?int, 1: ?string} la conversation, ou la raison du refus
     */
    public static function creer(int $moi, string $nom, array $ids): array
    {
        if ((string) (Amis::compte($moi)['pseudo'] ?? '') === '') {
            return [null, 'Choisissez d’abord un pseudo dans « Mon compte » : c’est lui que verront les membres.'];
        }
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return [null, $probleme];
        }
        [$ids, $refus] = self::amisChoisis($moi, $ids);
        if ($refus !== null) {
            return [null, $refus];
        }
        if ($ids === []) {
            return [null, 'Choisissez au moins un ami.'];
        }
        if (count($ids) + 1 > self::MEMBRES_MAX) {
            return [null, 'Un groupe réunit ' . self::MEMBRES_MAX . ' personnes au plus.'];
        }

        Database::run('INSERT INTO conversations (nom, cree_par, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$nom, $moi]);
        $conversation = Database::dernierId();
        Database::run(
            "INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, 'admin', UTC_TIMESTAMP())",
            [$conversation, $moi]
        );
        foreach ($ids as $id) {
            Database::run(
                "INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, 'membre', UTC_TIMESTAMP())",
                [$conversation, $id]
            );
        }
        self::noter($conversation, $moi, 'creation', null, $nom);

        return [$conversation, null];
    }

    /**
     * Ajoute des amis à la conversation (réservé aux administrateurs). Ils en
     * voient la suite, pas le passé.
     *
     * @return array{0: list<int>, 1: ?string} les comptes ajoutés, ou la raison du refus
     */
    public static function ajouter(int $moi, int $conversation, array $ids): array
    {
        if (!self::estAdmin($conversation, $moi)) {
            return [[], 'Seuls les administrateurs du groupe peuvent ajouter des membres.'];
        }
        [$ids, $refus] = self::amisChoisis($moi, $ids);
        if ($refus !== null) {
            return [[], $refus];
        }
        $deja = array_map('intval', array_column(Database::all(
            'SELECT user_id FROM conversation_membres WHERE conversation_id = ?', [$conversation]
        ), 'user_id'));
        $ids = array_values(array_diff($ids, $deja));
        if ($ids === []) {
            return [[], 'Choisissez au moins un ami qui n’est pas déjà dans le groupe.'];
        }
        if (count($deja) + count($ids) > self::MEMBRES_MAX) {
            return [[], 'Un groupe réunit ' . self::MEMBRES_MAX . ' personnes au plus.'];
        }

        $dernier = (int) Database::valeur('SELECT COALESCE(MAX(id), 0) FROM conversation_messages WHERE conversation_id = ?', [$conversation]);
        foreach ($ids as $id) {
            Database::run(
                "INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le, depuis_message, lu_jusqua)
                 VALUES (?, ?, 'membre', UTC_TIMESTAMP(), ?, ?)",
                [$conversation, $id, $dernier, $dernier]
            );
            Database::run('DELETE FROM conversation_invitations WHERE conversation_id = ? AND user_id = ?', [$conversation, $id]);
            self::noter($conversation, $moi, 'ajout', $id);
        }

        return [$ids, null];
    }

    /** Retire un membre (réservé aux administrateurs ; pour soi, c'est « quitter »). */
    public static function retirer(int $moi, int $conversation, int $cible): ?string
    {
        if (!self::estAdmin($conversation, $moi)) {
            return 'Seuls les administrateurs du groupe peuvent retirer des membres.';
        }
        if ($cible === $moi) {
            return 'Pour partir, quittez le groupe.';
        }
        if (self::membre($conversation, $cible) === null) {
            return 'Ce compte ne fait pas partie du groupe.';
        }
        self::oublier($conversation, $cible);
        self::noter($conversation, $moi, 'retrait', $cible);

        return null;
    }

    /** Nomme un membre administrateur (réservé aux administrateurs). */
    public static function nommerAdmin(int $moi, int $conversation, int $cible): ?string
    {
        if (!self::estAdmin($conversation, $moi)) {
            return 'Seuls les administrateurs du groupe peuvent en nommer d’autres.';
        }
        $membre = self::membre($conversation, $cible);
        if ($membre === null) {
            return 'Ce compte ne fait pas partie du groupe.';
        }
        if ($membre['role'] === 'admin') {
            return 'Ce membre est déjà administrateur.';
        }
        Database::run("UPDATE conversation_membres SET role = 'admin' WHERE conversation_id = ? AND user_id = ?", [$conversation, $cible]);
        self::noter($conversation, $moi, 'admin', $cible);

        return null;
    }

    /**
     * Repasse un administrateur en simple membre (réservé aux administrateurs).
     * On peut le faire pour soi, tant qu'il reste un autre administrateur :
     * un groupe en a toujours au moins un.
     */
    public static function retirerAdmin(int $moi, int $conversation, int $cible): ?string
    {
        if (!self::estAdmin($conversation, $moi)) {
            return 'Seuls les administrateurs du groupe peuvent retirer ce rôle.';
        }
        $membre = self::membre($conversation, $cible);
        if ($membre === null) {
            return 'Ce compte ne fait pas partie du groupe.';
        }
        if ($membre['role'] !== 'admin') {
            return 'Ce membre n’est pas administrateur.';
        }
        $admins = (int) Database::valeur("SELECT COUNT(*) FROM conversation_membres WHERE conversation_id = ? AND role = 'admin'", [$conversation]);
        if ($admins < 2) {
            return 'Le groupe doit garder au moins un administrateur : nommez-en un autre d’abord.';
        }
        Database::run("UPDATE conversation_membres SET role = 'membre' WHERE conversation_id = ? AND user_id = ?", [$conversation, $cible]);
        self::noter($conversation, $moi, 'admin_retire', $cible);

        return null;
    }

    /**
     * Des comptes trouvés par leur pseudo, pour les ajouter au groupe : ce
     * qu'on peut faire de chacun. Les comptes qui nous ont bloqués n'y sont
     * pas (Amis::chercher les écarte).
     *
     * @return list<array{id: int, pseudo: string, etat: string}> etat : membre, invite, bloque, ami, aucun
     */
    public static function chercher(int $moi, int $conversation, string $recherche): array
    {
        $membres = array_flip(array_map('intval', array_column(Database::all(
            'SELECT user_id FROM conversation_membres WHERE conversation_id = ?', [$conversation]
        ), 'user_id')));
        $invites = array_flip(array_map('intval', array_column(Database::all(
            'SELECT user_id FROM conversation_invitations WHERE conversation_id = ?', [$conversation]
        ), 'user_id')));

        return array_map(static fn (array $r): array => [
            'id' => $r['id'],
            'pseudo' => $r['pseudo'],
            'etat' => match (true) {
                isset($membres[$r['id']]) => 'membre',
                isset($invites[$r['id']]) => 'invite',
                $r['etat'] === 'bloque' => 'bloque',
                $r['etat'] === 'ami' => 'ami',
                default => 'aucun',
            },
        ], Amis::chercher($moi, $recherche));
    }

    /**
     * Ajoute quelqu'un trouvé par son pseudo (réservé aux administrateurs) :
     * un ami entre aussitôt, une autre personne reçoit une invitation.
     *
     * @return array{0: ?string, 1: ?string} « ajoute » ou « invite », ou la raison du refus
     */
    public static function inviter(int $moi, int $conversation, int $cible): array
    {
        if (!self::estAdmin($conversation, $moi)) {
            return [null, 'Seuls les administrateurs du groupe peuvent ajouter des membres.'];
        }
        $compte = Amis::compte($cible);
        if ($compte === null || $cible === $moi || Amis::aBloque($cible, $moi)) {
            return [null, 'Ce compte est introuvable.'];
        }
        if (Amis::aBloque($moi, $cible)) {
            return [null, 'Vous avez bloqué ' . $compte['pseudo'] . ' : débloquez-le d’abord.'];
        }
        if (self::membre($conversation, $cible) !== null) {
            return [null, $compte['pseudo'] . ' fait déjà partie du groupe.'];
        }
        if (Amis::sontAmis($moi, $cible)) {
            [, $refus] = self::ajouter($moi, $conversation, [$cible]);
            return $refus === null ? ['ajoute', null] : [null, $refus];
        }
        if (Database::valeur('SELECT 1 FROM conversation_invitations WHERE conversation_id = ? AND user_id = ?', [$conversation, $cible]) !== null) {
            return [null, $compte['pseudo'] . ' est déjà invité.'];
        }
        $places = (int) Database::valeur(
            'SELECT (SELECT COUNT(*) FROM conversation_membres WHERE conversation_id = ?) + (SELECT COUNT(*) FROM conversation_invitations WHERE conversation_id = ?)',
            [$conversation, $conversation]
        );
        if ($places >= self::MEMBRES_MAX) {
            return [null, 'Un groupe réunit ' . self::MEMBRES_MAX . ' personnes au plus, invitations comprises.'];
        }
        Database::run(
            'INSERT INTO conversation_invitations (conversation_id, user_id, invite_par, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$conversation, $cible, $moi]
        );
        self::noter($conversation, $moi, 'invitation', $cible);

        return ['invite', null];
    }

    /** Les invitations en attente d'un groupe, pour ses réglages. */
    public static function invitations(int $conversation): array
    {
        return Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo, COALESCE(p.pseudo, '') AS par, i.created_at
               FROM conversation_invitations i JOIN users u ON u.id = i.user_id LEFT JOIN users p ON p.id = i.invite_par
              WHERE i.conversation_id = ? ORDER BY i.created_at, u.pseudo",
            [$conversation]
        );
    }

    /** Les invitations reçues, pour la page « Amis ». */
    public static function mesInvitations(int $moi): array
    {
        return Database::all(
            "SELECT c.id, c.nom, c.photo_nom, COALESCE(p.pseudo, '') AS par, i.created_at,
                    (SELECT COUNT(*) FROM conversation_membres mb WHERE mb.conversation_id = c.id) AS membres
               FROM conversation_invitations i JOIN conversations c ON c.id = i.conversation_id LEFT JOIN users p ON p.id = i.invite_par
              WHERE i.user_id = ? ORDER BY i.created_at DESC",
            [$moi]
        );
    }

    public static function nombreInvitations(int $moi): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM conversation_invitations WHERE user_id = ?', [$moi]);
    }

    /** Annule une invitation (réservé aux administrateurs). */
    public static function annulerInvitation(int $moi, int $conversation, int $cible): ?string
    {
        if (!self::estAdmin($conversation, $moi)) {
            return 'Seuls les administrateurs du groupe peuvent annuler une invitation.';
        }
        if (Database::run('DELETE FROM conversation_invitations WHERE conversation_id = ? AND user_id = ?', [$conversation, $cible])->rowCount() === 0) {
            return 'Cette invitation n’existe plus.';
        }

        return null;
    }

    /**
     * Répond à une invitation : l'accepter fait entrer dans le groupe, qui se
     * lit à partir de là ; la refuser l'efface, sans rien dire au groupe.
     */
    public static function repondreInvitation(int $moi, int $conversation, bool $accepter): ?string
    {
        $invitation = Database::one('SELECT * FROM conversation_invitations WHERE conversation_id = ? AND user_id = ?', [$conversation, $moi]);
        if ($invitation === null) {
            return 'Cette invitation n’existe plus.';
        }
        Database::run('DELETE FROM conversation_invitations WHERE conversation_id = ? AND user_id = ?', [$conversation, $moi]);
        if (!$accepter || self::membre($conversation, $moi) !== null) {
            return null;
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM conversation_membres WHERE conversation_id = ?', [$conversation]) >= self::MEMBRES_MAX) {
            return 'Ce groupe est complet.';
        }
        $dernier = (int) Database::valeur('SELECT COALESCE(MAX(id), 0) FROM conversation_messages WHERE conversation_id = ?', [$conversation]);
        Database::run(
            "INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le, depuis_message, lu_jusqua)
             VALUES (?, ?, 'membre', UTC_TIMESTAMP(), ?, ?)",
            [$conversation, $moi, $dernier, $dernier]
        );
        self::noter($conversation, $moi, 'rejoint');

        return null;
    }

    /** Prévient qui vient d'être invité. */
    public static function notifierInvitation(int $auteur, int $conversation, int $cible): ?int
    {
        return FileNotifications::ajouter($cible, 'groupe', [
            'title' => '✉️ Invitation dans un groupe',
            'body' => (Amis::compte($auteur)['pseudo'] ?? 'Quelqu’un') . ' vous invite dans « ' . self::nom($conversation) . ' ».',
            'url' => url('amis'),
            'tag' => 'invitation-groupe-' . $conversation,
        ]);
    }

    /**
     * Quitte la conversation. S'il n'y reste plus d'administrateur, le plus
     * ancien membre le devient ; s'il n'y reste personne, elle est effacée,
     * ses fichiers compris.
     */
    public static function quitter(int $moi, int $conversation): bool
    {
        if (self::membre($conversation, $moi) === null) {
            return false;
        }
        self::oublier($conversation, $moi);

        $restants = (int) Database::valeur('SELECT COUNT(*) FROM conversation_membres WHERE conversation_id = ?', [$conversation]);
        if ($restants === 0) {
            self::effacer($conversation);
            return true;
        }
        self::noter($conversation, $moi, 'depart');
        $admins = (int) Database::valeur("SELECT COUNT(*) FROM conversation_membres WHERE conversation_id = ? AND role = 'admin'", [$conversation]);
        if ($admins === 0) {
            $suivant = (int) Database::valeur(
                'SELECT user_id FROM conversation_membres WHERE conversation_id = ? ORDER BY rejoint_le, user_id LIMIT 1',
                [$conversation]
            );
            Database::run("UPDATE conversation_membres SET role = 'admin' WHERE conversation_id = ? AND user_id = ?", [$conversation, $suivant]);
            self::noter($conversation, null, 'admin', $suivant);
        }

        return true;
    }

    /** Retire un compte de la conversation, avec ses épingles et ce qu'il y avait caché. */
    private static function oublier(int $conversation, int $userId): void
    {
        Database::run(
            'DELETE e FROM conversation_epingles e JOIN conversation_messages m ON m.id = e.message_id
              WHERE e.user_id = ? AND m.conversation_id = ?',
            [$userId, $conversation]
        );
        Database::run(
            'DELETE x FROM conversation_masques x JOIN conversation_messages m ON m.id = x.message_id
              WHERE x.user_id = ? AND m.conversation_id = ?',
            [$userId, $conversation]
        );
        Database::run('DELETE FROM conversation_membres WHERE conversation_id = ? AND user_id = ?', [$conversation, $userId]);
    }

    /** Efface une conversation sans membres : ses fichiers, puis tout le reste. */
    private static function effacer(int $conversation): void
    {
        $noms = Database::all(
            'SELECT image_nom AS nom FROM conversation_messages WHERE conversation_id = ? AND image_nom IS NOT NULL
             UNION ALL SELECT fichier_nom FROM conversation_messages WHERE conversation_id = ? AND fichier_nom IS NOT NULL
             UNION ALL SELECT audio_nom FROM conversation_messages WHERE conversation_id = ? AND audio_nom IS NOT NULL
             UNION ALL SELECT fond_nom FROM conversations WHERE id = ? AND fond_nom IS NOT NULL
             UNION ALL SELECT photo_nom FROM conversations WHERE id = ? AND photo_nom IS NOT NULL',
            [$conversation, $conversation, $conversation, $conversation, $conversation]
        );
        Database::run('DELETE FROM conversations WHERE id = ?', [$conversation]);
        foreach ($noms as $l) {
            self::effacerFichier((string) $l['nom']);
        }
    }

    private static function effacerFichier(?string $nom): void
    {
        if (is_string($nom) && preg_match('/^[0-9a-f]{32}\.[a-z0-9]{1,8}$/', $nom)) {
            @unlink(Amis::dossierImages() . DIRECTORY_SEPARATOR . $nom);
        }
    }

    /** Renomme la conversation (chaque membre le peut). */
    public static function renommer(int $moi, int $conversation, string $nom): ?string
    {
        $groupe = self::conversation($conversation, $moi);
        if ($groupe === null) {
            return 'Ce groupe est introuvable.';
        }
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return $probleme;
        }
        if ($nom === (string) $groupe['nom']) {
            return null;
        }
        Database::run('UPDATE conversations SET nom = ? WHERE id = ?', [$nom, $conversation]);
        self::noter($conversation, $moi, 'nom', null, $nom);

        return null;
    }

    /** L'adresse de la photo du groupe, qui change avec l'image. */
    public static function adressePhoto(int $conversation, ?string $nomPhoto): ?string
    {
        return $nomPhoto === null ? null : url('groupes/' . $conversation . '/photo', ['v' => substr($nomPhoto, 0, 12)]);
    }

    /** L'avatar du groupe : sa photo, ou 👥. */
    public static function avatar(int $conversation, ?string $nomPhoto, string $classes = ''): string
    {
        $adresse = self::adressePhoto($conversation, $nomPhoto);
        $classe = trim('avatar avatar--groupe ' . $classes);

        return $adresse === null
            ? '<span class="' . e($classe) . '" aria-hidden="true" data-groupe-avatar>👥</span>'
            : '<span class="' . e($classe) . ' avatar--photo" aria-hidden="true" data-groupe-avatar><img src="' . e($adresse) . '" alt=""></span>';
    }

    /** La photo du groupe, pour ses membres et ceux qui y sont invités. */
    public static function photo(int $conversation, int $moi): ?array
    {
        return Database::one(
            'SELECT c.photo_nom, c.photo_mime FROM conversations c
              WHERE c.id = ? AND c.photo_nom IS NOT NULL
                AND (EXISTS (SELECT 1 FROM conversation_membres mb WHERE mb.conversation_id = c.id AND mb.user_id = ?)
                  OR EXISTS (SELECT 1 FROM conversation_invitations i WHERE i.conversation_id = c.id AND i.user_id = ?))',
            [$conversation, $moi, $moi]
        );
    }

    /** Pose une photo de profil sur le groupe (chaque membre le peut). */
    public static function changerPhoto(int $moi, int $conversation, ?array $image): ?string
    {
        $groupe = self::conversation($conversation, $moi);
        if ($groupe === null) {
            return 'Ce groupe est introuvable.';
        }
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choisissez une image.';
        }
        $rangee = Amis::rangerImage($image);
        if (is_string($rangee)) {
            return $rangee;
        }
        Database::run('UPDATE conversations SET photo_nom = ?, photo_mime = ? WHERE id = ?', [$rangee['nom'], $rangee['mime'], $conversation]);
        self::effacerFichier($groupe['photo_nom']);
        self::noter($conversation, $moi, 'photo');

        return null;
    }

    /** Retire la photo du groupe. Vrai s'il y en avait une. */
    public static function retirerPhoto(int $moi, int $conversation): bool
    {
        $groupe = self::conversation($conversation, $moi);
        if ($groupe === null || $groupe['photo_nom'] === null) {
            return false;
        }
        Database::run('UPDATE conversations SET photo_nom = NULL, photo_mime = NULL WHERE id = ? AND photo_nom = ?', [$conversation, $groupe['photo_nom']]);
        self::effacerFichier($groupe['photo_nom']);
        self::noter($conversation, $moi, 'photo_retiree');

        return true;
    }

    /** L'adresse du fond, qui change avec l'image. */
    public static function adresseFond(int $conversation, ?string $nomFond): ?string
    {
        return $nomFond === null ? null : url('groupes/' . $conversation . '/fond', ['v' => substr($nomFond, 0, 12)]);
    }

    /** Pose un fond d'écran, pour tous les membres. */
    public static function changerFond(int $moi, int $conversation, ?array $image): ?string
    {
        $groupe = self::conversation($conversation, $moi);
        if ($groupe === null) {
            return 'Ce groupe est introuvable.';
        }
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choisissez une image.';
        }
        $rangee = Amis::rangerImage($image);
        if (is_string($rangee)) {
            return $rangee;
        }
        Database::run(
            'UPDATE conversations SET fond_nom = ?, fond_mime = ?, fond_par = ?, fond_le = UTC_TIMESTAMP() WHERE id = ?',
            [$rangee['nom'], $rangee['mime'], $moi, $conversation]
        );
        self::effacerFichier($groupe['fond_nom']);
        self::noter($conversation, $moi, 'fond');

        return null;
    }

    /** Retire le fond d'écran. Vrai s'il y en avait un. */
    public static function retirerFond(int $moi, int $conversation): bool
    {
        $groupe = self::conversation($conversation, $moi);
        if ($groupe === null || $groupe['fond_nom'] === null) {
            return false;
        }
        Database::run(
            'UPDATE conversations SET fond_nom = NULL, fond_mime = NULL, fond_par = NULL, fond_le = NULL WHERE id = ? AND fond_nom = ?',
            [$conversation, $groupe['fond_nom']]
        );
        self::effacerFichier($groupe['fond_nom']);
        self::noter($conversation, $moi, 'fond_retire');

        return true;
    }

    /** Écrit une note dans la conversation. */
    private static function noter(int $conversation, ?int $auteur, string $evenement, ?int $cible = null, ?string $texte = null): void
    {
        Database::run(
            "INSERT INTO conversation_messages (conversation_id, expediteur_id, texte, evenement, evenement_cible, evenement_texte, created_at)
             VALUES (?, ?, '', ?, ?, ?, UTC_TIMESTAMP())",
            [$conversation, $auteur, $evenement, $cible, $texte === null ? null : mb_substr($texte, 0, 80)]
        );
    }

    /** Le texte d'une note, du point de vue de qui la lit. */
    public static function texteEvenement(array $message, int $moi): string
    {
        $pseudo = static fn (?int $id): string => $id === null ? 'Un ancien membre' : (string) (Amis::compte($id)['pseudo'] ?? 'Un ancien membre');
        $auteur = $message['expediteur_id'] === null ? null : (int) $message['expediteur_id'];
        $cible = $message['evenement_cible'] === null ? null : (int) $message['evenement_cible'];
        $qui = $auteur === $moi ? 'Vous avez' : $pseudo($auteur) . ' a';
        $quiCible = $cible === $moi ? 'vous' : $pseudo($cible);
        $texte = (string) ($message['evenement_texte'] ?? '');
        // « Alma vous a ajouté » plutôt que « Alma a ajouté vous ».
        $geste = static fn (string $participe, string $suite = ''): string => $cible === $moi && $auteur !== $moi
            ? $pseudo($auteur) . ' vous a ' . $participe . $suite
            : $qui . ' ' . $participe . ' ' . $quiCible . $suite;

        return match ((string) $message['evenement']) {
            'creation' => '👥 ' . $qui . ' créé le groupe « ' . $texte . ' »',
            'ajout' => '➕ ' . $geste('ajouté'),
            'retrait' => '➖ ' . $geste('retiré'),
            'depart' => '🚪 ' . ($auteur === $moi ? 'Vous avez' : $pseudo($auteur) . ' a') . ' quitté le groupe',
            'nom' => '✏️ ' . $qui . ' renommé le groupe « ' . $texte . ' »',
            'admin' => $auteur === null
                ? '⭐ ' . ($cible === $moi ? 'Vous êtes' : $quiCible . ' est') . ' maintenant administrateur'
                : '⭐ ' . $geste('nommé', ' administrateur'),
            'admin_retire' => $auteur === $cible
                ? '⭐ ' . ($auteur === $moi ? 'Vous n’êtes' : $pseudo($auteur) . ' n’est') . ' plus administrateur'
                : '⭐ ' . ($cible === $moi
                    ? $pseudo($auteur) . ' vous a retiré le rôle d’administrateur'
                    : $qui . ' retiré le rôle d’administrateur à ' . $quiCible),
            'invitation' => '✉️ ' . $geste('invité'),
            'rejoint' => '➕ ' . ($auteur === $moi ? 'Vous avez' : $pseudo($auteur) . ' a') . ' rejoint le groupe',
            'fond' => '🖼️ ' . $qui . ' changé le fond d’écran',
            'fond_retire' => '🖼️ ' . $qui . ' retiré le fond d’écran',
            'photo' => '📷 ' . $qui . ' changé la photo du groupe',
            'photo_retiree' => '📷 ' . $qui . ' retiré la photo du groupe',
            default => $qui . ' modifié le groupe',
        };
    }

    /**
     * Les conversations de la personne, avec les non-lus et la dernière ligne
     * visible pour elle.
     */
    public static function liste(int $moi): array
    {
        return Database::all(
            "SELECT c.id, c.nom, c.created_at, c.photo_nom,
                    (SELECT COUNT(*) FROM conversation_messages m
                      WHERE m.conversation_id = c.id AND m.id > GREATEST(mb.lu_jusqua, mb.depuis_message)
                        AND (m.expediteur_id IS NULL OR m.expediteur_id <> ?)
                        AND m.evenement IS NULL AND m.supprime_le IS NULL
                        AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)) AS non_lus,
                    (SELECT m.id FROM conversation_messages m
                      WHERE m.conversation_id = c.id AND m.id > mb.depuis_message
                        AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)
                      ORDER BY m.id DESC LIMIT 1) AS dernier_id
               FROM conversation_membres mb JOIN conversations c ON c.id = mb.conversation_id
              WHERE mb.user_id = ?
              ORDER BY c.id DESC",
            [$moi, $moi, $moi, $moi]
        );
    }

    /** Les dernières lignes, par identifiant, pour l'aperçu de la liste. */
    public static function messagesParId(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return array_column(Database::all(
            'SELECT id, expediteur_id, texte, evenement, evenement_cible, evenement_texte, image_nom, fichier_origine, audio_nom, supprime_le, created_at
               FROM conversation_messages WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        ), null, 'id');
    }

    /** Les messages non lus, toutes conversations confondues : pour l'onglet « Amis ». */
    public static function nonLus(int $moi): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM conversation_membres mb
               JOIN conversation_messages m ON m.conversation_id = mb.conversation_id
              WHERE mb.user_id = ? AND m.id > GREATEST(mb.lu_jusqua, mb.depuis_message)
                AND (m.expediteur_id IS NULL OR m.expediteur_id <> ?)
                AND m.evenement IS NULL AND m.supprime_le IS NULL
                AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)',
            [$moi, $moi, $moi]
        );
    }

    /**
     * Écrit dans la conversation. Mêmes règles qu'entre deux amis : une pièce
     * jointe au plus, vérifiée et rangée par Amis.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function ecrire(int $moi, int $conversation, string $texte, ?array $image = null, ?array $fichier = null, ?int $reponseA = null,
                                  ?array $vocal = null, int $dureeVocal = 0, ?string $transcription = null): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        $present = static fn (?array $f): bool => $f !== null && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avecImage = $present($image);
        $avecFichier = $present($fichier);
        $avecVocal = $present($vocal);
        if ($texte === '' && !$avecImage && !$avecFichier && !$avecVocal) {
            return [null, 'Le message est vide.'];
        }
        if ((int) $avecImage + (int) $avecFichier + (int) $avecVocal > 1) {
            return [null, 'Une seule pièce jointe par message.'];
        }
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return [null, 'Un message ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.'];
        }
        if (self::membre($conversation, $moi) === null) {
            return [null, 'Vous ne faites pas partie de ce groupe.'];
        }
        $recents = (int) Database::valeur(
            'SELECT COUNT(*) FROM conversation_messages WHERE expediteur_id = ? AND evenement IS NULL AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 MINUTE',
            [$moi]
        );
        if ($recents >= Amis::MESSAGES_PAR_MINUTE) {
            return [null, 'Trop de messages d’un coup : patientez un instant.'];
        }

        $rangee = $avecImage ? Amis::rangerImage($image) : null;
        if (is_string($rangee)) {
            return [null, $rangee];
        }
        $joint = $avecFichier ? Amis::rangerFichier($fichier) : null;
        if (is_string($joint)) {
            return [null, $joint];
        }
        $transcrit = $avecVocal && (int) Database::valeur('SELECT transcription_vocale FROM users WHERE id = ?', [$moi]) === 1
            ? Amis::transcription($transcription) : null;
        $enregistre = $avecVocal ? Amis::rangerVocal($vocal, $dureeVocal) : null;
        if (is_string($enregistre)) {
            return [null, $enregistre];
        }

        try {
            Database::run(
                'INSERT INTO conversation_messages (conversation_id, expediteur_id, reponse_a, texte, image_nom, image_mime, image_largeur, image_hauteur,
                                       fichier_nom, fichier_origine, fichier_mime, fichier_taille, audio_nom, audio_duree, audio_transcription, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$conversation, $moi, $reponseA !== null && self::visible($moi, $conversation, $reponseA) ? $reponseA : null,
                 $texte, $rangee['nom'] ?? null, $rangee['mime'] ?? null, $rangee['largeur'] ?? null, $rangee['hauteur'] ?? null,
                 $joint['nom'] ?? null, $joint['origine'] ?? null, $joint['mime'] ?? null, $joint['taille'] ?? null,
                 $enregistre['nom'] ?? null, $enregistre['duree'] ?? null, $transcrit]
            );
        } catch (Throwable $e) {
            foreach ([$rangee, $joint, $enregistre] as $range) {
                self::effacerFichier($range['nom'] ?? null);
            }
            throw $e;
        }
        $id = Database::dernierId();
        Database::run(
            'UPDATE conversation_membres SET lu_jusqua = GREATEST(lu_jusqua, ?) WHERE conversation_id = ? AND user_id = ?',
            [$id, $conversation, $moi]
        );

        return [$id, null];
    }

    /**
     * Les messages visibles après tel identifiant (ou les derniers), du plus
     * ancien au plus récent ; la conversation est marquée lue jusque-là.
     */
    public static function fil(int $moi, int $conversation, int $apres = 0, ?int $depuisMessage = null): array
    {
        $messages = $depuisMessage !== null
            ? self::lignes($moi, $conversation, 'm.id >= ?', [$depuisMessage], 'ORDER BY m.id DESC LIMIT ' . Amis::FIL_DEPUIS_MAX)
            : self::lignes($moi, $conversation, 'm.id > ?', [$apres], 'ORDER BY m.id DESC LIMIT ' . Amis::FIL_MAX);
        if ($messages !== []) {
            Database::run(
                'UPDATE conversation_membres SET lu_jusqua = GREATEST(lu_jusqua, ?) WHERE conversation_id = ? AND user_id = ?',
                [(int) $messages[0]['id'], $conversation, $moi]
            );
        }

        return self::avecReactions(array_map(static fn (array $m): array => self::pourAffichage($m, $moi), array_reverse($messages)), $moi);
    }

    /**
     * Les messages de la conversation que la personne peut voir : arrivés
     * après elle, et pas cachés pour elle.
     */
    private static function lignes(int $moi, int $conversation, string $condition, array $parametres, string $suite): array
    {
        $depuis = (int) (self::membre($conversation, $moi)['depuis_message'] ?? PHP_INT_MAX);

        return Database::all(
            'SELECT m.id, m.expediteur_id, m.texte, m.image_nom, m.image_largeur, m.image_hauteur,
                    m.fichier_nom, m.fichier_origine, m.fichier_mime, m.fichier_taille, m.audio_nom, m.audio_duree, m.audio_transcription,
                    m.evenement, m.evenement_cible, m.evenement_texte,
                    m.created_at, m.modifie_le, m.supprime_le, m.reponse_a,
                    r.expediteur_id AS r_expediteur, r.texte AS r_texte, r.image_nom AS r_image,
                    r.fichier_origine AS r_fichier, r.audio_nom AS r_audio, r.supprime_le AS r_supprime,
                    (r.id <= ? OR EXISTS (SELECT 1 FROM conversation_masques rx WHERE rx.message_id = r.id AND rx.user_id = ?)) AS r_masque
               FROM conversation_messages m
               LEFT JOIN conversation_messages r ON r.id = m.reponse_a
              WHERE m.conversation_id = ? AND m.id > ?
                AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)
                AND ' . $condition . ' ' . $suite,
            array_merge([$depuis, $moi, $conversation, $depuis, $moi], $parametres)
        );
    }

    /** Un message de la conversation, qu'on peut citer : visible pour soi, et pas une note. */
    private static function visible(int $moi, int $conversation, int $messageId): bool
    {
        return self::lignes($moi, $conversation, 'm.id = ? AND m.evenement IS NULL', [$messageId], '') !== [];
    }

    /**
     * Un message, pour agir dessus : visible pour la personne, qui est toujours
     * membre. Les notes n'en sont pas.
     */
    private static function message(int $moi, int $messageId): ?array
    {
        $message = Database::one(
            'SELECT m.*, mb.depuis_message FROM conversation_messages m
               JOIN conversation_membres mb ON mb.conversation_id = m.conversation_id AND mb.user_id = ?
              WHERE m.id = ? AND m.id > mb.depuis_message AND m.evenement IS NULL
                AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)',
            [$moi, $messageId, $moi]
        );

        return $message;
    }

    /** Un message avec sa pièce jointe (image, fichier, vocal), pour qui a le droit de le voir. */
    public static function piece(int $moi, int $messageId, string $colonne): ?array
    {
        if (!in_array($colonne, ['image_nom', 'fichier_nom', 'audio_nom'], true)) {
            return null;
        }
        $message = self::message($moi, $messageId);

        return $message !== null && $message[$colonne] !== null ? $message : null;
    }

    public static function modifications(int $moi, int $conversation, string $depuis): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $depuis)) {
            return [];
        }
        $lignes = self::lignes($moi, $conversation, 'm.modifie_le >= ? - INTERVAL 2 SECOND', [$depuis], 'ORDER BY m.id LIMIT 200');

        return self::avecReactions(array_map(static fn (array $m): array => self::pourAffichage($m, $moi), $lignes), $moi);
    }

    /**
     * Ce qui a changé dans les messages déjà affichés : supprimés pour tous, ou
     * cachés pour soi depuis un autre onglet.
     *
     * @return array{supprimes: list<int>, masques: list<int>}
     */
    public static function changements(int $moi, int $conversation, int $jusqua): array
    {
        $supprimes = array_map('intval', array_column(self::lignes(
            $moi, $conversation, 'm.id <= ? AND m.supprime_le IS NOT NULL', [$jusqua], 'ORDER BY m.id DESC LIMIT 500'
        ), 'id'));
        $masques = array_map('intval', array_column(Database::all(
            'SELECT x.message_id FROM conversation_masques x JOIN conversation_messages m ON m.id = x.message_id
              WHERE x.user_id = ? AND m.conversation_id = ? AND m.id <= ?
              ORDER BY x.message_id DESC LIMIT 500',
            [$moi, $conversation, $jusqua]
        ), 'message_id'));

        return ['supprimes' => $supprimes, 'masques' => $masques];
    }

    public static function reactionsModifiees(int $moi, int $conversation, string $depuis): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $depuis)) {
            return [];
        }
        $ids = array_map('intval', array_column(self::lignes(
            $moi, $conversation, 'm.reactions_le >= ? - INTERVAL 2 SECOND', [$depuis], 'ORDER BY m.id LIMIT 200'
        ), 'id'));
        $reactions = self::reactionsDe($ids, $moi);

        return array_map(static fn (int $id): array => ['id' => $id, 'reactions' => $reactions[$id] ?? []], $ids);
    }

    /** Modifie le texte d'un de ses messages. */
    public static function modifierMessage(int $moi, int $messageId, string $texte): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        $message = self::message($moi, $messageId);
        if ($message === null || (int) $message['expediteur_id'] !== $moi) {
            return [false, 'Seul qui a écrit un message peut le modifier.'];
        }
        if ($message['supprime_le'] !== null) {
            return [false, 'Un message supprimé ne peut plus être modifié.'];
        }
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return [false, 'Un message ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.'];
        }
        if ($texte === '' && $message['image_nom'] === null && $message['fichier_nom'] === null && $message['audio_nom'] === null) {
            return [false, 'Le message ne peut pas être vide : pour l’enlever, supprimez-le.'];
        }
        if ($texte !== (string) $message['texte']) {
            Database::run('UPDATE conversation_messages SET texte = ?, modifie_le = UTC_TIMESTAMP() WHERE id = ?', [$texte, $messageId]);
        }

        return [true, 'Message modifié.'];
    }

    /**
     * Supprime un message : pour soi (« moi »), ou pour tout le groupe
     * (« tous », réservé à qui l'a écrit : son contenu est effacé).
     */
    public static function supprimerMessage(int $moi, int $messageId, string $portee): string
    {
        $message = self::message($moi, $messageId);
        if ($message === null) {
            return 'introuvable';
        }
        if ($portee === 'tous') {
            if ((int) $message['expediteur_id'] !== $moi) {
                return 'interdit';
            }
            foreach ([$message['image_nom'], $message['fichier_nom'], $message['audio_nom']] as $nom) {
                self::effacerFichier($nom);
            }
            Database::run(
                "UPDATE conversation_messages SET texte = '', image_nom = NULL, image_mime = NULL, image_largeur = NULL, image_hauteur = NULL,
                        fichier_nom = NULL, fichier_origine = NULL, fichier_mime = NULL, fichier_taille = NULL,
                        audio_nom = NULL, audio_duree = NULL, audio_transcription = NULL, reponse_a = NULL,
                        supprime_le = COALESCE(supprime_le, UTC_TIMESTAMP())
                  WHERE id = ?",
                [$messageId]
            );
            if (Database::run('DELETE FROM conversation_reactions WHERE message_id = ?', [$messageId])->rowCount() > 0) {
                Database::run('UPDATE conversation_messages SET reactions_le = UTC_TIMESTAMP() WHERE id = ?', [$messageId]);
            }

            return 'fait';
        }
        Database::run('INSERT IGNORE INTO conversation_masques (message_id, user_id) VALUES (?, ?)', [$messageId, $moi]);
        Database::run('DELETE FROM conversation_epingles WHERE message_id = ? AND user_id = ?', [$messageId, $moi]);

        return 'fait';
    }

    /**
     * Réagit à un message, ou retire sa réaction.
     *
     * @return array{0: bool, 1: string, 2: list<array>, 3: ?int}
     */
    public static function reagir(int $moi, int $messageId, string $emoji): array
    {
        $emoji = trim($emoji);
        $message = self::message($moi, $messageId);
        if ($message === null) {
            return [false, 'Ce message est introuvable.', [], null];
        }
        if ($message['supprime_le'] !== null) {
            return [false, 'On ne réagit pas à un message supprimé.', [], null];
        }
        if ($emoji !== '' && !Amis::emojiValide($emoji)) {
            return [false, 'Une réaction, c’est un emoji.', [], null];
        }

        $actuelle = Database::valeur('SELECT emoji FROM conversation_reactions WHERE message_id = ? AND user_id = ?', [$messageId, $moi]);
        $pose = $emoji !== '' && $emoji !== $actuelle;
        if ($pose) {
            Database::run(
                'INSERT INTO conversation_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = UTC_TIMESTAMP()',
                [$messageId, $moi, $emoji]
            );
        } else {
            Database::run('DELETE FROM conversation_reactions WHERE message_id = ? AND user_id = ?', [$messageId, $moi]);
        }
        Database::run('UPDATE conversation_messages SET reactions_le = UTC_TIMESTAMP() WHERE id = ?', [$messageId]);

        // L'auteur, s'il est toujours membre et n'a pas la discussion sous les yeux, est prévenu.
        $notification = null;
        $auteur = $message['expediteur_id'] === null ? null : (int) $message['expediteur_id'];
        $conversation = (int) $message['conversation_id'];
        if ($pose && $auteur !== null && $auteur !== $moi && self::absent($conversation, $auteur)) {
            $extrait = Amis::extrait([
                'r_texte' => $message['texte'], 'r_image' => $message['image_nom'], 'r_fichier' => $message['fichier_origine'],
                'r_audio' => $message['audio_nom'], 'r_supprime' => null, 'r_masque' => 0,
            ]);
            $notification = FileNotifications::ajouter($auteur, 'reaction', [
                'title' => $emoji . ' ' . (Amis::compte($moi)['pseudo'] ?? 'Un membre') . ' a réagi · ' . self::nom($conversation),
                'body' => 'À votre message : « ' . $extrait . ' »',
                'url' => url('groupes/' . $conversation),
                'tag' => 'reaction-groupe-' . $conversation,
            ]);
        }

        return [true, $pose ? 'Réaction ajoutée.' : 'Réaction retirée.', self::reactionsDe([$messageId], $moi)[$messageId] ?? [], $notification];
    }

    private static function nom(int $conversation): string
    {
        return (string) Database::valeur('SELECT nom FROM conversations WHERE id = ?', [$conversation]);
    }

    /** Les réactions de plusieurs messages, regroupées par emoji, avec qui. */
    public static function reactionsDe(array $ids, int $moi): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $lignes = Database::all(
            "SELECT r.message_id, r.user_id, r.emoji, COALESCE(u.pseudo, '') AS pseudo
               FROM conversation_reactions r JOIN users u ON u.id = r.user_id
              WHERE r.message_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')
              ORDER BY r.created_at, r.user_id',
            $ids
        );
        $parMessage = [];
        foreach ($lignes as $l) {
            $m = (int) $l['message_id'];
            $e = (string) $l['emoji'];
            $parMessage[$m][$e] ??= ['emoji' => $e, 'nombre' => 0, 'moi' => false, 'qui' => []];
            $parMessage[$m][$e]['nombre']++;
            if ((int) $l['user_id'] === $moi) {
                $parMessage[$m][$e]['moi'] = true;
                array_unshift($parMessage[$m][$e]['qui'], 'Vous');
            } else {
                $parMessage[$m][$e]['qui'][] = (string) $l['pseudo'];
            }
        }

        return array_map(static fn (array $groupes): array => array_values(array_map(
            static fn (array $g): array => ['qui' => implode(', ', $g['qui'])] + $g, $groupes
        )), $parMessage);
    }

    private static function avecReactions(array $messages, int $moi): array
    {
        $ids = array_column($messages, 'id');
        $reactions = self::reactionsDe($ids, $moi);
        $epingles = $ids === [] ? [] : array_flip(array_map('intval', array_column(Database::all(
            'SELECT message_id FROM conversation_epingles WHERE user_id = ? AND message_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_merge([$moi], $ids)
        ), 'message_id')));
        foreach ($messages as &$m) {
            $m['reactions'] = $reactions[$m['id']] ?? [];
            $m['epingle'] = isset($epingles[$m['id']]);
        }

        return $messages;
    }

    /** Cherche dans la conversation, comme entre deux amis. */
    public static function rechercher(int $moi, int $conversation, string $recherche): array
    {
        $recherche = trim((string) preg_replace('/\s+/u', ' ', $recherche));
        if (mb_strlen($recherche) < 2) {
            return ['total' => 0, 'resultats' => []];
        }
        $motif = '%' . addcslashes($recherche, '\\%_') . '%';
        $condition = 'm.supprime_le IS NULL AND m.evenement IS NULL AND (m.texte LIKE ? OR m.fichier_origine LIKE ?)';
        $tous = self::lignes($moi, $conversation, $condition, [$motif, $motif], 'ORDER BY m.id DESC');
        $pseudos = self::pseudos(array_column($tous, 'expediteur_id'));

        $resultats = array_map(static function (array $l) use ($moi, $recherche, $pseudos): array {
            $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $l['texte']));
            $nomFichier = (string) ($l['fichier_origine'] ?? '');
            $source = Amis::trouver($texte, $recherche) !== null || $nomFichier === '' ? $texte : $nomFichier;
            $moment = Amis::local((string) $l['created_at']);
            $jour = Amis::jour($moment);
            $auteur = $l['expediteur_id'] === null ? null : (int) $l['expediteur_id'];

            return [
                'id' => (int) $l['id'],
                'auteur' => $auteur === $moi ? 'Vous' : ($pseudos[$auteur] ?? 'Un ancien membre'),
                'quand' => ($jour === 'Aujourd’hui' ? '' : $jour . ' · ') . $moment->format('H:i'),
                'piece' => $nomFichier !== '' ? '📎 ' : ($l['image_nom'] !== null ? '📷 ' : ''),
            ] + Amis::decouper($source, $recherche);
        }, array_slice($tous, 0, Amis::RECHERCHE_MAX));

        return ['total' => count($tous), 'resultats' => $resultats];
    }

    /** Les pseudos de plusieurs comptes, par identifiant. */
    private static function pseudos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return array_column(Database::all(
            "SELECT id, COALESCE(pseudo, '') AS pseudo FROM users WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        ), 'pseudo', 'id');
    }

    /** Épingle un message pour soi, ou retire l'épingle. */
    public static function epingler(int $moi, int $messageId, bool $epingler): array
    {
        $message = self::message($moi, $messageId);
        if ($message === null) {
            return [false, 'Ce message est introuvable.', null];
        }
        if ($epingler) {
            Database::run('INSERT IGNORE INTO conversation_epingles (user_id, message_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$moi, $messageId]);
        } else {
            Database::run('DELETE FROM conversation_epingles WHERE user_id = ? AND message_id = ?', [$moi, $messageId]);
        }

        return [true, $epingler ? 'Message épinglé.' : 'Épingle retirée.', (int) $message['conversation_id']];
    }

    /** Mes messages épinglés dans la conversation, de la dernière épingle à la plus ancienne. */
    public static function epingles(int $moi, int $conversation): array
    {
        $depuis = (int) (self::membre($conversation, $moi)['depuis_message'] ?? PHP_INT_MAX);
        $lignes = Database::all(
            'SELECT m.id, m.expediteur_id, m.texte AS r_texte, m.image_nom AS r_image, m.fichier_origine AS r_fichier, m.audio_nom AS r_audio,
                    m.supprime_le AS r_supprime, 0 AS r_masque, m.created_at
               FROM conversation_epingles e JOIN conversation_messages m ON m.id = e.message_id
              WHERE e.user_id = ? AND m.conversation_id = ? AND m.id > ?
              ORDER BY e.created_at DESC, m.id DESC',
            [$moi, $conversation, $depuis]
        );
        $pseudos = self::pseudos(array_column($lignes, 'expediteur_id'));

        return array_map(static function (array $l) use ($moi, $pseudos): array {
            $moment = Amis::local((string) $l['created_at']);
            $jour = Amis::jour($moment);
            $auteur = $l['expediteur_id'] === null ? null : (int) $l['expediteur_id'];

            return [
                'id' => (int) $l['id'],
                'auteur' => $auteur === $moi ? 'Vous' : ($pseudos[$auteur] ?? 'Un ancien membre'),
                'extrait' => Amis::extrait($l),
                'quand' => ($jour === 'Aujourd’hui' ? '' : $jour . ' · ') . $moment->format('H:i'),
            ];
        }, $lignes);
    }

    /** Note que la conversation est sous les yeux de la personne. */
    public static function regarder(int $moi, int $conversation): void
    {
        Database::run(
            'UPDATE conversation_membres SET regarde_le = UTC_TIMESTAMP() WHERE conversation_id = ? AND user_id = ?',
            [$conversation, $moi]
        );
    }

    /** Ce membre n'a pas la conversation sous les yeux à l'instant. */
    private static function absent(int $conversation, int $userId): bool
    {
        $depuis = Database::valeur(
            'SELECT TIMESTAMPDIFF(SECOND, regarde_le, UTC_TIMESTAMP()) FROM conversation_membres WHERE conversation_id = ? AND user_id = ?',
            [$conversation, $userId]
        );

        return $depuis === null || (int) $depuis >= Amis::PRESENCE_SECONDES;
    }

    /**
     * Met en file la notification d'un message pour chaque membre qui n'a pas
     * la conversation sous les yeux.
     *
     * @return list<int> les notifications en file
     */
    public static function notifier(int $expediteur, int $conversation, string $texte, bool $avecImage = false, ?string $nomFichier = null,
                                    ?int $dureeVocal = null): array
    {
        $apercu = trim((string) preg_replace('/\s+/u', ' ', $texte));
        if ($avecImage) {
            $apercu = '📷 Photo' . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        if ($nomFichier !== null) {
            $apercu = '📎 ' . $nomFichier . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        if ($dureeVocal !== null) {
            $apercu = '🎤 Message vocal (' . Amis::duree($dureeVocal) . ')' . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        $pseudo = (string) (Amis::compte($expediteur)['pseudo'] ?? 'Un membre');
        $nom = self::nom($conversation);

        $ids = [];
        foreach (Database::all('SELECT user_id FROM conversation_membres WHERE conversation_id = ? AND user_id <> ?', [$conversation, $expediteur]) as $l) {
            $membre = (int) $l['user_id'];
            if (!self::absent($conversation, $membre)) {
                continue;
            }
            $id = FileNotifications::ajouter($membre, 'message', [
                'title' => '👥 ' . $nom,
                'body' => mb_strimwidth($pseudo . ' : ' . $apercu, 0, Amis::APERCU_NOTIFICATION, '…'),
                'url' => url('groupes/' . $conversation),
                'tag' => 'groupe-' . $conversation,
            ]);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Prévient les amis qu'on vient d'ajouter à une conversation.
     *
     * @return list<int>
     */
    public static function notifierAjout(int $auteur, int $conversation, array $ids): array
    {
        $pseudo = (string) (Amis::compte($auteur)['pseudo'] ?? 'Un ami');
        $nom = self::nom($conversation);
        $notifications = [];
        foreach ($ids as $id) {
            $n = FileNotifications::ajouter((int) $id, 'groupe', [
                'title' => '👥 ' . $nom,
                'body' => $pseudo . ' vous a ajouté au groupe.',
                'url' => url('groupes/' . $conversation),
                'tag' => 'groupe-' . $conversation,
            ]);
            if ($n !== null) {
                $notifications[] = $n;
            }
        }

        return $notifications;
    }

    /** Photos et fichiers échangés dans la conversation, visibles pour soi. */
    public static function partages(int $moi, int $conversation): array
    {
        $date = static fn (string $utc): string => date_fr(Amis::local($utc)->format('Y-m-d H:i:s'), false);
        $images = self::lignes($moi, $conversation, 'm.image_nom IS NOT NULL', [], 'ORDER BY m.id DESC LIMIT 300');
        $fichiers = self::lignes($moi, $conversation, 'm.fichier_nom IS NOT NULL', [], 'ORDER BY m.id DESC LIMIT 300');
        $pseudos = self::pseudos(array_merge(array_column($images, 'expediteur_id'), array_column($fichiers, 'expediteur_id')));
        $qui = static fn (array $l): string => (int) $l['expediteur_id'] === $moi ? 'vous' : ($pseudos[(int) $l['expediteur_id']] ?? 'un ancien membre');

        return [
            'photos' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'],
                'url' => url('groupes/images/' . (int) $l['id']),
                'qui' => $qui($l),
                'date' => $date((string) $l['created_at']),
            ], $images),
            'fichiers' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'],
                'url' => url('groupes/fichiers/' . (int) $l['id']),
                'telecharger' => url('groupes/fichiers/' . (int) $l['id'], ['telecharger' => 1]),
                'nom' => (string) $l['fichier_origine'],
                'taille' => taille_lisible((int) $l['fichier_taille']),
                'icone' => Fichiers::icone((string) $l['fichier_mime'], (string) $l['fichier_origine']),
                'qui' => $qui($l),
                'date' => $date((string) $l['created_at']),
            ], $fichiers),
        ];
    }

    /** Le dernier de mes messages que tous les autres membres ont lu, pour « Vu par tous ». */
    public static function vuJusqua(int $moi, int $conversation): int
    {
        $plusPetit = Database::valeur(
            'SELECT MIN(lu_jusqua) FROM conversation_membres WHERE conversation_id = ? AND user_id <> ?',
            [$conversation, $moi]
        );
        if ($plusPetit === null) {
            return 0;
        }

        return (int) Database::valeur(
            'SELECT COALESCE(MAX(id), 0) FROM conversation_messages
              WHERE conversation_id = ? AND expediteur_id = ? AND evenement IS NULL AND id <= ?',
            [$conversation, $moi, (int) $plusPetit]
        );
    }

    /** Un message prêt à montrer : comme entre amis, avec son auteur. */
    public static function pourAffichage(array $message, int $moi): array
    {
        $evenement = $message['evenement'] ?? null;
        $sansNote = $message;
        unset($sansNote['evenement']);
        $affiche = Amis::pourAffichage($sansNote, $moi, 'groupes');
        $auteur = $message['expediteur_id'] === null ? null : (int) $message['expediteur_id'];
        $affiche['auteur_id'] = $auteur;
        $affiche['auteur'] = $auteur === null ? 'Un ancien membre' : (string) (Amis::compte($auteur)['pseudo'] ?? 'Un ancien membre');
        $affiche['evenement'] = $evenement === null ? null : self::texteEvenement($message, $moi);
        if ($affiche['reponse'] !== null && $affiche['reponse']['auteur'] === '') {
            $affiche['reponse']['auteur'] = 'Un ancien membre';
        }

        return $affiche;
    }

    /** Le texte d'aperçu d'une dernière ligne, pour la liste des discussions. */
    public static function apercu(?array $dernier, int $moi): string
    {
        if ($dernier === null) {
            return 'Dites bonjour 👋';
        }
        if ($dernier['evenement'] !== null) {
            return self::texteEvenement($dernier, $moi);
        }
        if ($dernier['supprime_le'] !== null) {
            return '🚫 Message supprimé';
        }
        $auteur = $dernier['expediteur_id'] === null ? null : (int) $dernier['expediteur_id'];
        $qui = $auteur === $moi ? 'Vous' : (string) (Amis::compte((int) $auteur)['pseudo'] ?? 'Un ancien membre');
        $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $dernier['texte']));
        $piece = $dernier['image_nom'] !== null ? '📷 Photo'
            : ($dernier['fichier_origine'] !== null ? '📎 ' . $dernier['fichier_origine']
            : ($dernier['audio_nom'] !== null ? '🎤 Message vocal' : ''));

        return $qui . ' : ' . ($texte === '' ? $piece : ($piece === '' ? $texte : $piece . ' · ' . $texte));
    }
}
