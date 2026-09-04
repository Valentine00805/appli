<?php
declare(strict_types=1);

/**
 * Tableau kanban : tout ce qu'il y a à faire, réparti en quatre colonnes.
 *
 * Les cartes ne sont pas une nouvelle sorte d'objet — ce sont vos sous-tâches
 * et vos évènements, relus à chaque affichage. Rien n'est recopié, donc rien
 * ne peut se désynchroniser. Seule l’étape (en cours, validation) a demandé un
 * indicateur : « faite » et « termine » restent la référence pour le reste
 * de l'application, et un élément terminé l'est ici aussi.
 */
final class KanbanController
{
    /** Les quatre colonnes, dans l'ordre d'affichage. */
    public const COLONNES = [
        'a_faire'    => ['titre' => 'À faire',    'icone' => '📥'],
        'en_cours'   => ['titre' => 'En cours',   'icone' => '🚧'],
        'validation' => ['titre' => 'Validation', 'icone' => '🔎'],
        'termine'    => ['titre' => 'Terminé',    'icone' => '✅'],
    ];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $matiereId = entier_ou_null($_GET['matiere'] ?? null);
        $typeId    = entier_ou_null($_GET['type'] ?? null);
        $source    = in_array($_GET['source'] ?? '', ['taches', 'evenements'], true)
            ? (string) $_GET['source'] : 'tout';

        $cartes = [];
        if ($source !== 'evenements') {
            $cartes = array_merge($cartes, $this->cartesTaches($userId));
        }
        if ($source !== 'taches') {
            $cartes = array_merge($cartes, $this->cartesEvenements($userId, $matiereId, $typeId));
        }

        // Le plus urgent en tête de colonne ; ce qui n'a pas de date suit.
        usort($cartes, static function (array $a, array $b): int {
            if (($a['echeance'] === null) !== ($b['echeance'] === null)) {
                return $a['echeance'] === null ? 1 : -1;
            }
            return [$a['echeance'], $a['titre']] <=> [$b['echeance'], $b['titre']];
        });

        $parColonne = array_fill_keys(array_keys(self::COLONNES), []);
        foreach ($cartes as $carte) {
            $parColonne[$carte['colonne']][] = $carte;
        }

