<?php
declare(strict_types=1);

/**
 * Les liens vers d'autres applications (YouTube, NotebookLM…), rangés par chacun dans le menu en grille.
 *
 * Une adresse vient de l'utilisateur et sera suivie d'un clic : seule une adresse http(s) y passe (jamais
 * « javascript: », « data: » ou autre), sans identifiants dans l'adresse, et elle s'ouvre toujours dans un nouvel onglet,
 * avec rel="noopener noreferrer" (voir le gabarit). Le serveur de l'application ne contacte jamais ces sites. L'icône
 * est celle du site, que le navigateur demande au service de favicons de Google (seul le nom du site part, sans référent) ;
 * si elle ne vient pas, ou si on a choisi un emoji, c'est l'emoji qui s'affiche — choisi, ou proposé d'après le site.
 */
final class LienApp
{
    public const MAX = 24;
    public const NOM_MAX = 40;
    public const URL_MAX = 500;
    public const ICONE_MAX = 4;   // caractères : un emoji, avec ses éventuels modificateurs, ou quelques lettres

    /** L'icône de départ d'après le site : le nom d'hôte (ou son suffixe) → un emoji. */
    private const ICONES = [
        'youtube.com' => '▶️', 'youtu.be' => '▶️', 'notebooklm.google.com' => '📓', 'mail.google.com' => '✉️', 'gmail.com' => '✉️',
        'drive.google.com' => '📁', 'docs.google.com' => '📄', 'sheets.google.com' => '📊', 'slides.google.com' => '📽️',
        'calendar.google.com' => '🗓️', 'gemini.google.com' => '✨', 'maps.google.com' => '🗺️', 'translate.google.com' => '🌐',
        'classroom.google.com' => '🎓', 'meet.google.com' => '📹', 'github.com' => '🐙', 'claude.ai' => '🤖', 'chatgpt.com' => '💬',
        'wikipedia.org' => '📖', 'discord.com' => '🎧', 'spotify.com' => '🎵', 'linkedin.com' => '💼', 'outlook.office.com' => '📧',
        'outlook.live.com' => '📧', 'teams.microsoft.com' => '👥', 'office.com' => '🗃️', 'notion.so' => '🗒️', 'canva.com' => '🎨',
        'deepl.com' => '🔤', 'moodle' => '🎓', 'pronote' => '🏫',
    ];
    public const ICONE_PAR_DEFAUT = '🔗';
    /** Le service d'icônes de sites : on lui ajoute le nom d'hôte (encodé). */
    public const FAVICON = 'https://www.google.com/s2/favicons?sz=64&domain=';

    /** L'adresse de l'icône du site d'un lien. */
    public static function favicon(string $url): string
    {
        return self::FAVICON . rawurlencode((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    }

    /**
     * Vérifie et nettoie ce que le formulaire envoie.
     *
     * @return array{0: ?array{nom: string, url: string, icone: string}, 1: ?string} le lien propre, ou null et la clé de
     *         traduction de ce qui ne va pas
     */
    public static function valider(string $nom, string $url, string $icone): array
    {
        $nom = trim((string) preg_replace('/\s+/u', ' ', $nom));
        if ($nom === '' || mb_strlen($nom) > self::NOM_MAX) {
            return [null, 'lia.err.nom'];
        }
        $adresse = self::adresse($url);
        if ($adresse === null) {
            return [null, 'lia.err.url'];
        }
        $icone = trim($icone);
        if ($icone !== '' && (mb_strlen($icone) > self::ICONE_MAX || preg_match('/[<>&"\'\\\\\p{C}]/u', $icone) === 1)) {
            return [null, 'lia.err.icone'];
        }

        return [['nom' => $nom, 'url' => $adresse, 'icone' => $icone], null];
    }

    /**
     * Une adresse http(s) propre, ou null. Sans schéma (« notebooklm.google.com »), https est ajouté. Refusées : un autre
     * schéma, une adresse sans nom d'hôte, des identifiants dans l'adresse (« https://nom:mot@site »), une adresse trop
     * longue, des espaces ou des caractères de contrôle.
     */
    public static function adresse(string $brute): ?string
    {
        $brute = trim($brute);
        if ($brute === '' || strlen($brute) > self::URL_MAX || preg_match('/[\s\p{C}]/u', $brute) === 1) {
            return null;
        }
        // « //site » (sans schéma, avec deux barres) : trop ambigu, on le refuse plutôt que de le deviner.
        if (str_starts_with($brute, '//')) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $brute) === 1 && preg_match('#^https?://#i', $brute) !== 1) {
            // Un « hôte:port » sans schéma ressemble à un schéma : on ne l'accepte que s'il est suivi d'un port numérique.
            if (preg_match('#^[a-z0-9.\-]+:\d{1,5}(/|$)#i', $brute) !== 1) {
                return null;
            }
            $brute = 'https://' . $brute;
        } elseif (preg_match('#^https?://#i', $brute) !== 1) {
            $brute = 'https://' . $brute;
        }

        $parties = parse_url($brute);
        if (!is_array($parties) || !isset($parties['host']) || isset($parties['user']) || isset($parties['pass'])) {
            return null;
        }
        $hote = strtolower((string) $parties['host']);
        // Un nom d'hôte : lettres, chiffres, tirets, points ; au moins un point (ou « localhost »), pas de point en bord.
        $nomDHote = preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.)+[a-z0-9\-]{2,}$|^localhost$/', $hote) === 1;
        $ipv4 = filter_var($hote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        if (!$nomDHote && !$ipv4) {
            return null;
        }
        $schema = strtolower((string) ($parties['scheme'] ?? 'https'));
        if ($schema !== 'http' && $schema !== 'https') {
            return null;
        }

        return $schema . '://' . $hote . (isset($parties['port']) ? ':' . (int) $parties['port'] : '')
            . ($parties['path'] ?? '') . (isset($parties['query']) ? '?' . $parties['query'] : '')
            . (isset($parties['fragment']) ? '#' . $parties['fragment'] : '');
    }

    /** L'icône d'un lien : celle qu'on a choisie, sinon celle que le site évoque, sinon un maillon de chaîne. */
    public static function icone(string $choisie, string $url): string
    {
        if ($choisie !== '') {
            return $choisie;
        }
        $hote = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $hote = preg_replace('/^www\./', '', $hote) ?? $hote;
        foreach (self::ICONES as $motif => $icone) {
            if ($hote === $motif || str_ends_with($hote, '.' . $motif) || (!str_contains($motif, '.') && str_contains($hote, $motif))) {
                return $icone;
            }
        }

        return self::ICONE_PAR_DEFAUT;
    }

    /**
     * Les liens d'un utilisateur, dans leur ordre, avec l'icône à montrer.
     *
     * @return list<array{id: int, nom: string, url: string, icone: string, icone_choisie: string}>
     */
    public static function duUser(int $userId): array
    {
        $liens = [];
        foreach (Database::all('SELECT id, nom, url, icone FROM liens_apps WHERE user_id = ? ORDER BY position, id', [$userId]) as $l) {
            // Une adresse abîmée en base (modifiée à la main) n'est pas suivie : la ligne est ignorée.
            if (self::adresse((string) $l['url']) === null) {
                continue;
            }
            $liens[] = ['id' => (int) $l['id'], 'nom' => (string) $l['nom'], 'url' => (string) $l['url'],
                        'icone' => self::icone((string) $l['icone'], (string) $l['url']), 'icone_choisie' => (string) $l['icone']];
        }

        return $liens;
    }
}
