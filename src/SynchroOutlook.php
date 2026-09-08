<?php
declare(strict_types=1);

/**
 * Faire venir dans l'application les évènements d'un agenda Outlook.
 *
 * Le sens est unique pour l'instant : d'Outlook vers ici. Ce qui vient de
 * là-bas y est tenu pour vrai — une modification faite dans l'application sur
 * un évènement importé sera reprise à la synchronisation suivante. C'est dit
 * à l'écran plutôt que deviné.
 *
 * On demande à Microsoft une *vue de calendrier* plutôt que la liste brute des
 * évènements : elle déplie les séries récurrentes en occurrences, chacune avec
 * sa date. C'est ce qu'il faut pour un calendrier qu'on regarde, et cela évite
 * d'avoir à interpréter soi-même des règles de récurrence.
 *
 * En contrepartie, une vue porte sur une période. Ce qui en sort n'est pas
 * effacé de l'application : seul disparaît d'ici ce qui a disparu de là-bas
 * *à l'intérieur* de la fenêtre regardée.
 */
final class SynchroOutlook
{
    /** La fenêtre regardée, autour d'aujourd'hui. */
    private const AVANT = '-1 month';
    private const APRES = '+12 months';

    /** Ce qu'on lit de chaque évènement : rien de plus que ce qui s'affiche. */
    private const CHAMPS = 'id,subject,bodyPreview,location,start,end,isAllDay,isCancelled';

    private const PAR_PAGE = 100;

    /** Une garde : au-delà, quelque chose ne tourne pas rond côté Microsoft. */
    private const PAGES_MAX = 60;

    private const FUSEAU = 'Europe/Paris';

    /**
     * Le repos entre deux lectures automatiques, en secondes.
     *
     * Cinq minutes. Un quart d'heure paraissait raisonnable jusqu'à ce qu'on
     * ajoute un rendez-vous et qu'on attende devant l'écran : ce n'est pas la
     * charge du serveur qui fixe ce délai, c'est la patience de qui regarde.
     * Pour ne pas attendre du tout, le bouton est là.
     */
    private const REPOS = 300;

    /**
     * Va chercher les évènements et met l'application à jour.
     *
     * @return array{ajoutes: int, modifies: int, retires: int, inchanges: int}
     * @throws RuntimeException si le compte n'est pas relié, ou si Microsoft refuse
     */
    public static function tirer(int $userId): array
    {
        /*
         * Deux lectures en même temps — un onglet qui se réveille pendant
         * qu'on clique — créeraient les mêmes évènements deux fois : chacune
         * les croirait nouveaux. Le verrou est demandé sans attendre ; la
         * seconde repart les mains vides plutôt que de faire la queue.
         */
        $verrou = 'mescours_outlook_' . $userId;
        if ((int) Database::valeur('SELECT GET_LOCK(?, 0)', [$verrou]) !== 1) {
            return ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0,
                    'inchanges' => 0, 'occupe' => true];
        }

