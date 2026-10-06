<?php
declare(strict_types=1);

/**
 * Les serveurs, comme sur Discord : un espace nommé avec des membres et des salons.
 *
 * Un salon est une discussion de groupe (table `conversations`, colonne `serveur_id`) : il en a tous les pouvoirs — images, fichiers,
 * vocaux, réactions, sondages. Tous les membres du serveur sont membres de tous ses salons ; ce fichier les y met ou les en retire
 * quand quelqu'un entre, sort, ou qu'un salon naît. Les réglages propres aux groupes (membres, nom, départ) ne valent pas pour un
 * salon : c'est le serveur qui les gouverne (voir ConversationsController).
 *
 * Qui peut quoi :
 *   - propriétaire : tout, y compris nommer ou retirer les administrateurs et supprimer le serveur ; il ne peut pas partir (il
 *     supprime le serveur, ou y reste) ;
 *   - administrateur : renommer le serveur, créer, renommer et supprimer des salons, inviter, retirer des membres (pas un autre
 *     administrateur) ;
 *   - membre : lire, écrire, et partir.
 * Personne n'entre sans l'avoir voulu : on invite un ami, qui accepte ou refuse. Un nouveau membre voit tout l'historique des salons.
 */
final class Serveurs
{
    public const NOM_MAX = 60;
    public const SALON_MAX = 40;
    public const SALONS_MAX = 20;
    public const MEMBRES_MAX = 100;
    /** Serveurs au plus par personne (qu'elle y soit propriétaire ou simple membre). */
    public const SERVEURS_MAX = 20;
    public const SALON_PAR_DEFAUT = 'général';
    /** Les couleurs proposées pour le fond des initiales (on peut aussi en choisir une autre). */
    public const COULEURS = ['#5865f2', '#3ba55d', '#ed4245', '#f59e0b', '#eb459e', '#9b59b6', '#14b8a6', '#e67e22', '#3498db', '#607d8b', '#2c3e50', '#f1c40f'];

    // --- Lire ----------------------------------------------------------------------------------------------------------

    /** Le rôle d'un compte dans un serveur, ou null s'il n'en est pas membre. */
    public static function role(int $serveur, int $userId): ?string
    {
        $role = Database::valeur('SELECT role FROM serveur_membres WHERE serveur_id = ? AND user_id = ?', [$serveur, $userId]);

        return is_string($role) ? $role : null;
    }

    /** Ce rôle peut-il gérer le serveur (salons, invitations, retraits) ? */
    public static function gere(?string $role): bool
    {
        return $role === 'proprietaire' || $role === 'admin';
    }

    /** Le serveur, avec mon rôle, si j'en suis membre. */
    public static function serveur(int $serveur, int $moi): ?array
    {
        return Database::one(
            'SELECT s.*, m.role FROM serveurs s JOIN serveur_membres m ON m.serveur_id = s.id AND m.user_id = ? WHERE s.id = ?',
            [$moi, $serveur]
        );
    }

    /**
     * Mes serveurs, avec le nombre de membres, ce qui n'est pas lu, et le premier salon (où l'on arrive).
     *
     * @return list<array{id: int, nom: string, icone: string, photo: ?string, role: string, membres: int, non_lus: int, premier_salon: ?int}>
     */
    public static function liste(int $moi): array
    {
        $serveurs = [];
        foreach (Database::all(
            'SELECT s.id, s.nom, s.icone, s.photo_nom, s.couleur, m.role,
                    (SELECT COUNT(*) FROM serveur_membres x WHERE x.serveur_id = s.id) AS membres,
                    (SELECT c.id FROM conversations c WHERE c.serveur_id = s.id ORDER BY c.position, c.id LIMIT 1) AS premier_salon
               FROM serveur_membres m JOIN serveurs s ON s.id = m.serveur_id
              WHERE m.user_id = ? ORDER BY s.nom, s.id',
            [$moi]
        ) as $l) {
            $serveurs[] = ['id' => (int) $l['id'], 'nom' => (string) $l['nom'], 'icone' => (string) $l['icone'], 'photo' => self::adressePhoto((int) $l['id'], $l['photo_nom']), 'couleur' => $l['couleur'], 'role' => (string) $l['role'],
                           'membres' => (int) $l['membres'], 'non_lus' => array_sum(array_column(self::salons((int) $l['id'], $moi), 'non_lus')),
                           'premier_salon' => $l['premier_salon'] === null ? null : (int) $l['premier_salon']];
        }

        return $serveurs;
    }