        Vue::afficher('kanban/index', [
            'parColonne' => $parColonne,
            'colonnes'   => self::COLONNES,
            'matieres'   => Database::all(
                'SELECT id, nom FROM matieres WHERE user_id = ? ORDER BY nom',
                [$userId]
            ),
            'types'      => TypesEvenementController::pourUtilisateur($userId),
            'matiereId'  => $matiereId,
            'typeId'     => $typeId,
            'source'     => $source,
        ], 'Tableau');
    }

    /** Déplace une carte d'une colonne à l'autre. */
    public function deplacer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $colonne = (string) ($_POST['colonne'] ?? '');
        if (!array_key_exists($colonne, self::COLONNES)) {
            $this->introuvable();
        }

        $nature = (string) ($_POST['nature'] ?? '');
        $id = entier_ou_null($_POST['carte'] ?? null);
        if ($id === null || !in_array($nature, ['tache', 'evenement'], true)) {
            $this->introuvable();
        }

        // Une carte n'est que dans une colonne : « terminé » efface l'étape.
        $fait    = $colonne === 'termine' ? 1 : 0;
        $etape   = match ($colonne) { 'en_cours' => 1, 'validation' => 2, default => 0 };

        if ($nature === 'tache') {
            $existe = Database::valeur('SELECT id FROM taches WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($existe === null) {
                $this->introuvable();
            }
            Database::run(
                'UPDATE taches SET faite = ?, faite_le = ?, etape = ? WHERE id = ? AND user_id = ?',
                [$fait, $fait === 1 ? date('Y-m-d H:i:s') : null, $etape, $id, $userId]
            );
        } else {
            $existe = Database::valeur('SELECT id FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($existe === null) {
                $this->introuvable();
            }
            Database::run(
                'UPDATE evenements SET termine = ?, etape = ? WHERE id = ? AND user_id = ?',
                [$fait, $etape, $id, $userId]
            );
        }

        $this->repartirVers('tableau');
    }

    /**
     * Enregistre la remarque d'une carte.
     * Une sous-tâche a son champ « note » ; un évènement réutilise sa
     * description, qui dit déjà la même chose.
     */
    public function noter(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nature = (string) ($_POST['nature'] ?? '');
        $id = entier_ou_null($_POST['carte'] ?? null);
        if ($id === null || !in_array($nature, ['tache', 'evenement'], true)) {
            $this->introuvable();
        }

        $note = mb_substr(post('note'), 0, 500);
        $note = $note === '' ? null : $note;

        [$table, $colonne] = $nature === 'tache' ? ['taches', 'note'] : ['evenements', 'description'];

        if (Database::valeur("SELECT id FROM `$table` WHERE id = ? AND user_id = ?", [$id, $userId]) === null) {
            $this->introuvable();
        }
        Database::run(
            "UPDATE `$table` SET `$colonne` = ? WHERE id = ? AND user_id = ?",
            [$note, $id, $userId]
        );

        Session::flash('succes', $note === null ? 'Remarque effacée.' : 'Remarque enregistrée.');
        $this->repartirVers('tableau');
    }

    /* --- Interne -------------------------------------------------------- */

    /** Les sous-tâches, avec ou sans échéance. */
    private function cartesTaches(int $userId): array
    {
        $cartes = [];
        foreach (Database::all(
            'SELECT t.id, t.titre, t.echeance, t.faite, t.etape, t.note, t.liste_id,
                    l.nom AS liste_nom, l.couleur AS liste_couleur, l.icone AS liste_icone
             FROM taches t
             JOIN listes_taches l ON l.id = t.liste_id
             WHERE t.user_id = ?',
            [$userId]
        ) as $t) {
            $cartes[] = [
                'nature'      => 'tache',
                'id'          => (int) $t['id'],
                'titre'       => (string) $t['titre'],
                'echeance'    => $t['echeance'],
                'colonne'     => $this->colonneDe((int) $t['faite'], (int) $t['etape']),
                'couleur'     => (string) $t['liste_couleur'],
                'icone'       => (string) $t['liste_icone'] !== '' ? (string) $t['liste_icone'] : '📋',
                'origine'     => (string) $t['liste_nom'],
                'note'        => (string) ($t['note'] ?? ''),
                'lien'        => url('taches', ['liste' => (int) $t['liste_id']]),
                'cours_id'    => 0,
                'cours_titre' => '',
            ];
        }
        return $cartes;
    }

    /** Les évènements : révisions, devoirs, examens, cours… */
    private function cartesEvenements(int $userId, ?int $matiereId, ?int $typeId): array
    {
        // On écarte le passé déjà réglé : un cours d'il y a trois mois n'a
        // rien à faire sur un tableau de ce qui reste à faire.
        $sql = 'SELECT e.id, e.titre, e.debut, e.termine, e.etape, e.description,
                       e.cours_id, c.titre AS cours_titre,
                       m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                       t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur
                FROM evenements e
                LEFT JOIN matieres m        ON m.id = e.matiere_id
                LEFT JOIN cours c           ON c.id = e.cours_id
                LEFT JOIN types_evenement t ON t.id = e.type_id
                WHERE e.user_id = ?
                  AND (e.termine = 0 OR e.fin >= DATE_SUB(NOW(), INTERVAL 30 DAY))';
        $params = [$userId];

        if ($matiereId !== null) {
            $sql .= ' AND e.matiere_id = ?';
            $params[] = $matiereId;
        }
        if ($typeId !== null) {
            $sql .= ' AND e.type_id = ?';
            $params[] = $typeId;
        }

        $cartes = [];
        foreach (Database::all($sql, $params) as $e) {
            $cartes[] = [
                'nature'   => 'evenement',
                'id'       => (int) $e['id'],
                'titre'    => (string) $e['titre'],
                'echeance' => substr((string) $e['debut'], 0, 10),
                'colonne'  => $this->colonneDe((int) $e['termine'], (int) $e['etape']),
                'couleur'  => (string) ($e['matiere_couleur'] ?? '') !== ''
                    ? (string) $e['matiere_couleur']
                    : ((string) ($e['type_couleur'] ?? '') !== '' ? (string) $e['type_couleur'] : '#94a3b8'),
                'icone'    => (string) ($e['type_icone'] ?? '') !== '' ? (string) $e['type_icone'] : '📌',
                'origine'  => trim(((string) ($e['type_nom'] ?? 'Évènement'))
                    . ((string) ($e['matiere_nom'] ?? '') !== '' ? ' · ' . (string) $e['matiere_nom'] : '')),
                'note'     => (string) ($e['description'] ?? ''),
                'lien'     => url('evenements/' . (int) $e['id'] . '/modifier'),
                // De quoi rejoindre le cours et sa fiche, comme au calendrier.
                'cours_id'    => (int) ($e['cours_id'] ?? 0),
                'cours_titre' => (string) ($e['cours_titre'] ?? ''),
            ];
        }
        return $cartes;
    }

    /** « Terminé » l'emporte : un élément fait l'est, quelle que soit l'étape. */
    private function colonneDe(int $fait, int $etape): string
    {
        if ($fait === 1) {
            return 'termine';
        }
        return match ($etape) {
            1       => 'en_cours',
            2       => 'validation',
            default => 'a_faire',
        };
    }

    /** Même prudence qu'ailleurs : on ne suit qu'une adresse interne. */
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

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
