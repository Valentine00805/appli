<?php
declare(strict_types=1);

/**
 * Envoyer une notification à un navigateur, même fermé : le « Web Push ».
 *
 * Le navigateur s'abonne auprès de son service de notifications (Google pour
 * Chrome et Edge, Mozilla pour Firefox, Apple pour Safari) et nous confie une
 * adresse et deux clés. Pour lui écrire, on poste à cette adresse un message
 * chiffré pour lui seul, et signé de notre clé d'application.
 *
 * Aucune bibliothèque : tout tient dans l'OpenSSL de PHP.
 *   - RFC 8291 : le chiffrement du message (ECDH P-256, HKDF, AES-128-GCM) ;
 *   - RFC 8188 : son emballage « aes128gcm » ;
 *   - RFC 8292 : la signature VAPID (un jeton JWT en ES256).
 *
 * Les clés de l'application sont rangées en base, et non dans un fichier :
 * l'antivirus de ce poste met en quarantaine des fichiers du projet, et une clé
 * perdue désabonnerait tous les appareils.
 */
final class WebPush
{
    /** L'en-tête DER d'une clé publique P-256, avant ses 65 octets. */
    private const PREFIXE_PUBLIQUE = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Le temps qu'un service garde un message pour un appareil éteint. */
    private const DUREE_DE_VIE = 3600;

    /**
     * Les services de notifications des navigateurs : les seules adresses où
     * l'on accepte d'écrire. Sans cette liste, un abonnement inventé ferait
     * poster le serveur vers n'importe quel site.
     */
    private const SERVICES = '#^https://([a-z0-9-]+\.)*(fcm\.googleapis\.com|android\.googleapis\.com'
        . '|push\.services\.mozilla\.com|notify\.windows\.com|push\.apple\.com)(:443)?/#i';

    /** Cette adresse est-elle celle d'un service de notifications connu ? */
    public static function serviceConnu(string $pointFinal): bool
    {
        return strlen($pointFinal) <= 1024 && preg_match(self::SERVICES, $pointFinal) === 1
            && !preg_match('/\s/', $pointFinal);
    }

    /** @return array{publique: string, privee: string} la publique en base64url (65 octets), la privée en PEM */
    public static function clesVapid(): array
    {
        $lire = static fn (string $cle): string => (string) Database::valeur(
            'SELECT valeur FROM reglages_application WHERE cle = ?', [$cle]);

        if ($lire('vapid_publique') === '' || $lire('vapid_privee') === '') {
            $cle = self::nouvelleCle();
            $pem = '';
            openssl_pkey_export($cle, $pem, null, ['config' => self::configuration()]);
            // INSERT IGNORE : deux premiers envois simultanés gardent la même paire.
            Database::run('INSERT IGNORE INTO reglages_application (cle, valeur) VALUES (?, ?), (?, ?)',
                ['vapid_publique', self::base64url(self::pointPublic($cle)), 'vapid_privee', $pem]);
        }

        return ['publique' => $lire('vapid_publique'), 'privee' => $lire('vapid_privee')];
    }

