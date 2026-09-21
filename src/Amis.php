<?php
declare(strict_types=1);

/**
 * Les amis : se trouver par pseudo, se demander, s'accepter, et s'écrire.
 *
 * Tout passe par la même règle : on ne voit d'un autre compte que son pseudo,
 * et on n'échange de messages qu'avec un ami accepté. Une demande en attente
 * ne donne accès à rien.
 */
final class Amis
{
    /** Longueur maximale d'un message. */
    public const MESSAGE_MAX = 2000;

    /** Messages permis par minute, pour qu'un compte ne puisse pas en inonder un autre. */
    public const MESSAGES_PAR_MINUTE = 30;

    /** Demandes en attente qu'un compte peut avoir lancées à la fois. */
    public const DEMANDES_MAX = 50;

    /** Messages montrés à l'ouverture d'une conversation. */
    public const FIL_MAX = 200;

    /** Au plus, quand la conversation s'ouvre sur un message plus ancien. */
    public const FIL_DEPUIS_MAX = 2000;

    /**
     * Une discussion regardée — onglet affiché et fenêtre active — il y a
     * moins de tant de secondes est encore sous les yeux. La page le redit
     * toutes les quatre secondes tant que c'est le cas.
     */
    public const PRESENCE_SECONDES = 10;

    /** Longueur du texte repris dans une notification. */
    public const APERCU_NOTIFICATION = 140;

    /** Poids maximal d'une image envoyée. */
    public const IMAGE_MAX_OCTETS = 10 * 1024 * 1024;

    /** Au-delà, une image est réduite : assez pour la regarder en grand, pas pour remplir le disque. */
    public const IMAGE_COTE_MAX = 1600;

    /** Pixels qu'on accepte de décoder : une image plus grande épuiserait la mémoire du serveur. */
    public const IMAGE_PIXELS_MAX = 50_000_000;

    /** Les formats acceptés, et le type sous lequel chacun est servi. */
    public const IMAGE_TYPES = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG  => ['image/png', 'png'],
        IMAGETYPE_GIF  => ['image/gif', 'gif'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];

    /** Poids maximal d'un fichier joint (et jamais plus que ce que le serveur accepte). */
    public const FICHIER_MAX_OCTETS = 50 * 1024 * 1024;

    public static function fichierMax(): int
    {
        return min(self::FICHIER_MAX_OCTETS, Fichiers::tailleMax());
    }

    /** Les extensions acceptées : les mêmes que pour les pièces jointes des cours. */
    public static function extensionsFichiers(): array
    {
        return array_values(array_map('strtolower', (array) Config::get('app', 'extensions_autorisees')));
    }

    /** Un message vocal : poids et durée au plus. */
    public const VOCAL_MAX_OCTETS = 10 * 1024 * 1024;
    public const VOCAL_MAX_SECONDES = 300;
    public const TRANSCRIPTION_MAX = 10000;

