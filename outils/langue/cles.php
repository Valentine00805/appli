<?php
/**
 * Ajoute des clés aux quatre fichiers de langue, et remplace des phrases dans
 * des vues. Un seul endroit à écrire : ['cle' => ['fr', 'en', 'es', 'de']].
 *
 * ajouter_cles(['taches.titre' => ['Mes tâches', 'My tasks', 'Mis tareas', 'Meine Aufgaben']]);
 * traduire('views/taches/index.php', ['<h1>Mes tâches</h1>' => "<h1><?= e(t('taches.titre')) ?></h1>"]);
 */
const RACINE = 'C:/wamp64/www/mon_appli/appli/';

function ajouter_cles(array $cles, string $section = ''): void
{
    $langues = ['fr' => 0, 'en' => 1, 'es' => 2, 'de' => 3];
    foreach ($langues as $langue => $rang) {
        $f = RACINE . 'lang/' . $langue . '.php';
        $s = file_get_contents($f);
        $nl = str_contains($s, "\r\n") ? "\r\n" : "\n";
        $ajout = $section === '' ? '' : $nl . $nl . '    // ' . $section;
        foreach ($cles as $cle => $textes) {
            if (str_contains($s, "'" . $cle . "' =>")) {
                continue;
            }
            $texte = $textes[$rang] ?? $textes[0];
            $ajout .= $nl . "    '" . $cle . "' => '" . str_replace(["\\", "'"], ["\\\\", "\\'"], $texte) . "',";
        }
        if (trim($ajout) === '' || trim($ajout) === '// ' . $section) {
            continue;
        }
        $fin = strrpos($s, '];');
        $s = substr($s, 0, $fin) . ltrim($ajout, "\r\n") . $nl . substr($s, $fin);
        file_put_contents($f, $s);
    }
}

function traduire(string $vue, array $paires): void
{
    $f = RACINE . $vue;
    $s = file_get_contents($f);
    $nl = str_contains($s, "\r\n") ? "\r\n" : "\n";
    $manquantes = [];
    foreach ($paires as $a => $b) {
        $a = str_replace("\n", $nl, $a);
        $b = str_replace("\n", $nl, $b);
        if (substr_count($s, $a) !== 1) {
            $manquantes[] = substr_count($s, $a) . '× « ' . substr($a, 0, 70) . ' »';
            continue;
        }
        $s = str_replace($a, $b, $s);
    }
    file_put_contents($f, $s);
    if ($manquantes !== []) {
        echo "  ⚠ $vue :\n    " . implode("\n    ", $manquantes) . "\n";
        return;
    }
    echo "  ✓ $vue (" . count($paires) . ")\n";
}
