<?php
/** La langue de l'interface : le réglage, le menu, les pages, les pop-ups, les pluriels. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-58s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$email = 'langue-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Langue_essai', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai langue']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_langue.txt';
@unlink($ck);
$appel = static function (string $chemin, ?array $post = null) use ($ck): array {
    usleep(350000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_HTTP_CODE), $corps];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$langueDeLaPage = static fn (string $html): string => preg_match('/<html lang="([a-z]+)"/', $html, $m) ? $m[1] : 'absent';

try {
    [, $p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    [, $compte] = $appel('compte');
    $csrf = $jeton($compte);

    echo "\n1. Le réglage\n";
    $dire('« Langue » se lit repliée, et se déplie par « Modifier »',
        $oui(str_contains($compte, '🇫🇷 Français') && str_contains($compte, 'action="/mon_appli/appli/compte/langue"')
            && str_contains($compte, 'data-reglage-edition hidden')), 'oui');
    $dire('  les quatre langues sont proposées',
        (string) preg_match_all('/name="langue" value="[a-z]{2}"/', $compte), '4');
    $dire('  au départ, tout est en français', $langueDeLaPage($compte) . ' · ' . $oui(str_contains($compte, 'Mon compte')), 'fr · oui');

    echo "\n2. Changer de langue\n";
    [, $r] = $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);
    $dire('« English » : gardé, et le message est déjà en anglais',
        bd_valeur('SELECT langue FROM users WHERE id = ?', [$id]) . ' · ' . $oui(str_contains($r, 'Language: English.')), 'en · oui');
    [, $accueil] = $appel('');
    $dire('  la page s’annonce en anglais, menu compris',
        $langueDeLaPage($accueil) . ' · ' . $oui(str_contains($accueil, '>Home</a>') && str_contains($accueil, '>My courses</a>')
            && str_contains($accueil, 'My tasks') && str_contains($accueil, 'Exams &amp; assignments')), 'en · oui');
    $dire('  la date garde sa majuscule anglaise',
        $oui(preg_match('/Today is \d{1,2} (January|February|March|April|May|June|July|August|September|October|November|December) \d{4}/', $accueil) === 1), 'oui');

    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'es']);
    [, $accueilEs] = $appel('');
    $dire('« Español » : menu et accueil suivent',
        $langueDeLaPage($accueilEs) . ' · ' . $oui(str_contains($accueilEs, '>Inicio</a>') && str_contains($accueilEs, 'Mis tareas')), 'es · oui');
    $dire('  et les mois restent en minuscule, comme il se doit',
        $oui(preg_match('/Hoy es \d{1,2} de (enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre) de \d{4}/', $accueilEs) === 1), 'oui');

    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'de']);
    [, $accueilDe] = $appel('');
    $dire('« Deutsch » : menu et accueil suivent',
        $langueDeLaPage($accueilDe) . ' · ' . $oui(str_contains($accueilDe, '>Start</a>') && str_contains($accueilDe, 'Meine Aufgaben')), 'de · oui');
    $dire('  la date prend le point de l’ordinal : « 4. Oktober »',
        $oui(preg_match('/Heute ist der \d{1,2}\. (Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember) \d{4}/u', $accueilDe) === 1), 'oui');

    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'klingon']);
    $dire('une langue inconnue revient au français',
        bd_valeur('SELECT langue FROM users WHERE id = ?', [$id]) . ' · ' . $langueDeLaPage($appel('')[1]), 'fr · fr');

    echo "\n3. Le calendrier, les tâches et les cours\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);
    [, $cal] = $appel('calendrier');
    $dire('le calendrier : titre, vues, filtres',
        $oui(str_contains($cal, '<h1>Calendar</h1>') && str_contains($cal, '>Week</a>')
            && str_contains($cal, '>All subjects</option>') && str_contains($cal, '>Today</a>')), 'oui');
    $dire('  les jours, les mois et le bouton du jour',
        $oui(str_contains($cal, '>Mon<') && str_contains($cal, '>Year</a>')
            && str_contains($cal, '>Month</a>') && str_contains($cal, '>List</a>')), 'oui');
    [, $evt] = $appel('evenements/nouveau');
    $dire('  le formulaire d’un évènement, rappels compris',
        $oui(str_contains($evt, '>New event</h1>') && str_contains($evt, '>Start date</label>')
            && str_contains($evt, '>15 min</span>') && str_contains($evt, 'Add to the calendar')), 'oui');
    // Les onglets ne paraissent qu'avec au moins une liste.
    bd_run("INSERT INTO listes_taches (user_id, nom, icone, couleur, position) VALUES (?, 'Essai', '📋', '#4f46e5', 1)", [$id]);
    [, $tac] = $appel('taches');
    $dire('les tâches : titre, onglets, boutons',
        $oui(str_contains($tac, '<h1>My tasks</h1>') && str_contains($tac, 'Overdue')
            && str_contains($tac, '+ New list') && str_contains($tac, 'Create the list')), 'oui');
    [, $crs] = $appel('cours');
    $dire('les cours : titre, filtres, état vide',
        $oui(str_contains($crs, '<h1>My courses</h1>') && str_contains($crs, '>Sort by</label>')
            && str_contains($crs, 'Recently edited') && str_contains($crs, '+ New course')), 'oui');
    [, $crsf] = $appel('cours/nouveau');
    $dire('  le formulaire d’un cours',
        $oui(str_contains($crsf, '>New course</h1>') && str_contains($crsf, '>Course title</label>')
            && str_contains($crsf, 'Create the course')), 'oui');

    echo "\n4. La fiche de révision d’un cours\n";
    [, $cf] = $appel('cours/nouveau');
    $appel('cours/nouveau', ['_csrf' => $jeton($cf), 'titre' => 'Langue essai', 'contenu' => 'x']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    [, $fiche] = $appel('revision/' . $cours);
    $dire('la fiche : titre, note, éléments rattachés',
        $oui(str_contains($fiche, '>Revision sheet</h2>') && str_contains($fiche, '>What to remember</label>')
            && str_contains($fiche, 'Attached items') && str_contains($fiche, '📎 Files and images')), 'oui');
    $dire('  les rayons : cartes, liens, autres cours, calendrier',
        $oui(str_contains($fiche, 'No card for this course.') && str_contains($fiche, '+ Add a link')
            && str_contains($fiche, '📘 Other courses') && str_contains($fiche, 'Your calendar is still empty.')
            && str_contains($fiche, 'Nothing yet.')), 'oui');
    $dire('  le dépôt et les boutons de la fiche',
        $oui(str_contains($fiche, 'Drop here') && str_contains($fiche, 'Attach to the sheet')
            && str_contains($fiche, 'Save the sheet') && str_contains($fiche, 'Back to the sheets')), 'oui');
    [, $page] = $appel('cours/' . $cours);
    $dire('  et la page du cours elle-même',
        $oui(str_contains($page, '← My courses') && str_contains($page, 'Attached files')), 'oui');

    echo "\n5. Les pop-ups et l’organisation\n";
    // Un évènement à regarder dans sa fenêtre.
    bd_run("INSERT INTO evenements (user_id, titre, debut, fin, journee_entiere) VALUES (?, 'Langue essai', ?, ?, 0)",
        [$id, date('Y-m-d H:i:s', strtotime('+2 days 14:00')), date('Y-m-d H:i:s', strtotime('+2 days 16:00'))]);
    $evtId = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    [, $fenetre] = $appel('evenements/' . $evtId . '?fenetre=1');
    $dire('la fiche d’un évènement, en fenêtre',
        $oui(str_contains($fenetre, 'Mark as done') && str_contains($fenetre, 'Goes to')
            && str_contains($fenetre, 'My events')), 'oui');
    [, $modif] = $appel('evenements/' . $evtId . '/modifier');
    $dire('  et son formulaire de modification',
        $oui(str_contains($modif, '>Start date</label>') && str_contains($modif, 'Save')), 'oui');
    [, $types] = $appel('organisation/types');
    $dire('les types d’évènement',
        $oui(str_contains($types, '<h1>Event types</h1>') && str_contains($types, 'New type')
            && str_contains($types, 'Counts as a deadline') && str_contains($types, 'Create the type')), 'oui');
    [, $mat] = $appel('organisation/matieres');
    $dire('les matières',
        $oui(str_contains($mat, '<h1>My subjects</h1>') && str_contains($mat, 'New subject')
            && str_contains($mat, 'Create the subject')), 'oui');
    [, $tags] = $appel('organisation/tags');
    $dire('les tags',
        $oui(str_contains($tags, '<h1>My tags</h1>') && str_contains($tags, 'New tags')
            && str_contains($tags, 'How it works')), 'oui');

    [, $agendas] = $appel('agenda');
    $dire('« Mes agendas » et le réglage de la vue',
        $oui(str_contains($agendas, '<h1>My calendars</h1>')
            && str_contains($agendas, 'The calendar view on opening')
            && str_contains($agendas, 'Connect an online calendar')), 'oui');
    $dire('  chaque agenda dit où il en est',
        $oui(str_contains($agendas, 'not connected') || str_contains($agendas, 'not enabled here')), 'oui');
    [, $unAgenda] = $appel('agenda/google');
    $dire('  et sa page : le titre, le bouton, la promesse',
        $oui(str_contains($unAgenda, 'Google Agenda calendar') || str_contains($unAgenda, 'The link with')), 'oui');

    [, $focus] = $appel('focus');
    $dire('la session de révision : le départ et le suivi',
        $oui(str_contains($focus, 'Revision session') && str_contains($focus, '>Start</h2>')
            && str_contains($focus, 'What I am revising') && str_contains($focus, 'Where I stand')), 'oui');
    $dire('  les rythmes, l’objectif et le bilan par matière',
        $oui(str_contains($focus, '25 min of work, 5 of break') && str_contains($focus, 'Goal for the week')
            && str_contains($focus, 'No goal') && str_contains($focus, 'Plan a session')), 'oui');

    echo "\n6. Les messages du serveur\n";
    [, $apres] = $appel('types', ['_csrf' => $csrf, 'nom' => '']);
    $dire('un type sans nom : le refus est en anglais',
        $oui(str_contains($apres, 'The type needs a name.')), 'oui');
    [, $apresM] = $appel('matieres', ['_csrf' => $csrf, 'nom' => 'Langue essai', 'couleur' => '#4f46e5']);
    $dire('une matière créée : la confirmation aussi',
        $oui(str_contains($apresM, 'Subject “Langue essai” created.')), 'oui');
    bd_run('DELETE FROM matieres WHERE user_id = ? AND nom = ?', [$id, 'Langue essai']);

    echo "\n7. Le pluriel\n";
    $pluriel = static function (string $langue, string $cle, int $n) {
        $phrases = require 'C:/wamp64/www/mon_appli/appli/lang/' . $langue . '.php';
        $seul = $langue === 'fr' ? abs($n) < 2 : abs($n) === 1;
        return str_replace('{n}', (string) $n, (string) ($phrases[$cle . ($seul ? '.un' : '.plusieurs')] ?? '?'));
    };
    $dire('le français garde le singulier à zéro',
        $pluriel('fr', 'cal.evenements_nombre', 0) . ' · ' . $pluriel('fr', 'cal.evenements_nombre', 1)
        . ' · ' . $pluriel('fr', 'cal.evenements_nombre', 3), '0 évènement · 1 évènement · 3 évènements');
    $dire('l’anglais prend le pluriel à zéro',
        $pluriel('en', 'cal.evenements_nombre', 0) . ' · ' . $pluriel('en', 'cal.evenements_nombre', 1),
        '0 events · 1 event');

    echo "\n8. Les fichiers de langue\n";
    $phrasesDe = static fn (string $langue): array => require 'C:/wamp64/www/mon_appli/appli/lang/' . $langue . '.php';
    $fr = $phrasesDe('fr');
    foreach (['en', 'es', 'de'] as $langue) {
        $autre = $phrasesDe($langue);
        $dire('  ' . $langue . ' : les mêmes clés que le français',
            count(array_diff(array_keys($fr), array_keys($autre))) . ' manquante(s) · '
            . count(array_diff(array_keys($autre), array_keys($fr))) . ' en trop', '0 manquante(s) · 0 en trop');
    }
    $orphelines = [];
    foreach (array_keys($fr) as $cle) {
        if (str_ends_with($cle, '.un') && !isset($fr[substr($cle, 0, -3) . '.plusieurs'])) { $orphelines[] = $cle; }
    }
    $dire('  chaque clé au pluriel a bien ses deux moitiés',
        count($orphelines) . ' orpheline(s)', '0 orpheline(s)');
    $dire('  les phrases du script partent bien dans la page',
        (string) count(array_filter(array_keys($fr), static fn (string $c): bool => str_starts_with($c, 'js.')))
        . ' clés « js. »', count(array_filter(array_keys($fr), static fn (string $c): bool => str_starts_with($c, 'js.'))) . ' clés « js. »');

    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
} finally {
    bd_run('DELETE FROM users WHERE email = ?', [$email]);
    @unlink($ck);
}

echo $anomalies === 0 ? "\nAucune anomalie" : "\n$anomalies anomalie(s)";
echo ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'langue-%@exemple-test.fr'") . "\n";