    /**
     * Envoie un message à un abonnement.
     *
     * @param array{point_final: string, cle_p256dh: string, cle_auth: string} $abonnement
     * @param array<string, mixed> $message ce que le service worker recevra, en JSON
     * @return int le code HTTP du service : 201 reçu ; 404 et 410 : abonnement disparu
     */
    public static function envoyer(array $abonnement, array $message, string $contact): int
    {
        $pointFinal = $abonnement['point_final'];
        if (!self::serviceConnu($pointFinal)) {
            return 400;
        }

        $corps = self::chiffrer(
            (string) json_encode($message, JSON_UNESCAPED_UNICODE),
            self::base64urlDecoder($abonnement['cle_p256dh']),
            self::base64urlDecoder($abonnement['cle_auth'])
        );

        $cles = self::clesVapid();
        $morceaux = parse_url($pointFinal);
        $audience = $morceaux['scheme'] . '://' . $morceaux['host'] . (isset($morceaux['port']) ? ':' . $morceaux['port'] : '');
        $jeton = self::jetonVapid($audience, $contact, $cles['privee']);

        $h = curl_init($pointFinal);
        curl_setopt_array($h, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corps,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            // Comme pour Outlook et Google : le certificat se vérifie, avec la liste
            // d'autorités de Windows, que WAMP ne fournit pas (option ignorée ailleurs).
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 0,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: ' . self::DUREE_DE_VIE,
                'Urgency: high',
                'Authorization: vapid t=' . $jeton . ', k=' . $cles['publique'],
            ],
        ]);
        curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
        unset($h);

        return $code;
    }

    /**
     * Le corps chiffré d'un message (RFC 8291, emballage RFC 8188).
     *
     * La clé éphémère et le sel ne se donnent qu'aux essais, pour retrouver
     * le vecteur de la RFC ; un envoi réel les tire au hasard.
     */
    public static function chiffrer(string $texte, string $publiqueNavigateur, string $secretAuth,
                                    ?OpenSSLAsymmetricKey $ephemere = null, ?string $sel = null): string
    {
        $ephemere ??= self::nouvelleCle();
        $sel ??= random_bytes(16);
        $publiqueServeur = self::pointPublic($ephemere);

        $secretEcdh = openssl_pkey_derive(self::clePublique($publiqueNavigateur), $ephemere, 32);
        if ($secretEcdh === false) {
            throw new RuntimeException('La clé de cet appareil est illisible.');
        }

        // Le secret du message, mêlé au secret d'authentification de l'abonnement.
        $prkCle = hash_hmac('sha256', $secretEcdh, $secretAuth, true);
        $infoCle = "WebPush: info\x00" . $publiqueNavigateur . $publiqueServeur;
        $ikm = hash_hmac('sha256', $infoCle . "\x01", $prkCle, true);

        // La clé et le nonce du contenu, tirés du sel.
        $prk = hash_hmac('sha256', $ikm, $sel, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        // Un seul enregistrement : le texte, puis l'octet qui dit « dernier ».
        $etiquette = '';
        $chiffre = openssl_encrypt($texte . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $etiquette, '', 16);
        if ($chiffre === false) {
            throw new RuntimeException('Le chiffrement du message a échoué.');
        }

        return $sel . pack('N', 4096) . chr(strlen($publiqueServeur)) . $publiqueServeur . $chiffre . $etiquette;
    }

    public static function base64url(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }

    public static function base64urlDecoder(string $texte): string
    {
        $octets = base64_decode(strtr($texte, '-_', '+/'), true);

        return $octets === false ? '' : $octets;
    }

    /* --- Interne ------------------------------------------------------------ */

    /** Le jeton VAPID : qui écrit, à quel service, jusqu'à quand. */
    private static function jetonVapid(string $audience, string $contact, string $privee): string
    {
        $entete = self::base64url('{"typ":"JWT","alg":"ES256"}');
        $charge = self::base64url((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $contact,
        ], JSON_UNESCAPED_SLASHES));

        $signature = '';
        openssl_sign($entete . '.' . $charge, $signature, (string) $privee, OPENSSL_ALGO_SHA256);

        return $entete . '.' . $charge . '.' . self::base64url(self::signatureBrute($signature));
    }

    /** Une signature ECDSA en DER devient les 64 octets R‖S qu'attend un JWT. */
    private static function signatureBrute(string $der): string
    {
        $position = 3;                                   // 30 len 02
        $longueurR = ord($der[$position]);
        $r = substr($der, $position + 1, $longueurR);
        $position += 1 + $longueurR + 1;                 // … 02
        $longueurS = ord($der[$position]);
        $s = substr($der, $position + 1, $longueurS);

        $normaliser = static fn (string $n): string => str_pad(ltrim($n, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $normaliser($r) . $normaliser($s);
    }

    private static function nouvelleCle(): OpenSSLAsymmetricKey
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $cle = @openssl_pkey_new($options) ?: openssl_pkey_new($options + ['config' => self::configuration()]);
        if ($cle === false) {
            throw new RuntimeException('Impossible de créer une clé : ' . (string) openssl_error_string());
        }

        return $cle;
    }

    /**
     * Un fichier de configuration OpenSSL minimal.
     *
     * Sous Windows, PHP ne crée pas de clé sans lui, et le chemin de celui de
     * WAMP n'est pas connu d'avance ; en ligne, il ne sert pas.
     */
    private static function configuration(): string
    {
        $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mescours-openssl.cnf';
        if (!is_file($chemin)) {
            file_put_contents($chemin, "[ req ]\ndefault_bits = 2048\ndistinguished_name = req_dn\n[ req_dn ]\n");
        }

        return $chemin;
    }

    /** Les 65 octets d'une clé publique : 0x04, puis x et y. */
    private static function pointPublic(OpenSSLAsymmetricKey $cle): string
    {
        $details = openssl_pkey_get_details($cle);

        return "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    }

    private static function clePublique(string $point): OpenSSLAsymmetricKey
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new RuntimeException('La clé de cet appareil est illisible.');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(hex2bin(self::PREFIXE_PUBLIQUE) . $point), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $cle = openssl_pkey_get_public($pem);
        if ($cle === false) {
            throw new RuntimeException('La clé de cet appareil est illisible.');
        }

        return $cle;
    }
}
