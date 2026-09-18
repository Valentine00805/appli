<?php
declare(strict_types=1);

/**
 * Ce qui a changé entre deux versions d'un texte : ce qui a été ajouté, ce
 * qui a été retiré.
 *
 * La comparaison se fait en deux temps. D'abord paragraphe par paragraphe :
 * ceux qui n'ont pas bougé restent tels quels. Puis, dans un paragraphe
 * retouché, mot par mot — on voit le mot changé, pas tout le paragraphe.
 *
 * Les deux temps cherchent la plus longue suite commune. Son coût croît
 * comme le produit des deux longueurs : au-delà d'une taille raisonnable, on
 * renonce au détail et l'on montre l'avant retiré, l'après ajouté.
 */
final class Difference
{
    /** Cases au plus dans une table de comparaison. */
    private const CASES_MAX = 400_000;

    /**
     * Les paragraphes d'un texte de l'application, en texte brut.
     *
     * @return list<string>
     */
    public static function paragraphes(string $texte): array
    {
        $html = TexteRiche::versHtml($texte);
        // Chaque bloc, chaque retour à la ligne, fait un paragraphe.
        $html = (string) preg_replace('#<br\s*/?>|</?(p|div|li|ul|ol|h[1-6]|blockquote|tr|pre)\b[^>]*>#i', "\n", $html);
        $brut = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lignes = preg_split('/\n+/u', $brut) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $l): string => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $l)), $lignes),
            static fn (string $l): bool => $l !== ''
        ));
    }

    /**
     * Ce qui sépare deux textes, paragraphe par paragraphe.
     *
     * Chaque élément vaut ['etat' => 'egal'|'ajout'|'retrait', 'texte' => …],
     * ou ['etat' => 'modifie', 'morceaux' => [[etat, texte], …]] pour un
     * paragraphe retouché.
     *
     * @return list<array<string, mixed>>
     */
    public static function comparer(string $avant, string $apres): array
    {
        $a = self::paragraphes($avant);
        $b = self::paragraphes($apres);
        $suite = self::suite($a, $b);
        if ($suite === null) {
            return array_merge(
                array_map(static fn (string $p): array => ['etat' => 'retrait', 'texte' => $p], $a),
                array_map(static fn (string $p): array => ['etat' => 'ajout', 'texte' => $p], $b)
            );
        }

        // Un paragraphe retiré et un paragraphe ajouté au même endroit sont
        // souvent le même, retouché : on les compare alors mot à mot. Mais
        // seulement s'ils se ressemblent — rapprocher deux phrases sans rapport
        // mêlerait leurs mots en un texte illisible.
        $resultat = [];
        $retires = [];
        $ajoutes = [];
        $vider = static function () use (&$resultat, &$retires, &$ajoutes): void {
            $suivant = 0;
            foreach ($retires as $retire) {
                $trouve = null;
                for ($k = $suivant; $k < count($ajoutes); $k++) {
                    if (self::ressemblance($retire, $ajoutes[$k]) >= 0.5) {
                        $trouve = $k;
                        break;
                    }
                }
                if ($trouve === null) {
                    $resultat[] = ['etat' => 'retrait', 'texte' => $retire];
                    continue;
                }
                // Ce qui s'intercale avant le paragraphe retrouvé est ajouté.
                for ($k = $suivant; $k < $trouve; $k++) {
                    $resultat[] = ['etat' => 'ajout', 'texte' => $ajoutes[$k]];
                }
                $resultat[] = ['etat' => 'modifie', 'morceaux' => self::mots($retire, $ajoutes[$trouve])];
                $suivant = $trouve + 1;
            }
            for ($k = $suivant; $k < count($ajoutes); $k++) {
                $resultat[] = ['etat' => 'ajout', 'texte' => $ajoutes[$k]];
            }
            $retires = [];
            $ajoutes = [];
        };
        foreach ($suite as [$etat, $texte]) {
            if ($etat === 'egal') {
                $vider();
                $resultat[] = ['etat' => 'egal', 'texte' => $texte];
            } elseif ($etat === 'retrait') {
                $retires[] = $texte;
            } else {
                $ajoutes[] = $texte;
            }
        }
        $vider();

        return $resultat;
    }

    /**
     * À quel point deux paragraphes se ressemblent : la part de leurs mots
     * qu'ils ont en commun, de 0 (rien) à 1 (les mêmes).
     */
    private static function ressemblance(string $a, string $b): float
    {
        $mots = static fn (string $t): array => array_unique(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($t), -1, PREG_SPLIT_NO_EMPTY) ?: []
        );
        $motsA = $mots($a);
        $motsB = $mots($b);
        $plus = max(count($motsA), count($motsB));

        return $plus === 0 ? 0.0 : count(array_intersect($motsA, $motsB)) / $plus;
    }

    /**
     * Un paragraphe retouché, mot à mot.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function mots(string $avant, string $apres): array
    {
        $a = preg_split('/(\s+)/u', $avant, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/(\s+)/u', $apres, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $suite = self::suite($a, $b);
        if ($suite === null) {
            return [['retrait', $avant], ['ajout', ' ' . $apres]];
        }
        // Les morceaux voisins de même état se rejoignent : une phrase ajoutée
        // se lit d'un bloc, pas mot par mot.
        $morceaux = [];
        foreach ($suite as [$etat, $texte]) {
            $dernier = count($morceaux) - 1;
            if ($dernier >= 0 && $morceaux[$dernier][0] === $etat) {
                $morceaux[$dernier][1] .= $texte;
            } else {
                $morceaux[] = [$etat, $texte];
            }
        }

        return $morceaux;
    }

    /**
     * La plus longue suite commune de deux listes, déroulée : chaque élément
     * est égal, retiré ou ajouté. Null quand la table serait trop grande.
     *
     * @param list<string> $a
     * @param list<string> $b
     * @return ?list<array{0: string, 1: string}>
     */
    private static function suite(array $a, array $b): ?array
    {
        // Le début et la fin communs ne coûtent rien : on les met à part.
        $debut = 0;
        while ($debut < count($a) && $debut < count($b) && $a[$debut] === $b[$debut]) {
            $debut++;
        }
        $fin = 0;
        while ($fin < count($a) - $debut && $fin < count($b) - $debut
            && $a[count($a) - 1 - $fin] === $b[count($b) - 1 - $fin]) {
            $fin++;
        }
        $milieuA = array_slice($a, $debut, count($a) - $debut - $fin);
        $milieuB = array_slice($b, $debut, count($b) - $debut - $fin);
        $n = count($milieuA);
        $m = count($milieuB);
        if (($n + 1) * ($m + 1) > self::CASES_MAX) {
            return null;
        }

        // La table des longueurs, puis le chemin qui la remonte.
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $milieuA[$i] === $milieuB[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }
        $suite = array_map(static fn (string $x): array => ['egal', $x], array_slice($a, 0, $debut));
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($milieuA[$i] === $milieuB[$j]) {
                $suite[] = ['egal', $milieuA[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $suite[] = ['retrait', $milieuA[$i++]];
            } else {
                $suite[] = ['ajout', $milieuB[$j++]];
            }
        }
        while ($i < $n) {
            $suite[] = ['retrait', $milieuA[$i++]];
        }
        while ($j < $m) {
            $suite[] = ['ajout', $milieuB[$j++]];
        }
        foreach (array_slice($a, count($a) - $fin) as $x) {
            $suite[] = ['egal', $x];
        }

        return $suite;
    }
}
