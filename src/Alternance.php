<?php
declare(strict_types=1);

/**
 * L'espace alternance : le rythme école / entreprise, le journal des
 * missions et les documents. Les notes, elles, n'ont besoin de rien ici.
 *
 * Le rythme se pose à la main, période par période : l'école donne rarement
 * un planning régulier. Deux périodes ne se chevauchent jamais — la dernière
 * posée l'emporte et rogne les autres —, si bien qu'un jour a au plus un lieu.
 * Les samedis et dimanches ne comptent pas : une semaine en entreprise se
 * tape du lundi au vendredi ou du lundi au dimanche, et dit la même chose.
 */
final class Alternance
{
    /**
     * Ce qu'un jour peut être. Les congés, les fériés et les absences ne sont
     * ni l'école ni l'entreprise : les compter avec elles fausserait le bilan.
     */
    public const LIEUX = [
        'ecole'      => ['icone' => '🏫', 'nom' => 'École',      'dans' => 'à l’école'],
        'entreprise' => ['icone' => '🏢', 'nom' => 'Entreprise', 'dans' => 'en entreprise'],
        'conges'     => ['icone' => '🌴', 'nom' => 'Congés',     'dans' => 'en congés'],
        'ferie'      => ['icone' => '🎌', 'nom' => 'Férié',      'dans' => 'férié'],
        'absence'    => ['icone' => '🤒', 'nom' => 'Absence',    'dans' => 'absent'],
    ];

    public const CATEGORIES = [
        'contrat'    => ['icone' => '📝', 'nom' => 'Contrat'],
        'livret'     => ['icone' => '📘', 'nom' => 'Livret d’apprentissage'],
        'evaluation' => ['icone' => '⭐', 'nom' => 'Évaluations'],
        'rapport'    => ['icone' => '📄', 'nom' => 'Rapport'],
        'autre'      => ['icone' => '📎', 'nom' => 'Autres documents'],
    ];

    /** Une période ne dépasse pas trois ans : c'est déjà un contrat entier. */
    public const JOURS_MAX = 1100;

    /** Les dates du contrat qui méritent d'être posées au calendrier. */
    public const ECHEANCES = [
        'debut'          => ['titre' => 'Début du contrat d’alternance', 'libelle' => 'Début du contrat'],
        'fin'            => ['titre' => 'Fin du contrat d’alternance',   'libelle' => 'Fin du contrat'],
        'remise_rapport' => ['titre' => 'Remise du rapport d’alternance', 'libelle' => 'Remise du rapport'],
        'soutenance'     => ['titre' => 'Soutenance d’alternance',        'libelle' => 'Soutenance'],
    ];

    private const JOURS_COURTS = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
    private const MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
        'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** Les documents vivent à côté des pièces jointes des cours, pas avec elles. */
    public static function dossier(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'alternance';
    }

    /** « ven. 3 oct. », l'année en plus quand ce n'est pas celle-ci. */
    public static function jourCourt(string $date): string
    {
        $t = strtotime($date);
        if ($t === false) {
            return $date;
        }
        $texte = self::JOURS_COURTS[(int) date('N', $t) - 1] . ' ' . date('j', $t)
            . ' ' . self::MOIS_COURTS[(int) date('n', $t) - 1];

        return date('Y', $t) === date('Y') ? $texte : $texte . ' ' . date('Y', $t);
    }

