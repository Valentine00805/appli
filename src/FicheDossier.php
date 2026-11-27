<?php
declare(strict_types=1);

/**
 * La fiche de révision d'un dossier de « Mes cours » : ce qu'il faut retenir de tout le dossier, rattaché au dossier lui-même.
 *
 * Elle ne crée aucun cours et n'en transforme aucun : c'est un texte (mis en forme) de plus sur le dossier, à côté de ce qu'il contient. La page
 * montre ce contenu — les cours, les fichiers, les sous-dossiers, et leurs propres fiches — pour réviser « le dossier complet » d'un coup d'œil.
 *
 * Sans la migration « dossier-fiche », la fonction n'existe pas : le site tourne comme avant.
 */
final class FicheDossier
{
    /** La migration est-elle passée ? */
    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT fiche_revision, fiche_modifiee_le FROM dossiers LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Les éléments rattachés (fichiers, liens, renvois, évènements) sont-ils possibles ? Il faut la seconde migration, « dossier-fiche-elements » :
     * sans elle, la fiche du dossier ne montre que son texte et ce que le dossier contient.
     */
    public static function elementsDisponibles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT dossier_id FROM fichiers LIMIT 1');
                Database::valeur('SELECT dossier_id FROM fiche_elements LIMIT 1');
                $ok = self::disponible();
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Les fichiers et images joints à la fiche du dossier. Un PDF dit combien il a de pages : on les compte à la première consultation,
     * puis on les garde (c'est l'échelle de l'anneau d'avancement), comme pour la fiche d'un cours.
     *
     * @return list<array<string, mixed>>
     */
    public static function fichiers(int $moi, int $id): array
    {
        if (!self::elementsDisponibles()) {
            return [];
        }
        $fichiers = Database::all('SELECT * FROM fichiers WHERE dossier_id = ? AND user_id = ? AND pour_fiche = 1 ORDER BY created_at, id', [$id, $moi]);
        foreach ($fichiers as $rang => $fichier) {
            if ((int) $fichier['duree_lecture'] > 0 || !Fichiers::estPdf((string) $fichier['mime'], (string) $fichier['nom_origine'])) {
                continue;
            }
            $pages = Fichiers::pagesPdf(Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke']);
            if ($pages === null) {
                continue;
            }
            Database::run('UPDATE fichiers SET duree_lecture = ? WHERE id = ? AND user_id = ?', [$pages, (int) $fichier['id'], $moi]);
            $fichiers[$rang]['duree_lecture'] = $pages;
        }

        return $fichiers;
    }

    /**
     * Les liens, renvois vers d'autres cours et évènements rattachés à la fiche du dossier, rangés par type.
     *
     * @return array{lien: list<array>, cours: list<array>, evenement: list<array>}
     */
    public static function elements(int $moi, int $id): array
    {
        $parType = ['lien' => [], 'cours' => [], 'evenement' => []];
        if (!self::elementsDisponibles()) {
            return $parType;
        }
        foreach (Database::all(
            'SELECT e.*, c.titre AS cours_titre,
                    v.titre AS evenement_titre, v.debut AS evenement_debut, v.journee_entiere, v.termine,
                    t.icone AS type_icone, t.couleur AS type_couleur
               FROM fiche_elements e
               LEFT JOIN cours c           ON c.id = e.cible_cours_id
               LEFT JOIN evenements v      ON v.id = e.cible_evenement_id
               LEFT JOIN types_evenement t ON t.id = v.type_id
              WHERE e.dossier_id = ? AND e.user_id = ?
              ORDER BY e.type, e.position, e.id',
            [$id, $moi]
        ) as $element) {
            $parType[$element['type']][] = $element;
        }

        return $parType;
    }

    /**
     * Ajoute un lien web ; renvoie le message d'erreur, ou null si c'est fait. Seuls http et https passent : un « javascript: » deviendrait un
     * lien cliquable dans la page.
     */
    public static function ajouterLien(int $moi, int $id, string $url, string $libelle): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return t('cours.fl.lien_adresse');
        }
        if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return t('cours.fl.lien_invalide');
        }
        if (mb_strlen($url) > 2048) {
            return t('cours.fl.lien_long');
        }
        if ($libelle === '') {
            $libelle = (string) (parse_url($url, PHP_URL_HOST) ?: 'Lien');
        }
        Database::run(
            'INSERT INTO fiche_elements (user_id, dossier_id, type, libelle, url, position) VALUES (?, ?, \'lien\', ?, ?, ?)',
            [$moi, $id, $libelle, $url, self::rangSuivant($id, 'lien')]
        );

        return null;
    }

    /**
     * Rattache un cours ou un évènement — à moi, sinon la fiche pointerait chez quelqu'un d'autre ; renvoie le message d'erreur, ou null.
     *
     * @param string $type 'cours' ou 'evenement'
     */
    public static function ajouterRenvoi(int $moi, int $id, string $type, ?int $cible, string $libelle): ?string
    {
        if ($cible === null) {
            return t('cours.fl.aucun_element');
        }
        $table = $type === 'cours' ? 'cours' : 'evenements';
        $colonne = $type === 'cours' ? 'cible_cours_id' : 'cible_evenement_id';
        if (Database::valeur("SELECT id FROM `$table` WHERE id = ? AND user_id = ?", [$cible, $moi]) === null) {
            return t('cours.fl.element_introuvable');
        }
        if (Database::valeur("SELECT id FROM fiche_elements WHERE dossier_id = ? AND user_id = ? AND `$colonne` = ?", [$id, $moi, $cible]) !== null) {
            return t('cours.fl.element_deja');
        }
        Database::run(
            "INSERT INTO fiche_elements (user_id, dossier_id, type, libelle, `$colonne`, position) VALUES (?, ?, ?, ?, ?, ?)",
            [$moi, $id, $type, $libelle !== '' ? $libelle : null, $cible, self::rangSuivant($id, $type)]
        );

        return null;
    }

    private static function rangSuivant(int $id, string $type): int
    {
        return 1 + (int) Database::valeur('SELECT COALESCE(MAX(position), 0) FROM fiche_elements WHERE dossier_id = ? AND type = ?', [$id, $type]);
    }

    /** Mes cours (pour les renvois) et mes évènements (pour le calendrier), à proposer dans les formulaires de la fiche. */
    public static function choix(int $moi): array
    {
        return [
            'autresCours' => Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$moi]),
            'evenementsChoix' => Database::all('SELECT id, titre, debut FROM evenements WHERE user_id = ? ORDER BY debut DESC LIMIT 100', [$moi]),
        ];
    }

    /**
     * Ce que les cours reliés de la fiche ont déjà : leurs cartes de révision, leurs cartes mentales, leurs diaporamas rangés dans leur fiche. On ne les crée
     * pas ici (ils tiennent chacun à un cours) : la fiche du dossier les rassemble pour réviser le dossier d'un coup.
     *
     * @return array{cartes: array, cartesMentales: list<array>, diaporamas: list<array>}
     */
    public static function desCours(int $moi, int $id): array
    {
        // Les cours que la fiche relie : tout le dossier (sous-dossiers compris) tant qu'on n'a pas choisi, puis seulement le choix.
        $ids = self::coursRelies($moi, $id);
        $cartes = ['total' => 0, 'a_revoir' => 0, 'avancement' => null];
        $cartesMentales = [];
        $diaporamas = [];
        if ($ids !== []) {
            $marques = implode(',', array_fill(0, count($ids), '?'));
            $ligne = Database::one(
                "SELECT COUNT(*) AS total, SUM(revoir_le <= CURDATE()) AS a_revoir, AVG(boite) AS boite_moyenne
                   FROM cartes WHERE user_id = ? AND cours_id IN ($marques)",
                array_merge([$moi], $ids)
            );
            $cartes = [
                'total' => (int) ($ligne['total'] ?? 0),
                'a_revoir' => (int) ($ligne['a_revoir'] ?? 0),
                'avancement' => avancement_cartes((int) ($ligne['total'] ?? 0), (float) ($ligne['boite_moyenne'] ?? 1)),
            ];
            foreach ($ids as $coursId) {
                foreach (CarteMentale::duCours($coursId, $moi) as $carte) {
                    $cartesMentales[] = $carte;
                }
                foreach (Diaporama::duCours($coursId, $moi) as $diaporama) {
                    $diaporamas[$diaporama['id']] = $diaporama;
                }
            }
        }

        return ['cartes' => $cartes, 'cartesMentales' => $cartesMentales, 'diaporamas' => array_values($diaporamas)];
    }

    /** Efface du disque les fichiers de la fiche d'un dossier qu'on va supprimer (la base, elle, les retire avec le dossier). */
    public static function effacerFichiers(int $moi, int $id): void
    {
        if (!self::elementsDisponibles()) {
            return;
        }
        foreach (Database::all('SELECT id FROM fichiers WHERE dossier_id = ? AND user_id = ?', [$id, $moi]) as $fichier) {
            Fichiers::supprimer((int) $fichier['id'], $moi);
        }
    }

    /** Le choix des éléments reliés est-il possible ? Il faut la troisième migration, « dossier-fiche-liens » ; sans elle, la fiche relie tout le dossier. */
    public static function liensDisponibles(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT fiche_choix FROM dossiers LIMIT 1');
                Database::valeur('SELECT cours_id, sous_dossier_id FROM dossier_fiche_liens LIMIT 1');
                $ok = self::disponible();
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Tout ce que le dossier contient, sous-dossiers compris, à plat et dans l'ordre de l'arborescence : d'abord ses cours et fichiers, puis chaque
     * sous-dossier suivi de son propre contenu. Chaque ligne dit si la fiche la relie (« relie »), et pourquoi : choisie elle-même, ou parce qu'un
     * dossier au-dessus l'est (« heritee »).
     *
     * Tant qu'aucun choix n'a été fait, tout est relié.
     *
     * @return array{choix: bool, noeuds: list<array<string, mixed>>}
     */
    public static function arbre(int $moi, int $id): array
    {
        $choix = false;
        $coursChoisis = [];
        $dossiersChoisis = [];
        if (self::liensDisponibles()) {
            $choix = (int) Database::valeur('SELECT fiche_choix FROM dossiers WHERE id = ? AND user_id = ?', [$id, $moi]) === 1;
            foreach (Database::all('SELECT cours_id, sous_dossier_id FROM dossier_fiche_liens WHERE dossier_id = ? AND user_id = ?', [$id, $moi]) as $l) {
                if ($l['cours_id'] !== null) {
                    $coursChoisis[(int) $l['cours_id']] = true;
                }
                if ($l['sous_dossier_id'] !== null) {
                    $dossiersChoisis[(int) $l['sous_dossier_id']] = true;
                }
            }
        }

        $enfants = [];
        foreach (Database::all('SELECT id, parent_id, nom, icone FROM dossiers WHERE user_id = ? ORDER BY nom, id', [$moi]) as $d) {
            $enfants[(int) ($d['parent_id'] ?? 0)][] = $d;
        }
        $un = static fn (string $colonne): string => '(SELECT f.' . $colonne . ' FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0 ORDER BY f.id LIMIT 1)';
        $coursDe = [];
        foreach (Database::all(
            'SELECT c.id, c.titre, c.dossier_id, c.est_fichier, TRIM(COALESCE(c.fiche_revision, \'\')) <> \'\' AS a_fiche,
                    ' . $un('id') . ' AS fichier_id, ' . $un('nom_origine') . ' AS fichier_nom, ' . $un('mime') . ' AS fichier_mime
               FROM cours c WHERE c.user_id = ? AND c.dossier_id IS NOT NULL ORDER BY c.titre, c.id',
            [$moi]
        ) as $c) {
            $coursDe[(int) $c['dossier_id']][] = $c;
        }

        $noeuds = [];
        $vus = [$id => true];
        $dossierRelie = [];   // sous-dossier => relié (choisi, ou sous un dossier choisi) ; le dossier de la fiche lui-même n'y figure pas
        $descendre = static function (int $dossier, int $profondeur) use (&$descendre, &$noeuds, &$vus, &$dossierRelie, $choix, $id, $enfants, $coursDe, $coursChoisis, $dossiersChoisis): void {
            foreach ($coursDe[$dossier] ?? [] as $c) {
                $choisi = isset($coursChoisis[(int) $c['id']]);
                $herite = $dossier !== $id && ($dossierRelie[$dossier] ?? false);
                $noeuds[] = $c + [
                    'genre' => 'cours', 'profondeur' => $profondeur, 'parent' => $dossier,
                    'seul' => (int) $c['est_fichier'] === 1 && $c['fichier_id'] !== null,
                    'choisi' => $choisi, 'heritee' => $herite && !$choisi, 'relie' => !$choix || $choisi || $herite,
                ];
            }
            foreach ($enfants[$dossier] ?? [] as $d) {
                $sous = (int) $d['id'];
                if (isset($vus[$sous]) || $profondeur > 20) {
                    continue;
                }
                $vus[$sous] = true;
                $choisi = isset($dossiersChoisis[$sous]);
                $herite = $dossier !== $id && ($dossierRelie[$dossier] ?? false);
                $relie = !$choix || $choisi || $herite;
                $dossierRelie[$sous] = $relie;
                $noeuds[] = [
                    'genre' => 'dossier', 'id' => $sous, 'nom' => (string) $d['nom'], 'icone' => (string) $d['icone'],
                    'profondeur' => $profondeur, 'parent' => $dossier, 'nb_cours' => count($coursDe[$sous] ?? []),
                    'choisi' => $choisi, 'heritee' => $herite && !$choisi, 'relie' => $relie,
                ];
                $descendre($sous, $profondeur + 1);
            }
        };
        $descendre($id, 0);

        return ['choix' => $choix, 'noeuds' => $noeuds];
    }

    /** Les cours et fichiers que la fiche relie (identifiants), pour en tirer les cartes, cartes mentales et diaporamas. */
    public static function coursRelies(int $moi, int $id): array
    {
        $ids = [];
        foreach (self::arbre($moi, $id)['noeuds'] as $n) {
            if ($n['genre'] === 'cours' && $n['relie']) {
                $ids[] = (int) $n['id'];
            }
        }

        return $ids;
    }

    /**
     * Enregistre le choix : les cours et sous-dossiers cochés (de ce dossier seulement) ; « tout » revient à tout relier. Un sous-dossier relié l'est
     * avec tout son contenu : ce qu'il contient n'est donc pas retenu à part. Faux si ce n'est pas mon dossier.
     *
     * @param list<int> $cours      cours et fichiers cochés
     * @param list<int> $dossiers   sous-dossiers cochés
     */
    public static function enregistrerLiens(int $moi, int $id, array $cours, array $dossiers, bool $tout): bool
    {
        if (self::dossier($moi, $id) === null || !self::liensDisponibles()) {
            return false;
        }
        Database::run('DELETE FROM dossier_fiche_liens WHERE dossier_id = ? AND user_id = ?', [$id, $moi]);
        if ($tout) {
            Database::run('UPDATE dossiers SET fiche_choix = 0 WHERE id = ? AND user_id = ?', [$id, $moi]);

            return true;
        }

        // Seulement ce qui est vraiment dans ce dossier : jamais le cours ou le dossier de quelqu'un d'autre, ni d'ailleurs.
        $noeuds = [];
        foreach (self::arbre($moi, $id)['noeuds'] as $n) {
            $noeuds[$n['genre'] . ':' . $n['id']] = $n;
        }
        $dossiersChoisis = [];
        foreach ($dossiers as $d) {
            if (isset($noeuds['dossier:' . (int) $d])) {
                $dossiersChoisis[(int) $d] = true;
            }
        }
        // Un nœud est « couvert » quand un dossier au-dessus de lui est choisi.
        $couvert = static function (array $noeud) use (&$couvert, $noeuds, $dossiersChoisis, $id): bool {
            $parent = (int) $noeud['parent'];
            while ($parent !== $id && $parent !== 0) {
                if (isset($dossiersChoisis[$parent])) {
                    return true;
                }
                $parent = (int) ($noeuds['dossier:' . $parent]['parent'] ?? 0);
            }

            return false;
        };
        foreach (array_keys($dossiersChoisis) as $d) {
            if (!$couvert($noeuds['dossier:' . $d])) {
                Database::run('INSERT INTO dossier_fiche_liens (user_id, dossier_id, sous_dossier_id) VALUES (?, ?, ?)', [$moi, $id, $d]);
            }
        }
        foreach (array_unique(array_map('intval', $cours)) as $c) {
            if (isset($noeuds['cours:' . $c]) && !$couvert($noeuds['cours:' . $c])) {
                Database::run('INSERT INTO dossier_fiche_liens (user_id, dossier_id, cours_id) VALUES (?, ?, ?)', [$moi, $id, $c]);
            }
        }
        Database::run('UPDATE dossiers SET fiche_choix = 1 WHERE id = ? AND user_id = ?', [$id, $moi]);

        return true;
    }

    /** Mon dossier, avec sa matière et sa fiche ; null s'il n'est pas à moi (ou si la fonction n'est pas disponible). */
    public static function dossier(int $moi, int $id): ?array
    {
        if (!self::disponible()) {
            return null;
        }

        return Database::one(
            'SELECT d.id, d.nom, d.icone, d.couleur, d.parent_id, d.fiche_revision,
                    m.nom AS matiere_nom, m.couleur AS matiere_couleur
               FROM dossiers d LEFT JOIN matieres m ON m.id = d.matiere_id
              WHERE d.id = ? AND d.user_id = ?',
            [$id, $moi]
        );
    }

    /**
     * Ce que le dossier contient, à l'étage où l'on est : ses cours et fichiers, et ses sous-dossiers (avec leurs fiches).
     *
     * @return array{cours: list<array>, sous: list<array>}
     */
    public static function contenu(int $moi, int $id): array
    {
        $un = static fn (string $colonne): string => '(SELECT f.' . $colonne . ' FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0 ORDER BY f.id LIMIT 1)';

        return [
            'cours' => Database::all(
                'SELECT c.id, c.titre, c.est_fichier, TRIM(COALESCE(c.fiche_revision, \'\')) <> \'\' AS a_fiche,
                        ' . $un('id') . ' AS fichier_id, ' . $un('nom_origine') . ' AS fichier_nom, ' . $un('mime') . ' AS fichier_mime
                   FROM cours c WHERE c.user_id = ? AND c.dossier_id = ? ORDER BY c.titre, c.id',
                [$moi, $id]
            ),
            'sous' => Database::all(
                'SELECT d.id, d.nom, d.icone, TRIM(COALESCE(d.fiche_revision, \'\')) <> \'\' AS a_fiche,
                        (SELECT COUNT(*) FROM cours c WHERE c.dossier_id = d.id) AS nb_cours
                   FROM dossiers d WHERE d.user_id = ? AND d.parent_id = ? ORDER BY d.nom, d.id',
                [$moi, $id]
            ),
        ];
    }

    /** Ce dossier a-t-il une fiche écrite ? */
    public static function ecrite(int $moi, int $id): bool
    {
        if (!self::disponible()) {
            return false;
        }

        // Du texte, ou le moindre élément rattaché (fichier, lien, renvoi, évènement) : comme pour un cours, c'est déjà une fiche.
        $texte = (int) Database::valeur(
            'SELECT COUNT(*) FROM dossiers WHERE id = ? AND user_id = ? AND TRIM(COALESCE(fiche_revision, \'\')) <> \'\'',
            [$id, $moi]
        ) > 0;
        if ($texte || !self::elementsDisponibles()) {
            return $texte;
        }

        return (int) Database::valeur(
            'SELECT (SELECT COUNT(*) FROM fichiers WHERE dossier_id = ? AND user_id = ? AND pour_fiche = 1)
                  + (SELECT COUNT(*) FROM fiche_elements WHERE dossier_id = ? AND user_id = ?)',
            [$id, $moi, $id, $moi]
        ) > 0;
    }

    /** Enregistre la fiche (un texte vidé retire la fiche) ; faux si ce n'est pas mon dossier. */
    public static function enregistrer(int $moi, int $id, string $fiche): bool
    {
        if (self::dossier($moi, $id) === null) {
            return false;
        }
        Database::run(
            'UPDATE dossiers SET fiche_revision = ?, fiche_modifiee_le = ? WHERE id = ? AND user_id = ?',
            [$fiche === '' ? null : $fiche, $fiche === '' ? null : date('Y-m-d H:i:s'), $id, $moi]
        );

        return true;
    }

    /**
     * Les dossiers, sous la forme des fiches de la liste « Révision » : ils s'y rangent comme n'importe quelle fiche (même carte, même regroupement par
     * matière, même tri), avec « dossier » à vrai pour qu'on les ouvre à leur page. Ceux qui ont une fiche écrite, et ceux qui n'en ont pas encore.
     *
     * @param list<string> $termes  les mots cherchés : chacun doit se trouver dans le nom du dossier ou le texte de sa fiche
     * @return list<array<string, mixed>>
     */
    public static function pourRevision(int $moi, ?int $matiere, array $termes): array
    {
        if (!self::disponible()) {
            return [];
        }
        // Comme une fiche de cours, la fiche d'un dossier existe dès qu'elle porte du texte OU le moindre élément (un fichier, un lien, un renvoi,
        // un évènement) ; sans la seconde migration, il n'y a que le texte.
        $elements = self::elementsDisponibles();
        $compte = static fn (string $table, string $condition): string => '(SELECT COUNT(*) FROM ' . $table . ' WHERE dossier_id = d.id AND ' . $condition . ')';
        $lignes = Database::all(
            'SELECT d.id, d.nom, d.icone, d.fiche_revision, COALESCE(d.fiche_modifiee_le, d.created_at) AS modifiee_le,
                    m.nom AS matiere_nom, m.couleur AS matiere_couleur'
            . ($elements ? ',
                    ' . $compte('fichiers', 'pour_fiche = 1') . ' AS nb_fichiers,
                    ' . $compte('fiche_elements', 'type = \'lien\'') . ' AS nb_liens,
                    ' . $compte('fiche_elements', 'type = \'cours\'') . ' AS nb_renvois,
                    ' . $compte('fiche_elements', 'type = \'evenement\'') . ' AS nb_evenements,
                    GREATEST(COALESCE(d.fiche_modifiee_le, d.created_at),
                             COALESCE((SELECT MAX(f.created_at) FROM fichiers f WHERE f.dossier_id = d.id AND f.pour_fiche = 1), d.created_at),
                             COALESCE((SELECT MAX(e.created_at) FROM fiche_elements e WHERE e.dossier_id = d.id), d.created_at)) AS derniere_modif,
                    CONCAT_WS(\' \',
                        (SELECT GROUP_CONCAT(f.nom_origine SEPARATOR \' \') FROM fichiers f WHERE f.dossier_id = d.id AND f.pour_fiche = 1),
                        (SELECT GROUP_CONCAT(CONCAT_WS(\' \', e.libelle, e.url) SEPARATOR \' \') FROM fiche_elements e WHERE e.dossier_id = d.id)) AS texte_elements' : '') . '
               FROM dossiers d LEFT JOIN matieres m ON m.id = d.matiere_id
              WHERE d.user_id = ?' . ($matiere === null ? '' : ' AND d.matiere_id = ?') . '
              ORDER BY d.nom, d.id',
            $matiere === null ? [$moi] : [$moi, $matiere]
        );
        // L'avancement de révision d'un dossier, comme celui d'un cours : ce qui se lit ou s'écoute dans les fichiers de sa fiche, et où en est le paquet
        // de cartes des cours qu'elle relie. Chaque anneau pèse pareil ; ce qui n'a jamais été ouvert compte pour zéro.
        $medias = [];
        if ($elements) {
            foreach (fichiers_suivis(Database::all(
                'SELECT id, dossier_id, nom_origine, mime, position_lecture, duree_lecture' . Quizz::colonnes() . ' FROM fichiers WHERE user_id = ? AND pour_fiche = 1 AND dossier_id IS NOT NULL',
                [$moi]
            )) as $f) {
                $medias[(int) $f['dossier_id']][] = $f;
            }
        }
        $cartesParCours = [];
        foreach (Database::all('SELECT cours_id, COUNT(*) AS n, SUM(boite) AS s FROM cartes WHERE user_id = ? GROUP BY cours_id', [$moi]) as $k) {
            $cartesParCours[(int) $k['cours_id']] = [(int) $k['n'], (int) $k['s']];
        }
        $fiches = [];
        foreach ($lignes as $l) {
            $meule = (string) $l['nom'] . ' ' . TexteRiche::versTexte($l['fiche_revision']) . ' ' . (string) ($l['texte_elements'] ?? '');
            foreach ($termes as $terme) {
                if ($terme !== '' && mb_stripos($meule, $terme) === false) {
                    continue 2;
                }
            }
            $fiches[] = [
                'id' => (int) $l['id'],
                'titre' => (string) $l['nom'],
                'icone' => (string) $l['icone'],
                'fiche_revision' => $l['fiche_revision'],
                'updated_at' => $l['derniere_modif'] ?? $l['modifiee_le'],
                'modifiee_le' => $l['derniere_modif'] ?? $l['modifiee_le'],
                'matiere_nom' => $l['matiere_nom'],
                'matiere_couleur' => $l['matiere_couleur'],
                'nb_fichiers' => (int) ($l['nb_fichiers'] ?? 0), 'nb_liens' => (int) ($l['nb_liens'] ?? 0),
                'nb_renvois' => (int) ($l['nb_renvois'] ?? 0), 'nb_evenements' => (int) ($l['nb_evenements'] ?? 0),
                // Les cartes sont celles des cours : la carte d'un dossier n'en compte pas (pas d'anneau, pas de paquet).
                'nb_cartes' => 0, 'nb_cartes_dues' => 0,
                'nb_elements' => (int) ($l['nb_fichiers'] ?? 0) + (int) ($l['nb_liens'] ?? 0) + (int) ($l['nb_renvois'] ?? 0) + (int) ($l['nb_evenements'] ?? 0),
                'trouve_ailleurs' => false,
                'dossier' => true,
                'anneaux' => $medias[(int) $l['id']] ?? [],
                'paquet' => $cartesParCours === [] ? null : self::avancementDesCartes((int) $l['id'], $moi, $cartesParCours),
            ];
        }

        return $fiches;
    }

    /**
     * Où en est le paquet de cartes des cours que la fiche du dossier relie : la boîte moyenne de toutes leurs cartes, en pourcentage (null sans carte).
     *
     * @param array<int, array{0: int, 1: int}> $cartesParCours  cours => [nombre de cartes, somme des boîtes]
     */
    private static function avancementDesCartes(int $id, int $moi, array $cartesParCours): ?int
    {
        $total = 0;
        $somme = 0;
        foreach (self::coursRelies($moi, $id) as $coursId) {
            if (isset($cartesParCours[$coursId])) {
                $total += $cartesParCours[$coursId][0];
                $somme += $cartesParCours[$coursId][1];
            }
        }

        return avancement_cartes($total, $total > 0 ? $somme / $total : 1.0);
    }

    /** Range des fiches comme la liste « Révision » le fait : par matière (sans matière en dernier), la plus récente d'abord, ou la plus ancienne. */
    public static function trier(array $fiches, string $tri): array
    {
        $titre = static fn (array $f): string => mb_strtolower((string) $f['titre']);
        $matiere = static fn (array $f): string => $f['matiere_nom'] === null ? "\u{FFFF}" : mb_strtolower((string) $f['matiere_nom']);
        usort($fiches, static fn (array $a, array $b): int => match ($tri) {
            'recent' => [strcmp((string) $b['modifiee_le'], (string) $a['modifiee_le']), strcmp($titre($a), $titre($b))] <=> [0, 0],
            'ancien' => [strcmp((string) $a['modifiee_le'], (string) $b['modifiee_le']), strcmp($titre($a), $titre($b))] <=> [0, 0],
            default => [strcmp($matiere($a), $matiere($b)), strcmp($titre($a), $titre($b))] <=> [0, 0],
        });

        return $fiches;
    }
}
