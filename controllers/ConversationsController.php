<?php
declare(strict_types=1);

/**
 * Les discussions de groupe : les créer, y écrire, les régler, les quitter.
 *
 * « groupes/12 » est la conversation 12, ouverte à ses seuls membres. Les
 * réponses ont la même forme que celles des discussions entre amis : la page
 * et son script sont les mêmes.
 */
final class ConversationsController
{
    /** « Nouveau message », en fenêtre : un ami à qui écrire, ou un groupe à créer. */
    public function nouvelleDiscussion(): void
    {
        Auth::exiger();
        $amis = Amis::liste(Auth::id());
        usort($amis, static fn (array $x, array $y): int => strcasecmp((string) $x['pseudo'], (string) $y['pseudo']));
        if (Vue::enFenetre()) {
            Vue::fragment('amis/nouvelle_discussion', ['amis' => $amis]);
            return;
        }
        Vue::afficher('amis/nouvelle_discussion', ['amis' => $amis], t('titre.nouveau_message'));
    }

    /** Le formulaire de création, en fenêtre : un nom, et des amis à cocher. */
    public function nouveau(): void
    {
        Auth::exiger();
        $donnees = [
            'amis' => Amis::liste(Auth::id()),
            'aUnPseudo' => (string) (Auth::utilisateur()['pseudo'] ?? '') !== '',
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('amis/groupe_nouveau', $donnees);
            return;
        }
        Vue::afficher('amis/groupe_nouveau', $donnees, t('titre.nouveau_groupe'));
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        $ids = is_array($_POST['membres'] ?? null) ? $_POST['membres'] : [];
        [$conversation, $refus] = Conversations::creer($moi, (string) ($_POST['nom'] ?? ''), $ids);
        if ($conversation === null) {
            Session::flash('erreur', $refus);
            redirect('amis');
        }
        Session::flash('succes', t('grp.fl.cree'));
        $notifications = Conversations::notifierAjout($moi, $conversation, array_map('intval', array_column(
            Database::all('SELECT user_id FROM conversation_membres WHERE conversation_id = ? AND user_id <> ?', [$conversation, $moi]), 'user_id'
        )));
        $this->redirigerPuisEnvoyer(url('groupes/' . $conversation), $notifications);
    }

    /** La conversation de groupe. */
    public function conversation(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $groupe = Conversations::conversation($id, $moi);
        if ($groupe === null) {
            Session::flash('erreur', t('grp.fl.introuvable'));
            redirect('amis');
        }

        $maintenant = Amis::maintenant();
        $cible = entier_ou_null($_GET['message'] ?? null);
        $messages = Conversations::fil($moi, $id, 0, $cible);
        Conversations::regarder($moi, $id);

        Vue::afficher('amis/conversation', [
            'groupe' => $groupe,
            'nombreMembres' => count(Conversations::membres($id)),
            'messages' => $messages,
            'vuJusqua' => Conversations::vuJusqua($moi, $id),
            'maintenant' => $maintenant,
            'epingles' => Conversations::epingles($moi, $id),
            'adresseFond' => Conversations::adresseFond($id, $groupe['fond_nom']),
            'cible' => $cible,
        ] + self::liste($moi), $groupe['nom']);
    }

    /** De quoi dessiner la liste des discussions : amis et groupes. */
    public static function liste(int $moi): array
    {
        $amis = Amis::liste($moi);
        $groupes = Conversations::liste($moi);

        return [
            'amis' => $amis,
            'derniers' => Amis::messagesParId(array_column($amis, 'dernier_id')),
            'groupes' => $groupes,
            'derniersGroupes' => Conversations::messagesParId(array_column($groupes, 'dernier_id')),
        ];
    }

