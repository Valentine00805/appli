<?php
declare(strict_types=1);

/**
 * Interprétation des relevés tenus au tableur.
 *
 * Ces feuilles suivent toujours la même logique : colonne A la date, B le
 * libellé, C le montant. Les dépenses sont réunies en blocs séparés par une
 * ligne vide, et le libellé du bloc n'apparaît pas sur les lignes elles-mêmes
 * mais dans une colonne de totaux à droite — « Total essence : », « Total repas
 * pour les parents : ». C'est de là qu'on déduit la rubrique.
 *
 * Deux mentions particulières sont reconnues :
 *   « divisier par 2 » (ou « pas 2 ») → seule la moitié est réclamée ;
 *   « pas dans le total » / « à voir avec » → le bloc est gardé hors total.
 */
final class ReleveExcel
{
    private const MOIS = ['janvier', 'février', 'fevrier', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'aout', 'septembre', 'octobre', 'novembre', 'décembre', 'decembre'];

    /**
     * @return array{operations:array, ignorees:int, feuille:array}
     */
    public static function analyser(string $chemin): array
    {
        $lignes = ClasseurLecteur::lire($chemin);
        if ($lignes === []) {
            return ['operations' => [], 'ignorees' => 0, 'feuille' => []];
        }

        $numeros = array_keys($lignes);
        sort($numeros);

        // 1. Repérage des lignes de dépense : une date en A et un montant en C.
        $estDonnee = [];
        foreach ($numeros as $n) {
            $a = $lignes[$n]['A'] ?? '';
            $c = $lignes[$n]['C'] ?? '';
            $estDonnee[$n] = ClasseurLecteur::estNombre($a)
                && ClasseurLecteur::depuisSerial(ClasseurLecteur::nombre($a)) !== null
                && ClasseurLecteur::estNombre($c);
        }

        // 2. Découpage en blocs de lignes consécutives.
        $blocs = [];
        $courant = [];
        $precedent = null;
        foreach ($numeros as $n) {
            if ($estDonnee[$n] && ($precedent === null || $n === $precedent + 1 || $courant === [])) {
                $courant[] = $n;
            } elseif ($estDonnee[$n]) {
                $blocs[] = $courant;
                $courant = [$n];
            } elseif ($courant !== []) {
                $blocs[] = $courant;
                $courant = [];
            }
            $precedent = $n;
        }
        if ($courant !== []) {
            $blocs[] = $courant;
        }

        // 3. Interprétation de chaque bloc.
        $operations = [];
        $ignorees = 0;
        foreach ($blocs as $bloc) {
            $contexte = self::contexteDuBloc($lignes, $numeros, $bloc);
            $duBloc = [];

            foreach ($bloc as $n) {
                $date = ClasseurLecteur::depuisSerial(ClasseurLecteur::nombre($lignes[$n]['A']));
                $montant = round(abs(ClasseurLecteur::nombre($lignes[$n]['C'])), 2);
                $libelle = trim((string) ($lignes[$n]['B'] ?? ''));

                if ($date === null || $montant <= 0 || $libelle === '') {
                    $ignorees++;
                    continue;
                }

                $duBloc[] = [
                    'ligne'     => $n,
                    'date'      => $date,
                    'libelle'   => mb_substr(preg_replace('/\s+/u', ' ', $libelle) ?? $libelle, 0, 160),
                    'montant'   => $montant,
                    'rubrique'  => $contexte['rubrique'],
                    'part'      => $contexte['moitie'] ? round($montant / 2, 2) : null,
                    'statut'    => $contexte['hors_total'] ? 'hors_total' : 'a_reclamer',
                    'note'      => $contexte['note'],
                ];
            }

            // La feuille divise le total du bloc, pas chaque ligne : arrondir
            // ligne par ligne décalerait le total d'un centime. On reporte le
            // reliquat sur la dernière ligne pour retomber sur le même chiffre.
            if ($contexte['moitie'] && $duBloc !== []) {
                $sommeBloc = array_sum(array_column($duBloc, 'montant'));
                $exact = round($sommeBloc / 2, 2);
                $cumul = array_sum(array_column($duBloc, 'part'));
                $ecart = round($exact - $cumul, 2);
                if (abs($ecart) >= 0.01) {
                    $dernier = count($duBloc) - 1;
                    $duBloc[$dernier]['part'] = round($duBloc[$dernier]['part'] + $ecart, 2);
                }
            }

            array_push($operations, ...$duBloc);
        }

        return [
            'operations' => $operations,
            'ignorees'   => $ignorees,
            'totaux'     => self::totauxDeLaFeuille($lignes, $numeros),
            'feuille'    => $lignes,
        ];
    }

    /**
     * Les totaux mensuels inscrits dans la feuille elle-même (« Total Février »).
     * Ils servent de contrôle : si le total recalculé en diffère, c'est qu'une
     * ligne était comptée autrement, et mieux vaut le signaler que le deviner.
     *
     * @return array<string, float> indexé par nom de mois
     */
    private static function totauxDeLaFeuille(array $lignes, array $numeros): array
    {
        $totaux = [];
        foreach ($numeros as $n) {
            foreach (['E', 'D', 'H'] as $colonne) {
                $texte = trim((string) ($lignes[$n][$colonne] ?? ''));
                if ($texte === '') {
                    continue;
                }
                if (preg_match('/^total\s+(.+)$/iu', self::sansAccents($texte), $m) !== 1) {
                    continue;
                }
                $mois = trim($m[1], " \t:");
                if (!in_array($mois, self::MOIS, true)) {
                    continue;
                }
                // Le montant est dans la colonne juste à droite.
                $suivante = chr(ord($colonne) + 1);
                $valeur = (string) ($lignes[$n][$suivante] ?? '');
                if (ClasseurLecteur::estNombre($valeur)) {
                    $totaux[$mois] = round(ClasseurLecteur::nombre($valeur), 2);
                }
            }
        }
        return $totaux;
    }

