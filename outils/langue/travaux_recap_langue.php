<?php
/**
 * Le récapitulatif d'un travail de groupe en PDF : le calcul (jour de début, jour de fin, tâches restantes jour après jour, rythme idéal, tâches
 * ajoutées, tâches par personne ; un projet terminé ou en cours, sans tâche, d'une journée, de plusieurs années), puis le PDF téléchargé (HTTP :
 * qui y a droit, ce qu'il dit, ses pages, le bouton du tableau) et les quatre langues.
 */
require __DIR__ . '/base.php';
require dirname(__DIR__, 2) . '/src/RecapProjet.php';

date_default_timezone_set('Europe/Paris');

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE);

/* --- 1. Le calcul -------------------------------------------------------------------------------------------------------------------------- */

echo "\n1. Le calcul, sur des données écrites à la main\n";
$membres = [
    ['id' => 1, 'nom_affiche' => 'Alma', 'user_id' => 10],
    ['id' => 2, 'nom_affiche' => 'Zoé', 'user_id' => 11],
    ['id' => 3, 'nom_affiche' => 'Léo', 'user_id' => null],
];
$tache = static fn (int $id, ?int $membre, string $statut, string $cree, ?string $fait): array => [
    'id' => $id, 'titre' => 'Tâche ' . $id, 'membre_id' => $membre, 'statut' => $statut, 'created_at' => $cree . ' 10:00:00', 'fait_le' => $fait === null ? null : $fait . ' 18:00:00',
];
$projet = ['created_at' => '2026-10-01 09:00:00'];
$taches = [
    $tache(1, 1, 'fait', '2026-10-01', '2026-10-03'),
    $tache(2, 1, 'fait', '2026-10-01', '2026-10-05'),
    $tache(3, 2, 'fait', '2026-10-02', '2026-10-05'),
    $tache(4, 3, 'fait', '2026-10-04', '2026-10-06'),
    $tache(5, null, 'fait', '2026-10-04', '2026-10-06'),
];
$r = RecapProjet::calculer($projet, $membres, $taches, '2026-10-20');
$dire('toutes les tâches faites : projet terminé, il s\'arrête le jour de la dernière (le 6), pas aujourd\'hui',
    $r['debut'] . ' · ' . $r['fin'] . ' · ' . $oui($r['termine']) . ' · ' . $r['jours'] . ' jours · axe ' . $r['axe'], '2026-10-01 · 2026-10-06 · oui · 6 jours · axe 5');
$dire('5 tâches, 5 faites, 100 %', $r['total'] . ' · ' . $r['faites'] . ' · ' . $r['part'], '5 · 5 · 100');
$dire('tâches restantes à la fin de chaque jour', $json(array_column($r['courbe'], 'restant')), '[2,3,2,4,2,0]');
$dire('tâches ajoutées chaque jour', $json(array_column($r['courbe'], 'ajoutees')), '[2,1,0,2,0,0]');
$dire('rythme idéal : de 5 (le total) à 0, en ligne droite', $json(array_column($r['courbe'], 'ideal')), '[5,4,3,2,1,0]');
$dire('les jours de la courbe', $r['courbe'][0]['jour'] . ' … ' . $r['courbe'][5]['jour'] . ' · ' . count($r['courbe']), '2026-10-01 … 2026-10-06 · 6');
$dire('par personne : Alma 2/2, puis Léo 1/1 et Zoé 1/1 (à égalité, par nom)',
    implode(' · ', array_map(static fn (array $p): string => $p['nom'] . ' ' . $p['faites'] . '/' . $p['total'], $r['personnes'])), 'Alma 2/2 · Léo 1/1 · Zoé 1/1');
$dire('Léo n\'a pas de compte ; la tâche de personne va à part', $oui($r['personnes'][1]['sans_compte'] && !$r['personnes'][0]['sans_compte']) . ' · ' . $r['sans_responsable']['faites'] . '/' . $r['sans_responsable']['total'], 'oui · 1/1');
$dire('les tâches d\'une personne : les faites d\'abord, de la plus ancienne à la plus récente',
    implode(' · ', array_map(static fn (array $l): string => $l['titre'] . ' ' . $l['fait_le'], $r['personnes'][0]['taches'])), 'Tâche 1 2026-10-03 · Tâche 2 2026-10-05');
