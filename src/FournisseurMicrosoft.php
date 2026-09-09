<?php
declare(strict_types=1);

/**
 * Outlook, par l'API Microsoft Graph.
 *
 * Ce qu'il y a de particulier chez Microsoft : les calendriers sont rangés en
 * groupes — c'est dans « Autres calendriers » qu'il met ceux qu'on nous a
 * partagés —, une journée entière se borne par le lendemain à minuit, et une
 * vue de calendrier déplie d'elle-même les séries récurrentes.
 */
final class FournisseurMicrosoft extends Fournisseur
{
    private const RACINE = 'https://graph.microsoft.com/v1.0';

    /** Ce qu'on lit de chaque évènement : rien de plus que ce qui s'affiche. */
    private const CHAMPS = 'id,subject,bodyPreview,location,start,end,isAllDay,isCancelled';

    public function cle(): string
    {
        return 'microsoft';
    }

    public function nom(): string
    {
        return 'Outlook';
    }

    /* --- L'autorisation ---------------------------------------------------- */

    public function urlAutorisation(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($this->locataire())
            . '/oauth2/v2.0/authorize';
    }

    public function urlJetons(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($this->locataire())
            . '/oauth2/v2.0/token';
    }

    /** Celui déclaré dans Azure avant la refonte : on n'y touche pas. */
    public function cheminDeRetour(): string
    {
        return 'outlook/retour';
    }

    /**
     * « .Shared » ouvre les calendriers qu'on nous a partagés — celui d'un
     * proche, d'un groupe — que Microsoft tient à part des siens.
     */
    public function permissions(): string
    {
        return 'offline_access openid email User.Read '
            . 'Calendars.ReadWrite Calendars.ReadWrite.Shared';
    }

    public function parametresDAutorisation(): array
    {
        // Choisir son compte à chaque fois : on en a souvent plusieurs.
        return ['prompt' => 'select_account', 'response_mode' => 'query'];
    }

    public function secretObligatoire(): bool
    {
        return false;
    }

    private function locataire(): string
    {
        $locataire = trim((string) Config::get('outlook', 'locataire'));

        return $locataire === '' ? 'common' : $locataire;
    }

    /* --- Parler à l'API ---------------------------------------------------- */

    public function racineApi(): string
    {
        return self::RACINE;
    }

    public function identite(int $userId): array
    {
        $moi = LiaisonAgenda::pour($this)->appeler(
            $userId, 'GET', '/me?$select=displayName,mail,userPrincipalName');
        $agenda = LiaisonAgenda::pour($this)->appeler($userId, 'GET', '/me/calendar?$select=id,name');

        return [
            'compte'         => (string) ($moi['corps']['mail'] ?? $moi['corps']['userPrincipalName'] ?? ''),
            'calendrier_id'  => (string) ($agenda['corps']['id'] ?? ''),
            'calendrier_nom' => (string) ($agenda['corps']['name'] ?? ''),
        ];
    }

    /* --- Lire les calendriers ---------------------------------------------- */

