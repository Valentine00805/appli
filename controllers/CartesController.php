<?php
declare(strict_types=1);

/**
 * Les cartes de révision : un paquet par cours, et une séance qui les revoit.
 *
 * L'application ne fabrique rien toute seule dans le dos de l'utilisateur : elle
 * propose des cartes tirées du texte du cours, de sa fiche et de ses pièces
 * jointes, et c'est lui qui retient celles qui valent la peine.
 *
 * La révision suit la méthode de Leitner : une carte sue monte d'une boîte et
 * ne revient que plus tard, une carte ratée retombe en boîte 1 et se
 * représente le jour même.
 */
final class CartesController
{
    /** Combien de jours avant de revoir une carte, selon sa boîte. */
    private const DELAIS = [1 => 0, 2 => 1, 3 => 3, 4 => 7, 5 => 21];

    /** Au-delà, une séance devient une corvée : on s'arrête là. */
    private const SEANCE_MAX = 40;

    // --- Voir ses cartes -----------------------------------------------------

    /** Ce qu'il y a à revoir aujourd'hui, tous cours confondus. */
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $paquets = Database::all(
            'SELECT c.id, c.titre, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    COUNT(k.id) AS total,
                    SUM(k.revoir_le <= CURDATE()) AS a_revoir,
                    SUM(k.boite >= 5) AS sues
             FROM cartes k
             JOIN cours c ON c.id = k.cours_id
             LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE k.user_id = ?
             GROUP BY c.id, c.titre, m.nom, m.couleur
             ORDER BY a_revoir DESC, COALESCE(m.nom, \'￿\'), c.titre',
            [$userId]
        );

        Vue::afficher('cartes/index', [
            'paquets'  => $paquets,
            'aRevoir'  => (int) Database::valeur(
                'SELECT COUNT(*) FROM cartes WHERE user_id = ? AND revoir_le <= CURDATE()',
                [$userId]
            ),
            'total'    => (int) Database::valeur('SELECT COUNT(*) FROM cartes WHERE user_id = ?', [$userId]),
        ], 'Cartes');
    }

    /** Le paquet d'un cours : ses cartes, et de quoi en ajouter. */
    public function paquet(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $cours = $this->cours($id, $userId);

        Vue::afficher('cartes/paquet', [
            'cours'  => $cours,
            'cartes' => Database::all(
                'SELECT * FROM cartes WHERE cours_id = ? AND user_id = ? ORDER BY boite, revoir_le, id',
                [$id, $userId]
            ),
            'propositions' => (array) (Session::reprendre('propositions_cartes') ?? []),
        ], 'Cartes — ' . $cours['titre']);
    }

    // --- Fabriquer des cartes ------------------------------------------------

    /**
     * Propose des cartes tirées de tout ce que le cours contient de lisible.
     *
     * Rien n'est enregistré : les propositions passent par la session, et
     * l'utilisateur coche celles qu'il garde.
     */
    public function proposer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $cours = $this->cours($id, $userId);

        $muets = [];
        $brutes = array_merge(
            GenerateurCartes::depuisTexte((string) $cours['contenu'], 'cours'),
            GenerateurCartes::depuisTexte((string) $cours['fiche_revision'], 'fiche'),
            $this->depuisLesFichiers($id, $userId, $muets)
        );

        $dejaLa = array_column(Database::all(
            'SELECT empreinte FROM cartes WHERE cours_id = ? AND user_id = ?',
            [$id, $userId]
        ), 'empreinte');
        $propositions = GenerateurCartes::trier($brutes, $dejaLa);

        if ($propositions === []) {
            Session::flash('erreur', $dejaLa === []
                ? 'Rien à en tirer : les cartes se fabriquent à partir de lignes de la forme « Terme : définition ».'
                : 'Aucune nouvelle carte à proposer : tout ce qui était repérable est déjà dans le paquet.');
        }

        $this->signalerLesMuets($muets);

        if ($propositions === []) {
            redirect('cours/' . $id . '/cartes');
        }

