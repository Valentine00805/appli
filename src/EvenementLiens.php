<?php
declare(strict_types=1);

/**
 * Les cours et les dossiers liés à un évènement personnel.
 *
 * Un évènement peut en lier plusieurs (le « + » de « Lier à »). La table « evenement_liens » les garde tous ; les colonnes « cours_id » et
 * « dossier_id » de « evenements » gardent le premier cours et le premier dossier, ce que lisent encore l'agenda, le partage d'un évènement et
 * les sauvegardes d'avant.
 */
final class EvenementLiens
{
    /** Le fichier déposé derrière un fichier seul (le premier de la ligne de cours), à joindre à une requête sur « cours c ». */
    private const FICHIER = '(SELECT f.id FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0 ORDER BY f.id LIMIT 1) AS fichier_id,
                             (SELECT f.nom_origine FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0 ORDER BY f.id LIMIT 1) AS fichier_nom,
                             (SELECT f.mime FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0 ORDER BY f.id LIMIT 1) AS fichier_mime';

    /**
     * Les liens soumis par le formulaire (« lien[0][genre] », « lien[0][cours] »…), gardés s'ils sont à soi, sans doublon.
     *
     * @return list<array{0: string, 1: int}>  [genre, identifiant], genre « cours », « fichier » (seul) ou « dossier »
     */
    public static function depuisFormulaire(int $userId, mixed $lignes): array
    {
        if (!is_array($lignes)) {
            return [];
        }

        $liens = [];
        foreach ($lignes as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $genre = (string) ($ligne['genre'] ?? '');
            if ($genre === 'cours' || $genre === 'fichier') {
                // Un fichier seul est une ligne de « cours » marquée comme telle : le genre dit lequel des deux on attend.
                $id = entier_ou_null($ligne[$genre] ?? null);
                $a = $id !== null && Database::valeur(
                    'SELECT id FROM cours WHERE id = ? AND user_id = ? AND est_fichier = ?',
                    [$id, $userId, $genre === 'fichier' ? 1 : 0]
                ) !== null;
            } elseif ($genre === 'dossier') {
                $id = entier_ou_null($ligne['dossier'] ?? null);
                $a = $id !== null && DossiersController::valide($userId, $id) !== null;
            } else {
                continue;
            }
            if ($a && !isset($liens[$genre . ':' . $id])) {
                $liens[$genre . ':' . $id] = [$genre, (int) $id];
            }
        }

        return array_values($liens);
    }

    /** Le premier cours et le premier dossier d'une liste de liens : ce que gardent les colonnes de « evenements ». */
    public static function premiers(array $liens): array
    {
        $cours = null;
        $dossier = null;
        foreach ($liens as [$genre, $id]) {
            if ($genre === 'cours') {
                $cours ??= $id;
            } elseif ($genre === 'dossier') {
                $dossier ??= $id;
            }   // un fichier seul n'a pas de colonne : il vit dans la table (« Cours » et « Révision » n'ont rien à en faire)
        }

        return [$cours, $dossier];
    }

    /** Remplace les liens d'un évènement. */
    public static function ecrire(int $evenementId, array $liens): void
    {
        Database::run('DELETE FROM evenement_liens WHERE evenement_id = ?', [$evenementId]);
        foreach ($liens as [$genre, $id]) {
            Database::run(
                'INSERT INTO evenement_liens (evenement_id, cours_id, dossier_id) VALUES (?, ?, ?)',
                [$evenementId, $genre === 'dossier' ? null : $id, $genre === 'dossier' ? $id : null]
            );
        }
    }

    /**
     * Les liens d'un évènement, prêts à afficher : les cours et fichiers seuls (id, titre) puis les dossiers (id, nom, icone). Un lien dont le cours ou le
     * dossier n'est plus à soi n'est pas rendu.
     *
     * @return list<array{genre: string, id: int, nom: string, fichier_id?: ?int, fichier_nom?: string, fichier_mime?: string}>
     *         genre « cours », « fichier » ou « dossier » ; le fichier derrière un fichier seul
     */
    public static function de(int $evenementId, int $userId): array
    {
        $cours = Database::all(
            'SELECT c.id, c.titre AS nom, c.est_fichier, ' . self::FICHIER . '
               FROM evenement_liens l JOIN cours c ON c.id = l.cours_id
              WHERE l.evenement_id = ? AND c.user_id = ? ORDER BY l.id',
            [$evenementId, $userId]
        );
        $dossiers = Database::all(
            'SELECT d.id, d.nom, d.icone
               FROM evenement_liens l JOIN dossiers d ON d.id = l.dossier_id
              WHERE l.evenement_id = ? AND d.user_id = ? ORDER BY l.id',
            [$evenementId, $userId]
        );

        // Un évènement posé sans passer par le formulaire n'a que ses colonnes : à défaut de lignes, on lit celles-là.
        if ($cours === [] && $dossiers === [] && Database::valeur('SELECT COUNT(*) FROM evenement_liens WHERE evenement_id = ?', [$evenementId]) === 0) {
            $cours = Database::all(
                'SELECT c.id, c.titre AS nom, c.est_fichier, ' . self::FICHIER . ' FROM evenements e JOIN cours c ON c.id = e.cours_id WHERE e.id = ? AND c.user_id = ?',
                [$evenementId, $userId]
            );
            $dossiers = Database::all(
                'SELECT d.id, d.nom, d.icone FROM evenements e JOIN dossiers d ON d.id = e.dossier_id WHERE e.id = ? AND d.user_id = ?',
                [$evenementId, $userId]
            );
        }

        $rendu = [];
        foreach ($cours as $c) {
            $rendu[] = [
                'genre' => (int) $c['est_fichier'] === 1 ? 'fichier' : 'cours', 'id' => (int) $c['id'], 'nom' => (string) $c['nom'],
                // Un fichier seul s'ouvre dans le lecteur de fichiers, pas en page de cours : on garde le fichier derrière.
                'fichier_id' => $c['fichier_id'] === null ? null : (int) $c['fichier_id'],
                'fichier_nom' => (string) ($c['fichier_nom'] ?? ''), 'fichier_mime' => (string) ($c['fichier_mime'] ?? ''),
            ];
        }
        foreach ($dossiers as $d) {
            $rendu[] = ['genre' => 'dossier', 'id' => (int) $d['id'], 'nom' => trim((string) $d['icone'] . ' ' . (string) $d['nom'])];
        }

        return $rendu;
    }
}
