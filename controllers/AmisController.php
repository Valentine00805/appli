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

        // Prévenir l'autre : d'une demande, ou — demandes croisées — de l'acceptation.
        $aEnvoyer = match ($resultat) {
            'envoyee' => Amis::notifierDemande($moi, $autre),
            'acceptee' => Amis::notifierAcceptation($moi, $autre),
            default => null,
        };

        $this->retour($aEnvoyer);
    }

    public function accepter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $compte = Amis::compte($id);

        $aEnvoyer = null;
        if ($compte !== null && Amis::accepter(Auth::id(), $id)) {
            Session::flash('succes', 'Vous êtes maintenant amis avec ' . $compte['pseudo'] . '.');
            $aEnvoyer = Amis::notifierAcceptation(Auth::id(), $id);
        } else {
            Session::flash('erreur', 'Cette demande n’existe plus.');
        }
        $this->retour($aEnvoyer);
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
        $maintenant = Amis::maintenant();
        $messages = Amis::fil($moi, $id);
        Amis::regarder($moi, $id);
        $amis = Amis::liste($moi);

        Vue::afficher('amis/conversation', [
            'ami' => $ami,
            'messages' => $messages,
            'vuJusqua' => Amis::vuJusqua($moi, $id),
            'maintenant' => $maintenant,
            'amis' => $amis,
            'derniers' => Amis::messagesParId(array_column($amis, 'dernier_id')),
        ], 'Discussion avec ' . $ami['pseudo']);
    }

    /** Le profil d'un ami : ce qu'on a échangé, et de quoi le retirer de ses amis. */
    public function profil(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $ami = Amis::compte($id);
        if ($ami === null || !Amis::sontAmis($moi, $id)) {
            Session::flash('erreur', 'Ce profil n’est visible que de ses amis.');
            redirect('amis');
        }

        $relation = Amis::relation($moi, $id);
        $partages = Amis::partages($moi, $id);
        $donnees = [
            'ami' => $ami,
            'amisDepuis' => ($relation['acceptee_le'] ?? null) === null ? null
                : date_fr(Amis::local((string) $relation['acceptee_le'])->format('Y-m-d H:i:s'), false),
            'photos' => $partages['photos'],
            'fichiersPartages' => $partages['fichiers'],
            'messages' => $partages['messages'],
        ];

        if (Vue::enFenetre()) {
            Vue::fragment('amis/profil', $donnees);
            return;
        }
        Vue::afficher('amis/profil', $donnees, $ami['pseudo'] . ' · Profil');
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

        $apres = max(0, (int) ($_GET['apres'] ?? 0));
        // L'instant est pris avant de lire : ce qui change pendant le relevé sera vu au suivant.
        $maintenant = Amis::maintenant();
        repondre_json([
            'fait' => true,
            'messages' => Amis::fil($moi, $id, $apres),
            'vu_jusqua' => Amis::vuJusqua($moi, $id),
            'modifies' => Amis::modifications($moi, $id, (string) ($_GET['modifies_depuis'] ?? '')),
            'maintenant' => $maintenant,
        ] + Amis::changements($moi, $id, $apres));
    }

    /** Modifie le texte d'un de ses messages. */
    public function modifierMessage(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        [$fait, $message] = Amis::modifierMessage(Auth::id(), $id, (string) ($_POST['texte'] ?? ''));
        if (veut_du_json()) {
            if (!$fait) {
                http_response_code(422);
            }
            repondre_json(['fait' => $fait, 'message' => $message]);
        }
        Session::flash($fait ? 'succes' : 'erreur', $message);
        redirect('amis');
    }

    /** Supprime un message, pour soi (« moi ») ou pour les deux (« tous »). */
    public function supprimerMessage(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $portee = ($_POST['portee'] ?? '') === 'tous' ? 'tous' : 'moi';
        $resultat = Amis::supprimerMessage(Auth::id(), $id, $portee);
        $message = match ($resultat) {
            'fait' => $portee === 'tous' ? 'Message supprimé pour tout le monde.' : 'Message supprimé de votre conversation.',
            'interdit' => 'Seul qui a écrit un message peut le supprimer pour tout le monde.',
            default => 'Ce message est introuvable.',
        };

        if (veut_du_json()) {
            if ($resultat !== 'fait') {
                http_response_code($resultat === 'interdit' ? 403 : 404);
            }
            repondre_json(['fait' => $resultat === 'fait', 'message' => $message]);
        }
        Session::flash($resultat === 'fait' ? 'succes' : 'erreur', $message);
        redirect('amis');
    }

    /** Envoie un message ; en JSON pour la page, ou par un envoi ordinaire. */
    public function envoyer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();

        $texte = (string) ($_POST['texte'] ?? '');
        // Une pièce jointe à la fois : un envoi de plusieurs fichiers sous le même nom est ignoré.
        $televerse = static fn (string $champ): ?array => isset($_FILES[$champ]) && is_array($_FILES[$champ])
            && !is_array($_FILES[$champ]['name'] ?? null) ? $_FILES[$champ] : null;
        $image = $televerse('image');
        $fichier = $televerse('fichier');
        [$messageId, $refus] = Amis::ecrire($moi, $id, $texte, $image, $fichier, entier_ou_null($_POST['reponse_a'] ?? null));
        $avecImage = $messageId !== null && $image !== null && ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $nomFichier = $messageId !== null && $fichier !== null && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            ? (string) Database::valeur('SELECT fichier_origine FROM messages WHERE id = ?', [$messageId]) : null;

        if (veut_du_json()) {
            if ($messageId === null) {
                http_response_code(422);
                repondre_json(['fait' => false, 'message' => $refus]);
            }
            // La réponse part d'abord : l'envoi aux téléphones ne fait pas attendre la page.
            // La notification est écrite avant de répondre : même si l'envoi qui suit échoue, la file la retentera.
            $aEnvoyer = Amis::notifier($moi, $id, $texte, $avecImage, $nomFichier);
            $this->repondreAvant(['fait' => true, 'id' => $messageId]);
            if ($aEnvoyer !== null) {
                FileNotifications::envoyer($aEnvoyer);
            }
            exit;
        }

        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('amis/' . $id);
        }
        $this->redirigerPuisEnvoyer(url('amis/' . $id), Amis::notifier($moi, $id, $texte, $avecImage, $nomFichier));
    }

    /**
     * Renvoie vers une page, ferme la connexion, puis envoie la notification
     * en file : la page suivante n'attend pas le service de notifications.
     */
    private function redirigerPuisEnvoyer(string $adresse, ?int $notification): never
    {
        if ($notification === null) {
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
        FileNotifications::envoyer($notification);
        exit;
    }

    /** Le fichier joint à un message, pour les deux amis seulement ; « telecharger=1 » le donne à enregistrer. */
    public function fichier(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $message = Amis::fichier(Auth::id(), $id);
        if ($message === null) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        // Même envoi que les pièces jointes des cours : seuls les types sûrs s'ouvrent dans le navigateur.
        Fichiers::envoyer([
            'nom_stocke' => (string) $message['fichier_nom'],
            'nom_origine' => (string) $message['fichier_origine'],
            'mime' => (string) $message['fichier_mime'],
        ], ($_GET['telecharger'] ?? '') === '1', Amis::dossierImages());
    }

    /** L'image d'un message, pour les deux amis seulement. */
    public function image(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $message = Amis::image(Auth::id(), $id);
        $chemin = $message === null ? null : Amis::dossierImages() . DIRECTORY_SEPARATOR . basename((string) $message['image_nom']);

        if ($chemin === null || !is_file($chemin) || !in_array($message['image_mime'], array_column(Amis::IMAGE_TYPES, 0), true)) {
            http_response_code(404);
            exit('Image introuvable.');
        }

        header('Content-Type: ' . $message['image_mime']);
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        header('Content-Disposition: inline; filename="photo-' . (int) $message['id'] . '.' . pathinfo($chemin, PATHINFO_EXTENSION) . '"');
        // Une image ne change jamais : le navigateur la garde, mais pour lui seul.
        header('Cache-Control: private, max-age=604800, immutable');
        readfile($chemin);
        exit;
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
    private function retour(?int $notification = null): never
    {
        $pseudo = trim((string) ($_POST['recherche'] ?? ''));
        $this->redirigerPuisEnvoyer(url('amis', $pseudo === '' ? [] : ['pseudo' => $pseudo]), $notification);
    }
}
