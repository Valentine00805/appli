<?php
declare(strict_types=1);

/**
 * Google Agenda, par l'API Calendar v3.
 *
 * Ce qu'il y a de particulier chez Google : les calendriers sont dans une
 * liste unique, sans groupes ; une journée entière se dit par un champ
 * « date » au lieu de « dateTime », et se borne par le lendemain — exclu ; les
 * pages se suivent par un jeton plutôt que par une adresse ; et il faut
 * demander explicitement que les séries récurrentes soient dépliées.
 *
 * Google exige aussi le calendrier pour modifier ou supprimer un évènement, là
 * où Microsoft s'en passe : c'est pour lui que la table des envois le retient.
 */
final class FournisseurGoogle extends Fournisseur
{
    private const RACINE = 'https://www.googleapis.com/calendar/v3';

    public function cle(): string
    {
        return 'google';
    }

    public function nom(): string
    {
        return 'Google Agenda';
    }

    /* --- L'autorisation ---------------------------------------------------- */

    public function urlAutorisation(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    public function urlJetons(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    public function cheminDeRetour(): string
    {
        return 'agenda/google/retour';
    }

    /**
     * Une seule permission suffit : elle couvre la liste des agendas et le
     * contenu de chacun, en lecture comme en écriture.
     */
    public function permissions(): string
    {
        return 'openid email https://www.googleapis.com/auth/calendar';
    }

    public function parametresDAutorisation(): array
    {
        /*
         * Sans « access_type=offline », Google ne donne pas de jeton de
         * renouvellement ; sans « prompt=consent », il ne le redonne pas à la
         * deuxième autorisation. Les deux ensemble, sinon la liaison tient une
         * heure.
         */
        return ['access_type' => 'offline', 'prompt' => 'consent'];
    }

    public function secretObligatoire(): bool
    {
        return true;
    }

    /* --- Parler à l'API ---------------------------------------------------- */

    public function racineApi(): string
    {
        return self::RACINE;
    }

    public function identite(int $userId): array
    {
        $agenda = LiaisonAgenda::pour($this)->appeler($userId, 'GET', '/calendars/primary');

        /*
         * Un refus ici tient presque toujours à la permission d'agenda, que
         * l'écran de consentement de Google laisse décochée. Le taire donnerait
         * un compte relié sans nom et sans droits, dont on ne comprendrait
         * l'inutilité que trois clics plus loin.
         */
        if ($agenda['code'] >= 400) {
            throw new RuntimeException(
                'Google a refusé l’accès à votre agenda. Sur l’écran de '
                . 'consentement, cochez la case « Consulter, modifier, partager et '
                . 'supprimer définitivement tous les agendas », puis recommencez : '
                . 'elle n’est pas cochée d’avance.');
        }

        return [
            // Chez Google, l'identifiant du calendrier principal est l'adresse.
            'compte'         => (string) ($agenda['corps']['id'] ?? ''),
            'calendrier_id'  => (string) ($agenda['corps']['id'] ?? ''),
            'calendrier_nom' => (string) ($agenda['corps']['summary'] ?? ''),
        ];
    }

    /* --- Lire les calendriers ---------------------------------------------- */

    public function cheminsDesCalendriers(int $userId): array
    {
        // Une seule liste, celle à laquelle le compte est abonné : les agendas
        // partagés qu'on a acceptés s'y trouvent comme les siens.
        return ['/users/me/calendarList?maxResults=250'];
    }

    public function lireCalendrier(array $brut): ?array
    {
        if (!isset($brut['id'])) {
            return null;
        }

        return [
            'id'           => (string) $brut['id'],
            'nom'          => (string) ($brut['summaryOverride'] ?? $brut['summary'] ?? 'Agenda'),
            'proprietaire' => (string) ($brut['summary'] ?? ''),
            'adresse'      => (string) $brut['id'],
            'principal'    => ($brut['primary'] ?? false) === true,
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
        return '/calendars/' . rawurlencode($calendrierId ?? 'primary') . '/events?'
            . http_build_query([
                'timeMin'      => $depuis->format('c'),
                'timeMax'      => $jusqua->format('c'),
                // Déplier les séries : sans cela, Google rend la règle et non
                // les occurrences, et le calendrier n'aurait rien à montrer.
                'singleEvents' => 'true',
                'orderBy'      => 'startTime',
                'timeZone'     => $fuseau,
                'maxResults'   => min($parPage, 250),
            ]);
    }

    public function entetesDeLecture(string $fuseau): array
    {
        // Le fuseau voyage dans l'adresse, pas dans un en-tête.
        return [];
    }

    public function elements(array $corps): array
    {
        return is_array($corps['items'] ?? null) ? $corps['items'] : [];
    }

    public function pageSuivante(array $corps, string $cheminPrecedent): string
    {
        $jeton = (string) ($corps['nextPageToken'] ?? '');
        if ($jeton === '') {
            return '';
        }

        // Le jeton se rajoute au chemin précédent, débarrassé du sien.
        $sansJeton = (string) preg_replace('/&?pageToken=[^&]*/', '', $cheminPrecedent);

        return $sansJeton . '&pageToken=' . rawurlencode($jeton);
    }

    public function lireEvenement(array $brut, DateTimeZone $fuseau): ?array
    {
        if (($brut['status'] ?? '') === 'cancelled') {
            return null;
        }

        $journee = isset($brut['start']['date']);
        $debut = $this->moment($brut['start'] ?? null, $fuseau);
        $fin = $this->moment($brut['end'] ?? null, $fuseau);
        if ($debut === null || $fin === null) {
            return null;
        }

        if ($journee) {
            // Google borne une journée entière par le lendemain, exclu ;
            // l'application la termine à 23:59:59 du dernier jour.
            $debut = $debut->setTime(0, 0);
            $fin = $fin->modify('-1 second');
            if ($fin < $debut) {
                $fin = $debut->setTime(23, 59, 59);
            }
        }
        if ($fin < $debut) {
            $fin = $debut;
        }

        $titre = trim((string) ($brut['summary'] ?? ''));
        $lieu = trim((string) ($brut['location'] ?? ''));
        $texte = trim((string) ($brut['description'] ?? ''));

        return [
            'titre'           => mb_substr($titre === '' ? '(sans titre)' : $titre, 0, 200),
            'description'     => $texte === '' ? null : mb_substr($texte, 0, 2000),
            'lieu'            => $lieu === '' ? null : mb_substr($lieu, 0, 160),
            'debut'           => $debut->format('Y-m-d H:i:s'),
            'fin'             => $fin->format('Y-m-d H:i:s'),
            'journee_entiere' => $journee ? 1 : 0,
        ];
    }

    /**
     * Une borne Google : « dateTime » avec décalage, ou « date » toute seule
     * pour une journée entière.
     */
    private function moment(mixed $borne, DateTimeZone $fuseau): ?DateTimeImmutable
    {
        if (!is_array($borne)) {
            return null;
        }

        $ecrit = (string) ($borne['dateTime'] ?? $borne['date'] ?? '');
        if ($ecrit === '') {
            return null;
        }

        try {
            // Une date seule n'a pas de fuseau : on la lit dans le nôtre.
            $quand = new DateTimeImmutable($ecrit, $fuseau);

            return isset($borne['dateTime']) ? $quand->setTimezone($fuseau) : $quand;
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
            'summary'     => mb_substr($titre === '' ? '(sans titre)' : $titre, 0, 250),
            'description' => mb_substr($texte, 0, 4000),
        ];

        if ($journee) {
            // Bornes en dates nues, la fin exclue : c'est ainsi que Google
            // reconnaît une journée entière.
            $corps['start'] = ['date' => $debut->format('Y-m-d')];
            $corps['end'] = ['date' => $fin->format('Y-m-d')];
        } else {
            $corps['start'] = ['dateTime' => $debut->format('Y-m-d\TH:i:s'), 'timeZone' => $fuseau];
            $corps['end'] = ['dateTime' => $fin->format('Y-m-d\TH:i:s'), 'timeZone' => $fuseau];
        }

        if ($lieu !== '') {
            $corps['location'] = mb_substr($lieu, 0, 250);
        }

        return $corps;
    }

    public function cheminDeCreation(string $calendrierId): string
    {
        return '/calendars/' . rawurlencode($calendrierId) . '/events';
    }

    public function cheminDeModification(string $calendrierId, string $evenementId): string
    {
        return '/calendars/' . rawurlencode($calendrierId) . '/events/' . rawurlencode($evenementId);
    }

    public function cheminDeSuppression(string $calendrierId, string $evenementId): string
    {
        return '/calendars/' . rawurlencode($calendrierId) . '/events/' . rawurlencode($evenementId);
    }

    public function cheminDeListeSimple(): string
    {
        return '/users/me/calendarList?maxResults=250';
    }

    public function creationDeCalendrier(string $nom): array
    {
        return ['/calendars', ['summary' => $nom]];
    }

    public function idDuCalendrierCree(array $corps): string
    {
        return (string) ($corps['id'] ?? '');
    }
}
