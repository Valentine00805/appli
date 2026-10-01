<?php
declare(strict_types=1);

/**
 * Réinitialiser un mot de passe oublié, par un lien envoyé par e-mail.
 *
 * Le lien porte un jeton tiré au hasard ; la base n'en garde que l'empreinte,
 * si bien qu'une copie de la base ne permet pas de s'en servir. Il vaut une
 * heure, une seule fois, et une nouvelle demande annule la précédente.
 *
 * La réponse à une demande est toujours la même, que le compte existe ou non :
 * la page ne dit pas qui est inscrit.
 */
final class Reinitialisation
{
    /** Durée de validité d'un lien, en minutes. */
    public const DUREE_MINUTES = 60;

    /** Demandes au plus, par compte puis par adresse IP, sur une heure. */
    private const MAX_COMPTE = 3;
    private const MAX_ADRESSE = 10;

    /**
     * Le site (« https://exemple.fr », sans chemin) à partir duquel écrire le
     * lien. Réglé en ligne (« adresse_publique ») : l'hôte qu'annonce un navigateur ne se croit pas
     * sur parole, un lien piégé partirait sinon vers un autre site. En local,
     * on la déduit de la requête.
     */
    public static function adresseDuSite(): ?string
    {
        $posee = rtrim((string) Config::get('app', 'adresse_publique'), '/');
        if ($posee !== '') {
            return $posee;
        }
        $hote = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!Courriel::enLocal() || !preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $hote)) {
            return null;
        }
        $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';

        return ($https ? 'https' : 'http') . '://' . $hote;
    }

    /**
     * Traite une demande : si l'identifiant mène à un compte et que les
     * limites le permettent, un lien part à son adresse. Ne dit rien de plus.
     */
    public static function demander(string $identifiant): void
    {
        $identifiant = trim($identifiant);
        $ip = LimiteurConnexion::adresse();
        $compte = $identifiant === '' ? null : (str_contains($identifiant, '@')
            ? Database::one('SELECT id, email, pseudo, nom, langue FROM users WHERE email = ?', [mb_strtolower($identifiant)])
            : Database::one('SELECT id, email, pseudo, nom, langue FROM users WHERE pseudo = ?', [$identifiant]));

        $parAdresse = (int) Database::valeur(
            'SELECT COUNT(*) FROM reinitialisations_mdp WHERE ip = ? AND cree_le >= UTC_TIMESTAMP() - INTERVAL 1 HOUR', [$ip]
        );
        if ($compte === null || $parAdresse >= self::MAX_ADRESSE) {
            usleep(300000);
            return;
        }
        $parCompte = (int) Database::valeur(
            'SELECT COUNT(*) FROM reinitialisations_mdp WHERE user_id = ? AND cree_le >= UTC_TIMESTAMP() - INTERVAL 1 HOUR', [(int) $compte['id']]
        );
        $site = self::adresseDuSite();
        if ($parCompte >= self::MAX_COMPTE || $site === null) {
            if ($site === null) {
                error_log('Réinitialisation : réglez « adresse_publique » (config/parametres.php, section « app ») pour écrire les liens.');
            }
            return;
        }

        $jeton = bin2hex(random_bytes(32));
        // Une nouvelle demande rend les liens précédents inutilisables.
        Database::run('UPDATE reinitialisations_mdp SET utilise_le = UTC_TIMESTAMP() WHERE user_id = ? AND utilise_le IS NULL', [(int) $compte['id']]);
        Database::run(
            'INSERT INTO reinitialisations_mdp (user_id, jeton_hash, ip, cree_le, expire_le)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
            [(int) $compte['id'], hash('sha256', $jeton), $ip, self::DUREE_MINUTES]
        );

        $lien = $site . url('mot-de-passe/nouveau', ['jeton' => $jeton]);
        $nomApp = (string) Config::get('app', 'nom');
        /*
         * Qui demande n'est pas connecté : la langue de la page est celle du
         * visiteur, pas celle du compte. On écrit l'e-mail dans la sienne.
         */
        $avant = Langue::courante();
        Langue::imposer((string) ($compte['langue'] ?? Langue::PAR_DEFAUT));

        $salut = t('mdp.courriel.salut', ['qui' => (string) ($compte['pseudo'] ?: $compte['nom'])]);
        $demande = t('mdp.courriel.demande', ['appli' => $nomApp]);
        $pasVous = t('mdp.courriel.pas_vous');
        $texte = $salut . "\n\n"
            . $demande . "\n"
            . t('mdp.courriel.ouvrez', ['min' => self::DUREE_MINUTES]) . "\n\n"
            . $lien . "\n\n"
            . $pasVous . "\n";
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1c2033;max-width:520px">'
            . '<p>' . e($salut) . '</p>'
            . '<p>' . e($demande) . '</p>'
            . '<p><a href="' . e($lien) . '" style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:bold">'
            . e(t('mdp.courriel.bouton')) . '</a></p>'
            . '<p style="color:#5b6177;font-size:13px">' . e(t('mdp.courriel.vaut', ['min' => self::DUREE_MINUTES])) . '<br>'
            . '<span style="word-break:break-all">' . e($lien) . '</span></p>'
            . '<p style="color:#5b6177;font-size:13px">' . e($pasVous) . '</p>'
            . '</div>';
        $sujet = t('mdp.courriel.sujet', ['appli' => $nomApp]);
        Langue::imposer($avant);

        if (!Courriel::envoyer((string) $compte['email'], $sujet, $texte, $html)) {
            error_log('Réinitialisation : l’e-mail n’a pas pu partir pour le compte ' . (int) $compte['id'] . '.');
        }
    }

    /** La demande valide qui correspond à un jeton, ou null. */
    public static function trouver(string $jeton): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) {
            return null;
        }

        return Database::one(
            'SELECT r.id, r.user_id, u.email, u.pseudo FROM reinitialisations_mdp r JOIN users u ON u.id = r.user_id
              WHERE r.jeton_hash = ? AND r.utilise_le IS NULL AND r.expire_le > UTC_TIMESTAMP()',
            [hash('sha256', $jeton)]
        );
    }

    /**
     * Choisit le nouveau mot de passe. Le lien ne sert plus ensuite, ni aucun
     * autre de ce compte ; les sessions ouvertes ailleurs sont fermées.
     *
     * @return ?string la raison du refus, ou null
     */
    public static function appliquer(string $jeton, string $nouveau, string $confirmation): ?string
    {
        $demande = self::trouver($jeton);
        if ($demande === null) {
            return t('mdp.lien_perime');
        }
        if (strlen($nouveau) < 8) {
            return t('auth.fl.mdp_court');
        }
        if ($nouveau !== $confirmation) {
            return t('auth.fl.confirmation');
        }
        // Réservé d'abord : deux envois simultanés du même lien n'aboutissent pas deux fois.
        if (Database::run('UPDATE reinitialisations_mdp SET utilise_le = UTC_TIMESTAMP() WHERE id = ? AND utilise_le IS NULL', [(int) $demande['id']])->rowCount() === 0) {
            return t('mdp.lien_perime');
        }
        $userId = (int) $demande['user_id'];
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($nouveau, PASSWORD_DEFAULT), $userId]);
        Database::run('UPDATE reinitialisations_mdp SET utilise_le = UTC_TIMESTAMP() WHERE user_id = ? AND utilise_le IS NULL', [$userId]);
        // Le compte n'est plus bloqué par les essais ratés d'avant.
        Database::run('DELETE FROM tentatives_connexion WHERE email = ? AND reussie = 0', [(string) $demande['email']]);

        return null;
    }
}