    /**
     * Vérifie un enregistrement vocal et le range. Ce doit être un vrai fichier
     * audio — reconnu à ses premiers octets, pas à ce qu'il annonce : WebM
     * (Chrome, Firefox, Edge), Ogg, ou MP4 (Safari).
     *
     * @return array{nom: string, duree: int}|string l'enregistrement rangé, ou la raison du refus
     */
    public static function rangerVocal(array $fichier, int $duree): array|string
    {
        $code = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            return 'Le message vocal n’a pas pu être envoyé : ' . Fichiers::messageErreur($code);
        }
        $tmp = (string) ($fichier['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            return 'Le message vocal n’a pas pu être envoyé.';
        }
        $taille = (int) filesize($tmp);
        if ($taille < 100) {
            return 'Le message vocal est vide.';
        }
        if ($taille > self::VOCAL_MAX_OCTETS) {
            return 'Ce message vocal est trop long.';
        }
        if ($duree < 1 || $duree > self::VOCAL_MAX_SECONDES) {
            return 'Un message vocal dure de 1 seconde à ' . intdiv(self::VOCAL_MAX_SECONDES, 60) . ' minutes.';
        }

        $debut = (string) file_get_contents($tmp, false, null, 0, 16);
        $extension = match (true) {
            str_starts_with($debut, "\x1A\x45\xDF\xA3") => 'weba',
            str_starts_with($debut, 'OggS') => 'ogg',
            substr($debut, 4, 4) === 'ftyp' => 'm4a',
            default => null,
        };
        if ($extension === null) {
            return 'Ce fichier n’est pas un enregistrement audio.';
        }

        $dossier = self::dossierImages();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return 'Impossible de ranger le message vocal sur le serveur.';
        }
        $nom = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, $dossier . DIRECTORY_SEPARATOR . $nom)) {
            return 'Impossible de ranger le message vocal sur le serveur.';
        }

        return ['nom' => $nom, 'duree' => $duree];
    }

    /** La poubelle dessinée, du même trait que le micro. */
    public static function poubelle(): string
    {
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
    }

    /** Le micro dessiné des messages vocaux, à la taille du texte. */
    public static function micro(): string
    {
        return '<svg class="micro" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><rect x="9" y="3" width="6" height="12" rx="3" fill="currentColor"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0"/><path d="M12 17.5V21"/></svg>';
    }

    /**
     * Un extrait prêt à afficher, échappé : « 🎤 Message vocal » y prend
     * le micro dessiné (les notifications, elles, gardent l'emoji).
     */
    public static function extraitHtml(string $extrait): string
    {
        return str_starts_with($extrait, '🎤 ')
            ? self::micro() . ' ' . e(substr($extrait, strlen('🎤 ')))
            : e($extrait);
    }

    /**
     * La transcription envoyée avec un vocal, sur une ligne, sans caractères
     * de contrôle, raccourcie au besoin ; null s'il n'y a rien à garder.
     */
    public static function transcription(?string $texte): ?string
    {
        if ($texte === null || !mb_check_encoding($texte, 'UTF-8')) {
            return null;
        }
        $texte = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $texte));
        if ($texte === '') {
            return null;
        }

        return mb_strlen($texte) > self::TRANSCRIPTION_MAX ? rtrim(mb_substr($texte, 0, self::TRANSCRIPTION_MAX - 1)) . '…' : $texte;
    }

    /** « 0:42 », « 3:05 ». */
    public static function duree(int $secondes): string
    {
        return intdiv($secondes, 60) . ':' . str_pad((string) ($secondes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Le message et son enregistrement vocal, si la personne connectée a le droit de l'écouter. */
    public static function vocal(int $moi, int $messageId): ?array
    {
        $message = Database::one(
            'SELECT id, expediteur_id, destinataire_id, audio_nom FROM messages
              WHERE id = ? AND audio_nom IS NOT NULL
                AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        if ($message === null) {
            return null;
        }
        $autre = (int) $message['expediteur_id'] === $moi ? (int) $message['destinataire_id'] : (int) $message['expediteur_id'];

        return self::sontAmis($moi, $autre) ? $message : null;
    }

    /** Le dossier des images des discussions, à côté des pièces jointes des cours. */
    public static function dossierImages(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'messages';
    }

    /** Les photos de profil déjà lues pendant cette requête, par compte. */
    private static array $photos = [];

    /** Le nom de fichier de la photo de profil d'un compte, ou null. */
    public static function photoDe(int $id): ?string
    {
        if (!array_key_exists($id, self::$photos)) {
            $nom = Database::valeur('SELECT photo_nom FROM users WHERE id = ?', [$id]);
            self::$photos[$id] = is_string($nom) ? $nom : null;
        }

        return self::$photos[$id];
    }

    /** L'adresse de la photo de profil, qui change avec l'image. */
    public static function adressePhoto(int $id): ?string
    {
        $nom = self::photoDe($id);

        return $nom === null ? null : url('comptes/' . $id . '/photo', ['v' => substr($nom, 0, 12)]);
    }

    /** L'avatar d'un compte : sa photo, ou l'initiale de son pseudo. */
    public static function avatar(int $id, string $pseudo, string $classes = ''): string
    {
        $adresse = self::adressePhoto($id);
        $classe = trim('avatar ' . $classes);
        if ($adresse === null) {
            return '<span class="' . e($classe) . '" aria-hidden="true" data-compte-avatar>'
                . e(mb_strtoupper(mb_substr($pseudo, 0, 1))) . '</span>';
        }

        return '<span class="' . e($classe) . ' avatar--photo" aria-hidden="true" data-compte-avatar><img src="' . e($adresse) . '" alt=""></span>';
    }

    /**
     * Pose une photo de profil sur son compte.
     *
     * @return ?string la raison du refus, ou null
     */
    public static function changerPhoto(int $moi, ?array $image): ?string
    {
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choisissez une image.';
        }
        $rangee = self::rangerImage($image);
        if (is_string($rangee)) {
            return $rangee;
        }
        $ancienne = self::photoDe($moi);
        Database::run('UPDATE users SET photo_nom = ?, photo_mime = ? WHERE id = ?', [$rangee['nom'], $rangee['mime'], $moi]);
        self::$photos[$moi] = $rangee['nom'];
        self::effacerImageFond((string) $ancienne);

        return null;
    }

    /** Retire sa photo de profil. Vrai s'il y en avait une. */
    public static function retirerPhoto(int $moi): bool
    {
        $ancienne = self::photoDe($moi);
        if ($ancienne === null) {
            return false;
        }
        Database::run('UPDATE users SET photo_nom = NULL, photo_mime = NULL WHERE id = ?', [$moi]);
        self::$photos[$moi] = null;
        self::effacerImageFond($ancienne);

        return true;
    }

    /** Le compte d'un autre, tel qu'on peut le voir : son identifiant et son pseudo. */
    public static function compte(int $id): ?array
    {
        return Database::one("SELECT id, pseudo FROM users WHERE id = ? AND pseudo IS NOT NULL AND pseudo <> ''", [$id]);
    }

    /** Le fond d'écran de la conversation entre deux comptes, ou null. */
    public static function fond(int $moi, int $autre): ?array
    {
        return Database::one(
            'SELECT image_nom, image_mime, choisi_par, choisi_le FROM fonds_discussion WHERE petit_id = ? AND grand_id = ?',
            [min($moi, $autre), max($moi, $autre)]
        );
    }

    /**
     * L'adresse du fond, pour la page : elle change avec l'image (son nom tiré
     * au hasard y figure), si bien que le navigateur peut la garder longtemps.
     */
    public static function adresseFond(int $moi, int $autre): ?string
    {
        $fond = self::fond($moi, $autre);

        return $fond === null ? null : url('amis/' . $autre . '/fond', ['v' => substr((string) $fond['image_nom'], 0, 12)]);
    }

    /**
     * Pose un fond d'écran sur la conversation, pour les deux.
     *
     * @return ?string la raison du refus, ou null
     */
    public static function changerFond(int $moi, int $autre, ?array $image): ?string
    {
        if (!self::sontAmis($moi, $autre)) {
            return 'Vous ne pouvez régler que les conversations avec vos amis.';
        }
        if ($image === null || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choisissez une image.';
        }
        $rangee = self::rangerImage($image);
        if (is_string($rangee)) {
            return $rangee;
        }

        $ancien = self::fond($moi, $autre);
        try {
            Database::run(
                'INSERT INTO fonds_discussion (petit_id, grand_id, image_nom, image_mime, choisi_par, choisi_le)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE image_nom = VALUES(image_nom), image_mime = VALUES(image_mime),
                                         choisi_par = VALUES(choisi_par), choisi_le = VALUES(choisi_le)',
                [min($moi, $autre), max($moi, $autre), $rangee['nom'], $rangee['mime'], $moi]
            );
        } catch (Throwable $e) {
            @unlink(self::dossierImages() . DIRECTORY_SEPARATOR . $rangee['nom']);
            throw $e;
        }
        if ($ancien !== null) {
            self::effacerImageFond((string) $ancien['image_nom']);
        }
        self::noter($moi, $autre, 'fond');

        return null;
    }

    /**
     * Écrit une note dans la discussion, vue par les deux. Elle est tenue pour
     * lue d'emblée : ce n'est pas un message qui attend une réponse.
     */
    private static function noter(int $moi, int $autre, string $evenement): void
    {
        Database::run(
            "INSERT INTO messages (expediteur_id, destinataire_id, texte, evenement, created_at, lu_le)
             VALUES (?, ?, '', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$moi, $autre, $evenement]
        );
    }

    /** Le texte d'une note, du point de vue de qui la lit. */
    public static function texteEvenement(string $evenement, bool $parMoi, string $pseudo): string
    {
        $qui = $parMoi ? 'Vous avez' : $pseudo . ' a';

        return match ($evenement) {
            'fond' => '🖼️ ' . $qui . ' changé le fond d’écran',
            'fond_retire' => '🖼️ ' . $qui . ' retiré le fond d’écran',
            default => $qui . ' modifié la conversation',
        };
    }

    /** Retire le fond d'écran de la conversation, pour les deux. Vrai s'il y en avait un. */
    public static function retirerFond(int $moi, int $autre): bool
    {
        $ancien = self::fond($moi, $autre);
        if ($ancien === null) {
            return false;
        }
        Database::run('DELETE FROM fonds_discussion WHERE petit_id = ? AND grand_id = ? AND image_nom = ?',
            [min($moi, $autre), max($moi, $autre), $ancien['image_nom']]);
        self::effacerImageFond((string) $ancien['image_nom']);
        self::noter($moi, $autre, 'fond_retire');

        return true;
    }

    private static function effacerImageFond(string $nom): void
    {
        if (preg_match('/^[0-9a-f]{32}\.[a-z0-9]{1,8}$/', $nom)) {
            @unlink(self::dossierImages() . DIRECTORY_SEPARATOR . $nom);
        }
    }

    /** L'amitié entre deux comptes, quel qu'en soit le sens, ou null. */
    public static function relation(int $moi, int $autre): ?array
    {
        return Database::one(
            'SELECT * FROM amities WHERE petit_id = ? AND grand_id = ?',
            [min($moi, $autre), max($moi, $autre)]
        );
    }

    public static function sontAmis(int $moi, int $autre): bool
    {
        $relation = self::relation($moi, $autre);

        return $relation !== null && $relation['statut'] === 'acceptee';
    }

    /**
     * Les comptes dont le pseudo contient ce qu'on cherche, avec où l'on en
     * est avec chacun. Sans majuscules ni accents, comme le pseudo lui-même.
     *
     * @return list<array{id: int, pseudo: string, etat: string}>
     *         etat : « ami », « envoyee », « recue » ou « aucun »
     */
    public static function chercher(int $moi, string $recherche): array
    {
        $recherche = trim($recherche);
        if (mb_strlen($recherche) < 2) {
            return [];
        }
        // « _ » et « % » sont des jokers pour LIKE, et « _ » est permis dans un pseudo.
        $motif = '%' . addcslashes($recherche, '\\%_') . '%';

        $lignes = Database::all(
            "SELECT u.id, u.pseudo, a.statut, a.demandeur_id,
                    EXISTS (SELECT 1 FROM blocages b WHERE b.bloqueur_id = ? AND b.bloque_id = u.id) AS bloque
               FROM users u
               LEFT JOIN amities a ON a.petit_id = LEAST(u.id, ?) AND a.grand_id = GREATEST(u.id, ?)
              WHERE u.id <> ? AND u.pseudo IS NOT NULL AND u.pseudo LIKE ?
                AND NOT EXISTS (SELECT 1 FROM blocages b WHERE b.bloqueur_id = u.id AND b.bloque_id = ?)
              ORDER BY (u.pseudo = ?) DESC, CHAR_LENGTH(u.pseudo), u.pseudo
              LIMIT 20",
            [$moi, $moi, $moi, $moi, $motif, $moi, $recherche]
        );

        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'pseudo' => (string) $l['pseudo'],
            'etat' => match (true) {
                (int) $l['bloque'] === 1 => 'bloque',
                $l['statut'] === 'acceptee' => 'ami',
                $l['statut'] === 'attente' && (int) $l['demandeur_id'] === $moi => 'envoyee',
                $l['statut'] === 'attente' => 'recue',
                default => 'aucun',
            },
        ], $lignes);
    }

    /**
     * Les amis, du plus récemment écrit au plus ancien, avec le nombre de
     * messages non lus et le dernier message échangé.
     */
    public static function liste(int $moi): array
    {
        return Database::all(
            "SELECT u.id, u.pseudo, a.acceptee_le,
                    (SELECT COUNT(*) FROM messages m
                      WHERE m.expediteur_id = u.id AND m.destinataire_id = ? AND m.lu_le IS NULL
                        AND m.supprime_le IS NULL AND m.masque_destinataire = 0) AS non_lus,
                    (SELECT m.id FROM messages m
                      WHERE (m.expediteur_id = u.id AND m.destinataire_id = ? AND m.masque_destinataire = 0)
                         OR (m.expediteur_id = ? AND m.destinataire_id = u.id AND m.masque_expediteur = 0)
                      ORDER BY m.id DESC LIMIT 1) AS dernier_id
               FROM amities a
               JOIN users u ON u.id = IF(a.demandeur_id = ?, a.destinataire_id, a.demandeur_id)
              WHERE (a.demandeur_id = ? OR a.destinataire_id = ?) AND a.statut = 'acceptee'
              ORDER BY COALESCE(dernier_id, 0) DESC, u.pseudo",
            [$moi, $moi, $moi, $moi, $moi, $moi]
        );
    }

    /** Les derniers messages, par identifiant — pour l'aperçu de la liste. */
    public static function messagesParId(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $lignes = Database::all(
            'SELECT id, expediteur_id, texte, evenement, image_nom, fichier_origine, audio_nom, partage_type, supprime_le, created_at FROM messages WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );

        return array_column($lignes, null, 'id');
    }

    /** Les demandes reçues, en attente d'une réponse. */
    public static function demandesRecues(int $moi): array
    {
        return Database::all(
            "SELECT u.id, u.pseudo, a.created_at
               FROM amities a JOIN users u ON u.id = a.demandeur_id
              WHERE a.destinataire_id = ? AND a.statut = 'attente'
              ORDER BY a.created_at DESC",
            [$moi]
        );
    }

    /** Les demandes envoyées, qui attendent encore. */
    public static function demandesEnvoyees(int $moi): array
    {
        return Database::all(
            "SELECT u.id, u.pseudo, a.created_at
               FROM amities a JOIN users u ON u.id = a.destinataire_id
              WHERE a.demandeur_id = ? AND a.statut = 'attente'
              ORDER BY a.created_at DESC",
            [$moi]
        );
    }

    /** Ce qui attend dans l'onglet « Amis » : messages non lus et demandes reçues. */
    public static function enAttente(int $moi): int
    {
        return (int) Database::valeur(
            "SELECT (SELECT COUNT(*) FROM messages m
                      JOIN amities a ON a.petit_id = LEAST(m.expediteur_id, m.destinataire_id)
                                    AND a.grand_id = GREATEST(m.expediteur_id, m.destinataire_id)
                                    AND a.statut = 'acceptee'
                     WHERE m.destinataire_id = ? AND m.lu_le IS NULL AND m.supprime_le IS NULL AND m.masque_destinataire = 0)
                  + (SELECT COUNT(*) FROM amities WHERE destinataire_id = ? AND statut = 'attente')",
            [$moi, $moi]
        ) + Conversations::nonLus($moi) + Conversations::nombreInvitations($moi);
    }

    /**
     * Demande quelqu'un en ami. S'il nous l'avait déjà demandé, c'est accepté.
     *
     * @return string ce qui s'est passé : « envoyee », « acceptee », « deja », « trop », « introuvable »
     */
    public static function demander(int $moi, int $autre): string
    {
        if ($autre === $moi || self::compte($autre) === null) {
            return 'introuvable';
        }
        // L'autre ne verrait de nous qu'un nom vide : il faut un pseudo pour se présenter.
        if (self::compte($moi) === null) {
            return 'sans_pseudo';
        }
        if (self::aBloque($moi, $autre)) {
            return 'bloque';
        }
        // Qui a été bloqué ne l'apprend pas : pour lui, ce compte est simplement introuvable.
        if (self::aBloque($autre, $moi)) {
            return 'introuvable';
        }

        $relation = self::relation($moi, $autre);
        if ($relation !== null) {
            if ($relation['statut'] === 'attente' && (int) $relation['destinataire_id'] === $moi) {
                self::accepter($moi, $autre);
                return 'acceptee';
            }
            return 'deja';
        }

        $enAttente = (int) Database::valeur("SELECT COUNT(*) FROM amities WHERE demandeur_id = ? AND statut = 'attente'", [$moi]);
        if ($enAttente >= self::DEMANDES_MAX) {
            return 'trop';
        }

        try {
            Database::run(
                'INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$moi, $autre, min($moi, $autre), max($moi, $autre), 'attente']
            );
        } catch (PDOException $e) {
            // Deux demandes croisées au même instant : la paire existe déjà.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            return 'deja';
        }

        return 'envoyee';
    }

    /** Accepte la demande que l'autre nous a faite. */
    public static function accepter(int $moi, int $autre): bool
    {
        if (self::aBloque($moi, $autre) || self::aBloque($autre, $moi)) {
            return false;
        }
        return Database::run(
            "UPDATE amities SET statut = 'acceptee', acceptee_le = UTC_TIMESTAMP()
              WHERE demandeur_id = ? AND destinataire_id = ? AND statut = 'attente'",
            [$autre, $moi]
        )->rowCount() > 0;
    }

    /** Ce compte en a-t-il bloqué cet autre ? */
    public static function aBloque(int $bloqueur, int $bloque): bool
    {
        return Database::valeur('SELECT 1 FROM blocages WHERE bloqueur_id = ? AND bloque_id = ?', [$bloqueur, $bloque]) !== null;
    }

    /**
     * Bloque un compte : l'amitié et les demandes en cours disparaissent, et
     * les notifications qu'il avait provoquées et qui n'étaient pas encore
     * parties ne partiront pas.
     */
    public static function bloquer(int $moi, int $autre): bool
    {
        if ($autre === $moi || self::compte($autre) === null) {
            return false;
        }
        Database::run('INSERT IGNORE INTO blocages (bloqueur_id, bloque_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$moi, $autre]);
        self::defaire($moi, $autre);
        Database::run(
            "DELETE FROM notifications_file WHERE user_id = ? AND envoye_le IS NULL AND etiquette IN (?, ?, ?)",
            [$moi, 'message-' . $autre, 'demande-' . $autre, 'acceptation-' . $autre]
        );

        return true;
    }

    /** Débloque un compte ; l'amitié, elle, ne revient pas d'elle-même. */
    public static function debloquer(int $moi, int $autre): bool
    {
        return Database::run('DELETE FROM blocages WHERE bloqueur_id = ? AND bloque_id = ?', [$moi, $autre])->rowCount() > 0;
    }

    /** Les comptes que la personne a bloqués. */
    public static function bloques(int $moi): array
    {
        return Database::all(
            'SELECT u.id, u.pseudo, b.created_at FROM blocages b JOIN users u ON u.id = b.bloque_id
              WHERE b.bloqueur_id = ? ORDER BY u.pseudo',
            [$moi]
        );
    }

    /**
     * Défait le lien, quel qu'il soit : refuser une demande reçue, annuler une
     * demande envoyée, retirer un ami. Les messages restent en base, mais ne
     * se lisent plus tant qu'on n'est pas de nouveau amis.
     */
    public static function defaire(int $moi, int $autre): bool
    {
        return Database::run(
            'DELETE FROM amities WHERE petit_id = ? AND grand_id = ?',
            [min($moi, $autre), max($moi, $autre)]
        )->rowCount() > 0;
    }

    /**
     * Écrit à un ami, avec ou sans image, avec ou sans fichier.
     *
     * @param ?array $image   l'image téléversée ($_FILES['image']), s'il y en a une
     * @param ?array $fichier le fichier téléversé ($_FILES['fichier']), s'il y en a un
     * @param ?int   $reponseA le message auquel celui-ci répond ; ignoré s'il n'est pas de la conversation
     * @return array{0: ?int, 1: ?string} l'identifiant du message, ou la raison du refus
     */
    public static function ecrire(int $moi, int $autre, string $texte, ?array $image = null, ?array $fichier = null, ?int $reponseA = null,
                                  ?array $vocal = null, int $dureeVocal = 0, ?string $transcription = null): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        $avecImage = $image !== null && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avecFichier = $fichier !== null && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avecVocal = $vocal !== null && ($vocal['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($texte === '' && !$avecImage && !$avecFichier && !$avecVocal) {
            return [null, 'Le message est vide.'];
        }
        if ((int) $avecImage + (int) $avecFichier + (int) $avecVocal > 1) {
            return [null, 'Une seule pièce jointe par message.'];
        }
        if (mb_strlen($texte) > self::MESSAGE_MAX) {
            return [null, 'Un message ne peut pas dépasser ' . self::MESSAGE_MAX . ' caractères.'];
        }
        if (!self::sontAmis($moi, $autre)) {
            return [null, 'Vous ne pouvez écrire qu’à vos amis.'];
        }
        $recents = (int) Database::valeur(
            'SELECT COUNT(*) FROM messages WHERE expediteur_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 MINUTE',
            [$moi]
        );
        if ($recents >= self::MESSAGES_PAR_MINUTE) {
            return [null, 'Trop de messages d’un coup : patientez un instant.'];
        }

        // La pièce jointe en dernier : on ne range rien sur le disque pour un message refusé.
        $rangee = null;
        $joint = null;
        if ($avecImage) {
            $rangee = self::rangerImage($image);
            if (is_string($rangee)) {
                return [null, $rangee];
            }
        }
        if ($avecFichier) {
            $joint = self::rangerFichier($fichier);
            if (is_string($joint)) {
                return [null, $joint];
            }
        }
        $enregistre = null;
        // Une transcription n'est gardée que si la personne ne l'a pas coupée.
        $transcrit = $avecVocal && (int) Database::valeur('SELECT transcription_vocale FROM users WHERE id = ?', [$moi]) === 1
            ? self::transcription($transcription) : null;
        if ($avecVocal) {
            $enregistre = self::rangerVocal($vocal, $dureeVocal);
            if (is_string($enregistre)) {
                return [null, $enregistre];
            }
        }

        try {
            Database::run(
                'INSERT INTO messages (expediteur_id, destinataire_id, reponse_a, texte, image_nom, image_mime, image_largeur, image_hauteur,
                                       fichier_nom, fichier_origine, fichier_mime, fichier_taille, audio_nom, audio_duree, audio_transcription, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$moi, $autre, $reponseA !== null && self::visible($moi, $autre, $reponseA) ? $reponseA : null,
                 $texte, $rangee['nom'] ?? null, $rangee['mime'] ?? null, $rangee['largeur'] ?? null, $rangee['hauteur'] ?? null,
                 $joint['nom'] ?? null, $joint['origine'] ?? null, $joint['mime'] ?? null, $joint['taille'] ?? null,
                 $enregistre['nom'] ?? null, $enregistre['duree'] ?? null, $transcrit]
            );
        } catch (Throwable $e) {
            foreach ([$rangee, $joint, $enregistre] as $range) {
                if ($range !== null) {
                    @unlink(self::dossierImages() . DIRECTORY_SEPARATOR . $range['nom']);
                }
            }
            throw $e;
        }

        return [Database::dernierId(), null];
    }

    /**
     * Vérifie une image téléversée et la range dans le dossier des discussions.
     *
     * Le fichier n'est jamais gardé tel quel, sauf un GIF (pour qu'il reste
     * animé) : l'image est redessinée, ce qui retire ses métadonnées — la
     * position GPS d'une photo prise au téléphone, notamment — et tout ce
     * qu'on aurait pu cacher dedans. Une photo penchée est remise d'aplomb
     * d'après son orientation, et une trop grande est réduite.
     *
     * @return array{nom: string, mime: string, largeur: int, hauteur: int}|string l'image rangée, ou la raison du refus
     */
    public static function rangerImage(array $fichier): array|string
    {
        $code = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            return 'L’image n’a pas pu être envoyée : ' . Fichiers::messageErreur($code);
        }
        $tmp = (string) ($fichier['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            return 'L’image n’a pas pu être envoyée.';
        }
        if ((int) filesize($tmp) > self::IMAGE_MAX_OCTETS) {
            return 'Cette image est trop lourde : ' . intdiv(self::IMAGE_MAX_OCTETS, 1024 * 1024) . ' Mo au plus.';
        }

        $infos = @getimagesize($tmp);
        if ($infos === false || !isset(self::IMAGE_TYPES[$infos[2]])) {
            return 'Ce fichier n’est pas une image acceptée (JPEG, PNG, GIF ou WebP).';
        }
        [$largeur, $hauteur, $type] = $infos;
        if ($largeur < 1 || $hauteur < 1 || $largeur * $hauteur > self::IMAGE_PIXELS_MAX) {
            return 'Cette image est trop grande pour être envoyée.';
        }
        [$mime, $extension] = self::IMAGE_TYPES[$type];

        $dossier = self::dossierImages();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return 'Impossible de ranger l’image sur le serveur.';
        }
        $nom = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $dossier . DIRECTORY_SEPARATOR . $nom;

        if ($type === IMAGETYPE_GIF) {
            if (!move_uploaded_file($tmp, $destination)) {
                return 'Impossible de ranger l’image sur le serveur.';
            }
            return ['nom' => $nom, 'mime' => $mime, 'largeur' => min($largeur, 65535), 'hauteur' => min($hauteur, 65535)];
        }

        // Décoder une grande photo demande de la place : pour cette seule requête.
        $memoire = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');
        try {
            $source = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
                IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
                IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
            };
            if ($source === false) {
                return 'Cette image est illisible.';
            }

            if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($tmp);
                $source = self::redresser($source, (int) ($exif['Orientation'] ?? 1));
            }

            $largeur = imagesx($source);
            $hauteur = imagesy($source);
            $echelle = min(1, self::IMAGE_COTE_MAX / max($largeur, $hauteur));
            $l = max(1, (int) round($largeur * $echelle));
            $h = max(1, (int) round($hauteur * $echelle));

            $finale = imagecreatetruecolor($l, $h);
            if ($type !== IMAGETYPE_JPEG) {
                imagealphablending($finale, false);
                imagesavealpha($finale, true);
                imagefill($finale, 0, 0, imagecolorallocatealpha($finale, 0, 0, 0, 127));
            }
            imagecopyresampled($finale, $source, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);
            imagedestroy($source);

            $ecrit = match ($type) {
                IMAGETYPE_JPEG => imagejpeg($finale, $destination, 85),
                IMAGETYPE_PNG  => imagepng($finale, $destination, 6),
                IMAGETYPE_WEBP => imagewebp($finale, $destination, 85),
            };
            imagedestroy($finale);
            if (!$ecrit) {
                @unlink($destination);
                return 'Impossible de ranger l’image sur le serveur.';
            }

            return ['nom' => $nom, 'mime' => $mime, 'largeur' => $l, 'hauteur' => $h];
        } finally {
            @ini_set('memory_limit', (string) $memoire);
        }
    }

    /**
     * Vérifie un fichier joint et le range, tel quel, dans le dossier des
     * discussions.
     *
     * Un fichier n'est jamais exécuté ni affiché dans la page : il est rangé
     * sous un nom tiré au hasard, dans un dossier fermé au navigateur, et ne
     * se lit que par l'application — qui le donne à télécharger, et n'ouvre
     * directement que les types sans danger (PDF, texte, audio, vidéo).
     *
     * @return array{nom: string, origine: string, mime: string, taille: int}|string le fichier rangé, ou la raison du refus
     */
    public static function rangerFichier(array $fichier): array|string
    {
        $code = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            return 'Le fichier n’a pas pu être envoyé : ' . Fichiers::messageErreur($code);
        }
        $tmp = (string) ($fichier['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            return 'Le fichier n’a pas pu être envoyé.';
        }
        $taille = (int) filesize($tmp);
        if ($taille > self::fichierMax()) {
            return 'Ce fichier est trop lourd : ' . intdiv(self::fichierMax(), 1024 * 1024) . ' Mo au plus.';
        }
        if ($taille === 0) {
            return 'Ce fichier est vide.';
        }

        // Le nom d'origine, débarrassé de tout chemin et des caractères invisibles.
        $origine = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string) ($fichier['name'] ?? '')))));
        $extension = strtolower(pathinfo($origine, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::extensionsFichiers(), true)) {
            return 'Ce type de fichier n’est pas accepté.';
        }
        if (mb_strlen($origine) > 255) {
            $origine = mb_substr(pathinfo($origine, PATHINFO_FILENAME), 0, 240) . '.' . $extension;
        }

        $dossier = self::dossierImages();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return 'Impossible de ranger le fichier sur le serveur.';
        }
        // Un texte reste un texte, même s'il contient des balises : il s'ouvrira en texte brut, jamais en page.
        $mime = in_array($extension, ['txt', 'md', 'csv'], true)
            ? 'text/plain'
            : (Fichiers::detecterMime($tmp) ?: 'application/octet-stream');
        $nom = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, $dossier . DIRECTORY_SEPARATOR . $nom)) {
            return 'Impossible de ranger le fichier sur le serveur.';
        }

        return ['nom' => $nom, 'origine' => $origine, 'mime' => mb_substr($mime, 0, 120), 'taille' => $taille];
    }

    /** Le message et son fichier, si la personne connectée a le droit de le voir. */
    public static function fichier(int $moi, int $messageId): ?array
    {
        $message = Database::one(
            'SELECT id, expediteur_id, destinataire_id, fichier_nom, fichier_origine, fichier_mime FROM messages
              WHERE id = ? AND fichier_nom IS NOT NULL
                AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        if ($message === null) {
            return null;
        }
        $autre = (int) $message['expediteur_id'] === $moi ? (int) $message['destinataire_id'] : (int) $message['expediteur_id'];

        return self::sontAmis($moi, $autre) ? $message : null;
    }

    /** Remet d'aplomb une photo d'après son orientation EXIF. */
    private static function redresser(GdImage $image, int $orientation): GdImage
    {
        $tournee = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($tournee === false) {
            return $image;
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($tournee, IMG_FLIP_HORIZONTAL);
        }
        if ($tournee !== $image) {
            imagedestroy($image);
        }

        return $tournee;
    }

    /** Le message et son image, si la personne connectée a le droit de la voir. */
    public static function image(int $moi, int $messageId): ?array
    {
        $message = Database::one(
            'SELECT id, expediteur_id, destinataire_id, image_nom, image_mime FROM messages
              WHERE id = ? AND image_nom IS NOT NULL
                AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        if ($message === null) {
            return null;
        }
        $autre = (int) $message['expediteur_id'] === $moi ? (int) $message['destinataire_id'] : (int) $message['expediteur_id'];

        return self::sontAmis($moi, $autre) ? $message : null;
    }

    /**
     * Les messages d'une conversation après tel identifiant (ou les derniers),
     * du plus ancien au plus récent. Ceux qu'on reçoit sont marqués lus.
     */
    public static function fil(int $moi, int $autre, int $apres = 0, ?int $depuisMessage = null): array
    {
        Database::run(
            'UPDATE messages SET lu_le = UTC_TIMESTAMP() WHERE expediteur_id = ? AND destinataire_id = ? AND lu_le IS NULL',
            [$autre, $moi]
        );

        // Aller à un message plus ancien que les derniers affichés (une épingle) : le fil part de lui.
        $messages = $depuisMessage !== null
            ? self::lignes($moi, $autre, 'm.id >= ?', [$depuisMessage], 'ORDER BY m.id DESC LIMIT ' . self::FIL_DEPUIS_MAX)
            : self::lignes($moi, $autre, 'm.id > ?', [$apres], 'ORDER BY m.id DESC LIMIT ' . self::FIL_MAX);

        return self::avecReactions(array_map(static fn (array $m): array => self::pourAffichage($m, $moi), array_reverse($messages)), $moi, $autre);
    }

    /**
     * Les messages d'une conversation que la personne peut voir, avec de quoi
     * citer celui auquel chacun répond.
     */
    private static function lignes(int $moi, int $autre, string $condition, array $parametres, string $suite): array
    {
        return Database::all(
            'SELECT m.id, m.expediteur_id, m.texte, m.image_nom, m.image_largeur, m.image_hauteur,
                    m.fichier_nom, m.fichier_origine, m.fichier_mime, m.fichier_taille, m.audio_nom, m.audio_duree, m.audio_transcription, m.evenement, m.partage_type, m.partage_id,
                    m.created_at, m.modifie_le, m.lu_le, m.supprime_le, m.reponse_a,
                    r.expediteur_id AS r_expediteur, r.texte AS r_texte, r.image_nom AS r_image,
                    r.fichier_origine AS r_fichier, r.audio_nom AS r_audio, r.partage_type AS r_partage, r.supprime_le AS r_supprime,
                    ((r.expediteur_id = ? AND r.masque_expediteur = 1) OR (r.destinataire_id = ? AND r.masque_destinataire = 1)) AS r_masque
               FROM messages m
               LEFT JOIN messages r ON r.id = m.reponse_a
              WHERE ((m.expediteur_id = ? AND m.destinataire_id = ? AND m.masque_expediteur = 0)
                  OR (m.expediteur_id = ? AND m.destinataire_id = ? AND m.masque_destinataire = 0))
                AND ' . $condition . ' ' . $suite,
            array_merge([$moi, $moi, $moi, $autre, $autre, $moi], $parametres)
        );
    }

    /** Ce message est-il de cette conversation, et encore visible pour la personne ? */
    private static function visible(int $moi, int $autre, int $messageId): bool
    {
        return Database::valeur(
            'SELECT id FROM messages WHERE id = ? AND evenement IS NULL
                AND ((expediteur_id = ? AND destinataire_id = ? AND masque_expediteur = 0)
                  OR (expediteur_id = ? AND destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $autre, $autre, $moi]
        ) !== null;
    }

    /**
     * Les messages modifiés depuis tel instant (en temps universel), pour
     * qu'une page déjà ouverte reprenne leur nouveau texte.
     */
    public static function modifications(int $moi, int $autre, string $depuis): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $depuis)) {
            return [];
        }
        // Deux secondes de marge : une modification enregistrée pendant le relevé précédent n'est pas perdue.
        $lignes = self::lignes($moi, $autre, 'm.modifie_le >= ? - INTERVAL 2 SECOND', [$depuis], 'ORDER BY m.id LIMIT 200');

        return self::avecReactions(array_map(static fn (array $m): array => self::pourAffichage($m, $moi), $lignes), $moi, $autre);
    }

    /** L'instant présent en temps universel, tel que la base le compte. */
    public static function maintenant(): string
    {
        return (string) Database::valeur('SELECT UTC_TIMESTAMP()');
    }

    /**
     * Modifie le texte d'un message envoyé.
     *
     * Seul qui l'a écrit le peut, tant qu'il n'est pas supprimé. Un message
     * avec une photo ou un fichier peut perdre sa légende ; un message de
     * texte seul ne peut pas devenir vide — c'est « Supprimer » qu'il faut.
     *
     * @return array{0: bool, 1: string} réussi ou non, et le message à montrer
     */
    public static function modifierMessage(int $moi, int $messageId, string $texte): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        $message = Database::one(
            'SELECT * FROM messages WHERE id = ? AND expediteur_id = ? AND masque_expediteur = 0 AND evenement IS NULL',
            [$messageId, $moi]
        );
        if ($message === null || !self::sontAmis($moi, (int) $message['destinataire_id'])) {
            return [false, 'Seul qui a écrit un message peut le modifier.'];
        }
        if ($message['supprime_le'] !== null) {
            return [false, 'Un message supprimé ne peut plus être modifié.'];
        }
        if (mb_strlen($texte) > self::MESSAGE_MAX) {
            return [false, 'Un message ne peut pas dépasser ' . self::MESSAGE_MAX . ' caractères.'];
        }
        if ($texte === '' && $message['image_nom'] === null && $message['fichier_nom'] === null && $message['audio_nom'] === null
            && $message['partage_type'] === null) {
            return [false, 'Le message ne peut pas être vide : pour l’enlever, supprimez-le.'];
        }
        if ($texte !== (string) $message['texte']) {
            Database::run('UPDATE messages SET texte = ?, modifie_le = UTC_TIMESTAMP() WHERE id = ?', [$texte, $messageId]);
        }

        return [true, 'Message modifié.'];
    }

    /** De quoi reconnaître un message cité : le début de son texte, ou sa pièce jointe. */
    public static function extrait(array $cite): string
    {
        if ((int) ($cite['r_masque'] ?? 0) === 1) {
            return 'Message supprimé';
        }
        if (($cite['r_supprime'] ?? null) !== null) {
            return '🚫 Message supprimé';
        }
        $texte = trim((string) preg_replace('/\s+/u', ' ', (string) ($cite['r_texte'] ?? '')));
        $piece = ($cite['r_image'] ?? null) !== null ? '📷 Photo'
            : (($cite['r_fichier'] ?? null) !== null ? '📎 ' . $cite['r_fichier']
            : (($cite['r_audio'] ?? null) !== null ? '🎤 Message vocal'
            : (($cite['r_partage'] ?? null) !== null ? '🔗 Document partagé' : '')));

        return mb_strimwidth($texte === '' ? $piece : ($piece === '' ? $texte : $piece . ' · ' . $texte), 0, 120, '…');
    }

    /**
     * Ce qui a changé dans les messages déjà affichés : ceux qu'on a supprimés
     * pour tout le monde (à montrer « supprimé ») et ceux qu'on a cachés pour
     * soi depuis un autre onglet (à retirer).
     *
     * @return array{supprimes: list<int>, masques: list<int>}
     */
    public static function changements(int $moi, int $autre, int $jusqua): array
    {
        $lignes = Database::all(
            'SELECT id, supprime_le IS NOT NULL AS supprime,
                    ((expediteur_id = ? AND masque_expediteur = 1) OR (destinataire_id = ? AND masque_destinataire = 1)) AS masque
               FROM messages
              WHERE ((expediteur_id = ? AND destinataire_id = ?) OR (expediteur_id = ? AND destinataire_id = ?))
                AND id <= ? AND (supprime_le IS NOT NULL OR masque_expediteur = 1 OR masque_destinataire = 1)
              ORDER BY id DESC LIMIT 500',
            [$moi, $moi, $moi, $autre, $autre, $moi, $jusqua]
        );
        $supprimes = [];
        $masques = [];
        foreach ($lignes as $l) {
            if ((int) $l['masque'] === 1) {
                $masques[] = (int) $l['id'];
            } elseif ((int) $l['supprime'] === 1) {
                $supprimes[] = (int) $l['id'];
            }
        }

        return ['supprimes' => $supprimes, 'masques' => $masques];
    }

    /**
     * Supprime un message.
     *
     * « moi » : il disparaît de ma conversation seulement ; l'autre le garde.
     * « tous » : réservé à qui l'a écrit ; le texte, l'image et le fichier
     * sont effacés pour de bon, et les deux côtés voient « Message supprimé ».
     * Un message caché des deux côtés n'a plus de lecteur : son contenu est
     * effacé aussi.
     *
     * @return string « fait », « introuvable » ou « interdit »
     */
    public static function supprimerMessage(int $moi, int $messageId, string $portee): string
    {
        $message = Database::one(
            'SELECT * FROM messages WHERE id = ? AND evenement IS NULL AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        if ($message === null) {
            return 'introuvable';
        }
        $jeSuisLAuteur = (int) $message['expediteur_id'] === $moi;
        $autre = $jeSuisLAuteur ? (int) $message['destinataire_id'] : (int) $message['expediteur_id'];
        if (!self::sontAmis($moi, $autre)) {
            return 'introuvable';
        }

        if ($portee === 'tous') {
            if (!$jeSuisLAuteur) {
                return 'interdit';
            }
            self::viderMessage($message);
            Database::run('UPDATE messages SET supprime_le = COALESCE(supprime_le, UTC_TIMESTAMP()) WHERE id = ?', [$messageId]);

            return 'fait';
        }

        Database::run('UPDATE messages SET ' . ($jeSuisLAuteur ? 'masque_expediteur' : 'masque_destinataire') . ' = 1 WHERE id = ?', [$messageId]);
        $apres = Database::one('SELECT * FROM messages WHERE id = ?', [$messageId]);
        if ($apres !== null && (int) $apres['masque_expediteur'] === 1 && (int) $apres['masque_destinataire'] === 1) {
            self::viderMessage($apres);
        }

        return 'fait';
    }

    /** Efface le contenu d'un message : son texte, et ses pièces jointes du disque. */
    private static function viderMessage(array $message): void
    {
        foreach ([$message['image_nom'] ?? null, $message['fichier_nom'] ?? null, $message['audio_nom'] ?? null] as $nom) {
            if (is_string($nom) && preg_match('/^[0-9a-f]{32}\.[a-z0-9]{1,8}$/', $nom)) {
                @unlink(self::dossierImages() . DIRECTORY_SEPARATOR . $nom);
            }
        }
        Database::run(
            "UPDATE messages SET texte = '', image_nom = NULL, image_mime = NULL, image_largeur = NULL, image_hauteur = NULL,
                    fichier_nom = NULL, fichier_origine = NULL, fichier_mime = NULL, fichier_taille = NULL, audio_nom = NULL, audio_duree = NULL, audio_transcription = NULL, partage_type = NULL, partage_id = NULL, reponse_a = NULL
              WHERE id = ?",
            [(int) $message['id']]
        );
        // Un message effacé n'a plus de réactions.
        if (Database::run('DELETE FROM reactions WHERE message_id = ?', [(int) $message['id']])->rowCount() > 0) {
            Database::run('UPDATE messages SET reactions_le = UTC_TIMESTAMP() WHERE id = ?', [(int) $message['id']]);
        }
    }

    /** Les réactions acceptées dans un menu : une poignée de raccourcis, le reste par le choix d'emojis. */
    public const REACTIONS_RAPIDES = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /**
     * Un emoji, et rien d'autre : pas de lettre, pas de chiffre, pas d'espace
     * ni de balise, une dizaine de caractères au plus (un emoji composé en
     * compte plusieurs : 👩‍🎓, 👍🏽…).
     */
    public static function emojiValide(string $emoji): bool
    {
        return $emoji !== '' && strlen($emoji) <= 32 && mb_strlen($emoji) <= 10
            && preg_match('/[\x00-\x7F]/', $emoji) !== 1
            && preg_match('/^[^\p{L}\p{N}\p{Z}]+$/u', $emoji) === 1
            && preg_match('/[\p{So}\p{Sk}]/u', $emoji) === 1;
    }

    /**
     * Réagit à un message : pose l'emoji, le remplace, ou l'enlève s'il était
     * déjà le sien (un emoji vide l'enlève aussi).
     *
     * @return array{0: bool, 1: string, 2: list<array>, 3: ?int} réussi, message, les réactions, la notification en file
     */
    public static function reagir(int $moi, int $messageId, string $emoji): array
    {
        $emoji = trim($emoji);
        $message = Database::one(
            'SELECT * FROM messages WHERE id = ? AND evenement IS NULL AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        if ($message === null) {
            return [false, 'Ce message est introuvable.', [], null];
        }
        $auteur = (int) $message['expediteur_id'];
        $autre = $auteur === $moi ? (int) $message['destinataire_id'] : $auteur;
        if (!self::sontAmis($moi, $autre)) {
            return [false, 'Ce message est introuvable.', [], null];
        }
        if ($message['supprime_le'] !== null) {
            return [false, 'On ne réagit pas à un message supprimé.', [], null];
        }
        if ($emoji !== '' && !self::emojiValide($emoji)) {
            return [false, 'Une réaction, c’est un emoji.', [], null];
        }

        $actuelle = Database::valeur('SELECT emoji FROM reactions WHERE message_id = ? AND user_id = ?', [$messageId, $moi]);
        $pose = $emoji !== '' && $emoji !== $actuelle;
        if ($pose) {
            Database::run(
                'INSERT INTO reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = UTC_TIMESTAMP()',
                [$messageId, $moi, $emoji]
            );
        } else {
            Database::run('DELETE FROM reactions WHERE message_id = ? AND user_id = ?', [$messageId, $moi]);
        }
        Database::run('UPDATE messages SET reactions_le = UTC_TIMESTAMP() WHERE id = ?', [$messageId]);

        // L'auteur est prévenu d'une réaction à son message — sauf s'il a la discussion sous les yeux.
        $notification = null;
        if ($pose && $auteur !== $moi) {
            $depuis = Database::valeur(
                'SELECT TIMESTAMPDIFF(SECOND, regarde_le, UTC_TIMESTAMP()) FROM discussions_etat WHERE user_id = ? AND ami_id = ?',
                [$auteur, $moi]
            );
            if (($depuis === null || (int) $depuis >= self::PRESENCE_SECONDES) && !self::estMuette($auteur, $moi)) {
                $extrait = self::extrait([
                    'r_texte' => $message['texte'], 'r_image' => $message['image_nom'],
                    'r_fichier' => $message['fichier_origine'], 'r_supprime' => null, 'r_masque' => 0,
                ]);
                $notification = FileNotifications::ajouter($auteur, 'reaction', [
                    'title' => $emoji . ' ' . (self::compte($moi)['pseudo'] ?? 'Un ami') . ' a réagi',
                    'body' => 'À votre message : « ' . $extrait . ' »',
                    'url' => url('amis/' . $moi),
                    'tag' => 'reaction-' . $moi,
                ]);
            }
        }

        return [true, $pose ? 'Réaction ajoutée.' : 'Réaction retirée.', self::reactionsDe([$messageId], $moi, $autre)[$messageId] ?? [], $notification];
    }

    /**
     * Les réactions de plusieurs messages, regroupées par emoji dans l'ordre où
     * elles sont apparues : l'emoji, combien, si j'en suis, et qui.
     *
     * @return array<int, list<array{emoji: string, nombre: int, moi: bool, qui: string}>>
     */
    public static function reactionsDe(array $ids, int $moi, int $autre): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $lignes = Database::all(
            'SELECT message_id, user_id, emoji FROM reactions WHERE message_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
              ORDER BY created_at, user_id',
            $ids
        );
        $pseudoAutre = (string) (self::compte($autre)['pseudo'] ?? '');
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
                $parMessage[$m][$e]['qui'][] = $pseudoAutre;
            }
        }

        return array_map(static fn (array $groupes): array => array_values(array_map(
            static fn (array $g): array => ['qui' => implode(', ', $g['qui'])] + $g, $groupes
        )), $parMessage);
    }

    /** Ajoute leurs réactions à des messages prêts à montrer. */
    private static function avecReactions(array $messages, int $moi, int $autre): array
    {
        $ids = array_column($messages, 'id');
        $reactions = self::reactionsDe($ids, $moi, $autre);
        $epingles = $ids === [] ? [] : array_flip(array_map('intval', array_column(Database::all(
            'SELECT message_id FROM epingles WHERE user_id = ? AND message_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_merge([$moi], $ids)
        ), 'message_id')));
        foreach ($messages as &$m) {
            $m['reactions'] = $reactions[$m['id']] ?? [];
            $m['epingle'] = isset($epingles[$m['id']]);
        }

        return $messages;
    }

    /** Résultats montrés au plus pour une recherche dans une conversation. */
    public const RECHERCHE_MAX = 50;

    /**
     * Cherche un mot dans une conversation : le texte des messages et le nom
     * des fichiers, sans tenir compte des majuscules ni des accents, du plus
     * récent au plus ancien. Les messages supprimés ou cachés pour soi n'y
     * sont pas.
     *
     * Chaque résultat est découpé autour du mot trouvé — avant, trouvé, après —
     * pour que la page le surligne sans jamais interpréter le texte.
     *
     * @return array{total: int, resultats: list<array>}
     */
    public static function rechercher(int $moi, int $autre, string $recherche): array
    {
        $recherche = trim((string) preg_replace('/\s+/u', ' ', $recherche));
        if (mb_strlen($recherche) < 2) {
            return ['total' => 0, 'resultats' => []];
        }
        $motif = '%' . addcslashes($recherche, '\\%_') . '%';
        $conditions = '((expediteur_id = ? AND destinataire_id = ? AND masque_expediteur = 0)
                     OR (expediteur_id = ? AND destinataire_id = ? AND masque_destinataire = 0))
                    AND supprime_le IS NULL AND evenement IS NULL AND (texte LIKE ? OR fichier_origine LIKE ?)';
        $parametres = [$moi, $autre, $autre, $moi, $motif, $motif];

        $total = (int) Database::valeur("SELECT COUNT(*) FROM messages WHERE $conditions", $parametres);
        $lignes = Database::all(
            "SELECT id, expediteur_id, texte, fichier_origine, image_nom, created_at FROM messages
              WHERE $conditions ORDER BY id DESC LIMIT " . self::RECHERCHE_MAX,
            $parametres
        );
        $pseudo = (string) (self::compte($autre)['pseudo'] ?? '');

        $resultats = array_map(static function (array $l) use ($moi, $pseudo, $recherche): array {
            $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $l['texte']));
            $nomFichier = (string) ($l['fichier_origine'] ?? '');
            // Le mot peut être dans le texte ou, à défaut, dans le nom du fichier.
            $source = self::trouver($texte, $recherche) !== null || $nomFichier === '' ? $texte : $nomFichier;
            $moment = self::local((string) $l['created_at']);
            $jour = self::jour($moment);

            return [
                'id' => (int) $l['id'],
                'auteur' => (int) $l['expediteur_id'] === $moi ? 'Vous' : $pseudo,
                'quand' => ($jour === 'Aujourd’hui' ? '' : $jour . ' · ') . $moment->format('H:i'),
                'piece' => $nomFichier !== '' ? '📎 ' : ($l['image_nom'] !== null ? '📷 ' : ''),
            ] + self::decouper($source, $recherche);
        }, $lignes);

        return ['total' => $total, 'resultats' => $resultats];
    }

    /** Un texte ramené à ses lettres de base, sans changer sa longueur : « Élève » → « eleve ». */
    public static function plier(string $texte): string
    {
        static $sans = null;
        $sans ??= array_combine(
            preg_split('//u', 'àâäáãåçéèêëíìîïñóòôöõúùûüýÿœæÀÂÄÁÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸŒÆ', -1, PREG_SPLIT_NO_EMPTY),
            preg_split('//u', 'aaaaaaceeeeiiiinooooouuuuyyoaaaaaaaceeeeiiiinooooouuuuyyoa', -1, PREG_SPLIT_NO_EMPTY)
        );

        return mb_strtolower(strtr($texte, $sans));
    }

    /** La position du mot cherché dans un texte, sans majuscules ni accents, ou null. */
    public static function trouver(string $texte, string $recherche): ?int
    {
        $position = mb_strpos(self::plier($texte), self::plier($recherche));

        return $position === false ? null : $position;
    }

    /**
     * Un extrait d'une centaine de caractères autour du mot trouvé.
     *
     * @return array{avant: string, trouve: string, apres: string}
     */
    public static function decouper(string $texte, string $recherche): array
    {
        $position = self::trouver($texte, $recherche);
        if ($position === null) {
            return ['avant' => mb_strimwidth($texte, 0, 110, '…'), 'trouve' => '', 'apres' => ''];
        }
        $longueur = mb_strlen($recherche);
        $debut = max(0, $position - 40);
        $avant = mb_substr($texte, $debut, $position - $debut);
        $apres = mb_substr($texte, $position + $longueur, 70);

        return [
            'avant' => ($debut > 0 ? '…' : '') . $avant,
            'trouve' => mb_substr($texte, $position, $longueur),
            'apres' => $apres . (mb_strlen($texte) > $position + $longueur + 70 ? '…' : ''),
        ];
    }

    /**
     * Épingle un message pour soi, ou retire l'épingle.
     *
     * @return array{0: bool, 1: string, 2: ?int} réussi, message, l'ami de la conversation
     */
    public static function epingler(int $moi, int $messageId, bool $epingler): array
    {
        $message = Database::one(
            'SELECT expediteur_id, destinataire_id FROM messages
              WHERE id = ? AND evenement IS NULL AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
            [$messageId, $moi, $moi]
        );
        $autre = $message === null ? null
            : ((int) $message['expediteur_id'] === $moi ? (int) $message['destinataire_id'] : (int) $message['expediteur_id']);
        if ($autre === null || !self::sontAmis($moi, $autre)) {
            return [false, 'Ce message est introuvable.', null];
        }
        if ($epingler) {
            Database::run('INSERT IGNORE INTO epingles (user_id, message_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$moi, $messageId]);
        } else {
            Database::run('DELETE FROM epingles WHERE user_id = ? AND message_id = ?', [$moi, $messageId]);
        }

        return [true, $epingler ? 'Message épinglé.' : 'Épingle retirée.', $autre];
    }

    /**
     * Les messages épinglés d'une conversation, de la dernière épingle à la
     * plus ancienne : qui l'a écrit, le début du texte, quand.
     *
     * @return list<array{id: int, auteur: string, extrait: string, quand: string}>
     */
    public static function epingles(int $moi, int $autre): array
    {
        $lignes = Database::all(
            'SELECT m.id, m.expediteur_id, m.texte AS r_texte, m.image_nom AS r_image, m.fichier_origine AS r_fichier, m.audio_nom AS r_audio,
                    m.supprime_le AS r_supprime, 0 AS r_masque, m.created_at
               FROM epingles e JOIN messages m ON m.id = e.message_id
              WHERE e.user_id = ?
                AND ((m.expediteur_id = ? AND m.destinataire_id = ? AND m.masque_expediteur = 0)
                  OR (m.expediteur_id = ? AND m.destinataire_id = ? AND m.masque_destinataire = 0))
              ORDER BY e.created_at DESC, m.id DESC',
            [$moi, $moi, $autre, $autre, $moi]
        );
        $pseudo = (string) (self::compte($autre)['pseudo'] ?? '');

        return array_map(static function (array $l) use ($moi, $pseudo): array {
            $moment = self::local((string) $l['created_at']);
            $jour = self::jour($moment);

            return [
                'id' => (int) $l['id'],
                'auteur' => (int) $l['expediteur_id'] === $moi ? 'Vous' : $pseudo,
                'extrait' => self::extrait($l),
                'quand' => ($jour === 'Aujourd’hui' ? '' : $jour . ' · ') . $moment->format('H:i'),
            ];
        }, $lignes);
    }

    /**
     * Les messages dont les réactions ont changé depuis tel instant, pour
     * qu'une page déjà ouverte les redessine.
     *
     * @return list<array{id: int, reactions: list<array>}>
     */
    public static function reactionsModifiees(int $moi, int $autre, string $depuis): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $depuis)) {
            return [];
        }
        $ids = array_map('intval', array_column(Database::all(
            'SELECT id FROM messages
              WHERE ((expediteur_id = ? AND destinataire_id = ? AND masque_expediteur = 0)
                  OR (expediteur_id = ? AND destinataire_id = ? AND masque_destinataire = 0))
                AND reactions_le >= ? - INTERVAL 2 SECOND
              ORDER BY id LIMIT 200',
            [$moi, $autre, $autre, $moi, $depuis]
        ), 'id'));
        $reactions = self::reactionsDe($ids, $moi, $autre);

        return array_map(static fn (int $id): array => ['id' => $id, 'reactions' => $reactions[$id] ?? []], $ids);
    }

    /** Note que la discussion avec cet ami est sous les yeux de la personne. */
    public static function regarder(int $moi, int $ami): void
    {
        Database::run(
            'INSERT INTO discussions_etat (user_id, ami_id, regarde_le) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE regarde_le = UTC_TIMESTAMP()',
            [$moi, $ami]
        );
    }

    /**
     * Met en file la notification d'un message, comme un rappel d'évènement.
     *
     * Chaque message a la sienne. Une seule exception : le destinataire a la
     * discussion ouverte, affichée et active à l'instant — il voit le message
     * arriver, une notification ne ferait que doubler l'écran.
     *
     * @return ?int la notification en file, ou null s'il n'y a personne à prévenir
     */
    /** A-t-on coupé les notifications de la conversation avec cet ami ? */
    public static function estMuette(int $moi, int $ami): bool
    {
        return self::coupure($moi, $ami) !== false;
    }

    /**
     * La coupure en cours : false s'il n'y en a pas, null si elle est sans fin,
     * ou sa fin (UTC). Passé ce moment, les notifications reviennent d'elles-mêmes.
     */
    public static function coupure(int $moi, int $ami): string|null|false
    {
        $l = Database::one(
            'SELECT muette_jusqua FROM discussions_etat WHERE user_id = ? AND ami_id = ? AND ' . Conversations::SQL_MUETTE,
            [$moi, $ami]);

        return $l === null ? false : $l['muette_jusqua'];
    }

    /** Coupe (sans fin, ou jusqu'à $jusqua), ou rétablit, les notifications de la conversation avec cet ami. */
    public static function rendreMuette(int $moi, int $ami, bool $muette, ?string $jusqua = null): void
    {
        Database::run(
            'INSERT INTO discussions_etat (user_id, ami_id, muette, muette_jusqua) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE muette = VALUES(muette), muette_jusqua = VALUES(muette_jusqua)',
            [$moi, $ami, $muette ? 1 : 0, $muette ? $jusqua : null]);
    }

    public static function notifier(int $expediteur, int $destinataire, string $texte, bool $avecImage = false, ?string $nomFichier = null,
                                    ?int $dureeVocal = null): ?int
    {
        $depuis = Database::valeur(
            'SELECT TIMESTAMPDIFF(SECOND, regarde_le, UTC_TIMESTAMP()) FROM discussions_etat WHERE user_id = ? AND ami_id = ?',
            [$destinataire, $expediteur]
        );
        if ($depuis !== null && (int) $depuis < self::PRESENCE_SECONDES) {
            return null;
        }
        // Conversation coupée : le message arrive, sans notification.
        if (self::estMuette($destinataire, $expediteur)) {
            return null;
        }

        $compte = self::compte($expediteur);
        $apercu = trim((string) preg_replace('/\s+/u', ' ', $texte));
        if ($avecImage) {
            $apercu = '📷 Photo' . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        if ($nomFichier !== null) {
            $apercu = '📎 ' . $nomFichier . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        if ($dureeVocal !== null) {
            $apercu = '🎤 Message vocal (' . self::duree($dureeVocal) . ')' . ($apercu === '' ? '' : ' · ' . $apercu);
        }
        $id = FileNotifications::ajouter($destinataire, 'message', [
            'title' => '💬 ' . ($compte['pseudo'] ?? 'Nouveau message'),
            'body' => mb_strimwidth($apercu, 0, self::APERCU_NOTIFICATION, '…'),
            'url' => url('amis/' . $expediteur),
            'tag' => 'message-' . $expediteur,
        ]);
        if ($id !== null) {
            Database::run(
                'INSERT INTO discussions_etat (user_id, ami_id, notifie_le) VALUES (?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE notifie_le = UTC_TIMESTAMP()',
                [$destinataire, $expediteur]
            );
        }

        return $id;
    }

    /** Met en file la notification d'une demande d'ami reçue. */
    public static function notifierDemande(int $demandeur, int $destinataire): ?int
    {
        $pseudo = (string) (self::compte($demandeur)['pseudo'] ?? 'Quelqu’un');

        return FileNotifications::ajouter($destinataire, 'demande', [
            'title' => '👋 Nouvelle demande d’ami',
            'body' => $pseudo . ' veut vous ajouter en ami.',
            'url' => url('amis'),
            'tag' => 'demande-' . $demandeur,
        ]);
    }

    /** Met en file, pour qui avait demandé, la notification d'une demande acceptée. */
    public static function notifierAcceptation(int $accepteur, int $demandeur): ?int
    {
        $pseudo = (string) (self::compte($accepteur)['pseudo'] ?? 'Votre ami');

        return FileNotifications::ajouter($demandeur, 'acceptation', [
            'title' => '🤝 Demande acceptée',
            'body' => $pseudo . ' a accepté votre demande : vous pouvez discuter.',
            'url' => url('amis/' . $accepteur),
            'tag' => 'acceptation-' . $accepteur,
        ]);
    }

    /**
     * Ce qu'on a échangé avec un ami : les photos et les fichiers encore
     * visibles pour soi, du plus récent au plus ancien.
     *
     * @return array{photos: list<array>, fichiers: list<array>, messages: int}
     */
    public static function partages(int $moi, int $autre): array
    {
        $visibles = '((expediteur_id = ? AND destinataire_id = ? AND masque_expediteur = 0)
                   OR (expediteur_id = ? AND destinataire_id = ? AND masque_destinataire = 0))';
        $parametres = [$moi, $autre, $autre, $moi];
        $date = static fn (string $utc): string => date_fr(self::local($utc)->format('Y-m-d H:i:s'), false);

        $photos = array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'url' => url('amis/images/' . (int) $l['id']),
            'moi' => (int) $l['expediteur_id'] === $moi,
            'date' => $date((string) $l['created_at']),
        ], Database::all(
            "SELECT id, expediteur_id, created_at FROM messages WHERE $visibles AND image_nom IS NOT NULL ORDER BY id DESC LIMIT 300",
            $parametres
        ));

        $fichiers = array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'url' => url('amis/fichiers/' . (int) $l['id']),
            'telecharger' => url('amis/fichiers/' . (int) $l['id'], ['telecharger' => 1]),
            'nom' => (string) $l['fichier_origine'],
            'taille' => taille_lisible((int) $l['fichier_taille']),
            'icone' => Fichiers::icone((string) $l['fichier_mime'], (string) $l['fichier_origine']),
            'moi' => (int) $l['expediteur_id'] === $moi,
            'date' => $date((string) $l['created_at']),
        ], Database::all(
            "SELECT id, expediteur_id, fichier_origine, fichier_mime, fichier_taille, created_at
               FROM messages WHERE $visibles AND fichier_nom IS NOT NULL ORDER BY id DESC LIMIT 300",
            $parametres
        ));

        $messages = (int) Database::valeur("SELECT COUNT(*) FROM messages WHERE $visibles AND supprime_le IS NULL AND evenement IS NULL", $parametres);

        return ['photos' => $photos, 'fichiers' => $fichiers, 'messages' => $messages];
    }

    /** Le dernier de mes messages que l'autre a lu, pour afficher « Vu ». */
    public static function vuJusqua(int $moi, int $autre): int
    {
        return (int) Database::valeur(
            'SELECT COALESCE(MAX(id), 0) FROM messages WHERE expediteur_id = ? AND destinataire_id = ? AND lu_le IS NOT NULL AND evenement IS NULL',
            [$moi, $autre]
        );
    }

    /**
     * Un message prêt à montrer, à l'heure de celui qui le lit. « $prefixe » :
     * l'adresse de ses pièces jointes — « amis » ou « groupes ».
     */
    public static function pourAffichage(array $message, int $moi, string $prefixe = 'amis'): array
    {
        $moment = self::local((string) $message['created_at']);

        return [
            'id' => (int) $message['id'],
            'moi' => (int) $message['expediteur_id'] === $moi,
            'texte' => (string) $message['texte'],
            'supprime' => ($message['supprime_le'] ?? null) !== null,
            'modifie' => ($message['modifie_le'] ?? null) !== null && ($message['supprime_le'] ?? null) === null,
            'reactions' => [],
            'epingle' => false,
            'partage' => Partages::carte($message['partage_type'] ?? null, isset($message['partage_id']) ? (int) $message['partage_id'] : null, $moi),
            'reponse' => ($message['r_expediteur'] ?? null) === null ? null : [
                'id' => (int) $message['reponse_a'],
                'auteur' => (int) $message['r_expediteur'] === $moi ? 'Vous'
                    : (string) (self::compte((int) $message['r_expediteur'])['pseudo'] ?? ''),
                'extrait' => self::extrait($message),
            ],
            'image' => ($message['image_nom'] ?? null) === null ? null : url($prefixe . '/images/' . (int) $message['id']),
            'largeur' => (int) ($message['image_largeur'] ?? 0),
            'hauteur' => (int) ($message['image_hauteur'] ?? 0),
            'vocal' => ($message['audio_nom'] ?? null) === null ? null : [
                'url' => url($prefixe . '/vocaux/' . (int) $message['id']),
                'duree' => (int) $message['audio_duree'],
                'duree_texte' => self::duree((int) $message['audio_duree']),
                'transcription' => isset($message['audio_transcription']) ? (string) $message['audio_transcription'] : null,
            ],
            'fichier' => ($message['fichier_nom'] ?? null) === null ? null : [
                'url' => url($prefixe . '/fichiers/' . (int) $message['id']),
                'telecharger' => url($prefixe . '/fichiers/' . (int) $message['id'], ['telecharger' => 1]),
                'nom' => (string) $message['fichier_origine'],
                'taille' => taille_lisible((int) $message['fichier_taille']),
                'icone' => Fichiers::icone((string) $message['fichier_mime'], (string) $message['fichier_origine']),
            ],
            'heure' => $moment->format('H:i'),
            'jour' => $moment->format('Y-m-d'),
            'jour_libelle' => self::jour($moment),
            'evenement' => ($message['evenement'] ?? null) === null ? null : self::texteEvenement(
                (string) $message['evenement'],
                (int) $message['expediteur_id'] === $moi,
                (string) (self::compte((int) $message['expediteur_id'])['pseudo'] ?? '')
            ),
        ];
    }

    /** Une date écrite en temps universel, dans le fuseau de la personne connectée. */
    public static function local(string $utc): DateTimeImmutable
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /** « Aujourd'hui », « Hier », ou la date. */
    public static function jour(DateTimeImmutable $moment): string
    {
        $aujourdhui = new DateTimeImmutable('today');
        $jour = $moment->setTime(0, 0);

        return match (true) {
            $jour == $aujourdhui => 'Aujourd’hui',
            $jour == $aujourdhui->modify('-1 day') => 'Hier',
            default => ucfirst(date_fr($moment->format('Y-m-d H:i:s'), false)),
        };
    }

    /** L'heure d'un dernier message, pour la liste : « 14:05 », « Hier », « 3 sept. ». */
    public static function quand(string $utc): string
    {
        $moment = self::local($utc);
        $jour = self::jour($moment);

        return $jour === 'Aujourd’hui' ? $moment->format('H:i') : $jour;
    }
}