    /**
     * Les salons du serveur, dans leur ordre, avec ce qu'il y a de non lu pour moi.
     *
     * @return list<array{id: int, nom: string, non_lus: int}>
     */
    public static function salons(int $serveur, int $moi): array
    {
        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'nom' => (string) $l['nom'], 'non_lus' => (int) $l['non_lus']], Database::all(
            "SELECT c.id, c.nom,
                    (SELECT COUNT(*) FROM conversation_messages m
                      WHERE m.conversation_id = c.id AND m.id > GREATEST(mb.lu_jusqua, mb.depuis_message)
                        AND (m.expediteur_id IS NULL OR m.expediteur_id <> ?)
                        AND m.evenement IS NULL AND m.supprime_le IS NULL
                        AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)) AS non_lus
               FROM conversations c LEFT JOIN conversation_membres mb ON mb.conversation_id = c.id AND mb.user_id = ?
              WHERE c.serveur_id = ? ORDER BY c.position, c.id",
            [$moi, $moi, $moi, $serveur]
        ));
    }

    /**
     * Les membres : le propriétaire, les administrateurs, puis les autres par pseudo.
     *
     * @return list<array{id: int, pseudo: string, role: string}>
     */
    public static function membres(int $serveur): array
    {
        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'pseudo' => (string) $l['pseudo'], 'role' => (string) $l['role']], Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo, m.role
               FROM serveur_membres m JOIN users u ON u.id = m.user_id
              WHERE m.serveur_id = ?
              ORDER BY FIELD(m.role, 'proprietaire', 'admin', 'membre'), u.pseudo",
            [$serveur]
        ));
    }

    /** @return list<array{id: int, pseudo: string}> les invités qui n'ont pas encore répondu */
    public static function invites(int $serveur): array
    {
        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'pseudo' => (string) $l['pseudo']], Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo FROM serveur_invitations i JOIN users u ON u.id = i.user_id
              WHERE i.serveur_id = ? ORDER BY i.created_at, u.pseudo",
            [$serveur]
        ));
    }

    /** Les invitations que j'ai reçues. @return list<array{id: int, nom: string, icone: string, par: string, membres: int}> */
    public static function invitations(int $moi): array
    {
        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'nom' => (string) $l['nom'], 'icone' => (string) $l['icone'], 'photo' => self::adressePhoto((int) $l['id'], $l['photo_nom']), 'couleur' => $l['couleur'],
            'par' => (string) $l['par'], 'membres' => (int) $l['membres']], Database::all(
            "SELECT s.id, s.nom, s.icone, s.photo_nom, s.couleur, COALESCE(u.pseudo, '') AS par,
                    (SELECT COUNT(*) FROM serveur_membres x WHERE x.serveur_id = s.id) AS membres
               FROM serveur_invitations i JOIN serveurs s ON s.id = i.serveur_id LEFT JOIN users u ON u.id = i.invite_par
              WHERE i.user_id = ? ORDER BY i.created_at DESC",
            [$moi]
        ));
    }

    public static function nombreInvitations(int $moi): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM serveur_invitations WHERE user_id = ?', [$moi]);
    }

    /** Mes amis qu'on peut inviter : ni membres ni déjà invités. @return list<array> */
    public static function aInviter(int $serveur, int $moi): array
    {
        $dedans = array_map('intval', array_column(Database::all('SELECT user_id FROM serveur_membres WHERE serveur_id = ?', [$serveur]), 'user_id'));
        $invites = array_map('intval', array_column(Database::all('SELECT user_id FROM serveur_invitations WHERE serveur_id = ?', [$serveur]), 'user_id'));

        return array_values(array_filter(Amis::liste($moi), static fn (array $a): bool => !in_array((int) $a['id'], $dedans, true) && !in_array((int) $a['id'], $invites, true)));
    }

    // --- Le logo --------------------------------------------------------------------------------------------------------

    /** L'adresse du logo, qui change avec l'image (le navigateur garde l'ancienne sinon). */
    public static function adressePhoto(int $serveur, ?string $nomPhoto): ?string
    {
        return $nomPhoto === null ? null : url('serveurs/' . $serveur . '/photo', ['v' => substr($nomPhoto, 0, 12)]);
    }

    /**
     * Les initiales d'un serveur, quand il n'a pas de logo : un seul mot, ses deux premières lettres (« Maths » → « Ma ») ;
     * plusieurs mots, la première lettre de chacun (« Licence 2 — groupe A » → « L2GA »). Ce qui n'est ni lettre ni chiffre ne compte pas.
     */
    public static function initiales(string $nom): string
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', $nom, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($mots === []) {
            return '?';
        }
        if (count($mots) === 1) {
            return mb_strtoupper(mb_substr($mots[0], 0, 1)) . mb_strtolower(mb_substr($mots[0], 1, 1));
        }

        return mb_strtoupper(implode('', array_map(static fn (string $m): string => mb_substr($m, 0, 1), $mots)));
    }

    /** Une couleur « #rrggbb » bien formée (en minuscules), ou null. */
    public static function couleurValide(mixed $couleur): ?string
    {
        return is_string($couleur) && preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : null;
    }

    /** La couleur du texte qui se lit sur ce fond : blanc, ou presque noir sur un fond clair (la couleur déduite du nom est toujours sombre). */
    public static function texteSur(?string $fond): string
    {
        if ($fond === null) {
            return '#fff';
        }
        $luminance = 0.299 * hexdec(substr($fond, 1, 2)) + 0.587 * hexdec(substr($fond, 3, 2)) + 0.114 * hexdec(substr($fond, 5, 2));

        return $luminance > 170 ? '#1f2937' : '#fff';
    }

    /** Une couleur propre au serveur (la même à chaque affichage) pour le fond de ses initiales, tant qu'on n'en a pas choisi. */
    public static function couleur(string $nom): string
    {
        return 'hsl(' . (crc32(mb_strtolower($nom)) % 360) . ' 52% 40%)';
    }

    /**
     * Le logo d'un serveur, dans son cadre : la photo, ou les initiales sur fond coloré. « $classes » dit la taille et la forme du cadre.
     */
    public static function pastille(string $nom, ?string $photo, string $classes = '', ?string $couleur = null): string
    {
        $initiales = self::initiales($nom);
        if ($photo !== null) {
            return '<span class="serveur-logo ' . e($classes) . '" aria-hidden="true"><img src="' . e($photo) . '" alt=""></span>';
        }

        return '<span class="serveur-logo serveur-logo--initiales serveur-logo--n' . min(4, mb_strlen($initiales)) . ' ' . e($classes)
            . '" style="--couleur: ' . e(self::couleurValide($couleur) ?? self::couleur($nom)) . '; --texte-logo: ' . self::texteSur(self::couleurValide($couleur)) . '" aria-hidden="true">' . e($initiales) . '</span>';
    }
    /** Le logo, pour les membres et ceux qui sont invités. */
    public static function photo(int $serveur, int $moi): ?array
    {
        return Database::one(
            'SELECT s.photo_nom, s.photo_mime FROM serveurs s
              WHERE s.id = ? AND s.photo_nom IS NOT NULL
                AND (EXISTS (SELECT 1 FROM serveur_membres m WHERE m.serveur_id = s.id AND m.user_id = ?)
                  OR EXISTS (SELECT 1 FROM serveur_invitations i WHERE i.serveur_id = s.id AND i.user_id = ?))',
            [$serveur, $moi, $moi]
        );
    }

    /** Pose un logo (administrateurs). */
    public static function changerPhoto(int $moi, int $serveur, ?array $image): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return t('msg.choisir_image');
        }
        $rangee = Amis::rangerImage($image);
        if (is_string($rangee)) {
            return $rangee;
        }
        $avant = Database::valeur('SELECT photo_nom FROM serveurs WHERE id = ?', [$serveur]);
        Database::run('UPDATE serveurs SET photo_nom = ?, photo_mime = ? WHERE id = ?', [$rangee['nom'], $rangee['mime'], $serveur]);
        self::effacerFichier(is_string($avant) ? $avant : null);

        return null;
    }

    /** Retire le logo : l'icône reprend sa place. Vrai s'il y en avait un ; un texte si c'est refusé. */
    public static function retirerPhoto(int $moi, int $serveur): bool|string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        $avant = Database::valeur('SELECT photo_nom FROM serveurs WHERE id = ?', [$serveur]);
        if (!is_string($avant)) {
            return false;
        }
        Database::run('UPDATE serveurs SET photo_nom = NULL, photo_mime = NULL WHERE id = ? AND photo_nom = ?', [$serveur, $avant]);
        self::effacerFichier($avant);

        return true;
    }

    private static function effacerFichier(?string $nom): void
    {
        if ($nom !== null && preg_match('/^[0-9a-f]{32}\.[a-z0-9]{1,8}$/', $nom)) {
            @unlink(Amis::dossierImages() . DIRECTORY_SEPARATOR . $nom);
        }
    }

    /** Le serveur d'un salon (ou null si la conversation n'est pas un salon). */
    public static function dUnSalon(int $conversation): ?int
    {
        $s = Database::valeur('SELECT serveur_id FROM conversations WHERE id = ?', [$conversation]);

        return $s === null || $s === false ? null : (int) $s;
    }

    // --- Nettoyer ------------------------------------------------------------------------------------------------------

    public static function nettoyerNom(string $nom): string
    {
        return trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $nom));
    }

    /** Un nom de salon : en minuscules, sans espace ni « # » — « Cours de maths » devient « cours-de-maths ». */
    public static function nomDeSalon(string $nom): string
    {
        $nom = mb_strtolower(self::nettoyerNom($nom));
        $nom = (string) preg_replace('/[^\p{L}\p{N}_-]+/u', '-', ltrim($nom, '#'));
        $nom = trim((string) preg_replace('/-{2,}/', '-', $nom), '-');

        return mb_substr($nom, 0, self::SALON_MAX);
    }

    private static function problemeNom(string $nom): ?string
    {
        if ($nom === '') {
            return t('srv.err.nom_vide');
        }

        return mb_strlen($nom) > self::NOM_MAX ? t('srv.err.nom_long', ['max' => self::NOM_MAX]) : null;
    }

    // --- Créer, régler -------------------------------------------------------------------------------------------------

    /** @return array{0: ?int, 1: ?string} le serveur, ou la raison du refus ; « $image » : le logo, facultatif */
    public static function creer(int $moi, string $nom, ?array $image = null, ?string $couleur = null): array
    {
        if ((string) (Amis::compte($moi)['pseudo'] ?? '') === '') {
            return [null, t('grp.pseudo_avant')];
        }
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return [null, $probleme];
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM serveur_membres WHERE user_id = ?', [$moi]) >= self::SERVEURS_MAX) {
            return [null, t('srv.err.trop_de_serveurs', ['max' => self::SERVEURS_MAX])];
        }

        // Le logo est facultatif : sans fichier choisi, on n'en parle pas ; un fichier refusé arrête la création.
        $rangee = null;
        if ($image !== null && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $rangee = Amis::rangerImage($image);
            if (is_string($rangee)) {
                return [null, $rangee];
            }
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('INSERT INTO serveurs (nom, photo_nom, photo_mime, couleur, cree_par) VALUES (?, ?, ?, ?, ?)', [$nom, $rangee['nom'] ?? null, $rangee['mime'] ?? null, self::couleurValide($couleur), $moi]);
            $id = Database::dernierId();
            Database::run("INSERT INTO serveur_membres (serveur_id, user_id, role) VALUES (?, ?, 'proprietaire')", [$id, $moi]);
            self::insererSalon($id, self::SALON_PAR_DEFAUT, 0, $moi);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            self::effacerFichier($rangee['nom'] ?? null);
            throw $e;
        }

        return [$id, null];
    }

    /** Renomme le serveur (administrateurs). */
    public static function modifier(int $moi, int $serveur, string $nom): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return $probleme;
        }
        Database::run('UPDATE serveurs SET nom = ? WHERE id = ?', [$nom, $serveur]);

        return null;
    }

    /** Change la couleur du fond des initiales (administrateurs) ; vide : la couleur redevient celle du nom. */
    public static function changerCouleur(int $moi, int $serveur, ?string $couleur): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        $couleur = $couleur === null ? '' : trim($couleur);
        if ($couleur !== '' && self::couleurValide($couleur) === null) {
            return t('srv.err.couleur');
        }
        Database::run('UPDATE serveurs SET couleur = ? WHERE id = ?', [$couleur === '' ? null : self::couleurValide($couleur), $serveur]);

        return null;
    }

    /** Supprime le serveur, ses salons (et leurs fichiers), ses membres. Le propriétaire seul — ou un administrateur s'il n'y a plus de propriétaire. */
    public static function supprimer(int $moi, int $serveur): ?string
    {
        $role = self::role($serveur, $moi);
        $sansProprietaire = (int) Database::valeur("SELECT COUNT(*) FROM serveur_membres WHERE serveur_id = ? AND role = 'proprietaire'", [$serveur]) === 0;
        if ($role !== 'proprietaire' && !($role === 'admin' && $sansProprietaire)) {
            return t('srv.err.proprietaire');
        }
        self::effacer($serveur);

        return null;
    }

    private static function effacer(int $serveur): void
    {
        foreach (Database::all('SELECT id FROM conversations WHERE serveur_id = ?', [$serveur]) as $salon) {
            Conversations::effacer((int) $salon['id']);
        }
        $photo = Database::valeur('SELECT photo_nom FROM serveurs WHERE id = ?', [$serveur]);
        Database::run('DELETE FROM serveurs WHERE id = ?', [$serveur]);
        self::effacerFichier(is_string($photo) ? $photo : null);
    }

    // --- Les salons ----------------------------------------------------------------------------------------------------

    /** Crée un salon et y met tous les membres du serveur. */
    private static function insererSalon(int $serveur, string $nom, int $position, int $auteur): int
    {
        Database::run(
            'INSERT INTO conversations (nom, cree_par, serveur_id, position, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$nom, $auteur, $serveur, $position]
        );
        $salon = Database::dernierId();
        Database::run(
            "INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le)
             SELECT ?, user_id, 'membre', UTC_TIMESTAMP() FROM serveur_membres WHERE serveur_id = ?",
            [$salon, $serveur]
        );

        return $salon;
    }

    /** @return array{0: ?int, 1: ?string} le salon, ou la raison du refus */
    public static function ajouterSalon(int $moi, int $serveur, string $nom): array
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return [null, t('srv.err.admins')];
        }
        $nom = self::nomDeSalon($nom);
        if ($nom === '') {
            return [null, t('srv.err.salon_vide')];
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM conversations WHERE serveur_id = ?', [$serveur]) >= self::SALONS_MAX) {
            return [null, t('srv.err.trop_de_salons', ['max' => self::SALONS_MAX])];
        }
        if (Database::valeur('SELECT id FROM conversations WHERE serveur_id = ? AND nom = ?', [$serveur, $nom]) !== null) {
            return [null, t('srv.err.salon_existe', ['nom' => $nom])];
        }
        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM conversations WHERE serveur_id = ?', [$serveur]);

        return [self::insererSalon($serveur, $nom, $position, $moi), null];
    }

    public static function renommerSalon(int $moi, int $serveur, int $salon, string $nom): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        if (self::dUnSalon($salon) !== $serveur) {
            return t('srv.err.salon_introuvable');
        }
        $nom = self::nomDeSalon($nom);
        if ($nom === '') {
            return t('srv.err.salon_vide');
        }
        if (Database::valeur('SELECT id FROM conversations WHERE serveur_id = ? AND nom = ? AND id <> ?', [$serveur, $nom, $salon]) !== null) {
            return t('srv.err.salon_existe', ['nom' => $nom]);
        }
        Database::run('UPDATE conversations SET nom = ? WHERE id = ?', [$nom, $salon]);

        return null;
    }

    /** Supprime un salon, ses messages et ses fichiers. Il en reste toujours au moins un. */
    public static function supprimerSalon(int $moi, int $serveur, int $salon): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        if (self::dUnSalon($salon) !== $serveur) {
            return t('srv.err.salon_introuvable');
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM conversations WHERE serveur_id = ?', [$serveur]) <= 1) {
            return t('srv.err.dernier_salon');
        }
        Conversations::effacer($salon);

        return null;
    }

    // --- Les membres ---------------------------------------------------------------------------------------------------

    /** @return array{0: ?int, 1: ?string} la notification en file, ou la raison du refus */
    public static function inviter(int $moi, int $serveur, int $cible): array
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return [null, t('srv.err.admins')];
        }
        if ($cible === $moi || !Amis::sontAmis($moi, $cible)) {
            return [null, t('srv.err.amis_seuls')];
        }
        if (self::role($serveur, $cible) !== null) {
            return [null, t('srv.err.deja_membre')];
        }
        if (Database::valeur('SELECT 1 FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$serveur, $cible]) !== null) {
            return [null, t('srv.err.deja_invite')];
        }
        $pris = (int) Database::valeur('SELECT COUNT(*) FROM serveur_membres WHERE serveur_id = ?', [$serveur])
            + (int) Database::valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$serveur]);
        if ($pris >= self::MEMBRES_MAX) {
            return [null, t('srv.err.complet', ['max' => self::MEMBRES_MAX])];
        }
        Database::run('INSERT INTO serveur_invitations (serveur_id, user_id, invite_par) VALUES (?, ?, ?)', [$serveur, $cible, $moi]);

        $nom = (string) Database::valeur('SELECT nom FROM serveurs WHERE id = ?', [$serveur]);
        $pseudoBrut = Amis::compte($moi)['pseudo'] ?? null;
        $notification = FileNotifications::ajouter($cible, 'groupe', fn (): array => [
            'title' => t('srv.notif_titre'),
            'body' => t('srv.notif_corps', ['qui' => (string) ($pseudoBrut ?? t('grp.quelquun')), 'nom' => $nom]),
            'url' => url('serveurs'),
            'tag' => 'invitation-serveur-' . $serveur,
        ]);

        return [$notification, null];
    }

    /** Retire une invitation qui n'a pas reçu de réponse (administrateurs). */
    public static function annulerInvitation(int $moi, int $serveur, int $cible): ?string
    {
        if (!self::gere(self::role($serveur, $moi))) {
            return t('srv.err.admins');
        }
        Database::run('DELETE FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$serveur, $cible]);

        return null;
    }

    /**
     * Répond à une invitation. Accepter fait entrer dans tous les salons, avec tout leur historique.
     *
     * @return ?string la raison du refus
     */
    public static function repondre(int $moi, int $serveur, bool $accepter): ?string
    {
        $invitation = Database::one('SELECT invite_par FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$serveur, $moi]);
        if ($invitation === null) {
            return t('srv.err.invitation_partie');
        }
        if (!$accepter) {
            Database::run('DELETE FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$serveur, $moi]);

            return null;
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM serveur_membres WHERE user_id = ?', [$moi]) >= self::SERVEURS_MAX) {
            return t('srv.err.trop_de_serveurs', ['max' => self::SERVEURS_MAX]);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$serveur, $moi]);
            Database::run("INSERT IGNORE INTO serveur_membres (serveur_id, user_id, role) VALUES (?, ?, 'membre')", [$serveur, $moi]);
            foreach (Database::all('SELECT id FROM conversations WHERE serveur_id = ? ORDER BY position, id', [$serveur]) as $salon) {
                Database::run(
                    "INSERT IGNORE INTO conversation_membres (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, 'membre', UTC_TIMESTAMP())",
                    [(int) $salon['id'], $moi]
                );
            }
            // Une note dans le premier salon : qui vient d'arriver.
            $premier = (int) Database::valeur('SELECT id FROM conversations WHERE serveur_id = ? ORDER BY position, id LIMIT 1', [$serveur]);
            if ($premier > 0) {
                Conversations::noter($premier, $moi, 'srv_rejoint');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return null;
    }

    /** Quitter le serveur. Le propriétaire ne part pas : il supprime le serveur (sauf s'il est seul, ce qui revient au même). */
    public static function quitter(int $moi, int $serveur): ?string
    {
        $role = self::role($serveur, $moi);
        if ($role === null) {
            return t('srv.err.pas_membre');
        }
        if ($role === 'proprietaire') {
            if ((int) Database::valeur('SELECT COUNT(*) FROM serveur_membres WHERE serveur_id = ?', [$serveur]) > 1) {
                return t('srv.err.proprietaire_part');
            }
            self::effacer($serveur);

            return null;
        }
        self::sortir($serveur, $moi, 'srv_depart', null);

        return null;
    }

    /** Retire un membre : le propriétaire, n'importe lequel ; un administrateur, un simple membre. */
    public static function retirer(int $moi, int $serveur, int $cible): ?string
    {
        $monRole = self::role($serveur, $moi);
        $sonRole = self::role($serveur, $cible);
        if (!self::gere($monRole)) {
            return t('srv.err.admins');
        }
        if ($sonRole === null || $cible === $moi) {
            return t('srv.err.pas_membre');
        }
        if ($sonRole === 'proprietaire' || ($sonRole === 'admin' && $monRole !== 'proprietaire')) {
            return t('srv.err.retrait_interdit');
        }
        self::sortir($serveur, $cible, 'srv_retrait', $moi);

        return null;
    }

    /** Sort un compte du serveur et de tous ses salons, et laisse une note dans le premier. */
    private static function sortir(int $serveur, int $userId, string $evenement, ?int $auteur): void
    {
        $premier = (int) Database::valeur('SELECT id FROM conversations WHERE serveur_id = ? ORDER BY position, id LIMIT 1', [$serveur]);
        foreach (Database::all('SELECT id FROM conversations WHERE serveur_id = ?', [$serveur]) as $salon) {
            Conversations::oublier((int) $salon['id'], $userId);
        }
        Database::run('DELETE FROM serveur_membres WHERE serveur_id = ? AND user_id = ?', [$serveur, $userId]);
        if ($premier > 0) {
            // Le départ : l'auteur est celui qui part ; le retrait : celui qui retire, la cible est le retiré.
            Conversations::noter($premier, $auteur ?? $userId, $evenement, $auteur === null ? null : $userId);
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM serveur_membres WHERE serveur_id = ?', [$serveur]) === 0) {
            self::effacer($serveur);
        }
    }

    /** Nomme un administrateur (le propriétaire seul). */
    public static function nommerAdmin(int $moi, int $serveur, int $cible): ?string
    {
        if (self::role($serveur, $moi) !== 'proprietaire') {
            return t('srv.err.proprietaire');
        }
        if (self::role($serveur, $cible) !== 'membre') {
            return t('srv.err.pas_membre');
        }
        Database::run("UPDATE serveur_membres SET role = 'admin' WHERE serveur_id = ? AND user_id = ?", [$serveur, $cible]);

        return null;
    }

    /** Redevient simple membre (le propriétaire seul). */
    public static function retirerAdmin(int $moi, int $serveur, int $cible): ?string
    {
        if (self::role($serveur, $moi) !== 'proprietaire') {
            return t('srv.err.proprietaire');
        }
        if (self::role($serveur, $cible) !== 'admin') {
            return t('srv.err.pas_membre');
        }
        Database::run("UPDATE serveur_membres SET role = 'membre' WHERE serveur_id = ? AND user_id = ?", [$serveur, $cible]);

        return null;
    }
}