    /** Les réglages du groupe, en fenêtre : nom, fond, membres, partages, départ. */
    public function reglages(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $groupe = Conversations::conversation($id, $moi);
        if ($groupe === null) {
            Session::flash('erreur', t('grp.fl.introuvable'));
            redirect('amis');
        }
        $membres = Conversations::membres($id);
        $dans = array_flip(array_column($membres, 'id'));
        $partages = Conversations::partages($moi, $id);
        $donnees = [
            'groupe' => $groupe,
            'membres' => $membres,
            'admin' => $groupe['role'] === 'admin',
            'nombreAdmins' => count(array_filter($membres, static fn (array $m): bool => $m['role'] === 'admin')),
            'invitations' => Conversations::invitations($id),
            'aAjouter' => array_values(array_filter(Amis::liste($moi), static fn (array $a): bool => !isset($dans[(int) $a['id']]))),
            'photos' => $partages['photos'],
            'fichiersPartages' => $partages['fichiers'],
            'adresseFond' => Conversations::adresseFond($id, $groupe['fond_nom']),
            'fondPar' => $groupe['fond_par'] === null ? null
                : ((int) $groupe['fond_par'] === $moi ? 'vous' : (string) (Amis::compte((int) $groupe['fond_par'])['pseudo'] ?? '')),
            'muette' => Conversations::estMuette($id, $moi),
            'coupure' => Conversations::coupure($id, $moi),
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('amis/groupe_reglages', $donnees);
            return;
        }
        Vue::afficher('amis/groupe_reglages', $donnees, t('titre.reglages_de', ['nom' => (string) $groupe['nom']]));
    }

    /** Les nouveaux messages, pour la page ouverte. */
    public function nouveaux(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        session_write_close();
        $groupe = Conversations::conversation($id, $moi);
        if ($groupe === null) {
            http_response_code(403);
            repondre_json(['fait' => false, 'message' => t('grp.fl.plus_membre')]);
        }
        if (($_GET['visible'] ?? '') === '1') {
            Conversations::regarder($moi, $id);
        }

        $apres = max(0, (int) ($_GET['apres'] ?? 0));
        $depuis = (string) ($_GET['modifies_depuis'] ?? '');
        $maintenant = Amis::maintenant();
        repondre_json([
            'fait' => true,
            'messages' => Conversations::fil($moi, $id, $apres),
            'vu_jusqua' => Conversations::vuJusqua($moi, $id),
            'modifies' => Conversations::modifications($moi, $id, $depuis),
            'reactions' => Conversations::reactionsModifiees($moi, $id, $depuis),
            'maintenant' => $maintenant,
            'fond' => Conversations::adresseFond($id, $groupe['fond_nom']),
            'titre' => (string) $groupe['nom'],
            'photo' => Conversations::adressePhoto($id, $groupe['photo_nom']),
        ] + Conversations::changements($moi, $id, $apres));
    }

    public function envoyer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();

        $texte = (string) ($_POST['texte'] ?? '');
        $televerse = static fn (string $champ): ?array => isset($_FILES[$champ]) && is_array($_FILES[$champ])
            && !is_array($_FILES[$champ]['name'] ?? null) ? $_FILES[$champ] : null;
        $image = $televerse('image');
        $fichier = $televerse('fichier');
        $vocal = $televerse('vocal');
        [$messageId, $refus] = Conversations::ecrire($moi, $id, $texte, $image, $fichier, entier_ou_null($_POST['reponse_a'] ?? null),
            $vocal, (int) ($_POST['duree'] ?? 0), isset($_POST['transcription']) && is_string($_POST['transcription']) ? $_POST['transcription'] : null);

        $notifications = [];
        if ($messageId !== null) {
            $ligne = Database::one('SELECT image_nom, fichier_origine, audio_duree FROM conversation_messages WHERE id = ?', [$messageId]) ?? [];
            $notifications = Conversations::notifier($moi, $id, $texte, ($ligne['image_nom'] ?? null) !== null,
                $ligne['fichier_origine'] ?? null, isset($ligne['audio_duree']) ? (int) $ligne['audio_duree'] : null);
        }

