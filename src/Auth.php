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
        $u = Database::one('SELECT id, nom, email, created_at FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            self::deconnecter();
            return null;
        }
        return self::$utilisateur = $u;
    }

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
