<?php
declare(strict_types=1);

final class CoursController
{
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $recherche = trim((string) ($_GET['q'] ?? ''));
        $matiereId = entier_ou_null($_GET['matiere'] ?? null);
        $tagId     = entier_ou_null($_GET['tag'] ?? null);
        $dossierId = entier_ou_null($_GET['dossier'] ?? null);
        $favoris   = isset($_GET['favoris']);
        $tri       = in_array($_GET['tri'] ?? '', ['titre', 'ancien'], true) ? (string) $_GET['tri'] : 'recent';

        $cours = $this->chercher($userId, $recherche, $matiereId, $tagId, $dossierId, $favoris, $tri);

        Vue::afficher('cours/index', [
            'cours'     => $cours,
            'matieres'  => $this->matieres($userId),
            'tags'      => $this->tags($userId),
            'dossiers'  => DossiersController::pourUtilisateur($userId, true),
            'sansDossier' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IS NULL',
                [$userId]
            ),
            'total'     => (int) Database::valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$userId]),
            'recherche' => $recherche,
            'matiereId' => $matiereId,
            'tagId'     => $tagId,
            'dossierId' => $dossierId,
            'favoris'   => $favoris,
            'tri'       => $tri,
        ], 'Mes cours');
    }

    public function recherche(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $recherche = trim((string) ($_GET['q'] ?? ''));

        $cours = $recherche === '' ? [] : $this->chercher($userId, $recherche, null, null, false, 'recent');
        $evenements = [];
        if ($recherche !== '') {
            $evenements = Database::all(
                'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                        t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
                 FROM evenements e
                 LEFT JOIN matieres m        ON m.id = e.matiere_id
                 LEFT JOIN types_evenement t ON t.id = e.type_id
                 WHERE e.user_id = ? AND (e.titre LIKE ? OR e.description LIKE ? OR e.lieu LIKE ?)
                 ORDER BY e.debut DESC LIMIT 50',
                [$userId, "%$recherche%", "%$recherche%", "%$recherche%"]
            );
        }

        Vue::afficher('cours/recherche', [
            'recherche'  => $recherche,
            'cours'      => $cours,
            'evenements' => $evenements,
            'termes'     => preg_split('/\s+/u', $recherche, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ], 'Recherche');
    }

    public function afficher(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $cours = Database::one(
            'SELECT c.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur
             FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );
        if ($cours === null) {
            $this->introuvable();
        }

        // Le volet de révision s'ouvre et se referme depuis le même bouton.
        $revision = isset($_GET['revision']);

        Vue::afficher('cours/afficher', [
            'cours'      => $cours,
            'revision'   => $revision,
            'fichiers'   => Database::all(
                'SELECT * FROM fichiers WHERE cours_id = ? AND pour_fiche = 0 ORDER BY created_at',
                [$id]
            ),
            // Les pièces de la fiche : rangées à part, elles ne viennent pas du cours.
            'fichiersFiche' => Database::all(
                'SELECT * FROM fichiers WHERE cours_id = ? AND pour_fiche = 1 ORDER BY created_at',
                [$id]
            ),
            'elements'   => $this->elementsDeFiche($id, $userId),
            // De quoi remplir les sélecteurs, seulement quand le volet est ouvert.
            'autresCours' => $revision ? Database::all(
                'SELECT id, titre FROM cours WHERE user_id = ? AND id <> ? ORDER BY titre',
                [$userId, $id]
            ) : [],
            'evenementsChoix' => $revision ? Database::all(
                'SELECT id, titre, debut FROM evenements WHERE user_id = ? ORDER BY debut DESC LIMIT 100',
                [$userId]
            ) : [],
            'tags'       => Database::all(
                'SELECT t.* FROM tags t JOIN cours_tag ct ON ct.tag_id = t.id WHERE ct.cours_id = ? ORDER BY t.nom',
                [$id]
            ),
            'evenements' => Database::all(
                'SELECT e.*, t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
                 FROM evenements e
                 LEFT JOIN types_evenement t ON t.id = e.type_id
                 WHERE e.cours_id = ? AND e.user_id = ? ORDER BY e.debut',
                [$id, $userId]
            ),
        ], $cours['titre']);
    }

    /** Enregistre la fiche de révision d'un cours, depuis son volet. */
    public function enregistrerRevision(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $existe = Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($existe === null) {
            $this->introuvable();
        }

        $fiche = trim((string) ($_POST['fiche_revision'] ?? ''));

        Database::run(
            'UPDATE cours SET fiche_revision = ? WHERE id = ? AND user_id = ?',
            // Une fiche vidée redevient absente : le cours n'affiche pas une fiche blanche.
            [$fiche === '' ? null : $fiche, $id, $userId]
        );

        Session::flash('succes', $fiche === '' ? 'Fiche de révision vidée.' : 'Fiche de révision enregistrée.');
        redirect('cours/' . $id, ['revision' => 1]);
    }

    /** Joint des fichiers à la fiche de révision, pas aux pièces jointes du cours. */
    public function joindreFiche(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null)) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            redirect('cours/' . $id, ['revision' => 1]);
        }

        $compte = static fn (): int => (int) Database::valeur(
            'SELECT COUNT(*) FROM fichiers WHERE cours_id = ? AND pour_fiche = 1',
            [$id]
        );

        $avant = $compte();
        $erreurs = Fichiers::enregistrer($_FILES['fichiers'], $id, $userId, true);
        $ajoutes = $compte() - $avant;

        foreach ($erreurs as $erreur) {
            Session::flash('erreur', $erreur);
        }
        if ($ajoutes > 0) {
            Session::flash('succes', $ajoutes === 1
                ? 'Fichier ajouté à la fiche.'
                : $ajoutes . ' fichiers ajoutés à la fiche.');
        }
        redirect('cours/' . $id, ['revision' => 1]);
    }

    /** Rattache à la fiche un lien web, un autre cours ou un évènement. */
    public function ajouterElement(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $type = (string) ($_POST['type'] ?? '');
        $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 200);

        $erreur = match ($type) {
            'lien'      => $this->ajouterLien($id, $userId, $libelle),
            'cours'     => $this->ajouterRenvoi($id, $userId, 'cible_cours_id', 'cours', $libelle),
            'evenement' => $this->ajouterRenvoi($id, $userId, 'cible_evenement_id', 'evenements', $libelle),
            default     => 'Type d’élément inconnu.',
        };

        Session::flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Élément ajouté à la fiche.');
        redirect('cours/' . $id, ['revision' => 1]);
    }

    public function supprimerElement(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $coursId = Database::valeur(
            'SELECT cours_id FROM fiche_elements WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($coursId === null) {
            $this->introuvable();
        }

        Database::run('DELETE FROM fiche_elements WHERE id = ? AND user_id = ?', [$id, $userId]);
        Session::flash('succes', 'Élément retiré de la fiche.');
        redirect('cours/' . (int) $coursId, ['revision' => 1]);
    }

    /** Formulaire de création (id null) ou de modification. */
    public function formulaire(?int $id = null): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $cours = null;
        $tagsCours = [];
        if ($id !== null) {
            $cours = Database::one('SELECT * FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($cours === null) {
                $this->introuvable();
            }
            $tagsCours = array_column(Database::all(
                'SELECT t.nom FROM tags t JOIN cours_tag ct ON ct.tag_id = t.id WHERE ct.cours_id = ?',
                [$id]
            ), 'nom');
        }

        Vue::afficher('cours/formulaire', [
            'cours'            => $cours,
            'matieres'         => $this->matieres($userId),
            'dossiers'         => DossiersController::pourUtilisateur($userId),
            'tagsCours'        => implode(', ', $tagsCours),
            'fichiers'         => $id !== null
                ? Database::all('SELECT * FROM fichiers WHERE cours_id = ? ORDER BY created_at', [$id])
                : [],
            'matiereSelection' => entier_ou_null($_GET['matiere'] ?? null),
            'tousLesTags'      => TagsController::nomsPourUtilisateur($userId),
        ], $cours === null ? 'Nouveau cours' : 'Modifier le cours');
    }

    /**
     * Aperçu texte d'un document bureautique.
     * Le navigateur ne sait pas les afficher ; on en montre le texte ici.
     */
    public function apercuFichier(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $fichier = Database::one(
            'SELECT f.*, c.id AS cours_id, c.titre AS cours_titre
             FROM fichiers f JOIN cours c ON c.id = f.cours_id
             WHERE f.id = ? AND f.user_id = ?',
            [$id, $userId]
        );
        if ($fichier === null) {
            $this->introuvable();
        }
        if (!ApercuDocument::possible((string) $fichier['nom_origine'])) {
            redirect('fichiers/' . $id);
        }

        $chemin = Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
        $nom = (string) $fichier['nom_origine'];
        $genre = (string) ApercuDocument::genre($nom);

        $paragraphes = [];
        $lignes = [];
        $texte = '';
        $tronque = false;
        $total = 0;
        $erreur = null;

        try {
            switch ($genre) {
                case 'tableur':
                    ['lignes' => $lignes, 'total' => $total] = ApercuDocument::tableau((string) $chemin, $nom);
                    break;
                case 'brut':
                    ['texte' => $texte, 'tronque' => $tronque] = ApercuDocument::texteBrut((string) $chemin);
                    break;
                case 'document':
                    $paragraphes = ApercuDocument::paragraphes((string) $chemin, $nom);
                    break;
                // Un PDF et une image sont affichés tels quels : rien à lire ici.
            }
        } catch (Throwable $e) {
            $erreur = $e->getMessage();
        }

        Vue::afficher('cours/apercu', [
            'fichier'     => $fichier,
            'genre'       => $genre,
            'estTableur'  => $genre === 'tableur',
            'paragraphes' => $paragraphes,
            'lignes'      => $lignes,
            'texte'       => $texte,
            'tronque'     => $tronque,
            'total'       => $total,
            'limite'      => ApercuDocument::LIGNES_MAX,
            'erreur'      => $erreur,
            'format'      => ApercuDocument::format($nom),
        ], $nom);
    }

    /**
     * Crée un cours par fichier déposé sur un dossier.
     *
     * Un fichier ne peut pas vivre seul dans l'application : il est toujours
     * attaché à un cours. Déposer un document sur un dossier crée donc le
     * cours qui l'accueille, nommé d'après le fichier — un fichier, un cours,
     * pour que le résultat soit prévisible.
     */
    public function deposer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $dossier = DossiersController::valide($userId, $_POST['dossier'] ?? null);
        $noms = $_FILES['fichiers']['name'] ?? null;

        if (!is_array($noms) || $noms === []) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            $this->repartirVers('cours');
        }

        $crees = 0;
        foreach (array_keys($noms) as $i) {
            if ((int) ($_FILES['fichiers']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $nom = (string) $noms[$i];
            $titre = mb_substr(pathinfo($nom, PATHINFO_FILENAME) ?: $nom, 0, 200);
            if (trim($titre) === '') {
                $titre = 'Document';
            }

            Database::run(
                'INSERT INTO cours (user_id, matiere_id, dossier_id, titre, contenu) VALUES (?, NULL, ?, ?, NULL)',
                [$userId, $dossier, $titre]
            );
            $coursId = Database::dernierId();

            // Le fichier de rang $i, présenté seul au service d'enregistrement.
            $unSeul = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $cle) {
                $unSeul[$cle] = [$_FILES['fichiers'][$cle][$i] ?? null];
            }
            $erreurs = Fichiers::enregistrer($unSeul, $coursId, $userId);

            // Fichier refusé : on ne laisse pas un cours vide derrière.
            if (Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$coursId]) < 1) {
                Database::run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]);
                foreach ($erreurs as $erreur) {
                    Session::flash('erreur', $erreur);
                }
                continue;
            }
            $crees++;
        }

        if ($crees > 0) {
            $ou = $dossier === null
                ? ''
                : ' dans « ' . Database::valeur(
                    'SELECT nom FROM dossiers WHERE id = ? AND user_id = ?',
                    [$dossier, $userId]
                ) . ' »';
            Session::flash('succes', $crees === 1
                ? 'Un cours créé' . $ou . ', avec son fichier.'
                : $crees . ' cours créés' . $ou . ', un par fichier.');
        }

        $this->repartirVers('cours');
    }

    /**
     * Joint des fichiers à un cours depuis sa propre page.
     * C'est ce qu'appelle le dépôt de fichiers, sans passer par « Modifier ».
     */
    public function joindre(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null)) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            redirect('cours/' . $id);
        }

        $avant = (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$id]);
        $erreurs = Fichiers::enregistrer($_FILES['fichiers'], $id, $userId);
        $ajoutes = (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$id]) - $avant;

        foreach ($erreurs as $erreur) {
            Session::flash('erreur', $erreur);
        }
        if ($ajoutes > 0) {
            Session::flash('succes', $ajoutes . ' fichier' . ($ajoutes > 1 ? 's joints' : ' joint') . '.');
        } elseif ($erreurs === []) {
            Session::flash('erreur', 'Aucun fichier reçu.');
        }

        redirect('cours/' . $id);
    }

    /**
     * Range un cours dans un dossier, sans passer par le formulaire.
     * C'est ce qu'appelle le glisser-déposer depuis la liste des cours.
     */
    public function ranger(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $coursId = entier_ou_null($_POST['cours'] ?? null);
        $cours = $coursId === null ? null : Database::one(
            'SELECT id, titre FROM cours WHERE id = ? AND user_id = ?',
            [$coursId, $userId]
        );
        if ($cours === null) {
            $this->introuvable();
        }

        // Un dossier vide signifie « hors dossier » : c'est un choix valable.
        $dossier = DossiersController::valide($userId, $_POST['dossier'] ?? null);

        Database::run(
            'UPDATE cours SET dossier_id = ? WHERE id = ? AND user_id = ?',
            [$dossier, $cours['id'], $userId]
        );

        $nom = $dossier === null
            ? null
            : Database::valeur('SELECT nom FROM dossiers WHERE id = ? AND user_id = ?', [$dossier, $userId]);
        Session::flash('succes', $dossier === null
            ? '« ' . $cours['titre'] . ' » ne fait plus partie d’un dossier.'
            : '« ' . $cours['titre'] . ' » rangé dans « ' . $nom . ' ».');

        $this->repartirVers('cours');
    }

    /** Ne suit qu'une adresse interne, comme ailleurs dans l'application. */
    private function repartirVers(string $defaut): never
    {
        $retour = $_POST['retour'] ?? '';

        if (is_string($retour) && $retour !== ''
            && $retour[0] === '/'
            && !str_starts_with($retour, '//')
            && !preg_match('/[\r\n]/', $retour)
            && (BASE_URL === '' || str_starts_with($retour, BASE_URL . '/'))
        ) {
            header('Location: ' . $retour);
            exit;
        }

        redirect($defaut);
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $titre = post('titre');
        if ($titre === '') {
            Session::flash('erreur', 'Le titre est obligatoire.');
            redirect('cours/nouveau');
        }

        Database::run(
            'INSERT INTO cours (user_id, matiere_id, dossier_id, titre, contenu) VALUES (?, ?, ?, ?, ?)',
            [
                $userId,
                $this->matiereValide($userId, $_POST['matiere_id'] ?? null),
                DossiersController::valide($userId, $_POST['dossier_id'] ?? null),
                mb_substr($titre, 0, 200),
                post('contenu'),
            ]
        );
        $coursId = Database::dernierId();

        $this->synchroniserTags($userId, $coursId, post('tags'));
        $this->traiterFichiers($coursId, $userId);

        Session::flash('succes', 'Cours enregistré.');
        redirect('cours/' . $coursId);
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $titre = post('titre');
        if ($titre === '') {
            Session::flash('erreur', 'Le titre est obligatoire.');
            redirect('cours/' . $id . '/modifier');
        }

        Database::run(
            'UPDATE cours SET matiere_id = ?, dossier_id = ?, titre = ?, contenu = ? WHERE id = ? AND user_id = ?',
            [
                $this->matiereValide($userId, $_POST['matiere_id'] ?? null),
                DossiersController::valide($userId, $_POST['dossier_id'] ?? null),
                mb_substr($titre, 0, 200),
                post('contenu'),
                $id,
                $userId,
            ]
        );

        $this->synchroniserTags($userId, $id, post('tags'));
        $this->traiterFichiers($id, $userId);

        Session::flash('succes', 'Cours mis à jour.');
        redirect('cours/' . $id);
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $fichiers = Database::all('SELECT id FROM fichiers WHERE cours_id = ? AND user_id = ?', [$id, $userId]);
        foreach ($fichiers as $fichier) {
            Fichiers::supprimer((int) $fichier['id'], $userId);
        }
        Database::run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', 'Cours supprimé.');
        redirect('cours');
    }

    public function basculerFavori(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Database::run(
            'UPDATE cours SET favori = 1 - favori WHERE id = ? AND user_id = ?',
            [$id, Auth::id()]
        );
        redirect('cours/' . $id);
    }

    public function telechargerFichier(int $id): void
    {
        Auth::exiger();
        $fichier = Database::one(
            'SELECT * FROM fichiers WHERE id = ? AND user_id = ?',
            [$id, Auth::id()]
        );
        if ($fichier === null) {
            $this->introuvable();
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']));
    }

    public function modifierFichier(int $id): void
    {
        Auth::exiger();
        $fichier = $this->fichierModifiable($id);
        $nom = (string) $fichier['nom_origine'];

        try {
            $paragraphes = EditionDocument::lire($this->cheminDe($fichier), $nom);
            $erreur = null;
        } catch (Throwable $e) {
            $paragraphes = [];
            $erreur = $e->getMessage();
        }

        Vue::afficher('cours/modifier-document', [
            'fichier'     => $fichier,
            'paragraphes' => $paragraphes,
            'format'      => ApercuDocument::format($nom),
            'erreur'      => $erreur,
        ], 'Modifier ' . $nom);
    }

    public function enregistrerFichier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $fichier = $this->fichierModifiable($id);
        $nom = (string) $fichier['nom_origine'];
        $chemin = $this->cheminDe($fichier);

        $entrees = $this->paragraphesSoumis();
        if ($entrees === []) {
            Session::flash('erreur', 'Un document ne peut pas être entièrement vidé : gardez au moins une ligne.');
            redirect('fichiers/' . $id . '/modifier');
        }

        try {
            EditionDocument::enregistrer($chemin, $nom, $entrees);
        } catch (Throwable $e) {
            Session::flash('erreur', 'Le document n’a pas été modifié : ' . $e->getMessage());
            redirect('fichiers/' . $id . '/modifier');
        }

        // La taille affichée doit suivre le fichier, qui vient de changer.
        clearstatcache(true, $chemin);
        Database::run(
            'UPDATE fichiers SET taille = ? WHERE id = ? AND user_id = ?',
            [(int) filesize($chemin), $id, Auth::id()]
        );

        Session::flash('succes', 'Document enregistré.');
        redirect('fichiers/' . $id . '/apercu');
    }

    public function supprimerFichier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $coursId = Database::valeur('SELECT cours_id FROM fichiers WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($coursId === null) {
            $this->introuvable();
        }
        Fichiers::supprimer($id, $userId);
        Session::flash('succes', 'Fichier supprimé.');
        redirect('cours/' . (int) $coursId);
    }

    // --- Outils internes ------------------------------------------------

    private function chercher(
        int $userId,
        string $recherche,
        ?int $matiereId,
        ?int $tagId,
        ?int $dossierId,
        bool $favoris,
        string $tri
    ): array {
        $sql = 'SELECT c.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                       d.nom AS dossier_nom, d.couleur AS dossier_couleur, d.icone AS dossier_icone,
                       (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id) AS nb_fichiers
                FROM cours c
                LEFT JOIN matieres m ON m.id = c.matiere_id
                LEFT JOIN dossiers d ON d.id = c.dossier_id
                WHERE c.user_id = ?';
        $params = [$userId];

        foreach (preg_split('/\s+/u', $recherche, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $terme) {
            $sql .= ' AND (c.titre LIKE ? OR c.contenu LIKE ? OR m.nom LIKE ?)';
            array_push($params, "%$terme%", "%$terme%", "%$terme%");
        }
        if ($matiereId !== null) {
            $sql .= ' AND c.matiere_id = ?';
            $params[] = $matiereId;
        }
        if ($tagId !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM cours_tag ct WHERE ct.cours_id = c.id AND ct.tag_id = ?)';
            $params[] = $tagId;
        }
        if ($dossierId !== null) {
            // Ouvrir un dossier montre aussi ce que contiennent ses sous-dossiers.
            $branche = DossiersController::avecDescendants($userId, $dossierId);
            $sql .= ' AND c.dossier_id IN (' . implode(',', array_fill(0, count($branche), '?')) . ')';
            array_push($params, ...$branche);
        }
        if ($favoris) {
            $sql .= ' AND c.favori = 1';
        }

        $sql .= match ($tri) {
            'titre'  => ' ORDER BY c.titre ASC',
            'ancien' => ' ORDER BY c.created_at ASC',
            default  => ' ORDER BY c.updated_at DESC',
        };
        $sql .= ' LIMIT 300';

        return Database::all($sql, $params);
    }

    private function matieres(int $userId): array
    {
        return Database::all('SELECT * FROM matieres WHERE user_id = ? ORDER BY nom', [$userId]);
    }

    private function tags(int $userId): array
    {
        return Database::all(
            'SELECT t.id, t.nom, COUNT(ct.cours_id) AS nb
             FROM tags t LEFT JOIN cours_tag ct ON ct.tag_id = t.id
             WHERE t.user_id = ? GROUP BY t.id, t.nom ORDER BY t.nom',
            [$userId]
        );
    }

    private function matiereValide(int $userId, mixed $matiereId): ?int
    {
        $id = entier_ou_null($matiereId);
        if ($id === null) {
            return null;
        }
        $existe = Database::valeur('SELECT id FROM matieres WHERE id = ? AND user_id = ?', [$id, $userId]);
        return $existe === null ? null : $id;
    }

    /** Remplace les tags d'un cours à partir d'une saisie « maths, chapitre 3 ». */
    private function synchroniserTags(int $userId, int $coursId, string $saisie): void
    {
        Database::run('DELETE FROM cours_tag WHERE cours_id = ?', [$coursId]);

        $noms = array_filter(
            array_map(static fn (string $t): string => mb_substr(trim($t), 0, 60), explode(',', $saisie)),
            static fn (string $t): bool => $t !== ''
        );

        foreach (array_unique($noms) as $nom) {
            $tagId = Database::valeur('SELECT id FROM tags WHERE user_id = ? AND nom = ?', [$userId, $nom]);
            if ($tagId === null) {
                Database::run('INSERT INTO tags (user_id, nom) VALUES (?, ?)', [$userId, $nom]);
                $tagId = Database::dernierId();
            }
            Database::run(
                'INSERT IGNORE INTO cours_tag (cours_id, tag_id) VALUES (?, ?)',
                [$coursId, (int) $tagId]
            );
        }

        // Les tags devenus inutilises sont conservés : ils restent disponibles pour
        // un prochain cours, et se suppriment depuis la page « Tags ».
    }

    private function traiterFichiers(int $coursId, int $userId): void
    {
        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null)) {
            return;
        }
        foreach (Fichiers::enregistrer($_FILES['fichiers'], $coursId, $userId) as $erreur) {
            Session::flash('erreur', $erreur);
        }
    }

    /**
     * Ce qui est rattaché à une fiche, avec de quoi l'afficher.
     *
     * Les jointures suffisent à décrire chaque renvoi : la cible supprimée
     * emporte la ligne, les clés étrangères s'en chargent.
     */
    private function elementsDeFiche(int $coursId, int $userId): array
    {
        return Database::all(
            'SELECT e.*,
                    c.titre AS cours_titre,
                    v.titre AS evenement_titre, v.debut AS evenement_debut,
                    v.journee_entiere, v.termine,
                    t.icone AS type_icone, t.couleur AS type_couleur
             FROM fiche_elements e
             LEFT JOIN cours c            ON c.id = e.cible_cours_id
             LEFT JOIN evenements v       ON v.id = e.cible_evenement_id
             LEFT JOIN types_evenement t  ON t.id = v.type_id
             WHERE e.cours_id = ? AND e.user_id = ?
             ORDER BY e.type, e.position, e.id',
            [$coursId, $userId]
        );
    }

    /** @return string|null le message d'erreur, ou null si l'ajout a réussi */
    private function ajouterLien(int $coursId, int $userId, string $libelle): ?string
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url === '') {
            return 'Il manque l’adresse du lien.';
        }
        /*
         * Seuls http et https sont acceptés. Un « javascript: » ou un « data: »
         * placé ici deviendrait un lien cliquable dans la page : c'est la porte
         * d'entrée classique d'un script injecté.
         */
        if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'Adresse invalide : elle doit commencer par http:// ou https://.';
        }
        if (mb_strlen($url) > 2048) {
            return 'Adresse trop longue.';
        }

        // Sans intitulé, le nom du site fait l'affaire.
        if ($libelle === '') {
            $libelle = (string) (parse_url($url, PHP_URL_HOST) ?: 'Lien');
        }

        Database::run(
            'INSERT INTO fiche_elements (user_id, cours_id, type, libelle, url, position)
             VALUES (?, ?, \'lien\', ?, ?, ?)',
            [$userId, $coursId, $libelle, $url, $this->rangSuivant($coursId, 'lien')]
        );
        return null;
    }

    /**
     * Rattache un cours ou un évènement, après avoir vérifié qu'il appartient
     * bien à l'utilisateur : sans quoi une fiche pointerait chez quelqu'un d'autre.
     *
     * @return string|null le message d'erreur, ou null si l'ajout a réussi
     */
    private function ajouterRenvoi(
        int $coursId,
        int $userId,
        string $colonne,
        string $table,
        string $libelle
    ): ?string {
        $cible = entier_ou_null($_POST['cible'] ?? null);
        if ($cible === null) {
            return 'Aucun élément choisi.';
        }
        if ($table === 'cours' && $cible === $coursId) {
            return 'Un cours ne peut pas renvoyer à lui-même.';
        }
        if (Database::valeur("SELECT id FROM `$table` WHERE id = ? AND user_id = ?", [$cible, $userId]) === null) {
            return 'Élément introuvable.';
        }

        $type = $table === 'cours' ? 'cours' : 'evenement';
        $deja = Database::valeur(
            "SELECT id FROM fiche_elements WHERE cours_id = ? AND user_id = ? AND `$colonne` = ?",
            [$coursId, $userId, $cible]
        );
        if ($deja !== null) {
            return 'Cet élément est déjà dans la fiche.';
        }

        Database::run(
            "INSERT INTO fiche_elements (user_id, cours_id, type, libelle, `$colonne`, position)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $coursId, $type, $libelle !== '' ? $libelle : null, $cible, $this->rangSuivant($coursId, $type)]
        );
        return null;
    }

    private function rangSuivant(int $coursId, string $type): int
    {
        return 1 + (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) FROM fiche_elements WHERE cours_id = ? AND type = ?',
            [$coursId, $type]
        );
    }

    /** Le fichier de l'utilisateur, à condition que son texte soit réécrivable. */
    private function fichierModifiable(int $id): array
    {
        $fichier = Database::one(
            'SELECT f.*, c.id AS cours_id, c.titre AS cours_titre
             FROM fichiers f JOIN cours c ON c.id = f.cours_id
             WHERE f.id = ? AND f.user_id = ?',
            [$id, Auth::id()]
        );
        if ($fichier === null) {
            $this->introuvable();
        }
        if (!EditionDocument::modifiable((string) $fichier['nom_origine'])) {
            redirect('fichiers/' . $id . '/apercu');
        }
        return $fichier;
    }

    private function cheminDe(array $fichier): string
    {
        return Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
    }

    /**
     * Les paragraphes envoyés par le formulaire, dans l'ordre de la page.
     *
     * Une zone de saisie peut contenir plusieurs lignes : chacune devient un
     * paragraphe à part, en gardant la mise en forme de celui d'où elle vient.
     * Une ligne ajoutée puis laissée vide est ignorée ; un paragraphe existant
     * qu'on vide reste, car c'est ainsi qu'on garde une ligne blanche.
     *
     * @return array<int, array{origine: ?int, texte: string}>
     */
    private function paragraphesSoumis(): array
    {
        $textes   = array_values((array) ($_POST['texte'] ?? []));
        $origines = array_values((array) ($_POST['origine'] ?? []));

        $entrees = [];
        foreach ($textes as $rang => $texte) {
            if (!is_scalar($texte)) {
                continue;
            }
            $reference = $origines[$rang] ?? '';
            $origine = is_numeric($reference) ? (int) $reference : null;

            // Découpage octet par octet : les fins de ligne sont de l'ASCII,
            // et un motif Unicode échouerait en silence sur un texte mal encodé
            // — au prix d'un paragraphe vidé sans prévenir.
            foreach (preg_split('/\r\n|\r|\n/', (string) $texte) ?: [''] as $ligne) {
                $ligne = rtrim($ligne);
                if ($ligne === '' && $origine === null) {
                    continue;
                }
                $entrees[] = ['origine' => $origine, 'texte' => $ligne];
            }
        }
        return $entrees;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
