<?php
declare(strict_types=1);

/**
 * Les sessions de révision : le temps qu'on y passe, et ce qu'il en reste.
 *
 * Une session s'ouvre quand on la démarre et se referme quand on la termine.
 * Seul le temps réellement travaillé compte — le minuteur s'arrête en pause,
 * et une session abandonnée sans être refermée reste à zéro : mieux vaut un
 * compteur qui dit moins que la vérité qu'un compteur qui la gonfle.
 */
final class Focus
{
    /** Les rythmes proposés : travail, puis pause. */
    public const RYTHMES = [
        25 => ['pause' => 5,  'nom' => '25 min de travail, 5 de pause'],
        50 => ['pause' => 10, 'nom' => '50 min de travail, 10 de pause'],
        15 => ['pause' => 3,  'nom' => '15 min, pour s’y remettre'],
        90 => ['pause' => 15, 'nom' => '90 min, pour un gros morceau'],
    ];

    /** En deçà, ce n'était pas une session : on ne la compte pas. */
    public const SECONDES_MIN = 60;

    public const RESSENTIS = [
        'bien'  => ['icone' => '😀', 'nom' => 'Ça a bien marché'],
        'moyen' => ['icone' => '😐', 'nom' => 'Moyen'],
        'dur'   => ['icone' => '😕', 'nom' => 'Difficile'],
    ];

    /** Le rythme demandé, ou celui par défaut. */
    public static function rythmeValide(mixed $minutes): int
    {
        $minutes = (int) $minutes;

        return isset(self::RYTHMES[$minutes]) ? $minutes : 25;
    }

    /**
     * Ouvre une session, et rend son identifiant.
     *
     * Une session peut porter sur plusieurs cours : le premier est le cours
     * principal — celui qu’on ouvre en arrivant —, et tous sont retenus.
     *
     * @param list<int> $coursIds
     */
    public static function demarrer(int $userId, array $coursIds, ?string $sujet, int $minutes,
                                     bool $nePasDeranger = true): int
    {
        $coursIds = array_values(array_unique(array_map('intval', $coursIds)));
        Database::run(
            'INSERT INTO sessions_revision (user_id, cours_id, sujet, minutes_voulues, ne_pas_deranger, debut)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, $coursIds[0] ?? null, $sujet === null ? null : mb_substr($sujet, 0, 150),
             $minutes, $nePasDeranger ? 1 : 0]
        );
        $id = Database::dernierId();
        foreach ($coursIds as $coursId) {
            Database::run('INSERT IGNORE INTO session_revision_cours (session_id, cours_id) VALUES (?, ?)',
                [$id, $coursId]);
        }

