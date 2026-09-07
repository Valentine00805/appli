<?php
declare(strict_types=1);

/**
 * Connexion PDO unique à MySQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            require __DIR__ . '/../views/erreurs/base.php';
            exit;
        }

        return self::$pdo;
    }

    /** Exécute une requête préparée et renvoie le statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Renvoie toutes les lignes. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Renvoie la première ligne ou null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $ligne = self::run($sql, $params)->fetch();
        return $ligne === false ? null : $ligne;
    }

    /** Renvoie la première colonne de la première ligne. */
    public static function valeur(string $sql, array $params = []): mixed
    {
        $valeur = self::run($sql, $params)->fetchColumn();
        return $valeur === false ? null : $valeur;
    }

    public static function dernierId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
