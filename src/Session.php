<?php
declare(strict_types=1);

/** Session, jeton CSRF et messages flash. */
final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('MESCOURS_SESSID');
        session_start();
    }

    // --- CSRF ---------------------------------------------------------

    public static function jetonCsrf(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Vérifie le jeton envoyé par le formulaire ; coupe la requête si invalide. */
    public static function verifierCsrf(): void
    {
        $envoye = $_POST['_csrf'] ?? '';
        if (!is_string($envoye) || !hash_equals(self::jetonCsrf(), $envoye)) {
            http_response_code(400);
            exit('Session expirée ou jeton invalide. Revenez en arrière et réessayez.');
        }
    }

    // --- Messages flash -----------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashs(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
