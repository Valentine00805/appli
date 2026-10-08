<?php
declare(strict_types=1);

/** Authentification et utilisateur courant. */
final class Auth
{
    private static ?array $utilisateur = null;

    public static function connecter(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        self::retenirMotDePasse($userId);
        self::$utilisateur = null;
    }

    /**
     * Note dans la session l'empreinte du mot de passe actuel : s'il change —
     * réinitialisé, ou modifié depuis un autre appareil —, les sessions qui en
     * gardent une autre sont fermées.
     */
    public static function retenirMotDePasse(int $userId): void
    {
        $hash = (string) Database::valeur('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        $_SESSION['empreinte_mdp'] = substr(hash('sha256', $hash), 0, 32);
    }

    public static function deconnecter(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$utilisateur = null;
    }

    /**
     * Le compte connecté est-il l'un des administrateurs du site ? Ce sont les adresses e-mail listées dans le réglage
     * « app.administrateurs » du fichier de réglages (en minuscules ou non). Sans liste, personne ne l'est : ce qui est réservé
     * aux administrateurs n'est alors montré qu'à l'ordinateur qui fait tourner l'application (voir peutVoirReglagesDuSite).
     */
    public static function estAdministrateur(): bool
    {
        $courriel = mb_strtolower((string) (self::utilisateur()['email'] ?? ''));
        if ($courriel === '') {
            return false;
        }
        $liste = Config::get('app', 'administrateurs');

        return is_array($liste) && in_array($courriel, array_map(static fn ($a): string => mb_strtolower(trim((string) $a)), $liste), true);
    }

    /**
     * Ce qui concerne le site entier (l'adresse d'envoi des rappels, par exemple) : visible des administrateurs s'il y en a de
     * déclarés ; sinon seulement de l'ordinateur qui fait tourner l'application, jamais d'un compte qui se connecte par Internet.
     */
    public static function peutVoirReglagesDuSite(): bool
    {
        $liste = Config::get('app', 'administrateurs');
        if (is_array($liste) && $liste !== []) {
            return self::estAdministrateur();
        }

        return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1', ''], true);
    }

    public static function utilisateur(): ?array
    {
        if (self::$utilisateur !== null) {
            return self::$utilisateur;
        }
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        $u = Database::one('SELECT id, nom, pseudo, photo_nom, email, fuseau, theme, langue, transcription_vocale, menu_favoris, menu_ordre, created_at, password_hash FROM users WHERE id = ?', [$id]);
        $empreinte = $u === null ? null : substr(hash('sha256', (string) $u['password_hash']), 0, 32);
        if ($u === null || (isset($_SESSION['empreinte_mdp']) && !hash_equals((string) $_SESSION['empreinte_mdp'], $empreinte))) {
            self::deconnecter();
            return null;
        }
        // Une session ouverte avant cette vérification l'adopte.
        $_SESSION['empreinte_mdp'] ??= $empreinte;
        unset($u['password_hash']);
        return self::$utilisateur = $u;
    }

    /** Longueurs admises pour un pseudo. */
    public const PSEUDO_MIN = 3;
    public const PSEUDO_MAX = 30;

    /**
     * Ce qui ne va pas dans un pseudo, ou null s'il convient.
     *
     * Lettres (accents compris), chiffres, point, tiret et tiret bas, sans
     * espace, et commençant par une lettre ou un chiffre. Unique : la
     * comparaison de la base ignore majuscules et accents, « Valou » et
     * « valóu » sont donc un seul et même pseudo.
     */
    public static function problemePseudo(string $pseudo, ?int $sauf = null): ?string
    {
        $longueur = mb_strlen($pseudo);
        if ($longueur < self::PSEUDO_MIN || $longueur > self::PSEUDO_MAX) {
            return t('auth.pseudo_longueur', ['min' => self::PSEUDO_MIN, 'max' => self::PSEUDO_MAX]);
        }
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_.\-]*$/u', $pseudo)) {
            return t('auth.pseudo_caracteres');
        }
        $pris = Database::valeur('SELECT id FROM users WHERE pseudo = ?' . ($sauf === null ? '' : ' AND id <> ?'),
            $sauf === null ? [$pseudo] : [$pseudo, $sauf]);
        if ($pris !== null) {
            return t('auth.pseudo_pris_simple');
        }

        return null;
    }

    /** Le nom à afficher : le pseudo s'il y en a un, sinon le nom. */
    public static function nomAffiche(?array $utilisateur = null): string
    {
        $utilisateur ??= self::utilisateur();
        $pseudo = (string) ($utilisateur['pseudo'] ?? '');

        return $pseudo !== '' ? $pseudo : (string) ($utilisateur['nom'] ?? '');
    }

    /** Faute de mieux : là où l'application a été écrite. */
    public const FUSEAU_PAR_DEFAUT = 'Europe/Paris';

    public static function id(): int
    {
        $u = self::utilisateur();
        if ($u === null) {
            redirect('connexion');
        }
        return (int) $u['id'];
    }

    public static function connecte(): bool
    {
        return self::utilisateur() !== null;
    }

    /**
     * Le fuseau horaire de la personne connectée, ou celui de l'installation.
     *
     * Il est appliqué une fois pour toutes au démarrage : ensuite, chaque
     * date lue ou écrite l'est dans ce fuseau, sans que rien d'autre dans
     * l'application ait à s'en soucier.
     */
    public static function fuseau(): string
    {
        $u = self::utilisateur();
        $dit = trim((string) ($u['fuseau'] ?? ''));

        return self::fuseauValide($dit) ? $dit : self::FUSEAU_PAR_DEFAUT;
    }

    /** Les apparences possibles, et ce qu'on en dit. */
    public const THEMES = [
        'auto'   => ['icone' => '🌗'],
        'clair'  => ['icone' => '☀️'],
        'sombre' => ['icone' => '🌙'],
    ];

    /** L'apparence choisie : « auto » tant qu'on n'a rien choisi. */
    public static function theme(?array $utilisateur = null): string
    {
        $utilisateur ??= self::utilisateur();
        $theme = (string) ($utilisateur['theme'] ?? 'auto');

        return isset(self::THEMES[$theme]) ? $theme : 'auto';
    }

    /** Ce fuseau existe-t-il vraiment ? On n'écrit pas n'importe quoi en base. */
    public static function fuseauValide(string $fuseau): bool
    {
        return $fuseau !== '' && in_array($fuseau, DateTimeZone::listIdentifiers(), true);
    }

    /** Bloque l'accès aux visiteurs non connectés. */
    public static function exiger(): void
    {
        if (!self::connecte()) {
            $_SESSION['_apres_connexion'] = $_SERVER['REQUEST_URI'] ?? null;
            Session::flash('info', t('auth.connectez_vous'));
            redirect('connexion');
        }
    }
}
