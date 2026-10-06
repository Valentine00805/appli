<?php
declare(strict_types=1);

/**
 * Les serveurs : un espace avec des gens et des salons, comme sur Discord.
 *
 * « serveurs » liste les miens et les invitations reçues ; « serveurs/12 » ouvre le premier salon du serveur 12. Les salons eux-mêmes
 * sont des discussions de groupe (« groupes/{salon} », voir ConversationsController) : ici, on gère le serveur — son nom, ses salons,
 * ses membres, ses invitations.
 */
final class ServeursController
{
    public function index(): void
    {
        Auth::exiger();
        $moi = Auth::id();
        Vue::afficher('serveurs/index', [
            'serveurs' => Serveurs::liste($moi),
            'invitations' => Serveurs::invitations($moi),
        ], t('srv.titre'));
    }

    /** Le formulaire de création, en fenêtre. */
    public function nouveau(): void
    {
        Auth::exiger();
        $donnees = ['aUnPseudo' => (string) (Auth::utilisateur()['pseudo'] ?? '') !== ''];
        if (Vue::enFenetre()) {
            Vue::fragment('serveurs/nouveau', $donnees);
            return;
        }
        Vue::afficher('serveurs/nouveau', $donnees, t('srv.nouveau_titre'));
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$id, $refus] = Serveurs::creer(Auth::id(), (string) ($_POST['nom'] ?? ''), $_POST['icone'] ?? null);
        if ($id === null) {
            Session::flash('erreur', $refus);
            redirect('serveurs');
        }
        Session::flash('succes', t('srv.fl.cree'));
        $this->entrer($id);
    }

    /** Le serveur s'ouvre sur son premier salon. */
    public function voir(int $id): void
    {
        Auth::exiger();
        if (Serveurs::role($id, Auth::id()) === null) {
            Session::flash('erreur', t('srv.fl.introuvable'));
            redirect('serveurs');
        }
        $this->entrer($id);
    }

    /** Les réglages, en fenêtre : le serveur, ses salons, ses membres, les invitations, le départ. */
    public function reglages(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $serveur = Serveurs::serveur($id, $moi);
        if ($serveur === null) {
            Session::flash('erreur', t('srv.fl.introuvable'));
            redirect('serveurs');
        }
        $donnees = [
            'serveur' => $serveur,
            'gere' => Serveurs::gere((string) $serveur['role']),
            'proprietaire' => $serveur['role'] === 'proprietaire',
            'salons' => Serveurs::salons($id, $moi),
            'membres' => Serveurs::membres($id),
            'invites' => Serveurs::invites($id),
            'aInviter' => Serveurs::aInviter($id, $moi),
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('serveurs/reglages', $donnees);
            return;
        }
        Vue::afficher('serveurs/reglages', $donnees, t('srv.reglages_de', ['nom' => (string) $serveur['nom']]));
    }

    public function modifier(int $id): void
    {
        $this->poster();
        $refus = Serveurs::modifier(Auth::id(), $id, (string) ($_POST['nom'] ?? ''), $_POST['icone'] ?? null);
        $this->retourReglages($id, $refus, t('srv.fl.modifie'));
    }

    public function ajouterSalon(int $id): void
    {
        $this->poster();
        [, $refus] = Serveurs::ajouterSalon(Auth::id(), $id, (string) ($_POST['nom'] ?? ''));
        $this->retourReglages($id, $refus, t('srv.fl.salon_cree'));
    }

    public function renommerSalon(int $id, int $salon): void
    {
        $this->poster();
        $refus = Serveurs::renommerSalon(Auth::id(), $id, $salon, (string) ($_POST['nom'] ?? ''));
        $this->retourReglages($id, $refus, t('srv.fl.salon_renomme'));
    }

    public function supprimerSalon(int $id, int $salon): void
    {
        $this->poster();
        $refus = Serveurs::supprimerSalon(Auth::id(), $id, $salon);
        $this->retourReglages($id, $refus, t('srv.fl.salon_supprime'));
    }

    public function inviter(int $id): void
    {
        $this->poster();
        $moi = Auth::id();
        $ids = is_array($_POST['amis'] ?? null) ? array_values(array_unique(array_map('intval', $_POST['amis']))) : [];
        if ($ids === []) {
            $this->retourReglages($id, t('srv.err.choisir_ami'), '');
        }
        $notifications = [];
        $faits = 0;
        $refus = null;
        foreach ($ids as $cible) {
            [$notification, $probleme] = Serveurs::inviter($moi, $id, $cible);
            if ($probleme !== null) {
                $refus = $probleme;
                break;
            }
            $faits++;
            if ($notification !== null) {
                $notifications[] = $notification;
            }
        }
        if ($faits > 0) {
            Session::flash('succes', tn('srv.fl.invites', $faits));
        }
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        }
        $this->redirigerPuisEnvoyer(url('serveurs/' . $id . '/reglages'), $notifications);
    }

    public function annulerInvitation(int $id, int $membre): void
    {
        $this->poster();
        $refus = Serveurs::annulerInvitation(Auth::id(), $id, $membre);
        $this->retourReglages($id, $refus, t('srv.fl.invitation_annulee'));
    }

    public function accepter(int $id): void
    {
        $this->poster();
        $refus = Serveurs::repondre(Auth::id(), $id, true);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('serveurs');
        }
        Session::flash('succes', t('srv.fl.rejoint'));
        $this->entrer($id);
    }

    public function refuser(int $id): void
    {
        $this->poster();
        $refus = Serveurs::repondre(Auth::id(), $id, false);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('srv.fl.refuse'));
        redirect('serveurs');
    }

    public function retirerMembre(int $id, int $membre): void
    {
        $this->poster();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Serveurs::retirer(Auth::id(), $id, $membre);
        $this->retourReglages($id, $refus, t('srv.fl.membre_retire', ['qui' => $pseudo]));
    }

    public function nommerAdmin(int $id, int $membre): void
    {
        $this->poster();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Serveurs::nommerAdmin(Auth::id(), $id, $membre);
        $this->retourReglages($id, $refus, t('srv.fl.nomme_admin', ['qui' => $pseudo]));
    }

    public function retirerAdmin(int $id, int $membre): void
    {
        $this->poster();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Serveurs::retirerAdmin(Auth::id(), $id, $membre);
        $this->retourReglages($id, $refus, t('srv.fl.plus_admin', ['qui' => $pseudo]));
    }

    public function quitter(int $id): void
    {
        $this->poster();
        $nom = (string) (Serveurs::serveur($id, Auth::id())['nom'] ?? '');
        $refus = Serveurs::quitter(Auth::id(), $id);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('serveurs/' . $id);
        }
        Session::flash('succes', t('srv.fl.quitte', ['nom' => $nom]));
        redirect('serveurs');
    }

    public function supprimer(int $id): void
    {
        $this->poster();
        $nom = (string) (Serveurs::serveur($id, Auth::id())['nom'] ?? '');
        $refus = Serveurs::supprimer(Auth::id(), $id);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('serveurs/' . $id);
        }
        Session::flash('succes', t('srv.fl.supprime', ['nom' => $nom]));
        redirect('serveurs');
    }

    // --- Outils --------------------------------------------------------------------------------------------------------

    private function poster(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
    }

    /** Ouvre le serveur sur son premier salon. */
    private function entrer(int $serveur): never
    {
        $salon = Database::valeur('SELECT id FROM conversations WHERE serveur_id = ? ORDER BY position, id LIMIT 1', [$serveur]);
        redirect($salon === null || $salon === false ? 'serveurs' : 'groupes/' . (int) $salon);
    }

    /** Revient aux réglages (la fenêtre reste ouverte) avec le message qui convient. */
    private function retourReglages(int $serveur, ?string $refus, string $succes): never
    {
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } elseif ($succes !== '') {
            Session::flash('succes', $succes);
        }
        redirect(Serveurs::role($serveur, Auth::id()) === null ? 'serveurs' : 'serveurs/' . $serveur . '/reglages');
    }

    /** Renvoie vers une page, puis envoie les notifications en file. */
    private function redirigerPuisEnvoyer(string $adresse, array $notifications): never
    {
        if ($notifications === []) {
            header('Location: ' . $adresse);
            exit;
        }
        session_write_close();
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $adresse);
        header('Content-Length: 0');
        header('Connection: close');
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($notifications as $n) {
            FileNotifications::envoyer($n);
        }
        exit;
    }
}
