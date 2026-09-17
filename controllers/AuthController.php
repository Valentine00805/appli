<?php
declare(strict_types=1);

final class AuthController
{
    public function formulaireInscription(): void
    {
        if (Auth::connecte()) {
            redirect('');
        }
        $this->verifierInscriptionPossible();

        Vue::afficherNu('auth/inscription', [
            'erreurs' => [],
            'codeExige' => $this->codeInscription() !== '',
        ], 'Inscription');
    }

    public function inscrire(): void
    {
        Session::verifierCsrf();
        $this->verifierInscriptionPossible();

        $ip = LimiteurConnexion::adresse();
        $attente = LimiteurConnexion::attenteRestante('', $ip);
        if ($attente !== null) {
            Session::flash('erreur', LimiteurConnexion::message($attente));
            redirect('connexion');
        }

        $nom = post('nom');
        $pseudo = post('pseudo');
        $email = mb_strtolower(post('email'));
        $mdp = $_POST['mot_de_passe'] ?? '';
        $mdp2 = $_POST['mot_de_passe_confirmation'] ?? '';
        $erreurs = [];

        $code = $this->codeInscription();
        if ($code !== '' && !hash_equals($code, (string) ($_POST['code_inscription'] ?? ''))) {
            // Un code faux compte comme un échec : on ne veut pas qu'il se devine
            // par essais successifs non plus.
            LimiteurConnexion::enregistrer('', $ip, false);
            $erreurs['code_inscription'] = 'Code d’inscription incorrect.';
        }

        if (mb_strlen($nom) < 2) {
            $erreurs['nom'] = 'Indiquez un nom d’au moins 2 caractères.';
        }
        if (($probleme = Auth::problemePseudo($pseudo)) !== null) {
            $erreurs['pseudo'] = $probleme;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse e-mail invalide.';
        } elseif (Database::valeur('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            $erreurs['email'] = 'Cette adresse est déjà utilisée.';
        }
        if (strlen($mdp) < 8) {
            $erreurs['mot_de_passe'] = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($mdp !== $mdp2) {
            $erreurs['mot_de_passe_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }

        if ($erreurs !== []) {
            Vue::afficherNu('auth/inscription', [
                'erreurs' => $erreurs,
                'codeExige' => $code !== '',
            ], 'Inscription');
            return;
        }

        try {
            Database::run(
                'INSERT INTO users (nom, pseudo, email, password_hash) VALUES (?, ?, ?, ?)',
                [$nom, $pseudo, $email, password_hash($mdp, PASSWORD_DEFAULT)]
            );
        } catch (PDOException $e) {
            // Pris entre la vérification et l'écriture, par une inscription simultanée.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Vue::afficherNu('auth/inscription', [
                'erreurs' => ['pseudo' => 'Ce pseudo vient d’être pris. Choisissez-en un autre.'],
                'codeExige' => $code !== '',
            ], 'Inscription');
            return;
        }
        $userId = Database::dernierId();
        $this->creerMatieresParDefaut($userId);
        TypesEvenementController::creerParDefaut($userId);
        BudgetController::creerCategoriesParDefaut($userId);

        Auth::connecter($userId);
        Session::flash('succes', 'Bienvenue ' . $pseudo . ' ! Votre espace est prêt.');
        redirect('');
    }

    public function formulaireConnexion(): void
    {
        if (Auth::connecte()) {
            redirect('');
        }
        Vue::afficherNu('auth/connexion', ['erreurs' => []], 'Connexion');
    }

    public function connecter(): void
    {
        Session::verifierCsrf();
        // L'adresse e-mail ou le pseudo : un pseudo n'a jamais d'arobase, une adresse toujours.
        $identifiant = post('identifiant') !== '' ? post('identifiant') : post('email');
        $mdp = $_POST['mot_de_passe'] ?? '';
        $ip = LimiteurConnexion::adresse();

        $utilisateur = str_contains($identifiant, '@')
            ? Database::one('SELECT * FROM users WHERE email = ?', [mb_strtolower($identifiant)])
            : ($identifiant === '' ? null : Database::one('SELECT * FROM users WHERE pseudo = ?', [$identifiant]));

        /*
         * Les tentatives se comptent par compte, quel que soit le nom tapé :
         * sans quoi l'adresse et le pseudo donneraient deux fois plus d'essais.
         * Un identifiant qui ne mène à rien se compte tel quel.
         */
        $email = $utilisateur !== null ? (string) $utilisateur['email'] : mb_strtolower($identifiant);

        // Le blocage est vérifié avant même de regarder le mot de passe : sinon
        // la durée de la réponse trahirait l'existence du compte.
        $attente = LimiteurConnexion::attenteRestante($email, $ip);
        if ($attente !== null) {
            Vue::afficherNu('auth/connexion', [
                'erreurs' => ['global' => LimiteurConnexion::message($attente)],
            ], 'Connexion');
            return;
        }

        if ($utilisateur === null || !password_verify($mdp, $utilisateur['password_hash'])) {
            LimiteurConnexion::enregistrer($email, $ip, false);

            // Message volontairement générique : on n'indique pas si le compte existe.
            usleep(300000);
            Vue::afficherNu('auth/connexion', [
                'erreurs' => ['global' => 'Identifiant ou mot de passe incorrect.'],
            ], 'Connexion');
            return;
        }

        LimiteurConnexion::enregistrer($email, $ip, true);

        if (password_needs_rehash($utilisateur['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($mdp, PASSWORD_DEFAULT), $utilisateur['id'],
            ]);
        }

        $destination = $_SESSION['_apres_connexion'] ?? null;
        Auth::connecter((int) $utilisateur['id']);
        Session::flash('succes', 'Content de vous revoir, ' . Auth::nomAffiche($utilisateur) . '.');

        if (is_string($destination) && $destination !== '') {
            header('Location: ' . $destination);
            exit;
        }
        redirect('');
    }

    public function deconnecter(): void
    {
        Session::verifierCsrf();
        Auth::deconnecter();
        Session::demarrer();
        Session::flash('info', 'Vous êtes déconnecté.');
        redirect('connexion');
    }

    public function compte(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $stats = [
            'cours'      => (int) Database::valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$userId]),
            'matieres'   => (int) Database::valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ?', [$userId]),
            'evenements' => (int) Database::valeur('SELECT COUNT(*) FROM evenements WHERE user_id = ?', [$userId]),
            'fichiers'   => (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE user_id = ?', [$userId]),
            'octets'     => (int) Database::valeur('SELECT COALESCE(SUM(taille), 0) FROM fichiers WHERE user_id = ?', [$userId]),
        ];
        Vue::afficher('auth/compte', [
            'stats'   => $stats,
            'erreurs' => [],
            'fuseau'  => Auth::fuseau(),
            'fuseaux' => self::fuseauxParRegion(),
        ], 'Mon compte');
    }

    /** Choisit ou change son pseudo. */
    public function changerPseudo(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $pseudo = post('pseudo');
        if ($pseudo === (string) (Auth::utilisateur()['pseudo'] ?? '')) {
            Session::flash('info', 'Votre pseudo reste « ' . $pseudo . ' ».');
            redirect('compte');
        }
        if (($probleme = Auth::problemePseudo($pseudo, $userId)) !== null) {
            Session::flash('erreur', $probleme);
            Session::garder('pseudo_saisi', $pseudo);
            redirect('compte');
        }

        try {
            Database::run('UPDATE users SET pseudo = ? WHERE id = ?', [$pseudo, $userId]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('erreur', 'Ce pseudo vient d’être pris. Choisissez-en un autre.');
            Session::garder('pseudo_saisi', $pseudo);
            redirect('compte');
        }

        Session::flash('succes', 'Pseudo enregistré : vous êtes désormais « ' . $pseudo . ' ».');
        redirect('compte');
    }

    /**
     * Change le fuseau horaire de la personne.
     *
     * Les dates sont écrites en heure locale, sans décalage : changer de
     * fuseau ne les déplace pas, il les relit autrement. Un cours noté à 8 h
     * reste à 8 h — ce qui est presque toujours ce qu'on veut en déménageant,
     * mais pas en corrigeant une erreur de réglage. L'écran le dit avant.
     */
    public function changerFuseau(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $fuseau = trim((string) ($_POST['fuseau'] ?? ''));
        if (!Auth::fuseauValide($fuseau)) {
            Session::flash('erreur', 'Ce fuseau horaire n’existe pas.');
            redirect('compte');
        }

        Database::run('UPDATE users SET fuseau = ? WHERE id = ?', [$fuseau, Auth::id()]);
        date_default_timezone_set($fuseau);

        Session::flash('succes', 'Fuseau horaire réglé sur ' . str_replace('_', ' ', $fuseau)
            . ' — il est ' . date('H:i') . ' chez vous.');
        redirect('compte');
    }

    /**
     * La photo de profil d'un compte. Comme son pseudo, elle se montre à tout
     * compte connecté — sauf à ceux qu'il a bloqués.
     */
    public function photo(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $compte = Database::one('SELECT photo_nom, photo_mime FROM users WHERE id = ? AND photo_nom IS NOT NULL', [$id]);
        $chemin = $compte === null || ($id !== Auth::id() && Amis::aBloque($id, Auth::id())) ? null
            : Amis::dossierImages() . DIRECTORY_SEPARATOR . basename((string) $compte['photo_nom']);
        if ($chemin === null || !is_file($chemin) || !in_array($compte['photo_mime'], array_column(Amis::IMAGE_TYPES, 0), true)) {
            http_response_code(404);
            exit('Photo introuvable.');
        }
        header('Content-Type: ' . $compte['photo_mime']);
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        header('Content-Disposition: inline; filename="photo-profil.' . pathinfo($chemin, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: private, max-age=604800, immutable');
        readfile($chemin);
        exit;
    }

    /** Choisit ou change sa photo de profil. */
    public function changerPhoto(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $image = isset($_FILES['photo']) && is_array($_FILES['photo']) && !is_array($_FILES['photo']['name'] ?? null) ? $_FILES['photo'] : null;
        $refus = Amis::changerPhoto(Auth::id(), $image);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? 'Photo de profil enregistrée.');
        redirect('compte');
    }

    public function retirerPhoto(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $retiree = Amis::retirerPhoto(Auth::id());
        Session::flash($retiree ? 'succes' : 'info', $retiree ? 'Photo de profil retirée.' : 'Vous n’aviez pas de photo de profil.');
        redirect('compte');
    }

    /** Active ou coupe la transcription de ses messages vocaux. */
    public function changerTranscription(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $choix = (string) ($_POST['transcription'] ?? '');
        if ($choix !== '0' && $choix !== '1') {
            Session::flash('erreur', 'Choisissez « Activée » ou « Coupée ».');
            redirect('compte');
        }

        Database::run('UPDATE users SET transcription_vocale = ? WHERE id = ?', [(int) $choix, Auth::id()]);
        Session::flash('succes', $choix === '1'
            ? 'Transcription activée : vos prochains messages vocaux seront transcrits.'
            : 'Transcription coupée : vos messages vocaux partiront sans texte.');
        redirect('compte');
    }

    /**
     * Les fuseaux connus, rangés par région.
     *
     * Quatre cents lignes d'affilée ne se lisent pas ; groupées par continent,
     * on trouve la sienne.
     *
     * @return array<string, array<int, string>>
     */
    private static function fuseauxParRegion(): array
    {
        $par = [];
        foreach (DateTimeZone::listIdentifiers() as $fuseau) {
            $coupe = strpos($fuseau, '/');
            $region = $coupe === false ? 'Autres' : substr($fuseau, 0, $coupe);
            $par[$region][] = $fuseau;
        }
        ksort($par);

        return $par;
    }

    public function changerMotDePasse(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $actuel = $_POST['mot_de_passe_actuel'] ?? '';
        $nouveau = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirmation = $_POST['nouveau_mot_de_passe_confirmation'] ?? '';

        $hash = (string) Database::valeur('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if (!password_verify($actuel, $hash)) {
            Session::flash('erreur', 'Mot de passe actuel incorrect.');
            // Le formulaire se rouvre, pour réessayer sans recliquer sur « Modifier ».
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }
        if (strlen($nouveau) < 8) {
            Session::flash('erreur', 'Le nouveau mot de passe doit faire au moins 8 caractères.');
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }
        if ($nouveau !== $confirmation) {
            Session::flash('erreur', 'La confirmation ne correspond pas.');
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }

        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($nouveau, PASSWORD_DEFAULT), $userId,
        ]);
        Session::flash('succes', 'Mot de passe mis à jour.');
        redirect('compte');
    }

    /**
     * Refuse l'inscription quand elle serait ouverte à n'importe qui.
     *
     * En local, une inscription libre ne gêne personne. Dès que l'application
     * est jointe depuis une autre machine, laisser le formulaire ouvert sans
     * code reviendrait à autoriser le premier venu à se créer un compte : on
     * bloque, en expliquant quoi faire.
     */
    private function verifierInscriptionPossible(): void
    {
        if (!Config::get('app', 'inscription_ouverte')) {
            Session::flash('erreur', 'Les inscriptions sont fermées.');
            redirect('connexion');
        }

        if ($this->codeInscription() === '' && !$this->accesLocal()) {
            Session::flash('erreur',
                'Inscription impossible : l’application est accessible depuis le réseau et '
                . 'aucun code d’inscription n’est défini. Renseignez « code_inscription » dans '
                . 'config/parametres.php, ou passez « inscription_ouverte » à false.');
            redirect('connexion');
        }
    }

    private function codeInscription(): string
    {
        return trim((string) Config::get('app', 'code_inscription'));
    }

    /** La requête vient-elle de la machine elle-même ? */
    private function accesLocal(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return in_array($ip, ['127.0.0.1', '::1', ''], true);
    }

    /** Quelques matières pour ne pas démarrer sur une page vide. */
    private function creerMatieresParDefaut(int $userId): void
    {
        $defauts = [
            ['Mathématiques', '#4f46e5'],
            ['Français', '#db2777'],
            ['Histoire-Géographie', '#ca8a04'],
            ['Sciences', '#059669'],
        ];
        foreach ($defauts as [$nom, $couleur]) {
            Database::run(
                'INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)',
                [$userId, $nom, $couleur]
            );
        }
    }
}