    /**
     * Ce que le bloc doit à son entourage : sa rubrique, la mention d'un partage
     * en deux, celle d'une mise hors total, et une éventuelle note libre.
     */
    private static function contexteDuBloc(array $lignes, array $numeros, array $bloc): array
    {
        $rubrique = null;
        $moitie = false;
        $horsTotal = false;
        $note = null;

        // Le total du bloc est posé selon les feuilles sur sa première ligne, sa
        // dernière, ou juste en dessous : on examine le bloc puis ses deux suivantes.
        $premier = $bloc[0];
        $dernier = end($bloc);
        $aExaminer = $bloc;
        foreach ($numeros as $n) {
            if ($n > $dernier && $n <= $dernier + 2) {
                $aExaminer[] = $n;
            }
        }

        // Étiquettes « Total … » rencontrées, dans l'ordre.
        $etiquettes = [];
        foreach ($aExaminer as $n) {
            foreach (['E', 'F', 'D'] as $colonne) {
                $texte = trim((string) ($lignes[$n][$colonne] ?? ''));
                if ($texte !== '' && str_starts_with(self::sansAccents($texte), 'total')) {
                    $etiquettes[] = $texte;
                }
            }
            $g = trim((string) ($lignes[$n]['G'] ?? ''));
            if ($g !== '' && $note === null && mb_strlen($g) > 3) {
                $note = mb_substr($g, 0, 500);
            }
        }

        foreach ($etiquettes as $texte) {
            $nom = self::nomDeRubrique($texte);
            if ($nom !== null && $rubrique === null) {
                $rubrique = $nom;
            }
        }

        // Le partage en deux ne vaut que s'il porte sur la rubrique du bloc :
        // une mention « divisier par 2 » posée sous le bloc suivant ne doit pas
        // déteindre sur celui-ci.
        foreach ($etiquettes as $texte) {
            if (self::contientPartage($texte) && self::nomDeRubrique($texte) === $rubrique) {
                $moitie = true;
            }
        }

        // « À voir avec… » annonce le bloc qui suit ; « Pas dans le total » clôt
        // celui qui précède. On n'accepte le marqueur que s'il touche ce bloc-ci,
        // sans autre ligne de dépense entre les deux.
        foreach ($numeros as $n) {
            $texte = self::sansAccents(
                (string) ($lignes[$n]['A'] ?? '') . ' ' . (string) ($lignes[$n]['B'] ?? '')
            );
            if (trim($texte) === '') {
                continue;
            }
            if (str_contains($texte, 'voir avec') && $n < $premier && !self::depenseEntre($lignes, $numeros, $n, $premier)) {
                $horsTotal = true;
            }
            if (str_contains($texte, 'pas dans le total') && $n > $dernier && !self::depenseEntre($lignes, $numeros, $dernier, $n)) {
                $horsTotal = true;
            }
        }

        return ['rubrique' => $rubrique, 'moitie' => $moitie, 'hors_total' => $horsTotal, 'note' => $note];
    }

    /** Y a-t-il une ligne de dépense strictement entre ces deux numéros ? */
    private static function depenseEntre(array $lignes, array $numeros, int $debut, int $fin): bool
    {
        foreach ($numeros as $n) {
            if ($n <= min($debut, $fin) || $n >= max($debut, $fin)) {
                continue;
            }
            $a = $lignes[$n]['A'] ?? '';
            $c = $lignes[$n]['C'] ?? '';
            if (ClasseurLecteur::estNombre($a) && ClasseurLecteur::estNombre($c)
                && ClasseurLecteur::depuisSerial(ClasseurLecteur::nombre($a)) !== null) {
                return true;
            }
        }
        return false;
    }

    /** « Total essence pour les parents (divisier pas 2) » → « Essence ». */
    private static function nomDeRubrique(string $texte): ?string
    {
        $nom = preg_replace('/^\s*total\s*/iu', '', $texte) ?? $texte;
        $nom = preg_replace('/\([^)]*\)/u', '', $nom) ?? $nom;          // parenthèses

        // « pour les parents » n'est qu'une précision de destinataire… sauf
        // quand c'est tout ce qui reste : « Total pour maman » est une rubrique.
        $sansDestinataire = preg_replace('/\bpour\s+(les\s+parents|maman|papa)\b/iu', '', $nom) ?? $nom;
        if (trim($sansDestinataire, " \t:.-–—") !== '') {
            $nom = $sansDestinataire;
        }

        $nom = trim(preg_replace('/\s+/u', ' ', $nom) ?? $nom, " \t:.-–—");

        if ($nom === '' || mb_strlen($nom) > 60) {
            return null;
        }
        // « Total Janvier » est le total du mois, pas une rubrique.
        if (in_array(self::sansAccents($nom), self::MOIS, true)) {
            return null;
        }
        return mb_convert_case(mb_strtolower($nom), MB_CASE_TITLE, 'UTF-8');
    }

    private static function contientPartage(string $texte): bool
    {
        // L'orthographe varie d'un fichier à l'autre : « divisier par 2 », « pas 2 ».
        $t = self::sansAccents($texte);
        return preg_match('/divis\w*\s+(par|pas)\s*2/u', $t) === 1;
    }

    private static function sansAccents(string $texte): string
    {
        $texte = mb_strtolower(trim($texte));
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }
}
