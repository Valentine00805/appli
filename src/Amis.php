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

    /** Le dossier des images des discussions, à côté des pièces jointes des cours. */
    public static function dossierImages(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'messages';
    }

    /** Le compte d'un autre, tel qu'on peut le voir : son identifiant et son pseudo. */
    public static function compte(int $id): ?array
    {
        return Database::one("SELECT id, pseudo FROM users WHERE id = ? AND pseudo IS NOT NULL AND pseudo <> ''", [$id]);
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
            "SELECT u.id, u.pseudo, a.statut, a.demandeur_id
               FROM users u
               LEFT JOIN amities a ON a.petit_id = LEAST(u.id, ?) AND a.grand_id = GREATEST(u.id, ?)
              WHERE u.id <> ? AND u.pseudo IS NOT NULL AND u.pseudo LIKE ?
              ORDER BY (u.pseudo = ?) DESC, CHAR_LENGTH(u.pseudo), u.pseudo
              LIMIT 20",
            [$moi, $moi, $moi, $motif, $recherche]
        );

        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'],
            'pseudo' => (string) $l['pseudo'],
            'etat' => match (true) {
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
            'SELECT id, expediteur_id, texte, image_nom, fichier_origine, supprime_le, created_at FROM messages WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
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
        );
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
        return Database::run(
            "UPDATE amities SET statut = 'acceptee', acceptee_le = UTC_TIMESTAMP()
              WHERE demandeur_id = ? AND destinataire_id = ? AND statut = 'attente'",
            [$autre, $moi]
        )->rowCount() > 0;
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
     * @return array{0: ?int, 1: ?string} l'identifiant du message, ou la raison du refus
     */
    public static function ecrire(int $moi, int $autre, string $texte, ?array $image = null, ?array $fichier = null): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        $avecImage = $image !== null && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avecFichier = $fichier !== null && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($texte === '' && !$avecImage && !$avecFichier) {
            return [null, 'Le message est vide.'];
        }
        if ($avecImage && $avecFichier) {
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

        try {
            Database::run(
                'INSERT INTO messages (expediteur_id, destinataire_id, texte, image_nom, image_mime, image_largeur, image_hauteur,
                                       fichier_nom, fichier_origine, fichier_mime, fichier_taille, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$moi, $autre, $texte, $rangee['nom'] ?? null, $rangee['mime'] ?? null, $rangee['largeur'] ?? null, $rangee['hauteur'] ?? null,
                 $joint['nom'] ?? null, $joint['origine'] ?? null, $joint['mime'] ?? null, $joint['taille'] ?? null]
            );
        } catch (Throwable $e) {
            foreach ([$rangee, $joint] as $range) {
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
    public static function fil(int $moi, int $autre, int $apres = 0): array
    {
        Database::run(
            'UPDATE messages SET lu_le = UTC_TIMESTAMP() WHERE expediteur_id = ? AND destinataire_id = ? AND lu_le IS NULL',
            [$autre, $moi]
        );

        $messages = Database::all(
            'SELECT id, expediteur_id, texte, image_nom, image_largeur, image_hauteur, fichier_nom, fichier_origine, fichier_mime, fichier_taille,
                    created_at, lu_le, supprime_le FROM messages
              WHERE ((expediteur_id = ? AND destinataire_id = ? AND masque_expediteur = 0)
                  OR (expediteur_id = ? AND destinataire_id = ? AND masque_destinataire = 0))
                AND id > ?
              ORDER BY id DESC LIMIT ' . self::FIL_MAX,
            [$moi, $autre, $autre, $moi, $apres]
        );

        return array_map(static fn (array $m): array => self::pourAffichage($m, $moi), array_reverse($messages));
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
            'SELECT * FROM messages WHERE id = ? AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
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
        foreach ([$message['image_nom'] ?? null, $message['fichier_nom'] ?? null] as $nom) {
            if (is_string($nom) && preg_match('/^[0-9a-f]{32}\.[a-z0-9]{1,8}$/', $nom)) {
                @unlink(self::dossierImages() . DIRECTORY_SEPARATOR . $nom);
            }
        }
        Database::run(
            "UPDATE messages SET texte = '', image_nom = NULL, image_mime = NULL, image_largeur = NULL, image_hauteur = NULL,
                    fichier_nom = NULL, fichier_origine = NULL, fichier_mime = NULL, fichier_taille = NULL
              WHERE id = ?",
            [(int) $message['id']]
        );
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
    public static function notifier(int $expediteur, int $destinataire, string $texte, bool $avecImage = false, ?string $nomFichier = null): ?int
    {
        $depuis = Database::valeur(
            'SELECT TIMESTAMPDIFF(SECOND, regarde_le, UTC_TIMESTAMP()) FROM discussions_etat WHERE user_id = ? AND ami_id = ?',
            [$destinataire, $expediteur]
        );
        if ($depuis !== null && (int) $depuis < self::PRESENCE_SECONDES) {
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

    /** Le dernier de mes messages que l'autre a lu, pour afficher « Vu ». */
    public static function vuJusqua(int $moi, int $autre): int
    {
        return (int) Database::valeur(
            'SELECT COALESCE(MAX(id), 0) FROM messages WHERE expediteur_id = ? AND destinataire_id = ? AND lu_le IS NOT NULL',
            [$moi, $autre]
        );
    }

    /** Un message prêt à montrer, à l'heure de celui qui le lit. */
    public static function pourAffichage(array $message, int $moi): array
    {
        $moment = self::local((string) $message['created_at']);

        return [
            'id' => (int) $message['id'],
            'moi' => (int) $message['expediteur_id'] === $moi,
            'texte' => (string) $message['texte'],
            'supprime' => ($message['supprime_le'] ?? null) !== null,
            'image' => ($message['image_nom'] ?? null) === null ? null : url('amis/images/' . (int) $message['id']),
            'largeur' => (int) ($message['image_largeur'] ?? 0),
            'hauteur' => (int) ($message['image_hauteur'] ?? 0),
            'fichier' => ($message['fichier_nom'] ?? null) === null ? null : [
                'url' => url('amis/fichiers/' . (int) $message['id']),
                'telecharger' => url('amis/fichiers/' . (int) $message['id'], ['telecharger' => 1]),
                'nom' => (string) $message['fichier_origine'],
                'taille' => taille_lisible((int) $message['fichier_taille']),
                'icone' => Fichiers::icone((string) $message['fichier_mime'], (string) $message['fichier_origine']),
            ],
            'heure' => $moment->format('H:i'),
            'jour' => $moment->format('Y-m-d'),
            'jour_libelle' => self::jour($moment),
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
