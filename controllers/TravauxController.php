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
            'mesTaches'   => Travaux::mesTaches($moi, 200),
            // Dépliées ou repliées d'office : le réglage de « Mon compte ».
            'tachesRepliees' => Travaux::tachesRepliees($moi),
            // Une invitation vient d'être acceptée : la liste rouvre aussitôt le groupe dans une fenêtre (lien « data-ouvrir-auto »).
            'aOuvrir'     => (int) ($_GET['ouvrir'] ?? 0) > 0 ? Travaux::projet((int) $_GET['ouvrir'], $moi) : null,
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
            'points'  => Travaux::pointsDesTaches($id),
        ], 'taches');
    }

    /** L'onglet « Listes » : les listes du groupe, à points à cocher. */
    public function listes(int $id): void
    {
        $projet = $this->projet($id);
        if (!Travaux::listesDisponibles()) {
            $this->introuvable();
        }
        $this->afficher('travaux/listes', $projet, ['listes' => Travaux::listes($id)], 'listes');
    }

    public function creerListe(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $this->finir(Travaux::creerListe(Auth::id(), $id, post('titre')), t('tr.li2.fl.creee'), 'travaux/' . $id . '/listes');
    }

    public function renommerListe(int $id): void
    {
        $this->exigerPost();
        $liste = Travaux::liste(Auth::id(), $id) ?? $this->introuvable();
        $this->finir(Travaux::renommerListe(Auth::id(), $id, post('titre')), t('tr.li2.fl.renommee'), 'travaux/' . (int) $liste['projet_id'] . '/listes');
    }

    public function supprimerListe(int $id): void
    {
        $this->exigerPost();
        $projet = Travaux::supprimerListe(Auth::id(), $id) ?? $this->introuvable();
        $this->finir(null, t('tr.li2.fl.supprimee'), 'travaux/' . $projet . '/listes');
    }

    /** « En faire une tâche » : la liste devient une tâche du tableau, avec ses points. */
    public function listeEnTache(int $id): void
    {
        $this->exigerPost();
        [$tache, $projet] = Travaux::listeEnTache(Auth::id(), $id);
        if ($tache === null) {
            $this->introuvable();
        }
        $this->finir(null, t('tr.li2.fl.en_tache'), 'travaux/' . $projet);
    }

    /** Un point de plus dans une tâche, ou dans une liste : on revient à la page d'où l'on vient. */
    public function ajouterPointTache(int $id): void
    {
        $this->ajouterUnPoint('tache', $id);
    }

    public function ajouterPointListe(int $id): void
    {
        $this->ajouterUnPoint('liste', $id);
    }

    private function ajouterUnPoint(string $genre, int $id): never
    {
        $this->exigerPost();
        $refus = Travaux::ajouterPoint(Auth::id(), $genre, $id, post('texte'));
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        }
        $this->retourDesPoints($genre, $id);
    }

    /** Coche (« fait » = 1) ou décoche (0) un point : sans bruit, la case suffit à le dire. */
    public function cocherPoint(int $id): void
    {
        $this->exigerPost();
        $point = Travaux::point(Auth::id(), $id) ?? $this->introuvable();
        Travaux::cocherPoint(Auth::id(), $id, (string) ($_POST['fait'] ?? '') === '1');
        if (veut_du_json()) {
            repondre_json(['fait' => (string) ($_POST['fait'] ?? '') === '1']);
        }
        $this->retourDesPoints($point['liste_id'] !== null ? 'liste' : 'tache', (int) ($point['liste_id'] ?? $point['tache_id']));
    }

    public function supprimerPoint(int $id): void
    {
        $this->exigerPost();
        $point = Travaux::point(Auth::id(), $id) ?? $this->introuvable();
        Travaux::supprimerPoint(Auth::id(), $id);
        $this->retourDesPoints($point['liste_id'] !== null ? 'liste' : 'tache', (int) ($point['liste_id'] ?? $point['tache_id']));
    }

    /** Après un point : là d'où l'on vient (le tableau, la fenêtre de la tâche), sinon la page de sa liste ou de sa tâche. */
    private function retourDesPoints(string $genre, int $parentId): never
    {
        if ($genre === 'liste') {
            $liste = Database::one('SELECT projet_id FROM projet_listes WHERE id = ?', [$parentId]);
            repartir_vers('travaux/' . (int) ($liste['projet_id'] ?? 0) . '/listes');
        }
        repartir_vers('travaux/taches/' . $parentId . '/modifier');
    }

    public function echeances(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/echeances', $projet, [
            'echeances' => Travaux::echeances($id),
            'types' => Travaux::types($id),
            // Le calendrier commun du projet, et ses prochains évènements : ils se lisent ici avec les échéances.
            'calendrier' => CalendriersAmis::duProjet($id, Auth::id()),
            'evenementsCalendrier' => CalendriersAmis::aVenirDuProjet($id),
        ], 'echeances');
    }

    public function fichiers(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/fichiers', $projet, ['fichiers' => Travaux::fichiers($id)], 'fichiers');
    }

    /** Les cours et dossiers liés : les lire, les ajouter à son espace, et en lier un des siens. */
    public function cours(int $id): void
    {
        $projet = $this->projet($id);
        $this->afficher('travaux/cours', $projet, [
            'liens' => Travaux::liens($id, Auth::id()),
            'aLier' => Travaux::aLier($id, Auth::id()),
        ], 'cours');
    }

    /** Lie un de ses cours ou dossiers au groupe (« lien » : « cours:12 » ou « dossier:3 »). */
    public function lier(int $id): void
    {
        $this->exigerPost();
        $choix = explode(':', (string) ($_POST['lien'] ?? ''), 2);
        $type = $choix[0];
        $refus = Travaux::lier(Auth::id(), $id, $type, (int) ($choix[1] ?? 0));
        $this->finir($refus, t($type === 'dossier' ? 'tr.fl.dossier_lie' : 'tr.fl.cours_lie'), 'travaux/' . $id . '/cours');
    }

    public function delier(int $id): void
    {
        $this->exigerPost();
        $projet = Travaux::delier(Auth::id(), $id);
        if ($projet === null) {
            Session::flash('erreur', t('tr.err.delier'));
            redirect('travaux');
        }
        Session::flash('succes', t('tr.fl.delie'));
        redirect('travaux/' . $projet . '/cours');
    }

    public function document(int $id): void
    {
        $projet = $this->projet($id);
        $brouillon = Session::reprendre('travaux-document-' . $id);
        $this->afficher('travaux/document', $projet, [
            'versions'  => Travaux::versions($id),
            'brouillon' => $brouillon,
            'auteur'    => $projet['document_par'] === null ? null : Amis::compte((int) $projet['document_par']),
            // On lit d'abord le document, mis en forme ; « Modifier » ouvre l'éditeur. L'éditeur s'ouvre d'office quand il n'y a encore rien à lire,
            // ou qu'on vient d'avoir un conflit (ce qu'on avait tapé est gardé à côté).
            'enEdition' => isset($_GET['modifier']) || trim((string) $projet['document']) === '' || (is_string($brouillon) && trim($brouillon) !== ''),
        ], 'document');
    }

    public function membres(int $id): void
    {
        $projet = $this->projet($id);
        $membres = Travaux::membres($id);
        $dedans = array_filter(array_map(static fn (array $m): ?int => $m['user_id'] === null ? null : (int) $m['user_id'], $membres));
        $this->afficher('travaux/membres', $projet, [
            'membres'       => $membres,
            // Ceux qui sont entrés par le lien public : leur prénom et quand.
            'visiteurs'     => Travaux::visiteurs($id),
            // Ceux des membres sans compte qui sont là par le lien (« Par le lien »).
            'parLeLien'     => Travaux::membresParLien($id),
            // Le calendrier commun du projet, s'il en a un.
            'calendrier'    => CalendriersAmis::duProjet($id, Auth::id()),
            'amisAInviter'  => array_values(array_filter(Amis::liste(Auth::id()),
                static fn (array $a): bool => !in_array((int) $a['id'], $dedans, true))),
            'discussions'   => Database::all(
                'SELECT c.id, c.nom FROM conversations c
                   JOIN conversation_membres mb ON mb.conversation_id = c.id AND mb.user_id = ?
                  WHERE c.serveur_id IS NULL ORDER BY c.nom', [Auth::id()]),
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
        redirect('travaux', ['ouvrir' => $id]);
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
        // Les points à cocher écrits dès la création (un par ligne).
        // (le formulaire envoie « points[] » un par un ; sans script, une zone de texte d'un point par ligne)
        $points = $_POST['points'] ?? '';
        Travaux::ajouterPointsEnLot($resultat, is_array($points) ? implode("\n", array_filter($points, 'is_string')) : (string) $points);
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
            'points' => Travaux::points('tache', $id),
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
            'liensEvenement' => [
                'projet' => (int) $echeance['projet_id'],
                'type' => 'echeance',
                'id' => $id,
                'liens' => LiensEvenements::liensDe((int) $echeance['projet_id'], Auth::id(), 'echeance', $id),
                'aLier' => LiensEvenements::aLier((int) $echeance['projet_id'], Auth::id(), 'echeance', $id),
            ],
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
        LiensEvenements::oublier('echeance', $id);
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
            redirect('travaux/' . $id . '/document', ['modifier' => 1]);
        }
        // Enregistré : on revient à la lecture du document, avec « Modifier » pour y retoucher.
        Session::flash('succes', t('tr.fl.document_enregistre'));
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

    /** Lie un cours, un dossier ou un fichier du projet à un de ses évènements (calendrier commun ou échéance). */
    public function lierEvenement(int $id): void
    {
        $this->exigerPost();
        $this->projet($id);
        $type = (string) ($_POST['evenement_type'] ?? '');
        $evenement = (int) ($_POST['evenement_id'] ?? 0);
        [$typeCible, $cible] = array_pad(explode(':', (string) ($_POST['lien'] ?? ''), 2), 2, '');
        $refus = LiensEvenements::lier(Auth::id(), $id, $type, $evenement, $typeCible, (int) $cible);
        // On revient sur la carte encore ouverte à la modification : on en lie souvent plusieurs d'affilée.
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? t('cam.fl.lien_ajoute'));
        redirect(self::pageEvenement($type, $evenement), ['liens' => 1]);
    }

    public function delierEvenement(int $id): void
    {
        $this->exigerPost();
        $evenement = LiensEvenements::delier(Auth::id(), $id);
        if ($evenement === null) {
            $this->finir(t('cam.err.lien_pas_a_vous'), '', 'calendrier');
        }
        Session::flash('succes', t('cam.fl.lien_retire'));
        redirect(self::pageEvenement($evenement['evenement_type'], $evenement['evenement_id']), ['liens' => 1]);
    }

    /** La page d'un évènement du projet, où l'on revient après avoir lié ou délié un document. */
    private static function pageEvenement(string $type, int $id): string
    {
        return $type === 'echeance' ? 'travaux/echeances/' . $id . '/modifier' : 'calendriers-amis/evenements/' . $id;
    }

    /** Crée le calendrier commun du projet : un calendrier partagé dont les membres sont ceux du projet. */
    public function creerCalendrier(int $id): void
    {
        $this->exigerPost();
        [$calendrier, $refus] = CalendriersAmis::creerPourProjet(Auth::id(), $id);
        $this->finir($calendrier === null ? $refus : null, t('cam.fl.projet_cree'), 'travaux/' . $id . '/membres');
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
        // Le lien demande un prénom : tant qu'on ne l'a pas donné (pas de cookie reconnu), on ne voit pas le projet.
        // Un membre du projet qui ouvre son propre lien n'a pas à se présenter : il est connecté, et ce n'est pas un visiteur.
        $visiteur = !Travaux::lienDemandeUnPrenom() || (Auth::connecte() && Travaux::projet($id, Auth::id()) !== null)
            ? ['id' => 0, 'prenom' => ''] : Travaux::visiteurDuLien($projet);
        if ($visiteur === null) {
            Vue::afficherPublic('travaux/public_entree', ['projet' => $projet, 'jeton' => $jeton, 'erreur' => null, 'prenom' => '', 'etat' => 'saisie'], (string) $projet['nom']);
            return;
        }
        // Le calendrier commun du projet, s'il en a un : le mois demandé (« ?mois=2026-11 »), le mois courant à défaut, de 2000 à 2100.
        $mois = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) ($_GET['mois'] ?? ''), $m) === 1 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100
            ? new DateTimeImmutable($m[1] . '-' . $m[2] . '-01') : new DateTimeImmutable('first day of this month midnight');
        $calendrier = CalendriersAmis::publicDuProjet($id, $mois, $mois->modify('last day of this month')->setTime(23, 59, 59));
        Vue::afficherPublic('travaux/public', [
            'projet'    => $projet,
            'jeton'     => $jeton,
            'calendrier' => $calendrier,
            'moisCalendrier' => $mois,
            'visiteur'  => $visiteur,
            'membres'   => Travaux::membresActifs($id),
            'taches'    => Travaux::taches($id),
            'echeances' => Travaux::echeances($id),
            'fichiers'  => Travaux::fichiers($id),
            'listes'    => Travaux::listes($id),
            'points'    => Travaux::pointsDesTaches($id),
        ], (string) $projet['nom']);
    }

    /** Quelqu'un dit son prénom pour entrer par le lien : on le garde, on lui pose son cookie, et il voit le projet. */
    public function entrer(string $jeton): void
    {
        $projet = Travaux::parJeton($jeton);
        if ($projet === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], t('titre.lien_introuvable'));
            return;
        }
        Session::verifierCsrf();
        $reponse = in_array(post('reponse'), ['moi', 'autre'], true) ? post('reponse') : '';
        $entree = Travaux::entrerParLeLien($projet, post('prenom'), $reponse);
        $secret = $entree['jeton'];
        if ($secret === null) {
            // Un refus (prénom illisible, trop de monde) est une erreur ; une question (« est-ce vous ? ») ou une invitation à se distinguer n'en est pas une.
            http_response_code($entree['etat'] === 'refus' ? 422 : 200);
            Vue::afficherPublic('travaux/public_entree', [
                'projet' => $projet, 'jeton' => $jeton, 'erreur' => $entree['refus'], 'prenom' => $entree['prenom'], 'etat' => $entree['etat'],
            ], (string) $projet['nom']);
            return;
        }
        // Le cookie ne se lit que par le serveur (httponly), jamais envoyé depuis un autre site (SameSite), et dure six mois.
        setcookie(Travaux::cookieVisiteur((int) $projet['id']), $secret, [
            'expires' => time() + 180 * 86400, 'path' => url(''), 'httponly' => true, 'samesite' => 'Lax',
            'secure' => (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off',
        ]);
        redirect('g/' . $jeton);
    }

    /**
     * « Se connecter » depuis la page d'entrée du lien : on va à la connexion, et une fois connecté on revient au lien (un membre du projet y entre
     * alors sans prénom). L'adresse de retour est fabriquée ici, jamais lue dans la demande.
     */
    public function seConnecter(string $jeton): void
    {
        if (Travaux::parJeton($jeton) !== null) {
            $_SESSION['_apres_connexion'] = url('g/' . $jeton);
        }
        redirect('connexion');
    }

    /** « Ce n'est pas vous ? » : le navigateur oublie le prénom, et le lien le redemande. */
    public function sortir(string $jeton): void
    {
        $projet = Travaux::parJeton($jeton);
        Session::verifierCsrf();
        if ($projet !== null) {
            setcookie(Travaux::cookieVisiteur((int) $projet['id']), '', ['expires' => time() - 3600, 'path' => url(''), 'httponly' => true, 'samesite' => 'Lax']);
        }
        redirect('g/' . $jeton);
    }

    /** Un administrateur retire quelqu'un de la liste de ceux qui sont entrés par le lien. */
    public function retirerVisiteur(int $id, int $visiteur): void
    {
        $this->exigerPost();
        $this->finir(Travaux::retirerVisiteur(Auth::id(), $id, $visiteur) ? null : t('tr.fl.admins_retirer_visiteur'),
            t('tr.fl.visiteur_retire'), 'travaux/' . $id . '/membres');
    }

    public function fichierPublic(string $jeton, int $id): void
    {
        $projet = Travaux::parJeton($jeton);
        // Les fichiers suivent le lien : sans prénom donné, on ne les sert pas non plus.
        if ($projet !== null && Travaux::lienDemandeUnPrenom() && Travaux::visiteurDuLien($projet) === null
            && !(Auth::connecte() && Travaux::projet((int) $projet['id'], Auth::id()) !== null)) {
            $projet = null;
        }
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
