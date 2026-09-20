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

    /** Ouvre une session, et rend son identifiant. */
    public static function demarrer(int $userId, ?int $coursId, ?string $sujet, int $minutes): int
    {
        Database::run(
            'INSERT INTO sessions_revision (user_id, cours_id, sujet, minutes_voulues, debut)
             VALUES (?, ?, ?, ?, NOW())',
            [$userId, $coursId, $sujet === null ? null : mb_substr($sujet, 0, 150), $minutes]
        );

        return Database::dernierId();
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
            'par_matiere' => Database::all(
                'SELECT COALESCE(m.nom, "Sans matière") AS matiere, COALESCE(m.couleur, "#94a3b8") AS couleur,
                        SUM(s.secondes) AS secondes
                 FROM sessions_revision s
                 LEFT JOIN cours c ON c.id = s.cours_id
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
