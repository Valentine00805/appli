<?php
declare(strict_types=1);

/**
 * L'envoi d'e-mails, sans bibliothèque : curl parle SMTP.
 *
 * Le serveur d'envoi se règle dans config/parametres.php, section
 * « courriel » — pour Gmail, l'adresse et un « mot de passe d'application »
 * (jamais le mot de passe du compte Google). La connexion passe par le port
 * 587, chiffrée par STARTTLS et vérifiée avec les certificats de Windows : le
 * port 465 est intercepté par l'antivirus de ce poste.
 *
 * Tant que rien n'est réglé, l'application utilisée en local range les
 * e-mails dans storage/courriels, pour qu'on puisse les lire ; en ligne, elle
 * refuse de faire semblant d'avoir envoyé.
 */
final class Courriel
{
    public static function configure(): bool
    {
        return (string) Config::get('courriel', 'utilisateur') !== ''
            && (string) Config::get('courriel', 'mot_de_passe') !== '';
    }

    /** Le dossier où atterrissent les e-mails tant que l'envoi n'est pas réglé, en local. */
    public static function dossierLocal(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'courriels';
    }

    /** La requête vient-elle de ce poste ? */
    public static function enLocal(): bool
    {
        return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1', ''], true);
    }

    /**
     * Envoie un e-mail, en texte et en HTML.
     *
     * @return bool vrai s'il est parti (ou, en local sans réglage, rangé)
     */
    public static function envoyer(string $destinataire, string $sujet, string $texte, string $html): bool
    {
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $destinataire . $sujet)) {
            return false;
        }

        $expediteur = (string) (Config::get('courriel', 'expediteur') ?: Config::get('courriel', 'utilisateur'));
        $nom = (string) (Config::get('courriel', 'nom') ?: Config::get('app', 'nom'));
        $message = self::composer($expediteur !== '' ? $expediteur : 'ne-pas-repondre@mes-cours.invalid', $nom, $destinataire, $sujet, $texte, $html);

        if (!self::configure()) {
            if (!self::enLocal()) {
                error_log('Courriel : aucun serveur d’envoi réglé (config/parametres.php, section « courriel »).');
                return false;
            }
            $dossier = self::dossierLocal();
            if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
                return false;
            }
            $nomFichier = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';

            return file_put_contents($dossier . DIRECTORY_SEPARATOR . $nomFichier, $message) !== false;
        }

        $corps = fopen('php://temp', 'w+');
        fwrite($corps, $message);
        rewind($corps);
        $h = curl_init((string) (Config::get('courriel', 'serveur') ?: 'smtp://smtp.gmail.com:587'));
        curl_setopt_array($h, [
            CURLOPT_USE_SSL        => CURLUSESSL_ALL,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_OPTIONS    => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 0,
            CURLOPT_USERNAME       => (string) Config::get('courriel', 'utilisateur'),
            // Google affiche le code par groupes de quatre : les espaces n'en font pas partie.
            CURLOPT_PASSWORD       => str_replace(' ', '', (string) Config::get('courriel', 'mot_de_passe')),
            CURLOPT_MAIL_FROM      => '<' . $expediteur . '>',
            CURLOPT_MAIL_RCPT      => ['<' . $destinataire . '>'],
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $corps,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $ok = curl_exec($h) !== false;
        if (!$ok) {
            error_log('Courriel : envoi refusé — ' . curl_error($h));
        }
        fclose($corps);

        return $ok;
    }

    /** Le message complet, en MIME : une partie texte et une partie HTML, en UTF-8. */
    private static function composer(string $de, string $nom, string $a, string $sujet, string $texte, string $html): string
    {
        $frontiere = 'mc-' . bin2hex(random_bytes(12));
        $domaine = substr((string) strrchr($de, '@'), 1) ?: 'mes-cours.invalid';
        $entete = static fn (string $t): string => '=?UTF-8?B?' . base64_encode($t) . '?=';
        $morceaux = static fn (string $t): string => rtrim(chunk_split(base64_encode($t), 76, "\r\n"));

        return implode("\r\n", [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $entete($nom) . ' <' . $de . '>',
            'To: <' . $a . '>',
            'Subject: ' . $entete($sujet),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domaine . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
            '',
            '--' . $frontiere,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $morceaux($texte),
            '--' . $frontiere,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $morceaux($html),
            '--' . $frontiere . '--',
            '',
        ]);
    }
}
