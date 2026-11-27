<?php
declare(strict_types=1);

/**
 * L'avancement d'un quizz : une page web (.html) rangée dans une fiche de révision, jouée sur place, qui signale elle-même où l'on en est.
 *
 * Le quizz tourne enfermé (voir Fichiers::envoyer) : il ne peut parler à personne — sauf à la page qui le contient, par « postMessage », avec trois
 * nombres : les questions faites, les bonnes réponses, le total de la série. L'application en tire deux pourcentages :
 *
 *   - l'avancement : questions faites / total ;
 *   - le score : bonnes réponses / total — c'est lui qui rejoint l'anneau de la fiche (savoir son cours, pas seulement l'avoir parcouru).
 *
 * Elle garde le dernier état signalé. Sans la migration « quizz-resultats », le quizz se joue sans pourcentages.
 */
final class Quizz
{
    /** Le plus de questions qu'on accepte pour une série : au-delà, ce n'est plus un quizz mais une donnée farfelue. */
    public const MAX = 100000;

    /** La migration est-elle passée ? */
    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::valeur('SELECT quizz_total, quizz_fait, quizz_bonnes FROM fichiers LIMIT 1');
                $ok = true;
            } catch (Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Garde l'état signalé par le quizz. Faux si le fichier n'est pas un quizz à moi (une page web rangée dans une fiche) ou si les nombres
     * n'ont pas de sens (négatifs, plus de réponses que de questions, bonnes réponses en trop).
     */
    public static function enregistrer(int $fichierId, int $moi, int $fait, int $bonnes, int $total): bool
    {
        if (!self::disponible() || $total < 1 || $total > self::MAX || $fait < 0 || $fait > $total || $bonnes < 0 || $bonnes > $fait) {
            return false;
        }
        $fichier = Database::one('SELECT nom_origine, pour_fiche FROM fichiers WHERE id = ? AND user_id = ?', [$fichierId, $moi]);
        if ($fichier === null || (int) $fichier['pour_fiche'] !== 1 || !Fichiers::estPageWeb((string) $fichier['nom_origine'])) {
            return false;
        }
        Database::run(
            'UPDATE fichiers SET quizz_total = ?, quizz_fait = ?, quizz_bonnes = ? WHERE id = ? AND user_id = ?',
            [$total, $fait, $bonnes, $fichierId, $moi]
        );

        return true;
    }

    /**
     * Les pourcentages d'un quizz (une ligne de « fichiers »), ou null tant qu'il n'a rien signalé.
     *
     * @return array{fait: int, bonnes: int, total: int, avancement: int, score: int}|null
     */
    public static function mesure(array $fichier): ?array
    {
        $total = (int) ($fichier['quizz_total'] ?? 0);
        if ($total < 1) {
            return null;
        }
        $fait = min($total, (int) ($fichier['quizz_fait'] ?? 0));
        $bonnes = min($fait, (int) ($fichier['quizz_bonnes'] ?? 0));

        return [
            'fait' => $fait, 'bonnes' => $bonnes, 'total' => $total,
            'avancement' => (int) round($fait / $total * 100),
            'score' => (int) round($bonnes / $total * 100),
        ];
    }

    /** Le pourcentage qui compte dans l'anneau de la fiche : le score (null tant que le quizz n'a rien signalé). */
    public static function pourcentage(array $fichier): ?int
    {
        return self::mesure($fichier)['score'] ?? null;
    }

    /** Les colonnes à lire pour mesurer les quizz, quand la migration est passée (à coller dans un SELECT). */
    public static function colonnes(): string
    {
        return self::disponible() ? ', quizz_total, quizz_fait, quizz_bonnes' : '';
    }
}
