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
     * Les sortes de notifications qu'on choisit de recevoir ou non, et les
     * natures qu'elles couvrent — celles des rappels comme celles de la file.
     */
    public const CATEGORIES = [
        'calendrier' => ['nom' => 'Calendrier', 'icone' => '📅', 'natures' => ['evenement'],
            'aide' => 'Les rappels de vos évènements, et des échéances des travaux de groupe.'],
        'taches'     => ['nom' => 'Tâches', 'icone' => '✅', 'natures' => ['tache', 'liste'],
            'aide' => 'À 8 h, le jour de l’échéance d’une tâche pas encore faite.'],
        'messages'   => ['nom' => 'Messages de mes amis', 'icone' => '💬', 'natures' => ['message'],
            'aide' => 'Dès qu’ils arrivent, à deux ou en groupe.'],
        'reactions'  => ['nom' => 'Réactions à mes messages', 'icone' => '😊', 'natures' => ['reaction'],
            'aide' => 'Quand un ami réagit d’un emoji à ce que vous avez écrit.'],
        'demandes'   => ['nom' => 'Demandes d’ami', 'icone' => '🤝', 'natures' => ['demande', 'acceptation'],
            'aide' => 'Les demandes reçues, et vos demandes acceptées.'],
        'groupes'    => ['nom' => 'Discussions de groupe', 'icone' => '👥', 'natures' => ['groupe'],
            'aide' => 'Une invitation dans un groupe, ou quand on vous y ajoute.'],
        'partages'   => ['nom' => 'Partages', 'icone' => '📤', 'natures' => ['partage', 'commentaire'],
            'aide' => 'Un cours, un fichier ou un évènement qu’on vous partage, et leurs commentaires.'],
        'travaux'    => ['nom' => 'Travaux de groupe', 'icone' => '🧩', 'natures' => ['projet'],
            'aide' => 'Une invitation, une tâche qu’on vous confie, une nouvelle échéance.'],
        'journal'    => ['nom' => 'Journal d’alternance', 'icone' => '📓', 'natures' => ['journal'],
            'aide' => 'Le rappel d’écrire la semaine de votre journal des missions.'],
    ];

    /** La sorte d'une nature (« message » → « messages »), ou null si elle n'en a pas. */
    public static function categorieDe(string $nature): ?string
    {
        foreach (self::CATEGORIES as $cle => $categorie) {
            if (in_array($nature, $categorie['natures'], true)) {
                return $cle;
            }
        }

        return null;
    }

    /** Pour combien de temps couper : une heure, une nuit, un jour… (en minutes). */
    public const DUREES = [
        '1h' => ['nom' => '1 heure',   'minutes' => 60],
        '8h' => ['nom' => '8 heures',  'minutes' => 480],
        '1j' => ['nom' => '1 jour',    'minutes' => 1440],
        '3j' => ['nom' => '3 jours',   'minutes' => 4320],
        '7j' => ['nom' => '1 semaine', 'minutes' => 10080],
    ];

    /** La fin d'une coupure (en UTC) pour cette durée, ou null : jusqu'à ce qu'on la lève. */
    public static function finDans(?string $duree): ?string
    {
        if ($duree === null || !isset(self::DUREES[$duree])) {
            return null;
        }

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::DUREES[$duree]['minutes'] . ' minutes')->format('Y-m-d H:i:s');
    }

    /** « Coupées jusqu'à demain 18:30 », ou sans fin. */
    public static function texteCoupure(?string $jusquaUtc): string
    {
        if ($jusquaUtc === null) {
            return 'Coupées : rien ne vous prévient, jusqu’à ce que vous recochiez.';
        }
        $fin = Amis::local($jusquaUtc);
        $aujourdhui = new DateTimeImmutable('today');
        $quand = match ($fin->format('Y-m-d')) {
            $aujourdhui->format('Y-m-d') => 'jusqu’à ' . $fin->format('H:i'),
            $aujourdhui->modify('+1 day')->format('Y-m-d') => 'jusqu’à demain ' . $fin->format('H:i'),
            default => 'jusqu’au ' . date_fr($fin->format('Y-m-d H:i:s'), false) . ', ' . $fin->format('H:i'),
        };

        return 'Coupées ' . $quand . ' — elles reviendront toutes seules.';
    }

    /**
     * Les sortes coupées, et la fin de chacune (en UTC ; null : sans fin). Une
     * coupure passée n'y est plus : la sorte est revenue.
     *
     * @return array<string, ?string>
     */
    public static function coupures(int $userId): array
    {
        $valeur = (string) Database::valeur('SELECT notifications_coupees FROM users WHERE id = ?', [$userId]);
        $maintenant = gmdate('Y-m-d H:i:s');
        $coupures = [];
        foreach (array_filter(explode(',', $valeur)) as $morceau) {
            [$cle, $fin] = array_pad(explode('@', $morceau, 2), 2, null);
            if (!isset(self::CATEGORIES[$cle])) {
                continue;
            }
            $fin = $fin === null ? null : DateTimeImmutable::createFromFormat('YmdHis', $fin, new DateTimeZone('UTC'));
            if ($fin === false) {
                continue;
            }
            $finTexte = $fin?->format('Y-m-d H:i:s');
            if ($finTexte !== null && $finTexte <= $maintenant) {
                continue;
            }
            $coupures[$cle] = $finTexte;
        }

        return $coupures;
    }

    /** @return list<string> les sortes que le compte a coupées, en ce moment */
    public static function coupees(int $userId): array
    {
        return array_keys(self::coupures($userId));
    }


    /** Le compte a-t-il coupé les notifications de cette nature ? */
    public static function estCoupee(int $userId, string $nature): bool
    {
        $categorie = self::categorieDe($nature);

        return $categorie !== null && in_array($categorie, self::coupees($userId), true);
    }

    /**
     * Enregistre les sortes cochées ; celles qui ne le sont pas sont coupées,
     * chacune pour la durée choisie à côté (« toujours », « 1h », « 1j »…) —
     * « garder » laisse la coupure en cours comme elle est.
     *
     * @param list<string> $cochees
     * @param array<string, string> $durees
     * @return list<string> les sortes coupées
     */
    public static function regler(int $userId, array $cochees, array $durees = []): array
    {
        $avant = self::coupures($userId);
        $morceaux = [];
        foreach (array_diff(array_keys(self::CATEGORIES), $cochees) as $cle) {
            $duree = (string) ($durees[$cle] ?? 'toujours');
            $fin = $duree === 'garder' && array_key_exists($cle, $avant) ? $avant[$cle] : self::finDans($duree);
            $morceaux[$cle] = $cle . ($fin === null ? '' : '@' . str_replace(['-', ' ', ':'], '', $fin));
        }
        Database::run('UPDATE users SET notifications_coupees = ? WHERE id = ?', [implode(',', $morceaux), $userId]);

        return array_keys($morceaux);
    }

    /**
     * Met une notification en file pour un compte — seulement s'il a un
     * appareil abonné : sans appareil, il n'y a personne à prévenir.
     *
     * @param array{title: string, body: string, url: string, tag: string} $message
     * @return ?int l'identifiant en file, ou null si le compte n'a pas d'appareil,
     *              ou s'il a choisi de ne pas recevoir cette sorte de notification
     */
    public static function ajouter(int $userId, string $nature, array $message): ?int
    {
        $appareils = (int) Database::valeur('SELECT COUNT(*) FROM abonnements_push WHERE user_id = ?', [$userId]);
        if ($appareils === 0) {
            return null;
        }
        // Une sorte de notification qu'on a choisi de ne pas recevoir ne part pas.
        if (self::estCoupee($userId, $nature)) {
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
