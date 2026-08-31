<?php
declare(strict_types=1);

/**
 * Lecture d'un relevé bancaire au format CSV.
 *
 * Les banques n'exportent pas toutes de la même façon : séparateur point-virgule
 * ou virgule, dates à la française ou à l'anglaise, montant unique signé ou deux
 * colonnes débit/crédit, encodage UTF-8 ou Windows-1252. Cette classe devine ce
 * qu'elle peut ; l'utilisateur corrige le reste dans l'écran d'aperçu.
 */
final class ReleveCsv
{
    /** Séparateurs testés, du plus probable au moins probable. */
    private const SEPARATEURS = [';', "\t", ',', '|'];

    /** Nombre de lignes maximum acceptées dans un relevé. */
    public const LIGNES_MAX = 2000;

    /**
     * Analyse le contenu brut d'un fichier.
     * @return array{separateur:string, entetes:array, lignes:array, mapping:array}
     */
    public static function analyser(string $contenu): array
    {
        $contenu = self::versUtf8($contenu);
        $separateur = self::detecterSeparateur($contenu);

        $lignes = [];
        foreach (preg_split("/\r\n|\n|\r/", $contenu) ?: [] as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }
            $champs = str_getcsv($ligne, $separateur, '"', '\\');
            $champs = array_map(static fn ($c): string => trim((string) $c, " \t\"'"), $champs);
            if (implode('', $champs) !== '') {
                $lignes[] = $champs;
            }
            if (count($lignes) > self::LIGNES_MAX) {
                break;
            }
        }

        if ($lignes === []) {
            return ['separateur' => $separateur, 'entetes' => [], 'lignes' => [], 'mapping' => []];
        }

        // Certaines banques précèdent le tableau d'un préambule (numéro de compte,
        // solde, ligne vide…). On ne garde que le plus long bloc de lignes ayant
        // toutes le même nombre de colonnes : c'est le tableau.
        $lignes = self::blocPrincipal($lignes);

        $entetes = [];
        if ($lignes !== [] && !self::ligneDeDonnees($lignes[0])) {
            $entetes = array_shift($lignes);
        }

