<?php
declare(strict_types=1);

/**
 * L'espace des travaux de groupe : la liste de mes projets, et chaque projet
 * en cinq onglets — qui fait quoi, échéances, fichiers, document, membres.
 * Les règles (qui peut quoi) vivent dans Travaux ; ici, on lit le formulaire,
 * on appelle, et on dit ce qui s'est passé.
 */
final class TravauxController
{
    public function index(): void
    {
        Auth::exiger();
        $moi = Auth::id();
        Vue::afficher('travaux/index', [
            'projets'     => Travaux::mesProjets($moi),
            'invitations' => Travaux::invitations($moi),
            'mesTaches'   => Travaux::mesTaches($moi, 8),
        ], 'Travaux de groupe');
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$projet, $refus] = Travaux::creer(Auth::id(), post('nom'), post('description'),
            is_array($_POST['amis'] ?? null) ? $_POST['amis'] : []);
        if ($projet === null) {
            Session::flash('erreur', (string) $refus);
            redirect('travaux/nouveau');
        }
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        }
        Session::flash('succes', 'Travail de groupe créé. Répartissez les premières tâches.');
        redirect('travaux/' . $projet);
    }

    /**
     * Les formulaires de création s’ouvrent dans une fenêtre, par-dessus la
     * page ; sans script, ils sont une page à part entière.
     */
    public function nouveau(): void
    {
        Auth::exiger();
        $this->formulaire('travaux/nouveau', ['amis' => Amis::liste(Auth::id())], 'Nouveau travail de groupe');
    }

    public function nouvelleTache(int $id): void
    {
        $projet = $this->projet($id);
        $this->formulaire('travaux/nouvelle_tache',
            ['projet' => $projet, 'membres' => Travaux::membresActifs($id)], 'Nouvelle tâche');
    }

    public function nouvelleEcheance(int $id): void
    {
        $projet = $this->projet($id);
        $this->formulaire('travaux/nouvelle_echeance',
            ['projet' => $projet, 'types' => Travaux::types($id)], 'Nouvelle échéance');
    }

    private function formulaire(string $vue, array $donnees, string $titre): void
    {
        if (Vue::enFenetre()) {
            Vue::fragment($vue, $donnees);
            return;
        }
        Vue::afficher($vue, $donnees, $titre);
    }

    // --- Les onglets -----------------------------------------------------------

    public function taches(int $id): void
    {
        $projet = $this->projet($id);
        $taches = Travaux::taches($id);
        $this->afficher('travaux/taches', $projet, [
            'taches'  => $taches,
            'membres' => Travaux::membresActifs($id),
            'filtre'  => in_array($_GET['voir'] ?? '', ['moi', 'personne'], true) ? $_GET['voir'] : 'tous',
        ], 'taches');
    }

    public function echeances(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/echeances', $projet,
            ['echeances' => Travaux::echeances($id), 'types' => Travaux::types($id)], 'echeances');
    }

    public function fichiers(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/fichiers', $projet, ['fichiers' => Travaux::fichiers($id)], 'fichiers');
    }

    public function document(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/document', $projet, [
            'versions'  => Travaux::versions($id),
            'brouillon' => Session::reprendre('travaux-document-' . $id),
            'auteur'    => $projet['document_par'] === null ? null : Amis::compte((int) $projet['document_par']),
        ], 'document');
    }

    public function membres(int $id): void
    {
        $projet = $this->projet($id);
        $membres = Travaux::membres($id);
        $dedans = array_filter(array_map(static fn (array $m): ?int => $m['user_id'] === null ? null : (int) $m['user_id'], $membres));
        $this->afficher('travaux/membres', $projet, [
            'membres'       => $membres,
            'amisAInviter'  => array_values(array_filter(Amis::liste(Auth::id()),
                static fn (array $a): bool => !in_array((int) $a['id'], $dedans, true))),
            'discussions'   => Database::all(
                'SELECT c.id, c.nom FROM conversations c
                   JOIN conversation_membres mb ON mb.conversation_id = c.id AND mb.user_id = ?
                  ORDER BY c.nom', [Auth::id()]),
        ], 'membres');
    }

    // --- Le projet -------------------------------------------------------------

    public function modifier(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::modifier(Auth::id(), $id, post('nom'), post('description')),
            'Projet mis à jour.', 'travaux/' . $id . '/membres');
    }

    public function supprimer(int $id): void
    {
        $this->exigerPost();
        $nom = (string) (Travaux::projet($id, Auth::id())['nom'] ?? '');
        if (!Travaux::supprimer(Auth::id(), $id)) {
            Session::flash('erreur', 'Seuls les administrateurs du projet peuvent le supprimer.');
            redirect('travaux/' . $id . '/membres');
        }
        Session::flash('succes', '« ' . $nom . ' » supprimé, avec ses fichiers et ses échéances.');
        redirect('travaux');
    }

    public function rejoindre(int $id): void
    {
        $this->exigerPost();
        if (!Travaux::accepter(Auth::id(), $id)) {
            Session::flash('erreur', 'Cette invitation n’existe plus.');
            redirect('travaux');
        }
        Session::flash('succes', 'Bienvenue dans le groupe : ses échéances sont dans votre calendrier.');
        redirect('travaux/' . $id);
    }

    public function refuser(int $id): void
    {
        $this->exigerPost();
        Travaux::refuser(Auth::id(), $id);
        Session::flash('succes', 'Invitation refusée.');
        redirect('travaux');
    }

    public function quitter(int $id): void
    {
        $this->exigerPost();
        if (Travaux::quitter(Auth::id(), $id)) {
            Session::flash('succes', 'Vous avez quitté le projet ; ses échéances ont quitté votre calendrier.');
        }
        redirect('travaux');
    }

    public function inviter(int $id): void
    {
        $this->exigerPost();
        [$nombre, $refus] = Travaux::inviter(Auth::id(), $id, is_array($_POST['amis'] ?? null) ? $_POST['amis'] : []);
        $this->finir($refus, $nombre . ' invitation' . ($nombre > 1 ? 's envoyées' : ' envoyée')
            . ' : le projet s’ouvrira pour eux quand ils accepteront.', 'travaux/' . $id . '/membres');
    }

    public function ajouterSansCompte(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::ajouterSansCompte(Auth::id(), $id, post('nom')),
            '« ' . trim(post('nom')) . ' » ajouté : confiez-lui des tâches, et donnez-lui le lien public pour suivre le projet.',
            'travaux/' . $id . '/membres');
    }

    public function retirerMembre(int $id): void
    {
        $this->exigerPost();
        $projet = (int) Database::valeur('SELECT projet_id FROM projet_membres WHERE id = ?', [$id]);
        $this->finir(Travaux::retirer(Auth::id(), $id), 'Retiré du projet.', 'travaux/' . $projet . '/membres');
    }

    public function nommerAdmin(int $id): void
    {
        $this->changerRole($id, true);
    }

    public function retirerAdmin(int $id): void
    {
        $this->changerRole($id, false);
    }

    private function changerRole(int $id, bool $admin): void
    {
        $this->exigerPost();
        $projet = (int) Database::valeur('SELECT projet_id FROM projet_membres WHERE id = ?', [$id]);
        $this->finir(Travaux::changerRole(Auth::id(), $id, $admin),
            $admin ? 'Nommé administrateur.' : 'N’est plus administrateur.', 'travaux/' . $projet . '/membres');
    }

    // --- Qui fait quoi ---------------------------------------------------------

    public function ajouterTache(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $resultat = Travaux::ajouterTache(Auth::id(), $id, post('titre'), $_POST['membre_id'] ?? null,
            post('echeance'), post('note'));
        if (is_string($resultat)) {
            $this->finir($resultat, '', 'travaux/' . $id . '/taches/nouvelle');
        }
        $this->finir(null, 'Tâche ajoutée.', 'travaux/' . $id);
    }

    public function modifierTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::tache(Auth::id(), $id) ?? $this->introuvable();
        $this->finir(Travaux::modifierTache(Auth::id(), $id, post('titre'), $_POST['membre_id'] ?? null,
            post('echeance'), post('note')), 'Tâche modifiée.', 'travaux/' . (int) $tache['projet_id']);
    }

    public function statutTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::tache(Auth::id(), $id) ?? $this->introuvable();
        $fait = Travaux::changerStatut(Auth::id(), $id, post('statut'));
        if (veut_du_json()) {
            repondre_json(['fait' => $fait]);
        }
        repartir_vers('travaux/' . (int) $tache['projet_id']);
    }

    public function prendreTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::tache(Auth::id(), $id) ?? $this->introuvable();
        Travaux::prendre(Auth::id(), $id);
        Session::flash('succes', 'C’est pour vous : « ' . $tache['titre'] . ' ».');
        repartir_vers('travaux/' . (int) $tache['projet_id']);
    }

    public function supprimerTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::supprimerTache(Auth::id(), $id) ?? $this->introuvable();
        Session::flash('succes', '« ' . $tache['titre'] . ' » supprimée.');
        redirect('travaux/' . (int) $tache['projet_id']);
    }

    // --- Les échéances ---------------------------------------------------------

    public function poserEcheance(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $donnees = Travaux::lireEcheance($_POST, $id);
        if (is_string($donnees)) {
            $this->finir($donnees, '', 'travaux/' . $id . '/echeances/nouvelle');
        }
        Travaux::poserEcheance(Auth::id(), $id, $donnees);
        Session::flash('succes', '« ' . $donnees['titre'] . ' » posée dans le calendrier de chaque membre, avec ses rappels.');
        redirect('travaux/' . $id . '/echeances');
    }

    public function modifierEcheance(int $id): void
    {
        $this->exigerPost();
        $echeance = Travaux::echeance(Auth::id(), $id) ?? $this->introuvable();
        $retour = 'travaux/' . (int) $echeance['projet_id'] . '/echeances';
        $donnees = Travaux::lireEcheance($_POST, (int) $echeance['projet_id']);
        if (is_string($donnees)) {
            $this->finir($donnees, '', $retour);
        }
        Travaux::modifierEcheance($id, $donnees);
        Session::flash('succes', 'Échéance modifiée, dans le calendrier de chacun aussi.');
        redirect($retour);
    }

    public function supprimerEcheance(int $id): void
    {
        $this->exigerPost();
        $echeance = Travaux::echeance(Auth::id(), $id) ?? $this->introuvable();
        Database::run('DELETE FROM projet_echeances WHERE id = ?', [$id]);
        Session::flash('succes', '« ' . $echeance['titre'] . ' » retirée, du calendrier de chacun aussi.');
        redirect('travaux/' . (int) $echeance['projet_id'] . '/echeances');
    }

    // --- Les types d'échéance ----------------------------------------------------

    /** Régler les types du projet, comme ceux d'évènement dans Organisation. */
    public function types(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/types', $projet, ['types' => Travaux::types($id)], 'echeances');
    }

    /** Un nouveau type, en fenêtre. */
    public function nouveauType(int $id): void
    {
        $projet = $this->projet($id);
        $this->formulaire('travaux/type', [
            'projet' => $projet, 'type' => null,
            'palette' => TypesEvenementController::PALETTE, 'icones' => Travaux::icones(),
        ], 'Nouveau type');
    }

    /** Modifier un type, en fenêtre. */
    public function formulaireType(int $id): void
    {
        Auth::exiger();
        $type = Travaux::type(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/type', [
            'projet' => Travaux::projet((int) $type['projet_id'], Auth::id()), 'type' => $type,
            'palette' => TypesEvenementController::PALETTE, 'icones' => Travaux::icones(),
        ], 'Modifier le type');
    }

    public function creerType(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $refus = Travaux::enregistrerType($id, null, $_POST);
        // Refusé : on reste sur le formulaire, pour corriger.
        $this->finir($refus, 'Type « ' . trim(post('nom')) . ' » créé.',
            'travaux/' . $id . ($refus === null ? '/types' : '/types/nouveau'));
    }

    public function modifierType(int $id): void
    {
        $this->exigerPost();
        $type = Travaux::type(Auth::id(), $id) ?? $this->introuvable();
        $refus = Travaux::enregistrerType((int) $type['projet_id'], $id, $_POST);
        $this->finir($refus, 'Type mis à jour.',
            $refus === null ? 'travaux/' . (int) $type['projet_id'] . '/types' : 'travaux/types/' . $id . '/modifier');
    }

    public function supprimerType(int $id): void
    {
        $this->exigerPost();
        $type = Travaux::supprimerType(Auth::id(), $id) ?? $this->introuvable();
        $n = (int) $type['nb_echeances'];
        $this->finir(null, 'Type « ' . $type['nom'] . ' » supprimé.'
            . ($n === 0 ? '' : ' ' . $n . ' échéance' . ($n > 1 ? 's gardées' : ' gardée') . ', désormais sans type.'),
            'travaux/' . (int) $type['projet_id'] . '/types');
    }

    public function deplacerType(int $id): void
    {
        $this->exigerPost();
        $projet = Travaux::deplacerType(Auth::id(), $id, post('sens') !== 'bas') ?? $this->introuvable();
        redirect('travaux/' . $projet . '/types');
    }

    // --- Les fichiers ----------------------------------------------------------

    public function deposer(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $compter = static fn (): int => (int) Database::valeur('SELECT COUNT(*) FROM projet_fichiers WHERE projet_id = ?', [$id]);
        $avant = $compter();
        $erreurs = Travaux::deposer($_FILES['fichiers'] ?? [], Auth::id(), $id);
        $recus = $compter() - $avant;
        if ($recus > 0) {
            Session::flash('succes', $recus . ' fichier' . ($recus > 1 ? 's déposés' : ' déposé') . ' pour le groupe.');
        }
        if ($erreurs !== []) {
            Session::flash('erreur', implode(' ', $erreurs));
        } elseif ($recus === 0) {
            Session::flash('erreur', 'Choisissez au moins un fichier.');
        }
        redirect('travaux/' . $id . '/fichiers');
    }

    public function fichier(int $id): void
    {
        Auth::exiger();
        $fichier = Travaux::fichier(Auth::id(), $id) ?? $this->introuvable();
        Fichiers::envoyer($fichier, isset($_GET['telecharger']), Travaux::dossier());
    }

    public function supprimerFichier(int $id): void
    {
        $this->exigerPost();
        $projet = (int) (Travaux::fichier(Auth::id(), $id)['projet_id'] ?? 0);
        $fichier = Travaux::supprimerFichier(Auth::id(), $id);
        if ($fichier === null) {
            Session::flash('erreur', 'Seuls qui l’a déposé et les administrateurs peuvent retirer ce fichier.');
        } else {
            Session::flash('succes', '« ' . $fichier['nom_origine'] . ' » supprimé.');
        }
        redirect($projet > 0 ? 'travaux/' . $projet . '/fichiers' : 'travaux');
    }

    // --- Le document commun ----------------------------------------------------

    public function ecrireDocument(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $contenu = post('document');
        if (!Travaux::ecrireDocument(Auth::id(), $id, $contenu, (int) ($_POST['version'] ?? -1))) {
            // Quelqu'un a écrit entre-temps : on garde ce qu'on avait tapé, à côté.
            Session::garder('travaux-document-' . $id, $contenu);
            Session::flash('erreur', 'Quelqu’un a modifié le document pendant que vous écriviez. '
                . 'Voici sa version ; la vôtre est gardée juste en dessous, pour reprendre ce qui manque.');
        } else {
            Session::flash('succes', 'Document enregistré.');
        }
        redirect('travaux/' . $id . '/document');
    }

    public function version(int $id): void
    {
        Auth::exiger();
        $version = Travaux::version(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/version', ['version' => $version,
            'projet' => Travaux::projet((int) $version['projet_id'], Auth::id())], 'Version du document');
    }

    public function restaurer(int $id): void
    {
        $this->exigerPost();
        $projet = Travaux::restaurer(Auth::id(), $id) ?? $this->introuvable();
        Session::flash('succes', 'Version restaurée. Celle qu’elle remplace reste dans l’historique.');
        redirect('travaux/' . $projet . '/document');
    }

    // --- La discussion et le lien public ---------------------------------------

    public function discussion(int $id): void
    {
        $this->exigerPost();
        $conversation = entier_ou_null($_POST['conversation_id'] ?? null);
        $refus = Travaux::relierDiscussion(Auth::id(), $id, $conversation);
        if ($refus !== null) {
            $this->finir($refus, '', 'travaux/' . $id . '/membres');
        }
        $conversation = (int) Database::valeur('SELECT conversation_id FROM projets WHERE id = ?', [$id]);
        Session::flash('succes', 'La discussion du groupe est prête : tous les membres y sont.');
        redirect('groupes/' . $conversation);
    }

    public function delierDiscussion(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::delierDiscussion(Auth::id(), $id) ? null : 'Seuls les administrateurs peuvent la délier.',
            'La discussion n’est plus reliée au projet (elle continue d’exister).', 'travaux/' . $id . '/membres');
    }

    public function ouvrirLien(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::ouvrirLien(Auth::id(), $id) === null ? 'Seuls les administrateurs peuvent ouvrir le lien.' : null,
            'Lien public créé : qui l’a peut suivre le projet, sans rien modifier.', 'travaux/' . $id . '/membres');
    }

    public function fermerLien(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::fermerLien(Auth::id(), $id) ? null : 'Seuls les administrateurs peuvent fermer le lien.',
            'Lien désactivé : il ne mène plus nulle part.', 'travaux/' . $id . '/membres');
    }

    /** Le projet vu par le lien public : tout, en lecture seule. */
    public function public(string $jeton): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $projet = Travaux::parJeton($jeton);
        if ($projet === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        $id = (int) $projet['id'];
        Vue::afficherPublic('travaux/public', [
            'projet'    => $projet,
            'jeton'     => $jeton,
            'membres'   => Travaux::membresActifs($id),
            'taches'    => Travaux::taches($id),
            'echeances' => Travaux::echeances($id),
            'fichiers'  => Travaux::fichiers($id),
        ], (string) $projet['nom']);
    }

    public function fichierPublic(string $jeton, int $id): void
    {
        $projet = Travaux::parJeton($jeton);
        $fichier = $projet === null ? null
            : Database::one('SELECT * FROM projet_fichiers WHERE id = ? AND projet_id = ?', [$id, (int) $projet['id']]);
        if ($fichier === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']), Travaux::dossier());
    }

    // --- Commun ----------------------------------------------------------------

    /** Le projet dont je suis membre, ou 404. */
    private function projet(int $id): array
    {
        Auth::exiger();

        return Travaux::projet($id, Auth::id()) ?? $this->introuvable();
    }

    private function afficher(string $vue, array $projet, array $donnees, string $onglet): void
    {
        // Ouvert depuis la liste, le projet vit dans une fenêtre, onglets compris.
        if (Vue::enFenetre()) {
            Vue::fragment($vue, $donnees + ['projet' => $projet, 'onglet' => $onglet]);
            return;
        }
        Vue::afficher($vue, $donnees + ['projet' => $projet, 'onglet' => $onglet], (string) $projet['nom']);
    }

    private function exigerPost(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
    }

    /** Dit l'erreur, ou la réussite, puis repart. */
    private function finir(?string $refus, string $succes, string $retour): never
    {
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? $succes);
        redirect($retour);
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
