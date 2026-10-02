<?php
/*
 * Le français qui reste, sans les commentaires.
 *
 * reste.php et scan2.php lisent le fichier comme du texte : ils ne savent pas
 * qu'« // on écrit dans le document pour qu'il… » est un commentaire et non une
 * phrase affichée. C'est ce qui fait l'essentiel de leur compte, et ce qui rend
 * leur sortie fatigante à relire.
 *
 * Celui-ci passe par token_get_all() : les commentaires et les blocs de
 * documentation disparaissent pour de bon, et il ne reste que ce qui peut
 * atteindre un écran — les chaînes littérales et le HTML entre les balises PHP.
 *
 *     php outils/langue/scan3.php                 tout le projet
 *     php outils/langue/scan3.php src/Amis.php    ces fichiers-là
 *     php outils/langue/scan3.php --tout          sans écarter les faux amis
 */

$racine = dirname(__DIR__, 2);
$arguments = array_slice($argv, 1);
$toutMontrer = in_array('--tout', $arguments, true);
$fichiers = array_values(array_filter($arguments, static fn (string $a): bool => $a !== '--tout'));

if ($fichiers === []) {
    foreach (['views', 'src', 'controllers'] as $dossier) {
        $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/' . $dossier));
        foreach ($iterateur as $entree) {
            if ($entree->isFile() && $entree->getExtension() === 'php') {
                $fichiers[] = str_replace('\\', '/', substr($entree->getPathname(), strlen($racine) + 1));
            }
        }
    }
    sort($fichiers);
}

/*
 * Ce qui a l'air français : un accent suffit, ou bien un mot grammatical.
 * Il faut aussi trois lettres de suite, pour écarter « %s » et « · ».
 */
$motsFr = '/(^|[^\p{L}])(le|la|les|un|une|des|du|et|ou|à|au|aux|ce|cette|ces|sur|pour|dans'
    . '|par|qui|que|est|sont|sans|avec|plus|tout|toute|tous|aucun|aucune|votre|vos|mes|mon'
    . '|ma|son|ses|pas|puis|vous|ici|cet|vient|veut|peut|doit|faire|dire)([^\p{L}]|$)/iu';
$aLAirFrancais = static function (string $v) use ($motsFr): bool {
    if (preg_match('/\p{L}{3}/u', $v) !== 1) {
        return false;
    }

    return preg_match('/[àâäçéèêëîïôöùûüœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒ]/u', $v) === 1
        || preg_match($motsFr, $v) === 1;
};

/*
 * Les faux amis : ce qui a l'air d'une phrase sans jamais s'afficher. On les
 * écarte par défaut, et « --tout » les ramène pour qui veut vérifier.
 */
$fauxAmis = static function (string $v): ?string {
    $t = trim($v);
    if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE|WITH|JOIN|LEFT|INNER|AND|OR|ON|WHERE|ORDER|GROUP|HAVING|LIMIT|SET|VALUES|FROM)\b/i', $t)) {
        return 'du SQL';
    }
    if (preg_match('/\b(FROM|WHERE|VALUES|INTERVAL|UTC_TIMESTAMP|COALESCE|GREATEST|TIMESTAMPDIFF)\b/', $t)) {
        return 'du SQL';
    }
    // Un morceau de requête, recollé ailleurs : il porte ses marques.
    if (preg_match('/(= \?|IS NOT NULL|IS NULL|LIKE \?)/', $t)) {
        return 'du SQL';
    }
    /*
     * Un attribut HTML coupé en deux par une balise PHP : la vue en est pleine,
     * et ce n'en est pas moins du balisage.
     */
    if (preg_match('/[a-z-]+="/i', $t)) {
        return 'un attribut HTML';
    }
    if (preg_match('#^[a-z0-9_]+(\.[a-z0-9_]+)+$#', $t)) {
        return 'une clé de langue';
    }
    if (preg_match('#^[a-z0-9_./ -]+$#', $t)) {
        return 'un chemin ou un mot-clé';
    }
    if (preg_match('~^[\[\(/.\#][^ ]*$~', $t) || str_starts_with($t, 'data-')) {
        return 'un sélecteur';
    }
    if (preg_match('/^[\d\s%.,:;·\-–—\/()]+$/u', $t)) {
        return 'de la ponctuation';
    }
    if (preg_match('/^[a-z-]+\s*:\s*/i', $t) && preg_match('/[;{}]|rem\b|px\b|%/', $t)) {
        return 'du CSS';
    }
    if (str_contains($t, '{') && str_contains($t, ':') && str_contains($t, ';')) {
        return 'du CSS';
    }

    return null;
};