        try {
            return self::vraimentTirer($userId);
        } finally {
            Database::run('SELECT RELEASE_LOCK(?)', [$verrou]);
        }
    }

    /** @return array{ajoutes: int, modifies: int, retires: int, inchanges: int, occupe: bool} */
    private static function vraimentTirer(int $userId): array
    {
        $fuseau = new DateTimeZone(self::FUSEAU);
        $maintenant = new DateTimeImmutable('now', $fuseau);
        $depuis = $maintenant->modify(self::AVANT)->setTime(0, 0);
        $jusqua = $maintenant->modify(self::APRES)->setTime(23, 59, 59);

        $venus = self::lire($userId, $depuis, $jusqua);
        $connus = self::liens($userId);
        /*
         * Ce que l'application a elle-même écrit dans « Mes Cours ». On le
         * croise en relisant ce calendrier, et le rapatrier ferait de chaque
         * évènement son propre double — puis le double d'un double.
         */
        $ecrits = self::ecritsParNous($userId);

        $bilan = ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0,
                  'inchanges' => 0, 'occupe' => false];
        $vus = [];

        foreach ($venus as $brut) {
            $champs = self::traduire($brut, $fuseau);
            if ($champs === null) {
                continue;
            }

            $outlookId = (string) $brut['id'];
            if (isset($ecrits[$outlookId])) {
                continue;
            }
            $vus[$outlookId] = true;
            $empreinte = md5(json_encode($champs, JSON_THROW_ON_ERROR));
            $lien = $connus[$outlookId] ?? null;

            if ($lien === null) {
                self::ajouter($userId, $outlookId, $champs, $empreinte);
                $bilan['ajoutes']++;
                continue;
            }
            if ($lien['empreinte'] === $empreinte) {
                $bilan['inchanges']++;
                continue;
            }

            self::mettreAJour($userId, (int) $lien['evenement_id'], $outlookId, $champs, $empreinte);
            $bilan['modifies']++;
        }

        $bilan['retires'] = self::retirerLesDisparus($userId, $connus, $vus, $depuis, $jusqua);

        Database::run('UPDATE outlook_comptes SET synchro_le = NOW() WHERE user_id = ?', [$userId]);

        return $bilan;
    }

    /**
     * Est-il temps de relire l'agenda de son propre chef ?
     *
     * Faux si le compte n'est pas relié, si aucun calendrier n'est suivi, ou
     * si la dernière lecture est trop fraîche.
     */
    public static function aBesoinDEtreRelu(int $userId): bool
    {
        if (!Outlook::configuree() || !Outlook::relie($userId)) {
            return false;
        }

        $connus = (int) Database::valeur(
            'SELECT COUNT(*) FROM outlook_calendriers WHERE user_id = ?', [$userId]);
        $suivis = (int) Database::valeur(
            'SELECT COUNT(*) FROM outlook_calendriers WHERE user_id = ? AND suivi = 1', [$userId]);
        if ($connus > 0 && $suivis === 0) {
            // Tout a été décoché : il n'y a plus rien à aller chercher.
            return false;
        }

        $quand = self::derniereFois($userId);
        if ($quand === null) {
            // Jamais lu : c'est à l'utilisateur de lancer la première fois,
            // pour qu'il voie arriver ce qu'il a demandé.
            return false;
        }

        return (time() - (int) strtotime($quand)) >= self::REPOS;
    }

    /**
     * Retient le dernier échec, ou l'efface quand tout est rentré dans l'ordre.
     *
     * La synchronisation tourne aussi seule, en arrière-plan, où elle ne peut
     * rien dire sans interrompre. Muette, elle laisserait quelqu'un attendre
     * des jours des évènements qui ne viennent plus.
     */
    public static function retenirLeSouci(int $userId, ?string $souci): void
    {
        $texte = $souci === null ? null : mb_substr($souci, 0, 500);

        Database::run(
            'UPDATE outlook_comptes
                SET souci = ?, souci_le = IF(? IS NULL, NULL, NOW())
              WHERE user_id = ?',
            [$texte, $texte, $userId]
        );
    }

    /**
     * Le dernier échec, s'il n'a pas été suivi d'une réussite.
     *
     * @return ?array{quoi: string, quand: string}
     */
    public static function dernierSouci(int $userId): ?array
    {
        $ligne = Database::one(
            'SELECT souci, souci_le FROM outlook_comptes WHERE user_id = ? AND souci IS NOT NULL',
            [$userId]
        );

        return $ligne === null
            ? null
            : ['quoi' => (string) $ligne['souci'], 'quand' => (string) $ligne['souci_le']];
    }

    /** La date de la dernière synchronisation, ou null s'il n'y en a jamais eu. */
    public static function derniereFois(int $userId): ?string
    {
        $quand = Database::valeur(
            'SELECT synchro_le FROM outlook_comptes WHERE user_id = ?',
            [$userId]
        );

        return $quand === null ? null : (string) $quand;
    }

    /** Combien d'évènements de l'application viennent d'Outlook. */
    public static function combien(int $userId): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM outlook_liens WHERE user_id = ?',
            [$userId]
        );
    }

    /**
     * Retire du calendrier tout ce qui venait d'Outlook.
     *
     * Rien n'est perdu : ces évènements existent toujours dans l'agenda
     * Microsoft, d'où ils reviendront à la prochaine lecture. Ce qui a été
     * créé dans l'application, lui, n'est pas concerné — la suppression ne
     * porte que sur ce que la synchronisation avait elle-même apporté.
     *
     * @return int  combien ont été retirés
     */
    public static function toutRetirer(int $userId): int
    {
        $ids = Database::all(
            'SELECT evenement_id FROM outlook_liens WHERE user_id = ? AND evenement_id IS NOT NULL',
            [$userId]
        );

        $retires = 0;
        foreach ($ids as $ligne) {
            // Un par un, et toujours borné au compte : la règle de la maison.
            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?',
                [(int) $ligne['evenement_id'], $userId]);
            $retires++;
        }

        // Le lien tombe avec l'évènement, mais une ligne orpheline peut rester.
        Database::run('DELETE FROM outlook_liens WHERE user_id = ?', [$userId]);
        Database::run('UPDATE outlook_comptes SET synchro_le = NULL WHERE user_id = ?', [$userId]);

        return $retires;
    }

    /* --- Les calendriers -------------------------------------------------- */

    /**
     * Les calendriers connus de l'application, tels qu'elle les a vus.
     *
     * @return array<int, array>
     */
    public static function calendriers(int $userId): array
    {
        $ecriture = EnvoiOutlook::calendrierConnu($userId);

        return Database::all(
            'SELECT id, empreinte, nom, proprietaire, partage, principal, suivi
               FROM outlook_calendriers
              WHERE user_id = ? AND empreinte <> ?
              ORDER BY principal DESC, partage ASC, nom ASC',
            [$userId, $ecriture === null ? '' : md5($ecriture)]
        );
    }

    /**
     * Redemande à Microsoft la liste des calendriers du compte.
     *
     * Les calendriers vivent dans des groupes — « Mes calendriers », « Autres
     * calendriers » —, et c'est dans le second que Microsoft range ceux qu'on
     * nous a partagés. Les demander groupe par groupe est le seul moyen de ne
     * pas passer à côté de la moitié d'un agenda.
     *
     * Un calendrier vu pour la première fois n'est pas suivi, sauf le
     * principal : on ne remplit pas le calendrier de quelqu'un sans qu'il l'ait
     * demandé.
     *
     * @return int  combien de calendriers sont désormais connus
     * @throws RuntimeException si Microsoft refuse
     */
    public static function rafraichirLesCalendriers(int $userId): int
    {
        $moi = mb_strtolower((string) (Outlook::compte($userId)['compte'] ?? ''));
        $trouves = [];

        foreach (self::sourcesDeCalendriers($userId) as $chemin) {
            $reponse = Outlook::appeler($userId, 'GET', $chemin);
            if ($reponse['code'] >= 400) {
                // Un groupe inaccessible ne doit pas emporter les autres :
                // certains comptes n'ont pas tous les groupes.
                continue;
            }
            foreach (($reponse['corps']['value'] ?? []) as $cal) {
                if (isset($cal['id'])) {
                    $trouves[(string) $cal['id']] = $cal;
                }
            }
        }

        if ($trouves === []) {
            throw new RuntimeException('Microsoft n’a donné aucun calendrier.');
        }

        $connus = [];
        foreach (self::calendriers($userId) as $ligne) {
            $connus[(string) $ligne['empreinte']] = true;
        }

        foreach ($trouves as $id => $cal) {
            $empreinte = md5($id);
            $adresse = mb_strtolower((string) ($cal['owner']['address'] ?? ''));
            $principal = ($cal['isDefaultCalendar'] ?? false) === true ? 1 : 0;
            $partage = ($adresse !== '' && $moi !== '' && $adresse !== $moi) ? 1 : 0;

            if (isset($connus[$empreinte])) {
                // Le choix de l'utilisateur ne se réécrit pas : seul le
                // signalement change.
                Database::run(
                    'UPDATE outlook_calendriers
                        SET nom = ?, proprietaire = ?, partage = ?, principal = ?, vu_le = NOW()
                      WHERE user_id = ? AND empreinte = ?',
                    [
                        mb_substr((string) ($cal['name'] ?? 'Calendrier'), 0, 190),
                        mb_substr((string) ($cal['owner']['name'] ?? $adresse), 0, 190),
                        $partage, $principal, $userId, $empreinte,
                    ]
                );
                continue;
            }

            Database::run(
                'INSERT INTO outlook_calendriers
                     (user_id, calendrier_id, empreinte, nom, proprietaire, partage, principal, suivi)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId, $id, $empreinte,
                    mb_substr((string) ($cal['name'] ?? 'Calendrier'), 0, 190),
                    mb_substr((string) ($cal['owner']['name'] ?? $adresse), 0, 190),
                    $partage, $principal, $principal,
                ]
            );
        }

        return count($trouves);
    }

    /**
     * Retient les calendriers à lire.
     *
     * @param  array<int, string> $empreintes  ceux que l'utilisateur a cochés
     * @return int  combien sont suivis
     */
    public static function choisir(int $userId, array $empreintes): int
    {
        $garder = [];
        foreach ($empreintes as $empreinte) {
            if (is_string($empreinte) && preg_match('/^[0-9a-f]{32}$/', $empreinte) === 1) {
                $garder[$empreinte] = true;
            }
        }

        foreach (self::calendriers($userId) as $ligne) {
            $veut = isset($garder[(string) $ligne['empreinte']]) ? 1 : 0;
            if ((int) $ligne['suivi'] === $veut) {
                continue;
            }
            Database::run(
                'UPDATE outlook_calendriers SET suivi = ? WHERE user_id = ? AND empreinte = ?',
                [$veut, $userId, (string) $ligne['empreinte']]
            );
        }

        return count($garder);
    }

    /** Où Microsoft range les calendriers d'un compte. */
    private static function sourcesDeCalendriers(int $userId): array
    {
        $chemins = ['/me/calendars?$select=id,name,owner,isDefaultCalendar&$top=100'];

        $groupes = Outlook::appeler($userId, 'GET', '/me/calendarGroups?$select=id&$top=50');
        if ($groupes['code'] < 400) {
            foreach (($groupes['corps']['value'] ?? []) as $groupe) {
                if (!isset($groupe['id'])) {
                    continue;
                }
                $chemins[] = '/me/calendarGroups/' . rawurlencode((string) $groupe['id'])
                    . '/calendars?$select=id,name,owner,isDefaultCalendar&$top=100';
            }
        }

        return $chemins;
    }

    /* --- Lire chez Microsoft --------------------------------------------- */

    /**
     * Toutes les occurrences de la fenêtre, page après page.
     *
     * @return array<int, array>
     */
    private static function lire(int $userId, DateTimeImmutable $depuis, DateTimeImmutable $jusqua): array
    {
        $question = http_build_query([
            'startDateTime' => $depuis->format('c'),
            'endDateTime'   => $jusqua->format('c'),
            '$select'       => self::CHAMPS,
            '$orderby'      => 'start/dateTime',
            '$top'          => self::PAR_PAGE,
        ]);

        $tout = [];
        foreach (self::aLire($userId) as $calendrier) {
            foreach (self::unCalendrier($userId, $calendrier, $question) as $evenement) {
                // Un évènement partagé entre deux calendriers ne compte qu'une
                // fois : son identifiant tranche.
                $tout[(string) ($evenement['id'] ?? '')] = $evenement;
            }
        }
        unset($tout['']);

        return array_values($tout);
    }

    /**
     * Les calendriers à parcourir.
     *
     * Tant que la liste n'a jamais été demandée à Microsoft, on s'en tient au
     * calendrier principal : c'est le comportement d'avant, et il vaut mieux
     * lire trop peu que se mettre à remplir un calendrier sans prévenir.
     *
     * « Mes Cours » — celui que l'application remplit — est lu lui aussi, mais
     * à part : il n'est pas à cocher, puisqu'on ne choisit pas de suivre son
     * propre reflet. Ce qu'on y trouve et qu'on n'y a pas écrit soi-même est
     * un évènement créé depuis Outlook, et il a sa place ici.
     *
     * @return array<int, ?array>  null désigne le calendrier principal
     */
    private static function aLire(int $userId): array
    {
        $ecriture = EnvoiOutlook::calendrierConnu($userId);

        $suivis = Database::all(
            'SELECT calendrier_id, nom FROM outlook_calendriers
              WHERE user_id = ? AND suivi = 1 AND empreinte <> ?',
            [$userId, $ecriture === null ? '' : md5($ecriture)]
        );
        if ($suivis === []) {
            $suivis = [null];
        }

        if ($ecriture !== null) {
            $suivis[] = ['calendrier_id' => $ecriture, 'nom' => 'Mes Cours'];
        }

        return $suivis;
    }

    /**
     * Les occurrences d'un calendrier, page après page.
     *
     * @param  ?array $calendrier  null pour le calendrier principal
     * @return array<int, array>
     */
    private static function unCalendrier(int $userId, ?array $calendrier, string $question): array
    {
        $base = $calendrier === null
            ? '/me/calendarView?'
            : '/me/calendars/' . rawurlencode((string) $calendrier['calendrier_id']) . '/calendarView?';
        $chemin = $base . $question;
        $nom = $calendrier === null ? 'l’agenda' : '« ' . (string) $calendrier['nom'] . ' »';

        /*
         * « Prefer » demande que les heures reviennent déjà dans notre fuseau.
         * Sans cela Microsoft répond en UTC, et il faudrait convertir à la main
         * une date que lui sait convertir mieux que nous.
         */
        $entetes = ['Prefer: outlook.timezone="' . self::FUSEAU . '"'];

        $tout = [];
        for ($page = 0; $page < self::PAGES_MAX && $chemin !== ''; $page++) {
            $reponse = Outlook::appeler($userId, 'GET', $chemin, null, $entetes);

            if ($reponse['code'] === 403) {
                throw new RuntimeException(
                    'Microsoft refuse l’accès à ' . $nom . '. Si c’est un calendrier '
                    . 'partagé par quelqu’un d’autre, réautorisez l’application : '
                    . 'la permission qui les ouvre est plus récente que votre liaison.'
                );
            }
            if ($reponse['code'] >= 400) {
                $dit = (string) ($reponse['corps']['error']['message'] ?? '');

                throw new RuntimeException('Microsoft a refusé de donner ' . $nom
                    . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
            }

            foreach (($reponse['corps']['value'] ?? []) as $evenement) {
                $tout[] = $evenement;
            }
            $chemin = (string) ($reponse['corps']['@odata.nextLink'] ?? '');
        }

        return $tout;
    }

    /* --- Traduire ---------------------------------------------------------- */

    /**
     * Un évènement Microsoft dans les termes de l'application.
     *
     * @return ?array  null si l'évènement n'a pas sa place ici (annulé, illisible)
     */
    private static function traduire(array $brut, DateTimeZone $fuseau): ?array
    {
        if (($brut['isCancelled'] ?? false) === true) {
            return null;
        }

        $journee = ($brut['isAllDay'] ?? false) === true;
        $debut = self::moment($brut['start'] ?? null, $fuseau);
        $fin = self::moment($brut['end'] ?? null, $fuseau);
        if ($debut === null || $fin === null) {
            return null;
        }

        if ($journee) {
            /*
             * Microsoft borne une journée entière par le lendemain à minuit ;
             * l'application, elle, la termine à 23:59:59 du dernier jour. On
             * recule d'une seconde pour retomber sur sa convention.
             */
            $debut = $debut->setTime(0, 0);
            $fin = $fin->modify('-1 second');
            if ($fin < $debut) {
                $fin = $debut->setTime(23, 59, 59);
            }
        }
        if ($fin < $debut) {
            $fin = $debut;
        }

        $titre = trim((string) ($brut['subject'] ?? ''));
        $lieu = trim((string) ($brut['location']['displayName'] ?? ''));
        $texte = trim((string) ($brut['bodyPreview'] ?? ''));

        return [
            'titre'           => mb_substr($titre === '' ? '(sans titre)' : $titre, 0, 200),
            'description'     => $texte === '' ? null : mb_substr($texte, 0, 2000),
            'lieu'            => $lieu === '' ? null : mb_substr($lieu, 0, 160),
            'debut'           => $debut->format('Y-m-d H:i:s'),
            'fin'             => $fin->format('Y-m-d H:i:s'),
            'journee_entiere' => $journee ? 1 : 0,
        ];
    }

    /** Une date Microsoft, lue dans le fuseau qu'on a demandé. */
    private static function moment(mixed $borne, DateTimeZone $fuseau): ?DateTimeImmutable
    {
        if (!is_array($borne) || !isset($borne['dateTime'])) {
            return null;
        }

        // « 2026-09-08T14:00:00.0000000 » : les fractions de seconde gênent.
        $ecrit = substr((string) $borne['dateTime'], 0, 19);

        try {
            return new DateTimeImmutable($ecrit, $fuseau);
        } catch (Exception) {
            return null;
        }
    }

    /* --- Écrire ici -------------------------------------------------------- */

    /**
     * Les identifiants des évènements que l'application a écrits dans Outlook.
     *
     * @return array<string, true>
     */
    private static function ecritsParNous(int $userId): array
    {
        $par = [];
        foreach (Database::all('SELECT outlook_id FROM outlook_envois WHERE user_id = ?',
            [$userId]) as $ligne) {
            $par[(string) $ligne['outlook_id']] = true;
        }

        return $par;
    }

    /** Ce que l'application sait déjà, rangé par identifiant Outlook. */
    private static function liens(int $userId): array
    {
        $lignes = Database::all(
            'SELECT outlook_id, evenement_id, empreinte FROM outlook_liens WHERE user_id = ?',
            [$userId]
        );

        $par = [];
        foreach ($lignes as $ligne) {
            $par[(string) $ligne['outlook_id']] = $ligne;
        }

        return $par;
    }

    private static function ajouter(int $userId, string $outlookId, array $champs, string $empreinte): void
    {
        Database::run(
            'INSERT INTO evenements (user_id, titre, description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $champs['titre'], $champs['description'], $champs['lieu'],
                $champs['debut'], $champs['fin'], $champs['journee_entiere'],
            ]
        );
        $evenementId = Database::dernierId();

        Database::run(
            'INSERT INTO outlook_liens (user_id, evenement_id, outlook_id, empreinte)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE evenement_id = VALUES(evenement_id), empreinte = VALUES(empreinte)',
            [$userId, $evenementId, $outlookId, $empreinte]
        );
    }

    private static function mettreAJour(
        int $userId,
        int $evenementId,
        string $outlookId,
        array $champs,
        string $empreinte
    ): void {
        /*
         * Le user_id est répété dans la condition alors que l'identifiant
         * suffirait : c'est la règle de la maison, aucune requête ne franchit
         * la frontière d'un compte, même quand elle vient de nous.
         */
        Database::run(
            'UPDATE evenements
                SET titre = ?, description = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ?
              WHERE id = ? AND user_id = ?',
            [
                $champs['titre'], $champs['description'], $champs['lieu'],
                $champs['debut'], $champs['fin'], $champs['journee_entiere'],
                $evenementId, $userId,
            ]
        );

        Database::run(
            'UPDATE outlook_liens SET empreinte = ? WHERE user_id = ? AND outlook_id = ?',
            [$empreinte, $userId, $outlookId]
        );
    }

    /**
     * Efface ce qui a disparu d'Outlook — et rien d'autre.
     *
     * Seuls sont retirés les évènements que la synchronisation avait elle-même
     * apportés, et seulement ceux dont la date tombe dans la fenêtre qu'on
     * vient de regarder. Un évènement plus lointain n'a pas été vu ; ce n'est
     * pas une raison pour croire qu'il a été supprimé.
     */
    private static function retirerLesDisparus(
        int $userId,
        array $connus,
        array $vus,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua
    ): int {
        $retires = 0;

        foreach ($connus as $outlookId => $lien) {
            if (isset($vus[$outlookId])) {
                continue;
            }

            $dedans = Database::valeur(
                'SELECT id FROM evenements WHERE id = ? AND user_id = ? AND debut BETWEEN ? AND ?',
                [
                    (int) $lien['evenement_id'], $userId,
                    $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s'),
                ]
            );
            if ($dedans === null) {
                continue;
            }

            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?',
                [(int) $lien['evenement_id'], $userId]);
            Database::run('DELETE FROM outlook_liens WHERE user_id = ? AND outlook_id = ?',
                [$userId, (string) $outlookId]);
            $retires++;
        }

        return $retires;
    }
}
