<?php
/** Ne montre que ce qui reste vraiment en français : texte littéral, attributs, chaînes PHP. */
$fichiers = $argv;
array_shift($fichiers);
$motsFr = '/(^|[^\p{L}])(le|la|les|un|une|des|du|et|ou|à|au|aux|ce|cette|ces|sur|pour|dans|par|qui|que|est|sont|sans|avec|plus|tout|toute|tous|aucun|aucune|votre|vos|mes|mon|ma|son|ses|pas|déjà|encore|puis|très|vous|ici|cet|d’un|d’une|l’|n’|c’est)([^\p{L}]|$)/iu';

foreach ($fichiers as $f) {
    $src = file_get_contents($f);
    $vus = [];

    $sansPhp = preg_replace('/<\?php.*?\?>|<\?=.*?\?>/s', '␟', $src);
    $sansPhp = preg_replace('/<\?php.*$/s', '', (string) $sansPhp);
    $sansPhp = preg_replace('/<!--.*?-->/s', '', (string) $sansPhp);
    foreach (preg_split('/\R/', (string) $sansPhp) as $i => $l) {
        $t = trim(str_replace('␟', ' ', trim(strip_tags($l))));
        if ($t !== '' && preg_match('/[a-zà-ÿ]{3}/u', $t)) {
            $vus[] = '  texte    ' . $t;
        }
    }

    foreach (preg_split('/\R/', $src) as $i => $l) {
        if (preg_match_all('/(aria-label|title|placeholder|alt|data-confirmation|data-quoi)="([^"]*)"/u', $l, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $reste = preg_replace('/<\?=.*?\?>|<\?php.*?\?>/s', '', $x[2]);
                if (preg_match('/[a-zà-ÿ]{3}/u', (string) $reste)) {
                    $vus[] = '  ' . ($i + 1) . ' @' . $x[1] . ' = ' . trim($x[2]);
                }
            }
        }
        foreach (["/'([^']{6,})'/u", '/"([^"<>]{6,})"/u'] as $motif) {
            if (preg_match_all($motif, $l, $m)) {
                foreach ($m[1] as $v) {
                    /*
                     * Une phrase se reconnaît à un mot-outil français — ou, à
                     * défaut, à un accent : « Cours enregistré. » n'a ni « le »
                     * ni « la », et passait donc entre les mailles.
                     */
                    $frSansOutil = preg_match('/[àâäçéèêëîïôöùûüœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒ]/u', $v) === 1
                        && preg_match('/\p{L}{3}/u', $v) === 1;
                    if (($frSansOutil || preg_match($motsFr, $v)) && !preg_match('#^[a-z0-9_./ -]+$#', $v)) {
                        $vus[] = '  ' . ($i + 1) . ' chaîne  ' . $v;
                    }
                }
            }
        }
    }

    if ($vus !== []) {
        echo "\n--- $f\n" . implode("\n", array_unique($vus)) . "\n";
    }
}
