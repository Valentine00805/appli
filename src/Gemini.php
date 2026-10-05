<?php
declare(strict_types=1);

/** Un refus ou une panne côté Gemini, avec de quoi la dire à l'utilisateur. */
final class GeminiErreur extends RuntimeException
{
    /**
     * @param string $nature  cle (refusée), quota (limite atteinte), modele (inconnu), service (panne),
     *                        reseau (injoignable), refus (contenu refusé), vide (rien rendu), requete (autre)
     */
    public function __construct(string $message, public readonly string $nature, public readonly int $http = 0)
    {
        parent::__construct($message);
    }
}

/**
 * Le client de l'API Gemini : du texte, et de la voix.
 *
 * Une seule fonction par service, curl et rien d'autre. La clé de l'utilisateur voyage dans un en-tête
 * (« x-goog-api-key »), jamais dans l'adresse — une adresse se retrouve dans les journaux. Elle n'est
 * ni écrite ni rendue : tout message d'erreur en est nettoyé avant de remonter.
 *
 * Les noms de modèles changent vite chez Google. Chaque service a donc une liste, essayée dans l'ordre
 * quand l'un est inconnu de la clé ; la configuration (section « gemini » : modele_texte, modele_voix)
 * peut en imposer un.
 */
final class Gemini
{
    private const ADRESSE = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * Les modèles, du préféré au dernier recours (liste de la documentation de Google, relue le 5 octobre 2026).
     * « gemini-2.5-flash » n'y est plus : Google en réserve l'accès aux comptes qui s'en servaient déjà, une clé récente
     * n'y a pas droit — c'est un repli qui ne repliait sur rien. Les « lite » ont plus de capacité quand un modèle sature.
     *
     * @var list<string>
     */
    private const MODELES_TEXTE = ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash-lite'];
    /** @var list<string> */
    private const MODELES_VOIX = ['gemini-3.8-flash-tts', 'gemini-3.8-flash-lite-tts'];

    /** Quelques-unes des voix de Google ; le nom est celui qu'il attend. */
    public const VOIX = ['Kore', 'Puck', 'Zephyr', 'Charon', 'Aoede', 'Fenrir'];

