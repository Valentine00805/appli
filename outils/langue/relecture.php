<?php
/*
 * Les contrôles mécaniques d'une relecture : ce qu'un œil fatigué rate, une machine le voit.
 *
 * Compare chaque phrase de l'anglais, de l'espagnol et de l'allemand à son français, et signale :
 *   - une variable perdue, ajoutée ou renommée ({n}, {titre}…) — un trou dans la phrase affichée ;
 *   - une balise HTML perdue ou ajoutée (<strong>, <br>…) ;
 *   - du français resté dans la phrase (accents anglais, « le », « des », « pour »…) ;
 *   - une phrase identique au français (non traduite) ;
 *   - une ponctuation finale qui change, un emoji de tête qui disparaît ;
 *   - des guillemets d'une autre langue, une apostrophe qui change de forme ;
 *   - le vouvoiement, quand le ton est partout le tutoiement (et « du » en allemand) ;
 *   - un écart de longueur anormal, signe d'une phrase tronquée ou d'un contresens ;
 *   - la ponctuation propre à l'espagnol (¿…?, ¡…!).
 *
 * Ce n'est PAS une relecture : elle ne sait pas si une phrase est juste, seulement si elle est bien
 * formée. Voir LISEZMOI.md, « Ce que la relecture ne remplace pas ».
 *
 *     php outils/langue/relecture.php            tout
 *     php outils/langue/relecture.php de         une langue
 *     php outils/langue/relecture.php --resume   les compteurs seuls
 */
$racine = dirname(__DIR__, 2);
$fr = require $racine . '/lang/fr.php';
$resume = in_array('--resume', $argv, true);
$langues = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => in_array($a, ['en', 'es', 'de'], true)));
$langues = $langues === [] ? ['en', 'es', 'de'] : $langues;

$variables = static function (string $s): array {
    preg_match_all('/\{[a-z_0-9]+\}/i', $s, $m);
    $v = $m[0];
    sort($v);

    return $v;
};
$balises = static function (string $s): array {
    preg_match_all('/<\/?[a-z][a-z0-9]*[^>]*>|&[a-z]+;|&#\d+;/i', $s, $m);
    $v = array_map(static fn (string $b): string => strtolower($b), $m[0]);
    sort($v);

    return $v;
};
// Les guillemets d'ouverture changent de forme d'une langue à l'autre (« “ „) : on ne compare que le reste.
$tete = static fn (string $s): string => preg_match('/^[^\p{L}\p{N}{<]+/u', $s, $m) === 1
    ? trim((string) preg_replace('/[«“„»”]/u', '', $m[0])) : '';
$fin = static fn (string $s): string => preg_match('/[.…:?!;»”“]+\s*$/u', trim($s), $m) === 1 ? trim($m[0]) : '';

$rapport = [];
$ajouter = static function (string $langue, string $genre, string $cle, string $detail) use (&$rapport): void {
    $rapport[$langue][$genre][] = [$cle, $detail];
};