    /** Une date « Y-m-d » qui existe vraiment, ou null. */
    public static function dateValide(string $date): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date ? $date : null;
    }

    /** Le lundi de la semaine d'une date. */
    public static function lundi(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
    }

    private static function estWeekEnd(DateTimeImmutable $jour): bool
    {
        return (int) $jour->format('N') >= 6;
    }

    // --- Le rythme -------------------------------------------------------------

    /** Toutes les périodes, dans l'ordre du temps. */
    public static function periodes(int $userId): array
    {
        return Database::all(
            'SELECT * FROM alternance_periodes WHERE user_id = ? ORDER BY debut, id', [$userId]);
    }

    /**
     * Pose une période, ou la déplace quand $id est donné. Celles qu'elle
     * recouvre sont rognées, coupées en deux ou effacées : la dernière saisie
     * dit vrai, et un jour n'a jamais deux lieux.
     */
    public static function poserPeriode(int $userId, string $lieu, string $debut, string $fin,
                                        ?string $note, ?int $id = null): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $veille = (new DateTimeImmutable($debut))->modify('-1 day')->format('Y-m-d');
            $lendemain = (new DateTimeImmutable($fin))->modify('+1 day')->format('Y-m-d');

            $recouvertes = Database::all(
                'SELECT * FROM alternance_periodes
                 WHERE user_id = ? AND id <> ? AND debut <= ? AND fin >= ?',
                [$userId, $id ?? 0, $fin, $debut]);
            foreach ($recouvertes as $p) {
                $avant = $p['debut'] < $debut;
                $apres = $p['fin'] > $fin;
                if ($avant && $apres) {
                    Database::run('UPDATE alternance_periodes SET fin = ? WHERE id = ?', [$veille, $p['id']]);
                    Database::run(
                        'INSERT INTO alternance_periodes (user_id, lieu, debut, fin, note) VALUES (?, ?, ?, ?, ?)',
                        [$userId, $p['lieu'], $lendemain, $p['fin'], $p['note']]);
                } elseif ($avant) {
                    Database::run('UPDATE alternance_periodes SET fin = ? WHERE id = ?', [$veille, $p['id']]);
                } elseif ($apres) {
                    Database::run('UPDATE alternance_periodes SET debut = ? WHERE id = ?', [$lendemain, $p['id']]);
                } else {
                    Database::run('DELETE FROM alternance_periodes WHERE id = ?', [$p['id']]);
                }
            }

            if ($id === null) {
                Database::run(
                    'INSERT INTO alternance_periodes (user_id, lieu, debut, fin, note) VALUES (?, ?, ?, ?, ?)',
                    [$userId, $lieu, $debut, $fin, $note]);
                $id = Database::dernierId();
            } else {
                Database::run(
                    'UPDATE alternance_periodes SET lieu = ?, debut = ?, fin = ?, note = ? WHERE id = ? AND user_id = ?',
                    [$lieu, $debut, $fin, $note, $id, $userId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    /**
     * Le lieu de chaque jour de semaine entre deux dates, pour le calendrier :
     * [« Y-m-d » => ['lieu' => …, 'note' => …]]. Les week-ends restent vides.
     */
    public static function lieuxEntre(int $userId, DateTimeInterface $debut, DateTimeInterface $fin): array
    {
        $du = $debut->format('Y-m-d');
        $au = $fin->format('Y-m-d');
        $jours = [];
        foreach (Database::all(
            'SELECT lieu, debut, fin, note FROM alternance_periodes
             WHERE user_id = ? AND debut <= ? AND fin >= ? ORDER BY debut',
            [$userId, $au, $du]) as $p) {
            $jour = new DateTimeImmutable(max($p['debut'], $du));
            $dernier = min($p['fin'], $au);
            while ($jour->format('Y-m-d') <= $dernier) {
                if (!self::estWeekEnd($jour)) {
                    $jours[$jour->format('Y-m-d')] = ['lieu' => $p['lieu'], 'note' => $p['note']];
                }
                $jour = $jour->modify('+1 day');
            }
        }

        return $jours;
    }

    /** Le lieu d'un jour, ou null (rien de posé, ou week-end). */
    public static function lieuDuJour(int $userId, string $jour): ?array
    {
        $d = new DateTimeImmutable($jour);

        return self::lieuxEntre($userId, $d, $d)[$jour] ?? null;
    }

    /**
     * Où l'on en est, pour le bandeau de l'espace : le lieu d'aujourd'hui
     * jusqu'à quand, puis la période suivante. Null quand rien n'est posé.
     *
     * @return array{maintenant: ?array, ensuite: ?array}|null
     */
    public static function situation(int $userId): ?array
    {
        $auj = date('Y-m-d');
        $maintenant = null;
        if (!self::estWeekEnd(new DateTimeImmutable($auj))) {
            $maintenant = Database::one(
                'SELECT * FROM alternance_periodes WHERE user_id = ? AND debut <= ? AND fin >= ?',
                [$userId, $auj, $auj]);
        }
        /*
         * La suite : la première période qui a encore un jour de semaine à
         * venir. Un samedi, celle qui a commencé la veille et reprend lundi
         * compte — elle s'annonce alors à partir de lundi.
         */
        $apres = (new DateTimeImmutable($maintenant['fin'] ?? $auj))->modify($maintenant === null ? '+0 day' : '+1 day');
        while (self::estWeekEnd($apres)) {
            $apres = $apres->modify('+1 day');
        }
        $ensuite = null;
        foreach (Database::all(
            'SELECT * FROM alternance_periodes WHERE user_id = ? AND fin >= ? ORDER BY debut LIMIT 5',
            [$userId, $apres->format('Y-m-d')]) as $p) {
            $p['debut'] = max($p['debut'], $apres->format('Y-m-d'));
            if (self::joursOuvres($p['debut'], $p['fin']) > 0) {
                $ensuite = $p;
                break;
            }
        }

        return $maintenant === null && $ensuite === null
            ? null
            : ['maintenant' => $maintenant, 'ensuite' => $ensuite];
    }

    /**
     * Les jours de semaine posés, lieu par lieu : en tout et encore à venir
     * (aujourd'hui compris).
     *
     * @return array<string, array{total: int, a_venir: int}>
     */
    public static function bilan(int $userId): array
    {
        $bilan = array_fill_keys(array_keys(self::LIEUX), ['total' => 0, 'a_venir' => 0]);
        $auj = date('Y-m-d');
        foreach (self::periodes($userId) as $p) {
            $jour = new DateTimeImmutable($p['debut']);
            while ($jour->format('Y-m-d') <= $p['fin']) {
                if (!self::estWeekEnd($jour)) {
                    $bilan[$p['lieu']]['total']++;
                    if ($jour->format('Y-m-d') >= $auj) {
                        $bilan[$p['lieu']]['a_venir']++;
                    }
                }
                $jour = $jour->modify('+1 day');
            }
        }

        return $bilan;
    }

    /** Le nombre de jours de semaine d'une période. */
    public static function joursOuvres(string $debut, string $fin): int
    {
        $n = 0;
        $jour = new DateTimeImmutable($debut);
        while ($jour->format('Y-m-d') <= $fin) {
            $n += self::estWeekEnd($jour) ? 0 : 1;
            $jour = $jour->modify('+1 day');
        }

        return $n;
    }

    // --- La fiche de l'alternance ----------------------------------------------

    /** La fiche du compte : entreprise, tuteur, dates. Toujours un tableau. */
    public static function contrat(int $userId): array
    {
        $vide = array_fill_keys(['entreprise', 'adresse', 'poste', 'tuteur', 'tuteur_email',
            'tuteur_tel', 'referent', 'debut', 'fin', 'remise_rapport', 'soutenance'], null);

        return (Database::one('SELECT * FROM alternance_contrat WHERE user_id = ?', [$userId]) ?? []) + $vide;
    }

    /** Une fiche encore vide ne s'affiche pas ailleurs dans l'application. */
    public static function ficheRemplie(array $contrat): bool
    {
        foreach ($contrat as $cle => $valeur) {
            if ($cle !== 'user_id' && $cle !== 'updated_at' && trim((string) $valeur) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Où l'on en est du contrat : la part écoulée, en jours de semaine.
     * Null tant que les deux dates ne sont pas données.
     *
     * @return array{faits: int, total: int, part: int}|null
     */
    public static function avancementContrat(array $contrat): ?array
    {
        $debut = (string) ($contrat['debut'] ?? '');
        $fin = (string) ($contrat['fin'] ?? '');
        if ($debut === '' || $fin === '' || $fin < $debut) {
            return null;
        }
        $auj = date('Y-m-d');
        $total = self::joursOuvres($debut, $fin);
        $faits = $auj < $debut ? 0 : self::joursOuvres($debut, min($auj, $fin));

        return ['faits' => $faits, 'total' => $total,
                'part' => $total === 0 ? 0 : (int) round($faits / $total * 100)];
    }

    // --- Le journal ------------------------------------------------------------

    /** Les compétences tapées à la suite, séparées par des virgules ou des retours. */
    public static function competences(?string $texte): array
    {
        $morceaux = preg_split('/[,;\n\r]+/u', (string) $texte) ?: [];
        $vues = [];
        foreach ($morceaux as $m) {
            $m = trim($m);
            if ($m !== '' && !isset($vues[mb_strtolower($m)])) {
                $vues[mb_strtolower($m)] = $m;
            }
        }

        return array_values($vues);
    }

    /**
     * Les semaines passées en entreprise, jusqu'à celle-ci, dont la page du
     * journal reste à écrire : ce sont elles qu'on oublie. Douze au plus.
     *
     * @return list<string> leurs lundis, la plus récente d'abord
     */
    public static function semainesAEcrire(int $userId): array
    {
        $lundi = new DateTimeImmutable(self::lundi(date('Y-m-d')));
        $depuis = $lundi->modify('-11 weeks');
        $jours = self::lieuxEntre($userId, $depuis, $lundi->modify('+4 days'));
        $ecrites = array_flip(array_column(Database::all(
            'SELECT semaine FROM alternance_journal WHERE user_id = ? AND semaine >= ?',
            [$userId, $depuis->format('Y-m-d')]), 'semaine'));

        $semaines = [];
        foreach ($jours as $jour => $l) {
            if ($l['lieu'] === 'entreprise') {
                $semaines[self::lundi($jour)] = true;
            }
        }
        $semaines = array_keys(array_diff_key($semaines, $ecrites));
        rsort($semaines);

        return $semaines;
    }

    // --- Les documents ---------------------------------------------------------

    /** @return string[] les erreurs rencontrées */
    public static function deposer(array $fichiers, int $userId, string $categorie): array
    {
        $dossier = self::dossier();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return ['Impossible de créer le dossier de stockage des documents.'];
        }

        return Fichiers::recevoir($fichiers, $dossier,
            static function (string $nomOrigine, string $nomStocke, string $mime, int $taille) use ($userId, $categorie): void {
                Database::run(
                    'INSERT INTO alternance_documents (user_id, categorie, nom_origine, nom_stocke, mime, taille)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$userId, $categorie, mb_substr($nomOrigine, 0, 255), $nomStocke, $mime, $taille]);
            });
    }

    /** Efface un document, en base et sur le disque, s'il est bien à nous. */
    public static function supprimerDocument(int $userId, int $id): ?string
    {
        $doc = Database::one('SELECT * FROM alternance_documents WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($doc === null) {
            return null;
        }
        $chemin = self::dossier() . DIRECTORY_SEPARATOR . basename((string) $doc['nom_stocke']);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
        Database::run('DELETE FROM alternance_documents WHERE id = ?', [$id]);

        return (string) $doc['nom_origine'];
    }
}