$dire('l\'axe va jusqu\'à 5 (le plus grand compte : 5 tâches)', (string) $r['maximum'], '5');

$enCours = RecapProjet::calculer($projet, $membres, array_merge($taches, [$tache(6, 2, 'en_cours', '2026-10-02', null), $tache(7, 3, 'a_faire', '2026-10-07', null)]), '2026-10-09');
$dire('une tâche pas faite : projet en cours, la courbe va jusqu\'à aujourd\'hui (le 9)',
    $oui(!$enCours['termine']) . ' · ' . $enCours['fin'] . ' · ' . $enCours['axe'] . ' · ' . $enCours['faites'] . '/' . $enCours['total'] . ' · ' . $enCours['part'] . ' %', 'oui · 2026-10-09 · 8 · 5/7 · 71 %');
$dire('  et il en reste 2 à la fin (la 6 et la 7), dont une ajoutée le 7',
    end($enCours['courbe'])['restant'] . ' · ' . $enCours['courbe'][6]['ajoutees'] . ' · ' . $enCours['courbe'][6]['jour'], '2 · 1 · 2026-10-07');
$dire('  Zoé a 1 faite sur 2 (une en cours) : l\'en cours passe après la faite',
    $json(array_column(array_values(array_filter($enCours['personnes'], static fn (array $p): bool => $p['nom'] === 'Zoé'))[0]['taches'], 'statut')), '["fait","en_cours"]');

$avecPoints = RecapProjet::calculer($projet, $membres, $taches, '2026-10-20', [
    1 => [['texte' => 'a', 'fait' => true, 'fait_le' => '2026-10-02 10:00:00', 'par_user_id' => 10], ['texte' => 'b', 'fait' => true, 'fait_le' => '2026-10-03 10:00:00', 'par_user_id' => 10], ['texte' => 'c', 'fait' => false, 'fait_le' => null, 'par_user_id' => null]],
    3 => [['texte' => 'd', 'fait' => true, 'fait_le' => '2026-10-04 10:00:00', 'par_user_id' => 11]],
]);
$dire('les points cochés par personne (par compte) : Alma 2, Zoé 1, Léo (sans compte) 0',
    implode(' · ', array_map(static fn (array $p): string => $p['nom'] . ' ' . $p['points_coches'], $avecPoints['personnes'])), 'Alma 2 · Léo 0 · Zoé 1');
$dire('l\'avancement des points sur la tâche (2 cochés sur 3), et rien pour une tâche sans point',
    $json($avecPoints['personnes'][0]['taches'][0]['points']) . ' · ' . $json($avecPoints['personnes'][0]['taches'][1]['points']), '[2,3] · null');
$dire('sans points donnés, les chiffres sont ceux d\'avant : 0 point coché, pas d\'avancement de points', $r['personnes'][0]['points_coches'] . ' · ' . $json($r['personnes'][0]['taches'][0]['points']), '0 · null');
$sansDate = RecapProjet::calculer($projet, $membres, [$tache(1, 1, 'fait', '2026-10-02', null), $tache(2, 1, 'a_faire', '2026-10-02', null)], '2026-10-04');
$dire('une tâche faite sans date est comptée faite le jour de sa création', $json(array_column($sansDate['courbe'], 'restant')) . ' · ' . $sansDate['personnes'][0]['taches'][0]['fait_le'], '[0,1,1,1] · 2026-10-02');
$avant = RecapProjet::calculer($projet, $membres, [$tache(1, 1, 'a_faire', '2026-09-20', null)], '2026-10-02');
$dire('une tâche datée d\'avant le projet est comptée dès son premier jour', $json(array_column($avant['courbe'], 'restant')) . ' · ' . $avant['debut'], '[1,1] · 2026-10-01');

$vide = RecapProjet::calculer($projet, $membres, [], '2026-10-01');
$dire('sans tâche, un seul jour : 0 %, courbe plate, pas de division par zéro',
    $vide['total'] . ' · ' . $vide['part'] . ' % · ' . $oui(!$vide['termine']) . ' · ' . $vide['axe'] . ' · ' . $json(array_column($vide['courbe'], 'restant')) . ' · ' . $json(array_column($vide['courbe'], 'ideal')) . ' · ' . $vide['maximum'] . ' · ' . $oui($vide['sans_responsable'] === null), '0 · 0 % · oui · 1 · [0,0] · [0,0] · 1 · oui');
