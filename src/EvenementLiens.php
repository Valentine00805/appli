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
    /**
     * Les liens soumis par le formulaire (« lien[0][genre] », « lien[0][cours] »…), gardés s'ils sont à soi, sans doublon.
     *
     * @return list<array{0: string, 1: int}>  [genre, identifiant], genre « cours » ou « dossier »
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
            if ($genre === 'cours') {
                $id = entier_ou_null($ligne['cours'] ?? null);
                $a = $id !== null && Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) !== null;
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
            } else {
                $dossier ??= $id;
            }
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
                [$evenementId, $genre === 'cours' ? $id : null, $genre === 'dossier' ? $id : null]
            );
        }
    }

    /**
     * Les liens d'un évènement, prêts à afficher : les cours (id, titre) puis les dossiers (id, nom, icone). Un lien dont le cours ou le
     * dossier n'est plus à soi n'est pas rendu.
     *
     * @return list<array{genre: string, id: int, nom: string}>
     */
    public static function de(int $evenementId, int $userId): array
    {
        $cours = Database::all(
            'SELECT c.id, c.titre AS nom
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
                'SELECT c.id, c.titre AS nom FROM evenements e JOIN cours c ON c.id = e.cours_id WHERE e.id = ? AND c.user_id = ?',
                [$evenementId, $userId]
            );
            $dossiers = Database::all(
                'SELECT d.id, d.nom, d.icone FROM evenements e JOIN dossiers d ON d.id = e.dossier_id WHERE e.id = ? AND d.user_id = ?',
                [$evenementId, $userId]
            );
        }

        $rendu = [];
        foreach ($cours as $c) {
            $rendu[] = ['genre' => 'cours', 'id' => (int) $c['id'], 'nom' => (string) $c['nom']];
        }
        foreach ($dossiers as $d) {
            $rendu[] = ['genre' => 'dossier', 'id' => (int) $d['id'], 'nom' => trim((string) $d['icone'] . ' ' . (string) $d['nom'])];
        }

        return $rendu;
    }
}
