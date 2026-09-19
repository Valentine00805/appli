<?php
declare(strict_types=1);

/**
 * Partager un cours ou un fichier : la fenêtre « Partager » du propriétaire,
 * la lecture pour un ami, et le lien public pour tout le monde.
 *
 * Dans les adresses, le type s'écrit « cours » ou « fichiers ».
 */
final class PartagesController
{
    /** « cours » ou « fichiers » dans l'adresse ; « cours » ou « fichier » en base. */
    private static function type(string $mot): string
    {
        return match ($mot) {
            'cours' => 'cours',
            'fichiers' => 'fichier',
            'fiches' => 'fiche',
            'dossiers' => 'dossier',
            'evenements' => 'evenement',
            default => self::introuvable(),
        };
    }

    /** L'onglet « Partagés » : ce qu'on m'a partagé, et ce que je partage. */
    public function index(): void
    {
        $this->onglet('recus');
    }

    /** Le second volet du même onglet : ce que je partage. */
    public function envoyes(): void
    {
        $this->onglet('envoyes');
    }

    private function onglet(string $vue): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $recus = Partages::recus($moi);
        if ($vue === 'recus') {
            Partages::marquerVus($moi);
        }
        Vue::afficher('partages/index', [
            'vue' => $vue,
            'recus' => $recus,
            'envoyes' => Partages::envoyes($moi),
        ], $vue === 'recus' ? 'Partagés avec moi' : 'Ce que je partage');
    }

    /** La fenêtre « Partager plusieurs documents » : on coche des cours, des fichiers et des dossiers, puis des amis. */
    public function plusieurs(): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $donnees = [
            'mesCours' => Database::all(
                "SELECT c.id, c.titre, m.nom AS matiere_nom, d.nom AS dossier_nom, c.updated_at
                   FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id LEFT JOIN dossiers d ON d.id = c.dossier_id
                  WHERE c.user_id = ? ORDER BY c.updated_at DESC",
                [$moi]
            ),
            'mesFichiers' => Database::all(
                'SELECT f.id, f.nom_origine, f.mime, f.taille, f.pour_fiche, c.titre AS cours_titre
                   FROM fichiers f JOIN cours c ON c.id = f.cours_id
                  WHERE f.user_id = ? ORDER BY f.created_at DESC',
                [$moi]
            ),
            'mesDossiers' => DossiersController::pourUtilisateur($moi, true),
            // Une fiche existe dès qu'il y a de quoi la lire : du texte, des
            // fichiers à elle, ou des liens.
            'mesFiches' => Database::all(
                "SELECT c.id, c.titre, c.fiche_revision, m.nom AS matiere_nom,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 1) AS nb_fichiers
                   FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                  WHERE c.user_id = ?
                    AND (TRIM(COALESCE(c.fiche_revision, '')) <> ''
                         OR EXISTS (SELECT 1 FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 1)
                         OR EXISTS (SELECT 1 FROM fiche_elements e WHERE e.cours_id = c.id))
                  ORDER BY c.updated_at DESC",
                [$moi]
            ),
            'choisis' => array_flip(array_map('intval', is_array($_GET['cours'] ?? null) ? $_GET['cours'] : [])),
            'choisisFichiers' => array_flip(array_map('intval', is_array($_GET['fichiers'] ?? null) ? $_GET['fichiers'] : [])),
            'choisisDossiers' => array_flip(array_map('intval', is_array($_GET['dossiers'] ?? null) ? $_GET['dossiers'] : [])),
            'choisisFiches' => array_flip(array_map('intval', is_array($_GET['fiches'] ?? null) ? $_GET['fiches'] : [])),
            'mesLots' => Partages::mesLots($moi),
            'amis' => Amis::liste($moi),
            'groupes' => Conversations::liste($moi),
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/plusieurs', $donnees + ['dansUneFenetre' => true]);
            return;
        }
        Vue::afficher('partages/plusieurs', $donnees, 'Partager plusieurs documents');
    }

    /** L'envoi du lot : un accès et une carte par document. */
    public function envoyerPlusieurs(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        // Le formulaire nomme ses listes comme les adresses : cours, fiches,
        // dossiers, fichiers.
        $choisis = [];
        foreach (Partages::TYPES as $type) {
            $champ = Partages::mot($type);
            $choisis[$type] = is_array($_POST[$champ] ?? null) ? $_POST[$champ] : [];
        }
        [$partis, $nombre, $refus, $notifications] = Partages::partagerPlusieurs(
            Auth::id(), $choisis,
            is_array($_POST['amis'] ?? null) ? $_POST['amis'] : [],
            is_array($_POST['groupes'] ?? null) ? $_POST['groupes'] : [],
            (string) ($_POST['texte'] ?? ''),
            Partages::droitValide($_POST['droit'] ?? null)
        );
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            // « 1 cours partagé », « 2 fiches de révision partagées ».
            $accord = 'partagé' . (str_contains($partis, 'fiche') ? 'e' : '') . (str_starts_with($partis, '1 ') ? '' : 's');
            Session::flash('succes', $partis . ' ' . $accord
                . ' avec ' . $nombre . ' personne' . ($nombre > 1 ? 's' : '') . ' : les cartes sont parties dans vos discussions.');
        }
        $this->retourPuisEnvoyer('partager/plusieurs', $notifications);
    }

    /** Un lien public pour tout ce qui est coché : le lot, et son adresse. */
    public function creerLienLot(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $choisis = [];
        foreach (Partages::TYPES as $type) {
            $champ = Partages::mot($type);
            $choisis[$type] = is_array($_POST[$champ] ?? null) ? $_POST[$champ] : [];
        }
        [$lot, $refus] = Partages::creerLot(Auth::id(), $choisis, (string) ($_POST['nom'] ?? ''));
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', 'Lien créé pour ' . count($lot['documents']) . ' document'
                . (count($lot['documents']) > 1 ? 's' : '') . ' : copiez-le et donnez-le à qui vous voulez.');
        }
        redirect('partager/plusieurs');
    }

    /** Défait un lot : son adresse ne mène plus à rien, ses documents restent. */
    public function desactiverLot(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $defait = Partages::supprimerLot(Auth::id(), $id);
        Session::flash(
            $defait ? 'succes' : 'erreur',
            $defait ? 'Lien désactivé : l’adresse ne mène plus à rien, même si elle a circulé. Vos documents, eux, sont intacts.'
                : 'Ce lien n’existe plus.'
        );
        redirect('partager/plusieurs');
    }

    /** La fenêtre « Partager » : les amis d'abord, puis le lien public. */
    public function fenetre(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::mienne($type, $id, $moi);
        if ($cible === null) {
            self::introuvable();
        }
        $lien = Partages::lien($type, $id);
        $donnees = [
            'type' => $type,
            'mot' => $mot,
            'cible' => $cible,
            'amis' => Amis::liste($moi),
            'groupes' => Conversations::liste($moi),
            'destinataires' => Partages::destinataires($moi, $type, $id),
            'commentaires' => Partages::commentaires($type, $id),
            'lien' => $lien === null ? null : Partages::adresseLien((string) $lien['jeton']),
            'vues' => $lien === null ? 0 : (int) $lien['vues'],
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/partager', $donnees);
            return;
        }
        Vue::afficher('partages/partager', $donnees, 'Partager');
    }

    public function envoyer(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        [$nombre, $refus, $notifications] = Partages::partagerAvecAmis(
            Auth::id(), $type, $id,
            is_array($_POST['amis'] ?? null) ? $_POST['amis'] : [],
            is_array($_POST['groupes'] ?? null) ? $_POST['groupes'] : [],
            (string) ($_POST['texte'] ?? ''),
            Partages::droitValide($_POST['droit'] ?? null)
        );
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', 'Partagé avec ' . $nombre . ' personne' . ($nombre > 1 ? 's' : '') . ' : une carte est partie dans vos discussions.');
        }
        $this->retourPuisEnvoyer('partager/' . $mot . '/' . $id, $notifications);
    }

    public function retirerAcces(string $mot, int $id, int $destinataire): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($destinataire)['pseudo'] ?? '');
        if (Partages::retirerAcces(Auth::id(), self::type($mot), $id, $destinataire)) {
            Session::flash('succes', $pseudo . ' n’y a plus accès.');
        } else {
            Session::flash('erreur', 'Ce partage n’existe plus.');
        }
        self::retourProfil();
        redirect('partager/' . $mot . '/' . $id);
    }

    /**
     * Retiré depuis le profil d'un ami : c'est là qu'on revient, dans la même
     * fenêtre, plutôt que dans celle du partage ou dans l'onglet.
     */
    private static function retourProfil(): void
    {
        $ami = entier_ou_null($_POST['profil'] ?? null);
        if ($ami !== null) {
            redirect('amis/' . $ami . '/profil');
        }
    }

    /** Change ce qu'un ami peut faire de ce document. */
    public function changerDroit(string $mot, int $id, int $destinataire): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $droit = Partages::droitValide($_POST['droit'] ?? null);
        $pseudo = (string) (Amis::compte($destinataire)['pseudo'] ?? '');
        if (Partages::changerDroit(Auth::id(), self::type($mot), $id, $destinataire, $droit)) {
            Session::flash('succes', $pseudo . ' : ' . mb_strtolower(Partages::libelleDroit($droit)) . '.');
        } else {
            Session::flash('erreur', 'Ce partage n’existe plus.');
        }
        self::retourProfil();
        redirect('partager/' . $mot . '/' . $id);
    }

    public function creerLien(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $lien = Partages::creerLien(Auth::id(), self::type($mot), $id);
        if ($lien === null) {
            self::introuvable();
        }
        Session::flash('succes', 'Lien créé : toute personne qui l’a peut voir et télécharger ce document, sans compte.');
        redirect('partager/' . $mot . '/' . $id);
    }

    public function desactiverLien(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Session::flash(
            Partages::desactiverLien(Auth::id(), self::type($mot), $id) ? 'succes' : 'info',
            'Lien désactivé : l’adresse ne mène plus à rien, même si elle a circulé.'
        );
        redirect('partager/' . $mot . '/' . $id);
    }

    /** Lire un document partagé par un ami. */
    public function lire(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::cible($type, $id);
        if ($cible !== null && (int) $cible['user_id'] === $moi) {
            // Le sien : sa vraie page.
            if ($type === 'dossier') {
                redirect('cours', ['dossier' => $id]);
            }
            redirect(match ($type) {
                'cours' => 'cours/' . $id,
                'fiche' => 'revision/' . $id,
                'evenement' => 'evenements/' . $id,
                default => 'fichiers/' . $id . (ApercuDocument::possible((string) $cible['nom_origine']) ? '/apercu' : ''),
            });
        }
        if ($cible === null || !Partages::peutVoir($type, $id, $moi)) {
            Session::flash('erreur', 'Ce document n’est pas, ou plus, partagé avec vous.');
            redirect('partages');
        }
        // L'ouvrir, c'est l'avoir vu : l'onglet ne le compte plus.
        Partages::marquerVus($moi, $type, $id);
        $donnees = [
            'type' => $type,
            'cible' => $cible,
            'public' => false,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours($id), 'fiche' => Partages::fichiersDeLaFiche($id), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche($id) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier($id, (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('partages/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('partages/fichiers/' . $f . '/contenu', $telecharger ? ['telecharger' => 1] : []),
            'mesCours' => $type === 'fichier'
                ? Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$moi]) : [],
            'recu' => Database::valeur('SELECT 1 FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?', [$moi, $type, $id]) !== null,
            'droit' => Partages::droit($type, $id, $moi) ?? 'lecture',
            'commentaires' => Partages::commentaires($type, $id, $moi),
            'adresseIcs' => url('partages/evenements/' . $id . '/ics'),
            'afficheDOffice' => $type === 'evenement' && Partages::afficheDOffice($moi, (int) $cible['user_id']),
            // Ma copie de cet évènement, si je l'ai ajouté à mon calendrier.
            'maCopie' => $type === 'evenement'
                ? Database::valeur('SELECT id FROM evenements WHERE user_id = ? AND partage_de = ?', [$moi, $id])
                : null,
            'mot' => $mot,
        ];
        // Un cours ouvert depuis un dossier partagé : de quoi y revenir.
        if ($type === 'cours') {
            $dossierId = Partages::dossierPartage($id, $moi);
            if ($dossierId !== null) {
                $dossier = Partages::cible('dossier', $dossierId);
                $donnees['retour'] = ['url' => url('partages/dossiers/' . $dossierId), 'texte' => '← ' . (string) ($dossier['titre'] ?? 'Dossier partagé')];
            }
        }
        if (Vue::enFenetre()) {
            Vue::fragment('partages/lire', $donnees + ['dansUneFenetre' => true]);
            return;
        }
        Vue::afficher('partages/lire', $donnees, (string) $cible['titre']);
    }

    /** Un commentaire sous un document partagé. */
    public function commenter(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        $reponseA = entier_ou_null($_POST['reponse_a'] ?? null);
        $refus = Partages::commenter(Auth::id(), $type, $id, (string) ($_POST['texte'] ?? ''), $reponseA);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? ($reponseA === null ? 'Commentaire ajouté.' : 'Réponse ajoutée.'));
        $this->retourDocument($type, $id);
    }

    /**
     * Les commentaires d'un document, seuls : une petite fenêtre qui s'ouvre
     * sans le reste, pour les lire, y répondre et les aimer.
     */
    public function fil(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::cible($type, $id);
        if ($cible === null || !Partages::permet(Partages::droit($type, $id, $moi), 'commentaire')) {
            self::introuvable();
        }
        $donnees = [
            'type' => $type,
            'mot' => $mot,
            'cible' => $cible,
            'commentaires' => Partages::commentaires($type, $id, $moi),
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/commentaires', $donnees + ['dansUneFenetre' => true]);
            return;
        }
        Vue::afficher('partages/commentaires', $donnees, 'Commentaires');
    }

    /**
     * Ce que d'autres ont changé dans un document : pour son propriétaire, et
     * pour qui a lui aussi le droit de le modifier.
     */
    public function historique(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::cible($type, $id);
        if ($cible === null || !in_array($type, ['cours', 'fiche'], true)
            || !Partages::permet(Partages::droit($type, $id, $moi), 'modification')) {
            self::introuvable();
        }
        $donnees = [
            'type' => $type,
            'mot' => $mot,
            'cible' => $cible,
            'modifications' => Partages::historique($type, $id),
            'chezMoi' => (int) $cible['user_id'] === $moi,
            // Qui ouvre cette page peut modifier le document : il peut aussi annuler.
            'peutAnnuler' => true,
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/modifications', $donnees + ['dansUneFenetre' => true]);
            return;
        }
        Vue::afficher('partages/modifications', $donnees, 'Modifications');
    }

    /** Ouvre un fichier qu'un ami a retiré : son propriétaire seul le peut. */
    public function fichierMisDeCote(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $ligne = Partages::fichierMisDeCote(Auth::id(), $id);
        if ($ligne === null) {
            self::introuvable();
        }
        Fichiers::envoyer($ligne, isset($_GET['telecharger']));
    }

    /** Annule une modification d'un ami, depuis l'historique. */
    public function annulerModification(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $fait = Partages::annulerModification(Auth::id(), $id);
        if ($fait === null) {
            self::introuvable();
        }
        Session::flash('succes', $fait[2]);
        redirect('partages/' . Partages::mot($fait[0]) . '/' . $fait[1] . '/modifications');
    }

    /** Remet dans le document un fichier qu'un ami en avait retiré. */
    public function restaurerFichier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $ou = Partages::restaurerFichier(Auth::id(), $id);
        if ($ou === null) {
            self::introuvable();
        }
        Session::flash('succes', 'Fichier remis à sa place.');
        redirect('partages/' . Partages::mot($ou[0]) . '/' . $ou[1] . '/modifications');
    }

    /** Aime un commentaire, ou cesse de l'aimer. */
    public function aimerCommentaire(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $ou = Partages::aimerCommentaire(Auth::id(), $id);
        if ($ou === null) {
            self::introuvable();
        }
        $this->retourDocument($ou[0], $ou[1]);
    }

    /** Retire un commentaire : le sien, ou l'un de ceux qu'on a reçus. */
    public function retirerCommentaire(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $ou = Partages::retirerCommentaire(Auth::id(), $id);
        if ($ou === null) {
            self::introuvable();
        }
        Session::flash('succes', 'Commentaire retiré.');
        $this->retourDocument($ou[0], $ou[1]);
    }

    /** Écrire dans un document partagé : le texte du cours, ou de la fiche. */
    public function ecrire(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        $refus = Partages::ecrire(Auth::id(), $type, $id, (string) ($_POST['contenu'] ?? ''));
        Session::flash($refus === null ? 'succes' : 'erreur', $refus ?? 'Enregistré.');
        $this->retourDocument($type, $id);
    }

    /** Joindre des fichiers à un document partagé. */
    public function joindre(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null)) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            $this->retourDocument($type, $id);
        }
        [$ajoutes, $erreurs] = Partages::joindre(Auth::id(), $type, $id, $_FILES['fichiers']);
        foreach ($erreurs as $erreur) {
            Session::flash('erreur', $erreur);
        }
        if ($ajoutes > 0) {
            Session::flash('succes', $ajoutes . ' fichier' . ($ajoutes > 1 ? 's joints' : ' joint') . '.');
        } elseif ($erreurs === []) {
            Session::flash('erreur', 'Aucun fichier reçu.');
        }
        $this->retourDocument($type, $id);
    }

    /** Retirer un fichier d'un document partagé. */
    public function retirerFichier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $ou = Partages::retirerFichier(Auth::id(), $id);
        if ($ou === null) {
            self::introuvable();
        }
        Session::flash('succes', 'Fichier retiré.');
        $this->retourDocument($ou[0], $ou[1]);
    }

    /** Revient au document : le sien chez soi, la page de lecture sinon. */
    private function retourDocument(string $type, int $id): never
    {
        if (($_POST['depuis'] ?? '') === 'fil') {
            redirect('partages/' . Partages::mot($type) . '/' . $id . '/commentaires');
        }
        $cible = Partages::cible($type, $id);
        if ($cible !== null && (int) $cible['user_id'] === Auth::id()) {
            redirect(match ($type) {
                'cours' => 'cours/' . $id,
                'fiche' => 'revision/' . $id,
                'dossier' => 'cours',
                default => 'fichiers/' . $id,
            });
        }
        redirect('partages/' . Partages::mot($type) . '/' . $id);
    }

    /** Où arrive ce qu'on me partage : aussi dans la discussion, ou seulement dans « Partagés ». */
    public function reglerReception(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $dansLaDiscussion = ($_POST['dans_discussion'] ?? '') === '1';
        Partages::reglerReception(Auth::id(), $dansLaDiscussion);
        Session::flash('succes', $dansLaDiscussion
            ? 'Ce qu’on vous partage arrivera aussi en carte dans vos discussions.'
            : 'Ce qu’on vous partage n’arrivera plus que dans l’onglet « Partagés », avec une notification.');
        repartir_vers('compte');
    }

    /** Les évènements de cet ami paraissent, ou non, d'office dans mon calendrier. */
    public function reglerCalendrier(int $ami): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $afficher = ($_POST['afficher'] ?? '') === '1';
        $compte = Amis::compte($ami);
        if (!Partages::reglerAffichage(Auth::id(), $ami, $afficher)) {
            self::introuvable();
        }
        Session::flash('succes', $afficher
            ? 'Les évènements que ' . $compte['pseudo'] . ' vous partage s’affichent désormais dans votre calendrier.'
            : 'Les évènements que ' . $compte['pseudo'] . ' vous partage ne s’affichent plus d’office : ils restent dans « Partagés ».');
        repartir_vers('compte');
    }

    /** Un évènement partagé, au format de tous les agendas. */
    public function ics(int $id): void
    {
        Auth::exiger();
        $evenement = Partages::cible('evenement', $id);
        if ($evenement === null || !Partages::peutVoir('evenement', $id, Auth::id())) {
            self::introuvable();
        }
        $this->envoyerIcs($evenement);
    }

    /** Le même, par un lien public. */
    public function icsPublic(string $jeton, string $mot, int $id): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $trouve = Partages::parJeton($jeton);
        $evenement = $mot === 'evenements' ? Partages::cible('evenement', $id) : null;
        if ($trouve === null || $evenement === null || !Partages::visiblePar($trouve['lien'], 'evenement', $id)) {
            http_response_code(404);
            exit('Évènement introuvable.');
        }
        $this->envoyerIcs($evenement);
    }

    private function envoyerIcs(array $evenement): never
    {
        session_write_close();
        $nom = trim((string) preg_replace('/[^\p{L}\p{N} _-]+/u', '', (string) $evenement['titre'])) ?: 'evenement';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="evenement.ics"; filename*=UTF-8\'\'' . rawurlencode(mb_substr($nom, 0, 60) . '.ics'));
        header('X-Content-Type-Options: nosniff');
        echo Partages::ics($evenement);
        exit;
    }

    /** Le contenu d'un fichier partagé, pour un ami. */
    public function contenu(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $moi = Auth::id();
        $fichier = Partages::cible('fichier', $id);
        if ($fichier === null || !Partages::peutVoir('fichier', $id, $moi)) {
            self::introuvable();
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']));
    }

    public function copier(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        [$cours, $refus] = Partages::copier(Auth::id(), $type, $id, entier_ou_null($_POST['cours'] ?? null));
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('partages/' . $mot . '/' . $id);
        }
        Session::flash('succes', match ($type) {
            'cours' => 'Cours copié dans vos cours : cette copie est à vous, modifiable.',
            'fiche' => 'Fiche copiée : un nouveau cours à vous, dont c’est la fiche de révision.',
            'dossier' => 'Dossier copié dans vos dossiers : ces copies sont à vous, modifiables.',
            'evenement' => 'Évènement ajouté à votre calendrier : il est à vous, modifiable.',
            default => 'Fichier copié dans votre cours.',
        });
        if ($type === 'dossier') {
            redirect('cours', ['dossier' => $cours]);
        }
        if ($type === 'evenement') {
            redirect('calendrier', ['date' => substr((string) Database::valeur('SELECT debut FROM evenements WHERE id = ?', [$cours]), 0, 10)]);
        }
        redirect(($type === 'fiche' ? 'revision/' : 'cours/') . $cours);
    }

    public function oublier(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Partages::oublierRecu(Auth::id(), self::type($mot), $id);
        Session::flash('succes', 'Retiré de vos documents partagés.');
        self::retourProfil();
        redirect('partages');
    }

    /** Le lien public : la page du document, pour tout le monde. */
    public function public(string $jeton): void
    {
        $trouve = Partages::parJeton($jeton);
        header('X-Robots-Tag: noindex, nofollow');
        if ($trouve === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        Partages::compterVue((int) $trouve['lien']['id']);
        $type = (string) $trouve['lien']['cible_type'];
        $cible = $trouve['cible'];
        if ($type === 'lot') {
            Vue::afficherPublic('partages/lot', [
                'lot' => $cible,
                'documents' => Partages::documentsDuLot((int) $cible['id']),
                'adresse' => static fn (string $t, int $i): string => url('p/' . $jeton . '/' . Partages::mot($t) . '/' . $i),
            ], (string) $cible['titre']);
            return;
        }
        Vue::afficherPublic('partages/lire', [
            'type' => $type,
            'cible' => $cible,
            'public' => true,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours((int) $cible['id']), 'fiche' => Partages::fichiersDeLaFiche((int) $cible['id']), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche((int) $cible['id']) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier((int) $cible['id'], (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('p/' . $jeton . '/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('p/' . $jeton . '/fichiers/' . $f, $telecharger ? ['telecharger' => 1] : []),
            'adresseIcs' => url('p/' . $jeton . '/evenements/' . (int) $cible['id'] . '/ics'),
            'mesCours' => [],
            'recu' => false,
            'droit' => 'lecture',
            'mot' => Partages::mot($type),
        ], (string) $cible['titre']);
    }

    /**
     * Un document ouvert par un lien public : le cours d'un dossier partagé,
     * ou l'un de ceux qu'un lot rassemble.
     */
    public function documentPublic(string $jeton, string $mot, int $id): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $type = self::type($mot);
        $trouve = Partages::parJeton($jeton);
        $cible = Partages::cible($type, $id);
        if ($trouve === null || $cible === null || !Partages::visiblePar($trouve['lien'], $type, $id)) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        Vue::afficherPublic('partages/lire', [
            'type' => $type,
            'cible' => $cible,
            'public' => true,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours($id), 'fiche' => Partages::fichiersDeLaFiche($id), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche($id) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier($id, (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('p/' . $jeton . '/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('p/' . $jeton . '/fichiers/' . $f, $telecharger ? ['telecharger' => 1] : []),
            'adresseIcs' => url('p/' . $jeton . '/evenements/' . $id . '/ics'),
            'mesCours' => [],
            'recu' => false,
            'droit' => 'lecture',
            'mot' => $mot,
            'retour' => ['url' => url('p/' . $jeton), 'texte' => '← ' . (string) ($trouve['cible']['titre'] ?? 'Partage')],
        ], (string) $cible['titre']);
    }

    /** Un fichier par le lien public : le fichier partagé, ou un fichier joint du cours partagé. */
    public function fichierPublic(string $jeton, int $id): void
    {
        session_write_close();
        header('X-Robots-Tag: noindex, nofollow');
        $trouve = Partages::parJeton($jeton);
        $fichier = Partages::cible('fichier', $id);
        if ($trouve === null || $fichier === null || !Partages::visiblePar($trouve['lien'], 'fichier', $id)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']));
    }

    /** Revient à la fenêtre « Partager », puis envoie les notifications en file. */
    private function retourPuisEnvoyer(string $chemin, array $notifications): never
    {
        $adresse = url($chemin);
        session_write_close();
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Envoyé depuis la fenêtre : on y revient, en fragment.
        if (($_POST['fenetre'] ?? '') === '1') {
            $adresse .= '?fenetre=1';
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

    private static function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
