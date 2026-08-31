<?php
declare(strict_types=1);

/**
 * Import d'un relevé bancaire au format CSV.
 *
 * Le parcours se fait en deux temps : on dépose le fichier, on vérifie et on
 * corrige l'aperçu, puis on valide. Rien n'entre en base avant la validation.
 * Les lignes importées deviennent des opérations ordinaires, modifiables et
 * supprimables comme celles saisies à la main.
 */
final class ImportController
{
    /** Taille maximale d'un relevé. */
    private const TAILLE_MAX = 4 * 1024 * 1024;

    private const EXTENSIONS = ['csv', 'txt', 'tsv', 'xlsx'];

    public function formulaire(): void
    {
        Auth::exiger();

        Vue::afficher('budget/import', [
            'dernierImport' => Database::one(
                "SELECT MAX(created_at) AS date, COUNT(*) AS nb
                 FROM operations WHERE user_id = ? AND source = 'import'",
                [Auth::id()]
            ),
        ], 'Importer un relevé');
    }

    /** Première étape : on lit le fichier et on montre ce qu'on a compris. */
    public function analyser(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $fichier = $_FILES['releve'] ?? null;
        if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('erreur', 'Choisissez un fichier à importer.');
            redirect('budget/import');
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            Session::flash('erreur', 'Le transfert du fichier a échoué. Vérifiez sa taille.');
            redirect('budget/import');
        }
        if ($fichier['size'] > self::TAILLE_MAX) {
            Session::flash('erreur', 'Fichier trop volumineux (maximum ' . taille_lisible(self::TAILLE_MAX) . ').');
            redirect('budget/import');
        }