$uneJournee = RecapProjet::calculer($projet, $membres, [$tache(1, 1, 'fait', '2026-10-01', '2026-10-01')], '2026-10-01');
$dire('un projet fait en une journée : terminé, 1 jour de travail', $oui($uneJournee['termine']) . ' · ' . $uneJournee['jours'] . ' jour · ' . $json(array_column($uneJournee['courbe'], 'restant')), 'oui · 1 jour · [0,0]');

$long = RecapProjet::calculer(['created_at' => '2020-01-01 00:00:00'], $membres, [$tache(1, 1, 'a_faire', '2020-01-01', null)], '2026-10-08');
$dire('un projet de six ans : la courbe est échantillonnée (400 points au plus) et finit le dernier jour',
    $oui(count($long['courbe']) <= 402) . ' · ' . $long['courbe'][count($long['courbe']) - 1]['indice'] . ' = ' . $long['axe'] . ' · ' . $long['jours'] . ' jours', 'oui · 2472 = 2472 · 2473 jours');
$dire('  (sans tâche perdue dans l\'échantillon : celle du premier jour est comptée)', (string) $long['courbe'][0]['ajoutees'] . ' · ' . $long['courbe'][0]['restant'], '1 · 1');
$sansMembre = RecapProjet::calculer($projet, [], [$tache(1, 99, 'fait', '2026-10-01', '2026-10-02')], '2026-10-03');
$dire('une tâche confiée à quelqu\'un qui n\'est plus du projet va dans « sans responsable »', $sansMembre['sans_responsable']['faites'] . '/' . $sansMembre['sans_responsable']['total'] . ' · ' . count($sansMembre['personnes']), '1/1 · 0');

/* --- 2. Le PDF ---------------------------------------------------------------------------------------------------------------------------- */

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'trec-a@exemple-test.fr', 'pseudo' => 'Trec_Alma'],
    'b' => ['email' => 'trec-b@exemple-test.fr', 'pseudo' => 'Trec_Zoe'],
    'c' => ['email' => 'trec-c@exemple-test.fr', 'pseudo' => 'Trec_Dehors'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$jar = static fn (string $nom): string => __DIR__ . '/ck_trec_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null) use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $brut = (string) curl_exec($h);
    $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), substr($brut, $taille), substr($brut, 0, $taille), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

