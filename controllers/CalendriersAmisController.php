<?php
declare(strict_types=1);

/**
 * Les calendriers partagés entre amis : la liste, la création, les réglages d'un calendrier (nom, couleur, membres), et ses évènements.
 *
 * Tout s'ouvre en fenêtre depuis le volet du calendrier ; chaque page répond aussi en entier quand on l'ouvre directement. Ce qui se voit
 * dans le calendrier lui-même (les évènements, les cases du volet) passe par CalendrierController et Agenda.
 */
final class CalendriersAmisController
{
    /** Mes calendriers partagés. */
    public function index(): void
    {
        Auth::exiger();
        $donnees = ['calendriers' => CalendriersAmis::liste(Auth::id())];
        $this->afficher('calendriers-amis/index', $donnees, t('cam.titre'));
    }

    public function nouveau(): void
    {
        Auth::exiger();
        $donnees = ['amis' => Amis::liste(Auth::id()), 'aUnPseudo' => (string) (Auth::utilisateur()['pseudo'] ?? '') !== ''];
        $this->afficher('calendriers-amis/nouveau', $donnees, t('cam.nouveau_titre'));
    }

    public function creer(): void
    {
        $this->poster();
        $ids = is_array($_POST['amis'] ?? null) ? array_map('intval', $_POST['amis']) : [];
        [$id, $refus] = CalendriersAmis::creer(Auth::id(), (string) ($_POST['nom'] ?? ''), self::couleurChoisie(), $ids);
        if ($id === null) {
            Session::flash('erreur', (string) $refus);
            redirect('calendriers-amis/nouveau');
        }
        Session::flash('succes', t('cam.fl.cree'));
        redirect('calendriers-amis/' . $id);
    }

    /** Les réglages d'un calendrier : ses membres, ses prochains évènements, et de quoi le gérer ou le quitter. */
    public function reglages(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $calendrier = CalendriersAmis::calendrier($id, $moi);
        if ($calendrier === null) {
            Session::flash('erreur', t('cam.err.introuvable'));
            redirect('calendriers-amis');
        }
        $donnees = [
            'calendrier' => $calendrier,
            'membres' => CalendriersAmis::membres($id),
            'aAjouter' => $calendrier['est_proprietaire'] ? CalendriersAmis::aAjouter($id, $moi) : [],
            'aVenir' => CalendriersAmis::aVenir($id),
        ];
        $this->afficher('calendriers-amis/reglages', $donnees, (string) $calendrier['nom']);
    }

    public function modifier(int $id): void
    {
        $this->poster();
        $refus = CalendriersAmis::modifier(Auth::id(), $id, (string) ($_POST['nom'] ?? ''), self::couleurChoisie());
        $this->retourReglages($id, $refus, t('cam.fl.modifie'));
    }

    public function ajouterMembres(int $id): void
    {
        $this->poster();
        $ids = is_array($_POST['amis'] ?? null) ? $_POST['amis'] : [];
        if ($ids === []) {
            $this->retourReglages($id, t('cam.err.choisir_ami'), '');
        }
        $n = CalendriersAmis::ajouterMembres(Auth::id(), $id, $ids);
        $this->retourReglages($id, $n === 0 ? t('cam.err.aucun_ajoute') : null, tn('cam.fl.membres_ajoutes', $n));
    }

    public function retirerMembre(int $id, int $membre): void
    {
        $this->poster();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = CalendriersAmis::retirerMembre(Auth::id(), $id, $membre);
        $this->retourReglages($id, $refus, t('cam.fl.membre_retire', ['qui' => $pseudo]));
    }

