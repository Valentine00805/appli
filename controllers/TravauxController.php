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
        ], t('titre.tr_liste'));
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
        Session::flash('succes', t('tr.fl.cree'));
        redirect('travaux/' . $projet);
    }

    /**
     * Les formulaires de création s’ouvrent dans une fenêtre, par-dessus la
     * page ; sans script, ils sont une page à part entière.
     */
    public function nouveau(): void
    {
        Auth::exiger();
        $this->formulaire('travaux/nouveau', ['amis' => Amis::liste(Auth::id())], t('titre.tr_nouveau'));
    }

    public function nouvelleTache(int $id): void
    {
        $projet = $this->projet($id);
        $this->formulaire('travaux/nouvelle_tache',
            ['projet' => $projet, 'membres' => Travaux::membresActifs($id)], t('titre.tr_nouvelle_tache'));
    }

    public function nouvelleEcheance(int $id): void
    {
        $projet = $this->projet($id);
        $this->formulaire('travaux/nouvelle_echeance',
            ['projet' => $projet, 'types' => Travaux::types($id)], t('titre.tr_nouvelle_echeance'));
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
            t('tr.fl.mis_a_jour'), 'travaux/' . $id . '/membres');
    }

    public function supprimer(int $id): void
    {
        $this->exigerPost();
        $nom = (string) (Travaux::projet($id, Auth::id())['nom'] ?? '');
        if (!Travaux::supprimer(Auth::id(), $id)) {
            Session::flash('erreur', t('tr.fl.admins_supprimer'));
            redirect('travaux/' . $id . '/membres');
        }
        Session::flash('succes', t('tr.fl.supprime', ['nom' => $nom]));
        redirect('travaux');
    }

    public function rejoindre(int $id): void
    {
        $this->exigerPost();
        if (!Travaux::accepter(Auth::id(), $id)) {
            Session::flash('erreur', t('tr.fl.invitation_partie'));
            redirect('travaux');
        }
        Session::flash('succes', t('tr.fl.bienvenue'));
        redirect('travaux/' . $id);
    }

    public function refuser(int $id): void
    {
        $this->exigerPost();
        Travaux::refuser(Auth::id(), $id);
        Session::flash('succes', t('tr.fl.refusee'));
        redirect('travaux');
    }

    public function quitter(int $id): void
    {
        $this->exigerPost();
        if (Travaux::quitter(Auth::id(), $id)) {
            Session::flash('succes', t('tr.fl.quitte'));
        }
        redirect('travaux');
    }

    public function inviter(int $id): void
    {
        $this->exigerPost();
        [$nombre, $refus] = Travaux::inviter(Auth::id(), $id, is_array($_POST['amis'] ?? null) ? $_POST['amis'] : []);
        $this->finir($refus, tn('tr.fl.invitations', $nombre), 'travaux/' . $id . '/membres');
    }

    public function ajouterSansCompte(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::ajouterSansCompte(Auth::id(), $id, post('nom')),
            t('tr.fl.sans_compte_ajoute', ['nom' => trim(post('nom'))]),
            'travaux/' . $id . '/membres');
    }

    public function retirerMembre(int $id): void
    {
        $this->exigerPost();
        $projet = (int) Database::valeur('SELECT projet_id FROM projet_membres WHERE id = ?', [$id]);
        $this->finir(Travaux::retirer(Auth::id(), $id), t('tr.fl.retire'), 'travaux/' . $projet . '/membres');
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
            t($admin ? 'tr.fl.nomme_admin' : 'tr.fl.plus_admin'), 'travaux/' . $projet . '/membres');
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
        $this->finir(null, t('tr.fl.tache_ajoutee'), 'travaux/' . $id);
    }

    /** Modifier une tâche, dans une fenêtre par-dessus le tableau. */
    public function formulaireTache(int $id): void
    {
        Auth::exiger();
        $tache = Travaux::tache(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/tache', [
            'tache' => $tache,
            'projet' => Travaux::projet((int) $tache['projet_id'], Auth::id()),
            'membres' => Travaux::membresActifs((int) $tache['projet_id']),
        ], t('titre.tr_modifier_tache'));
    }

    public function modifierTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::tache(Auth::id(), $id) ?? $this->introuvable();
        $refus = Travaux::modifierTache(Auth::id(), $id, post('titre'), $_POST['membre_id'] ?? null,
            post('echeance'), post('note'));
        // Refusé : on reste sur le formulaire, pour corriger.
        $this->finir($refus, t('tr.fl.tache_modifiee'),
            $refus === null ? 'travaux/' . (int) $tache['projet_id'] : 'travaux/taches/' . $id . '/modifier');
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
        Session::flash('succes', t('tr.fl.pour_vous', ['titre' => (string) $tache['titre']]));
        repartir_vers('travaux/' . (int) $tache['projet_id']);
    }

    public function supprimerTache(int $id): void
    {
        $this->exigerPost();
        $tache = Travaux::supprimerTache(Auth::id(), $id) ?? $this->introuvable();
        Session::flash('succes', t('tr.fl.tache_supprimee', ['titre' => (string) $tache['titre']]));
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
        Session::flash('succes', t('tr.fl.echeance_posee', ['titre' => (string) $donnees['titre']]));
        redirect('travaux/' . $id . '/echeances');
    }

    /** Modifier une échéance, dans une fenêtre par-dessus la liste. */
    public function formulaireEcheance(int $id): void
    {
        Auth::exiger();
        $echeance = Travaux::echeance(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/echeance', [
            'echeance' => $echeance,
            'projet' => Travaux::projet((int) $echeance['projet_id'], Auth::id()),
            'types' => Travaux::types((int) $echeance['projet_id']),
        ], t('titre.tr_modifier_echeance'));
    }

    public function modifierEcheance(int $id): void
    {
        $this->exigerPost();
        $echeance = Travaux::echeance(Auth::id(), $id) ?? $this->introuvable();
        $retour = 'travaux/' . (int) $echeance['projet_id'] . '/echeances';
        $donnees = Travaux::lireEcheance($_POST, (int) $echeance['projet_id']);
        if (is_string($donnees)) {
            $this->finir($donnees, '', 'travaux/echeances/' . $id . '/modifier');
        }
        Travaux::modifierEcheance($id, $donnees);
        Session::flash('succes', t('tr.fl.echeance_modifiee'));
        redirect($retour);
    }

    public function supprimerEcheance(int $id): void
    {
        $this->exigerPost();
        $echeance = Travaux::echeance(Auth::id(), $id) ?? $this->introuvable();
        Database::run('DELETE FROM projet_echeances WHERE id = ?', [$id]);
        Session::flash('succes', t('tr.fl.echeance_retiree', ['titre' => (string) $echeance['titre']]));
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
        ], t('titre.tr_nouveau_type'));
    }

    /** Modifier un type, en fenêtre. */
    public function formulaireType(int $id): void
    {
        Auth::exiger();
        $type = Travaux::type(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/type', [
            'projet' => Travaux::projet((int) $type['projet_id'], Auth::id()), 'type' => $type,
            'palette' => TypesEvenementController::PALETTE, 'icones' => Travaux::icones(),
        ], t('titre.tr_modifier_type'));
    }

    public function creerType(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $refus = Travaux::enregistrerType($id, null, $_POST);
        // Refusé : on reste sur le formulaire, pour corriger.
        $this->finir($refus, t('tr.fl.type_cree', ['nom' => trim(post('nom'))]),
            'travaux/' . $id . ($refus === null ? '/types' : '/types/nouveau'));
    }

    public function modifierType(int $id): void
    {
        $this->exigerPost();
        $type = Travaux::type(Auth::id(), $id) ?? $this->introuvable();
        $refus = Travaux::enregistrerType((int) $type['projet_id'], $id, $_POST);
        $this->finir($refus, t('tr.fl.type_maj'),
            $refus === null ? 'travaux/' . (int) $type['projet_id'] . '/types' : 'travaux/types/' . $id . '/modifier');
    }

    public function supprimerType(int $id): void
    {
        $this->exigerPost();
        $type = Travaux::supprimerType(Auth::id(), $id) ?? $this->introuvable();
        $n = (int) $type['nb_echeances'];
        $this->finir(null, t('tr.fl.type_supprime', ['nom' => (string) $type['nom']])
            . ($n === 0 ? '' : tn('tr.fl.echeances_gardees', $n)),
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
            Session::flash('succes', tn('tr.fl.fichiers_deposes', $recus));
        }
        if ($erreurs !== []) {
            Session::flash('erreur', implode(' ', $erreurs));
        } elseif ($recus === 0) {
            Session::flash('erreur', t('tr.fl.choisir_fichier'));
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
            Session::flash('erreur', t('tr.fl.retirer_fichier_interdit'));
        } else {
            Session::flash('succes', t('tr.fl.fichier_supprime', ['nom' => (string) $fichier['nom_origine']]));
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
            Session::flash('erreur', t('tr.fl.document_conflit'));
        } else {
            Session::flash('succes', t('tr.fl.document_enregistre'));
        }
        redirect('travaux/' . $id . '/document');
    }

    public function version(int $id): void
    {
        Auth::exiger();
        $version = Travaux::version(Auth::id(), $id) ?? $this->introuvable();
        $this->formulaire('travaux/version', ['version' => $version,
            'projet' => Travaux::projet((int) $version['projet_id'], Auth::id())], t('titre.tr_version'));
    }

    public function restaurer(int $id): void
    {
        $this->exigerPost();
        $projet = Travaux::restaurer(Auth::id(), $id) ?? $this->introuvable();
        Session::flash('succes', t('tr.fl.version_restauree'));
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
        Session::flash('succes', t('tr.fl.discussion_prete'));
        redirect('groupes/' . $conversation);
    }

    public function delierDiscussion(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::delierDiscussion(Auth::id(), $id) ? null : t('tr.fl.admins_delier'),
            t('tr.fl.discussion_deliee'), 'travaux/' . $id . '/membres');
    }

    public function ouvrirLien(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::ouvrirLien(Auth::id(), $id) === null ? t('tr.fl.admins_ouvrir_lien') : null,
            t('tr.fl.lien_cree'), 'travaux/' . $id . '/membres');
    }

    public function fermerLien(int $id): void
    {
        $this->exigerPost();
        $this->finir(Travaux::fermerLien(Auth::id(), $id) ? null : t('tr.fl.admins_fermer_lien'),
            t('tr.fl.lien_desactive'), 'travaux/' . $id . '/membres');
    }

    /** Le projet vu par le lien public : tout, en lecture seule. */
    public function public(string $jeton): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $projet = Travaux::parJeton($jeton);
        if ($projet === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], t('titre.lien_introuvable'));
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
            Vue::afficherPublic('partages/lien_mort', [], t('titre.lien_introuvable'));
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
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