        return [
            'separateur' => $separateur,
            'entetes'    => $entetes,
            'lignes'     => $lignes,
            'mapping'    => self::deviner($entetes, $lignes),
        ];
    }

    /**
     * Isole le tableau parmi d'éventuelles lignes de préambule : on retient le
     * plus long enchaînement de lignes ayant le même nombre de colonnes.
     */
    private static function blocPrincipal(array $lignes): array
    {
        $largeurs = array_map('count', $lignes);
        $frequences = array_count_values($largeurs);
        arsort($frequences);

        $meilleur = [];
        foreach (array_keys($frequences) as $largeur) {
            if ($largeur < 2) {
                continue;
            }
            $courant = [];
            foreach ($lignes as $i => $ligne) {
                if ($largeurs[$i] === $largeur) {
                    $courant[] = $ligne;
                } else {
                    if (count($courant) > count($meilleur)) {
                        $meilleur = $courant;
                    }
                    $courant = [];
                }
            }
            if (count($courant) > count($meilleur)) {
                $meilleur = $courant;
            }
        }

        return $meilleur === [] ? $lignes : array_values($meilleur);
    }

    /**
     * Convertit les lignes brutes en opérations, selon le mapping choisi.
     * @return array<int, array{date:?string, libelle:string, montant:?float, sens:string, brut:array}>
     */
    public static function extraire(array $lignes, array $mapping): array
    {
        $resultat = [];
        foreach ($lignes as $ligne) {
            $date = self::lireDate(self::champ($ligne, $mapping['date'] ?? null));
            $libelle = self::lireLibelle($ligne, $mapping);

            if (($mapping['mode'] ?? 'montant') === 'debit_credit') {
                $debit  = montant_depuis_saisie(self::champ($ligne, $mapping['debit'] ?? null));
                $credit = montant_depuis_saisie(self::champ($ligne, $mapping['credit'] ?? null));
                if ($credit !== null && abs($credit) > 0) {
                    $montant = abs($credit);
                    $sens = 'recette';
                } elseif ($debit !== null && abs($debit) > 0) {
                    $montant = abs($debit);
                    $sens = 'depense';
                } else {
                    $montant = null;
                    $sens = 'depense';
                }
            } else {
                $valeur = montant_depuis_saisie(self::champ($ligne, $mapping['montant'] ?? null));
                $montant = $valeur === null ? null : abs($valeur);
                $sens = ($valeur !== null && $valeur > 0) ? 'recette' : 'depense';
                if ($montant !== null && abs($montant) < 0.005) {
                    $montant = null;
                }
            }

            $resultat[] = [
                'date'    => $date,
                'libelle' => $libelle,
                'montant' => $montant,
                'sens'    => $sens,
                'brut'    => $ligne,
            ];
        }
        return $resultat;
    }

    /** Signature d'une opération, pour repérer un doublon d'un import à l'autre. */
    public static function empreinte(string $date, float $montant, string $libelle): string
    {
        $normalise = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $libelle) ?? ''));
        return sha1($date . '|' . number_format($montant, 2, '.', '') . '|' . $normalise);
    }

    // --- Détection ----------------------------------------------------------

    private static function versUtf8(string $contenu): string
    {
        // BOM UTF-8 éventuel
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            return substr($contenu, 3);
        }
        if (mb_check_encoding($contenu, 'UTF-8')) {
            return $contenu;
        }
        // Les exports Windows des banques françaises sont en général en CP1252.
        return mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }

    private static function detecterSeparateur(string $contenu): string
    {
        $extrait = implode("\n", array_slice(preg_split("/\r\n|\n|\r/", $contenu) ?: [], 0, 20));
        $meilleur = ';';
        $score = -1;
        foreach (self::SEPARATEURS as $sep) {
            $n = substr_count($extrait, $sep);
            if ($n > $score) {
                $score = $n;
                $meilleur = $sep;
            }
        }
        return $score > 0 ? $meilleur : ';';
    }

    /** Une ligne de données contient au moins une date reconnaissable. */
    private static function ligneDeDonnees(array $champs): bool
    {
        foreach ($champs as $champ) {
            if (self::lireDate($champ) !== null) {
                return true;
            }
        }
        return false;
    }

    /** Devine quelles colonnes contiennent la date, le libellé et le montant. */
    private static function deviner(array $entetes, array $lignes): array
    {
        $mapping = ['mode' => 'montant', 'date' => null, 'libelle' => null, 'montant' => null,
            'debit' => null, 'credit' => null];

        // 1. D'après les intitulés de colonnes, quand il y en a.
        foreach ($entetes as $i => $entete) {
            $e = self::sansAccents($entete);
            if ($mapping['date'] === null && preg_match('/\bdate\b|^date/', $e) === 1) {
                $mapping['date'] = $i;
            }
            // Une colonne déjà prise par la date ne peut pas être le libellé :
            // « Date operation » contient « operation » sans être un libellé.
            if ($mapping['libelle'] === null && $i !== $mapping['date']
                && preg_match('/libell|label|description|nature|motif|intitul|operation/', $e) === 1) {
                $mapping['libelle'] = $i;
            }
            if ($mapping['debit'] === null && preg_match('/debit/', $e) === 1) {
                $mapping['debit'] = $i;
            }
            if ($mapping['credit'] === null && preg_match('/credit/', $e) === 1) {
                $mapping['credit'] = $i;
            }
            if ($mapping['montant'] === null && preg_match('/montant|amount|somme/', $e) === 1) {
                $mapping['montant'] = $i;
            }
        }

        // 2. À défaut, d'après le contenu des premières lignes.
        $echantillon = array_slice($lignes, 0, 15);
        $nbColonnes = $echantillon === [] ? 0 : max(array_map('count', $echantillon));

        for ($i = 0; $i < $nbColonnes; $i++) {
            $dates = 0;
            $nombres = 0;
            $longueurTexte = 0;
            $n = 0;
            foreach ($echantillon as $ligne) {
                $v = self::champ($ligne, $i);
                if ($v === '') {
                    continue;
                }
                $n++;
                if (self::lireDate($v) !== null) {
                    $dates++;
                } elseif (montant_depuis_saisie($v) !== null) {
                    $nombres++;
                } else {
                    $longueurTexte += mb_strlen($v);
                }
            }
            if ($n === 0) {
                continue;
            }
            if ($mapping['date'] === null && $dates / $n > 0.7) {
                $mapping['date'] = $i;
            } elseif ($mapping['montant'] === null && $mapping['debit'] === null
                      && $nombres / $n > 0.7 && $dates === 0) {
                $mapping['montant'] = $i;
            } elseif ($mapping['libelle'] === null && $longueurTexte / $n > 4) {
                $mapping['libelle'] = $i;
            }
        }

        if ($mapping['debit'] !== null || $mapping['credit'] !== null) {
            $mapping['mode'] = 'debit_credit';
            $mapping['montant'] = null;
        }

        return $mapping;
    }

    private static function champ(array $ligne, int|string|null $index): string
    {
        if ($index === null || $index === '') {
            return '';
        }
        return trim((string) ($ligne[(int) $index] ?? ''));
    }

    /** Le libellé peut tenir sur plusieurs colonnes : on les concatène. */
    private static function lireLibelle(array $ligne, array $mapping): string
    {
        $libelle = self::champ($ligne, $mapping['libelle'] ?? null);
        if ($libelle !== '') {
            return mb_substr(preg_replace('/\s+/u', ' ', $libelle) ?? $libelle, 0, 160);
        }

        // Repli : la plus longue colonne qui n'est ni une date ni un montant.
        $reserves = array_filter([
            $mapping['date'] ?? null, $mapping['montant'] ?? null,
            $mapping['debit'] ?? null, $mapping['credit'] ?? null,
        ], static fn ($v): bool => $v !== null);

        $meilleur = '';
        foreach ($ligne as $i => $v) {
            if (in_array((string) $i, array_map('strval', $reserves), true)) {
                continue;
            }
            $v = trim((string) $v);
            if (mb_strlen($v) > mb_strlen($meilleur) && self::lireDate($v) === null) {
                $meilleur = $v;
            }
        }
        return mb_substr(preg_replace('/\s+/u', ' ', $meilleur) ?? $meilleur, 0, 160);
    }

    /** Reconnaît les formats de date courants ; renvoie AAAA-MM-JJ ou null. */
    public static function lireDate(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        if (preg_match('#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$#', $valeur, $m) === 1) {
            [$annee, $mois, $jour] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})$#', $valeur, $m) === 1) {
            [$jour, $mois, $annee] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($annee < 100) {
                $annee += $annee < 70 ? 2000 : 1900;
            }
        } else {
            return null;
        }

        if (!checkdate($mois, $jour, $annee)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
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
