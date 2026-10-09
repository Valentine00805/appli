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
        ], t($vue === 'recus' ? 'pt.recus_titre' : 'pt.envoyes_titre'));
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
        Vue::afficher('partages/plusieurs', $donnees, t('titre.partager_plusieurs'));
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
            // La phrase accorde sur le nombre de personnes ; le lot, lui, se dit déjà tout seul.
            Session::flash('succes', tn('pt.flash_lot', $nombre, ['combien' => $partis]));
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
            Session::flash('succes', t('pt.flash_lien_lot', [
                'combien' => tn('pt.combien_document', count($lot['documents'])),
            ]));
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
            t($defait ? 'pt.flash_lot_desactive' : 'pt.flash_lot_parti')
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
            // Un cours ou un dossier peut aussi se mettre dans un travail de groupe, dont les membres le lisent.
            'projets' => in_array($type, ['cours', 'dossier'], true) ? Travaux::projetsPourPartage($moi, $type, $id) : null,
            'commentaires' => Partages::commentaires($type, $id),
            'lien' => $lien === null ? null : Partages::adresseLien((string) $lien['jeton']),
            'vues' => $lien === null ? 0 : (int) $lien['vues'],
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/partager', $donnees);
            return;
        }
        Vue::afficher('partages/partager', $donnees, t('titre.partager'));
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
        $succes = tn('pt.flash_un', $nombre);
        // Un évènement lié à un cours : le cours part aussi si on l'a demandé (même droit, mêmes destinataires, sans message).
        if ($refus === null && $type === 'evenement' && (string) ($_POST['avec_cours'] ?? '') === '1') {
            $evenement = Partages::mienne('evenement', $id, Auth::id());
            $coursId = (int) ($evenement['cours_id'] ?? 0);
            if ($coursId > 0) {
                [$nombreCours, $refusCours, $notificationsCours] = Partages::partagerAvecAmis(
                    Auth::id(), 'cours', $coursId,
                    is_array($_POST['amis'] ?? null) ? $_POST['amis'] : [],
                    is_array($_POST['groupes'] ?? null) ? $_POST['groupes'] : [],
                    '',
                    Partages::droitValide($_POST['droit'] ?? null)
                );
                if ($refusCours === null) {
                    $succes .= ' ' . t('pt.flash_avec_cours', ['titre' => (string) $evenement['cours_titre']]);
                    array_push($notifications, ...$notificationsCours);
                } else {
                    $refus = $refusCours;
                }
            }
        }
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', $succes);
        }
        $this->retourPuisEnvoyer('partager/' . $mot . '/' . $id, $notifications);
    }

    /** Met ce cours ou dossier dans des travaux de groupe : leurs membres le lisent (et peuvent l'ajouter à leur espace). */
    public function envoyerProjets(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        if (!in_array($type, ['cours', 'dossier'], true) || Partages::mienne($type, $id, Auth::id()) === null) {
            self::introuvable();
        }
        $ids = is_array($_POST['projets'] ?? null) ? array_values(array_unique(array_map('intval', $_POST['projets']))) : [];
        if ($ids === []) {
            Session::flash('erreur', t('pt.projets_choisir'));
            redirect('partager/' . $mot . '/' . $id);
        }
        $faits = 0;
        $refus = null;
        foreach ($ids as $projet) {
            $probleme = Travaux::lier(Auth::id(), $projet, $type, $id);
            if ($probleme === null) {
                $faits++;
            } else {
                $refus = $probleme;
            }
        }
        if ($faits > 0) {
            Session::flash('succes', tn('pt.flash_projets', $faits));
        }
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        }
        redirect('partager/' . $mot . '/' . $id);
    }

    /** Retire ce cours ou dossier d'un travail de groupe (il reste chez son propriétaire). */
    public function retirerDuProjet(string $mot, int $id, int $lien): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        if (Partages::mienne($type, $id, Auth::id()) === null) {
            self::introuvable();
        }
        // Le lien doit bien être celui de ce document : un numéro quelconque ne retire pas autre chose.
        $estLeSien = Database::valeur('SELECT 1 FROM projet_liens WHERE id = ? AND type = ? AND cible_id = ?', [$lien, $type, $id]);
        if ($estLeSien !== null && $estLeSien !== false && Travaux::delier(Auth::id(), $lien) !== null) {
            Session::flash('succes', t('pt.flash_projet_retire'));
        } else {
            Session::flash('erreur', t('pt.projets_pas_a_vous'));
        }
        redirect('partager/' . $mot . '/' . $id);
    }

    public function retirerAcces(string $mot, int $id, int $destinataire): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($destinataire)['pseudo'] ?? '');
        if (Partages::retirerAcces(Auth::id(), self::type($mot), $id, $destinataire)) {
            Session::flash('succes', t('pt.flash_plus_acces', ['qui' => $pseudo]));
        } else {
            Session::flash('erreur', t('pt.flash_partage_parti'));
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
            Session::flash('succes', t('pt.flash_droit', [
                'qui' => $pseudo,
                'droit' => mb_strtolower(Partages::libelleDroit($droit)),
            ]));
        } else {
            Session::flash('erreur', t('pt.flash_partage_parti'));
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
        Session::flash('succes', t('pt.flash_lien_cree'));
        redirect('partager/' . $mot . '/' . $id);
    }

    public function desactiverLien(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Session::flash(
            Partages::desactiverLien(Auth::id(), self::type($mot), $id) ? 'succes' : 'info',
            t('pt.flash_lien_desactive')
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
        $leMien = $cible !== null && (int) $cible['user_id'] === $moi;
        // L'aperçu de ce que je partage, tel que le voient les autres (lecture seule) : depuis la liste d'un groupe, par exemple.
        $apercuProprio = $leMien && (string) ($_GET['apercu'] ?? '') === '1' && in_array($type, ['cours', 'fiche', 'dossier'], true);
        if ($leMien && !$apercuProprio) {
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
            Session::flash('erreur', t('pt.flash_pas_partage'));
            redirect('partages');
        }
        // L'ouvrir, c'est l'avoir vu : l'onglet ne le compte plus.
        Partages::marquerVus($moi, $type, $id);
        // Sa matière est déjà chez moi (ajoutée un autre jour) : mes copies qui n'en ont pas la reçoivent, sans rien demander.
        if (!$leMien && in_array($type, ['cours', 'fiche', 'evenement'], true) && (string) ($cible['matiere_nom'] ?? '') !== ''
            && Partages::maMatiere($moi, $cible['matiere_nom']) !== null) {
            Partages::ajouterMatiere($moi, $type, $id);
        }
        $donnees = [
            'type' => $type,
            'cible' => $cible,
            'public' => false,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours($id), 'fiche' => Partages::fichiersDeLaFiche($id), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche($id) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier($id, (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('partages/cours/' . $c, $apercuProprio ? ['apercu' => 1] : []),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('partages/fichiers/' . $f . '/contenu', $telecharger ? ['telecharger' => 1] : []),
            'mesCours' => $type === 'fichier'
                ? Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$moi]) : [],
            'recu' => Database::valeur('SELECT 1 FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?', [$moi, $type, $id]) !== null,
            'droit' => $apercuProprio ? 'lecture' : (Partages::droit($type, $id, $moi) ?? 'lecture'),
            'commentaires' => Partages::commentaires($type, $id, $moi),
            'adresseIcs' => url('partages/evenements/' . $id . '/ics'),
            'afficheDOffice' => $type === 'evenement' && Partages::afficheDOffice($moi, (int) $cible['user_id']),
            // Ma copie de cet évènement (ajouté à mon calendrier), ou de ce cours, cette fiche, ce dossier (déjà chez moi) : on ne la propose plus.
            'maCopie' => $type === 'evenement'
                ? Database::valeur('SELECT id FROM evenements WHERE user_id = ? AND partage_de = ?', [$moi, $id])
                : ($leMien ? null : Partages::maCopie($moi, $type, $id)),
            'mot' => $mot,
            // Dans un travail de groupe : le document se modifie ici, par tout le groupe ; une copie à soi reste indépendante.
            'projetsDuDocument' => !$leMien && in_array($type, ['cours', 'dossier'], true) ? Travaux::projetsDuDocument($moi, $type, $id) : [],
            // L'aperçu de mon propre document : pas de copie à proposer, mais de quoi ouvrir le vrai.
            'apercuProprio' => $apercuProprio,
            'urlDuMien' => $apercuProprio ? match ($type) {
                'dossier' => url('cours', ['dossier' => $id]),
                'fiche' => url('revision/' . $id),
                default => url('cours/' . $id),
            } : null,
            // Sa matière : la voir, et l'ajouter à mes matières si je ne l'ai pas.
            'matiereAjoutable' => !$leMien && $type !== 'fichier' && $type !== 'dossier' && (string) ($cible['matiere_nom'] ?? '') !== ''
                ? ['nom' => (string) $cible['matiere_nom'], 'deja' => Partages::maMatiere($moi, $cible['matiere_nom']) !== null] : null,
            // L'évènement d'un ami : son cours lié (s'il m'est partagé), mes rappels et mes notes.
            'coursLie' => $type === 'evenement' ? Partages::coursLie($cible, $moi) : null,
            'perso' => $type === 'evenement' ? Partages::persoEvenement($id, $moi) : null,
        ];
        // Un cours ouvert depuis un dossier partagé : de quoi y revenir.
        if ($type === 'cours') {
            $dossierId = Partages::dossierPartage($id, $moi);
            if ($dossierId !== null) {
                $dossier = Partages::cible('dossier', $dossierId);
                $donnees['retour'] = [
                    'url' => url('partages/dossiers/' . $dossierId),
                    'texte' => t('pt.retour_dossier', ['titre' => (string) ($dossier['titre'] ?? t('pt.libelle_dossier'))]),
                ];
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
        Session::flash($refus === null ? 'succes' : 'erreur',
            $refus ?? t($reponseA === null ? 'pt.flash_commentaire_ajoute' : 'pt.flash_reponse_ajoutee'));
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
        Vue::afficher('partages/commentaires', $donnees, t('titre.commentaires'));
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
        Vue::afficher('partages/modifications', $donnees, t('titre.modifications'));
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
        Session::flash('succes', t('pt.flash_fichier_remis'));
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
        Session::flash('succes', t('pt.flash_commentaire_retire'));
        $this->retourDocument($ou[0], $ou[1]);
    }

    /** Écrire dans un document partagé : le texte du cours, ou de la fiche. */
    public function ecrire(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        $base = isset($_POST['base']) && is_string($_POST['base']) ? $_POST['base'] : null;
        $refus = Partages::ecrire(Auth::id(), $type, $id, (string) ($_POST['contenu'] ?? ''), $base);
        Session::flash($refus === null ? 'succes' : 'erreur', $refus === Partages::CONFLIT ? t('pt.conflit') : ($refus ?? t('pt.flash_enregistre')));
        $this->retourDocument($type, $id);
    }

    /** Joindre des fichiers à un document partagé. */
    public function joindre(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null)) {
            Session::flash('erreur', t('pt.flash_aucun_fichier'));
            $this->retourDocument($type, $id);
        }
        [$ajoutes, $erreurs] = Partages::joindre(Auth::id(), $type, $id, $_FILES['fichiers']);
        foreach ($erreurs as $erreur) {
            Session::flash('erreur', $erreur);
        }
        if ($ajoutes > 0) {
            Session::flash('succes', tn('pt.flash_joints', $ajoutes));
        } elseif ($erreurs === []) {
            Session::flash('erreur', t('pt.flash_aucun_fichier'));
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
        Session::flash('succes', t('pt.flash_fichier_retire'));
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

    /** Ouvre, ou referme, tout mon calendrier à un ami. */
    public function partagerCalendrier(int $ami): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $partager = ($_POST['partager'] ?? '') === '1';
        $compte = Amis::compte($ami);
        if (!Partages::partagerCalendrier(Auth::id(), $ami, $partager)) {
            self::introuvable();
        }
        Session::flash('succes', t($partager ? 'pt.fl.calendrier_partage' : 'pt.fl.calendrier_retire',
            ['qui' => (string) $compte['pseudo']]));
        repartir_vers('compte');
    }

    /** Où arrive ce qu'on me partage : aussi dans la discussion, ou seulement dans « Partagés ». */
    public function reglerReception(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $dansLaDiscussion = ($_POST['dans_discussion'] ?? '') === '1';
        Partages::reglerReception(Auth::id(), $dansLaDiscussion);
        Session::flash('succes', t($dansLaDiscussion ? 'pt.fl.reception_discussion' : 'pt.fl.reception_onglet'));
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
        Session::flash('succes', t($afficher ? 'pt.flash_calendrier_oui' : 'pt.flash_calendrier_non',
            ['qui' => (string) $compte['pseudo']]));
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
            exit(t('pt.fl.evenement_introuvable'));
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

    /** Ajoute à mes matières celle d'un document partagé. */
    public function ajouterMatiere(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$etat, $detail, $copies] = Partages::ajouterMatiere(Auth::id(), self::type($mot), $id);
        if ($etat === null) {
            Session::flash('erreur', $detail);
        } else {
            // « Ajoutée », ou « déjà là » ; et, s'il y en avait, les copies qui l'ont reçue.
            Session::flash($etat === 'ajoutee' || $copies > 0 ? 'succes' : 'info',
                t('pt.fl.matiere_' . $etat, ['nom' => $detail]) . ($copies > 0 ? ' ' . tn('pt.fl.matiere_copies', $copies) : ''));
        }
        redirect('partages/' . $mot . '/' . $id);
    }

    /** Mes rappels et mes notes sur l'évènement d'un ami. */
    public function perso(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $refus = Partages::enregistrerPerso(Auth::id(), $id, $_POST['rappels'] ?? [], (string) ($_POST['note'] ?? ''));
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', t('pt.fl.perso_enregistre'));
        }
        redirect('partages/evenements/' . $id);
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
        Session::flash('succes',
            t('pt.fl.copie_' . (in_array($type, ['cours', 'fiche', 'dossier', 'evenement'], true) ? $type : 'fichier')));
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
        Session::flash('succes', t('pt.flash_oublie'));
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
            Vue::afficherPublic('partages/lien_mort', [], t('titre.lien_introuvable'));
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
            Vue::afficherPublic('partages/lien_mort', [], t('titre.lien_introuvable'));
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
            exit(t('corps.fichier_introuvable'));
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
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