$total = 0;
$totalEcartes = 0;
$parFichier = [];

foreach ($fichiers as $chemin) {
    $absolu = $racine . '/' . $chemin;
    if (!is_file($absolu)) {
        fwrite(STDERR, 'introuvable : ' . $chemin . PHP_EOL);
        continue;
    }
    $source = (string) file_get_contents($absolu);
    $trouves = [];
    $ecartes = [];

    $jetons = @token_get_all($source);
    $ligne = 1;
    foreach ($jetons as $jeton) {
        if (is_string($jeton)) {
            continue;
        }
        [$sorte, $texte, $ligne] = [$jeton[0], $jeton[1], $jeton[2]];

        // Les commentaires n'atteignent jamais un écran : on les laisse là.
        if ($sorte === T_COMMENT || $sorte === T_DOC_COMMENT) {
            continue;
        }

        $morceaux = [];
        if ($sorte === T_CONSTANT_ENCAPSED_STRING) {
            // Les guillemets, et l'échappement de PHP à l'intérieur.
            $nu = substr($texte, 1, -1);
            $morceaux[] = $texte[0] === "'"
                ? str_replace(["\\'", '\\\\'], ["'", '\\'], $nu)
                : stripcslashes($nu);
        } elseif ($sorte === T_ENCAPSED_AND_WHITESPACE) {
            $morceaux[] = stripcslashes($texte);
        } elseif ($sorte === T_INLINE_HTML) {
            // Le texte visible entre les balises, une ligne à la fois.
            $sansCommentaire = (string) preg_replace('/<!--.*?-->/s', '', $texte);
            foreach (preg_split('/\R/', $sansCommentaire) ?: [] as $rang => $brute) {
                $nu = trim(html_entity_decode(strip_tags($brute), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($nu !== '') {
                    $morceaux[$rang] = $nu;
                }
            }
        }

        foreach ($morceaux as $decalage => $v) {
            if (!$aLAirFrancais($v)) {
                continue;
            }
            $ou = $ligne + ($sorte === T_INLINE_HTML ? (int) $decalage : 0);
            $pourquoi = $fauxAmis($v);
            $vu = sprintf('%6d  %s', $ou, mb_strimwidth(preg_replace('/\s+/u', ' ', $v) ?? '', 0, 110, '…'));
            if ($pourquoi === null) {
                $trouves[] = $vu;
            } else {
                $ecartes[] = $vu . '   [' . $pourquoi . ']';
            }
        }
    }

    $total += count($trouves);
    $totalEcartes += count($ecartes);
    if ($trouves !== [] || ($toutMontrer && $ecartes !== [])) {
        $parFichier[$chemin] = $toutMontrer ? array_merge($trouves, $ecartes) : $trouves;
    }
}

foreach ($parFichier as $chemin => $lignes) {
    echo PHP_EOL . $chemin . PHP_EOL;
    foreach ($lignes as $l) {
        echo $l . PHP_EOL;
    }
}

echo PHP_EOL . $total . ' phrase(s) à regarder dans ' . count($parFichier) . ' fichier(s)'
    . ' · ' . $totalEcartes . ' écartée(s) comme SQL, clé, chemin, sélecteur ou CSS'
    . ($toutMontrer ? ' (montrées)' : ' (« --tout » les montre)') . PHP_EOL;
