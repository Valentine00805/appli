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
        ], 'Calendrier Outlook');
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