    /**
     * Écrit un texte.
     *
     * Avec un schéma, Gemini rend du JSON de cette forme (mode « responseSchema ») au lieu de prose : de quoi
     * obtenir des fiches question/réponse sans avoir à deviner où finit l'une et où commence l'autre.
     *
     * @param array<string, mixed>|null $schema  le schéma JSON attendu, ou null pour du texte libre
     * @return array{0: string, 1: string}  le texte, et le modèle qui l'a écrit
     * @throws GeminiErreur
     */
    public static function texte(string $cle, string $consigne, string $contenu, ?array $schema = null): array
    {
        $corps = [
            'systemInstruction' => ['parts' => [['text' => $consigne]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $contenu]]]],
            'generationConfig' => ['temperature' => 0.4],
        ];
        if ($schema !== null) {
            $corps['generationConfig'] += ['responseMimeType' => 'application/json', 'responseSchema' => $schema];
        }

        return self::essayer($cle, self::modeles('modele_texte', self::MODELES_TEXTE), $corps,
            static function (array $reponse): string {
                $texte = '';
                foreach ((array) ($reponse['candidates'][0]['content']['parts'] ?? []) as $partie) {
                    $texte .= (string) ($partie['text'] ?? '');
                }
                if (trim($texte) === '') {
                    $motif = (string) ($reponse['promptFeedback']['blockReason'] ?? $reponse['candidates'][0]['finishReason'] ?? '');
                    throw new GeminiErreur(
                        $motif !== '' ? $motif : 'Réponse vide.',
                        in_array($motif, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'OTHER', 'RECITATION'], true) ? 'refus' : 'vide'
                    );
                }

                return $texte;
            });
    }

    /**
     * Lit un texte à voix haute.
     *
     * @return array{0: string, 1: int, 2: string}  le son brut (PCM 16 bits mono), sa fréquence, le modèle
     * @throws GeminiErreur
     */
    public static function voix(string $cle, string $texte, string $voix): array
    {
        if (!in_array($voix, self::VOIX, true)) {
            $voix = self::VOIX[0];
        }
        $corps = [
            'contents' => [['parts' => [['text' => $texte]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voix]]],
            ],
        ];
        $frequence = 24000;

        [$pcm, $modele] = self::essayer($cle, self::modeles('modele_voix', self::MODELES_VOIX), $corps,
            static function (array $reponse) use (&$frequence): string {
                foreach ((array) ($reponse['candidates'][0]['content']['parts'] ?? []) as $partie) {
                    $donnees = $partie['inlineData']['data'] ?? null;
                    if (!is_string($donnees) || $donnees === '') {
                        continue;
                    }
                    $octets = base64_decode($donnees, true);
                    if ($octets === false) {
                        continue;
                    }
                    $mime = (string) ($partie['inlineData']['mimeType'] ?? '');
                    if (preg_match('/rate=(\d{4,6})/', $mime, $m) === 1) {
                        $frequence = (int) $m[1];
                    }
                    if (str_starts_with($octets, 'RIFF')) {
                        [$octets, $frequence] = self::pcmDuWav($octets, $frequence);
                    }

                    return $octets;
                }
                throw new GeminiErreur('Aucun son rendu.', 'vide');
            });

        return [$pcm, $frequence, $modele];
    }

    /** Un son PCM 16 bits mono, mis dans un fichier WAV que tout navigateur sait lire. */
    public static function wav(string $pcm, int $frequence = 24000): string
    {
        $taille = strlen($pcm);

        return 'RIFF' . pack('V', 36 + $taille) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $frequence, $frequence * 2, 2, 16)
            . 'data' . pack('V', $taille) . $pcm;
    }

    /**
     * Le son et la fréquence d'un fichier WAV déjà fait (en-tête lu, pas supposé de 44 octets).
     *
     * @return array{0: string, 1: int}
     */
    private static function pcmDuWav(string $wav, int $frequenceParDefaut): array
    {
        $frequence = $frequenceParDefaut;
        $position = 12;
        $longueur = strlen($wav);
        while ($position + 8 <= $longueur) {
            $nom = substr($wav, $position, 4);
            $taille = (int) unpack('V', substr($wav, $position + 4, 4))[1];
            if ($nom === 'fmt ' && $taille >= 8) {
                $frequence = (int) unpack('V', substr($wav, $position + 12, 4))[1];
            }
            if ($nom === 'data') {
                return [substr($wav, $position + 8, $taille), $frequence];
            }
            $position += 8 + $taille + ($taille % 2);
        }

        return [$wav, $frequence];
    }

    /**
     * Les modèles à essayer : celui que la configuration impose, ou la liste d'usage.
     *
     * @param list<string> $defaut
     * @return list<string>
     */
    private static function modeles(string $reglage, array $defaut): array
    {
        $impose = trim((string) Config::get('gemini', $reglage));

        return $impose !== '' ? [$impose] : $defaut;
    }

    /**
     * Appelle les modèles dans l'ordre jusqu'à ce que l'un réponde ; seul un modèle inconnu passe au suivant.
     *
     * @param list<string> $modeles
     * @param callable(array): mixed $lire  tire de la réponse ce qu'on cherche, ou lève GeminiErreur
     * @return array{0: mixed, 1: string}
     */
    /**
     * Essaie les modèles dans l'ordre. On passe au suivant quand le modèle est inconnu de la clé, ou quand il reste
     * surchargé après ses reprises (500, 502, 503) : un modèle saturé ne l'est pas forcément son voisin, qui a sa
     * propre capacité. Les autres refus (clé, limite, contenu bloqué, délai) se répéteraient à l'identique : on ne
     * change pas de modèle pour eux.
     */
    private static function essayer(string $cle, array $modeles, array $corps, callable $lire): array
    {
        $derniere = null;
        $surcharge = null;
        $echecs = [];
        foreach ($modeles as $modele) {
            try {
                $reponse = self::appelerAvecReprises($cle, $modele, $corps);

                return [$lire($reponse), $modele];
            } catch (GeminiErreur $e) {
                $passager = $e->nature === 'service' && in_array($e->http, [500, 502, 503], true);
                if ($e->nature !== 'modele' && !$passager) {
                    throw $e;
                }
                $derniere = $e;
                $surcharge ??= $passager ? $e : null;
                $echecs[] = $e->getMessage() . ' [' . $modele . ']';
            }
        }

        // Tous ont échoué : la surcharge, s'il y en a eu une, est ce qu'il faut dire (un modèle inconnu n'est qu'un détail).
        // Avec plusieurs modèles essayés, le message dit ce que chacun a répondu : « saturé » ou « inconnu de la clé »
        // ne se traitent pas pareil, et on ne le devinerait pas de l'extérieur.
        $cause = $surcharge ?? $derniere ?? new GeminiErreur('Aucun modèle disponible.', 'modele');
        if (count($echecs) > 1) {
            throw new GeminiErreur(implode(' ; ', $echecs), $cause->nature, $cause->http);
        }
        throw $cause;
    }

    /**
     * Rappelle quand le service est surchargé (503, « model is overloaded ») : c'est fréquent, et ça passe
     * presque toujours à la deuxième ou à la troisième tentative. Trois essais au plus, séparés d'une pause
     * qui s'allonge ; tout autre refus (clé, limite, modèle) est définitif et remonte tout de suite.
     *
     * @return array<string, mixed>
     */
    private static function appelerAvecReprises(string $cle, string $modele, array $corps): array
    {
        $pause = (int) (Config::get('gemini', 'pause_reessai') ?? 2);
        for ($essai = 1; ; $essai++) {
            try {
                return self::appeler($cle, $modele, $corps);
            } catch (GeminiErreur $e) {
                // Un 500, 502 ou 503 est passager. Un 504 (délai dépassé chez Google) se répéterait à l'identique : on ne le rappelle pas.
                if ($e->nature !== 'service' || !in_array($e->http, [500, 502, 503], true) || $essai >= 3) {
                    throw $e;
                }
                if ($pause > 0) {
                    sleep($pause * $essai);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private static function appeler(string $cle, string $modele, array $corps): array
    {
        $h = curl_init(self::adresse($modele));
        curl_setopt_array($h, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $cle],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 170,
            // Le certificat de Google se vérifie, avec la liste d'autorités de Windows : WAMP n'en livre aucune et
            // php.ini n'en désigne pas (comme pour le courriel, l'agenda et les notifications). Option ignorée ailleurs.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 0,
        ]);
        $brut = curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        $panne = curl_error($h);
        unset($h);

        if ($brut === false) {
            throw new GeminiErreur(self::nettoyer($panne, $cle), 'reseau');
        }
        $json = json_decode((string) $brut, true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            return $json;
        }

        $message = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
        $statut = is_array($json) ? (string) ($json['error']['status'] ?? '') : '';
        $nature = match (true) {
            $code === 429 || $statut === 'RESOURCE_EXHAUSTED'                       => 'quota',
            $code === 401 || $code === 403 || $statut === 'PERMISSION_DENIED'
                || $statut === 'UNAUTHENTICATED' || stripos($message, 'API key') !== false => 'cle',
            $code === 404 || $statut === 'NOT_FOUND'                                => 'modele',
            $code >= 500                                                            => 'service',
            default                                                                 => 'requete',
        };

        // Le code HTTP accompagne toujours le message : sans lui, « surchargé » et « délai dépassé » se ressemblent.
        throw new GeminiErreur(self::nettoyer('HTTP ' . $code . ($message !== '' ? ' — ' . $message : ''), $cle), $nature, $code);
    }

    /**
     * L'adresse d'un modèle. La clé de l'utilisateur ne part que chez Google — ou vers un faux serveur
     * local, pour les essais : toute autre adresse de configuration est ignorée.
     */
    private static function adresse(string $modele): string
    {
        $base = trim((string) Config::get('gemini', 'adresse'));
        if (preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?/#', $base) !== 1) {
            $base = self::ADRESSE;
        }

        return rtrim($base, '/') . '/models/' . $modele . ':generateContent';
    }

    /** Un message d'erreur sans la clé, au cas où le fournisseur la citerait, et d'une longueur raisonnable. */
    private static function nettoyer(string $message, string $cle): string
    {
        if ($cle !== '') {
            $message = str_replace($cle, '…', $message);
        }

        return mb_substr(trim($message), 0, 300);
    }
}
