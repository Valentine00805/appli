<?php
declare(strict_types=1);

/**
 * La liaison avec un calendrier Outlook, par l'API Microsoft Graph.
 *
 * L'application se présente en « client public » : elle n'a pas de secret à
 * garder, et prouve son identité par PKCE — un code tiré au sort à l'aller,
 * dont seule l'empreinte voyage, et qu'on redonne au retour. C'est ce qui
 * convient à une application posée sur le poste de quelqu'un : un secret
 * inscrit dans ses fichiers n'en serait plus un.
 *
 * Rien ici ne suppose une bibliothèque : curl suffit à parler à Microsoft.
 */
final class Outlook
{
    /** Ce que l'application demande : lire et écrire l'agenda, et revenir plus tard. */
    private const PERMISSIONS = 'offline_access openid email User.Read Calendars.ReadWrite';

    private const AUTORISATION = 'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize';
    private const JETONS       = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const GRAPH        = 'https://graph.microsoft.com/v1.0';

    /** Un jeton demandé un peu avant son terme : l'horloge n'est jamais parfaite. */
    private const MARGE = 120;

    /* --- Le compte relié -------------------------------------------------- */

    /** Ce que l'application sait du compte Outlook de quelqu'un, ou null. */
    public static function compte(int $userId): ?array
    {
        return Database::one('SELECT * FROM outlook_comptes WHERE user_id = ?', [$userId]);
    }

    /** Le compte est-il relié, c'est-à-dire y a-t-il de quoi revenir sans lui ? */
    public static function relie(int $userId): bool
    {
        $compte = self::compte($userId);

        return $compte !== null && (string) $compte['renouvellement'] !== '';
    }

