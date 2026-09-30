<?php
/**
 * Les clés que le script appelle, et celles que le serveur lui envoie.
 *
 * mot('chat.envoyer') dans app.js lit la clé « js.chat.envoyer » des fichiers
 * de langue. Une clé appelée sans exister se rendrait telle quelle à l'écran ;
 * une clé « js. » que personne n'appelle part pour rien dans chaque page.
 */
$racine = 'C:/wamp64/www/mon_appli/appli/';
$fr = file_get_contents($racine . 'lang/fr.php');

// Le script, sans ses commentaires : une clé citée en commentaire n'est pas un appel.
$js = '';
foreach (preg_split("/\r\n|\n/", file_get_contents($racine . 'assets/js/app.js')) as $ligne) {
    $nue = ltrim($ligne);
    if (str_starts_with($nue, '//') || str_starts_with($nue, '*') || str_starts_with($nue, '/*')) {
        continue;
    }
    $js .= $ligne . "\n";
}

// Ce que le script demande : mot('x'), motN('y'), et les clés posées dans un tableau.
$demandees = [];
preg_match_all("/\bmot\(\s*'([a-z0-9_.]+)'/i", $js, $m);
foreach ($m[1] as $cle) { $demandees[$cle] = "mot('" . $cle . "')"; }
preg_match_all("/\bmotN\(\s*'([a-z0-9_.]+)'/i", $js, $m);
foreach ($m[1] as $cle) {
    $demandees[$cle . '.un'] = "motN('" . $cle . "')";
    $demandees[$cle . '.plusieurs'] = "motN('" . $cle . "')";
}
// Une clé peut arriver par un ternaire, une concaténation ou un raccourci local :
// toute chaîne qui a la forme d'une clé compte comme demandée.
preg_match_all("/'([a-z][a-z0-9_]*\.[a-z0-9_.]+)'/", $js, $m);
foreach ($m[1] as $cle) { $demandees[$cle] = $demandees[$cle] ?? ("'" . $cle . "', quelque part dans le script"); }

// Le serveur peut aussi écrire une clé « js. » dans une page : elle n'est pas perdue.
$cotePhp = '';
$dossiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine));
foreach ($dossiers as $f) {
    $chemin = str_replace('\\', '/', (string) $f);
    if (!str_ends_with($chemin, '.php') || str_contains($chemin, '/lang/') || str_contains($chemin, '/outils/')) {
        continue;
    }
    $cotePhp .= file_get_contents($chemin);
}

$manquantes = [];
foreach ($demandees as $cle => $appel) {
    // Une clé au pluriel existe par ses deux moitiés, pas sous son nom nu.
    if (str_contains($fr, "'js." . $cle . "' =>") || str_contains($fr, "'js." . $cle . ".un' =>")) {
        continue;
    }
    $manquantes[$cle] = $appel;
}

preg_match_all("/'js\.([a-z0-9_.]+)' =>/i", $fr, $m);
$toutes = $m[1];
$inutiles = [];
foreach ($toutes as $cle) {
    $nu = preg_replace('/\.(un|plusieurs)$/', '', $cle);
    if (isset($demandees[$cle]) || isset($demandees[$nu . '.un'])) {
        continue;
    }
    if (str_contains($cotePhp, "'js." . $cle . "'") || str_contains($cotePhp, "'js." . $nu . "'")) {
        continue;
    }
    $inutiles[] = 'js.' . $cle;
}

printf("clés demandées par le script : %d\n", count($demandees));
printf("clés « js. » côté serveur    : %d\n", count($toutes));
if ($manquantes !== []) {
    echo "\n✗ " . count($manquantes) . " clé(s) appelée(s) mais absente(s) :\n";
    foreach ($manquantes as $appel) { echo '    ' . $appel . "\n"; }
} else {
    echo "\n✓ toutes les clés appelées existent\n";
}
if ($inutiles !== []) {
    echo "\n✗ " . count($inutiles) . " clé(s) « js. » que personne n’appelle :\n";
    foreach ($inutiles as $cle) { echo '    ' . $cle . "\n"; }
} else {
    echo "✓ aucune clé « js. » ne part pour rien\n";
}
exit($manquantes === [] && $inutiles === [] ? 0 : 1);