        if (veut_du_json()) {
            if ($messageId === null) {
                http_response_code(422);
                repondre_json(['fait' => false, 'message' => $refus]);
            }
            $this->repondreAvant(['fait' => true, 'id' => $messageId]);
            foreach ($notifications as $n) {
                FileNotifications::envoyer($n);
            }
            exit;
        }
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        }
        $this->redirigerPuisEnvoyer(url(Conversations::membre($id, $moi) !== null ? 'groupes/' . $id : 'amis'), $notifications);
    }

    public function rechercher(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        session_write_close();
        if (Conversations::membre($id, $moi) === null) {
            http_response_code(403);
            repondre_json(['fait' => false, 'message' => t('grp.fl.plus_membre')]);
        }
        repondre_json(['fait' => true] + Conversations::rechercher($moi, $id, (string) ($_GET['q'] ?? '')));
    }

    public function renommer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $refus = Conversations::renommer(Auth::id(), $id, (string) ($_POST['nom'] ?? ''));
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('grp.fl.renomme'));
        $this->retour($id);
    }

    /** Recevoir, ou non, les notifications de ce groupe. */
    public function notifications(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        // Décochée : coupée ; « pendant » (1h, 1j…) lui donne une fin.
        $pendant = (string) ($_POST['pendant'] ?? '');
        $muette = ($_POST['recevoir'] ?? '1') !== '1' || isset(FileNotifications::DUREES[$pendant]);
        $jusqua = $muette ? FileNotifications::finDans($pendant) : null;
        if (!Conversations::rendreMuette($id, Auth::id(), $muette, $jusqua)) {
            $this->retour($id);
        }
        Session::flash('succes', $muette
            ? '🔕 ' . FileNotifications::texteCoupure($jusqua)
            : t('grp.fl.notifications_retablies'));
        // Le script de la carte n'attend que la réponse : il met la carte à jour lui-même.
        if (veut_du_json()) {
            Session::flashs();
            repondre_json(['muette' => $muette, 'etat' => $muette
                ? FileNotifications::texteCoupure($jusqua)
                : t('ami.fl.notif_etat')]);
        }
        redirect('groupes/' . $id . '/reglages');
    }

    public function ajouter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        [$ajoutes, $refus] = Conversations::ajouter($moi, $id, is_array($_POST['membres'] ?? null) ? $_POST['membres'] : []);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            $this->retour($id);
        }
        Session::flash('succes', tn('grp.fl.membres_ajoutes', count($ajoutes)));
        $this->redirigerPuisEnvoyer(url('groupes/' . $id), Conversations::notifierAjout($moi, $id, $ajoutes));
    }

    public function retirerMembre(int $id, int $membre): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Conversations::retirer(Auth::id(), $id, $membre);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? $pseudo . ' ne fait plus partie du groupe.');
        $this->retour($id);
    }

    public function nommerAdmin(int $id, int $membre): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Conversations::nommerAdmin(Auth::id(), $id, $membre);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('grp.fl.nomme_admin', ['qui' => $pseudo]));
        $this->retour($id);
    }

    public function retirerAdmin(int $id, int $membre): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Conversations::retirerAdmin($moi, $id, $membre);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? ($membre === $moi
            ? t('grp.fl.vous_plus_admin')
            : t('grp.fl.plus_admin', ['qui' => $pseudo])));
        $this->retour($id);
    }

    /** Des pseudos à ajouter au groupe, pour ses réglages (administrateurs). */
    public function chercher(int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        session_write_close();
        if (!Conversations::estAdmin($id, $moi)) {
            http_response_code(403);
            repondre_json(['fait' => false, 'message' => t('grp.admins_ajout')]);
        }
        repondre_json(['fait' => true, 'resultats' => Conversations::chercher($moi, $id, (string) ($_GET['pseudo'] ?? ''))]);
    }

    /** Ajoute un ami, ou invite un autre compte, trouvé par son pseudo. */
    public function inviter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();
        $cible = (int) ($_POST['compte'] ?? 0);
        $pseudo = (string) (Amis::compte($cible)['pseudo'] ?? '');
        [$resultat, $refus] = Conversations::inviter($moi, $id, $cible);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            $this->retour($id);
        }
        if ($resultat === 'ajoute') {
            Session::flash('succes', t('grp.fl.ajoute_au_groupe', ['qui' => $pseudo]));
            $this->redirigerPuisEnvoyer(url('groupes/' . $id), Conversations::notifierAjout($moi, $id, [$cible]));
        }
        Session::flash('succes', t('grp.fl.invitation_envoyee', ['qui' => $pseudo]));
        $notification = Conversations::notifierInvitation($moi, $id, $cible);
        $this->redirigerPuisEnvoyer(url('groupes/' . $id), $notification === null ? [] : [$notification]);
    }

    public function annulerInvitation(int $id, int $membre): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($membre)['pseudo'] ?? '');
        $refus = Conversations::annulerInvitation(Auth::id(), $id, $membre);
        Session::flash($refus === null ? 'succes' : 'erreur',
            $refus ?? t('grp.fl.invitation_annulee', ['qui' => $pseudo]));
        $this->retour($id);
    }

    /** Accepte une invitation reçue : on entre dans le groupe. */
    public function rejoindre(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $refus = Conversations::repondreInvitation(Auth::id(), $id, true);
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('amis');
        }
        Session::flash('succes', t('grp.fl.rejoint'));
        redirect('groupes/' . $id);
    }

    public function refuser(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $refus = Conversations::repondreInvitation(Auth::id(), $id, false);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('grp.fl.invitation_refusee'));
        redirect('amis');
    }

    public function quitter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $nom = (string) (Conversations::conversation($id, Auth::id())['nom'] ?? '');
        if (Conversations::quitter(Auth::id(), $id)) {
            Session::flash('succes', t('grp.fl.quitte', ['nom' => $nom]));
        } else {
            Session::flash('erreur', t('grp.fl.pas_dans_groupe'));
        }
        redirect('amis');
    }

    public function photo(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $photo = Conversations::photo($id, Auth::id());
        $chemin = $photo === null ? null : Amis::dossierImages() . DIRECTORY_SEPARATOR . basename((string) $photo['photo_nom']);
        if ($chemin === null || !is_file($chemin) || !in_array($photo['photo_mime'], array_column(Amis::IMAGE_TYPES, 0), true)) {
            http_response_code(404);
            exit(t('corps.photo_introuvable'));
        }
        $this->image($chemin, (string) $photo['photo_mime'], 'photo-groupe');
    }

    public function changerPhoto(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $image = isset($_FILES['photo']) && is_array($_FILES['photo']) && !is_array($_FILES['photo']['name'] ?? null) ? $_FILES['photo'] : null;
        $refus = Conversations::changerPhoto(Auth::id(), $id, $image);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('grp.fl.photo_changee'));
        $this->retour($id);
    }

    public function retirerPhoto(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $retiree = Conversations::retirerPhoto(Auth::id(), $id);
        Session::flash($retiree ? 'succes' : 'info',
            $retiree ? t('grp.fl.photo_retiree') : t('grp.fl.photo_absente'));
        $this->retour($id);
    }

    public function fond(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $groupe = Conversations::conversation($id, Auth::id());
        $chemin = $groupe === null || $groupe['fond_nom'] === null ? null
            : Amis::dossierImages() . DIRECTORY_SEPARATOR . basename((string) $groupe['fond_nom']);
        if ($chemin === null || !is_file($chemin) || !in_array($groupe['fond_mime'], array_column(Amis::IMAGE_TYPES, 0), true)) {
            http_response_code(404);
            exit(t('corps.fond_introuvable'));
        }
        $this->image($chemin, (string) $groupe['fond_mime'], 'fond');
    }

    public function changerFond(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $image = isset($_FILES['fond']) && is_array($_FILES['fond']) && !is_array($_FILES['fond']['name'] ?? null) ? $_FILES['fond'] : null;
        $refus = Conversations::changerFond(Auth::id(), $id, $image);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('grp.fl.fond_change'));
        $this->retour($id);
    }

    public function retirerFond(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $retire = Conversations::retirerFond(Auth::id(), $id);
        Session::flash($retire ? 'succes' : 'info',
            $retire ? t('grp.fl.fond_retire') : t('grp.fl.fond_absent'));
        $this->retour($id);
    }

    public function epingler(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $voulu = ($_POST['epingle'] ?? '') === '1';
        [$fait, $message, $conversation] = Conversations::epingler(Auth::id(), $id, $voulu);
        if (!$fait) {
            http_response_code(422);
            repondre_json(['fait' => false, 'message' => $message]);
        }
        repondre_json(['fait' => true, 'message' => $message, 'epingle' => $voulu, 'epingles' => Conversations::epingles(Auth::id(), (int) $conversation)]);
    }

    public function reagir(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$fait, $message, $reactions, $notification] = Conversations::reagir(Auth::id(), $id, (string) ($_POST['emoji'] ?? ''));
        if (!$fait) {
            http_response_code(422);
            repondre_json(['fait' => false, 'message' => $message]);
        }
        $this->repondreAvant(['fait' => true, 'message' => $message, 'reactions' => $reactions]);
        if ($notification !== null) {
            FileNotifications::envoyer($notification);
        }
        exit;
    }

    public function modifierMessage(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$fait, $message] = Conversations::modifierMessage(Auth::id(), $id, (string) ($_POST['texte'] ?? ''));
        if (!$fait) {
            http_response_code(422);
        }
        repondre_json(['fait' => $fait, 'message' => $message]);
    }

    public function supprimerMessage(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $portee = ($_POST['portee'] ?? '') === 'tous' ? 'tous' : 'moi';
        $resultat = Conversations::supprimerMessage(Auth::id(), $id, $portee);
        if ($resultat !== 'fait') {
            http_response_code($resultat === 'interdit' ? 403 : 404);
        }
        repondre_json(['fait' => $resultat === 'fait', 'message' => match ($resultat) {
            'fait' => t($portee === 'tous' ? 'ami.fl.supprime_tous' : 'ami.fl.supprime_moi'),
            'interdit' => t('ami.fl.supprime_interdit'),
            default => t('msg.introuvable'),
        }]);
    }

    public function imageMessage(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $message = Conversations::piece(Auth::id(), $id, 'image_nom');
        $chemin = $message === null ? null : Amis::dossierImages() . DIRECTORY_SEPARATOR . basename((string) $message['image_nom']);
        if ($chemin === null || !is_file($chemin) || !in_array($message['image_mime'], array_column(Amis::IMAGE_TYPES, 0), true)) {
            http_response_code(404);
            exit(t('corps.image_introuvable'));
        }
        $this->image($chemin, (string) $message['image_mime'], 'photo-' . $id);
    }

    public function fichier(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $message = Conversations::piece(Auth::id(), $id, 'fichier_nom');
        if ($message === null) {
            http_response_code(404);
            exit(t('corps.fichier_introuvable'));
        }
        Fichiers::envoyer([
            'nom_stocke' => (string) $message['fichier_nom'],
            'nom_origine' => (string) $message['fichier_origine'],
            'mime' => (string) $message['fichier_mime'],
        ], ($_GET['telecharger'] ?? '') === '1', Amis::dossierImages());
    }

    public function vocal(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $message = Conversations::piece(Auth::id(), $id, 'audio_nom');
        if ($message === null) {
            http_response_code(404);
            exit(t('corps.vocal_introuvable'));
        }
        $extension = pathinfo((string) $message['audio_nom'], PATHINFO_EXTENSION);
        Fichiers::envoyer([
            'nom_stocke' => (string) $message['audio_nom'],
            'nom_origine' => 'message-vocal-' . $id . '.' . $extension,
            'mime' => match ($extension) { 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', default => 'audio/webm' },
        ], false, Amis::dossierImages());
    }

    /** Envoie une image rangée, pour les seuls membres, gardée par leur navigateur. */
    private function image(string $chemin, string $mime, string $nom): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        header('Content-Disposition: inline; filename="' . $nom . '.' . pathinfo($chemin, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: private, max-age=604800, immutable');
        readfile($chemin);
        exit;
    }

    /** Revient à la conversation, ou à « Amis » si on n'en fait plus partie. */
    private function retour(int $id): never
    {
        redirect(Conversations::membre($id, Auth::id()) !== null ? 'groupes/' . $id : 'amis');
    }

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
