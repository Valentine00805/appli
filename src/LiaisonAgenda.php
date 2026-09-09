<?php
declare(strict_types=1);

/**
 * Relier un agenda distant, quel qu'il soit.
 *
 * Une seule application est inscrite chez le fournisseur : celle-ci. Chacun y
 * relie ensuite son propre compte et n'a rien à déclarer — demander à chaque
 * personne d'inscrire une application Azure ou un projet Google reviendrait à
 * réserver la fonctionnalité à qui sait le faire.
 *
 * L'identité de l'application se prouve par PKCE : un code tiré au sort à
 * l'aller, dont seule l'empreinte voyage, et qu'on redonne au retour. Certains
 * fournisseurs réclament en plus un secret ; il s'ajoute alors à la preuve au
 * lieu de la remplacer.
 *
 * Rien ici ne suppose une bibliothèque : curl suffit à parler aux deux.
 */
final class LiaisonAgenda
{
    /** @var array<string, self> une instance par fournisseur, pas davantage */
    private static array $instances = [];

    /** Un jeton demandé un peu avant son terme : l'horloge n'est jamais parfaite. */
    private const MARGE = 120;

    private function __construct(private readonly Fournisseur $f)
    {
    }

    public static function pour(Fournisseur $f): self
    {
        return self::$instances[$f->cle()] ??= new self($f);
    }

    public function fournisseur(): Fournisseur
    {
        return $this->f;
    }

    /* --- L'installation --------------------------------------------------- */

    /** L'application est-elle inscrite chez ce fournisseur ? */
    public function configure(): bool
    {
        return $this->reglage('client_id') !== '';
    }

    /** Un réglage de cet agenda, dans sa propre section de configuration. */
    private function reglage(string $cle): string
    {
        return trim((string) Config::get($this->f->cle() === 'microsoft' ? 'outlook' : 'google', $cle));
    }

    /**
     * Où le fournisseur renvoie le navigateur après l'autorisation.
     *
     * Cette adresse doit correspondre au mot près à celle qui lui a été
     * déclarée. En ligne, on l'inscrit dans la configuration : ce qu'un
     * navigateur annonce comme hôte ne se croit pas sur parole. Faute de quoi
     * on la déduit de la requête, ce qui suffit sur un poste.
     */
    public function adresseDeRetour(): string
    {
        $posee = $this->reglage('adresse_retour');
        if ($posee !== '') {
            return $posee;
        }

        $protocole = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            ? 'https' : 'http';

        return $protocole . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . url($this->f->cheminDeRetour());
    }

    /* --- Le compte relié -------------------------------------------------- */

