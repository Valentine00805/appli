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

    /** Une discussion regardée il y a moins de tant de secondes est encore sous les yeux. */
    public const PRESENCE_SECONDES = 15;

    /** Dans ce délai après une notification, la suivante remplace la première sans faire vibrer. */
    public const RELANCE_SECONDES = 30;

    /** Longueur du texte repris dans une notification. */
    public const APERCU_NOTIFICATION = 140;

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
                      WHERE m.expediteur_id = u.id AND m.destinataire_id = ? AND m.lu_le IS NULL) AS non_lus,
                    (SELECT m.id FROM messages m
                      WHERE (m.expediteur_id = u.id AND m.destinataire_id = ?) OR (m.expediteur_id = ? AND m.destinataire_id = u.id)
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
            'SELECT id, expediteur_id, texte, created_at FROM messages WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
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
                     WHERE m.destinataire_id = ? AND m.lu_le IS NULL)
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
     * Écrit à un ami.
     *
     * @return array{0: ?int, 1: ?string} l'identifiant du message, ou la raison du refus
     */
    public static function ecrire(int $moi, int $autre, string $texte): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        if ($texte === '') {
            return [null, 'Le message est vide.'];
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

        Database::run(
            'INSERT INTO messages (expediteur_id, destinataire_id, texte, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$moi, $autre, $texte]
        );

        return [Database::dernierId(), null];
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
            'SELECT id, expediteur_id, texte, created_at, lu_le FROM messages
              WHERE ((expediteur_id = ? AND destinataire_id = ?) OR (expediteur_id = ? AND destinataire_id = ?))
                AND id > ?
              ORDER BY id DESC LIMIT ' . self::FIL_MAX,
            [$moi, $autre, $autre, $moi, $apres]
        );

        return array_map(static fn (array $m): array => self::pourAffichage($m, $moi), array_reverse($messages));
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
     * Prévient le destinataire d'un message sur ses appareils abonnés.
     *
     * Pas s'il a la discussion ouverte sous les yeux : il voit le message
     * arriver. Et plusieurs messages d'affilée remplacent la même notification
     * — une par ami — sans refaire vibrer le téléphone à chaque fois.
     *
     * @return string « envoyee », « silencieuse », « regarde » ou « aucun_appareil »
     */
    public static function notifier(int $expediteur, int $destinataire, string $texte): string
    {
        $appareils = (int) Database::valeur('SELECT COUNT(*) FROM abonnements_push WHERE user_id = ?', [$destinataire]);
        if ($appareils === 0) {
            return 'aucun_appareil';
        }

        $etat = Database::one(
            'SELECT TIMESTAMPDIFF(SECOND, regarde_le, UTC_TIMESTAMP()) AS depuis_regarde,
                    TIMESTAMPDIFF(SECOND, notifie_le, UTC_TIMESTAMP()) AS depuis_notifie
               FROM discussions_etat WHERE user_id = ? AND ami_id = ?',
            [$destinataire, $expediteur]
        );
        if ($etat !== null && $etat['depuis_regarde'] !== null && (int) $etat['depuis_regarde'] < self::PRESENCE_SECONDES) {
            return 'regarde';
        }
        $silencieuse = $etat !== null && $etat['depuis_notifie'] !== null
            && (int) $etat['depuis_notifie'] < self::RELANCE_SECONDES;

        Database::run(
            'INSERT INTO discussions_etat (user_id, ami_id, notifie_le) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE notifie_le = UTC_TIMESTAMP()',
            [$destinataire, $expediteur]
        );

        $compte = self::compte($expediteur);
        $apercu = trim((string) preg_replace('/\s+/u', ' ', $texte));
        Rappels::envoyerAuCompte($destinataire, [
            'title' => '💬 ' . ($compte['pseudo'] ?? 'Nouveau message'),
            'body' => mb_strimwidth($apercu, 0, self::APERCU_NOTIFICATION, '…'),
            'url' => url('amis/' . $expediteur),
            'tag' => 'message-' . $expediteur,
            'silencieux' => $silencieuse,
        ]);

        return $silencieuse ? 'silencieuse' : 'envoyee';
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
