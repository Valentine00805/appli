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
        ], t('titre.inscription'));
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
            $erreurs['code_inscription'] = t('auth.fl.code_incorrect');
        }

        if (mb_strlen($nom) < 2) {
            $erreurs['nom'] = t('auth.fl.nom_court');
        }
        if (($probleme = Auth::problemePseudo($pseudo)) !== null) {
            $erreurs['pseudo'] = $probleme;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = t('auth.fl.email_invalide');
        } elseif (Database::valeur('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            $erreurs['email'] = t('auth.fl.email_prise');
        }
        if (strlen($mdp) < 8) {
            $erreurs['mot_de_passe'] = t('auth.fl.mdp_court');
        } elseif ($mdp !== $mdp2) {
            $erreurs['mot_de_passe_confirmation'] = t('auth.fl.mdp_different');
        }

        if ($erreurs !== []) {
            Vue::afficherNu('auth/inscription', [
                'erreurs' => $erreurs,
                'codeExige' => $code !== '',
            ], t('titre.inscription'));
            return;
        }

        try {
            Database::run(
                'INSERT INTO users (nom, pseudo, email, password_hash, langue) VALUES (?, ?, ?, ?, ?)',
                [$nom, $pseudo, $email, password_hash($mdp, PASSWORD_DEFAULT), Langue::courante()]
            );
        } catch (PDOException $e) {
            // Pris entre la vérification et l'écriture, par une inscription simultanée.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Vue::afficherNu('auth/inscription', [
                'erreurs' => ['pseudo' => t('auth.fl.pseudo_pris')],
                'codeExige' => $code !== '',
            ], t('titre.inscription'));
            return;
        }
        $userId = Database::dernierId();
        $this->creerMatieresParDefaut($userId);
        TypesEvenementController::creerParDefaut($userId);
        BudgetController::creerCategoriesParDefaut($userId);

        Auth::connecter($userId);
        Langue::retenirPourLeVisiteur(Langue::courante());
        Session::flash('succes', t('auth.fl.bienvenue', ['qui' => $pseudo]));
        redirect('');
    }

    /**
     * Le choix de langue d'un visiteur, sur une page ouverte sans compte. Il se garde
     * dans un cookie ; un compte, lui, garde la sienne dans « Mon compte ».
     */
    public function choisirLangueVisiteur(): void
    {
        Session::verifierCsrf();
        Langue::retenirPourLeVisiteur(post('langue'));
        repartir_vers('connexion');
    }

    public function formulaireConnexion(): void
    {
        if (Auth::connecte()) {
            redirect('');
        }
        Vue::afficherNu('auth/connexion', ['erreurs' => []], t('titre.connexion'));
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
            ], t('titre.connexion'));
            return;
        }

        if ($utilisateur === null || !password_verify($mdp, $utilisateur['password_hash'])) {
            LimiteurConnexion::enregistrer($email, $ip, false);

            // Message volontairement générique : on n'indique pas si le compte existe.
            usleep(300000);
            Vue::afficherNu('auth/connexion', [
                'erreurs' => ['global' => t('auth.fl.identifiants')],
            ], t('titre.connexion'));
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
        Langue::imposer((string) $utilisateur['langue']);
        Langue::retenirPourLeVisiteur(Langue::courante());
        Session::flash('succes', t('auth.fl.content_revoir', ['qui' => Auth::nomAffiche($utilisateur)]));

        if (is_string($destination) && $destination !== '') {
            header('Location: ' . $destination);
            exit;
        }
        redirect('');
    }

    /** « Mot de passe oublié » : on donne son adresse ou son pseudo. */
    public function formulaireOubli(): void
    {
        if (Auth::connecte()) {
            redirect('compte');
        }
        Vue::afficherNu('auth/oubli', ['envoye' => false], t('auth.oubli_titre'));
    }

    public function demanderReinitialisation(): void
    {
        Session::verifierCsrf();
        Reinitialisation::demander(post('identifiant'));
        // La même réponse, que le compte existe ou non.
        Vue::afficherNu('auth/oubli', ['envoye' => true], t('auth.oubli_titre'));
    }

    /** Le lien reçu par e-mail : choisir un nouveau mot de passe. */
    public function formulaireNouveau(): void
    {
        $jeton = (string) ($_GET['jeton'] ?? '');
        $demande = Reinitialisation::trouver($jeton);
        // Le jeton ne fuit pas par l'en-tête Referer : le site entier est en « same-origin » (.htaccess).
        Vue::afficherNu('auth/nouveau_mdp', [
            'jeton' => $demande === null ? '' : $jeton,
            'compte' => $demande,
            'erreur' => null,
        ], t('auth.nouveau_mdp_titre'));
    }

    public function reinitialiser(): void
    {
        Session::verifierCsrf();
        $jeton = (string) ($_POST['jeton'] ?? '');
        $refus = Reinitialisation::appliquer($jeton, (string) ($_POST['mot_de_passe'] ?? ''), (string) ($_POST['mot_de_passe_confirmation'] ?? ''));
        if ($refus !== null) {
            $demande = Reinitialisation::trouver($jeton);
            Vue::afficherNu('auth/nouveau_mdp', [
                'jeton' => $demande === null ? '' : $jeton,
                'compte' => $demande,
                'erreur' => $refus,
            ], t('auth.nouveau_mdp_titre'));
            return;
        }
        // Qui était connecté (sur ce navigateur) doit se reconnecter, comme les autres appareils.
        if (Auth::connecte()) {
            Auth::deconnecter();
            Session::demarrer();
        }
        Session::flash('succes', t('auth.fl.mdp_change'));
        redirect('connexion');
    }

    public function deconnecter(): void
    {
        Session::verifierCsrf();
        Auth::deconnecter();
        Session::demarrer();
        Session::flash('info', t('auth.fl.deconnecte'));
        redirect('connexion');
    }

    public function compte(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        // « ?apercu_langue=en » montre la page dans cette langue sans rien enregistrer : seul « Valider » écrit.
        $apercuLangue = null;
        $demandee = $_GET['apercu_langue'] ?? null;
        if (is_string($demandee) && isset(Langue::LANGUES[$demandee])) {
            Langue::imposer($demandee);
            $apercuLangue = $demandee;
        }
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
            'calendrierAmis' => Partages::reglagesCalendrier($userId),
            'partagesDansDiscussion' => Partages::dansLaDiscussion($userId),
            'calendriersAmis' => Partages::calendriersAvecMesAmis($userId),
            'fuseaux' => self::fuseauxParRegion(),
            'apercuLangue' => $apercuLangue,
        ], t('titre.mon_compte'));
    }

    /** Choisit ou change son pseudo. */
    public function changerPseudo(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $pseudo = post('pseudo');
        if ($pseudo === (string) (Auth::utilisateur()['pseudo'] ?? '')) {
            Session::flash('info', t('auth.fl.pseudo_inchange', ['pseudo' => $pseudo]));
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
            Session::flash('erreur', t('auth.fl.pseudo_pris'));
            Session::garder('pseudo_saisi', $pseudo);
            redirect('compte');
        }

        Session::flash('succes', t('auth.fl.pseudo_enregistre', ['pseudo' => $pseudo]));
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
    /** La langue de l'application. */
    public function changerLangue(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $langue = post('langue');
        if (!isset(Langue::LANGUES[$langue])) {
            $langue = Langue::PAR_DEFAUT;
        }
        Database::run('UPDATE users SET langue = ? WHERE id = ?', [$langue, Auth::id()]);
        // Le message part dans la langue qu'on vient de choisir.
        Langue::imposer($langue);
        Langue::retenirPourLeVisiteur($langue);
        Session::flash('succes', t('langue.enregistree', ['nom' => Langue::LANGUES[$langue]['nom']]));
        redirect('compte');
    }

    /**
     * Enregistre la clé d'API Gemini de l'utilisateur.
     *
     * La clé arrive en POST seulement, jamais dans une adresse ; elle n'est ni rendue à l'écran, ni
     * mise dans un message, ni dans le journal. Le texte est nettoyé des espaces qu'un copier-coller
     * ajoute volontiers, puis jugé à son allure seule — aucun appel au fournisseur ici.
     */
    public function enregistrerCleGemini(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        if (!CleApi::configure()) {
            Session::flash('erreur', t('gemini.non_configure'));
            redirect('compte');
        }
        $cle = CleApi::nettoyer(post('cle_gemini'));
        if (!CleApi::valide($cle)) {
            // La longueur reçue aide à comprendre (une ligne de trop, une clé tronquée) sans rien révéler de la clé.
            Session::flash('erreur', t('gemini.invalide', ['n' => mb_strlen($cle)]));
            redirect('compte');
        }
        CleApi::enregistrer(Auth::id(), CleApi::GEMINI, $cle);
        Session::flash('succes', t('gemini.enregistree', ['fin' => substr($cle, -4)]));
        redirect('compte');
    }

    /** Retire la clé d'API Gemini : elle est effacée de la base, pas seulement cachée. */
    public function retirerCleGemini(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        CleApi::retirer(Auth::id(), CleApi::GEMINI);
        Session::flash('info', t('gemini.retiree'));
        redirect('compte');
    }

    /** L'apparence : claire, sombre, ou celle de l'appareil. */
    /**
     * Garde les favoris du menu en grille (la liste des sections, dans l'ordre). Appelée par le script à chaque
     * changement, sans recharger la page : elle ne répond rien, sinon un code (204 si c'est gardé). Seules les clés
     * connues du catalogue sont retenues (voir Menu::nettoyer).
     */
    public function menuFavoris(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $favoris = Menu::nettoyer((array) ($_POST['favoris'] ?? []));
        Database::run('UPDATE users SET menu_favoris = ? WHERE id = ?', [json_encode($favoris), Auth::id()]);
        http_response_code(204);
        exit;
    }

    public function changerTheme(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $theme = post('theme');
        if (!isset(Auth::THEMES[$theme])) {
            $theme = 'auto';
        }
        Database::run('UPDATE users SET theme = ? WHERE id = ?', [$theme, Auth::id()]);
        Session::flash('succes', t('apparence.enregistree', ['nom' => mb_strtolower(t('apparence.' . $theme))]));
        redirect('compte');
    }

    public function changerFuseau(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $fuseau = trim((string) ($_POST['fuseau'] ?? ''));
        if (!Auth::fuseauValide($fuseau)) {
            Session::flash('erreur', t('auth.fl.fuseau_inconnu'));
            redirect('compte');
        }

        Database::run('UPDATE users SET fuseau = ? WHERE id = ?', [$fuseau, Auth::id()]);
        date_default_timezone_set($fuseau);

        Session::flash('succes', t('auth.fl.fuseau_regle', [
            'fuseau' => str_replace('_', ' ', $fuseau),
            'heure' => heure_courte(time()),
        ]));
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
            exit(t('corps.photo_introuvable'));
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
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('auth.fl.photo_enregistree'));
        redirect('compte');
    }

    public function retirerPhoto(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $retiree = Amis::retirerPhoto(Auth::id());
        Session::flash($retiree ? 'succes' : 'info', t($retiree ? 'auth.fl.photo_retiree' : 'auth.fl.pas_de_photo'));
        redirect('compte');
    }

    /** Active ou coupe la transcription de ses messages vocaux. */
    public function changerTranscription(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $choix = (string) ($_POST['transcription'] ?? '');
        if ($choix !== '0' && $choix !== '1') {
            Session::flash('erreur', t('auth.fl.transcription_choix'));
            redirect('compte');
        }

        Database::run('UPDATE users SET transcription_vocale = ? WHERE id = ?', [(int) $choix, Auth::id()]);
        Session::flash('succes', t($choix === '1' ? 'auth.fl.transcription_on' : 'auth.fl.transcription_off'));
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
            Session::flash('erreur', t('auth.fl.mdp_actuel'));
            // Le formulaire se rouvre, pour réessayer sans recliquer sur « Modifier ».
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }
        if (strlen($nouveau) < 8) {
            Session::flash('erreur', t('auth.fl.nouveau_mdp_court'));
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }
        if ($nouveau !== $confirmation) {
            Session::flash('erreur', t('auth.fl.confirmation'));
            Session::garder('mot_de_passe_ouvert', true);
            redirect('compte');
        }

        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($nouveau, PASSWORD_DEFAULT), $userId,
        ]);
        // Cette session reste ouverte ; celles des autres appareils se ferment.
        Auth::retenirMotDePasse($userId);
        Session::flash('succes', t('auth.fl.mdp_maj'));
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
            Session::flash('erreur', t('auth.fl.inscriptions_fermees'));
            redirect('connexion');
        }

        if ($this->codeInscription() === '' && !$this->accesLocal()) {
            Session::flash('erreur', t('auth.fl.inscription_impossible'));
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
            [t('mat.defaut.maths'), '#4f46e5'],
            [t('mat.defaut.langue'), '#db2777'],
            [t('mat.defaut.histoire_geo'), '#ca8a04'],
            [t('mat.defaut.sciences'), '#059669'],
        ];
        foreach ($defauts as [$nom, $couleur]) {
            Database::run(
                'INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)',
                [$userId, $nom, $couleur]
            );
        }
    }
}
