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
     * Va chercher les évènements et met l'application à jour.
     *
     * @return array{ajoutes: int, modifies: int, retires: int, inchanges: int}
     * @throws RuntimeException si le compte n'est pas relié, ou si Microsoft refuse
     */
    public static function tirer(int $userId): array
    {
        $fuseau = new DateTimeZone(self::FUSEAU);
        $maintenant = new DateTimeImmutable('now', $fuseau);
        $depuis = $maintenant->modify(self::AVANT)->setTime(0, 0);
        $jusqua = $maintenant->modify(self::APRES)->setTime(23, 59, 59);

        $venus = self::lire($userId, $depuis, $jusqua);
        $connus = self::liens($userId);

        $bilan = ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0, 'inchanges' => 0];
        $vus = [];

        foreach ($venus as $brut) {
            $champs = self::traduire($brut, $fuseau);
            if ($champs === null) {
                continue;
            }

            $outlookId = (string) $brut['id'];
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

    /* --- Lire chez Microsoft --------------------------------------------- */

    /**
     * Toutes les occurrences de la fenêtre, page après page.
     *
     * @return array<int, array>
     */
    private static function lire(int $userId, DateTimeImmutable $depuis, DateTimeImmutable $jusqua): array
    {
        $chemin = '/me/calendarView?' . http_build_query([
            'startDateTime' => $depuis->format('c'),
            'endDateTime'   => $jusqua->format('c'),
            '$select'       => self::CHAMPS,
            '$orderby'      => 'start/dateTime',
            '$top'          => self::PAR_PAGE,
        ]);

        /*
         * « Prefer » demande que les heures reviennent déjà dans notre fuseau.
         * Sans cela Microsoft répond en UTC, et il faudrait convertir à la main
         * une date que lui sait convertir mieux que nous.
         */
        $entetes = ['Prefer: outlook.timezone="' . self::FUSEAU . '"'];

        $tout = [];
        for ($page = 0; $page < self::PAGES_MAX && $chemin !== ''; $page++) {
            $reponse = Outlook::appeler($userId, 'GET', $chemin, null, $entetes);

            if ($reponse['code'] >= 400) {
                $dit = (string) ($reponse['corps']['error']['message'] ?? '');

                throw new RuntimeException($dit === ''
                    ? 'Microsoft a refusé de donner l’agenda.'
                    : 'Microsoft a refusé de donner l’agenda : ' . mb_substr($dit, 0, 200));
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
