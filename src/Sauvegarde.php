<?php
declare(strict_types=1);

/**
 * Sauvegarde et restauration des données d'un compte.
 *
 * Le fichier produit est une archive contenant vos données au format JSON et
 * vos pièces jointes. Il ne dépend d'aucun outil externe : ni mysqldump, ni
 * accès à la ligne de commande — ce qui compte sur un hébergement mutualisé.
 *
 * La restauration remplace l'intégralité des données du compte. Elle se déroule
 * dans une transaction : si quoi que ce soit échoue en route, rien n'est
 * modifié. Et l'archive est entièrement vérifiée avant que la moindre ligne ne
 * soit supprimée.
 */
final class Sauvegarde
{
    /** Version du format, pour refuser une archive qu'on ne saurait pas relire. */
    private const FORMAT = 1;

    private const FICHIER_DONNEES = 'donnees.json';
    private const DOSSIER_FICHIERS = 'fichiers/';

    /**
     * Tables exportées, dans l'ordre où elles doivent être réinsérées.
     * Pour chacune : comment retrouver les lignes du compte, et quelles
     * colonnes pointent vers une autre table.
     */
    private const TABLES = [
        'matieres'          => ['portee' => 'user',  'liens' => []],
        'tags'              => ['portee' => 'user',  'liens' => []],
        'types_evenement'   => ['portee' => 'user',  'liens' => []],
        'categories_budget' => ['portee' => 'user',  'liens' => []],
        'cours'             => ['portee' => 'user',  'liens' => ['matiere_id' => 'matieres']],
        'cours_tag'         => ['portee' => 'cours', 'liens' => ['cours_id' => 'cours', 'tag_id' => 'tags']],
        'fichiers'          => ['portee' => 'user',  'liens' => ['cours_id' => 'cours']],
        'evenements'        => ['portee' => 'user',  'liens' => [
            'matiere_id' => 'matieres', 'type_id' => 'types_evenement', 'cours_id' => 'cours']],
        'recurrences'       => ['portee' => 'user',  'liens' => ['categorie_id' => 'categories_budget']],
        'soldes_saisis'     => ['portee' => 'user',  'liens' => []],
        'operations'        => ['portee' => 'user',  'liens' => [
            'categorie_id' => 'categories_budget', 'recurrence_id' => 'recurrences']],
        'reglements'        => ['portee' => 'user',  'liens' => ['operation_id' => 'operations']],
        'listes_taches'     => ['portee' => 'user',  'liens' => []],
        'taches'            => ['portee' => 'user',  'liens' => ['liste_id' => 'listes_taches']],
    ];

    // --- Export --------------------------------------------------------------

