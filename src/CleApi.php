<?php
declare(strict_types=1);

/**
 * Les clés d'API que les utilisateurs apportent (Gemini pour commencer).
 *
 * Une clé donne accès à un compte chez un fournisseur — parfois payant : elle se traite comme un
 * mot de passe.
 *
 *  - elle est chiffrée avant d'entrer en base (AES-256-GCM), avec une clé tenue dans
 *    config/parametres.php, hors de la base : qui lirait la base seule ne verrait que du chiffré ;
 *  - le chiffré est lié à sa ligne (compte + fournisseur) : recopié ailleurs, il ne s'ouvre plus ;
 *  - l'écran n'en montre jamais plus que les quatre derniers caractères, gardés à part pour ne pas
 *    avoir à déchiffrer ;
 *  - la table n'est pas dans les sauvegardes exportables : une archive se partage, une clé non.
 *
 * Seul `lire()` rend la clé en clair, pour l'appel au fournisseur ; il ne doit ni la journaliser ni
 * l'afficher.
 */
final class CleApi
{
    public const GEMINI = 'gemini';

    /** Les fournisseurs connus, et le nom sous lequel on les montre. */
    public const FOURNISSEURS = [self::GEMINI => 'Gemini'];

    private const ALGO = 'aes-256-gcm';
    private const IV = 12;
    private const ETIQUETTE = 16;

    /** Le chiffrement est-il en place sur cette installation ? */
    public static function configure(): bool
    {
        return self::secret() !== null && in_array(self::ALGO, openssl_get_cipher_methods(), true);
    }

    /**
     * Une clé a-t-elle l'allure de celles qu'on délivre ? Ce n'est pas un essai auprès du
     * fournisseur : seulement de quoi refuser un texte collé par erreur (espaces, phrase, URL).
     */
    public static function valide(string $cle): bool
    {
        return preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $cle) === 1;
    }

    public static function enregistrer(int $userId, string $fournisseur, string $cle): void
    {
        $secret = self::secret();
        if ($secret === null || !isset(self::FOURNISSEURS[$fournisseur]) || !self::valide($cle)) {
            throw new RuntimeException('Clé d’API non enregistrable.');
        }
        $iv = random_bytes(self::IV);
        $etiquette = '';
        $chiffre = openssl_encrypt($cle, self::ALGO, $secret, OPENSSL_RAW_DATA, $iv, $etiquette,
            self::lien($userId, $fournisseur), self::ETIQUETTE);
        if ($chiffre === false) {
            throw new RuntimeException('Le chiffrement de la clé a échoué.');
        }

        Database::run(
            'INSERT INTO cles_api (user_id, fournisseur, cle_chiffree, fin) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cle_chiffree = VALUES(cle_chiffree), fin = VALUES(fin)',
            [$userId, $fournisseur, base64_encode($iv . $etiquette . $chiffre), substr($cle, -4)]);
    }

    public static function retirer(int $userId, string $fournisseur): void
    {
        Database::run('DELETE FROM cles_api WHERE user_id = ? AND fournisseur = ?', [$userId, $fournisseur]);
    }

    public static function existe(int $userId, string $fournisseur): bool
    {
        return self::fin($userId, $fournisseur) !== null;
    }

    /** Les quatre derniers caractères, pour dire « se termine par … ». Null : aucune clé. */
    public static function fin(int $userId, string $fournisseur): ?string
    {
        $fin = Database::valeur(
            'SELECT fin FROM cles_api WHERE user_id = ? AND fournisseur = ?', [$userId, $fournisseur]);

        return is_string($fin) && $fin !== '' ? $fin : null;
    }

    /**
     * La clé en clair, pour l'appel au fournisseur. Null s'il n'y en a pas, ou si elle ne se
     * déchiffre plus (clé de chiffrement changée, ligne altérée) : on redemande alors la saisie.
     */
    public static function lire(int $userId, string $fournisseur): ?string
    {
        $secret = self::secret();
        $stocke = Database::valeur(
            'SELECT cle_chiffree FROM cles_api WHERE user_id = ? AND fournisseur = ?', [$userId, $fournisseur]);
        if ($secret === null || !is_string($stocke)) {
            return null;
        }
        $brut = base64_decode($stocke, true);
        if ($brut === false || strlen($brut) <= self::IV + self::ETIQUETTE) {
            return null;
        }
        $clair = openssl_decrypt(
            substr($brut, self::IV + self::ETIQUETTE), self::ALGO, $secret, OPENSSL_RAW_DATA,
            substr($brut, 0, self::IV), substr($brut, self::IV, self::ETIQUETTE), self::lien($userId, $fournisseur));

        return $clair === false ? null : $clair;
    }

    /** Ce à quoi le chiffré est attaché : le même texte ne s'ouvre que sur sa propre ligne. */
    private static function lien(int $userId, string $fournisseur): string
    {
        return 'cles_api:' . $userId . ':' . $fournisseur;
    }

    /** La clé de chiffrement : 32 octets, décodés de la configuration locale. Null : non configurée. */
    private static function secret(): ?string
    {
        $brut = base64_decode(trim((string) Config::get('securite', 'cle_chiffrement')), true);

        return is_string($brut) && strlen($brut) === 32 ? $brut : null;
    }
}
