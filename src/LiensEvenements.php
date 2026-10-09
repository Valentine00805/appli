<?php
declare(strict_types=1);

/**
 * Les documents d'un travail de groupe liés à un de ses évènements.
 *
 * Un évènement du projet — un évènement de son calendrier commun, ou une échéance — peut renvoyer à un cours, un dossier ou un fichier du
 * projet (ceux de ses onglets « Cours » et « Fichiers ») : « ce cours se lit pour cette séance », « ce plan se rend à cette date ». On le
 * voit des deux côtés : sur l'évènement, qui liste ses documents, et sur le document, qui dit à quels évènements il se rattache.
 *
 * L'évènement et le document sont désignés par leur type et leur numéro (deux tables d'origine possibles pour chacun) : une ligne dont
 * l'un des deux a disparu ou n'est plus dans le projet est ignorée à la lecture, jamais montrée.
 */
final class LiensEvenements
{
    public const TYPES_EVENEMENT = ['evenement', 'echeance'];
    public const TYPES_CIBLE = ['cours', 'dossier', 'fichier'];

    /**
     * Ce qu'on peut lier à un évènement du projet : ses cours, dossiers et fichiers, sans ceux qui le sont déjà.
     *
     * @return array{cours: list<array>, dossiers: list<array>, fichiers: list<array>}
     */
    public static function aLier(int $projet, int $moi, string $typeEvenement, int $evenement): array
    {
        $deja = [];
        foreach (Database::all(
            'SELECT cible_type, cible_id FROM projet_evenement_liens WHERE evenement_type = ? AND evenement_id = ?',
            [$typeEvenement, $evenement]
        ) as $l) {
            $deja[$l['cible_type'] . ':' . $l['cible_id']] = true;
        }

        $cours = [];
        $dossiers = [];
        foreach (Travaux::liens($projet, $moi) as $l) {
            if (isset($deja[$l['type'] . ':' . $l['cible_id']])) {
                continue;
            }
            if ($l['type'] === 'dossier') {
                $dossiers[] = $l;
            } else {
                $cours[] = $l;
            }
        }
        $fichiers = array_values(array_filter(
            Travaux::fichiers($projet),
            static fn (array $f): bool => !isset($deja['fichier:' . $f['id']])
        ));

        return ['cours' => $cours, 'dossiers' => $dossiers, 'fichiers' => $fichiers];
    }

    /**
     * Les documents liés à un évènement, avec de quoi les ouvrir.
     *
     * @return list<array{id: int, type: string, cible_id: int, titre: string, icone: string, url: string, ajoute_par: ?int}>
     */
    public static function liensDe(int $projet, int $moi, string $typeEvenement, int $evenement): array
    {
        $lignes = Database::all(
            'SELECT id, cible_type, cible_id, ajoute_par FROM projet_evenement_liens
              WHERE projet_id = ? AND evenement_type = ? AND evenement_id = ? ORDER BY id',
            [$projet, $typeEvenement, $evenement]
        );
        if ($lignes === []) {
            return [];
        }
        $dans = [];
        foreach (Travaux::liens($projet, $moi) as $l) {
            $dans[$l['type'] . ':' . $l['cible_id']] = $l;
        }
        $fichiers = [];
        foreach (Travaux::fichiers($projet) as $f) {
            $fichiers[(int) $f['id']] = $f;
        }

        $liens = [];
        foreach ($lignes as $l) {
            $type = (string) $l['cible_type'];
            $cible = (int) $l['cible_id'];
            if ($type === 'fichier') {
                if (!isset($fichiers[$cible])) {
                    continue;
                }
                $titre = (string) $fichiers[$cible]['nom_origine'];
                $icone = '📎';
                $url = url('travaux/fichiers/' . $cible);
            } else {
                if (!isset($dans[$type . ':' . $cible])) {
                    continue;
                }
                $titre = $dans[$type . ':' . $cible]['titre'];
                $icone = $dans[$type . ':' . $cible]['icone'];
                $url = url('partages/' . ($type === 'dossier' ? 'dossiers' : 'cours') . '/' . $cible,
                    $dans[$type . ':' . $cible]['proprietaire_id'] === $moi ? ['apercu' => 1] : []);
            }
            $liens[] = [
                'id' => (int) $l['id'], 'type' => $type, 'cible_id' => $cible, 'titre' => $titre, 'icone' => $icone, 'url' => $url,
                'ajoute_par' => $l['ajoute_par'] === null ? null : (int) $l['ajoute_par'],
            ];
        }

        return $liens;
    }