        Session::garder('propositions_cartes', $propositions);
        redirect('cours/' . $id . '/cartes');
    }

    /**
     * Le texte des pièces jointes que l'application sait lire.
     *
     * Les PDF muets — ceux dont on n'a rien pu tirer, un scan par exemple —
     * sont retenus au passage : mieux vaut le dire que laisser croire que le
     * document ne contenait rien.
     */
    private function depuisLesFichiers(int $coursId, int $userId, array &$muets = []): array
    {
        $cartes = [];

        $fichiers = Database::all(
            'SELECT nom_origine, nom_stocke FROM fichiers WHERE cours_id = ? AND user_id = ? ORDER BY created_at',
            [$coursId, $userId]
        );

        foreach ($fichiers as $fichier) {
            $nom = (string) $fichier['nom_origine'];
            $genre = ApercuDocument::genre($nom);
            $chemin = Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
            if ($genre === null || !is_file($chemin)) {
                continue;
            }

            if ($genre === 'pdf') {
                $texte = TextePdf::extraire($chemin);
                if ($texte === null) {
                    $muets[] = $nom;
                    continue;
                }
                $cartes = array_merge($cartes, GenerateurCartes::depuisTexte($texte, 'fichier', $nom));
                continue;
            }

            // Un document illisible ne doit pas faire échouer toute la moisson.
            try {
                $cartes = array_merge($cartes, match ($genre) {
                    'brut'     => GenerateurCartes::depuisTexte(
                        ApercuDocument::texteBrut($chemin)['texte'], 'fichier', $nom
                    ),
                    'document' => GenerateurCartes::depuisTexte(
                        implode("\n", ApercuDocument::paragraphes($chemin, $nom)), 'fichier', $nom
                    ),
                    'tableur'  => GenerateurCartes::depuisTableau(
                        ApercuDocument::tableau($chemin, $nom)['lignes'], $nom
                    ),
                    default    => [],
                });
            } catch (Throwable) {
                continue;
            }
        }

        return $cartes;
    }

    /**
     * Prévient quand un PDF n'a rien donné.
     *
     * Un document scanné n'est qu'une suite d'images : il n'y a pas de texte
     * à lire dedans, et le dire vaut mieux que laisser l'utilisateur croire
     * que l'application l'a ignoré.
     */
    private function signalerLesMuets(array $muets): void
    {
        if ($muets === []) {
            return;
        }

        Session::flash('erreur', count($muets) > 1
            ? 'Aucun texte lisible dans ' . implode(', ', $muets) . ' : ces PDF sont sans doute des scans, c\'est-à-dire des images.'
            : 'Aucun texte lisible dans ' . $muets[0] . ' : ce PDF est sans doute un scan, c\'est-à-dire une image.');
    }

    /** Retient les propositions cochées. */
    public function retenir(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $this->cours($id, $userId);

        $gardees = (array) ($_POST['carte'] ?? []);
        $ajoutees = 0;

        foreach ($gardees as $carte) {
            // Les champs d'une proposition décochée sont envoyés quand même :
            // c'est la case qui dit ce qu'on garde.
            if (($carte['garder'] ?? '') !== '1') {
                continue;
            }
            $question = trim((string) ($carte['question'] ?? ''));
            $reponse = trim((string) ($carte['reponse'] ?? ''));
            if ($question === '' || $reponse === '') {
                continue;
            }
            $ajoutees += $this->ajouter(
                $id,
                $userId,
                $question,
                $reponse,
                (string) ($carte['origine'] ?? 'main'),
                (string) ($carte['source'] ?? '')
            ) ? 1 : 0;
        }

        Session::flash($ajoutees > 0 ? 'succes' : 'erreur', match (true) {
            $ajoutees > 1  => $ajoutees . ' cartes ajoutées au paquet.',
            $ajoutees === 1 => 'Carte ajoutée au paquet.',
            default        => 'Aucune carte retenue.',
        });
        redirect('cours/' . $id . '/cartes');
    }

    /** Une carte écrite à la main. */
    public function ajouterUne(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $this->cours($id, $userId);

        $question = trim(post('question'));
        $reponse = trim(post('reponse'));

        if ($question === '' || $reponse === '') {
            Session::flash('erreur', 'Une carte a besoin d\'une question et d\'une réponse.');
        } elseif ($this->ajouter($id, $userId, $question, $reponse, 'main')) {
            Session::flash('succes', 'Carte ajoutée.');
        } else {
            Session::flash('erreur', 'Cette question est déjà dans le paquet.');
        }

        redirect('cours/' . $id . '/cartes');
    }

    /**
     * Range une carte dans le paquet, sauf si sa question y est déjà.
     *
     * L'unicité est tenue par la base : deux envois simultanés ne peuvent pas
     * créer de doublon, et l'échec attendu ne remonte pas comme une erreur.
     */
    private function ajouter(
        int $coursId,
        int $userId,
        string $question,
        string $reponse,
        string $origine,
        string $source = ''
    ): bool {
        $origines = ['cours', 'fiche', 'fichier', 'main'];

        try {
            Database::run(
                'INSERT INTO cartes (user_id, cours_id, question, reponse, origine, source, empreinte, revoir_le)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())',
                [
                    $userId,
                    $coursId,
                    mb_substr($question, 0, 500),
                    $reponse,
                    in_array($origine, $origines, true) ? $origine : 'main',
                    mb_substr($source, 0, 255),
                    GenerateurCartes::empreinte($question),
                ]
            );
        } catch (PDOException $e) {
            // 23000 : la question existe déjà pour ce cours. Ce n'est pas un incident.
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    // --- Modifier et supprimer ----------------------------------------------

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $carte = $this->carte($id, $userId);

        $question = trim(post('question'));
        $reponse = trim(post('reponse'));

        if ($question === '' || $reponse === '') {
            Session::flash('erreur', 'Une carte a besoin d\'une question et d\'une réponse.');
            redirect('cours/' . $carte['cours_id'] . '/cartes');
        }

        try {
            Database::run(
                'UPDATE cartes SET question = ?, reponse = ?, empreinte = ? WHERE id = ? AND user_id = ?',
                [mb_substr($question, 0, 500), $reponse, GenerateurCartes::empreinte($question), $id, $userId]
            );
            Session::flash('succes', 'Carte modifiée.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('erreur', 'Une autre carte pose déjà cette question.');
        }

        redirect('cours/' . $carte['cours_id'] . '/cartes');
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $carte = $this->carte($id, $userId);

        Database::run('DELETE FROM cartes WHERE id = ? AND user_id = ?', [$id, $userId]);
        Session::flash('succes', 'Carte supprimée.');
        redirect('cours/' . $carte['cours_id'] . '/cartes');
    }

    // --- Réviser -------------------------------------------------------------

    /**
     * Une séance : les cartes dues, une par une.
     *
     * Sans cours précisé, la séance mélange tous les paquets. Les cartes les
     * plus en retard passent les premières.
     */
    public function seance(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $coursId = entier_ou_null($_GET['cours'] ?? null);
        $cours = $coursId !== null ? $this->cours($coursId, $userId) : null;

        $filtre = $cours !== null ? ' AND k.cours_id = ?' : '';
        $params = $cours !== null ? [$userId, $coursId] : [$userId];

        $cartes = Database::all(
            'SELECT k.*, c.titre AS cours_titre
             FROM cartes k JOIN cours c ON c.id = k.cours_id
             WHERE k.user_id = ?' . $filtre . '
               AND k.revoir_le <= CURDATE()
             ORDER BY k.revoir_le, k.boite, RAND()
             LIMIT ' . self::SEANCE_MAX,
            $params
        );

        Vue::afficher('cartes/seance', [
            'cartes' => $cartes,
            'cours'  => $cours,
        ], $cours !== null ? 'Réviser — ' . $cours['titre'] : 'Réviser');
    }

    /**
     * Le verdict sur une carte : sue, elle monte ; ratée, elle repart de zéro.
     *
     * Répond en 204 : la séance se déroule sans quitter la page.
     */
    public function repondre(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $carte = $this->carte($id, $userId);

        $sue = ($_POST['sue'] ?? '') === '1';
        $boite = $sue ? min(5, (int) $carte['boite'] + 1) : 1;
        $delai = self::DELAIS[$boite];

        Database::run(
            'UPDATE cartes
                SET boite = ?, revoir_le = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                    vues = vues + 1, reussies = reussies + ?
              WHERE id = ? AND user_id = ?',
            [$boite, $delai, $sue ? 1 : 0, $id, $userId]
        );

        http_response_code(204);
        exit;
    }

    // --- Le tout-venant ------------------------------------------------------

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }

    /** Un cours de l'utilisateur, ou une page introuvable. */
    private function cours(int $id, int $userId): array
    {
        $cours = Database::one(
            'SELECT c.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur
             FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );
        if ($cours === null) {
            $this->introuvable();
        }

        return $cours;
    }

    /** Une carte de l'utilisateur, ou une page introuvable. */
    private function carte(int $id, int $userId): array
    {
        $carte = Database::one('SELECT * FROM cartes WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($carte === null) {
            $this->introuvable();
        }

        return $carte;
    }
}
