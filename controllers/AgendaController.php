<?php
declare(strict_types=1);

/**
 * Relier — et délier — un agenda distant, quel qu'il soit.
 *
 * L'inscription chez le fournisseur appartient à l'installation, pas à chacun :
 * une seule application y est déclarée, et tout le monde y relie son compte.
 * Le fournisseur vient de l'adresse, ce qui laisse une page et un retour
 * d'autorisation distincts pour chacun sans dupliquer une ligne de code.
 */
final class AgendaController
{
    /** Le fournisseur nommé dans l'adresse, ou une page introuvable. */
    private function fournisseur(string $cle): Fournisseur
    {
        $f = Agenda::pour($cle);
        if ($f === null) {
            http_response_code(404);
            Vue::afficher('erreurs/404', [], 'Page introuvable');
            exit;
        }

        return $f;
    }

    /** L'écran de la liaison : où l'on en est, et ce qu'on peut y faire. */
    public function index(string $cle): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $f = $this->fournisseur($cle);
        $lien = LiaisonAgenda::pour($f);

        Vue::afficher('agenda/index', [
            'f'           => $f,
            'configuree'  => $lien->configure(),
            'compte'      => $lien->compte($userId),
            'relie'       => $lien->relie($userId),
            'retour'      => $lien->adresseDeRetour(),
            'derniere'    => SynchroAgenda::pour($f)->derniereFois($userId),
            'combien'     => SynchroAgenda::pour($f)->combien($userId),
            'calendriers' => SynchroAgenda::pour($f)->calendriers($userId),
            'partage'     => $lien->permissionsCompletes($userId),
            'envoyes'     => EnvoiAgenda::pour($f)->combien($userId),
            'envoiLe'     => EnvoiAgenda::pour($f)->derniereFois($userId),
            'ouEcrire'    => Agenda::ouEcrire($userId, $f),
            'destination' => EnvoiAgenda::pour($f)->destination($userId),
            'souci'       => SynchroAgenda::pour($f)->dernierSouci($userId),
            'autres'      => Agenda::tous(),
        ], $f->nom());
    }

    /** Part demander l'autorisation au fournisseur. */
    public function connexion(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        if (!LiaisonAgenda::pour($f)->configure()) {
            Session::flash('erreur', 'La liaison avec ' . $f->nom()
                . ' n’est pas configurée sur cette installation.');
            redirect('agenda/' . $f->cle());
        }

        header('Location: ' . LiaisonAgenda::pour($f)->adresseDAutorisation());
        exit;
    }

    /**
     * Le retour du fournisseur.
     *
     * Aucun jeton CSRF ici : c'est le fournisseur qui renvoie le navigateur, et
     * il n'en connaît aucun. C'est « state », parti à l'aller et gardé en
     * session, qui joue ce rôle — la liaison ne se termine que si les deux
     * concordent.
     */
    public function retour(string $cle): void
    {
        Auth::exiger();
        $f = $this->fournisseur($cle);

        $refus = (string) ($_GET['error_description'] ?? $_GET['error'] ?? '');
        if ($refus !== '') {
            Session::flash('erreur', $f->nom() . ' a refusé : ' . mb_substr($refus, 0, 200));
            redirect('agenda/' . $f->cle());
        }

        $code = (string) ($_GET['code'] ?? '');
        $etat = (string) ($_GET['state'] ?? '');
        if ($code === '') {
            Session::flash('erreur', $f->nom() . ' n’a renvoyé aucun code d’autorisation.');
            redirect('agenda/' . $f->cle());
        }

        try {
            $souci = LiaisonAgenda::pour($f)->terminerLaLiaison(Auth::id(), $code, $etat);
        } catch (Throwable $e) {
            $souci = $e->getMessage();
        }

        if ($souci !== null) {
            Session::flash('erreur', $souci);
            redirect('agenda/' . $f->cle());
        }

        $compte = LiaisonAgenda::pour($f)->compte(Auth::id());
        Session::flash('succes', 'Compte ' . $f->nom() . ' relié'
            . (($compte['compte'] ?? '') === '' ? '.' : ' : ' . $compte['compte'] . '.'));
        redirect('agenda/' . $f->cle());
    }

    /** Redemande au fournisseur quels calendriers possède le compte. */
    public function calendriers(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        try {
            $combien = SynchroAgenda::pour($f)->rafraichirLesCalendriers(Auth::id());
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            redirect('agenda/' . $f->cle());
        }

        Session::flash('succes', $combien . ' calendrier' . ($combien > 1 ? 's trouvés' : ' trouvé')
            . ' sur votre compte. Cochez ceux que l’application doit lire.');
        redirect('agenda/' . $f->cle());
    }

    /** Retient les calendriers cochés. */
    public function suivre(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        $coches = $_POST['calendriers'] ?? [];
        $suivis = SynchroAgenda::pour($f)->choisir(Auth::id(), is_array($coches) ? $coches : []);

        Session::flash('succes', $suivis === 0
            ? 'Aucun calendrier suivi : plus rien ne viendra de ' . $f->nom() . '.'
            : $suivis . ' calendrier' . ($suivis > 1 ? 's suivis' : ' suivi')
              . '. Relisez l’agenda pour en voir les évènements.');
        redirect('agenda/' . $f->cle());
    }

    /**
     * Lit l'agenda et y porte ce qui est né ici.
     *
     * Deux appelants : le bouton, et le script de la page qui relit de
     * lui-même. Le second n'insiste pas — s'il n'y a rien à faire, il repart
     * sans rien faire, et c'est le serveur qui en décide.
     */
    public function synchroniser(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $f = $this->fournisseur($cle);
        $userId = Auth::id();
        $seul = ($_POST['seul'] ?? '') === '1';

        $bilan = $this->unAgenda($f, $userId, $seul);
        if ($bilan === null) {
            if ($seul || veut_du_json()) {
                repondre_json(['fait' => false, 'change' => 0]);
            }
            Session::flash('succes', 'Tout était déjà à jour.');
            repartir_vers('agenda/' . $f->cle());
        }

        $this->rendreCompte([$f->cle() => $bilan], $seul, 'agenda/' . $f->cle());
    }

    /**
     * Synchronise tous les agendas reliés, d'un seul geste.
     *
     * C'est ce que veut le bouton du calendrier, et ce que réveille la page :
     * on n'a pas à savoir de quel agenda vient tel rendez-vous pour vouloir
     * qu'il soit à jour.
     */
    public function synchroniserTout(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $userId = Auth::id();
        $seul = ($_POST['seul'] ?? '') === '1';
        $bilans = [];

        foreach (Agenda::relies($userId) as $f) {
            $bilan = $this->unAgenda($f, $userId, $seul);
            if ($bilan !== null) {
                $bilans[$f->cle()] = $bilan;
            }
        }

        if ($bilans === []) {
            if ($seul || veut_du_json()) {
                repondre_json(['fait' => false, 'change' => 0]);
            }
            Session::flash('succes', 'Tout était déjà à jour.');
            repartir_vers('agenda');
        }

        $this->rendreCompte($bilans, $seul, 'agenda');
    }

    /**
     * Lit et envoie pour un agenda, et rend ce qui s'est passé.
     *
     * Rend null quand il n'y avait rien à faire — et non un bilan vide : la
     * différence compte pour savoir s'il faut dire quelque chose.
     *
     * @return ?array{f: Fournisseur, lu: array, envoye: array, souci: ?string}
     */
    private function unAgenda(Fournisseur $f, int $userId, bool $seul): ?array
    {
        $synchro = SynchroAgenda::pour($f);
        $envoiDe = EnvoiAgenda::pour($f);
        $rien = ['crees' => 0, 'majs' => 0, 'retires' => 0, 'inchanges' => 0];

        $lire = !$seul || $synchro->aBesoinDEtreRelu($userId);
        $envoyer = !$seul || $envoiDe->aPousser($userId);
        if (!$lire && !$envoyer) {
            return null;
        }

        try {
            $bilan = $lire
                ? $synchro->tirer($userId)
                : ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0,
                   'inchanges' => 0, 'effaces' => 0, 'occupe' => false];
            /*
             * L'envoi ne part que si la lecture n'a pas buté sur le verrou :
             * quelqu'un d'autre fait alors déjà les deux, et écrire par dessus
             * créerait les doublons que le verrou évite.
             */
            $envoi = ($envoyer && !$bilan['occupe']) ? $envoiDe->pousser($userId) : $rien;
        } catch (Throwable $e) {
            // Noté en base, pas seulement affiché : une synchronisation de fond
            // n'a personne devant elle, et son échec doit survivre à la requête.
            $synchro->retenirLeSouci($userId, $e->getMessage());

            return ['f' => $f, 'lu' => [], 'envoye' => [], 'souci' => $e->getMessage()];
        }

        $synchro->retenirLeSouci($userId, null);

        return ['f' => $f, 'lu' => $bilan, 'envoye' => $envoi, 'souci' => null];
    }

    /**
     * Dit ce que la synchronisation a fait, sur un ou plusieurs agendas.
     *
     * @param array<string, array> $bilans
     */
    private function rendreCompte(array $bilans, bool $seul, string $retour): never
    {
        $change = 0;
        $soucis = [];
        $phrases = [];

        foreach ($bilans as $bilan) {
            if ($bilan['souci'] !== null) {
                $soucis[] = $bilan['souci'];
                continue;
            }
            $lu = $bilan['lu'];
            $envoye = $bilan['envoye'];
            $change += $lu['ajoutes'] + $lu['modifies'] + $lu['retires'] + $lu['effaces']
                + $envoye['crees'] + $envoye['majs'] + $envoye['retires'];
            $dit = self::raconter($bilan['f'], $lu, $envoye);
            if ($dit !== '') {
                $phrases[] = $dit;
            }
        }

        if ($seul || veut_du_json()) {
            repondre_json($soucis === []
                ? ['fait' => true, 'change' => $change]
                : ['fait' => false, 'change' => $change, 'souci' => implode(' ', $soucis)]);
        }

        if ($soucis !== []) {
            Session::flash('erreur', implode(' ', $soucis));
        }
        Session::flash('succes', $phrases === []
            ? 'Synchronisé : tout était déjà à jour, de part et d’autre.'
            : implode(' ', $phrases));

        repartir_vers($retour);
    }

    /** La page qui montre les agendas et leur état. */
    public function liste(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $etats = [];
        foreach (Agenda::tous() as $f) {
            $lien = LiaisonAgenda::pour($f);
            $etats[] = [
                'f'          => $f,
                'configure'  => $lien->configure(),
                'relie'      => $lien->relie($userId),
                'compte'     => (string) ($lien->compte($userId)['compte'] ?? ''),
                'evenements' => SynchroAgenda::pour($f)->combien($userId),
                'envoyes'    => EnvoiAgenda::pour($f)->combien($userId),
                'souci'      => SynchroAgenda::pour($f)->dernierSouci($userId),
            ];
        }

        Vue::afficher('agenda/liste', ['etats' => $etats], 'Mes agendas');
    }

    /**
     * Le compte rendu des deux sens, en français.
     *
     * Une synchronisation qui ne dit pas ce qu'elle a fait ne se laisse ni
     * vérifier, ni corriger — et l'on n'ose plus la relancer.
     */
    private static function raconter(Fournisseur $f, array $bilan, array $envoi): string
    {
        $partis = [];
        $venus = [];
        if ($bilan['ajoutes'] > 0)  { $venus[] = $bilan['ajoutes'] . ' ajouté' . ($bilan['ajoutes'] > 1 ? 's' : ''); }
        if ($bilan['modifies'] > 0) { $venus[] = $bilan['modifies'] . ' mis à jour'; }
        if ($bilan['retires'] > 0)  { $venus[] = $bilan['retires'] . ' retiré' . ($bilan['retires'] > 1 ? 's' : ''); }
        if (($bilan['effaces'] ?? 0) > 0) {
            $partis[] = $bilan['effaces'] . ' supprimé' . ($bilan['effaces'] > 1 ? 's' : '');
        }

        if ($envoi['crees'] > 0)   { $partis[] = $envoi['crees'] . ' créé' . ($envoi['crees'] > 1 ? 's' : ''); }
        if ($envoi['majs'] > 0)    { $partis[] = $envoi['majs'] . ' mis à jour'; }
        if ($envoi['retires'] > 0) { $partis[] = $envoi['retires'] . ' retiré' . ($envoi['retires'] > 1 ? 's' : ''); }

        $phrases = [];
        if ($venus !== [])  { $phrases[] = 'venus ' . de_agenda($f->nom()) . ' : ' . implode(', ', $venus); }
        if ($partis !== []) { $phrases[] = 'partis vers ' . $f->nom() . ' : ' . implode(', ', $partis); }

        return $phrases === [] ? '' : 'Synchronisé — ' . implode(' ; ', $phrases) . '.';
    }

    /** Retire de l'application les évènements venus de cet agenda. */
    public function retirer(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        $retires = SynchroAgenda::pour($f)->toutRetirer(Auth::id());
        Session::flash('succes', $retires === 0
            ? 'Il n’y avait aucun évènement importé.'
            : $retires . ' évènement' . ($retires > 1 ? 's importés retirés' : ' importé retiré')
              . ' du calendrier. Ils restent dans votre agenda ' . $f->nom() . '.');
        repartir_vers('agenda/' . $f->cle());
    }

    /**
     * Choisit l'agenda qui reçoit les évènements de l'application.
     *
     * Par défaut c'est « Mes Cours », que l'application crée pour elle-même :
     * son propre calendrier, qu'une erreur de notre part ne peut pas répandre
     * ailleurs. Mais un agenda que la famille regarde ne sert à rien s'il ne
     * reçoit rien — d'où ce choix, qui reste le sien.
     *
     * Le déménagement est complet : ce qui était parti quitte l'ancien
     * calendrier tout de suite, et la synchronisation qui suit — elle part
     * d'elle-même, l'empreinte d'envoi ayant été effacée — le recrée dans le
     * nouveau. Rien ne reste à cheval entre les deux.
     */
    public function destination(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        $voulu = (string) ($_POST['destination'] ?? '');
        $empreinte = preg_match('/^[0-9a-f]{32}$/', $voulu) === 1 ? $voulu : null;

        try {
            $fait = EnvoiAgenda::pour($f)->changerDeDestination(Auth::id(), $empreinte);
        } catch (Throwable $e) {
            /*
             * Vider l'ancien calendrier vient avant tout le reste : tant qu'on
             * n'y arrive pas, changer de destination ne ferait que répandre
             * les mêmes évènements à deux endroits. Le réglage n'a donc pas
             * bougé, et c'est la première chose à dire.
             */
            Session::flash('erreur', 'La destination n’a pas changé : ' . $e->getMessage());
            repartir_vers('agenda/' . $f->cle());
        }

        Session::flash('succes', 'Vos évènements iront désormais dans « ' . $fait['nom'] . ' »'
            . ($fait['retires'] === 0
                ? '. Ils y arriveront à la prochaine synchronisation.'
                : ', et ' . $fait['retires'] . ' élément' . ($fait['retires'] > 1 ? 's ont' : ' a')
                  . ' quitté l’ancien. La synchronisation suivante '
                  . ($fait['retires'] > 1 ? 'les y remet' : 'l’y remet') . ', tout de suite.'));
        repartir_vers('agenda/' . $f->cle());
    }

    /**
     * Retire de l'agenda ce que l'application y avait mis.
     *
     * Le calendrier « Mes Cours » reste : il appartient au compte, pas à nous,
     * et rien ne dit qu'on n'y a pas ajouté autre chose à la main.
     */
    public function retirerEnvoi(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $f = $this->fournisseur($cle);

        try {
            $retires = EnvoiAgenda::pour($f)->toutRetirer(Auth::id());
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            repartir_vers('agenda/' . $f->cle());
        }

        Session::flash('succes', $retires === 0
            ? 'Il n’y avait rien à retirer ' . de_agenda($f->nom()) . '.'
            : $retires . ' élément' . ($retires > 1 ? 's ont quitté' : ' a quitté')
              . ' votre agenda ' . $f->nom() . '. Le calendrier « Mes Cours » y reste, vide.');
        repartir_vers('agenda/' . $f->cle());
    }

    /**
     * Oublie les jetons : l'application ne touche plus à cet agenda.
     *
     * On tente d'abord de reprendre ce qu'on avait mis là-bas : après la
     * coupure, on n'aurait plus de quoi le faire. Si cela échoue, on délie
     * quand même — on ne retient pas quelqu'un qui part — mais on le dit.
     */
    public function deconnexion(string $cle): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $f = $this->fournisseur($cle);
        $userId = Auth::id();

        $reste = false;
        try {
            EnvoiAgenda::pour($f)->toutRetirer($userId);
        } catch (Throwable) {
            $reste = true;
        }

        $retires = SynchroAgenda::pour($f)->toutRetirer($userId);
        LiaisonAgenda::pour($f)->delier($userId);

        Session::flash('succes', 'Compte ' . $f->nom()
            . ' délié. L’application n’accède plus à votre agenda'
            . ($retires === 0 ? '.' : ', et ' . $retires . ' évènement'
               . ($retires > 1 ? 's importés ont' : ' importé a') . ' quitté le calendrier.'));
        if ($reste) {
            Session::flash('erreur', 'Ce que l’application avait écrit dans ' . $f->nom()
                . ' n’a pas pu être repris : supprimez le calendrier « Mes Cours » depuis '
                . $f->nom() . '.');
        }
        repartir_vers('agenda/' . $f->cle());
    }

    /** Les anciens liens vers « outlook » mènent toujours quelque part. */
    public function ancienLien(): void
    {
        redirect('agenda/microsoft');
    }

    /**
     * Le retour d'autorisation de Microsoft, à son adresse historique.
     *
     * Elle est inscrite dans Azure : la déplacer obligerait chacun à
     * retoucher son inscription pour un renommage interne.
     */
    public function retourMicrosoft(): void
    {
        $this->retour('microsoft');
    }
}
