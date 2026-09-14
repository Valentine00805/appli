<?php
declare(strict_types=1);

/**
 * La page « Amis » : chercher un pseudo, gérer ses demandes, et discuter.
 *
 * Les identifiants dans les adresses sont ceux des comptes : « amis/12 » est
 * la conversation avec le compte 12, qui n'est accessible qu'entre amis.
 */
final class AmisController
{
    public function index(): void
    {
        Auth::exiger();
        $moi = Auth::id();

        $recherche = trim((string) ($_GET['pseudo'] ?? ''));
        $amis = Amis::liste($moi);

        Vue::afficher('amis/index', [
            'recherche' => $recherche,
            'resultats' => Amis::chercher($moi, $recherche),
            'recues' => Amis::demandesRecues($moi),
            'envoyees' => Amis::demandesEnvoyees($moi),
            'amis' => $amis,
            'derniers' => Amis::messagesParId(array_column($amis, 'dernier_id')),
            'aUnPseudo' => (string) (Auth::utilisateur()['pseudo'] ?? '') !== '',
        ], 'Amis');
    }

    /** Envoie une demande d'ami (ou accepte celle que l'autre avait faite). */
    public function demander(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        $autre = (int) ($_POST['compte'] ?? 0);
        $compte = Amis::compte($autre);

        $resultat = Amis::demander($moi, $autre);
        $pseudo = $compte === null ? '' : (string) $compte['pseudo'];
        match ($resultat) {
            'envoyee' => Session::flash('succes', 'Demande envoyée à ' . $pseudo . '.'),
            'acceptee' => Session::flash('succes', $pseudo . ' vous l’avait déjà demandé : vous êtes maintenant amis.'),
            'deja' => Session::flash('info', 'Une demande est déjà en cours avec ' . $pseudo . ', ou vous êtes déjà amis.'),
            'trop' => Session::flash('erreur', 'Vous avez déjà ' . Amis::DEMANDES_MAX . ' demandes en attente : attendez des réponses.'),
            'sans_pseudo' => Session::flash('erreur', 'Choisissez d’abord un pseudo dans « Mon compte » : c’est lui que verra la personne.'),
            default => Session::flash('erreur', 'Ce compte est introuvable.'),
        };

        $this->retour();
    }

    public function accepter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $compte = Amis::compte($id);

        if ($compte !== null && Amis::accepter(Auth::id(), $id)) {
            Session::flash('succes', 'Vous êtes maintenant amis avec ' . $compte['pseudo'] . '.');
        } else {
            Session::flash('erreur', 'Cette demande n’existe plus.');
        }
        $this->retour();
    }

    /** Refuser une demande, annuler la sienne, ou retirer un ami : le lien disparaît. */
    public function retirer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        $compte = Amis::compte($id);
        $relation = Amis::relation($moi, $id);

        if ($compte === null || $relation === null) {
            Session::flash('erreur', 'Il n’y a rien à retirer.');
            $this->retour();
        }

        Amis::defaire($moi, $id);
        $pseudo = (string) $compte['pseudo'];
        Session::flash('succes', match (true) {
            $relation['statut'] === 'acceptee' => $pseudo . ' ne fait plus partie de vos amis.',
            (int) $relation['demandeur_id'] === $moi => 'Demande à ' . $pseudo . ' annulée.',
            default => 'Demande de ' . $pseudo . ' refusée.',
        });
        redirect('amis');
    }

    /** La conversation avec un ami. */
    public function conversation(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $ami = Amis::compte($id);

        if ($ami === null || !Amis::sontAmis($moi, $id)) {
            Session::flash('erreur', 'Vous ne pouvez discuter qu’avec vos amis.');
            redirect('amis');
        }

        // Le fil d'abord : il marque les messages reçus comme lus, et la liste le reflète.
        $messages = Amis::fil($moi, $id);
        Amis::regarder($moi, $id);
        $amis = Amis::liste($moi);

        Vue::afficher('amis/conversation', [
            'ami' => $ami,
            'messages' => $messages,
            'vuJusqua' => Amis::vuJusqua($moi, $id),
            'amis' => $amis,
            'derniers' => Amis::messagesParId(array_column($amis, 'dernier_id')),
        ], 'Discussion avec ' . $ami['pseudo']);
    }

    /** Les nouveaux messages d'une conversation, pour la page ouverte. */
    public function nouveaux(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        // La session n'a rien à attendre : on la libère pour les autres onglets.
        session_write_close();

        if (!Amis::sontAmis($moi, $id)) {
            http_response_code(403);
            repondre_json(['fait' => false, 'message' => 'Vous n’êtes plus amis.']);
        }

        // Onglet visible : la discussion est sous les yeux, ses messages n'ont pas à être notifiés.
        if (($_GET['visible'] ?? '') === '1') {
            Amis::regarder($moi, $id);
        }

        repondre_json([
            'fait' => true,
            'messages' => Amis::fil($moi, $id, max(0, (int) ($_GET['apres'] ?? 0))),
            'vu_jusqua' => Amis::vuJusqua($moi, $id),
        ]);
    }

    /** Envoie un message ; en JSON pour la page, ou par un envoi ordinaire. */
    public function envoyer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();

        $texte = (string) ($_POST['texte'] ?? '');
        [$messageId, $refus] = Amis::ecrire($moi, $id, $texte);

        if (veut_du_json()) {
            if ($messageId === null) {
                http_response_code(422);
                repondre_json(['fait' => false, 'message' => $refus]);
            }
            // La réponse part d'abord : l'envoi aux téléphones ne fait pas attendre la page.
            $this->repondreAvant(['fait' => true, 'id' => $messageId]);
            Amis::notifier($moi, $id, $texte);
            exit;
        }

        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Amis::notifier($moi, $id, $texte);
        }
        redirect('amis/' . $id);
    }

    /**
     * Envoie la réponse et ferme la connexion, puis laisse le script continuer :
     * le navigateur a son résultat pendant que les notifications partent.
     */
    private function repondreAvant(array $donnees): void
    {
        session_write_close();
        ignore_user_abort(true);
        $corps = (string) json_encode($donnees, JSON_UNESCAPED_UNICODE);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($corps));
        header('Connection: close');
        echo $corps;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /** Revient à la page d'où venait le geste, recherche comprise. */
    private function retour(): never
    {
        $pseudo = trim((string) ($_POST['recherche'] ?? ''));
        redirect('amis', $pseudo === '' ? [] : ['pseudo' => $pseudo]);
    }
}
