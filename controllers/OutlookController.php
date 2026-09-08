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
        if ($seul && !SynchroOutlook::aBesoinDEtreRelu($userId)) {
            repondre_json(['fait' => false, 'change' => 0]);
        }

        try {
            $bilan = SynchroOutlook::tirer($userId);
        } catch (Throwable $e) {
            if ($seul || veut_du_json()) {
                // En arrière-plan, un échec ne doit pas s'imposer à l'écran :
                // le bouton reste là pour le provoquer et lire le message.
                repondre_json(['fait' => false, 'change' => 0, 'souci' => $e->getMessage()]);
            }
            Session::flash('erreur', $e->getMessage());
            repartir_vers('outlook');
        }

        $change = $bilan['ajoutes'] + $bilan['modifies'] + $bilan['retires'];

        if ($seul || veut_du_json()) {
            repondre_json(['fait' => !$bilan['occupe'], 'change' => $change]);
        }

        if ($bilan['occupe']) {
            Session::flash('succes', 'Une lecture était déjà en cours : rien n’a été fait deux fois.');
            repartir_vers('outlook');
        }

        $dit = [];
        if ($bilan['ajoutes'] > 0)  { $dit[] = $bilan['ajoutes'] . ' ajouté' . ($bilan['ajoutes'] > 1 ? 's' : ''); }
        if ($bilan['modifies'] > 0) { $dit[] = $bilan['modifies'] . ' mis à jour'; }
        if ($bilan['retires'] > 0)  { $dit[] = $bilan['retires'] . ' retiré' . ($bilan['retires'] > 1 ? 's' : ''); }

        Session::flash('succes', $dit === []
            ? 'Agenda relu : rien de nouveau.'
            : 'Agenda relu : ' . implode(', ', $dit) . '.');
        repartir_vers('outlook');
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
        $retires = SynchroOutlook::toutRetirer($userId);
        Outlook::delier($userId);

        Session::flash('succes', 'Compte Outlook délié. L’application n’accède plus à votre agenda'
            . ($retires === 0 ? '.' : ', et ' . $retires . ' évènement' . ($retires > 1 ? 's importés ont' : ' importé a')
               . ' quitté le calendrier.'));
        redirect('outlook');
    }
}
