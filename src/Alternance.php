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

    // --- Importer un planning --------------------------------------------------

    /** Ce qu'un titre d'agenda laisse deviner du lieu. */
    private const MOTS_DES_LIEUX = [
        'entreprise' => ['entreprise', 'boite', 'boîte', 'societe', 'société', 'travail', 'stage', 'pro', 'alternance'],
        'ecole'      => ['ecole', 'école', 'cfa', 'cours', 'universite', 'université', 'fac', 'iut', 'bts', 'formation', 'centre'],
        'conges'     => ['conge', 'congé', 'vacance', 'repos', 'rtt'],
        'ferie'      => ['ferie', 'férié', 'jour ferie'],
        'absence'    => ['absence', 'absent', 'maladie', 'arret', 'arrêt'],
    ];

    /** Le lieu que dit un intitulé, ou celui qu'on a choisi par défaut. */
    public static function lieuDepuisTitre(string $titre, string $defaut): string
    {
        $titre = mb_strtolower($titre);
        foreach (self::MOTS_DES_LIEUX as $lieu => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($titre, $mot)) {
                    return $lieu;
                }
            }
        }

        return $defaut;
    }

    /**
     * Les périodes que contient un fichier : l'agenda de l'école (.ics) ou un
     * tableau (CSV, une ligne par période : début ; fin ; lieu ; précision).
     * Rien n'est écrit ici — l'appelant décide.
     *
     * @return array{periodes: list<array{lieu: string, debut: string, fin: string, note: ?string}>, ignorees: int}
     */
    public static function lirePlanning(string $contenu, string $defaut): array
    {
        $contenu = str_replace(["\r\n", "\r"], "\n", ltrim($contenu, "\u{FEFF} \n"));

        return str_contains($contenu, 'BEGIN:VEVENT')
            ? self::lireIcs($contenu, $defaut)
            : self::lireTableau($contenu, $defaut);
    }

    /** @return array{periodes: list<array>, ignorees: int} */
    private static function lireIcs(string $contenu, string $defaut): array
    {
        // Une ligne repliée reprend par une espace ou une tabulation.
        $contenu = (string) preg_replace('/\n[ \t]/', '', $contenu);
        $periodes = [];
        $ignorees = 0;

        foreach (explode('BEGIN:VEVENT', $contenu) as $rang => $bloc) {
            if ($rang === 0) {
                continue;
            }
            $bloc = explode('END:VEVENT', $bloc)[0];
            $jour = static function (string $nom) use ($bloc): ?string {
                if (preg_match('/^' . $nom . '[^:\n]*:(\d{8})/mi', $bloc, $m) !== 1) {
                    return null;
                }
                return substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
            };
            $debut = $jour('DTSTART');
            if ($debut === null || self::dateValide($debut) === null) {
                $ignorees++;
                continue;
            }
            $fin = $jour('DTEND');
            // Une journée entière finit le lendemain : on revient au dernier jour.
            $journee = preg_match('/^DTSTART;VALUE=DATE:/mi', $bloc) === 1;
            if ($fin !== null && self::dateValide($fin) !== null) {
                $fin = $journee ? (new DateTimeImmutable($fin))->modify('-1 day')->format('Y-m-d') : $fin;
            }
            if ($fin === null || $fin < $debut) {
                $fin = $debut;
            }
            $titre = preg_match('/^SUMMARY[^:\n]*:(.*)$/mi', $bloc, $m) === 1
                ? trim(str_replace(['\\,', '\\;', '\\n', '\\\\'], [',', ';', ' ', '\\'], $m[1])) : '';

            $periodes[] = ['lieu' => self::lieuDepuisTitre($titre, $defaut), 'debut' => $debut, 'fin' => $fin,
                           'note' => mb_substr($titre, 0, 200) ?: null];
        }

        return ['periodes' => $periodes, 'ignorees' => $ignorees];
    }

    /** @return array{periodes: list<array>, ignorees: int} */
    private static function lireTableau(string $contenu, string $defaut): array
    {
        $periodes = [];
        $ignorees = 0;

        foreach (explode("\n", $contenu) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '') {
                continue;
            }
            $cases = array_map('trim', preg_split('/[;\t,]/', $ligne) ?: []);
            $debut = self::dateValide(self::dateEcrite($cases[0] ?? ''));
            if ($debut === null) {
                $ignorees++;   // L'en-tête du tableau tombe ici, et c'est très bien.
                continue;
            }
            $fin = self::dateValide(self::dateEcrite($cases[1] ?? '')) ?? $debut;
            $lieu = self::lieuDepuisTitre($cases[2] ?? '', $defaut);
            $note = mb_substr(trim((string) ($cases[3] ?? '')), 0, 200) ?: null;

            $periodes[] = ['lieu' => $lieu, 'debut' => $debut, 'fin' => max($debut, $fin), 'note' => $note];
        }

        return ['periodes' => $periodes, 'ignorees' => $ignorees];
    }

    /** « 05/10/2026 » comme « 2026-10-05 » : un tableau s'écrit des deux façons. */
    private static function dateEcrite(string $valeur): string
    {
        $valeur = trim($valeur);

        return preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $valeur, $m) === 1
            ? sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1])
            : $valeur;
    }

    // --- Le rythme dans un autre agenda ----------------------------------------

    /**
     * Le jeton du lien d'abonnement, créé au premier besoin. Le renouveler
     * coupe l'ancien lien : c'est ce qu'on fait quand on l'a partagé de trop.
     */
    public static function jetonIcs(int $userId, bool $renouveler = false): string
    {
        $jeton = bin2hex(random_bytes(16));
        Database::run(
            'INSERT INTO alternance_contrat (user_id, jeton_ics) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE jeton_ics = ' . ($renouveler ? 'VALUES(jeton_ics)' : 'COALESCE(jeton_ics, VALUES(jeton_ics))'),
            [$userId, $jeton]);

        return (string) Database::valeur('SELECT jeton_ics FROM alternance_contrat WHERE user_id = ?', [$userId]);
    }

    /** L'adresse entière du lien d'abonnement : elle part dans un autre agenda. */
    public static function adresseIcs(string $jeton): string
    {
        $site = Reinitialisation::adresseDuSite();
        if ($site === null) {
            $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
            $site = ($https ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return $site . url('alternance/rythme/' . $jeton . '.ics');
    }

    /** À qui appartient ce lien d'abonnement ? */
    public static function parJetonIcs(string $jeton): ?int
    {
        $userId = Database::valeur('SELECT user_id FROM alternance_contrat WHERE jeton_ics = ?', [$jeton]);

        return $userId === null || $userId === false ? null : (int) $userId;
    }

    /**
     * Le rythme en iCalendar : une journée entière par période, que l'agenda
     * d'Outlook ou de Google relit tout seul. Les périodes déplacées suivent,
     * puisque chacune garde son identifiant.
     */
    public static function icsRythme(int $userId): string
    {
        $echapper = static fn (string $t): string => str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $t);

        $lignes = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Mes Cours//Alternance//FR',
            'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:Alternance',
            // Une demi-journée : le délai au bout duquel un agenda relit le lien.
            'REFRESH-INTERVAL;VALUE=DURATION:PT12H', 'X-PUBLISHED-TTL:PT12H'];

        foreach (self::periodes($userId) as $p) {
            $lieu = self::LIEUX[$p['lieu']];
            $lignes[] = 'BEGIN:VEVENT';
            $lignes[] = 'UID:alternance-periode-' . (int) $p['id'] . '@mes-cours';
            $lignes[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lignes[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', (string) $p['debut']);
            // Une journée entière finit le lendemain, par convention.
            $lignes[] = 'DTEND;VALUE=DATE:'
                . (new DateTimeImmutable((string) $p['fin']))->modify('+1 day')->format('Ymd');
            $lignes[] = 'SUMMARY:' . $echapper($lieu['icone'] . ' ' . $lieu['nom']);
            if (trim((string) $p['note']) !== '') {
                $lignes[] = 'DESCRIPTION:' . $echapper((string) $p['note']);
            }
            $lignes[] = 'TRANSP:TRANSPARENT';
            $lignes[] = 'END:VEVENT';
        }
        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", $lignes) . "\r\n";
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
     * Toutes les compétences du journal, la plus travaillée en tête, avec les
     * semaines où elles reviennent : c'est ce qu'on recopie dans le livret, et
     * ce qui montre celles qu'on n'a pas encore vues.
     *
     * @return list<array{nom: string, semaines: list<string>}>
     */
    public static function bilanCompetences(int $userId): array
    {
        $vues = [];
        foreach (Database::all(
            'SELECT semaine, competences FROM alternance_journal
             WHERE user_id = ? AND competences IS NOT NULL ORDER BY semaine DESC', [$userId]) as $page) {
            foreach (self::competences($page['competences']) as $competence) {
                $cle = mb_strtolower($competence);
                $vues[$cle] ??= ['nom' => $competence, 'semaines' => []];
                $vues[$cle]['semaines'][] = (string) $page['semaine'];
            }
        }
        usort($vues, static fn (array $a, array $b): int =>
            [count($b['semaines']), mb_strtolower($a['nom'])] <=> [count($a['semaines']), mb_strtolower($b['nom'])]);

        return $vues;
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

    // --- Ce qu'il y a à faire --------------------------------------------------

    /** La liste de tâches de l'alternance, créée au premier besoin. */
    public static function listeDesTaches(int $userId): int
    {
        $liste = Database::valeur(
            'SELECT id FROM listes_taches WHERE user_id = ? AND nom = ? LIMIT 1', [$userId, self::LISTE]);
        if ($liste !== null && $liste !== false) {
            return (int) $liste;
        }

        $rang = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM listes_taches WHERE user_id = ?', [$userId]);
        Database::run(
            'INSERT INTO listes_taches (user_id, nom, couleur, icone, position, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, self::LISTE, '#b45309', '🏢', $rang]);

        return Database::dernierId();
    }

    /** Le nom de cette liste : on la retrouve à son nom, et on n'en fait qu'une. */
    public const LISTE = 'Alternance';

    /**
     * Ce qu'une note laisse à faire : les lignes cochables qu'on y a écrites.
     * « - [ ] rappeler le fournisseur », « [ ] », « ☐ » — celles déjà cochées
     * sont laissées de côté, elles sont faites.
     *
     * @return list<string>
     */
    public static function aFaireDans(?string $contenu): array
    {
        $aFaire = [];
        foreach (preg_split('/\r\n|\r|\n/', TexteRiche::versTexte($contenu)) ?: [] as $ligne) {
            $ligne = trim(str_replace("\u{00A0}", ' ', $ligne));
            if (preg_match('/^(?:[-*•]\s*)?(?:\[\s*\]|\[\s*[xX✓]\s*\]|☐|☑|✅)\s*(.*)$/u', $ligne, $m) !== 1) {
                continue;
            }
            $cochee = preg_match('/^(?:[-*•]\s*)?(?:\[\s*[xX✓]\s*\]|☑|✅)/u', $ligne) === 1;
            $texte = trim($m[1]);
            if (!$cochee && $texte !== '') {
                $aFaire[] = mb_substr($texte, 0, 200);
            }
        }

        return array_values(array_unique($aFaire));
    }

    /**
     * Range ces tâches dans la liste de l'alternance. Celles qui y sont déjà,
     * et pas encore faites, ne sont pas écrites deux fois.
     *
     * @param list<string> $titres
     * @return array{liste: int, ajoutees: int, connues: int}
     */
    public static function poserTaches(int $userId, array $titres, ?string $echeance = null): array
    {
        $liste = self::listeDesTaches($userId);
        $ajoutees = 0;
        $connues = 0;

        foreach ($titres as $titre) {
            $titre = mb_substr(trim($titre), 0, 200);
            if ($titre === '') {
                continue;
            }
            $deja = Database::valeur(
                'SELECT id FROM taches WHERE user_id = ? AND liste_id = ? AND titre = ? AND faite = 0',
                [$userId, $liste, $titre]);
            if ($deja !== null && $deja !== false) {
                $connues++;
                continue;
            }
            $rang = (int) Database::valeur(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM taches WHERE user_id = ? AND liste_id = ?', [$userId, $liste]);
            Database::run(
                'INSERT INTO taches (user_id, liste_id, titre, echeance, position) VALUES (?, ?, ?, ?, ?)',
                [$userId, $liste, $titre, $echeance, $rang]);
            $ajoutees++;
        }

        return ['liste' => $liste, 'ajoutees' => $ajoutees, 'connues' => $connues];
    }

    /**
     * Ce qui reste à faire dans la liste de l'alternance, échéances d'abord.
     *
     * @return list<array>
     */
    public static function tachesAFaire(int $userId, int $limite = 8): array
    {
        $liste = Database::valeur(
            'SELECT id FROM listes_taches WHERE user_id = ? AND nom = ? LIMIT 1', [$userId, self::LISTE]);
        if ($liste === null || $liste === false) {
            return [];
        }

        return Database::all(
            'SELECT id, titre, echeance FROM taches
             WHERE user_id = ? AND liste_id = ? AND faite = 0
             ORDER BY echeance IS NULL, echeance, position LIMIT ' . max(1, $limite),
            [$userId, (int) $liste]);
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