    /** Ce que l'application sait du compte de quelqu'un chez ce fournisseur. */
    public function compte(int $userId): ?array
    {
        return Database::one(
            'SELECT * FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        );
    }

    /**
     * L'autorisation obtenue couvre-t-elle tout ce qu'on demande aujourd'hui ?
     *
     * Une autorisation donnée avant qu'une permission ne soit ajoutée ne la
     * contient pas : le compte reste relié, mais l'appel échouerait sans qu'on
     * sache dire pourquoi. On préfère le voir venir et proposer de réautoriser.
     */
    public function permissionsCompletes(int $userId): bool
    {
        $eues = (string) ($this->compte($userId)['permissions'] ?? '');
        if ($eues === '') {
            return false;
        }

        foreach (explode(' ', $this->f->permissions()) as $voulue) {
            if ($voulue !== '' && !str_contains($eues, $voulue)) {
                return false;
            }
        }

        return true;
    }

    /** Le compte est-il relié, c'est-à-dire y a-t-il de quoi revenir sans lui ? */
    public function relie(int $userId): bool
    {
        $compte = $this->compte($userId);

        return $compte !== null && (string) $compte['renouvellement'] !== '';
    }

    /** Oublie les jetons : l'application ne touche plus à cet agenda. */
    public function delier(int $userId): void
    {
        $cle = $this->f->cle();
        Database::run('DELETE FROM agenda_liens WHERE user_id = ? AND fournisseur = ?', [$userId, $cle]);
        Database::run('DELETE FROM agenda_calendriers WHERE user_id = ? AND fournisseur = ?', [$userId, $cle]);
        Database::run('DELETE FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?', [$userId, $cle]);
    }

    /* --- L'aller et le retour -------------------------------------------- */

    /**
     * L'adresse où envoyer quelqu'un pour qu'il autorise l'application.
     *
     * Le vérificateur et l'état sont mis de côté le temps du voyage : le
     * premier prouve au retour que c'est bien nous qui étions partis, le
     * second que la réponse répond à notre demande et non à une autre.
     */
    public function adresseDAutorisation(): string
    {
        $verificateur = self::motDePasseDeVoyage(64);
        $etat = self::motDePasseDeVoyage(24);

        Session::garder($this->f->cle() . '_verificateur', $verificateur);
        Session::garder($this->f->cle() . '_etat', $etat);

        $empreinte = rtrim(strtr(base64_encode(hash('sha256', $verificateur, true)), '+/', '-_'), '=');

        return $this->f->urlAutorisation() . '?' . http_build_query(
            [
                'client_id'             => $this->reglage('client_id'),
                'response_type'         => 'code',
                'redirect_uri'          => $this->adresseDeRetour(),
                'scope'                 => $this->f->permissions(),
                'state'                 => $etat,
                'code_challenge'        => $empreinte,
                'code_challenge_method' => 'S256',
            ] + $this->f->parametresDAutorisation()
        );
    }

    /**
     * Échange le code du retour contre des jetons, et retient qui s'est relié.
     *
     * @return ?string  le message d'erreur, ou null si tout s'est bien passé
     */
    public function terminerLaLiaison(int $userId, string $code, string $etat): ?string
    {
        $attendu = Session::reprendre($this->f->cle() . '_etat');
        $verificateur = Session::reprendre($this->f->cle() . '_verificateur');

        if (!is_string($attendu) || $attendu === '' || !hash_equals($attendu, $etat)) {
            return 'La réponse de ' . $this->f->nom()
                . ' ne correspond pas à la demande envoyée. Recommencez.';
        }
        if (!is_string($verificateur) || $verificateur === '') {
            return 'La demande a expiré avant le retour de ' . $this->f->nom() . '. Recommencez.';
        }
        if (!$this->configure()) {
            return 'La liaison avec ' . $this->f->nom()
                . ' n’est pas configurée sur cette installation.';
        }

        $reponse = $this->demanderDesJetons([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->adresseDeRetour(),
            'code_verifier' => $verificateur,
        ]);
        if (is_string($reponse)) {
            return $reponse;
        }

        $this->garderLesJetons($userId, $reponse);
        $this->retenirQui($userId);

        return null;
    }

    /* --- Parler au fournisseur -------------------------------------------- */

    /**
     * Un jeton d'accès valable, renouvelé au besoin.
     *
     * @throws RuntimeException si le compte n'est pas relié ou refuse de l'être
     */
    public function jeton(int $userId): string
    {
        $compte = $this->compte($userId);
        if ($compte === null || (string) $compte['renouvellement'] === '') {
            throw new RuntimeException(
                'Le compte ' . $this->f->nom() . ' n’est pas relié.');
        }

        $expire = $compte['expire_le'] === null ? 0 : (int) strtotime((string) $compte['expire_le']);
        if ((string) $compte['jeton'] !== '' && $expire - self::MARGE > time()) {
            return (string) $compte['jeton'];
        }

        $reponse = $this->demanderDesJetons([
            'grant_type'    => 'refresh_token',
            'refresh_token' => (string) $compte['renouvellement'],
        ]);
        if (is_string($reponse)) {
            throw new RuntimeException(
                'La liaison avec ' . $this->f->nom() . ' a été rompue : ' . $reponse);
        }
        $this->garderLesJetons($userId, $reponse);

        return (string) $reponse['access_token'];
    }

    /**
     * Un appel à l'API de l'agenda.
     *
     * @param  ?array $corps  envoyé en JSON ; null pour une simple lecture
     * @param  array<int, string> $enPlus  en-têtes propres à l'appel
     * @return array{code: int, corps: array, entetes: array<string, string>}
     * @throws RuntimeException si l'appel n'aboutit pas du tout
     */
    public function appeler(
        int $userId,
        string $methode,
        string $chemin,
        ?array $corps = null,
        array $enPlus = []
    ): array {
        $url = str_starts_with($chemin, 'https://') ? $chemin : $this->f->racineApi() . $chemin;

        return self::requete($url, $methode, $corps === null ? null : json_encode($corps), [
            'Authorization: Bearer ' . $this->jeton($userId),
            'Content-Type: application/json',
            'Accept: application/json',
            ...$enPlus,
        ]);
    }

    /** Qui est relié, et sur quel calendrier : de quoi le dire à l'écran. */
    private function retenirQui(int $userId): void
    {
        $qui = $this->f->identite($userId);

        Database::run(
            'UPDATE agenda_comptes SET compte = ?, calendrier_id = ?, calendrier_nom = ?
              WHERE user_id = ? AND fournisseur = ?',
            [
                mb_substr($qui['compte'], 0, 190),
                mb_substr($qui['calendrier_id'], 0, 255),
                mb_substr($qui['calendrier_nom'], 0, 190),
                $userId, $this->f->cle(),
            ]
        );
    }

    /* --- Interne ---------------------------------------------------------- */

    /**
     * Demande des jetons, à l'aller comme au renouvellement.
     *
     * Le secret ne part que s'il y en a un : sans lui, l'application est un
     * client public et PKCE suffit ; avec lui, le fournisseur attend les deux.
     *
     * @return array|string  la réponse, ou le message d'erreur à montrer
     */
    private function demanderDesJetons(array $champs): array|string
    {
        $champs += ['client_id' => $this->reglage('client_id')];

        $secret = $this->reglage('secret');
        if ($secret !== '') {
            $champs['client_secret'] = $secret;
        } elseif ($this->f->secretObligatoire()) {
            return $this->f->nom() . ' exige un secret client, absent de la configuration.';
        }

        $reponse = self::requete(
            $this->f->urlJetons(),
            'POST',
            http_build_query($champs),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if ($reponse['code'] >= 400 || !isset($reponse['corps']['access_token'])) {
            $dit = (string) ($reponse['corps']['error_description'] ?? $reponse['corps']['error'] ?? '');
            // Les fournisseurs écrivent de longs messages à codes : la première
            // ligne suffit.
            $dit = trim((string) preg_replace('/\R.*$/s', '', $dit));

            return $dit === '' ? $this->f->nom() . ' a refusé la demande.' : $dit;
        }

        return $reponse['corps'];
    }

    /** Garde les jetons, en créant la ligne du compte s'il le faut. */
    private function garderLesJetons(int $userId, array $reponse): void
    {
        $duree = (int) ($reponse['expires_in'] ?? 3600);
        $expire = date('Y-m-d H:i:s', time() + $duree);
        $acces = (string) $reponse['access_token'];
        $renouvellement = isset($reponse['refresh_token']) ? (string) $reponse['refresh_token'] : null;

        // Ce que le fournisseur dit avoir accordé, qui n'est pas forcément ce
        // qu'on a demandé : c'est cette liste-là qui fait foi.
        $permissions = isset($reponse['scope']) ? (string) $reponse['scope'] : null;

        Database::run(
            'INSERT INTO agenda_comptes (user_id, fournisseur, jeton, renouvellement, permissions, expire_le)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE jeton = VALUES(jeton),
                 renouvellement = COALESCE(VALUES(renouvellement), renouvellement),
                 permissions = COALESCE(VALUES(permissions), permissions),
                 expire_le = VALUES(expire_le)',
            [$userId, $this->f->cle(), $acces, $renouvellement, $permissions, $expire]
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
            throw new RuntimeException('Impossible de contacter l’agenda.');
        }

        $recues = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_HTTPHEADER     => $entetes,
            CURLOPT_TIMEOUT        => 20,
            // La vérification du certificat n'est pas négociable : c'est elle
            // qui garantit qu'on parle bien à qui l'on croit.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            /*
             * Encore faut-il savoir à qui se fier. WAMP ne livre aucune liste
             * d'autorités de certification et n'en désigne aucune dans
             * php.ini : sans cela, la vérification échoue faute de pouvoir
             * s'exercer. Windows, lui, tient cette liste et la met à jour.
             * Ailleurs — un hébergeur Linux —, l'option est ignorée.
             */
            CURLOPT_SSL_OPTIONS    => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 0,
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
            throw new RuntimeException('L’agenda n’a pas répondu : ' . $panne);
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
