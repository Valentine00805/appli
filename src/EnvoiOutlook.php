<?php
declare(strict_types=1);

/**
 * Porter vers Outlook ce qui est né dans l'application.
 *
 * L'autre sens de la synchronisation. Il écrit, ce que le premier ne faisait
 * pas, et cela change tout : une lecture ratée ne coûte qu'une lecture, une
 * écriture ratée abîme l'agenda de quelqu'un.
 *
 * D'où le calendrier séparé. L'application crée le sien chez Microsoft —
 * « Mes Cours » — et n'écrit jamais ailleurs. On l'affiche ou on le masque
 * d'une case dans Outlook, le supprimer n'emporte que ce qui vient d'ici, et
 * une erreur de notre part ne peut pas atteindre l'agenda dont on se sert.
 *
 * Ce qui monte : les évènements créés dans l'application, et les échéances des
 * tâches, en journée entière. Ce qui est venu d'Outlook n'y retourne pas —
 * sans quoi chaque passage rendrait les évènements à leur expéditeur, et
 * l'agenda enflerait tout seul.
 */
final class EnvoiOutlook
{
    /** Le nom du calendrier créé chez Microsoft. */
    private const CALENDRIER = 'Mes Cours';

    /** La fenêtre envoyée, la même que celle qu'on lit. */
    private const AVANT = '-1 month';
    private const APRES = '+12 months';

    private const FUSEAU = 'Europe/Paris';

    /** Une échéance de tâche se reconnaît d'un coup d'œil dans l'agenda. */
    private const MARQUE_TACHE = '☑ ';

    /**
     * Envoie ce qui doit l'être, corrige ce qui a changé, retire ce qui a disparu.
     *
     * @return array{crees: int, majs: int, retires: int, inchanges: int}
     * @throws RuntimeException si le compte n'est pas relié, ou si Microsoft refuse
     */
    public static function pousser(int $userId): array
    {
        $calendrier = self::calendrierDEnvoi($userId);

        $fuseau = new DateTimeZone(self::FUSEAU);
        $maintenant = new DateTimeImmutable('now', $fuseau);
        $depuis = $maintenant->modify(self::AVANT)->setTime(0, 0);
        $jusqua = $maintenant->modify(self::APRES)->setTime(23, 59, 59);

        $aEnvoyer = self::aEnvoyer($userId, $depuis, $jusqua);
        $partis = self::partis($userId);

        $bilan = ['crees' => 0, 'majs' => 0, 'retires' => 0, 'inchanges' => 0];

        foreach ($aEnvoyer as $cle => $quoi) {
            $empreinte = md5(json_encode($quoi['corps'], JSON_THROW_ON_ERROR));
            $connu = $partis[$cle] ?? null;

            if ($connu === null) {
                self::creer($userId, $calendrier, $quoi, $empreinte);
                $bilan['crees']++;
                continue;
            }
            if ((string) $connu['empreinte'] === $empreinte) {
                $bilan['inchanges']++;
                continue;
            }

            self::modifier($userId, $quoi, $empreinte, (string) $connu['outlook_id']);
            $bilan['majs']++;
        }

        foreach ($partis as $cle => $connu) {
            if (isset($aEnvoyer[$cle])) {
                continue;
            }
            self::effacer($userId, (string) $connu['outlook_id']);
            Database::run('DELETE FROM outlook_envois WHERE user_id = ? AND id = ?',
                [$userId, (int) $connu['id']]);
            $bilan['retires']++;
        }

        Database::run('UPDATE outlook_comptes SET envoi_le = NOW() WHERE user_id = ?', [$userId]);

        return $bilan;
    }

    /** La date du dernier envoi, ou null s'il n'y en a jamais eu. */
    public static function derniereFois(int $userId): ?string
    {
        $quand = Database::valeur('SELECT envoi_le FROM outlook_comptes WHERE user_id = ?', [$userId]);

        return $quand === null ? null : (string) $quand;
    }

