<?php
declare(strict_types=1);

/**
 * Limitation du rythme des tentatives de connexion.
 *
 * Un mot de passe se devine par essais successifs si rien ne les ralentit. On
 * compte donc les échecs récents, par compte et par adresse, et on bloque
 * temporairement au-delà d'un seuil. Le compteur d'un compte est remis à zéro
 * dès qu'une connexion réussit, pour ne pas pénaliser quelqu'un qui a fini par
 * retrouver son mot de passe.
 */
final class LimiteurConnexion
{
    /** Durée sur laquelle on compte les échecs, en minutes. */
    private const FENETRE = 15;

    /** Échecs tolérés sur un même compte avant blocage. */
    private const MAX_COMPTE = 5;

    /** Échecs tolérés depuis une même adresse, tous comptes confondus. */
    private const MAX_ADRESSE = 20;

    /** Durée du blocage, en minutes. */
    private const BLOCAGE = 15;

    /** Au-delà, les traces ne servent plus à rien. */
    private const RETENTION_HEURES = 24;

    /**
     * Combien de secondes reste-t-il à attendre ? null si la voie est libre.
     */
    public static function attenteRestante(string $email, string $ip): ?int
    {
        foreach ([['email', $email, self::MAX_COMPTE], ['ip', $ip, self::MAX_ADRESSE]] as [$colonne, $valeur, $seuil]) {
            if ($valeur === '') {
                continue;
            }

            $ligne = Database::one(
                "SELECT COUNT(*) AS echecs, MAX(created_at) AS dernier
                 FROM tentatives_connexion
                 WHERE `$colonne` = ? AND reussie = 0
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$valeur, self::FENETRE]
            );

            if ($ligne === null || (int) $ligne['echecs'] < $seuil) {
                continue;
            }

            $fin = strtotime((string) $ligne['dernier']) + self::BLOCAGE * 60;
            $reste = $fin - time();
            if ($reste > 0) {
                return $reste;
            }
        }

        return null;
    }

    /** Garde la trace d'un essai. */
    public static function enregistrer(string $email, string $ip, bool $reussie): void
    {
        Database::run(
            'INSERT INTO tentatives_connexion (email, ip, reussie) VALUES (?, ?, ?)',
            [$email !== '' ? mb_substr($email, 0, 190) : null, $ip, $reussie ? 1 : 0]
        );

        if ($reussie && $email !== '') {
            // La connexion a fini par aboutir : on repart de zéro pour ce compte.
            Database::run(
                'DELETE FROM tentatives_connexion WHERE email = ? AND reussie = 0',
                [$email]
            );
        }

        // Purge de temps en temps, sans tâche planifiée.
        if (random_int(1, 50) === 1) {
            Database::run(
                'DELETE FROM tentatives_connexion WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)',
                [self::RETENTION_HEURES]
            );
        }
    }

    /** Message d'attente, en minutes arrondies vers le haut. */
    public static function message(int $secondes): string
    {
        $minutes = max(1, (int) ceil($secondes / 60));
        return sprintf(
            'Trop de tentatives de connexion. Réessayez dans %d minute%s.',
            $minutes,
            $minutes > 1 ? 's' : ''
        );
    }

    /** Adresse de l'appelant, telle que la voit le serveur. */
    public static function adresse(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        // On ne fait pas confiance aux en-têtes de proxy : ils se falsifient et
        // permettraient de contourner la limite en changeant d'adresse annoncée.
        return $ip !== '' ? mb_substr($ip, 0, 45) : 'inconnue';
    }
}