    public function cheminsDesCalendriers(int $userId): array
    {
        $chemins = ['/me/calendars?$select=id,name,owner,isDefaultCalendar&$top=100'];

        $groupes = LiaisonAgenda::pour($this)->appeler(
            $userId, 'GET', '/me/calendarGroups?$select=id&$top=50');
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

    public function lireCalendrier(array $brut): ?array
    {
        if (!isset($brut['id'])) {
            return null;
        }

        $adresse = (string) ($brut['owner']['address'] ?? '');

        return [
            'id'           => (string) $brut['id'],
            'nom'          => (string) ($brut['name'] ?? 'Calendrier'),
            'proprietaire' => (string) ($brut['owner']['name'] ?? $adresse),
            'adresse'      => $adresse,
            'principal'    => ($brut['isDefaultCalendar'] ?? false) === true,
        ];
    }

    /* --- Lire les évènements ----------------------------------------------- */

    public function cheminDesEvenements(
        ?string $calendrierId,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua,
        int $parPage,
        string $fuseau
    ): string {
        /*
         * Une « vue de calendrier » plutôt que la liste brute : elle déplie les
         * séries récurrentes en occurrences datées, ce qu'il faut pour un
         * calendrier qu'on regarde.
         */
        $base = $calendrierId === null
            ? '/me/calendarView?'
            : '/me/calendars/' . rawurlencode($calendrierId) . '/calendarView?';

        return $base . http_build_query([
            'startDateTime' => $depuis->format('c'),
            'endDateTime'   => $jusqua->format('c'),
            '$select'       => self::CHAMPS,
            '$orderby'      => 'start/dateTime',
            '$top'          => $parPage,
        ]);
    }

    public function entetesDeLecture(string $fuseau): array
    {
        /*
         * « Prefer » demande que les heures reviennent déjà dans notre fuseau.
         * Sans cela Microsoft répond en UTC, et il faudrait convertir à la main
         * une date que lui sait convertir mieux que nous.
         */
        return ['Prefer: outlook.timezone="' . $fuseau . '"'];
    }

    public function pageSuivante(array $corps, string $cheminPrecedent): string
    {
        return (string) ($corps['@odata.nextLink'] ?? '');
    }

    public function lireEvenement(array $brut, DateTimeZone $fuseau): ?array
    {
        if (($brut['isCancelled'] ?? false) === true) {
            return null;
        }

        $journee = ($brut['isAllDay'] ?? false) === true;
        $debut = $this->moment($brut['start'] ?? null, $fuseau);
        $fin = $this->moment($brut['end'] ?? null, $fuseau);
        if ($debut === null || $fin === null) {
            return null;
        }

        if ($journee) {
            /*
             * Microsoft borne une journée entière par le lendemain à minuit ;
             * l'application, elle, la termine à 23:59:59 du dernier jour.
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
    private function moment(mixed $borne, DateTimeZone $fuseau): ?DateTimeImmutable
    {
        if (!is_array($borne) || !isset($borne['dateTime'])) {
            return null;
        }

        // « 2026-09-08T14:00:00.0000000 » : les fractions de seconde gênent.
        try {
            return new DateTimeImmutable(substr((string) $borne['dateTime'], 0, 19), $fuseau);
        } catch (Exception) {
            return null;
        }
    }

    /* --- Écrire ------------------------------------------------------------ */

    public function corpsDUnEvenement(
        string $titre,
        string $texte,
        string $lieu,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        bool $journee,
        string $fuseau
    ): array {
        $corps = [
            'subject'  => mb_substr($titre === '' ? '(sans titre)' : $titre, 0, 250),
            'body'     => ['contentType' => 'text', 'content' => mb_substr($texte, 0, 4000)],
            'isAllDay' => $journee,
            'start'    => ['dateTime' => $debut->format('Y-m-d\TH:i:s'), 'timeZone' => $fuseau],
            'end'      => ['dateTime' => $fin->format('Y-m-d\TH:i:s'), 'timeZone' => $fuseau],
        ];
        if ($lieu !== '') {
            $corps['location'] = ['displayName' => mb_substr($lieu, 0, 250)];
        }

        return $corps;
    }

    public function cheminDeCreation(string $calendrierId): string
    {
        return '/me/calendars/' . rawurlencode($calendrierId) . '/events';
    }

    public function cheminDeModification(string $calendrierId, string $evenementId): string
    {
        // Microsoft retrouve un évènement sans son calendrier.
        return '/me/events/' . rawurlencode($evenementId);
    }

    public function cheminDeSuppression(string $calendrierId, string $evenementId): string
    {
        return '/me/events/' . rawurlencode($evenementId);
    }

    public function cheminDeListeSimple(): string
    {
        return '/me/calendars?$select=id,name&$top=100';
    }

    public function creationDeCalendrier(string $nom): array
    {
        return ['/me/calendars', ['name' => $nom]];
    }

    public function idDuCalendrierCree(array $corps): string
    {
        return (string) ($corps['id'] ?? '');
    }
}