    public function quitter(int $id): void
    {
        $this->poster();
        $nom = (string) (CalendriersAmis::calendrier($id, Auth::id())['nom'] ?? '');
        $refus = CalendriersAmis::quitter(Auth::id(), $id);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('calendriers-amis/' . $id);
        }
        Session::flash('succes', t('cam.fl.quitte', ['nom' => $nom]));
        redirect('calendriers-amis');
    }

    public function supprimer(int $id): void
    {
        $this->poster();
        $nom = (string) (CalendriersAmis::calendrier($id, Auth::id())['nom'] ?? '');
        $refus = CalendriersAmis::supprimer(Auth::id(), $id);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('calendriers-amis/' . $id);
        }
        Session::flash('succes', t('cam.fl.supprime', ['nom' => $nom]));
        redirect('calendriers-amis');
    }

    // --- Les évènements -------------------------------------------------------------------------------------------------

    /** Le formulaire d'un nouvel évènement dans ce calendrier. */
    public function nouvelEvenement(int $id): void
    {
        Auth::exiger();
        $calendrier = CalendriersAmis::calendrier($id, Auth::id());
        if ($calendrier === null) {
            Session::flash('erreur', t('cam.err.introuvable'));
            redirect('calendriers-amis');
        }
        $date = (string) ($_GET['date'] ?? '');
        $this->afficher('calendriers-amis/evenement-formulaire', [
            'calendrier' => $calendrier,
            'evenement' => null,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : date('Y-m-d'),
        ], t('cam.evt_nouveau'));
    }

    public function creerEvenement(int $id): void
    {
        $this->poster();
        [$donnees, $refus] = CalendriersAmis::lireFormulaire($_POST);
        if ($donnees === null) {
            Session::flash('erreur', (string) $refus);
            redirect('calendriers-amis/' . $id . '/evenements/nouveau', ['date' => (string) ($_POST['date_debut'] ?? '')]);
        }
        [$evenement, $refus] = CalendriersAmis::creerEvenement(Auth::id(), $id, $donnees);
        if ($evenement === null) {
            Session::flash('erreur', (string) $refus);
            redirect('calendriers-amis');
        }
        Session::flash('succes', t('cam.fl.evt_cree'));
        redirect('calendriers-amis/evenements/' . $evenement);
    }

    /** Un évènement, en lecture. */
    public function evenement(int $id): void
    {
        Auth::exiger();
        $evenement = CalendriersAmis::evenement($id, Auth::id());
        if ($evenement === null) {
            Session::flash('erreur', t('cam.err.evenement_introuvable'));
            redirect('calendrier');
        }
        // L'évènement du calendrier commun d'un projet peut renvoyer à des documents du projet.
        $projet = $evenement['calendrier']['projet_id'];
        $liens = $projet === null ? null : [
            'projet' => $projet,
            'type' => 'evenement',
            'id' => $id,
            'liens' => LiensEvenements::liensDe($projet, Auth::id(), 'evenement', $id),
            'aLier' => LiensEvenements::aLier($projet, Auth::id(), 'evenement', $id),
        ];
        $this->afficher('calendriers-amis/evenement', ['evenement' => $evenement, 'liensEvenement' => $liens], (string) $evenement['titre']);
    }

    public function modifierEvenementForm(int $id): void
    {
        Auth::exiger();
        $evenement = CalendriersAmis::evenement($id, Auth::id());
        if ($evenement === null || !$evenement['peut_modifier']) {
            Session::flash('erreur', $evenement === null ? t('cam.err.evenement_introuvable') : t('cam.err.pas_le_votre'));
            redirect($evenement === null ? 'calendrier' : 'calendriers-amis/evenements/' . $id);
        }
        $this->afficher('calendriers-amis/evenement-formulaire', [
            'calendrier' => $evenement['calendrier'],
            'evenement' => $evenement,
            'date' => substr((string) $evenement['debut'], 0, 10),
        ], t('cam.evt_modifier'));
    }

    public function modifierEvenement(int $id): void
    {
        $this->poster();
        [$donnees, $refus] = CalendriersAmis::lireFormulaire($_POST);
        if ($donnees === null) {
            Session::flash('erreur', (string) $refus);
            redirect('calendriers-amis/evenements/' . $id . '/modifier');
        }
        $refus = CalendriersAmis::modifierEvenement(Auth::id(), $id, $donnees);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', t('cam.fl.evt_modifie'));
        }
        redirect('calendriers-amis/evenements/' . $id);
    }

    public function supprimerEvenement(int $id): void
    {
        $this->poster();
        $evenement = CalendriersAmis::evenement($id, Auth::id());
        $refus = CalendriersAmis::supprimerEvenement(Auth::id(), $id);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect($evenement === null ? 'calendrier' : 'calendriers-amis/evenements/' . $id);
        }
        Session::flash('succes', t('cam.fl.evt_supprime'));
        redirect('calendriers-amis/' . (int) $evenement['calendrier_id']);
    }

    // --- Outils --------------------------------------------------------------------------------------------------------

    private function poster(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
    }

    /** Une page, ou son fragment quand elle s'ouvre dans une fenêtre. */
    private function afficher(string $vue, array $donnees, string $titre): void
    {
        if (Vue::enFenetre()) {
            Vue::fragment($vue, $donnees);
            return;
        }
        Vue::afficher($vue, $donnees, $titre);
    }

    /** La couleur du formulaire : une pastille choisie ou la couleur libre. */
    private static function couleurChoisie(): ?string
    {
        $choix = (string) ($_POST['couleur'] ?? '');

        return $choix === 'perso' ? (string) ($_POST['couleur_perso'] ?? '') : $choix;
    }

    /** Revient aux réglages du calendrier, avec le message qui convient. */
    private function retourReglages(int $id, ?string $refus, string $succes): never
    {
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } elseif ($succes !== '') {
            Session::flash('succes', $succes);
        }
        redirect(CalendriersAmis::calendrier($id, Auth::id()) === null ? 'calendriers-amis' : 'calendriers-amis/' . $id);
    }
}