/** Le texte écrit dans un PDF de PdfSimple, page par page (UTF-8) : les flux sont compressés, le texte est en Windows-1252. */
$pdfTextes = static function (string $pdf): array {
    $pages = [];
    preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $flux);
    foreach ($flux[1] as $brut) {
        $contenu = @gzuncompress($brut);
        if ($contenu === false || !str_contains($contenu, ' Tj')) {
            if ($contenu !== false) { $pages[] = ['texte' => '', 'operations' => $contenu]; }
            continue;
        }
        preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', $contenu, $m);
        $morceaux = array_map(static fn (string $s): string => (string) mb_convert_encoding(strtr($s, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\', '\\r' => "\r"]), 'UTF-8', 'Windows-1252'), $m[1]);
        $pages[] = ['texte' => implode("\n", $morceaux), 'operations' => $contenu];
    }
    return $pages;
};
$tout = static fn (array $pages): string => implode("\n", array_column($pages, 'texte'));

$projets = [];
try {
    foreach (['a', 'b', 'c'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    $jourPlus = static fn (int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
    bd_run('INSERT INTO projets (nom, cree_par, jeton, created_at) VALUES (?, ?, ?, ?)', ['Rapport « Étoiles » (essai)', $id('a'), bin2hex(random_bytes(16)), $jourPlus(-9) . ' 09:00:00']);
    $p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $p;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre'), (?, ?, 'membre', 'membre')", [$p, $id('a'), $p, $id('b')]);
    bd_run("INSERT INTO projet_membres (projet_id, user_id, nom, role, statut) VALUES (?, NULL, 'Léo Sans-Compte', 'membre', 'membre')", [$p]);
    $mA = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$p, $id('a')]);
    $mB = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$p, $id('b')]);
    $mL = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id IS NULL', [$p]);
    $tacheSql = 'INSERT INTO projet_taches (projet_id, titre, membre_id, statut, created_at, fait_le) VALUES (?, ?, ?, ?, ?, ?)';
    foreach ([['Écrire l\'introduction', $mA, -9, -6], ['Chercher les sources', $mB, -9, -4], ['Faire les schémas', $mL, -8, -3], ['Relire le tout', $mA, -5, -1], ['Mettre en page', null, -5, -1]] as [$titre, $m, $cree, $fait]) {
        bd_run($tacheSql, [$p, $titre, $m, 'fait', $jourPlus($cree) . ' 10:00:00', $jourPlus($fait) . ' 17:30:00']);
    }

    // Des points à cocher sur « Relire le tout » (d'Alma) : deux cochés par Alma, un par Zoé.
    $tacheRelire = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Relire le tout']);
    foreach ([['Orthographe', 1, $id('a')], ['Citations', 1, $id('a')], ['Mise en forme', 1, $id('b')]] as $k => [$texte, $fait, $par]) {
        bd_run('INSERT INTO projet_points (tache_id, texte, fait, fait_par, fait_le, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tacheRelire, $texte, $fait, $par, $jourPlus(-1) . ' 16:00:00', $k + 1, $jourPlus(-5) . ' 10:00:00']);
    }
    echo "\n2. Le PDF d'un projet terminé\n";
    [$cod, $pdf, $entetes] = $appel('a', 'travaux/' . $p . '/recapitulatif');
    $dire('un PDF en pièce jointe, avec un nom lisible', $cod . ' · ' . $oui(str_contains($entetes, 'Content-Type: application/pdf') && str_contains($entetes, 'attachment') && str_contains($entetes, 'filename*=UTF-8\'\'R%C3%A9capitulatif%20-%20Rapport')), '200 · oui');
    $dire('un vrai PDF (entête, fin, au moins une page)', $oui(str_starts_with($pdf, '%PDF-1.4') && str_ends_with(rtrim($pdf), '%%EOF') && substr_count($pdf, '/Type /Page ') >= 1), 'oui');
    $pages = $pdfTextes($pdf);
    $texte = $tout($pages);
    $dire('le titre, le nom du projet et « Projet terminé le … » (la date du dernier jour)',
        $oui(str_contains($texte, 'Récapitulatif du travail de groupe') && str_contains($texte, 'Rapport « Étoiles » (essai)') && str_contains($texte, 'Projet terminé le ' . (int) date('j', strtotime($jourPlus(-1))) . ' ')), 'oui');
    $dire('les chiffres : 5 / 5 tâches faites, 100 %, 9 jours de travail, 3 membres',
        $oui(str_contains($texte, "5 / 5\n") && str_contains($texte, '100 %') && str_contains($texte, "9\njours de travail") && str_contains($texte, "3\nMembres")), 'oui');
    $dire('« Tâches faites par personne » : Trec_Alma 2 / 2, Trec_Zoe 1 / 1, Léo Sans-Compte 1 / 1, et « Sans responsable » 1 / 1',
        $oui(preg_match('/Tâches faites par personne\nTrec_Alma\n.*?2 \/ 2\nLéo Sans-Compte\n.*?1 \/ 1\nTrec_Zoe\n.*?1 \/ 1\nSans responsable\n.*?1 \/ 1/s', $texte) === 1), 'oui');
    $dire('le burn down : son titre, sa légende et les dates sur l\'axe',
        $oui(str_contains($texte, 'Burn down : les tâches restantes au fil du projet') && str_contains($texte, 'Tâches restantes') && str_contains($texte, 'Rythme idéal') && str_contains($texte, 'Tâches ajoutées ce jour-là')
            && str_contains($texte, date('j/n', strtotime($jourPlus(-9)))) && str_contains($texte, date('j/n', strtotime($jourPlus(-1))))), 'oui');
    $ops = implode("\n", array_column($pages, 'operations'));
    $dire('le graphique est dessiné : la courbe bleue (RG), le rythme idéal orange, des barres jaunes',
        $oui(str_contains($ops, '0.100 0.270 0.500 RG') && str_contains($ops, '0.930 0.490 0.130 RG') && str_contains($ops, '1.000 0.750 0.000 rg') && substr_count($ops, ' l ') >= 5), 'oui');
    $dire('« Qui a fait quoi » : chaque tâche, avec « fait le … », sous son responsable',
        $oui(preg_match('/Qui a fait quoi\nTrec_Alma\n2 points cochés\n2 \/ 2\nÉcrire l\'introduction\nfait le \d+ [^\n]+\nRelire le tout\n3\/3 points · fait le/u', $texte) === 1 && str_contains($texte, 'Faire les schémas') && str_contains($texte, 'Mettre en page')), 'oui');
    $dire('les points : « 3/3 points » sur la tâche, et les points cochés de chacun (Alma 2, Zoé 1) sous leur nom',
        $oui(str_contains($texte, '3/3 points · fait le') && preg_match('/Trec_Alma\n2 points cochés\n/u', $texte) === 1 && preg_match('/Trec_Zoe\n1 point coché\n/u', $texte) === 1), 'oui');
    $dire('le pied de page : le projet, la date de génération, « Page n / N »', $oui(str_contains($texte, 'Généré le ') && str_contains($texte, 'Page 1 /')), 'oui');

    echo "\n3. Qui y a droit\n";
    [$cod] = $appel('b', 'travaux/' . $p . '/recapitulatif');
    $dire('un autre membre (avec un compte) le télécharge aussi', (string) $cod, '200');
    [$cod, $corps] = $appel('c', 'travaux/' . $p . '/recapitulatif');
    $dire('quelqu\'un qui n\'est pas du projet : 404, pas de PDF', $cod . ' · ' . $oui(!str_starts_with($corps, '%PDF')), '404 · oui');
    [$cod, $corps, , $adresse] = $appel('anonyme', 'travaux/' . $p . '/recapitulatif');
    $dire('sans compte : renvoyé à la connexion, pas de PDF', $oui(!str_starts_with($corps, '%PDF') && str_contains($adresse, '/connexion')), 'oui');
    [$cod] = $appel('a', 'travaux/999999999/recapitulatif');
    $dire('un projet qui n\'existe pas : 404', (string) $cod, '404');

    echo "\n4. Le bouton, dans le tableau\n";
    [, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('« Récapitulatif (PDF) » dans « Répartition », et « Projet terminé » puisque tout est fait',
        $oui(str_contains($tableau, 'href="/mon_appli/appli/travaux/' . $p . '/recapitulatif"') && str_contains($tableau, '📄 Récapitulatif (PDF)') && str_contains($tableau, '✅ Projet terminé')), 'oui');
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, statut, created_at) VALUES (?, ?, ?, ?, ?)', [$p, 'Dernière tâche', $mB, 'en_cours', $jourPlus(-2) . ' 08:00:00']);
    [, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('une tâche pas faite : le bouton reste, plus de « Projet terminé »', $oui(str_contains($tableau, '📄 Récapitulatif (PDF)') && !str_contains($tableau, 'Projet terminé')), 'oui');
    [$cod, $pdf2] = $appel('a', 'travaux/' . $p . '/recapitulatif');
    $texte2 = $tout($pdfTextes($pdf2));
    $dire('et le PDF dit « Projet en cours, état au … » (aujourd\'hui), avec « 5 / 6 »',
        $oui(str_contains($texte2, 'Projet en cours, état au ' . (int) date('j') . ' ') && !str_contains($texte2, 'Projet terminé le') && str_contains($texte2, "5 / 6\n") && str_contains($texte2, 'En cours')), 'oui');
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Projet vide (essai)', $id('a'), bin2hex(random_bytes(16))]);
    $q = (int) bd_valeur('SELECT id FROM projets WHERE nom = ? AND cree_par = ?', ['Projet vide (essai)', $id('a')]);
    $projets[] = $q;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$q, $id('a')]);
    [, $tableauVide] = $appel('a', 'travaux/' . $q);
    $dire('un projet sans tâche : pas de bouton', $oui(!str_contains($tableauVide, '/recapitulatif')), 'oui');
    [$cod, $pdfVide] = $appel('a', 'travaux/' . $q . '/recapitulatif');
    $dire('mais l\'adresse répond : un PDF qui dit « Aucune tâche pour l\'instant. »', $cod . ' · ' . $oui(str_contains($tout($pdfTextes($pdfVide)), 'Aucune tâche pour l’instant.') && str_contains($tout($pdfTextes($pdfVide)), 'Projet en cours')), '200 · oui');

    echo "\n4b. Cocher des cases met le PDF à jour\n";
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Cases cochées (essai)', $id('a'), bin2hex(random_bytes(16))]);
    $c4 = (int) bd_valeur('SELECT id FROM projets WHERE nom = ? AND cree_par = ?', ['Cases cochées (essai)', $id('a')]);
    $projets[] = $c4;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$c4, $id('a')]);
    [, $formulaire] = $appel('a', 'travaux/' . $c4 . '/taches/nouvelle');
    $appel('a', 'travaux/' . $c4 . '/taches', ['_csrf' => $jeton($formulaire), 'titre' => 'Écrire', 'points' => "Plan\nBrouillon"]);
    $tacheCases = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$c4, 'Écrire']);
    $lesPoints = array_column(bd_all('SELECT id FROM projet_points WHERE tache_id = ? ORDER BY position', [$tacheCases]), 'id');
    $pdfCases = static fn (): string => $tout($pdfTextes($appel('a', 'travaux/' . $c4 . '/recapitulatif')[1]));
    $avant = $pdfCases();
    $dire('aucune case cochée : « 0 / 1 », « Projet en cours »', $oui(str_contains($avant, "0 / 1\n") && str_contains($avant, 'Projet en cours') && str_contains($avant, '0/2 points')), 'oui');
    [, $tableauCases] = $appel('a', 'travaux/' . $c4);
    $appel('a', 'travaux/points/' . $lesPoints[0] . '/cocher', ['_csrf' => $jeton($tableauCases), 'fait' => '1']);
    $milieu = $pdfCases();
    $dire('une case sur deux cochée : la tâche est « En cours », « 1/2 points », « 1 point coché »',
        $oui(str_contains($milieu, '1/2 points · En cours') && str_contains($milieu, '1 point coché') && str_contains($milieu, 'Projet en cours')), 'oui');
    $appel('a', 'travaux/points/' . $lesPoints[1] . '/cocher', ['_csrf' => $jeton($tableauCases), 'fait' => '1']);
    $apres = $pdfCases();
    $dire('toutes cochées : « 1 / 1 », « 100 % », « Projet terminé le … », « 2 points cochés »',
        $oui(str_contains($apres, "1 / 1\n") && str_contains($apres, '100 %') && str_contains($apres, 'Projet terminé le') && str_contains($apres, '2 points cochés') && str_contains($apres, '2/2 points · fait le')), 'oui');
    [, $tableauCases] = $appel('a', 'travaux/' . $c4);
    $dire('et le tableau le dit aussi : la carte est dans « Fait », avec « Projet terminé »',
        $oui(preg_match('#data-statut="fait".*?id="tache-' . $tacheCases . '"#s', $tableauCases) === 1 && str_contains($tableauCases, '✅ Projet terminé')), 'oui');

    echo "\n5. Beaucoup de tâches : plusieurs pages\n";
    bd_run('INSERT INTO projets (nom, cree_par, jeton, created_at) VALUES (?, ?, ?, ?)', ['Grand projet (essai)', $id('a'), bin2hex(random_bytes(16)), $jourPlus(-30) . ' 09:00:00']);
    $g = (int) bd_valeur('SELECT id FROM projets WHERE nom = ? AND cree_par = ?', ['Grand projet (essai)', $id('a')]);
    $projets[] = $g;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$g, $id('a')]);
    $mG = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ?', [$g]);
    for ($i = 1; $i <= 70; $i++) {
        bd_run($tacheSql, [$g, 'Tâche numéro ' . $i . ' avec un titre assez long pour que certaines lignes se coupent en deux dans la page du récapitulatif ' . ($i % 7 === 0 ? str_repeat('ÉÀ', 80) : ''), $i % 5 === 0 ? null : $mG,
            $i % 3 === 0 ? 'a_faire' : 'fait', $jourPlus(-30 + (int) ($i / 3)) . ' 10:00:00', $i % 3 === 0 ? null : $jourPlus(-29 + (int) ($i / 2.5)) . ' 12:00:00']);
    }
    [$cod, $grand] = $appel('a', 'travaux/' . $g . '/recapitulatif');
    $pagesGrand = $pdfTextes($grand);
    $nb = substr_count($grand, '/Type /Page ');
    $dire('le PDF a plusieurs pages, numérotées « Page n / N » sur chacune',
        $cod . ' · ' . $oui($nb >= 3) . ' · ' . $oui(str_contains($tout($pagesGrand), 'Page 1 / ' . $nb) && str_contains($tout($pagesGrand), 'Page ' . $nb . ' / ' . $nb)), '200 · oui · oui');
    $dire('les 70 tâches y sont toutes (rien perdu au changement de page)', (string) preg_match_all('/Tâche numéro \d+ /u', $tout($pagesGrand)), '70');
    $dire('« Sans responsable » liste les tâches de personne (14)', (string) preg_match_all('/Tâche numéro (5|10|15|20|25|30|35|40|45|50|55|60|65|70) /u', substr($tout($pagesGrand), (int) strrpos($tout($pagesGrand), 'Sans responsable'))), '14');

    echo "\n6. Les quatre langues\n";
    $attendus = [
        'en' => ['Group project summary', 'Project finished on', 'Tasks done per person', 'Burn down: the tasks left over time', 'Ideal pace', 'Who did what', 'done on', 'Page 1 /', 'days of work', 'Nobody in charge', '3/3 items'],
        'es' => ['Resumen del trabajo en grupo', 'Proyecto terminado el', 'Tareas hechas por persona', 'Burn down: las tareas que quedan a lo largo del proyecto', 'Ritmo ideal', 'Quién hizo qué', 'hecha el', 'Página 1 /', 'días de trabajo', 'Sin responsable', '3/3 puntos'],
        'de' => ['Zusammenfassung der Gruppenarbeit', 'Projekt abgeschlossen am', 'Erledigte Aufgaben pro Person', 'Burn-down: die verbleibenden Aufgaben im Projektverlauf', 'Ideales Tempo', 'Wer hat was gemacht', 'erledigt am', 'Seite 1 /', 'Arbeitstage', 'Ohne Zuständige', '3/3 Punkte'],
    ];
    bd_run("DELETE FROM projet_taches WHERE titre = 'Dernière tâche' AND projet_id = ?", [$p]);
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $jeton($appel('a', 'compte')[1]), 'langue' => $langue]);
        [$cod, $pdfLangue, $entetesLangue] = $appel('a', 'travaux/' . $p . '/recapitulatif');
        $t = $tout($pdfTextes($pdfLangue));
        $manque = array_values(array_filter($mots, static fn (string $m): bool => !str_contains($t, $m)));
        $dire($langue . ' : le PDF est dans la langue du compte', $cod . ' · ' . ($manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque)), '200 · tout y est');
        $dire($langue . ' : ni clé brute ni français resté', $oui(!preg_match('/\btr\.rc\./', $t) && !str_contains($t, 'Tâches faites') && !str_contains($t, 'Projet terminé') && !str_contains($t, 'Qui a fait quoi')), 'oui');
        [, $tableauLangue] = $appel('a', 'travaux/' . $p);
        $bouton = ['en' => 'Summary (PDF)', 'es' => 'Resumen (PDF)', 'de' => 'Zusammenfassung (PDF)'][$langue];
        $dire($langue . ' : le bouton du tableau', $oui(str_contains($tableauLangue, '📄 ' . $bouton) && !str_contains($tableauLangue, 'Récapitulatif (PDF)')), 'oui');
    }
    $appel('a', 'compte/langue', ['_csrf' => $jeton($appel('a', 'compte')[1]), 'langue' => 'fr']);
    $termine = true;
} finally {
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach (glob(__DIR__ . '/ck_trec_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'trec-_@exemple-test.fr'") . "\n";
}