    /**
     * Enregistre l'application inscrite chez Microsoft.
     *
     * Changer d'identifiant délie le compte : les jetons obtenus par l'ancien
     * ne valent rien pour le nouveau, et les garder ne ferait qu'égarer.
     */
    public static function retenirApplication(int $userId, string $clientId, string $locataire): void
    {
        $locataire = $locataire === '' ? 'common' : $locataire;
        $avant = self::compte($userId);

        if ($avant === null) {
            Database::run(
                'INSERT INTO outlook_comptes (user_id, client_id, locataire) VALUES (?, ?, ?)',
                [$userId, $clientId, $locataire]
            );
            return;
        }

        $change = (string) $avant['client_id'] !== $clientId
            || (string) $avant['locataire'] !== $locataire;

        Database::run(
            'UPDATE outlook_comptes SET client_id = ?, locataire = ?'
            . ($change ? ', compte = NULL, jeton = NULL, renouvellement = NULL, expire_le = NULL,
                          calendrier_id = NULL, calendrier_nom = NULL, delta = NULL, synchro_le = NULL' : '')
            . ' WHERE user_id = ?',
            [$clientId, $locataire, $userId]
        );
    }

    /** Oublie les jetons, sans oublier l'application inscrite. */
    public static function delier(int $userId): void
    {
        Database::run(
            'UPDATE outlook_comptes SET compte = NULL, jeton = NULL, renouvellement = NULL,
                    expire_le = NULL, calendrier_id = NULL, calendrier_nom = NULL,
                    delta = NULL, synchro_le = NULL
             WHERE user_id = ?',
            [$userId]
        );
        Database::run('DELETE FROM outlook_liens WHERE user_id = ?', [$userId]);
    }

    /* --- L'aller et le retour -------------------------------------------- */

    /** Où l'application demande à Microsoft de la renvoyer. */
    public static function adresseDeRetour(): string
    {
        $protocole = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            ? 'https' : 'http';

        return $protocole . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('outlook/retour');
    }

    /**
     * L'adresse où envoyer quelqu'un pour qu'il autorise l'application.
     *
     * Le vérificateur et l'état sont mis de côté le temps du voyage : le
     * premier prouve au retour que c'est bien nous qui étions partis, le
     * second que la réponse répond à notre demande et non à une autre.
     */
    public static function adresseDAutorisation(array $compte): string
    {
        $verificateur = self::motDePasseDeVoyage(64);
        $etat = self::motDePasseDeVoyage(24);

        Session::garder('outlook_verificateur', $verificateur);
        Session::garder('outlook_etat', $etat);

        $empreinte = rtrim(strtr(base64_encode(hash('sha256', $verificateur, true)), '+/', '-_'), '=');

        return sprintf(self::AUTORISATION, rawurlencode((string) $compte['locataire'])) . '?' . http_build_query([
            'client_id'             => (string) $compte['client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => self::adresseDeRetour(),
            'response_mode'         => 'query',
            'scope'                 => self::PERMISSIONS,
            'state'                 => $etat,
            'code_challenge'        => $empreinte,
            'code_challenge_method' => 'S256',
            // Choisir son compte à chaque fois : on en a souvent plusieurs.
            'prompt'                => 'select_account',
        ]);
    }

    /**
     * Échange le code du retour contre des jetons, et retient qui s'est relié.
     *
     * @return ?string  le message d'erreur, ou null si tout s'est bien passé
     */
    public static function terminerLaLiaison(int $userId, string $code, string $etat): ?string
    {
        $attendu = Session::reprendre('outlook_etat');
        $verificateur = Session::reprendre('outlook_verificateur');

        if (!is_string($attendu) || $attendu === '' || !hash_equals($attendu, $etat)) {
            return 'La réponse de Microsoft ne correspond pas à la demande envoyée. Recommencez.';
        }
        if (!is_string($verificateur) || $verificateur === '') {
            return 'La demande a expiré avant le retour de Microsoft. Recommencez.';
        }

        $compte = self::compte($userId);
        if ($compte === null) {
            return 'Aucune application Microsoft n’est enregistrée.';
        }

        $reponse = self::demanderDesJetons($compte, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => self::adresseDeRetour(),
            'code_verifier' => $verificateur,
        ]);
        if (is_string($reponse)) {
            return $reponse;
        }

        self::garderLesJetons($userId, $reponse);
        self::retenirQui($userId);

        return null;
    }

    /* --- Parler à Microsoft ---------------------------------------------- */

    /**
     * Un jeton d'accès valable, renouvelé au besoin.
     *
     * @throws RuntimeException si le compte n'est pas relié ou refuse de l'être
     */
    public static function jeton(int $userId): string
    {
        $compte = self::compte($userId);
        if ($compte === null || (string) $compte['renouvellement'] === '') {
            throw new RuntimeException('Le compte Outlook n’est pas relié.');
        }

        $expire = $compte['expire_le'] === null ? 0 : (int) strtotime((string) $compte['expire_le']);
        if ((string) $compte['jeton'] !== '' && $expire - self::MARGE > time()) {
            return (string) $compte['jeton'];
        }

        $reponse = self::demanderDesJetons($compte, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => (string) $compte['renouvellement'],
        ]);
        if (is_string($reponse)) {
            throw new RuntimeException('La liaison avec Outlook a été rompue : ' . $reponse);
        }
        self::garderLesJetons($userId, $reponse);

        return (string) $reponse['access_token'];
    }