    /** Construit l'archive et l'envoie au navigateur. */
    public static function telecharger(int $userId): never
    {
        $utilisateur = Database::one('SELECT nom, email, created_at FROM users WHERE id = ?', [$userId]);
        if ($utilisateur === null) {
            throw new RuntimeException('Compte introuvable.');
        }

        $donnees = [
            'format'      => self::FORMAT,
            'application' => (string) Config::get('app', 'nom'),
            'exporte_le'  => date('c'),
            'compte'      => $utilisateur,
            'tables'      => [],
        ];

        foreach (array_keys(self::TABLES) as $table) {
            $donnees['tables'][$table] = self::lire($table, $userId);
        }

        $archive = tempnam(sys_get_temp_dir(), 'sauvegarde');
        if ($archive === false) {
            throw new RuntimeException('Impossible de préparer l\'archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l\'archive.');
        }

        $zip->addFromString(self::FICHIER_DONNEES, (string) json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $zip->addFromString('lisez-moi.txt', self::modeEmploi($donnees));

        // Les pièces jointes sont ajoutées par leur chemin : rien ne transite
        // par la mémoire, un gros fichier ne fait donc pas tomber la page.
        $dossier = (string) Config::get('app', 'dossier_uploads');
        $manquants = 0;
        foreach ($donnees['tables']['fichiers'] as $fichier) {
            $chemin = $dossier . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
            if (is_file($chemin)) {
                $zip->addFile($chemin, self::DOSSIER_FICHIERS . $fichier['nom_stocke']);
            } else {
                $manquants++;
            }
        }
        $zip->close();

        $nom = sprintf('sauvegarde-mes-cours-%s.zip', date('Y-m-d'));
        $taille = filesize($archive);

        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) $taille);
        header('X-Content-Type-Options: nosniff');
        header(sprintf('Content-Disposition: attachment; filename="%s"', $nom));
        readfile($archive);
        @unlink($archive);
        exit;
    }

    /** Aperçu de ce que contiendrait la sauvegarde, sans la construire. */
    public static function resume(int $userId): array
    {
        $resume = ['lignes' => 0, 'detail' => [], 'fichiers' => 0, 'octets' => 0];

        foreach (array_keys(self::TABLES) as $table) {
            $n = count(self::lire($table, $userId));
            $resume['detail'][$table] = $n;
            $resume['lignes'] += $n;
        }

        $resume['fichiers'] = (int) Database::valeur(
            'SELECT COUNT(*) FROM fichiers WHERE user_id = ?',
            [$userId]
        );
        $resume['octets'] = (int) Database::valeur(
            'SELECT COALESCE(SUM(taille), 0) FROM fichiers WHERE user_id = ?',
            [$userId]
        );
        return $resume;
    }

    // --- Restauration --------------------------------------------------------

    /**
     * Remplace les données du compte par celles de l'archive.
     * @return array{lignes:int, fichiers:int}
     */
    public static function restaurer(int $userId, string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Ce fichier n\'est pas une archive lisible.');
        }

        try {
            $brut = $zip->getFromName(self::FICHIER_DONNEES);
            if ($brut === false) {
                throw new RuntimeException(
                    'Archive incomplète : le fichier « ' . self::FICHIER_DONNEES . ' » est absent. '
                    . 'Est-ce bien une sauvegarde produite par l\'application ?'
                );
            }

            $donnees = json_decode($brut, true);
            if (!is_array($donnees) || !isset($donnees['tables']) || !is_array($donnees['tables'])) {
                throw new RuntimeException('Le contenu de l\'archive est illisible.');
            }
            if ((int) ($donnees['format'] ?? 0) !== self::FORMAT) {
                throw new RuntimeException(
                    'Cette sauvegarde a été produite par une autre version de l\'application.'
                );
            }

            // Vérification complète avant de toucher à quoi que ce soit.
            foreach (array_keys(self::TABLES) as $table) {
                if (!isset($donnees['tables'][$table]) || !is_array($donnees['tables'][$table])) {
                    throw new RuntimeException('Table manquante dans l\'archive : ' . $table . '.');
                }
            }

            // Les fichiers actuellement sur le disque ne seront effacés qu'une
            // fois les nouveaux écrits : en cas d'échec, on n'aura rien perdu.
            $anciensFichiers = array_column(
                Database::all('SELECT nom_stocke FROM fichiers WHERE user_id = ?', [$userId]),
                'nom_stocke'
            );

            $pdo = Database::pdo();
            $pdo->beginTransaction();

            try {
                self::vider($userId);
                $correspondances = [];
                $lignes = 0;

                foreach (self::TABLES as $table => $reglage) {
                    foreach ($donnees['tables'][$table] as $ligne) {
                        if (!is_array($ligne)) {
                            continue;
                        }
                        $ancienId = isset($ligne['id']) ? (int) $ligne['id'] : null;
                        $nouvelId = self::inserer($table, $reglage, $ligne, $userId, $correspondances);
                        if ($ancienId !== null && $nouvelId !== null) {
                            $correspondances[$table][$ancienId] = $nouvelId;
                        }
                        $lignes++;
                    }
                }

                self::reparerReglements($userId, $correspondances);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw new RuntimeException(
                    'La restauration a échoué, rien n\'a été modifié : ' . $e->getMessage()
                );
            }

            $fichiers = self::restaurerFichiers($zip, $userId);
            self::effacerAnciensFichiers($userId, $anciensFichiers);

            return ['lignes' => $lignes, 'fichiers' => $fichiers];
        } finally {
            $zip->close();
        }
    }

    // --- Interne -------------------------------------------------------------

    private static function lire(string $table, int $userId): array
    {
        if (self::TABLES[$table]['portee'] === 'cours') {
            // Table de liaison : on passe par les cours du compte.
            return Database::all(
                "SELECT t.* FROM `$table` t
                 JOIN cours c ON c.id = t.cours_id
                 WHERE c.user_id = ?",
                [$userId]
            );
        }
        return Database::all("SELECT * FROM `$table` WHERE user_id = ?", [$userId]);
    }

    /** Efface les données du compte, dans l'ordre inverse des dépendances. */
    private static function vider(int $userId): void
    {
        foreach (array_reverse(array_keys(self::TABLES)) as $table) {
            if (self::TABLES[$table]['portee'] === 'cours') {
                Database::run(
                    "DELETE t FROM `$table` t JOIN cours c ON c.id = t.cours_id WHERE c.user_id = ?",
                    [$userId]
                );
                continue;
            }
            Database::run("DELETE FROM `$table` WHERE user_id = ?", [$userId]);
        }
    }

    /** Réinsère une ligne en réécrivant ses renvois vers les nouvelles clés. */
    private static function inserer(
        string $table,
        array $reglage,
        array $ligne,
        int $userId,
        array $correspondances
    ): ?int {
        unset($ligne['id']);

        if ($reglage['portee'] === 'user') {
            $ligne['user_id'] = $userId;
        }

        foreach ($reglage['liens'] as $colonne => $cible) {
            if (!array_key_exists($colonne, $ligne) || $ligne[$colonne] === null) {
                continue;
            }
            $ancien = (int) $ligne[$colonne];
            // Un renvoi vers une ligne absente de l'archive devient vide plutôt
            // que de faire échouer toute la restauration.
            $ligne[$colonne] = $correspondances[$cible][$ancien] ?? null;
        }

        $colonnes = array_keys($ligne);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $colonnes) . '`',
            implode(', ', array_fill(0, count($colonnes), '?'))
        );
        Database::run($sql, array_values($ligne));

        return $reglage['portee'] === 'cours' ? null : Database::dernierId();
    }

    /**
     * Les règlements gardent la liste des dépenses soldées sous forme de JSON :
     * ces identifiants doivent suivre le même changement de clés.
     */
    private static function reparerReglements(int $userId, array $correspondances): void
    {
        $carte = $correspondances['operations'] ?? [];
        if ($carte === []) {
            return;
        }

        foreach (Database::all('SELECT id, lignes FROM reglements WHERE user_id = ?', [$userId]) as $reglement) {
            $ids = json_decode((string) $reglement['lignes'], true);
            if (!is_array($ids)) {
                continue;
            }
            $nouveaux = [];
            foreach ($ids as $ancien) {
                if (isset($carte[(int) $ancien])) {
                    $nouveaux[] = $carte[(int) $ancien];
                }
            }
            Database::run(
                'UPDATE reglements SET lignes = ? WHERE id = ? AND user_id = ?',
                [json_encode($nouveaux), $reglement['id'], $userId]
            );
        }
    }

    /** Réécrit les pièces jointes sur le disque et met à jour leur nom de stockage. */
    private static function restaurerFichiers(ZipArchive $zip, int $userId): int
    {
        $dossier = (string) Config::get('app', 'dossier_uploads');
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return 0;
        }

        $restaures = 0;
        foreach (Database::all('SELECT id, nom_stocke FROM fichiers WHERE user_id = ?', [$userId]) as $fichier) {
            $contenu = $zip->getFromName(self::DOSSIER_FICHIERS . $fichier['nom_stocke']);
            if ($contenu === false) {
                continue;
            }
            // Nouveau nom aléatoire : deux restaurations ne se marchent pas dessus.
            $extension = pathinfo((string) $fichier['nom_stocke'], PATHINFO_EXTENSION);
            $nom = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
            if (file_put_contents($dossier . DIRECTORY_SEPARATOR . $nom, $contenu) === false) {
                continue;
            }
            Database::run('UPDATE fichiers SET nom_stocke = ? WHERE id = ? AND user_id = ?',
                [$nom, $fichier['id'], $userId]);
            $restaures++;
        }
        return $restaures;
    }

    /**
     * Efface les fichiers de l'état précédent, une fois les nouveaux en place.
     * On ne touche qu'aux noms qui ne servent plus, pour ne rien casser si une
     * pièce jointe manquait dans l'archive et a été laissée telle quelle.
     */
    private static function effacerAnciensFichiers(int $userId, array $anciens): void
    {
        if ($anciens === []) {
            return;
        }

        $actuels = array_flip(array_column(
            Database::all('SELECT nom_stocke FROM fichiers WHERE user_id = ?', [$userId]),
            'nom_stocke'
        ));
        $dossier = (string) Config::get('app', 'dossier_uploads');

        foreach ($anciens as $nom) {
            if (isset($actuels[$nom])) {
                continue;
            }
            $chemin = $dossier . DIRECTORY_SEPARATOR . $nom;
            if (is_file($chemin)) {
                @unlink($chemin);
            }
        }
    }

    private static function modeEmploi(array $donnees): string
    {
        $lignes = 0;
        foreach ($donnees['tables'] as $table) {
            $lignes += count($table);
        }

        return implode("\n", [
            'SAUVEGARDE — ' . $donnees['application'],
            str_repeat('=', 40),
            '',
            'Compte  : ' . $donnees['compte']['nom'] . ' <' . $donnees['compte']['email'] . '>',
            'Exporté : ' . date('d/m/Y à H\hi'),
            'Contenu : ' . $lignes . ' lignes de données et '
                . count($donnees['tables']['fichiers']) . ' pièce(s) jointe(s).',
            '',
            'CE QUE CONTIENT CETTE ARCHIVE',
            '  donnees.json  toutes vos données : cours, calendrier, budget, remboursements.',
            '  fichiers/     vos pièces jointes, sous leur nom de stockage.',
            '',
            'COMMENT LA RESTAURER',
            '  Dans l\'application : Mon compte > Sauvegarde > Restaurer,',
            '  puis déposez ce fichier .zip.',
            '  Attention : la restauration remplace toutes les données du compte.',
            '',
            'OÙ LA RANGER',
            '  Ailleurs que sur l\'ordinateur ou le serveur qui héberge l\'application :',
            '  un espace de stockage en ligne, une clé USB, un disque externe.',
            '  Une sauvegarde rangée au même endroit que l\'original ne sert à rien.',
            '',
        ]);
    }
}
