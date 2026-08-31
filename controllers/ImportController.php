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

    private const EXTENSIONS = ['csv', 'txt', 'tsv'];

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
                'Format non reconnu. Exportez votre relevé en CSV depuis le site de votre banque.');
            redirect('budget/import');
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
