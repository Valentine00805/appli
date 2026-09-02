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

        Vue::afficher('cours/afficher', [
            'cours'      => $cours,
            'fichiers'   => Database::all('SELECT * FROM fichiers WHERE cours_id = ? ORDER BY created_at', [$id]),
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

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