    /**
     * Lie un document du projet à un évènement du projet (tout membre du projet).
     *
     * @return ?string la raison du refus
     */
    public static function lier(int $moi, int $projet, string $typeEvenement, int $evenement, string $typeCible, int $cible): ?string
    {
        if (Travaux::projet($projet, $moi) === null) {
            return t('tr.err.pas_membre');
        }
        if (!in_array($typeEvenement, self::TYPES_EVENEMENT, true) || !in_array($typeCible, self::TYPES_CIBLE, true)) {
            return t('cam.err.lien_inconnu');
        }
        // L'évènement est bien celui de ce projet.
        $duProjet = $typeEvenement === 'echeance'
            ? Database::valeur('SELECT 1 FROM projet_echeances WHERE id = ? AND projet_id = ?', [$evenement, $projet])
            : Database::valeur('SELECT 1 FROM calendrier_amis_evenements e JOIN calendriers_amis c ON c.id = e.calendrier_id WHERE e.id = ? AND c.projet_id = ?', [$evenement, $projet]);
        // Et le document est bien dans ce projet : un cours ou dossier lié, ou un fichier déposé.
        $dedans = $typeCible === 'fichier'
            ? Database::valeur('SELECT 1 FROM projet_fichiers WHERE id = ? AND projet_id = ?', [$cible, $projet])
            : Database::valeur('SELECT 1 FROM projet_liens WHERE projet_id = ? AND type = ? AND cible_id = ?', [$projet, $typeCible, $cible]);
        if ($duProjet === null || $duProjet === false || $dedans === null || $dedans === false) {
            return t('cam.err.lien_inconnu');
        }
        $neuf = Database::run(
            'INSERT IGNORE INTO projet_evenement_liens (projet_id, evenement_type, evenement_id, cible_type, cible_id, ajoute_par)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$projet, $typeEvenement, $evenement, $typeCible, $cible, $moi]
        )->rowCount() > 0;

        return $neuf ? null : t('cam.err.lien_existe');
    }

    /**
     * Retire un lien (le document et l'évènement restent) : qui l'a posé, ou un administrateur du projet.
     *
     * @return ?array{projet_id: int, evenement_type: string, evenement_id: int}  l'évènement, ou null si ce n'est pas à cette personne de le retirer
     */
    public static function delier(int $moi, int $lienId): ?array
    {
        $lien = Database::one('SELECT * FROM projet_evenement_liens WHERE id = ?', [$lienId]);
        if ($lien === null) {
            return null;
        }
        $projet = Travaux::projet((int) $lien['projet_id'], $moi);
        if ($projet === null || ((int) ($lien['ajoute_par'] ?? 0) !== $moi && $projet['role'] !== 'admin')) {
            return null;
        }
        Database::run('DELETE FROM projet_evenement_liens WHERE id = ?', [$lienId]);

        return ['projet_id' => (int) $lien['projet_id'], 'evenement_type' => (string) $lien['evenement_type'], 'evenement_id' => (int) $lien['evenement_id']];
    }

    /** Efface les liens d'un évènement qui disparaît. */
    public static function oublier(string $typeEvenement, int $evenement): void
    {
        Database::run('DELETE FROM projet_evenement_liens WHERE evenement_type = ? AND evenement_id = ?', [$typeEvenement, $evenement]);
    }

    /**
     * Les évènements du projet auxquels un document est lié (pour l'afficher sur le document).
     *
     * @return list<array{titre: string, debut: string, url: string}>
     */
    public static function evenementsDe(int $projet, string $typeCible, int $cible): array
    {
        $evenements = [];
        foreach (Database::all(
            'SELECT l.evenement_type, l.evenement_id,
                    COALESCE(e.titre, pe.titre) AS titre, COALESCE(e.debut, pe.debut) AS debut
               FROM projet_evenement_liens l
               LEFT JOIN calendrier_amis_evenements e ON l.evenement_type = \'evenement\' AND e.id = l.evenement_id
               LEFT JOIN projet_echeances pe ON l.evenement_type = \'echeance\' AND pe.id = l.evenement_id
              WHERE l.projet_id = ? AND l.cible_type = ? AND l.cible_id = ?
              ORDER BY COALESCE(e.debut, pe.debut)',
            [$projet, $typeCible, $cible]
        ) as $l) {
            if ($l['titre'] === null) {
                continue;
            }
            $evenements[] = [
                'titre' => (string) $l['titre'],
                'debut' => (string) $l['debut'],
                'url' => $l['evenement_type'] === 'echeance'
                    ? url('travaux/echeances/' . (int) $l['evenement_id'] . '/modifier')
                    : url('calendriers-amis/evenements/' . (int) $l['evenement_id']),
            ];
        }

        return $evenements;
    }
}