        return $id;
    }

    /**
     * Les cours d’une session, le principal d’abord.
     *
     * @return list<array>
     */
    public static function coursDeLaSession(int $sessionId): array
    {
        return Database::all(
            'SELECT c.id, c.titre, c.contenu, c.fiche_revision, m.nom AS matiere_nom
             FROM session_revision_cours s
             JOIN cours c ON c.id = s.cours_id
             LEFT JOIN matieres m ON m.id = c.matiere_id
             JOIN sessions_revision r ON r.id = s.session_id
             WHERE s.session_id = ?
             ORDER BY (c.id = r.cours_id) DESC, c.titre',
            [$sessionId]
        );
    }

    /**
     * Tous les cours d’un choix : ceux cochés, et ceux des dossiers retenus,
     * sous-dossiers compris. Seuls les siens, et cinquante au plus — au-delà,
     * ce n’est plus une session, c’est une bibliothèque.
     *
     * @param list<mixed> $coursIds
     * @param list<mixed> $dossierIds
     * @return list<int>
     */
    public static function coursChoisis(int $userId, array $coursIds, array $dossierIds): array
    {
        $voulus = array_map('intval', array_filter($coursIds, 'is_numeric'));

        foreach (array_map('intval', array_filter($dossierIds, 'is_numeric')) as $dossierId) {
            if (Database::valeur('SELECT id FROM dossiers WHERE id = ? AND user_id = ?', [$dossierId, $userId]) === null) {
                continue;
            }
            $sous = DossiersController::avecDescendants($userId, $dossierId);
            $voulus = array_merge($voulus, array_column(Database::all(
                'SELECT id FROM cours WHERE user_id = ? AND dossier_id IN ('
                . implode(',', array_fill(0, count($sous), '?')) . ')',
                array_merge([$userId], $sous)), 'id'));
        }

        $voulus = array_values(array_unique(array_map('intval', $voulus)));
        if ($voulus === []) {
            return [];
        }

        // On ne garde que les siens : un identifiant glissé dans le formulaire
        // n’ouvre pas le cours de quelqu’un d’autre.
        return array_map('intval', array_column(Database::all(
            'SELECT id FROM cours WHERE user_id = ? AND id IN ('
            . implode(',', array_fill(0, count($voulus), '?')) . ') ORDER BY titre LIMIT 50',
            array_merge([$userId], $voulus)), 'id'));
    }

    // --- L'objectif de la semaine ----------------------------------------------

    /** Les objectifs proposés, en minutes par semaine. */
    public const OBJECTIFS = [0 => 'Aucun objectif', 60 => '1 h', 120 => '2 h', 180 => '3 h',
        300 => '5 h', 420 => '7 h', 600 => '10 h', 900 => '15 h'];

    /** L'objectif hebdomadaire du compte, en minutes. 0 : aucun. */
    public static function objectif(int $userId): int
    {
        return (int) Database::valeur('SELECT objectif_revision FROM users WHERE id = ?', [$userId]);
    }

    /** Retient l'objectif voulu ; un chiffre inconnu remet « aucun ». */
    public static function changerObjectif(int $userId, mixed $minutes): int
    {
        $minutes = isset(self::OBJECTIFS[(int) $minutes]) ? (int) $minutes : 0;
        Database::run('UPDATE users SET objectif_revision = ? WHERE id = ?', [$minutes, $userId]);

        return $minutes;
    }

    /**
     * Où en est l'objectif de la semaine : la part faite, ce qu'il reste, et
     * ce que cela ferait par jour d'ici dimanche. Null sans objectif.
     *
     * @return array{minutes: int, faites: int, part: int, reste: int, jours: int, par_jour: int}|null
     */
    public static function avancementObjectif(int $userId, int $secondesSemaine): ?array
    {
        $minutes = self::objectif($userId);
        if ($minutes === 0) {
            return null;
        }
        $faites = intdiv($secondesSemaine, 60);
        $reste = max(0, $minutes - $faites);
        // Aujourd'hui compris : la journée n'est pas finie, elle compte encore.
        $jours = max(1, 8 - (int) date('N'));

        return [
            'minutes'  => $minutes,
            'faites'   => $faites,
            'part'     => (int) min(100, round($faites / $minutes * 100)),
            'reste'    => $reste,
            'jours'    => $jours,
            'par_jour' => (int) ceil($reste / $jours),
        ];
    }

    // --- Ce qui suit la session -------------------------------------------------

    /** Le nom de la liste où sont rangées les révisions à venir. */
    public const LISTE = 'Révisions';

    /** Les délais d'une révision espacée : le lendemain, puis trois, puis sept jours. */
    public const ESPACEMENT = [1, 3, 7];

    /**
     * On retient mieux en revoyant plusieurs fois, de plus en plus loin. Une
     * fois la session finie, on pose donc les prochaines : le lendemain, trois
     * jours après, une semaine après. Ce sont des tâches, avec leur échéance —
     * elles arrivent donc dans le rappel du matin, comme le reste.
     *
     * @return array{liste: int, posees: int, connues: int}
     */
    public static function programmerRevisions(int $userId, int $coursId): array
    {
        $titre = (string) Database::valeur('SELECT titre FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]);
        if ($titre === '') {
            return ['liste' => 0, 'posees' => 0, 'connues' => 0];
        }

        $liste = self::listeDesRevisions($userId);
        $libelle = mb_substr('Revoir : ' . $titre, 0, 200);
        $posees = 0;
        $connues = 0;

        foreach (self::ESPACEMENT as $jours) {
            $quand = (new DateTimeImmutable('today'))->modify('+' . $jours . ' days')->format('Y-m-d');
            $deja = Database::valeur(
                'SELECT id FROM taches WHERE user_id = ? AND liste_id = ? AND titre = ? AND echeance = ? AND faite = 0',
                [$userId, $liste, $libelle, $quand]);
            if ($deja !== null && $deja !== false) {
                $connues++;
                continue;
            }
            $rang = (int) Database::valeur(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM taches WHERE user_id = ? AND liste_id = ?', [$userId, $liste]);
            Database::run(
                'INSERT INTO taches (user_id, liste_id, titre, echeance, position) VALUES (?, ?, ?, ?, ?)',
                [$userId, $liste, $libelle, $quand, $rang]);
            $posees++;
        }

        return ['liste' => $liste, 'posees' => $posees, 'connues' => $connues];
    }

    /** La liste « Révisions », créée au premier besoin. */
    public static function listeDesRevisions(int $userId): int
    {
        $liste = Database::valeur(
            'SELECT id FROM listes_taches WHERE user_id = ? AND nom = ? LIMIT 1', [$userId, self::LISTE]);
        if ($liste !== null && $liste !== false) {
            return (int) $liste;
        }

        $rang = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM listes_taches WHERE user_id = ?', [$userId]);
        Database::run(
            'INSERT INTO listes_taches (user_id, nom, couleur, icone, position, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, self::LISTE, '#7c3aed', '🔁', $rang]);

        return Database::dernierId();
    }

    /** Combien de cartes de ce cours sont à revoir aujourd'hui. */
    public static function cartesAReviser(int $userId, ?int $coursId): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM cartes WHERE user_id = ? AND revoir_le <= CURDATE()'
            . ($coursId === null ? '' : ' AND cours_id = ?'),
            $coursId === null ? [$userId] : [$userId, $coursId]);
    }

    /**
     * Pose une session au calendrier : un évènement d'une heure ou d'une
     * demi-heure, selon le rythme, rattaché au cours qu'on y révisera.
     */
    public static function planifier(int $userId, ?int $coursId, string $jour, string $heure, int $minutes): ?int
    {
        if (self::dateValide($jour) === null || preg_match('/^\d{2}:\d{2}$/', $heure) !== 1) {
            return null;
        }
        $debut = new DateTimeImmutable($jour . ' ' . $heure . ':00');
        $titre = 'Révision';
        if ($coursId !== null) {
            $cours = (string) Database::valeur('SELECT titre FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]);
            $titre = $cours === '' ? $titre : mb_substr('Révision : ' . $cours, 0, 200);
        }

        Database::run(
            'INSERT INTO evenements (user_id, cours_id, titre, debut, fin, journee_entiere, rappels)
             VALUES (?, ?, ?, ?, ?, 0, ?)',
            [$userId, $coursId, $titre, $debut->format('Y-m-d H:i:s'),
             $debut->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s'), '15']);

        return Database::dernierId();
    }

    /** Une date « Y-m-d » qui existe vraiment, ou null. */
    private static function dateValide(string $date): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date ? $date : null;
    }

    // --- Ne pas déranger --------------------------------------------------------

    /**
     * Une session est-elle en cours, et demande-t-elle le silence ?
     *
     * Les rappels qui tombent pendant ne partent pas : ils repartiront après.
     * Interrompre une session de révision par une notification de révision,
     * ce serait se tirer dans le pied.
     */
    public static function silence(int $userId): bool
    {
        return Database::valeur(
            'SELECT id FROM sessions_revision
             WHERE user_id = ? AND fin IS NULL AND ne_pas_deranger = 1
               AND debut > (NOW() - INTERVAL 4 HOUR) LIMIT 1',
            [$userId]) !== null;
    }

    /**
     * Referme une session. La durée vient du navigateur, qui seul sait ce qui
     * a été travaillé et ce qui a été mis en pause — mais elle ne peut pas
     * dépasser le temps écoulé depuis le début : on ne se donne pas des
     * heures en changeant l'horloge de son ordinateur.
     */
    public static function terminer(int $userId, int $id, int $secondes, int $pauses, ?string $ressenti): bool
    {
        $session = Database::one(
            'SELECT id, debut, fin FROM sessions_revision WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($session === null || $session['fin'] !== null) {
            return false;
        }

        $ecoule = max(0, time() - strtotime((string) $session['debut']));
        $secondes = max(0, min($secondes, $ecoule));
        Database::run(
            'UPDATE sessions_revision SET secondes = ?, pauses = ?, ressenti = ?, fin = NOW()
             WHERE id = ? AND user_id = ?',
            [$secondes, max(0, min($pauses, 200)), isset(self::RESSENTIS[(string) $ressenti]) ? $ressenti : null,
             $id, $userId]
        );

        return true;
    }

    /** La session ouverte, s'il y en a une, pour la reprendre ou la refermer. */
    public static function enCours(int $userId): ?array
    {
        return Database::one(
            'SELECT s.*, c.titre AS cours_titre FROM sessions_revision s
             LEFT JOIN cours c ON c.id = s.cours_id
             WHERE s.user_id = ? AND s.fin IS NULL AND s.debut > (NOW() - INTERVAL 12 HOUR)
             ORDER BY s.debut DESC LIMIT 1',
            [$userId]
        );
    }

    /**
     * Le suivi : aujourd'hui, cette semaine, et la série de jours d'affilée.
     *
     * @return array{aujourdhui: int, semaine: int, sessions: int, serie: int, par_matiere: list<array>}
     */
    public static function bilan(int $userId): array
    {
        $secondes = static fn (string $depuis): int => (int) Database::valeur(
            'SELECT COALESCE(SUM(secondes), 0) FROM sessions_revision
             WHERE user_id = ? AND secondes >= ? AND debut >= ?',
            [$userId, self::SECONDES_MIN, $depuis]);

        $lundi = (new DateTimeImmutable('monday this week'))->format('Y-m-d 00:00:00');

        return [
            'aujourdhui' => $secondes(date('Y-m-d 00:00:00')),
            'semaine'    => $secondes($lundi),
            'sessions'   => (int) Database::valeur(
                'SELECT COUNT(*) FROM sessions_revision WHERE user_id = ? AND secondes >= ? AND debut >= ?',
                [$userId, self::SECONDES_MIN, $lundi]),
            'serie'      => self::serie($userId),
            /*
             * Une session peut couvrir plusieurs cours : son temps se partage
             * alors à parts égales entre eux. Tout mettre sur le premier
             * donnerait une matière gonflée et une autre à zéro.
             */
            'par_matiere' => Database::all(
                'SELECT COALESCE(m.nom, "Sans matière") AS matiere, COALESCE(m.couleur, "#94a3b8") AS couleur,
                        ROUND(SUM(s.secondes / n.combien)) AS secondes
                 FROM sessions_revision s
                 JOIN (SELECT session_id, COUNT(*) AS combien FROM session_revision_cours GROUP BY session_id) n
                      ON n.session_id = s.id
                 JOIN session_revision_cours sc ON sc.session_id = s.id
                 JOIN cours c ON c.id = sc.cours_id
                 LEFT JOIN matieres m ON m.id = c.matiere_id
                 WHERE s.user_id = ? AND s.secondes >= ? AND s.debut >= ?
                 GROUP BY matiere, couleur ORDER BY secondes DESC',
                [$userId, self::SECONDES_MIN, $lundi]),
        ];
    }

    /**
     * Depuis combien de jours d'affilée révise-t-on ?
     *
     * La série tient tant qu'il n'y a pas de jour vide. Celle qui s'arrête
     * hier compte encore aujourd'hui : on n'a pas encore manqué sa journée,
     * elle n'est pas finie.
     */
    public static function serie(int $userId): int
    {
        $jours = array_column(Database::all(
            'SELECT DISTINCT DATE(debut) AS jour FROM sessions_revision
             WHERE user_id = ? AND secondes >= ? ORDER BY jour DESC LIMIT 400',
            [$userId, self::SECONDES_MIN]), 'jour');
        if ($jours === []) {
            return 0;
        }

        $attendu = new DateTimeImmutable('today');
        if ($jours[0] !== $attendu->format('Y-m-d')) {
            $attendu = $attendu->modify('-1 day');
            if ($jours[0] !== $attendu->format('Y-m-d')) {
                return 0;
            }
        }

        $serie = 0;
        foreach ($jours as $jour) {
            if ($jour !== $attendu->format('Y-m-d')) {
                break;
            }
            $serie++;
            $attendu = $attendu->modify('-1 day');
        }

        return $serie;
    }

    /** Les dernières sessions refermées, pour s'en souvenir. */
    public static function dernieres(int $userId, int $limite = 8): array
    {
        return Database::all(
            'SELECT s.*, c.titre AS cours_titre, m.nom AS matiere_nom, m.couleur AS matiere_couleur
             FROM sessions_revision s
             LEFT JOIN cours c ON c.id = s.cours_id
             LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE s.user_id = ? AND s.fin IS NOT NULL AND s.secondes >= ?
             ORDER BY s.debut DESC LIMIT ' . max(1, $limite),
            [$userId, self::SECONDES_MIN]
        );
    }

    /**
     * La session qu'on vient de finir, si elle est fraîche et portait sur un
     * cours : c'est à ce moment-là qu'il faut proposer la suite.
     */
    public static function sessionRecente(int $userId): ?array
    {
        return Database::one(
            'SELECT s.id, s.cours_id, s.secondes, c.titre AS cours_titre
             FROM sessions_revision s JOIN cours c ON c.id = s.cours_id
             WHERE s.user_id = ? AND s.fin IS NOT NULL AND s.secondes >= ?
               AND s.fin > (NOW() - INTERVAL 3 HOUR)
             ORDER BY s.fin DESC LIMIT 1',
            [$userId, self::SECONDES_MIN]
        );
    }

    /** Le dernier cours révisé : celui qu'on propose de reprendre. */
    public static function dernierCours(int $userId): ?array
    {
        return Database::one(
            'SELECT c.id, c.titre FROM sessions_revision s JOIN cours c ON c.id = s.cours_id
             WHERE s.user_id = ? AND s.secondes >= ? ORDER BY s.debut DESC LIMIT 1',
            [$userId, self::SECONDES_MIN]
        );
    }

    /** « 1 h 05 », « 45 min », « moins d'une minute ». */
    public static function duree(int $secondes): string
    {
        if ($secondes < 60) {
            return 'moins d’une minute';
        }
        $minutes = intdiv($secondes, 60);
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return intdiv($minutes, 60) . ' h' . ($minutes % 60 > 0 ? ' ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : '');
    }
}
