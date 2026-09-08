<?php
declare(strict_types=1);

/**
 * Relier — et délier — un calendrier Outlook.
 *
 * La synchronisation elle-même viendra ensuite : ici, on ne fait qu'obtenir
 * puis garder l'autorisation d'y toucher.
 */
final class OutlookController
{
    /** L'écran de la liaison : ce qu'il faut inscrire, et où l'on en est. */
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        Vue::afficher('outlook/index', [
            'compte'  => Outlook::compte($userId),
            'relie'   => Outlook::relie($userId),
            'retour'  => Outlook::adresseDeRetour(),
        ], 'Calendrier Outlook');
    }

    /** Retient l'application inscrite chez Microsoft. */
    public function enregistrer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        /*
         * L'identifiant d'une application Azure est un GUID. Le vérifier ici
         * évite de partir vers Microsoft pour se faire renvoyer une erreur
         * qu'on aurait pu lire soi-même.
         */
        $clientId = post('client_id');
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientId) !== 1) {
            Session::flash('erreur', 'L’identifiant d’application attendu ressemble à '
                . '« 11111111-2222-3333-4444-555555555555 ».');
            redirect('outlook');
        }

        // Le locataire : « common » pour tout compte, ou celui d'un établissement.
        $locataire = post('locataire', 'common');
        if (preg_match('/^[0-9a-zA-Z._-]{1,64}$/', $locataire) !== 1) {
            Session::flash('erreur', 'Le locataire ne peut contenir que des lettres, chiffres, points et tirets.');
            redirect('outlook');
        }

        Outlook::retenirApplication(Auth::id(), strtolower($clientId), $locataire);
        Session::flash('succes', 'Application enregistrée. Vous pouvez relier votre compte.');
        redirect('outlook');
    }

    /** Part demander l'autorisation à Microsoft. */
    public function connexion(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $compte = Outlook::compte(Auth::id());
        if ($compte === null) {
            Session::flash('erreur', 'Enregistrez d’abord l’application inscrite chez Microsoft.');
            redirect('outlook');
        }

        header('Location: ' . Outlook::adresseDAutorisation($compte));
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

    /** Oublie les jetons : l'application ne touche plus à l'agenda. */
    public function deconnexion(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        Outlook::delier(Auth::id());
        Session::flash('succes', 'Compte Outlook délié. L’application n’accède plus à votre agenda.');
        redirect('outlook');
    }
}
