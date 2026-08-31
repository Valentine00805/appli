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
        $favoris   = isset($_GET['favoris']);
        $tri       = in_array($_GET['tri'] ?? '', ['titre', 'ancien'], true) ? (string) $_GET['tri'] : 'recent';

        $cours = $this->chercher($userId, $recherche, $matiereId, $tagId, $favoris, $tri);

        Vue::afficher('cours/index', [
            'cours'     => $cours,
            'matieres'  => $this->matieres($userId),
            'tags'      => $this->tags($userId),
            'recherche' => $recherche,
            'matiereId' => $matiereId,
            'tagId'     => $tagId,
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
                'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur
                 FROM evenements e
                 LEFT JOIN matieres m ON m.id = e.matiere_id
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
                'SELECT * FROM evenements WHERE cours_id = ? AND user_id = ? ORDER BY debut',
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
            'tagsCours'        => implode(', ', $tagsCours),
            'fichiers'         => $id !== null
                ? Database::all('SELECT * FROM fichiers WHERE cours_id = ? ORDER BY created_at', [$id])
                : [],
            'matiereSelection' => entier_ou_null($_GET['matiere'] ?? null),
        ], $cours === null ? 'Nouveau cours' : 'Modifier le cours');
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
            'INSERT INTO cours (user_id, matiere_id, titre, contenu) VALUES (?, ?, ?, ?)',
            [
                $userId,
                $this->matiereValide($userId, $_POST['matiere_id'] ?? null),
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
            'UPDATE cours SET matiere_id = ?, titre = ?, contenu = ? WHERE id = ? AND user_id = ?',
            [
                $this->matiereValide($userId, $_POST['matiere_id'] ?? null),
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
        bool $favoris,
        string $tri
    ): array {
        $sql = 'SELECT c.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                       (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id) AS nb_fichiers
                FROM cours c
                LEFT JOIN matieres m ON m.id = c.matiere_id
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

        // Nettoyage des tags devenus orphelins.
        Database::run(
            'DELETE FROM tags WHERE user_id = ? AND id NOT IN (SELECT tag_id FROM cours_tag)',
            [$userId]
        );
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
