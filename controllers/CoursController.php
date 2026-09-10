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

        // Aucun filtre : la recherche globale cherche dans tous les cours,
        // quels que soient leur matière, leur tag et leur dossier.
        $cours = $recherche === ''
            ? []
            : $this->chercher($userId, $recherche, null, null, null, false, 'recent');
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
            'fichiersFiche' => $this->fichiersDeFiche($id, $userId),
            'cartes'     => $this->cartesDuCours($id, $userId),
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

    /**
     * Enregistre le seul texte du cours, depuis sa page.
     *
     * Corriger une phrase ne devrait pas obliger à rouvrir la fiche entière,
     * avec le titre, la matière et le dossier : c'est beaucoup de champs à
     * traverser, et autant d'occasions d'en changer un par mégarde.
     */
    public function enregistrerContenu(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $existe = Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($existe === null) {
            $this->introuvable();
        }

        $contenu = trim((string) ($_POST['contenu'] ?? ''));

        Database::run(
            'UPDATE cours SET contenu = ? WHERE id = ? AND user_id = ?',
            // Un texte effacé redevient absent, comme une fiche vidée.
            [$contenu === '' ? null : $contenu, $id, $userId]
        );

        Session::flash('succes', $contenu === '' ? 'Contenu du cours vidé.' : 'Contenu du cours enregistré.');
        redirect('cours/' . $id, ($_POST['revision'] ?? '') === '1' ? ['revision' => 1] : []);
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
        $this->retourFiche($id);
    }

    /**
     * Retient où l'on s'est arrêté dans un enregistrement.
     *
     * Appelée par le lecteur en cours de route, sans recharger la page : elle
     * ne répond donc rien, sinon un code. La durée arrive en même temps, le
     * navigateur étant le seul à la connaître.
     */
    public function positionLecture(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $fichier = Database::one(
            'SELECT id, duree_lecture FROM fichiers WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($fichier === null) {
            http_response_code(404);
            exit;
        }

        $position = max(0, (int) round((float) ($_POST['position'] ?? 0)));
        $duree = max(0, (int) round((float) ($_POST['duree'] ?? 0)));

        // Une durée absente ne doit pas effacer celle qu'on connaissait.
        if ($duree === 0) {
            $duree = (int) $fichier['duree_lecture'];
        }
        if ($duree > 0) {
            $position = min($position, $duree);
        }

        Database::run(
            'UPDATE fichiers SET position_lecture = ?, duree_lecture = ? WHERE id = ? AND user_id = ?',
            [$position, $duree, $id, $userId]
        );

        http_response_code(204);
        exit;
    }

    /** Toutes les fiches de révision, groupées par matière. */
    public function revisions(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $recherche = trim((string) ($_GET['q'] ?? ''));
        $termes = preg_split('/\s+/u', $recherche, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matiereId = entier_ou_null($_GET['matiere'] ?? null);
        $matieres = $this->matieres($userId);
        $tri = in_array($_GET['tri'] ?? '', ['recent', 'ancien'], true) ? (string) $_GET['tri'] : 'matiere';

        // Une matière qui n'est pas la sienne ne filtre rien : la retenir
        // afficherait « 0 fiche » sans pouvoir dire de quelle matière.
        if ($matiereId !== null
            && !in_array($matiereId, array_map(static fn (array $m): int => (int) $m['id'], $matieres), true)
        ) {
            $matiereId = null;
        }

        $filtre = '';
        $params = [$userId];

        if ($matiereId !== null) {
            $filtre .= ' AND c.matiere_id = ?';
            $params[] = $matiereId;
        }
        /*
         * Chaque terme doit se retrouver quelque part dans la fiche : son
         * texte, le titre du cours, un intitulé de lien, un nom de fichier.
         * Chercher « annales » doit trouver la fiche où elles sont jointes,
         * même si le mot n'est pas écrit dedans.
         */
        foreach ($termes as $terme) {
            $filtre .= ' AND (c.titre LIKE ? OR c.fiche_revision LIKE ?
                         OR EXISTS (SELECT 1 FROM fiche_elements e
                                     WHERE e.cours_id = c.id
                                       AND (e.libelle LIKE ? OR e.url LIKE ?))
                         OR EXISTS (SELECT 1 FROM fichiers f
                                     WHERE f.cours_id = c.id AND f.pour_fiche = 1
                                       AND f.nom_origine LIKE ?))';
            array_push($params, "%$terme%", "%$terme%", "%$terme%", "%$terme%", "%$terme%");
        }

        /*
         * La date de modification d'une fiche n'est pas seulement celle du
         * cours : ajouter un lien ou joindre un fichier la fait vivre sans
         * toucher à la ligne « cours ». On prend donc la plus récente des trois.
         */
        $modifiee = 'GREATEST(
                c.updated_at,
                COALESCE((SELECT MAX(e.created_at) FROM fiche_elements e
                           WHERE e.cours_id = c.id), c.updated_at),
                COALESCE((SELECT MAX(f.created_at) FROM fichiers f
                           WHERE f.cours_id = c.id AND f.pour_fiche = 1), c.updated_at)
            )';

        $ordre = match ($tri) {
            'recent' => $modifiee . ' DESC, c.titre',
            'ancien' => $modifiee . ' ASC, c.titre',
            default  => 'COALESCE(m.nom, \'￿\'), c.titre',
        };

        $cours = Database::all(
            'SELECT c.id, c.titre, c.fiche_revision, c.updated_at,
                    ' . $modifiee . ' AS modifiee_le,
                    m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    (SELECT COUNT(*) FROM fichiers f
                      WHERE f.cours_id = c.id AND f.pour_fiche = 1)            AS nb_fichiers,
                    (SELECT COUNT(*) FROM fiche_elements e
                      WHERE e.cours_id = c.id AND e.type = \'lien\')           AS nb_liens,
                    (SELECT COUNT(*) FROM fiche_elements e
                      WHERE e.cours_id = c.id AND e.type = \'cours\')          AS nb_renvois,
                    (SELECT COUNT(*) FROM fiche_elements e
                      WHERE e.cours_id = c.id AND e.type = \'evenement\')      AS nb_evenements,
                    (SELECT COUNT(*) FROM cartes k
                      WHERE k.cours_id = c.id)                                 AS nb_cartes,
                    (SELECT COUNT(*) FROM cartes k
                      WHERE k.cours_id = c.id AND k.revoir_le <= CURDATE())     AS nb_cartes_dues
             FROM cours c
             LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE c.user_id = ?' . $filtre . '
             ORDER BY ' . $ordre,
            $params
        );

        // Une fiche existe dès qu'elle porte du texte ou le moindre élément.
        $garnies = [];
        $vides = [];
        foreach ($cours as $c) {
            $elements = (int) $c['nb_fichiers'] + (int) $c['nb_liens']
                      + (int) $c['nb_renvois'] + (int) $c['nb_evenements'];
            $c['nb_elements'] = $elements;

            // Le terme se cache peut-être dans un lien ou un nom de fichier :
            // la carte le dira, plutôt que d'afficher un extrait sans surlignage.
            $c['trouve_ailleurs'] = $termes !== []
                && !$this->contient((string) $c['titre'] . ' ' . (string) $c['fiche_revision'], $termes);

            if (trim((string) $c['fiche_revision']) !== '' || $elements > 0) {
                $garnies[] = $c;
            } else {
                $vides[] = $c;
            }
        }

        // Chaque carte porte l'anneau de sa fiche : ce qui en a été écouté, lu,
        // et où en est son paquet de cartes.
        $anneaux = $this->anneauxDesFiches(array_merge($garnies, $vides), $userId);
        $paquets = $this->paquetsDesFiches($userId);

        Vue::afficher('cours/revisions', [
            'garnies'   => $garnies,
            'vides'     => $vides,
            'anneaux'   => $anneaux,
            'paquets'   => $paquets,
            'recherche' => $recherche,
            'termes'    => $termes,
            'matieres'  => $matieres,
            'matiereId' => $matiereId,
            'tri'       => $tri,
        ], $recherche === '' ? 'Révision' : 'Révision — ' . $recherche);
    }

    /**
     * Ce qui se lit ou s'écoute dans les fiches de ces cours, rangé par cours.
     *
     * Un seul aller-retour en base, et le tri se fait ici : la table ne dit
     * pas si un fichier se lit ou s'écoute, seul son nom le dit vraiment.
     *
     * @param array<int, array> $cours
     * @return array<int, array<int, array>>
     */
    private function anneauxDesFiches(array $cours, int $userId): array
    {
        $ids = array_map(static fn (array $c): int => (int) $c['id'], $cours);
        if ($ids === []) {
            return [];
        }

        $lignes = Database::all(
            'SELECT id, cours_id, nom_origine, mime, position_lecture, duree_lecture
               FROM fichiers
              WHERE user_id = ? AND pour_fiche = 1
                AND cours_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_merge([$userId], $ids)
        );

        $parCours = [];
        foreach (fichiers_suivis($lignes) as $ligne) {
            $parCours[(int) $ligne['cours_id']][] = $ligne;
        }
        return $parCours;
    }
    /**
     * Où en est le paquet de cartes d'un cours.
     *
     * @return array{total: int, a_revoir: int}
     */
    private function cartesDuCours(int $coursId, int $userId): array
    {
        $ligne = Database::one(
            'SELECT COUNT(*) AS total, SUM(revoir_le <= CURDATE()) AS a_revoir,
                    AVG(boite) AS boite_moyenne, SUM(boite) AS somme_boites
               FROM cartes WHERE cours_id = ? AND user_id = ?',
            [$coursId, $userId]
        );

        $aRevoir = (int) ($ligne['a_revoir'] ?? 0);
        $total = (int) ($ligne['total'] ?? 0);

        return [
            'total'    => $total,
            'a_revoir' => $aRevoir,
            'avancement' => avancement_cartes($total, (float) ($ligne['boite_moyenne'] ?? 1)),
            // La somme des boîtes suit la séance : l'anneau de l'en-tête la
            // recalcule à chaque verdict, sans repasser par le serveur.
            'somme_boites' => (int) ($ligne['somme_boites'] ?? 0),
            // Les cartes dues voyagent avec le compte : la fiche les déplie
            // sur place, sans aller les chercher ailleurs.
            'dues'     => $aRevoir === 0 ? [] : Database::all(
                'SELECT id, question, reponse, boite FROM cartes
                  WHERE cours_id = ? AND user_id = ? AND revoir_le <= CURDATE()
                  ORDER BY revoir_le, boite, RAND() LIMIT ' . CartesController::SEANCE_MAX,
                [$coursId, $userId]
            ),
        ];
    }

    /**
     * Les pièces jointes d'une fiche, un PDF sachant combien il a de pages.
     *
     * Le compte est fait une seule fois, à la première consultation, puis
     * gardé en base : il sert d'échelle à l'anneau d'avancement, comme la
     * durée d'un enregistrement.
     *
     * @return array<int, array>
     */
    private function fichiersDeFiche(int $coursId, int $userId): array
    {
        $fichiers = Database::all(
            'SELECT * FROM fichiers WHERE cours_id = ? AND pour_fiche = 1 ORDER BY created_at',
            [$coursId]
        );

        foreach ($fichiers as $rang => $fichier) {
            if ((int) $fichier['duree_lecture'] > 0
                || !Fichiers::estPdf((string) $fichier['mime'], (string) $fichier['nom_origine'])) {
                continue;
            }

            $pages = Fichiers::pagesPdf(
                Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke']
            );
            if ($pages === null) {
                continue;
            }

            Database::run(
                'UPDATE fichiers SET duree_lecture = ? WHERE id = ? AND user_id = ?',
                [$pages, (int) $fichier['id'], $userId]
            );
            $fichiers[$rang]['duree_lecture'] = $pages;
        }

        return $fichiers;
    }
    /**
     * L'avancement du paquet de cartes de chaque cours, par cours.
     *
     * @return array<int, int> identifiant du cours => pourcentage
     */
    private function paquetsDesFiches(int $userId): array
    {
        $paquets = [];

        $lignes = Database::all(
            'SELECT cours_id, COUNT(*) AS total, AVG(boite) AS boite_moyenne
               FROM cartes WHERE user_id = ? GROUP BY cours_id',
            [$userId]
        );
        foreach ($lignes as $ligne) {
            $part = avancement_cartes((int) $ligne['total'], (float) $ligne['boite_moyenne']);
            if ($part !== null) {
                $paquets[(int) $ligne['cours_id']] = $part;
            }
        }

        return $paquets;
    }

    /** Ce texte contient-il au moins un des termes cherchés ? */
    private function contient(string $texte, array $termes): bool
    {
        foreach ($termes as $terme) {
            if ($terme !== '' && mb_stripos($texte, $terme) !== false) {
                return true;
            }
        }
        return false;
    }

    /** La fiche d'un cours, seule : ni son contenu, ni ses pièces jointes. */
    public function fiche(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $cours = Database::one(
            'SELECT c.id, c.titre, c.fiche_revision,
                    m.nom AS matiere_nom, m.couleur AS matiere_couleur
             FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );
        if ($cours === null) {
            $this->introuvable();
        }

        $parType = ['lien' => [], 'cours' => [], 'evenement' => []];
        foreach ($this->elementsDeFiche($id, $userId) as $element) {
            $parType[$element['type']][] = $element;
        }

        Vue::afficher('cours/fiche', [
            'cours'   => $cours,
            'fiche'   => (string) ($cours['fiche_revision'] ?? ''),
            'parType' => $parType,
            'fichiersFiche' => $this->fichiersDeFiche($id, $userId),
            'cartes'     => $this->cartesDuCours($id, $userId),
            'autresCours' => Database::all(
                'SELECT id, titre FROM cours WHERE user_id = ? AND id <> ? ORDER BY titre',
                [$userId, $id]
            ),
            'evenementsChoix' => Database::all(
                'SELECT id, titre, debut FROM evenements WHERE user_id = ? ORDER BY debut DESC LIMIT 100',
                [$userId]
            ),
        ], 'Fiche — ' . $cours['titre']);
    }

    /**
     * Où revenir après avoir modifié une fiche : sur sa page si l'on y était,
     * sur le cours sinon. Deux destinations connues, aucune venue de l'URL.
     */
    private function retourFiche(int $coursId): never
    {
        if (($_POST['page'] ?? '') === 'fiche') {
            redirect('revision/' . $coursId);
        }
        redirect('cours/' . $coursId, ['revision' => 1]);
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
            $this->retourFiche($id);
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
        $this->retourFiche($id);
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
        $this->retourFiche($id);
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
        $this->retourFiche((int) $coursId);
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
        $enrichis = [];
        $sommaire = 0;
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
                    /*
                     * Le même texte, mise en forme comprise, quand le format se
                     * laisse relire. L'aperçu montre alors ce que l'éditeur a
                     * enregistré ; sinon il reste au texte nu, ce qui vaut
                     * mieux que rien.
                     */
                    if (EditionDocument::modifiable($nom)) {
                        try {
                            $enrichis = EditionDocument::apercuRiche((string) $chemin, $nom);
                        } catch (Throwable) {
                            $enrichis = [];
                        }
                        /*
                         * Les deux lectures doivent trouver autant de
                         * paragraphes de texte l'une que l'autre : au moindre
                         * écart, la lecture riche s'est égarée et l'on s'en
                         * tient au texte nu, qui vaut mieux que faux.
                         *
                         * On ne compte que ceux qui portent du texte : la
                         * lecture riche rend aussi les paragraphes faits d'une
                         * seule image, que la lecture nue ne voit pas.
                         */
                        $textuels = count(array_filter(
                            $enrichis,
                            static fn (array $e): bool => ($e['html'] ?? '') !== ''
                        ));
                        if ($textuels !== count($paragraphes)) {
                            $enrichis = [];
                        }
                        // Le sommaire du document n'est pas montré tel quel :
                        // l'aperçu le refait à partir des titres, à jour.
                        $sommaire = EditionDocument::profondeurDuSommaire((string) $chemin, $nom);
                    }
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
            'enrichis'    => $enrichis,
            'sommaire'    => $sommaire,
            'lignes'      => $lignes,
            'texte'       => $texte,
            'tronque'     => $tronque,
            'total'       => $total,
            'limite'      => ApercuDocument::LIGNES_MAX,
            'erreur'      => $erreur,
            'format'      => ApercuDocument::format($nom),
        ], $nom);
    }

    /** Jusqu'où l'on recrée l'arborescence d'un dossier déposé. */
    private const DOSSIERS_MAX = 4;

    /**
     * Dépose un dossier entier : un cours par fichier, l'arborescence reprise.
     *
     * Le navigateur n'accepte qu'une poignée de fichiers par envoi — vingt le
     * plus souvent — et le script découpe donc le dépôt en paquets qu'il
     * enchaîne. Chaque fichier arrive avec son chemin à côté de lui : un envoi
     * ordinaire ne transmet que le nom, jamais le chemin.
     *
     * La réponse est en JSON, car c'est le script qui mène le dépôt et fait la
     * somme des paquets.
     */
    public function deposerDossier(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $racine = DossiersController::valide($userId, $_POST['dossier'] ?? null);
        $chemins = array_values((array) ($_POST['chemins'] ?? []));
        $noms = $_FILES['fichiers']['name'] ?? null;

        if (!is_array($noms)) {
            $this->repondreJson(['cours' => 0, 'dossiers' => 0, 'erreurs' => ['Aucun fichier reçu.']]);
        }

        $cours = 0;
        $dossiers = 0;
        $erreurs = [];

        foreach (array_keys($noms) as $i) {
            if ((int) ($_FILES['fichiers']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nom = (string) $noms[$i];
            $chemin = $chemins[$i] ?? '';
            [$parent, $nes] = $this->dossierDuChemin($userId, $racine, is_string($chemin) ? $chemin : '');
            $dossiers += $nes;

            $titre = trim(mb_substr(pathinfo($nom, PATHINFO_FILENAME) ?: $nom, 0, 200));
            if ($titre === '') {
                $titre = 'Document';
            }

            Database::run(
                'INSERT INTO cours (user_id, matiere_id, dossier_id, titre, contenu) VALUES (?, NULL, ?, ?, NULL)',
                [$userId, $parent, $titre]
            );
            $coursId = Database::dernierId();

            // Le fichier de rang $i, présenté seul au service d'enregistrement.
            $unSeul = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $cle) {
                $unSeul[$cle] = [$_FILES['fichiers'][$cle][$i] ?? null];
            }
            $refus = Fichiers::enregistrer($unSeul, $coursId, $userId);

            // Fichier refusé : on ne laisse pas un cours vide derrière.
            if ((int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$coursId]) < 1) {
                Database::run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]);
                foreach ($refus as $refuse) {
                    $erreurs[] = $refuse;
                }
                continue;
            }
            $cours++;
        }

        $this->repondreJson(['cours' => $cours, 'dossiers' => $dossiers, 'erreurs' => $erreurs]);
    }

    /**
     * Le dossier où ranger un fichier, d'après son chemin dans le dépôt.
     *
     * Ce chemin vient du navigateur : il ne sert qu'à nommer des dossiers, et
     * jamais à écrire où que ce soit — le fichier, lui, est rangé sous un nom
     * tiré au sort. « .. », les noms vides et les caractères de contrôle sont
     * écartés, et on ne descend pas au-delà de quelques étages : un dossier
     * peut en cacher de très profonds.
     *
     * @return array{0: ?int, 1: int}  le dossier d'arrivée, et combien sont nés
     */
    private function dossierDuChemin(int $userId, ?int $racine, string $chemin): array
    {
        $morceaux = preg_split('#[\\\\/]+#', $chemin) ?: [];
        array_pop($morceaux); // le dernier morceau est le nom du fichier

        $parent = $racine;
        $nes = 0;
        $etage = 0;

        foreach ($morceaux as $morceau) {
            if ($etage >= self::DOSSIERS_MAX) {
                break;
            }
            $nom = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $morceau));
            if ($nom === '' || $nom === '.' || $nom === '..') {
                continue;
            }
            [$parent, $ne] = $this->dossierNomme($userId, $parent, mb_substr($nom, 0, 120));
            $nes += $ne;
            $etage++;
        }

        return [$parent, $nes];
    }

    /**
     * Un dossier de ce nom, créé s'il n'existait pas.
     *
     * L'application ne permet qu'un dossier par nom : celui qui existe déjà
     * sert de destination, où qu'il se trouve. Deux dossiers « TD » ne se
     * distingueraient pas l'un de l'autre dans la liste.
     *
     * @return array{0: int, 1: int}  le dossier, et 1 s'il vient d'être créé
     */
    private function dossierNomme(int $userId, ?int $parent, string $nom): array
    {
        $existe = Database::valeur('SELECT id FROM dossiers WHERE user_id = ? AND nom = ?', [$userId, $nom]);
        if ($existe !== null) {
            return [(int) $existe, 0];
        }

        // Un nouveau dossier se range à la fin de ses frères.
        $rang = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers
             WHERE user_id = ? AND parent_id ' . ($parent === null ? 'IS NULL' : '= ?'),
            $parent === null ? [$userId] : [$userId, $parent]
        );
        Database::run(
            'INSERT INTO dossiers (user_id, parent_id, nom, position) VALUES (?, ?, ?, ?)',
            [$userId, $parent, $nom, $rang]
        );

        return [(int) Database::dernierId(), 1];
    }

    /** Répond en JSON et s'arrête là : c'est le script qui attend cela. */
    private function repondreJson(array $donnees): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
        exit;
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
            repartir_vers('cours');
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

        repartir_vers('cours');
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

        repartir_vers('cours');
    }

    /** Ne suit qu'une adresse interne, comme ailleurs dans l'application. */

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

    /**
     * Une image du document, relue de l'archive et servie telle quelle.
     *
     * L'adresse ne porte qu'un rang dans la liste des images du document :
     * jamais un chemin. Un rang qui ne désigne rien — ou qui désigne un format
     * que le navigateur n'affiche pas — ne donne rien du tout.
     *
     * Le fichier n'est relu qu'à la première demande de chaque image : ensuite
     * le navigateur garde la sienne, et une page qui en compte trente ne
     * rouvre pas trente fois l'archive à chaque défilement.
     */
    public function imageFichier(int $id): void
    {
        Auth::exiger();
        $fichier = Database::one(
            'SELECT * FROM fichiers WHERE id = ? AND user_id = ?',
            [$id, Auth::id()]
        );
        if ($fichier === null || !ImagesDocument::possible((string) $fichier['nom_origine'])) {
            $this->introuvable();
        }

        $chemin = $this->cheminDe($fichier);
        $rang = entier_ou_null($_GET['n'] ?? null);
        $image = $rang === null ? null
            : ImagesDocument::octets($chemin, (string) $fichier['nom_origine'], $rang);
        if ($image === null) {
            $this->introuvable();
        }

        // Le document ne change qu'en étant réenregistré : sa date de
        // modification suffit à dire si l'image d'hier vaut encore.
        $marque = '"' . md5($id . ':' . $rang . ':' . (string) @filemtime($chemin)) . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $marque) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $image['type']);
        header('Content-Length: ' . (string) strlen($image['octets']));
        header('Content-Disposition: inline');
        // Le type annoncé fait foi : pas de reniflage, pas de script déguisé
        // en image.
        header('X-Content-Type-Options: nosniff');
        header('ETag: ' . $marque);
        header('Cache-Control: private, max-age=86400');
        echo $image['octets'];
        exit;
    }

    public function modifierFichier(int $id): void
    {
        Auth::exiger();
        $fichier = $this->fichierModifiable($id);
        $nom = (string) $fichier['nom_origine'];

        try {
            $chemin = $this->cheminDe($fichier);
            // Deux lectures du même document : le texte nu pour le formulaire
            // sans JavaScript, la mise en forme pour l'éditeur.
            $paragraphes = EditionDocument::lire($chemin, $nom);
            $enrichis = EditionDocument::lireRiche($chemin, $nom);
            $sommaire = EditionDocument::profondeurDuSommaire($chemin, $nom);
            $erreur = null;
        } catch (Throwable $e) {
            $paragraphes = [];
            $enrichis = [];
            $sommaire = 0;
            $erreur = $e->getMessage();
        }

        Vue::afficher('cours/modifier-document', [
            'fichier'     => $fichier,
            'paragraphes' => $paragraphes,
            'enrichis'    => $enrichis,
            'sommaire'    => $sommaire,
            'titreMax'    => EditionDocument::TITRE_MAX,
            'tailles'     => EditionDocument::TAILLES,
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
            // « riche » n'est envoyé que par l'éditeur : sans lui, le texte est
            // nu et l'absence de gras ne veut pas dire qu'il faut l'enlever.
            // Un envoi qui ne dit rien du sommaire ne doit pas l'effacer :
            // seule une valeur reçue vaut décision.
            $profondeur = $_POST['sommaire'] ?? null;
            $profondeur = is_numeric($profondeur)
                ? max(0, min((int) $profondeur, EditionDocument::TITRE_MAX))
                : null;

            EditionDocument::enregistrer($chemin, $nom, $entrees, ($_POST['riche'] ?? '') === '1',
                $profondeur);
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
            // La fiche de révision fait partie du cours : la recherche globale
            // doit la trouver comme elle trouve son contenu.
            $sql .= ' AND (c.titre LIKE ? OR c.contenu LIKE ? OR c.fiche_revision LIKE ? OR m.nom LIKE ?)';
            array_push($params, "%$terme%", "%$terme%", "%$terme%", "%$terme%");
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
            /*
             * Un dossier ne montre que ce qu'il tient lui-même. Ce que ses
             * sous-dossiers contiennent s'y trouve, pas ici : on descend en
             * les ouvrant, comme dans un explorateur de fichiers. Le compte
             * annoncé à côté de chaque dossier est ainsi celui qu'on y verra.
             */
            $sql .= ' AND c.dossier_id = ?';
            $params[] = $dossierId;
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
        $textes    = array_values((array) ($_POST['texte'] ?? []));
        $origines  = array_values((array) ($_POST['origine'] ?? []));
        $alignements = array_values((array) ($_POST['alignement'] ?? []));
        $listes = array_values((array) ($_POST['liste'] ?? []));
        $niveaux = array_values((array) ($_POST['niveau'] ?? []));
        $titres = array_values((array) ($_POST['titre'] ?? []));
        $connus    = ['gauche', 'centre', 'droite', 'justifie'];

        $entrees = [];
        foreach ($textes as $rang => $texte) {
            if (!is_scalar($texte)) {
                continue;
            }
            $reference = $origines[$rang] ?? '';
            $origine = is_numeric($reference) ? (int) $reference : null;

            // Un alignement inconnu ne dit rien plutôt que n'importe quoi : le
            // paragraphe garde alors celui du document.
            $aligne = $alignements[$rang] ?? '';
            $aligne = is_string($aligne) && in_array($aligne, $connus, true) ? $aligne : null;

            // Une puce, une numérotation, ou rien. Tout autre mot ne dit rien.
            $liste = $listes[$rang] ?? '';
            $liste = in_array($liste, ['puce', 'numero'], true) ? $liste : '';

            // Le premier niveau, ou celui d'une sous-liste. Hors d'une liste,
            // la profondeur ne veut rien dire.
            $niveau = $niveaux[$rang] ?? 0;
            $niveau = $liste === '' || !is_numeric($niveau)
                ? 0
                : max(0, min((int) $niveau, EditionDocument::NIVEAU_MAX));

            // Titre 1, Titre 2, ou du texte ordinaire.
            $titre = $titres[$rang] ?? 0;
            $titre = is_numeric($titre)
                ? max(0, min((int) $titre, EditionDocument::TITRE_MAX))
                : 0;

            // Découpage octet par octet : les fins de ligne sont de l'ASCII,
            // et un motif Unicode échouerait en silence sur un texte mal encodé
            // — au prix d'un paragraphe vidé sans prévenir.
            foreach (preg_split('/\r\n|\r|\n/', (string) $texte) ?: [''] as $ligne) {
                $ligne = rtrim($ligne);
                if ($ligne === '' && $origine === null) {
                    continue;
                }
                $entrees[] = ['origine' => $origine, 'texte' => $ligne,
                    'alignement' => $aligne, 'liste' => $liste, 'niveau' => $niveau,
                    'titre' => $titre];
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
