<?php
/**
 * Les liens qui s'ouvrent dans une fenêtre : le cours lié d'un évènement s'ouvre dans la fenêtre de l'évènement
 * (et reste un lien ordinaire sur la page entière), le cours répond bien en fragment, les cloisonnements entre comptes.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-66s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 90), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['fen-a@exemple-test.fr', 'fen-b@exemple-test.fr'];
foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
foreach ($emails as $i => $a) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, 'Fenetre_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai fenêtres']);
}
[$idA, $idB] = array_map(static fn (string $a): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$a]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'DM à rendre', 'Le sujet du devoir.']);
$coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
bd_run('INSERT INTO evenements (user_id, titre, debut, fin, journee_entiere, cours_id) VALUES (?, ?, ?, ?, 1, ?)',
    [$idA, 'Rendre le DM', '2026-10-31 00:00:00', '2026-10-31 23:59:00', $coursA]);
$evenement = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ?', [$idA]);

$cookies = [$emails[0] => __DIR__ . '/ck_fen_a.txt', $emails[1] => __DIR__ . '/ck_fen_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }

    echo "\n1. Le cours lié d'un évènement\n";
    [$popup, , $code] = $appel($a, 'evenements/' . $evenement . '?fenetre=1');
    $dire('dans la fenêtre de l’évènement, le cours lié s’ouvre dans la fenêtre (lien « data-fenetre »)',
        $code . ' · ' . $oui(preg_match('#<a href="[^"]*/cours/' . $coursA . '" data-fenetre>DM à rendre</a>#', $popup) === 1
            && !str_contains($popup, '<header class="entete"')), '200 · oui');
    [$page] = $appel($a, 'evenements/' . $evenement);
    $dire('  sur la page entière de l’évènement, c’est un lien ordinaire (la page change)',
        $oui(preg_match('#<a href="[^"]*/cours/' . $coursA . '">DM à rendre</a>#', $page) === 1 && str_contains($page, '<header class="entete"')), 'oui');
    [$cours, , $code] = $appel($a, 'cours/' . $coursA . '?fenetre=1');
    $dire('  et le cours répond en fragment (sans bandeau ni menu), large, avec son titre et ses boutons',
        $code . ' · ' . $oui(!str_contains($cours, '<header class="entete"') && str_contains($cours, 'data-large data-document')
            && str_contains($cours, 'DM à rendre') && str_contains($cours, 'Modifier')), '200 · oui');
    [, , $code] = $appel($b, 'cours/' . $coursA . '?fenetre=1');
    $dire('  un autre compte ne voit ni l’évènement ni le cours', $code . ' · ' . $appel($b, 'evenements/' . $evenement . '?fenetre=1')[2], '404 · 404');
    [$js] = $appel($a, 'assets/js/app.js');
    $dire('  le script de la fenêtre sait ouvrir un lien « data-fenetre » posé dans une fenêtre',
        $oui(str_contains($js, "closest('[data-fenetre]')")), 'oui');

    echo "\n2. L'évènement d'un cours, depuis le cours\n";
    [$coursPage] = $appel($a, 'cours/' . $coursA);
    $dire('la liste « Au calendrier » du cours ouvre l’aperçu de l’évènement dans une fenêtre (lien « data-fenetre »)',
        $oui(preg_match('#<a class="evt-ligne" href="[^"]*/evenements/' . $evenement . '" data-fenetre>#', $coursPage) === 1), 'oui');
    bd_run('INSERT INTO fiche_elements (user_id, cours_id, type, cible_evenement_id) VALUES (?, ?, \'evenement\', ?)', [$idA, $coursA, $evenement]);
    [$fiche] = $appel($a, 'revision/' . $coursA);
    $dire('  et celle de sa fiche de révision aussi',
        $oui(preg_match('#<a href="[^"]*/evenements/' . $evenement . '" data-fenetre>#', $fiche) === 1), 'oui');
    [$form, , $code] = $appel($a, 'evenements/' . $evenement . '/modifier?fenetre=1');
    $dire('  le formulaire de l’évènement répond en fragment (sans bandeau ni menu)',
        $code . ' · ' . $oui(!str_contains($form, '<header class="entete"') && str_contains($form, 'Rendre le DM')), '200 · oui');

    echo "\n3. L'accueil : « Examens & devoirs »\n";
    bd_run('INSERT INTO types_evenement (user_id, nom, icone, couleur, est_echeance, au_tableau) VALUES (?, ?, ?, ?, 1, 1)', [$idA, 'Devoir', 'D', '#ea580c']);
    $type = (int) bd_valeur('SELECT id FROM types_evenement WHERE user_id = ?', [$idA]);
    $bientot = date('Y-m-d H:i:s', strtotime('+10 days'));
    bd_run('INSERT INTO evenements (user_id, type_id, cours_id, titre, debut, fin) VALUES (?, ?, ?, ?, ?, ?)', [$idA, $type, $coursA, 'Rendre le TP', $bientot, $bientot]);
    $devoir = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ? AND titre = ?', [$idA, 'Rendre le TP']);
    [$accueil] = $appel($a, '');
    $dire('« Cours » et « Révision » s\'ouvrent dans une fenêtre (lien « data-fenetre »)',
        $oui(preg_match('#<a class="bouton bouton--secondaire" href="[^"]*/cours/' . $coursA . '" data-fenetre#', $accueil) === 1
            && preg_match('#<a class="bouton bouton--secondaire" href="[^"]*/revision/' . $coursA . '" data-fenetre#', $accueil) === 1), 'oui');
    $dire('  le crayon aussi, et il ramène à l\'accueil (retour = « / »)',
        $oui(preg_match('#<a class="bouton bouton--discret bouton--petit" data-fenetre\s+href="[^"]*/evenements/' . $devoir . '/modifier\?retour=%2F"#', $accueil) === 1), 'oui');
    [$form, , $code] = $appel($a, 'evenements/' . $devoir . '/modifier?retour=%2F&fenetre=1');
    $dire('  le formulaire ouvert ainsi garde ce retour (enregistrer, et les deux boutons « supprimer »)',
        $code . ' · ' . (substr_count($form, 'name="retour" value="/"') >= 2 ? 'oui' : 'non'), '200 · oui');
    [$form] = $appel($a, 'evenements/' . $devoir . '/modifier');
    $dire('  sans retour demandé, le formulaire n\'en porte pas (le calendrier reste la destination)', $oui(!str_contains($form, 'name="retour"')), 'oui');

    $jour = date('Y-m-d', strtotime('+12 days'));
    $champs = ['_csrf' => $jeton($form), 'titre' => 'Rendre le TP (modifié)', 'type_id' => $type, 'date_debut' => $jour, 'date_fin' => $jour,
        'journee_entiere' => '1', 'cours_id' => $coursA];
    [, $adresse] = $appel($a, 'evenements/' . $devoir . '/modifier', $champs + ['retour' => '/']);
    $dire('  enregistré depuis l\'accueil : on revient à l\'accueil (et non au calendrier)',
        $oui(preg_match('#/appli/?$#', $adresse) === 1) . ' · ' . bd_valeur('SELECT titre FROM evenements WHERE id = ?', [$devoir]), 'oui · Rendre le TP (modifié)');
    [, $adresse] = $appel($a, 'evenements/' . $devoir . '/modifier', $champs + ['titre' => 'Rendre le TP (2)']);
    $dire('  enregistré sans retour : le calendrier, comme avant', $oui(str_contains($adresse, '/calendrier')), 'oui');
    [, $adresse] = $appel($a, 'evenements/' . $devoir . '/modifier', $champs + ['titre' => 'Rendre le TP (3)', 'retour' => 'https://exemple.org/piege']);
    $dire('  un retour qui n\'est pas un chemin interne est refusé : le calendrier', $oui(str_contains($adresse, '/calendrier') && !str_contains($adresse, 'exemple.org')), 'oui');
    [, $adresse] = $appel($a, 'evenements/' . $devoir . '/supprimer', ['_csrf' => $champs['_csrf'], 'retour' => '/']);
    $dire('  supprimé depuis l\'accueil : on revient à l\'accueil', $oui(preg_match('#/appli/?$#', $adresse) === 1) . ' · ' . bd_valeur('SELECT COUNT(*) FROM evenements WHERE id = ?', [$devoir]), 'oui · 0');
    $termine = true;
} finally {
    if (!$termine) { $anomalies++; echo "
   ✗ le script s'est arrêté avant la fin
"; }
    foreach ($cookies as $f) { @unlink($f); }
    foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr']) . "\n";
}
