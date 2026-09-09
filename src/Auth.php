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
        self::$utilisateur = null;
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

    public static function utilisateur(): ?array
    {
        if (self::$utilisateur !== null) {
            return self::$utilisateur;
        }
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        $u = Database::one('SELECT id, nom, email, fuseau, created_at FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            self::deconnecter();
            return null;
        }
        return self::$utilisateur = $u;
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
            Session::flash('info', 'Connectez-vous pour accéder à cette page.');
            redirect('connexion');
        }
    }
}