        $extension = strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            Session::flash('erreur',
                'Format non reconnu. Déposez un relevé en CSV, ou un ancien classeur au format .xlsx.');
            redirect('budget/import');
        }

        if ($extension === 'xlsx') {
            $this->analyserClasseur((string) $fichier['tmp_name'], (string) $fichier['name']);
        }

        $contenu = file_get_contents((string) $fichier['tmp_name']);
        if ($contenu === false || trim($contenu) === '') {
            Session::flash('erreur', 'Le fichier est vide ou illisible.');
            redirect('budget/import');
        }

        $analyse = ReleveCsv::analyser($contenu);
        if ($analyse['lignes'] === []) {
            Session::flash('erreur',
                'Aucune ligne exploitable trouvée. Le fichier est-il bien un relevé au format CSV ?');
            redirect('budget/import');
        }

        // Le relevé attend dans la session : rien n'est écrit en base à ce stade.
        $_SESSION['_import'] = [
            'nom'        => mb_substr((string) $fichier['name'], 0, 120),
            'separateur' => $analyse['separateur'],
            'entetes'    => $analyse['entetes'],
            'lignes'     => $analyse['lignes'],
            'mapping'    => $analyse['mapping'],
        ];

        redirect('budget/import/apercu');
    }

    /**
     * Lecture d'un ancien classeur tenu à la main. La structure y est différente
     * d'un relevé bancaire : rubriques déduites des lignes de total, parts
     * partagées, blocs mis hors total. On la traite à part.
     */
    private function analyserClasseur(string $chemin, string $nom): never
    {
        try {
            $analyse = ReleveExcel::analyser($chemin);
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
            redirect('budget/import');
        }

        if ($analyse['operations'] === []) {
            Session::flash('erreur',
                'Aucune dépense reconnue. La feuille doit comporter une date, un libellé et un montant '
                . 'dans les trois premières colonnes.');
            redirect('budget/import');
        }

        $_SESSION['_import'] = [
            'type'       => 'classeur',
            'nom'        => mb_substr($nom, 0, 120),
            'operations' => $analyse['operations'],
            'totaux'     => $analyse['totaux'],
            'ignorees'   => $analyse['ignorees'],
        ];
        redirect('budget/import/apercu');
    }

    /** Aperçu d'un ancien classeur : rubriques à rattacher, lignes à cocher. */
    private function apercuClasseur(array $releve, int $userId): never
    {
        $categories = $this->categories($userId);
        $operations = $releve['operations'];

        // Empreintes déjà connues, pour ne pas réimporter deux fois le même mois.
        $pourEmpreinte = array_map(
            static fn (array $o): array => ['date' => $o['date'], 'montant' => $o['montant'], 'libelle' => $o['libelle']],
            $operations
        );
        $connues = $this->empreintesConnues($userId, $pourEmpreinte);

        $rubriques = [];
        $lignes = [];
        $parMois = [];

        foreach ($operations as $i => $o) {
            $empreinte = ReleveCsv::empreinte($o['date'], (float) $o['montant'], $o['libelle']);
            $doublon = isset($connues[$empreinte]);

            $rubrique = $o['rubrique'] ?? '';
            if ($rubrique !== '' && !isset($rubriques[$rubrique])) {
                $rubriques[$rubrique] = [
                    'nom'       => $rubrique,
                    'categorie' => $this->categorieProche($rubrique, $categories),
                    'nb'        => 0,
                ];
            }
            if ($rubrique !== '') {
                $rubriques[$rubrique]['nb']++;
            }

            $lignes[] = $o + ['index' => $i, 'doublon' => $doublon];

            if ($o['statut'] !== 'hors_total' && !$doublon) {
                $mois = substr($o['date'], 0, 7);
                $parMois[$mois] = ($parMois[$mois] ?? 0) + ($o['part'] ?? $o['montant']);
            }
        }
        ksort($parMois);

        // Comparaison avec les totaux inscrits dans la feuille.
        $controles = [];
        foreach ($parMois as $mois => $somme) {
            $nom = self::sansAccents(strtolower(nom_mois((int) substr($mois, 5, 2))));
            $sien = $releve['totaux'][$nom] ?? null;
            $controles[] = [
                'mois'      => $mois,
                'recalcule' => $somme,
                'feuille'   => $sien,
                'ecart'     => $sien === null ? null : round($somme - $sien, 2),
            ];
        }

        Vue::afficher('budget/import_apercu_classeur', [
            'nom'        => $releve['nom'],
            'lignes'     => $lignes,
            'rubriques'  => $rubriques,
            'categories' => $categories,
            'controles'  => $controles,
            'ignorees'   => (int) $releve['ignorees'],
            'doublons'   => count(array_filter($lignes, static fn (array $l): bool => $l['doublon'])),
            'personnes'  => RemboursementsController::personnes($userId),
        ], 'Aperçu du classeur');
        exit;
    }

    /** Rapproche une rubrique du classeur d'une catégorie existante. */
    private function categorieProche(string $rubrique, array $categories): ?int
    {
        $cible = self::sansAccents(mb_strtolower($rubrique));
        foreach ($categories as $c) {
            if ($c['sens'] !== 'depense') {
                continue;
            }
            $nom = self::sansAccents(mb_strtolower((string) $c['nom']));
            if ($nom === $cible || str_contains($cible, $nom) || str_contains($nom, $cible)) {
                return (int) $c['id'];
            }
        }
        return null;
    }

    private static function sansAccents(string $texte): string
    {
        return strtr(trim($texte), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    /** Deuxième étape : vérification, correction du mapping, choix des lignes. */
    public function apercu(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $releve = $_SESSION['_import'] ?? null;
        if (!is_array($releve)) {
            Session::flash('info', 'Commencez par déposer un fichier.');
            redirect('budget/import');
        }

        if (($releve['type'] ?? '') === 'classeur') {
            $this->apercuClasseur($releve, $userId);
        }

        // Un ajustement du mapping renvoie ici en GET.
        $mapping = $releve['mapping'];
        foreach (['mode', 'date', 'libelle', 'montant', 'debit', 'credit'] as $cle) {
            if (isset($_GET[$cle]) && $_GET[$cle] !== '') {
                $mapping[$cle] = $cle === 'mode' ? (string) $_GET[$cle] : (int) $_GET[$cle];
            } elseif (isset($_GET['ajuste'])) {
                $mapping[$cle] = $cle === 'mode' ? 'montant' : null;
            }
        }
        if (isset($_GET['ajuste'])) {
            $mapping['mode'] = in_array($_GET['mode'] ?? '', ['montant', 'debit_credit'], true)
                ? (string) $_GET['mode'] : 'montant';
            $_SESSION['_import']['mapping'] = $mapping;
        }

        $operations = ReleveCsv::extraire($releve['lignes'], $mapping);
        $empreintesConnues = $this->empreintesConnues($userId, $operations);
        $categories = $this->categories($userId);

        $lignes = [];
        foreach ($operations as $i => $op) {
            $valide = $op['date'] !== null && $op['montant'] !== null && $op['libelle'] !== '';
            $empreinte = $valide
                ? ReleveCsv::empreinte($op['date'], (float) $op['montant'], $op['libelle'])
                : null;

            $lignes[] = $op + [
                'index'     => $i,
                'valide'    => $valide,
                'doublon'   => $empreinte !== null && isset($empreintesConnues[$empreinte]),
                'empreinte' => $empreinte,
                'categorie' => $valide ? $this->deviner($op['libelle'], $op['sens'], $categories) : null,
            ];
        }

        Vue::afficher('budget/import_apercu', [
            'nom'        => $releve['nom'],
            'entetes'    => $releve['entetes'],
            'colonnes'   => $this->nomsDeColonnes($releve),
            'mapping'    => $mapping,
            'lignes'     => $lignes,
            'categories' => $categories,
            'valides'    => count(array_filter($lignes, static fn (array $l): bool => $l['valide'] && !$l['doublon'])),
            'doublons'   => count(array_filter($lignes, static fn (array $l): bool => $l['doublon'])),
            'invalides'  => count(array_filter($lignes, static fn (array $l): bool => !$l['valide'])),
        ], 'Aperçu de l\'import');
    }

    /** Troisième étape : création des opérations retenues. */
    public function confirmer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $releve = $_SESSION['_import'] ?? null;
        if (!is_array($releve)) {
            Session::flash('erreur', 'La session d\'import a expiré. Redéposez le fichier.');
            redirect('budget/import');
        }

        $retenues = array_map('intval', (array) ($_POST['ligne'] ?? []));
        if ($retenues === []) {
            Session::flash('erreur', 'Aucune ligne sélectionnée.');
            redirect('budget/import/apercu');
        }

        if (($releve['type'] ?? '') === 'classeur') {
            $this->confirmerClasseur($releve, $userId, $retenues);
        }

        $mapping = $releve['mapping'];
        $operations = ReleveCsv::extraire($releve['lignes'], $mapping);
        $categoriesSaisies = (array) ($_POST['categorie'] ?? []);
        $categoriesValides = array_column($this->categories($userId), 'sens', 'id');

        $importees = 0;
        $ignorees = 0;

        foreach ($retenues as $index) {
            $op = $operations[$index] ?? null;
            if ($op === null || $op['date'] === null || $op['montant'] === null || $op['libelle'] === '') {
                $ignorees++;
                continue;
            }

            $empreinte = ReleveCsv::empreinte($op['date'], (float) $op['montant'], $op['libelle']);
            $existe = Database::valeur(
                'SELECT id FROM operations WHERE user_id = ? AND empreinte = ? LIMIT 1',
                [$userId, $empreinte]
            );
            if ($existe !== null) {
                $ignorees++;
                continue;
            }

            // La catégorie choisie doit appartenir à l'utilisateur et coller au sens.
            $categorieId = entier_ou_null($categoriesSaisies[$index] ?? null);
            if ($categorieId !== null
                && (($categoriesValides[$categorieId] ?? null) !== $op['sens'])) {
                $categorieId = null;
            }

            Database::run(
                "INSERT INTO operations
                   (user_id, categorie_id, libelle, montant, sens, date_operation, source, empreinte)
                 VALUES (?, ?, ?, ?, ?, ?, 'import', ?)",
                [
                    $userId,
                    $categorieId,
                    $op['libelle'],
                    number_format((float) $op['montant'], 2, '.', ''),
                    $op['sens'],
                    $op['date'],
                    $empreinte,
                ]
            );
            $importees++;
        }

        unset($_SESSION['_import']);

        Session::flash('succes', sprintf(
            '%d opération%s importée%s.%s',
            $importees,
            $importees > 1 ? 's' : '',
            $importees > 1 ? 's' : '',
            $ignorees > 0 ? ' ' . $ignorees . ' ligne' . ($ignorees > 1 ? 's ignorées' : ' ignorée')
                . ' (doublon ou données incomplètes).' : ''
        ));

        // On atterrit sur le mois de la première opération importée.
        $premiere = null;
        foreach ($retenues as $index) {
            if (isset($operations[$index]['date']) && $operations[$index]['date'] !== null) {
                $premiere = $operations[$index]['date'];
                break;
            }
        }
        redirect('budget', $premiere !== null ? ['mois' => substr($premiere, 0, 7)] : []);
    }

    /** Enregistrement des lignes retenues d'un ancien classeur. */
    private function confirmerClasseur(array $releve, int $userId, array $retenues): never
    {
        $operations = $releve['operations'];
        $mapping = (array) ($_POST['rubrique'] ?? []);
        $personne = mb_substr(post('rembourse_par'), 0, 80) ?: null;
        $dejaRegle = isset($_POST['deja_rembourse']);
        $categoriesValides = array_column($this->categories($userId), 'sens', 'id');

        $creees = [];
        $importees = 0;
        $ignorees = 0;

        foreach ($retenues as $index) {
            $o = $operations[$index] ?? null;
            if ($o === null) {
                $ignorees++;
                continue;
            }

            $empreinte = ReleveCsv::empreinte($o['date'], (float) $o['montant'], $o['libelle']);
            if (Database::valeur(
                'SELECT id FROM operations WHERE user_id = ? AND empreinte = ? LIMIT 1',
                [$userId, $empreinte]
            ) !== null) {
                $ignorees++;
                continue;
            }

            // Rattachement de la rubrique : catégorie existante, création, ou rien.
            $categorieId = null;
            $rubrique = (string) ($o['rubrique'] ?? '');
            if ($rubrique !== '') {
                $choix = (string) ($mapping[$rubrique] ?? '');
                if ($choix === 'creer') {
                    $categorieId = $creees[$rubrique] ??= $this->creerCategorie($userId, $rubrique);
                } elseif ($choix !== '' && $choix !== 'aucune') {
                    $id = (int) $choix;
                    if (($categoriesValides[$id] ?? null) === 'depense') {
                        $categorieId = $id;
                    }
                }
            }

            $statut = $o['statut'] === 'hors_total'
                ? 'hors_total'
                : ($dejaRegle ? 'rembourse' : 'a_reclamer');

            Database::run(
                "INSERT INTO operations
                   (user_id, categorie_id, libelle, montant, sens, date_operation, note, source, empreinte,
                    a_rembourser, part_rembourser, rembourse_par, statut_remb, date_remboursement)
                 VALUES (?, ?, ?, ?, 'depense', ?, ?, 'import', ?, 1, ?, ?, ?, ?)",
                [
                    $userId,
                    $categorieId,
                    $o['libelle'],
                    number_format((float) $o['montant'], 2, '.', ''),
                    $o['date'],
                    $o['note'] ?? null,
                    $empreinte,
                    $o['part'] === null ? null : number_format((float) $o['part'], 2, '.', ''),
                    $personne,
                    $statut,
                    $statut === 'rembourse' ? $o['date'] : null,
                ]
            );
            $importees++;
        }

        unset($_SESSION['_import']);

        Session::flash('succes', sprintf(
            '%d dépense%s reprise%s du classeur.%s%s',
            $importees,
            $importees > 1 ? 's' : '',
            $importees > 1 ? 's' : '',
            $creees !== [] ? ' ' . count($creees) . ' catégorie' . (count($creees) > 1 ? 's créées' : ' créée') . '.' : '',
            $ignorees > 0 ? ' ' . $ignorees . ' ligne' . ($ignorees > 1 ? 's ignorées' : ' ignorée') . '.' : ''
        ));
        redirect('budget/remboursements');
    }

    private function creerCategorie(int $userId, string $nom): int
    {
        $existe = Database::valeur(
            "SELECT id FROM categories_budget WHERE user_id = ? AND nom = ? AND sens = 'depense'",
            [$userId, $nom]
        );
        if ($existe !== null) {
            return (int) $existe;
        }

        $position = (int) Database::valeur(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM categories_budget WHERE user_id = ? AND sens = 'depense'",
            [$userId]
        );
        Database::run(
            "INSERT INTO categories_budget (user_id, nom, icone, couleur, sens, position)
             VALUES (?, ?, '💶', ?, 'depense', ?)",
            [$userId, mb_substr($nom, 0, 60), BudgetController::PALETTE[$position % count(BudgetController::PALETTE)], $position]
        );
        return Database::dernierId();
    }

    public function abandonner(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        unset($_SESSION['_import']);
        Session::flash('info', 'Import abandonné. Rien n\'a été enregistré.');
        redirect('budget/import');
    }

    // --- Outils internes -----------------------------------------------------

    private function categories(int $userId): array
    {
        return Database::all(
            'SELECT id, nom, icone, couleur, sens FROM categories_budget WHERE user_id = ? ORDER BY sens DESC, position, nom',
            [$userId]
        );
    }

    /**
     * Propose une catégorie en rapprochant le libellé du relevé du nom des
     * catégories. « CARTE LECLERC » tombe sur « Courses » si le mot y figure.
     */
    private function deviner(string $libelle, string $sens, array $categories): ?int
    {
        $texte = mb_strtolower($libelle);
        foreach ($categories as $c) {
            if ($c['sens'] !== $sens) {
                continue;
            }
            $nom = mb_strtolower((string) $c['nom']);
            if (mb_strlen($nom) >= 4 && str_contains($texte, $nom)) {
                return (int) $c['id'];
            }
        }
        return null;
    }

    /** Empreintes déjà présentes en base parmi celles du relevé. */
    private function empreintesConnues(int $userId, array $operations): array
    {
        $empreintes = [];
        foreach ($operations as $op) {
            if ($op['date'] !== null && $op['montant'] !== null && $op['libelle'] !== '') {
                $empreintes[] = ReleveCsv::empreinte($op['date'], (float) $op['montant'], $op['libelle']);
            }
        }
        if ($empreintes === []) {
            return [];
        }

        $empreintes = array_values(array_unique($empreintes));
        $trous = implode(',', array_fill(0, count($empreintes), '?'));
        $lignes = Database::all(
            "SELECT empreinte FROM operations WHERE user_id = ? AND empreinte IN ($trous)",
            array_merge([$userId], $empreintes)
        );
        return array_flip(array_column($lignes, 'empreinte'));
    }

    /** Intitulés à afficher dans les listes de choix de colonnes. */
    private function nomsDeColonnes(array $releve): array
    {
        $largeur = $releve['lignes'] === [] ? 0 : max(array_map('count', $releve['lignes']));
        $noms = [];
        for ($i = 0; $i < $largeur; $i++) {
            $entete = trim((string) ($releve['entetes'][$i] ?? ''));
            $exemple = trim((string) ($releve['lignes'][0][$i] ?? ''));
            $noms[$i] = $entete !== ''
                ? $entete
                : 'Colonne ' . ($i + 1) . ($exemple !== '' ? ' (' . mb_substr($exemple, 0, 18) . ')' : '');
        }
        return $noms;
    }
}