    /** Combien d'éléments de l'application vivent dans Outlook. */
    public static function combien(int $userId): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM outlook_envois WHERE user_id = ?', [$userId]);
    }

    /** L'identifiant du calendrier où l'application écrit, s'il existe déjà. */
    public static function calendrierConnu(int $userId): ?string
    {
        $id = Database::valeur(
            'SELECT calendrier_envoi_id FROM outlook_comptes WHERE user_id = ?', [$userId]);

        return ($id === null || (string) $id === '') ? null : (string) $id;
    }

    /**
     * Retire d'Outlook tout ce que l'application y avait mis.
     *
     * Le calendrier lui-même reste : le supprimer relève de l'utilisateur, pas
     * de nous — il a pu y ajouter autre chose à la main.
     *
     * @return int  combien ont été retirés
     */
    public static function toutRetirer(int $userId): int
    {
        $retires = 0;
        foreach (Database::all('SELECT id, outlook_id FROM outlook_envois WHERE user_id = ?',
            [$userId]) as $ligne) {
            self::effacer($userId, (string) $ligne['outlook_id']);
            Database::run('DELETE FROM outlook_envois WHERE user_id = ? AND id = ?',
                [$userId, (int) $ligne['id']]);
            $retires++;
        }

        Database::run('UPDATE outlook_comptes SET envoi_le = NULL WHERE user_id = ?', [$userId]);

        return $retires;
    }

    /* --- Le calendrier de destination ------------------------------------- */

    /**
     * Le calendrier « Mes Cours », créé au besoin.
     *
     * On ne le recrée pas s'il porte déjà ce nom chez Microsoft : quelqu'un
     * qui délie puis relie son compte retrouverait sinon deux calendriers
     * identiques, et ne saurait pas lequel jeter.
     *
     * @throws RuntimeException si Microsoft refuse de le donner ou de le créer
     */
    private static function calendrierDEnvoi(int $userId): string
    {
        $connu = self::calendrierConnu($userId);
        if ($connu !== null) {
            return $connu;
        }

        $liste = Outlook::appeler($userId, 'GET', '/me/calendars?$select=id,name&$top=100');
        if ($liste['code'] < 400) {
            foreach (($liste['corps']['value'] ?? []) as $cal) {
                if ((string) ($cal['name'] ?? '') === self::CALENDRIER && isset($cal['id'])) {
                    return self::retenirLeCalendrier($userId, (string) $cal['id']);
                }
            }
        }

        $cree = Outlook::appeler($userId, 'POST', '/me/calendars', ['name' => self::CALENDRIER]);
        if ($cree['code'] >= 400 || !isset($cree['corps']['id'])) {
            $dit = (string) ($cree['corps']['error']['message'] ?? '');

            throw new RuntimeException('Microsoft a refusé de créer le calendrier « '
                . self::CALENDRIER . ' »' . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
        }

        return self::retenirLeCalendrier($userId, (string) $cree['corps']['id']);
    }

    private static function retenirLeCalendrier(int $userId, string $id): string
    {
        Database::run(
            'UPDATE outlook_comptes SET calendrier_envoi_id = ?, calendrier_envoi_nom = ?
              WHERE user_id = ?',
            [$id, self::CALENDRIER, $userId]
        );

        return $id;
    }

    /* --- Ce qui doit monter ----------------------------------------------- */

    /**
     * Tout ce qui, dans l'application, a sa place dans l'agenda.
     *
     * @return array<string, array{corps: array}>  rangé par « sorte:id »
     */
    private static function aEnvoyer(int $userId, DateTimeImmutable $depuis, DateTimeImmutable $jusqua): array
    {
        $tout = [];

        /*
         * Les évènements, sauf ceux qui viennent d'Outlook : les renvoyer
         * reviendrait à les rendre à leur expéditeur, en double.
         */
        foreach (Database::all(
            'SELECT e.id, e.titre, e.description, e.lieu, e.debut, e.fin, e.journee_entiere
               FROM evenements e
               LEFT JOIN outlook_liens l ON l.evenement_id = e.id AND l.user_id = e.user_id
              WHERE e.user_id = ? AND l.id IS NULL AND e.debut BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s')]
        ) as $evt) {
            $tout['evenement:' . (int) $evt['id']] = [
                'sorte'     => 'evenement',
                'source_id' => (int) $evt['id'],
                'corps'     => self::corpsDUnEvenement($evt),
            ];
        }

        /*
         * Les échéances de tâches, en journée entière. Une tâche faite quitte
         * l'agenda : elle n'a plus rien à y rappeler.
         */
        foreach (Database::all(
            'SELECT t.id, t.titre, t.note, t.echeance, l.nom AS liste_nom
               FROM taches t
               JOIN listes_taches l ON l.id = t.liste_id
              WHERE t.user_id = ? AND t.faite = 0 AND t.echeance BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d'), $jusqua->format('Y-m-d')]
        ) as $tache) {
            $tout['tache:' . (int) $tache['id']] = [
                'sorte'     => 'tache',
                'source_id' => (int) $tache['id'],
                'corps'     => self::corpsDUneTache($tache),
            ];
        }

        return $tout;
    }

    /** Un évènement de l'application dans les termes de Microsoft. */
    private static function corpsDUnEvenement(array $evt): array
    {
        $journee = (int) $evt['journee_entiere'] === 1;
        $debut = new DateTimeImmutable((string) $evt['debut']);
        $fin = new DateTimeImmutable((string) $evt['fin']);

        if ($journee) {
            /*
             * L'application termine une journée entière à 23:59:59 ; Microsoft
             * la borne par le lendemain à minuit. On avance d'une seconde puis
             * on coupe à la date : c'est l'inverse exact de ce que fait la
             * lecture, et les deux doivent rester d'accord.
             */
            $debut = $debut->setTime(0, 0);
            $fin = $fin->modify('+1 second')->setTime(0, 0);
            if ($fin <= $debut) {
                $fin = $debut->modify('+1 day');
            }
        }

        return self::corps(
            (string) $evt['titre'],
            (string) ($evt['description'] ?? ''),
            (string) ($evt['lieu'] ?? ''),
            $debut,
            $fin,
            $journee
        );
    }

    /** Une échéance de tâche : une journée entière, et de quoi savoir d'où elle vient. */
    private static function corpsDUneTache(array $tache): array
    {
        $jour = new DateTimeImmutable((string) $tache['echeance']);
        $note = trim((string) ($tache['note'] ?? ''));

        return self::corps(
            self::MARQUE_TACHE . (string) $tache['titre'],
            'Échéance d’une tâche de la liste « ' . (string) $tache['liste_nom'] . ' ».'
                . ($note === '' ? '' : "\n\n" . $note),
            '',
            $jour->setTime(0, 0),
            $jour->modify('+1 day')->setTime(0, 0),
            true
        );
    }

    /** La forme qu'attend Microsoft, la même pour tout ce qu'on envoie. */
    private static function corps(
        string $titre,
        string $texte,
        string $lieu,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        bool $journee
    ): array {
        $corps = [
            'subject'  => mb_substr($titre === '' ? '(sans titre)' : $titre, 0, 250),
            'body'     => ['contentType' => 'text', 'content' => mb_substr($texte, 0, 4000)],
            'isAllDay' => $journee,
            'start'    => ['dateTime' => $debut->format('Y-m-d\TH:i:s'), 'timeZone' => self::FUSEAU],
            'end'      => ['dateTime' => $fin->format('Y-m-d\TH:i:s'), 'timeZone' => self::FUSEAU],
        ];
        if ($lieu !== '') {
            $corps['location'] = ['displayName' => mb_substr($lieu, 0, 250)];
        }

        return $corps;
    }

    /* --- Écrire chez Microsoft -------------------------------------------- */

    /** Ce qui est déjà parti, rangé par « sorte:id ». */
    private static function partis(int $userId): array
    {
        $par = [];
        foreach (Database::all(
            'SELECT id, sorte, source_id, outlook_id, empreinte FROM outlook_envois WHERE user_id = ?',
            [$userId]
        ) as $ligne) {
            $par[$ligne['sorte'] . ':' . (int) $ligne['source_id']] = $ligne;
        }

        return $par;
    }

    private static function creer(int $userId, string $calendrier, array $quoi, string $empreinte): void
    {
        $reponse = Outlook::appeler(
            $userId,
            'POST',
            '/me/calendars/' . rawurlencode($calendrier) . '/events',
            $quoi['corps']
        );
        self::verifier($reponse, 'créer un évènement');

        Database::run(
            'INSERT INTO outlook_envois (user_id, sorte, source_id, outlook_id, empreinte)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE outlook_id = VALUES(outlook_id), empreinte = VALUES(empreinte)',
            [$userId, $quoi['sorte'], $quoi['source_id'],
             (string) ($reponse['corps']['id'] ?? ''), $empreinte]
        );
    }

    private static function modifier(int $userId, array $quoi, string $empreinte, string $outlookId): void
    {
        $reponse = Outlook::appeler(
            $userId, 'PATCH', '/me/events/' . rawurlencode($outlookId), $quoi['corps']);

        /*
         * Introuvable : quelqu'un l'a supprimé dans Outlook. On ne s'en offusque
         * pas — on oublie le lien, et le prochain passage le recréera.
         */
        if ($reponse['code'] === 404) {
            Database::run('DELETE FROM outlook_envois WHERE user_id = ? AND outlook_id = ?',
                [$userId, $outlookId]);

            return;
        }
        self::verifier($reponse, 'mettre à jour un évènement');

        Database::run(
            'UPDATE outlook_envois SET empreinte = ?, maj_le = NOW()
              WHERE user_id = ? AND outlook_id = ?',
            [$empreinte, $userId, $outlookId]
        );
    }

    /** Efface là-bas, sans s'émouvoir de ce qui n'y est déjà plus. */
    private static function effacer(int $userId, string $outlookId): void
    {
        $reponse = Outlook::appeler($userId, 'DELETE', '/me/events/' . rawurlencode($outlookId));
        if ($reponse['code'] === 404 || $reponse['code'] === 410) {
            return;
        }
        self::verifier($reponse, 'supprimer un évènement');
    }

    /** @throws RuntimeException si Microsoft a refusé */
    private static function verifier(array $reponse, string $quoi): void
    {
        if ($reponse['code'] < 400) {
            return;
        }

        $dit = (string) ($reponse['corps']['error']['message'] ?? '');

        throw new RuntimeException('Microsoft a refusé de ' . $quoi
            . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
    }
}