foreach ($langues as $langue) {
    $t = require $racine . '/lang/' . $langue . '.php';
    $ratios = [];
    $droites = 0;
    $courbes = 0;
    foreach ($fr as $cle => $phraseFr) {
        if (!is_string($phraseFr) || !array_key_exists($cle, $t) || !is_string($t[$cle])) {
            continue;
        }
        $x = $t[$cle];
        $lettres = preg_match_all('/\p{L}/u', $phraseFr);

        if ($variables($phraseFr) !== $variables($x)) {
            $ajouter($langue, 'variables', $cle, implode(' ', $variables($phraseFr)) . '  →  ' . implode(' ', $variables($x)));
        }
        if ($balises($phraseFr) !== $balises($x)) {
            $ajouter($langue, 'balises', $cle, implode(' ', $balises($phraseFr)) . '  →  ' . implode(' ', $balises($x)));
        }
        if ($x === $phraseFr && $lettres >= 5 && !str_starts_with($cle, 'js.') && !preg_match('/^[\p{Lu}][\p{L}\-]+$/u', $phraseFr)) {
            $ajouter($langue, 'identiques', $cle, $x);
        }
        if ($tete($phraseFr) !== $tete($x) && !($langue === 'es' && str_starts_with($x, '¿') && $tete($phraseFr) === '')
            && !($langue === 'es' && str_starts_with($x, '¡') && $tete($phraseFr) === '')) {
            $ajouter($langue, 'tete', $cle, '« ' . $tete($phraseFr) . ' »  →  « ' . $tete($x) . ' »');
        }
        $ff = $fin($phraseFr);
        $fx = $fin($x);
        // Les guillemets de fin changent de forme d'une langue à l'autre : on ne compare que la ponctuation.
        $nu = static fn (string $p): string => (string) preg_replace('/[»”“\s]+/u', '', $p);
        // Les abréviations (« lun. » / « Mon ») et les formats de nombre n'ont pas de ponctuation à comparer.
        $abrege = str_starts_with($cle, 'alt.jour.') || str_starts_with($cle, 'alt.mois.') || str_starts_with($cle, 'fmt.')
            || str_starts_with($cle, 'date.') || str_starts_with($cle, 'rappel.court.') || str_starts_with($cle, 'pt.duree_');
        if (!$abrege && $nu($ff) !== $nu($fx) && !($nu($ff) === '…' && $nu($fx) === '...')) {
            $ajouter($langue, 'fin', $cle, '« ' . $ff . ' »  →  « ' . $fx . ' »');
        }
        if (preg_match('/  +/', $x) === 1 && preg_match('/  +/', $phraseFr) !== 1 && !str_starts_with($cle, 'sv.me.')) {
            $ajouter($langue, 'espaces', $cle, 'double espace');
        }
        // Le français met une espace avant « : » : « " : suite" » devient « ": suite" » ailleurs, c'est voulu.
        $enTete = static fn (string $s): bool => preg_match('/^\s(?![:;!?])/u', $s) === 1;
        if ($enTete($phraseFr) !== $enTete($x)
            || (preg_match('/\s$/', $phraseFr) === 1) !== (preg_match('/\s$/', $x) === 1)) {
            $ajouter($langue, 'espaces', $cle, 'espace de tête ou de queue : « ' . $phraseFr . ' » → « ' . $x . ' »');
        }

        // Guillemets : « » en français et en espagnol ; “ ” en anglais ; „ “ en allemand.
        if ($langue === 'en' && preg_match('/[«»„]/u', $x) === 1) {
            $ajouter($langue, 'guillemets', $cle, $x);
        }
        if ($langue === 'de' && preg_match('/[«»”]/u', $x) === 1) {
            $ajouter($langue, 'guillemets', $cle, $x);
        }
        if ($langue === 'es' && preg_match('/[“”„]/u', $x) === 1) {
            $ajouter($langue, 'guillemets', $cle, $x);
        }
        if (preg_match('/"[^"{}<>=]*"/', $x) === 1 && !str_contains($x, '<')) {
            $ajouter($langue, 'guillemets', $cle, 'guillemets droits : ' . $x);
        }

        // L'apostrophe : « ’ » typographique partout, comme dans le français.
        if (str_contains($x, "'") && !str_contains($x, '<')) {
            $droites++;
            $ajouter($langue, 'apostrophes', $cle, 'apostrophe droite : ' . $x);
        }
        if (str_contains($x, '’')) {
            $courbes++;
        }

        // Du français resté dans la phrase.
        if ($langue === 'en' && preg_match('/[éèêàâçùûôîïëü]/iu', $x) === 1) {
            $ajouter($langue, 'francais', $cle, 'accent : ' . $x);
        }
        // « du » est aussi le tutoiement allemand ; « sur », « ou », « et » n'y valent rien : on ne cherche
        // que le français sûr.
        $motsFr = '/(?<![\p{L}\'’])(les|pour|avec|dans|est|sont|votre|vos|cette|ces|sans|aux)(?![\p{L}])/iu';
        if ($langue === 'de' && preg_match($motsFr, preg_replace('/\{[a-z_]+\}/i', '', $x) ?? $x, $m) === 1
            && !preg_match('/\b(Le|La|Les|Du|Des|Une?)\b\s+[A-Z]/', $x)) {
            $ajouter($langue, 'francais', $cle, '« ' . $m[1] . ' » : ' . $x);
        }
        if ($langue === 'es' && preg_match('/(?<![\p{L}\'’])(les|des|du|pour|avec|dans|est|sont|votre|vos|et|ou|sur|aux|cette|ces|sans|vous|nous)(?![\p{L}])/iu',
                preg_replace('/\{[a-z_]+\}/i', '', $x) ?? $x, $m) === 1) {
            $ajouter($langue, 'francais', $cle, '« ' . $m[1] . ' » : ' . $x);
        }
        if ($langue === 'en' && preg_match('/(?<![\p{L}\'’])(les|des|du|pour|avec|dans|est|sont|votre|vos|une|aux|cette|ces|sans|vous|nous)(?![\p{L}])/iu',
                preg_replace('/\{[a-z_]+\}/i', '', $x) ?? $x, $m) === 1) {
            $ajouter($langue, 'francais', $cle, '« ' . $m[1] . ' » : ' . $x);
        }

        // Le ton : le tutoiement, partout.
        if ($langue === 'de' && preg_match('/\b(Sie|Ihnen|Ihr|Ihre[nrms]?)\b/u', $x) === 1) {
            // « Sie » au début d'une phrase peut être « elle/ils » : à lire, pas à condamner d'office.
            $ajouter($langue, 'ton', $cle, 'Sie/Ihr : ' . $x);
        }
        if ($langue === 'es' && preg_match('/\b(usted|ustedes|su cuenta|sus datos)\b/iu', $x) === 1) {
            $ajouter($langue, 'ton', $cle, 'usted ? ' . $x);
        }

        // La ponctuation espagnole.
        if ($langue === 'es') {
            $nbInterro = preg_match_all('/\?/u', $x);
            $nbOuvrant = preg_match_all('/¿/u', $x);
            if ($nbInterro !== $nbOuvrant) {
                $ajouter($langue, 'espagnol', $cle, '? et ¿ : ' . $x);
            }
            $nbExcl = preg_match_all('/!/u', $x);
            $nbExclOuvrant = preg_match_all('/¡/u', $x);
            if ($nbExcl !== $nbExclOuvrant) {
                $ajouter($langue, 'espagnol', $cle, '! et ¡ : ' . $x);
            }
        }

        // L'espace avant « : ; ! ? » est une habitude française : ni l'anglais, ni l'espagnol, ni l'allemand ne
        // la connaissent. Sont voulus : les « ; » d'un tableau CSV à coller (alt.ry.coller*, alt.msg.rien_lisible)
        // et les libellés alignés à la chasse fixe du fichier texte de la sauvegarde (sv.me.*).
        $sansBalises = preg_replace('/<[^>]*>/', '', $x) ?? $x;
        if (preg_match('/\S [:;!?]/u', $sansBalises) === 1
            && !preg_match('/^(alt\.(ry\.coller|msg\.rien_lisible)|sv\.me\.)/', $cle)) {
            $ajouter($langue, 'espaces', $cle, 'espace avant « : ; ! ? » : ' . $x);
        }

        // Une phrase dont la longueur s'écarte trop du français.
        $lf = mb_strlen(preg_replace('/\{[a-z_]+\}/i', '', $phraseFr) ?? $phraseFr);
        $lx = mb_strlen(preg_replace('/\{[a-z_]+\}/i', '', $x) ?? $x);
        if ($lf >= 25) {
            $ratio = $lx / $lf;
            $ratios[] = $ratio;
            if ($ratio < 0.4 || $ratio > 2.0) {
                $ajouter($langue, 'longueur', $cle, sprintf('%.2f × le français (%d → %d)', $ratio, $lf, $lx));
            }
        }
    }
    $rapport[$langue]['_stats'] = [
        'droites' => $droites, 'courbes' => $courbes,
        'ratio_moyen' => $ratios === [] ? 0 : array_sum($ratios) / count($ratios),
    ];
}

$total = 0;
foreach ($rapport as $langue => $genres) {
    $stats = $genres['_stats'];
    unset($genres['_stats']);
    echo "\n=== " . strtoupper($langue) . ' ===  longueur moyenne ' . sprintf('%.2f', $stats['ratio_moyen'])
        . ' × le français · apostrophes droites ' . $stats['droites'] . ' / typographiques ' . $stats['courbes'] . "\n";
    if ($genres === []) {
        echo "  rien à signaler\n";
    }
    ksort($genres);
    foreach ($genres as $genre => $lignes) {
        echo "\n  [$genre] " . count($lignes) . "\n";
        $total += count($lignes);
        if ($resume) {
            continue;
        }
        foreach ($lignes as [$cle, $detail]) {
            echo '    ' . $cle . "\n      " . mb_strimwidth($detail, 0, 220, '…') . "\n";
        }
    }
}
echo "\n" . $total . " signalement(s)\n";
exit($total === 0 ? 0 : 1);
