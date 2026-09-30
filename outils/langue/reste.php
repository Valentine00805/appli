<?php
/**
 * Combien de phrases françaises restent, fichier par fichier.
 *
 * Le détail avec « php reste.php views/xxx.php », le décompte sans argument.
 *
 * Un fichier de PHP pur (src, controllers) n'a pas de texte hors balises :
 * on n'y regarde que les chaînes. Une vue, elle, en a partout.
 */
$motsFr = '/(^|[^\p{L}])(le|la|les|un|une|des|du|et|ou|à|au|aux|ce|cette|ces|sur|pour|dans|par|qui|que|est|sont|sans|avec|plus|tout|toute|tous|aucun|aucune|votre|vos|mes|mon|ma|son|ses|pas|déjà|encore|puis|très|vous|ici|cet)([^\p{L}]|$)/iu';

function phrases(string $f, string $motsFr): array
{
    $src = file_get_contents($f);
    $vus = [];
    // Une vue mêle HTML et PHP ; un fichier de classe est du PHP de bout en bout.
    // Un fichier de « src » ou de « controllers » est du PHP de bout en bout.
    $estUneVue = !preg_match("#/(src|controllers)/#", str_replace("\\", "/", $f));

    if ($estUneVue) {
        $sansPhp = preg_replace('/<\?php.*?\?>|<\?=.*?\?>/s', '␟', $src);
        $sansPhp = preg_replace('/<\?php.*$/s', '', (string) $sansPhp);
        $sansPhp = preg_replace('/<!--.*?-->/s', '', (string) $sansPhp);
        foreach (preg_split('/\R/', (string) $sansPhp) as $l) {
            $t = trim(str_replace('␟', ' ', trim(strip_tags($l))));
            if ($t !== '' && preg_match('/[a-zà-ÿ]{3}/u', $t) && !preg_match('#^[a-z-]+="#', $t)
                && !str_contains($t, '--gauche') && !str_contains($t, '--largeur')) {
                $vus[] = 'texte   ' . $t;
            }
        }
    }

    foreach (preg_split('/\R/', $src) as $i => $l) {
        if (preg_match('#^\s*(//|\*|/\*)#', $l)) { continue; }
        if (preg_match_all('/(aria-label|title|placeholder|alt|data-confirmation)="([^"]*)"/u', $l, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $reste = preg_replace('/<\?=.*?\?>|<\?php.*?\?>/s', '', $x[2]);
                if (preg_match('/[a-zà-ÿ]{3}/u', (string) $reste)) { $vus[] = ($i + 1) . ' @' . $x[1] . ' = ' . trim($x[2]); }
            }
        }
        foreach (["/'([^']{8,})'/u", '/"([^"<>]{8,})"/u'] as $motif) {
            if (preg_match_all($motif, $l, $m)) {
                foreach ($m[1] as $v) {
                    if (preg_match($motsFr, $v) && !preg_match('#^[a-z0-9_./ -]+$#', $v) && !str_contains($v, '<?')
                        // Une requête SQL n'est pas une phrase à traduire.
                        && !preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|LEFT JOIN|JOIN|WHERE|ORDER|GROUP)\b/i', $v)) {
                        $vus[] = ($i + 1) . ' chaîne ' . $v;
                    }
                }
            }
        }
    }
    return array_unique($vus);
}

$cibles = array_slice($argv, 1);
if ($cibles !== []) {
    foreach ($cibles as $f) {
        $v = phrases($f, $motsFr);
        if ($v !== []) { echo "\n--- $f\n  " . implode("\n  ", $v) . "\n"; }
    }
    exit;
}

$racine = 'C:/wamp64/www/mon_appli/appli/';
$tous = [];
foreach (['views', 'src', 'controllers'] as $coin) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . $coin));
    foreach ($it as $fichier) {
        if ($fichier->isFile() && $fichier->getExtension() === 'php') {
            $chemin = str_replace('\\', '/', $fichier->getPathname());
            $tous[substr($chemin, strlen($racine))] = count(phrases($chemin, $motsFr));
        }
    }
}
$tous = array_filter($tous);
arsort($tous);
$total = array_sum($tous);
foreach ($tous as $f => $n) { printf("%5d  %s\n", $n, $f); }
echo "\n$total phrases dans " . count($tous) . " fichiers\n";
