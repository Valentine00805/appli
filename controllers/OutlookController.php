<?php
declare(strict_types=1);

/**
 * Relier — et délier — son calendrier Outlook.
 *
 * L'inscription chez Microsoft appartient à l'installation, pas à chacun :
 * une seule application y est déclarée, et tout le monde y relie son compte.
 * La synchronisation elle-même viendra ensuite ; ici, on ne fait qu'obtenir
 * puis garder l'autorisation d'y toucher.
 */
final class OutlookController
{
    /** L'écran de la liaison : où l'on en est, et ce qu'on peut y faire. */
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        Vue::afficher('outlook/index', [
            'configuree' => Outlook::configuree(),
            'compte'     => Outlook::compte($userId),
            'relie'      => Outlook::relie($userId),
            'retour'     => Outlook::adresseDeRetour(),
            'derniere'   => SynchroOutlook::derniereFois($userId),
            'combien'    => SynchroOutlook::combien($userId),
            'calendriers' => SynchroOutlook::calendriers($userId),
            'partage'    => Outlook::partageAutorise($userId),
            'envoyes'    => EnvoiOutlook::combien($userId),
            'envoiLe'    => EnvoiOutlook::derniereFois($userId),
            'souci'      => SynchroOutlook::dernierSouci($userId),
        ], 'Calendrier Outlook');
    }

    /**
     * Redemande à Microsoft quels calendriers possède le compte.
     *
     * On ne devine pas la liste : un calendrier ajouté ou partagé hier n'a
     * aucune raison d'être connu, et rien ne remplace le fait d'aller voir.
     */
    public function calendriers(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        try {
            $combien = SynchroOutlook::rafraichirLesCalendriers(Auth::id());
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            redirect('outlook');
        }

        Session::flash('succes', $combien . ' calendrier' . ($combien > 1 ? 's trouvés' : ' trouvé')
            . ' sur votre compte. Cochez ceux que l’application doit lire.');
        redirect('outlook');
    }

    /** Retient les calendriers cochés. */
    public function suivre(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $coches = $_POST['calendriers'] ?? [];
        $suivis = SynchroOutlook::choisir(Auth::id(), is_array($coches) ? $coches : []);

        Session::flash('succes', $suivis === 0
            ? 'Aucun calendrier suivi : plus rien ne viendra d’Outlook.'
            : $suivis . ' calendrier' . ($suivis > 1 ? 's suivis' : ' suivi')
              . '. Relisez l’agenda pour en voir les évènements.');
        redirect('outlook');
    }

    /**
     * Va chercher les évènements de l'agenda et met le calendrier à jour.
     *
     * Le bilan est rendu en clair — tant d'ajoutés, tant de modifiés, tant de
     * retirés : une synchronisation qui ne dit rien de ce qu'elle a fait ne se
     * laisse ni vérifier, ni corriger.
     */
    public function synchroniser(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $userId = Auth::id();
        /*
         * Deux appelants : le bouton, et le script de la page qui relit de
         * lui-même. Le second n'insiste pas — s'il n'est pas l'heure, il
         * repart sans rien faire, et c'est le serveur qui en décide.
         */
        $seul = ($_POST['seul'] ?? '') === '1';
        $rien = ['crees' => 0, 'majs' => 0, 'retires' => 0, 'inchanges' => 0];

        /*
         * Le bouton fait les deux sens sans discuter. La relecture de fond,
         * elle, ne fait que ce qui sert : relire quand l'heure est venue,
         * envoyer dès qu'il y a quelque chose à envoyer — un évènement qu'on
         * vient de créer part ainsi tout de suite, sans attendre le tour de la
         * lecture.
         */
        $lire = !$seul || SynchroOutlook::aBesoinDEtreRelu($userId);
        $envoyer = !$seul || EnvoiOutlook::aPousser($userId);
        if (!$lire && !$envoyer) {
            repondre_json(['fait' => false, 'change' => 0]);
        }

        try {
            $bilan = $lire
                ? SynchroOutlook::tirer($userId)
                : ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0, 'inchanges' => 0, 'occupe' => false];
            /*
             * L'envoi ne part que si la lecture n'a pas buté sur le verrou :
             * quelqu'un d'autre fait alors déjà les deux, et écrire par dessus
             * créerait les doublons que le verrou évite.
             */
            $envoi = ($envoyer && !$bilan['occupe']) ? EnvoiOutlook::pousser($userId) : $rien;
        } catch (Throwable $e) {
            /*
             * Noté en base, pas seulement affiché : une synchronisation de
             * fond n'a personne devant elle, et son échec doit survivre à la
             * requête qui l'a rencontré.
             */
            SynchroOutlook::retenirLeSouci($userId, $e->getMessage());

            if ($seul || veut_du_json()) {
                // En arrière-plan, un échec ne s'impose pas à l'écran : la
                // page Outlook le montrera, et le bouton le fera reparaître.
                repondre_json(['fait' => false, 'change' => 0, 'souci' => $e->getMessage()]);
            }
            Session::flash('erreur', $e->getMessage());
            repartir_vers('outlook');
        }

        SynchroOutlook::retenirLeSouci($userId, null);

        $change = $bilan['ajoutes'] + $bilan['modifies'] + $bilan['retires']
            + $envoi['crees'] + $envoi['majs'] + $envoi['retires'];

        if ($seul || veut_du_json()) {
            repondre_json(['fait' => !$bilan['occupe'], 'change' => $change]);
        }

        if ($bilan['occupe']) {
            Session::flash('succes', 'Une lecture était déjà en cours : rien n’a été fait deux fois.');
            repartir_vers('outlook');
        }

        Session::flash('succes', self::raconter($bilan, $envoi));
        repartir_vers('outlook');
    }

    /**
     * Le compte rendu des deux sens, en français.
     *
     * Une synchronisation qui ne dit pas ce qu'elle a fait ne se laisse ni
     * vérifier, ni corriger — et l'on n'ose plus la relancer.
     */
    private static function raconter(array $bilan, array $envoi): string
    {
        $venus = [];
        if ($bilan['ajoutes'] > 0)  { $venus[] = $bilan['ajoutes'] . ' ajouté' . ($bilan['ajoutes'] > 1 ? 's' : ''); }
        if ($bilan['modifies'] > 0) { $venus[] = $bilan['modifies'] . ' mis à jour'; }
        if ($bilan['retires'] > 0)  { $venus[] = $bilan['retires'] . ' retiré' . ($bilan['retires'] > 1 ? 's' : ''); }

        $partis = [];
        if ($envoi['crees'] > 0)   { $partis[] = $envoi['crees'] . ' créé' . ($envoi['crees'] > 1 ? 's' : ''); }
        if ($envoi['majs'] > 0)    { $partis[] = $envoi['majs'] . ' mis à jour'; }
        if ($envoi['retires'] > 0) { $partis[] = $envoi['retires'] . ' retiré' . ($envoi['retires'] > 1 ? 's' : ''); }

        $phrases = [];
        if ($venus !== [])  { $phrases[] = 'venus d’Outlook : ' . implode(', ', $venus); }
        if ($partis !== []) { $phrases[] = 'partis vers Outlook : ' . implode(', ', $partis); }

        return $phrases === []
            ? 'Synchronisé : tout était déjà à jour, de part et d’autre.'
            : 'Synchronisé — ' . implode(' ; ', $phrases) . '.';
    }

    /** Part demander l'autorisation à Microsoft. */
    public function connexion(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        if (!Outlook::configuree()) {
            Session::flash('erreur', 'La liaison Outlook n’est pas configurée sur cette installation.');
            redirect('outlook');
        }

        header('Location: ' . Outlook::adresseDAutorisation());
        exit;
    }

    /**
     * Le retour de Microsoft.
     *
     * Aucun jeton CSRF ici : c'est Microsoft qui renvoie le navigateur, et il
     * n'en connaît aucun. C'est « state », parti à l'aller et gardé en session,
     * qui joue ce rôle — la liaison ne se termine que si les deux concordent.
     */
    public function retour(): void
    {
        Auth::exiger();

        $refus = (string) ($_GET['error_description'] ?? $_GET['error'] ?? '');
        if ($refus !== '') {
            Session::flash('erreur', 'Microsoft a refusé : ' . mb_substr($refus, 0, 200));
            redirect('outlook');
        }

        $code = (string) ($_GET['code'] ?? '');
        $etat = (string) ($_GET['state'] ?? '');
        if ($code === '') {
            Session::flash('erreur', 'Microsoft n’a renvoyé aucun code d’autorisation.');
            redirect('outlook');
        }

        try {
            $souci = Outlook::terminerLaLiaison(Auth::id(), $code, $etat);
        } catch (Throwable $e) {
            $souci = $e->getMessage();
        }

        if ($souci !== null) {
            Session::flash('erreur', $souci);
            redirect('outlook');
        }

        $compte = Outlook::compte(Auth::id());
        Session::flash('succes', 'Compte Outlook relié'
            . (($compte['compte'] ?? '') === '' ? '.' : ' : ' . $compte['compte'] . '.'));
        redirect('outlook');
    }

    /**
     * Retire les évènements importés, sans délier le compte.
     *
     * De quoi essayer sans risque : ce qui a été apporté se reprend d'un
     * geste, et rien n'est perdu puisque tout est encore dans l'agenda.
     */
    public function retirer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $retires = SynchroOutlook::toutRetirer(Auth::id());
        Session::flash('succes', $retires === 0
            ? 'Il n’y avait aucun évènement importé.'
            : $retires . ' évènement' . ($retires > 1 ? 's importés retirés' : ' importé retiré')
              . ' du calendrier. Ils restent dans votre agenda Outlook.');
        redirect('outlook');
    }

    /**
     * Retire d'Outlook ce que l'application y avait mis.
     *
     * Le calendrier « Mes Cours » reste : il appartient au compte, pas à
     * nous, et rien ne dit qu'on n'y a pas ajouté autre chose à la main.
     */
    public function retirerEnvoi(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        try {
            $retires = EnvoiOutlook::toutRetirer(Auth::id());
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            repartir_vers('outlook');
        }

        Session::flash('succes', $retires === 0
            ? 'Il n’y avait rien à retirer d’Outlook.'
            : $retires . ' élément' . ($retires > 1 ? 's ont quitté' : ' a quitté')
              . ' votre agenda Outlook. Le calendrier « Mes Cours » y reste, vide.');
        repartir_vers('outlook');
    }

    /**
     * Oublie les jetons : l'application ne touche plus à l'agenda.
     *
     * Les évènements importés partent avec. Les garder les rendrait
     * orphelins — plus rien ne les rattacherait à Outlook — et une
     * reconnexion les aurait doublés. Ils sont toujours dans l'agenda.
     */
    public function deconnexion(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $userId = Auth::id();

        /*
         * On tente d'abord de reprendre ce qu'on avait mis chez Microsoft :
         * après la coupure, on n'aurait plus de quoi le faire. Si cela échoue,
         * on délie quand même — l'utilisateur a demandé à partir, on ne le
         * retient pas pour un agenda qui ne répond pas — mais on le dit.
         */
        $reste = false;
        try {
            EnvoiOutlook::toutRetirer($userId);
        } catch (Throwable) {
            $reste = true;
        }

        $retires = SynchroOutlook::toutRetirer($userId);
        Outlook::delier($userId);

        Session::flash('succes', 'Compte Outlook délié. L’application n’accède plus à votre agenda'
            . ($retires === 0 ? '.' : ', et ' . $retires . ' évènement' . ($retires > 1 ? 's importés ont' : ' importé a')
               . ' quitté le calendrier.'));
        if ($reste) {
            Session::flash('erreur', 'Ce que l’application avait écrit dans Outlook n’a pas pu être '
                . 'repris : supprimez le calendrier « Mes Cours » depuis Outlook.');
        }
        repartir_vers('outlook');
    }
}
