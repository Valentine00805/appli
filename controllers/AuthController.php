<?php
declare(strict_types=1);

final class AuthController
{
    public function formulaireInscription(): void
    {
        if (Auth::connecte()) {
            redirect('');
        }
        if (!Config::get('app', 'inscription_ouverte')) {
            Session::flash('erreur', 'Les inscriptions sont fermées.');
            redirect('connexion');
        }
        Vue::afficherNu('auth/inscription', ['erreurs' => []], 'Inscription');
    }

    public function inscrire(): void
    {
        Session::verifierCsrf();
        if (!Config::get('app', 'inscription_ouverte')) {
            redirect('connexion');
        }

        $nom = post('nom');
        $email = mb_strtolower(post('email'));
        $mdp = $_POST['mot_de_passe'] ?? '';
        $mdp2 = $_POST['mot_de_passe_confirmation'] ?? '';
        $erreurs = [];

        if (mb_strlen($nom) < 2) {
            $erreurs['nom'] = 'Indiquez un nom d’au moins 2 caractères.';
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
            Vue::afficherNu('auth/inscription', ['erreurs' => $erreurs], 'Inscription');
            return;
        }

        Database::run(
            'INSERT INTO users (nom, email, password_hash) VALUES (?, ?, ?)',
            [$nom, $email, password_hash($mdp, PASSWORD_DEFAULT)]
        );
        $userId = Database::dernierId();
        $this->creerMatieresParDefaut($userId);
        TypesEvenementController::creerParDefaut($userId);
        BudgetController::creerCategoriesParDefaut($userId);

        Auth::connecter($userId);
        Session::flash('succes', 'Bienvenue ' . $nom . ' ! Votre espace est prêt.');
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
        $email = mb_strtolower(post('email'));
        $mdp = $_POST['mot_de_passe'] ?? '';

        $utilisateur = Database::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($utilisateur === null || !password_verify($mdp, $utilisateur['password_hash'])) {
            // Message volontairement générique : on n'indique pas si le compte existe.
            usleep(300000);
            Vue::afficherNu('auth/connexion', [
                'erreurs' => ['global' => 'Adresse e-mail ou mot de passe incorrect.'],
            ], 'Connexion');
            return;
        }

        if (password_needs_rehash($utilisateur['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($mdp, PASSWORD_DEFAULT), $utilisateur['id'],
            ]);
        }

        $destination = $_SESSION['_apres_connexion'] ?? null;
        Auth::connecter((int) $utilisateur['id']);
        Session::flash('succes', 'Content de vous revoir, ' . $utilisateur['nom'] . '.');

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
        Vue::afficher('auth/compte', ['stats' => $stats, 'erreurs' => []], 'Mon compte');
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
            redirect('compte');
        }
        if (strlen($nouveau) < 8) {
            Session::flash('erreur', 'Le nouveau mot de passe doit faire au moins 8 caractères.');
            redirect('compte');
        }
        if ($nouveau !== $confirmation) {
            Session::flash('erreur', 'La confirmation ne correspond pas.');
            redirect('compte');
        }

        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($nouveau, PASSWORD_DEFAULT), $userId,
        ]);
        Session::flash('succes', 'Mot de passe mis à jour.');
        redirect('compte');
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