    /**
     * Un appel à Microsoft Graph.
     *
     * @param  ?array $corps  envoyé en JSON ; null pour une simple lecture
     * @return array{code: int, corps: array, entetes: array<string, string>}
     * @throws RuntimeException si l'appel n'aboutit pas du tout
     */
    public static function appeler(
        int $userId,
        string $methode,
        string $chemin,
        ?array $corps = null
    ): array {
        $url = str_starts_with($chemin, 'https://') ? $chemin : self::GRAPH . $chemin;

        return self::requete($url, $methode, $corps === null ? null : json_encode($corps), [
            'Authorization: Bearer ' . self::jeton($userId),
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    /** Qui est relié, et sur quel calendrier : de quoi le dire à l'écran. */
    private static function retenirQui(int $userId): void
    {
        $moi = self::appeler($userId, 'GET', '/me?$select=displayName,mail,userPrincipalName');
        $adresse = (string) ($moi['corps']['mail'] ?? $moi['corps']['userPrincipalName'] ?? '');

        $agenda = self::appeler($userId, 'GET', '/me/calendar?$select=id,name');

        Database::run(
            'UPDATE outlook_comptes SET compte = ?, calendrier_id = ?, calendrier_nom = ? WHERE user_id = ?',
            [
                mb_substr($adresse, 0, 190),
                mb_substr((string) ($agenda['corps']['id'] ?? ''), 0, 255),
                mb_substr((string) ($agenda['corps']['name'] ?? ''), 0, 190),
                $userId,
            ]
        );
    }

    /* --- Interne ---------------------------------------------------------- */

    /**
     * Demande des jetons, à l'aller comme au renouvellement.
     *
     * @return array|string  la réponse, ou le message d'erreur à montrer
     */
    private static function demanderDesJetons(array $compte, array $champs): array|string
    {
        $reponse = self::requete(
            sprintf(self::JETONS, rawurlencode((string) $compte['locataire'])),
            'POST',
            http_build_query($champs + [
                'client_id' => (string) $compte['client_id'],
                'scope'     => self::PERMISSIONS,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if ($reponse['code'] >= 400 || !isset($reponse['corps']['access_token'])) {
            $dit = (string) ($reponse['corps']['error_description'] ?? $reponse['corps']['error'] ?? '');
            // Microsoft écrit de longs messages à codes : la première ligne suffit.
            $dit = trim((string) preg_replace('/\R.*$/s', '', $dit));

            return $dit === '' ? 'Microsoft a refusé la demande.' : $dit;
        }

        return $reponse['corps'];
    }

    private static function garderLesJetons(int $userId, array $reponse): void
    {
        $duree = (int) ($reponse['expires_in'] ?? 3600);

        Database::run(
            'UPDATE outlook_comptes SET jeton = ?, renouvellement = COALESCE(?, renouvellement),
                    expire_le = ? WHERE user_id = ?',
            [
                (string) $reponse['access_token'],
                isset($reponse['refresh_token']) ? (string) $reponse['refresh_token'] : null,
                date('Y-m-d H:i:s', time() + $duree),
                $userId,
            ]
        );
    }

    /**
     * Un appel HTTPS, et rien d'autre.
     *
     * @return array{code: int, corps: array, entetes: array<string, string>}
     * @throws RuntimeException si la connexion échoue
     */
    private static function requete(string $url, string $methode, ?string $corps, array $entetes): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Impossible de contacter Microsoft.');
        }

        $recues = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_HTTPHEADER     => $entetes,
            CURLOPT_TIMEOUT        => 20,
            // La vérification du certificat n'est pas négociable : c'est elle
            // qui garantit qu'on parle bien à Microsoft.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $ligne) use (&$recues): int {
                $coupe = strpos($ligne, ':');
                if ($coupe !== false) {
                    $recues[strtolower(trim(substr($ligne, 0, $coupe)))] = trim(substr($ligne, $coupe + 1));
                }
                return strlen($ligne);
            },
        ]);
        if ($corps !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
        }

        $recu = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $panne = curl_error($ch);
        curl_close($ch);

        if ($recu === false) {
            throw new RuntimeException('Microsoft n’a pas répondu : ' . $panne);
        }

        $decode = json_decode((string) $recu, true);

        return ['code' => $code, 'corps' => is_array($decode) ? $decode : [], 'entetes' => $recues];
    }

    /** Une suite de caractères tirée au sort, sûre pour un aller-retour. */
    private static function motDePasseDeVoyage(int $octets): string
    {
        return rtrim(strtr(base64_encode(random_bytes($octets)), '+/', '-_'), '=');
    }
}
