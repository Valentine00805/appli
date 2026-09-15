<?php
declare(strict_types=1);

/**
 * La file des notifications qui ne sont pas des rappels : un message reçu,
 * une demande d'ami, une demande acceptée.
 *
 * Elles suivent le chemin des rappels d'évènements : écrites d'abord, envoyées
 * ensuite. L'envoi est tenté aussitôt ; s'il échoue — pas de réseau, service
 * indisponible — la notification reste en file et repart au passage suivant de
 * l'adresse d'envoi (la tâche planifiée, chaque minute) ou de la page ouverte.
 * Passé une heure, une notification qui n'a pas pu partir est abandonnée :
 * annoncer un message de la veille n'apprendrait plus rien.
 */
final class FileNotifications
{
    /** Au-delà, une notification en attente n'est plus envoyée. */
    private const DUREE_MINUTES = 60;

    /** Un envoi commencé depuis plus longtemps est tenu pour interrompu. */
    private const ENVOI_BLOQUE_MINUTES = 2;

    /** Tentatives au plus, pour ne pas insister indéfiniment. */
    private const TENTATIVES_MAX = 30;

    /**
     * Met une notification en file pour un compte — seulement s'il a un
     * appareil abonné : sans appareil, il n'y a personne à prévenir.
     *
     * @param array{title: string, body: string, url: string, tag: string} $message
     * @return ?int l'identifiant en file, ou null si le compte n'a pas d'appareil
     */
    public static function ajouter(int $userId, string $nature, array $message): ?int
    {
        $appareils = (int) Database::valeur('SELECT COUNT(*) FROM abonnements_push WHERE user_id = ?', [$userId]);
        if ($appareils === 0) {
            return null;
        }

        Database::run(
            'INSERT INTO notifications_file (user_id, nature, titre, corps, adresse, etiquette, cree_le)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$userId, mb_substr($nature, 0, 20), mb_substr((string) $message['title'], 0, 190),
             mb_strimwidth((string) $message['body'], 0, 500, '…'), mb_substr((string) $message['url'], 0, 255),
             mb_substr((string) $message['tag'], 0, 64)]
        );

        return Database::dernierId();
    }

    /**
     * Envoie une notification en file, si personne d'autre n'est déjà en train
     * de le faire.
     *
     * @return string « envoyee », « retenue » (à retenter), « abandonnee » ou « deja »
     */
    public static function envoyer(int $id): string
    {
        // On la réserve : un passage simultané de la tâche planifiée ne l'enverra pas une seconde fois.
        $reservee = Database::run(
            'UPDATE notifications_file SET envoi_en_cours = UTC_TIMESTAMP(), tentatives = tentatives + 1
              WHERE id = ? AND envoye_le IS NULL
                AND (envoi_en_cours IS NULL OR envoi_en_cours < UTC_TIMESTAMP() - INTERVAL ' . self::ENVOI_BLOQUE_MINUTES . ' MINUTE)',
            [$id]
        )->rowCount();
        if ($reservee === 0) {
            return 'deja';
        }

        $ligne = Database::one('SELECT * FROM notifications_file WHERE id = ?', [$id]);
        if ($ligne === null) {
            return 'deja';
        }

        $bilan = Rappels::envoyerAuCompte((int) $ligne['user_id'], [
            'title' => (string) $ligne['titre'],
            'body' => (string) $ligne['corps'],
            'url' => (string) $ligne['adresse'],
            'tag' => (string) $ligne['etiquette'],
        ]);

        // Partie vers au moins un appareil, ou plus aucun appareil à joindre : c'est fini.
        if ($bilan['envoyes'] > 0 || $bilan['echecs'] === 0) {
            Database::run('UPDATE notifications_file SET envoye_le = UTC_TIMESTAMP(), envoi_en_cours = NULL WHERE id = ?', [$id]);
            return $bilan['envoyes'] > 0 ? 'envoyee' : 'abandonnee';
        }

        Database::run('UPDATE notifications_file SET envoi_en_cours = NULL WHERE id = ?', [$id]);

        return 'retenue';
    }

    /**
     * Retente ce qui attend encore, pour tous les comptes ou pour un seul, et
     * fait le ménage des notifications trop anciennes.
     *
     * @return array{envoyees: int, retenues: int}
     */
    public static function envoyerEnAttente(?int $seulement = null): array
    {
        $bilan = ['envoyees' => 0, 'retenues' => 0];

        $attente = Database::all(
            'SELECT id FROM notifications_file
              WHERE envoye_le IS NULL AND tentatives < ' . self::TENTATIVES_MAX . '
                AND cree_le >= UTC_TIMESTAMP() - INTERVAL ' . self::DUREE_MINUTES . ' MINUTE'
            . ($seulement === null ? '' : ' AND user_id = ?') . '
              ORDER BY id LIMIT 200',
            $seulement === null ? [] : [$seulement]
        );
        foreach ($attente as $ligne) {
            match (self::envoyer((int) $ligne['id'])) {
                'envoyee' => $bilan['envoyees']++,
                'retenue' => $bilan['retenues']++,
                default => null,
            };
        }

        if ($seulement === null) {
            Database::run('DELETE FROM notifications_file WHERE cree_le < UTC_TIMESTAMP() - INTERVAL 2 DAY');
        }

        return $bilan;
    }
}
