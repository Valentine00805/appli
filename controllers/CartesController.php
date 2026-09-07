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

    /**
     * Au-delà, une séance devient une corvée : on s'arrête là.
     *
     * Publique parce que la fiche prépare sa propre séance et doit s'arrêter au
     * même endroit — et parce que les vues annoncent le reste à l'utilisateur.
     */
    public const SEANCE_MAX = 40;

    /**
     * Au-delà, une fournée de propositions devient illisible : on s'arrête là.
     *
     * Le plafond vaut pour la fournée entière, et non par cours : en choisir
     * dix ne doit pas produire une page interminable.
     */
    public const PROPOSITIONS_MAX = 200;

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
                    SUM(k.boite >= 5) AS sues,
                    AVG(k.boite) AS boite_moyenne
             FROM cartes k
             JOIN cours c ON c.id = k.cours_id
             LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE k.user_id = ?
             GROUP BY c.id, c.titre, m.nom, m.couleur
             ORDER BY a_revoir DESC, COALESCE(m.nom, \'￿\'), c.titre',
            [$userId]
        );

        // Les cartes de tous les paquets : l'onglet les déplie sur place, il n'y
        // a donc pas d'autre page à charger.
        $cartesParCours = [];
        foreach (Database::all(
            'SELECT * FROM cartes WHERE user_id = ? ORDER BY cours_id, boite, revoir_le, id',
            [$userId]
        ) as $carte) {
            $cartesParCours[(int) $carte['cours_id']][] = $carte;
        }

        // Les documents de chaque cours : le formulaire de fabrication montre
        // ceux du cours choisi, et il les a donc tous sous la main.
        $documentsParCours = [];
        foreach (Database::all(
            'SELECT id, cours_id, nom_origine, mime, taille, pour_fiche
               FROM fichiers WHERE user_id = ? ORDER BY pour_fiche, created_at',
            [$userId]
        ) as $fichier) {
            $documentsParCours[(int) $fichier['cours_id']][] = $fichier;
        }

        Vue::afficher('cartes/index', [
            'paquets'  => $paquets,
            'cartesParCours' => $cartesParCours,
            'documentsParCours' => $documentsParCours,
            // De quoi fabriquer sans passer par la page d'un cours.
            'cours'    => Database::all(
                'SELECT c.id, c.titre, m.nom AS matiere_nom,
                        TRIM(COALESCE(c.contenu, \'\')) <> \'\'        AS a_contenu,
                        TRIM(COALESCE(c.fiche_revision, \'\')) <> \'\' AS a_fiche,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id) AS nb_fichiers
                 FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                 WHERE c.user_id = ?
                 ORDER BY COALESCE(m.nom, \'￿\'), c.titre',
                [$userId]
            ),
            'aRevoir'  => (int) Database::valeur(
                'SELECT COUNT(*) FROM cartes WHERE user_id = ? AND revoir_le <= CURDATE()',
                [$userId]
            ),
            'total'    => (int) Database::valeur('SELECT COUNT(*) FROM cartes WHERE user_id = ?', [$userId]),
            // Les propositions passent par la session : elles ne sont pas encore
            // des cartes, et rien ne doit les enregistrer avant validation.
            'propositions' => (array) (Session::reprendre('propositions_cartes') ?? []),
        ], 'Cartes');
    }

    /** Le paquet d'un cours : ses cartes, et de quoi en ajouter. */
    public function paquet(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $cours = $this->cours($id, $userId);

        $cours['nb_fichiers'] = (int) Database::valeur(
            'SELECT COUNT(*) FROM fichiers WHERE cours_id = ? AND user_id = ?',
            [$id, $userId]
        );

        Vue::afficher('cartes/paquet', [
            'cours'  => $cours,
            'cartes' => Database::all(
                'SELECT * FROM cartes WHERE cours_id = ? AND user_id = ? ORDER BY boite, revoir_le, id',
                [$id, $userId]
            ),
        ], 'Cartes — ' . $cours['titre']);
    }

    // --- Fabriquer des cartes ------------------------------------------------

    /**
     * Propose des cartes tirées de ce que les cours choisis ont de lisible.
     *
     * Rien n'est enregistré : les propositions passent par la session, et
     * l'utilisateur coche celles qu'il garde. Chacune sait de quel cours elle
     * vient, puisqu'une même fournée peut en mêler plusieurs.
     */
    public function proposer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $ids = $this->coursDemandes($userId);
        $sources = $this->sourcesDemandees();

        $muets = [];
        $parCours = [];
        $relus = [];
        $sansSource = 0;

        foreach ($ids as $id) {
            $cours = $this->cours($id, $userId);
            $documents = $this->documentsDemandes($id);

            /*
             * Une liste ouverte puis entièrement décochée dit « pas les
             * documents de ce cours-là ». L'annoncer quand même comme une
             * source relue rendrait le message d'échec faux : on la retire.
             */
            $siennes = $documents === []
                ? array_values(array_diff($sources, ['documents']))
                : $sources;
            if ($siennes === []) {
                $sansSource++;
                continue;
            }
            $relus = array_values(array_unique(array_merge($relus, $siennes)));

            $brutes = array_merge(
                in_array('cours', $siennes, true)
                    ? GenerateurCartes::depuisTexte((string) $cours['contenu'], 'cours') : [],
                in_array('fiche', $siennes, true)
                    ? GenerateurCartes::depuisTexte((string) $cours['fiche_revision'], 'fiche') : [],
                in_array('documents', $siennes, true)
                    ? $this->depuisLesFichiers($id, $userId, $muets, $documents) : []
            );

            $dejaLa = array_column(Database::all(
                'SELECT empreinte FROM cartes WHERE cours_id = ? AND user_id = ?',
                [$id, $userId]
            ), 'empreinte');

            // Le tri se fait cours par cours : deux paquets peuvent porter la
            // même carte sans que l'un empêche l'autre.
            $trouvees = GenerateurCartes::trier($brutes, $dejaLa);
            foreach (array_keys($trouvees) as $rang) {
                $trouvees[$rang]['cours_id'] = $id;
                $trouvees[$rang]['cours_titre'] = (string) $cours['titre'];
            }
            if ($trouvees !== []) {
                $parCours[$id] = $trouvees;
            }
        }

        $laissees = 0;
        $propositions = $this->partagerLePlafond($parCours, $laissees);

        if ($propositions === []) {
            Session::flash('erreur', match (true) {
                $sansSource === count($ids) => 'Aucun document coché : il n\'y a rien à relire.',
                count($ids) > 1 => 'Aucune nouvelle carte dans ' . $this->nommerLesSources($relus)
                    . ', pour aucun des ' . count($ids) . ' cours choisis.',
                default => 'Aucune nouvelle carte dans ' . $this->nommerLesSources($relus)
                    . ' : ce qui était repérable est déjà dans le paquet.',
            });
        }

        $this->signalerLesMuets($muets);

        if ($laissees > 0) {
            Session::flash('erreur', $laissees . ' propositions laissées de côté : une fournée '
                . 'en compte au plus ' . self::PROPOSITIONS_MAX
                . '. Relancez après avoir trié celles-ci.');
        }

        if ($propositions === []) {
            redirect('cartes');
        }

        Session::garder('propositions_cartes', $propositions);
        redirect('cartes');
    }

    /**
     * Les cours désignés par le formulaire de fabrication.
     *
     * @return list<int>
     */
    private function coursDemandes(int $userId): array
    {
        $ids = [];
        foreach ((array) ($_POST['cours'] ?? []) as $brut) {
            $id = entier_ou_null($brut);
            // Chacun est vérifié au passage : un cours qui n'est pas le vôtre
            // ne va pas plus loin, et un doublon ne fait pas le travail deux fois.
            if ($id !== null && !in_array($id, $ids, true)) {
                $this->cours($id, $userId);
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            Session::flash('erreur', 'Choisissez au moins un cours.');
            redirect('cartes');
        }

        return $ids;
    }

    /**
     * Les propositions des différents cours, le plafond partagé entre eux.
     *
     * Chacun se voit accorder une carte à tour de rôle jusqu'à épuisement :
     * sans cela, un cours bavard mangerait tout le plafond et les suivants
     * n'auraient rien. L'ordre des cours est conservé, pour que la page les
     * présente groupés.
     *
     * @param  array<int, list<array>> $parCours
     * @param  int $laissees  ce que le plafond a écarté, pour pouvoir le dire
     * @return list<array>
     */
    private function partagerLePlafond(array $parCours, int &$laissees = 0): array
    {
        $parts = array_fill_keys(array_keys($parCours), 0);
        $place = self::PROPOSITIONS_MAX;

        $encore = true;
        while ($place > 0 && $encore) {
            $encore = false;
            foreach ($parCours as $id => $liste) {
                if ($parts[$id] >= count($liste)) {
                    continue;
                }
                $parts[$id]++;
                $place--;
                $encore = true;
                if ($place === 0) {
                    break;
                }
            }
        }

        $retenues = [];
        $laissees = 0;
        foreach ($parCours as $id => $liste) {
            foreach (array_slice($liste, 0, $parts[$id]) as $proposition) {
                $retenues[] = $proposition;
            }
            $laissees += count($liste) - $parts[$id];
        }

        return $retenues;
    }

    /**
     * Le cours désigné par un formulaire qui n'en vise qu'un.
     *
     * C'est le cas d'une carte écrite à la main : elle rejoint un paquet, et un
     * seul.
     */
    private function coursDemande(int $userId): int
    {
        $id = entier_ou_null($_POST['cours'] ?? null);
        if ($id === null) {
            Session::flash('erreur', 'Choisissez un cours.');
            redirect('cartes');
        }
        $this->cours($id, $userId);

        return $id;
    }

    /**
     * Ce que l'utilisateur demande de relire.
     *
     * Sans rien de coché, on prend tout : c'est ce qu'attend quelqu'un qui
     * clique sans réfléchir, et c'est le cas le plus courant.
     *
     * @return list<string>
     */
    private function sourcesDemandees(): array
    {
        $connues = ['cours', 'fiche', 'documents'];
        $demandees = array_values(array_intersect($connues, (array) ($_POST['sources'] ?? [])));

        return $demandees === [] ? $connues : $demandees;
    }

    /**
     * Les documents que l'utilisateur a retenus, quand il a eu la liste.
     *
     * Elle n'apparaît que si le script a pu l'ouvrir. Sans lui, le formulaire
     * ne dit rien des documents, et « les documents joints » garde son sens
     * d'origine : tous ceux du cours. Le champ caché dit de quels cours
     * viennent les listes ouvertes, ce qui évite de prendre pour un choix une
     * liste restée sur un cours entre-temps décoché.
     *
     * @return ?list<int>  null quand aucune liste n'a été soumise : on prend tout
     */
    private function documentsDemandes(int $coursId): ?array
    {
        $listes = [];
        foreach ((array) ($_POST['documents_de'] ?? []) as $brut) {
            $listes[] = entier_ou_null($brut);
        }
        if (!in_array($coursId, $listes, true)) {
            return null;
        }

        $retenus = [];
        foreach ((array) ($_POST['documents'] ?? []) as $brut) {
            $id = entier_ou_null($brut);
            if ($id !== null) {
                $retenus[] = $id;
            }
        }

        return $retenus;
    }

    /** Les sources, dites comme on les dirait à voix haute. */
    private function nommerLesSources(array $sources): string
    {
        $noms = [];
        foreach ($sources as $source) {
            $noms[] = match ($source) {
                'cours'     => 'le texte du cours',
                'fiche'     => 'la fiche de révision',
                'documents' => 'les documents joints',
                default     => $source,
            };
        }

        if (count($noms) <= 1) {
            return $noms[0] ?? 'ce qui a été choisi';
        }
        $dernier = array_pop($noms);

        return implode(', ', $noms) . ' ni ' . $dernier;
    }

    /**
     * Le texte des pièces jointes que l'application sait lire.
     *
     * Les PDF muets — ceux dont on n'a rien pu tirer, un scan par exemple —
     * sont retenus au passage : mieux vaut le dire que laisser croire que le
     * document ne contenait rien.
     *
     * @param ?list<int> $seulement  les documents retenus ; null pour tous
     */
    private function depuisLesFichiers(
        int $coursId,
        int $userId,
        array &$muets = [],
        ?array $seulement = null
    ): array {
        $cartes = [];

        $fichiers = Database::all(
            'SELECT id, nom_origine, nom_stocke FROM fichiers
              WHERE cours_id = ? AND user_id = ? ORDER BY pour_fiche, created_at',
            [$coursId, $userId]
        );

        foreach ($fichiers as $fichier) {
            // Le tri se fait ici, sur des fichiers déjà rattachés au cours et à
            // son propriétaire : un identifiant inventé ne peut que ne rien
            // désigner, jamais atteindre le document d'un autre.
            if ($seulement !== null && !in_array((int) $fichier['id'], $seulement, true)) {
                continue;
            }

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
    public function retenir(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $gardees = (array) ($_POST['carte'] ?? []);
        $ajoutees = 0;
        $sansReponse = 0;
        // Les cours déjà vérifiés : une fournée en mêle plusieurs, et il serait
        // inutile de redemander le même à la base à chaque carte.
        $connus = [];
        $paquets = [];

        foreach ($gardees as $carte) {
            // Les champs d'une proposition décochée sont envoyés quand même :
            // c'est la case qui dit ce qu'on garde.
            if (($carte['garder'] ?? '') !== '1') {
                continue;
            }
            $question = trim((string) ($carte['question'] ?? ''));
            $reponse = trim((string) ($carte['reponse'] ?? ''));
            if ($question === '') {
                continue;
            }
            // Une question de devoir arrive sans réponse : à l'utilisateur de
            // l'écrire. Sans elle, la carte n'aurait rien à montrer.
            if ($reponse === '') {
                $sansReponse++;
                continue;
            }
            // Chaque proposition porte son cours : c'est lui qui décide du
            // paquet, et il est vérifié comme n'importe quelle entrée.
            $coursId = entier_ou_null($carte['cours'] ?? null);
            if ($coursId === null) {
                continue;
            }
            if (!isset($connus[$coursId])) {
                $this->cours($coursId, $userId);
                $connus[$coursId] = true;
            }

            $retenue = $this->ajouter(
                $coursId,
                $userId,
                $question,
                $reponse,
                (string) ($carte['origine'] ?? 'main'),
                (string) ($carte['source'] ?? '')
            );
            if ($retenue) {
                $ajoutees++;
                $paquets[$coursId] = true;
            }
        }

        $ou = count($paquets) > 1 ? ' aux ' . count($paquets) . ' paquets.' : ' au paquet.';
        Session::flash($ajoutees > 0 ? 'succes' : 'erreur', match (true) {
            $ajoutees > 1  => $ajoutees . ' cartes ajoutées' . $ou,
            $ajoutees === 1 => 'Carte ajoutée au paquet.',
            default        => 'Aucune carte retenue.',
        });

        if ($sansReponse > 0) {
            Session::flash('erreur', $sansReponse > 1
                ? $sansReponse . ' cartes laissées de côté : leur réponse était vide.'
                : 'Une carte laissée de côté : sa réponse était vide.');
        }

        redirect('cartes');
    }

    /** Une carte écrite à la main. */
    public function ajouterUne(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $id = $this->coursDemande($userId);

        $question = trim(post('question'));
        $reponse = trim(post('reponse'));

        if ($question === '' || $reponse === '') {
            Session::flash('erreur', 'Une carte a besoin d\'une question et d\'une réponse.');
        } elseif ($this->ajouter($id, $userId, $question, $reponse, 'main')) {
            Session::flash('succes', 'Carte ajoutée.');
        } else {
            Session::flash('erreur', 'Cette question est déjà dans le paquet.');
        }

        redirect('cartes');
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

    /**
     * Ramène tout un paquet au début du cycle.
     *
     * Les cartes ne bougent pas, ni ce qu'on sait d'elles : seul l'échéancier
     * repart de zéro — toutes en boîte 1, toutes dues aujourd'hui. Le compte des
     * passages est gardé, parce qu'il dit quelque chose qu'aucune boîte ne dit :
     * celle qu'on a ratée six fois n'est pas celle qu'on découvre.
     */
    public function reinitialiser(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $cours = $this->cours($id, $userId);

        // Deux comptes différents : ce qui bougerait, et ce qu'on annoncera.
        $aBouger = (int) Database::valeur(
            'SELECT COUNT(*) FROM cartes
              WHERE cours_id = ? AND user_id = ? AND (boite > 1 OR revoir_le > CURDATE())',
            [$id, $userId]
        );

        if ($aBouger === 0) {
            Session::flash('erreur', 'Ce paquet est déjà au début : rien à remettre à zéro.');
            $this->retourPaquet($id);
        }

        Database::run(
            'UPDATE cartes SET boite = 1, revoir_le = CURDATE() WHERE cours_id = ? AND user_id = ?',
            [$id, $userId]
        );

        // Le message parle du paquet entier, comme le bouton qui l'a déclenché :
        // annoncer « 2 cartes » quand le bouton en promettait 3 sèmerait le doute.
        $total = (int) Database::valeur(
            'SELECT COUNT(*) FROM cartes WHERE cours_id = ? AND user_id = ?',
            [$id, $userId]
        );

        Session::flash('succes', $total > 1
            ? 'Les ' . $total . ' cartes de « ' . $cours['titre'] . ' » sont de nouveau à revoir aujourd\'hui.'
            : 'La carte de « ' . $cours['titre'] . ' » est de nouveau à revoir aujourd\'hui.');
        $this->retourPaquet($id);
    }

    /**
     * Ramène là d'où l'on a cliqué.
     *
     * Le même bouton figure sur la page du paquet et dans le rayon Cartes d'une
     * fiche : renvoyer toujours au paquet ferait quitter la fiche qu'on relisait.
     */
    private function retourPaquet(int $coursId): never
    {
        $retour = $_POST['retour'] ?? '';

        if ($retour === 'fiche') {
            redirect('revision/' . $coursId);
        }
        if ($retour === 'volet') {
            redirect('cours/' . $coursId, ['revision' => 1]);
        }
        if ($retour === 'onglet') {
            redirect('cartes');
        }

        redirect('cours/' . $coursId . '/cartes');
    }

    /**
     * Vide le paquet d'un cours.
     *
     * La suppression est bornée au cours et à son propriétaire : deux
     * conditions, pas une. Ce qui disparaît n'est pas seulement les cartes mais
     * ce qu'on savait d'elles — la boîte où chacune était montée, le nombre de
     * fois qu'on l'a sue. Le compte est donc annoncé avant, et redit après.
     */
    public function viderPaquet(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $cours = $this->cours($id, $userId);

        $combien = (int) Database::valeur(
            'SELECT COUNT(*) FROM cartes WHERE cours_id = ? AND user_id = ?',
            [$id, $userId]
        );

        if ($combien === 0) {
            Session::flash('erreur', 'Ce paquet est déjà vide.');
            redirect('cours/' . $id . '/cartes');
        }

        Database::run('DELETE FROM cartes WHERE cours_id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $combien > 1
            ? 'Les ' . $combien . ' cartes de « ' . $cours['titre'] . ' » ont été supprimées.'
            : 'La carte de « ' . $cours['titre'] . ' » a été supprimée.');
        redirect('cartes');
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

        $paquet = $cours === null ? ['total' => 0, 'somme' => 0] : Database::one(
            'SELECT COUNT(*) AS total, COALESCE(SUM(boite), 0) AS somme
               FROM cartes WHERE cours_id = ? AND user_id = ?',
            [(int) $cours['id'], $userId]
        );

        Vue::afficher('cartes/seance', [
            'cartes' => $cartes,
            'cours'  => $cours,
            // Combien sont dues en tout, pour dire ce que la séance laisse de côté.
            'duesEnTout' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cartes WHERE user_id = ? AND revoir_le <= CURDATE()'
                . ($cours === null ? '' : ' AND cours_id = ?'),
                $cours === null ? [$userId] : [$userId, (int) $cours['id']]
            ),
            'rezeroTotal' => $cours === null ? 0 : (int) $paquet['total'],
            // L'anneau ne vaut que pour un paquet : une séance qui mêle
            // plusieurs cours n'en mesure aucun, et n'en montre donc pas.
            'paquetTotal' => (int) $paquet['total'],
            'paquetSomme' => (int) $paquet['somme'],
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
