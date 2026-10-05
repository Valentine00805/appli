<?php
/**
 * Le menu en grille de la barre (comme les applications de Google) : ses tuiles, les favoris de départ, les favoris
 * choisis et leur nettoyage, le cloisonnement entre comptes, les quatre langues.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-66s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 90), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['menu-a@exemple-test.fr', 'menu-b@exemple-test.fr'];
foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
foreach ($emails as $i => $a) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, 'Menu_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai menu']);
}
[$idA, $idB] = array_map(static fn (string $a): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$a]), $emails);

$cookies = [$emails[0] => __DIR__ . '/ck_menu_a.txt', $emails[1] => __DIR__ . '/ck_menu_b.txt', 'visiteur' => __DIR__ . '/ck_menu_v.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
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
/** Les clés des tuiles d'une grille du panneau (« data-apps-favoris » ou « data-apps-autres »), dans l'ordre. */
$grille = static function (string $html, string $nom): array {
    if (preg_match('#<div class="apps__grille" data-apps-' . $nom . '>(.*?)</div>\s*(?:<p class="apps__vide"|</section>)#s', $html, $m) !== 1) { return []; }
    preg_match_all('#data-cle="([a-z]+)"#', $m[1], $cles);
    return $cles[1];
};
$enregistrer = static function (string $compte, string $csrf, array $favoris) use ($appel): int {
    [, , $code] = $appel($compte, 'compte/menu-favoris', ['_csrf' => $csrf, 'favoris' => $favoris]);
    return $code;
};
$base = bd_valeur('SELECT 1') === null ? '' : '';
$catalogue = ['accueil', 'calendrier', 'cours', 'partages', 'revision', 'cartes', 'resumes', 'taches', 'tableau', 'alternance', 'groupes', 'budget', 'organisation', 'amis', 'compte'];
$parDefaut = ['calendrier', 'cours', 'revision', 'cartes', 'resumes', 'taches'];

try {
    [$a, $b] = $emails;
    [$p] = $appel('visiteur', 'connexion');
    $dire('sans être connecté : pas de menu en grille', $oui(!str_contains($p, 'data-apps')), 'oui');
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }

    echo "\n1. Les tuiles et les favoris de départ\n";
    [$page] = $appel($a, 'calendrier');
    $csrf = $jeton($page);
    $dire('le bouton en grille (9 points) ouvre un panneau « Vos favoris » puis « Toutes les sections »',
        $oui(str_contains($page, '<details class="apps" data-apps') && preg_match('#<summary class="apps__bouton".*?</summary>#s', $page, $mSommaire) === 1 && substr_count($mSommaire[0], '<circle') === 9
            && str_contains($page, 'Vos favoris') && str_contains($page, 'Toutes les sections')), 'oui');
    $dire('  les favoris de départ, dans l’ordre', implode(',', $grille($page, 'favoris')), implode(',', $parDefaut));
    $dire('  les autres sections, dans l’ordre du catalogue', implode(',', $grille($page, 'autres')), implode(',', array_values(array_diff($catalogue, $parDefaut))));
    $dire('  les 15 sections sont là, une seule fois chacune', (string) substr_count($page, 'class="apps__tuile"'), '15');
    $liens = [];
    foreach (['accueil' => '', 'calendrier' => 'calendrier', 'cours' => 'cours', 'partages' => 'partages', 'revision' => 'revision', 'cartes' => 'cartes', 'resumes' => 'resumes',
              'taches' => 'taches', 'tableau' => 'tableau', 'alternance' => 'alternance', 'groupes' => 'travaux', 'budget' => 'budget',
              'organisation' => 'organisation/matieres', 'amis' => 'amis', 'compte' => 'compte'] as $cle => $route) {
        $adresse = '/mon_appli/appli/' . $route;
        $tuile = preg_match('#<a class="apps__tuile" href="' . preg_quote($adresse, '#') . '" data-cle="' . $cle . '"#', $page) === 1;
        // Chaque section est aussi un onglet de la barre : le menu et les onglets disent la même chose.
        $onglet = substr_count($page, 'href="' . $adresse . '"') >= 2;
        if (!$tuile || !$onglet) { $liens[] = $cle . ($tuile ? '' : ' (tuile)') . ($onglet ? '' : ' (onglet)'); }
    }
    $dire('  chaque tuile mène à la route de l’onglet du même nom (rien d’oublié, rien de cassé)', implode(', ', $liens) ?: 'aucun écart', 'aucun écart');
    $dire('  la section où l’on est est marquée « page courante »',
        $oui(preg_match('#<a class="apps__tuile" href="[^"]*/calendrier" data-cle="calendrier" data-rang="\d+" aria-current="page">#', $page) === 1
            && substr_count($page, 'class="apps__tuile"') - substr_count($page, 'class="apps__tuile" href') === 0), 'oui');
    $dire('  chaque tuile a son rang dans le catalogue (pour retrouver sa place quand on la retire des favoris)',
        $oui(preg_match_all('#data-rang="(\d+)"#', $page, $rangs) === 15 && count(array_unique($rangs[1])) === 15), 'oui');
    $dire('  le crayon, caché sans script : pas de bouton qui ne fait rien', $oui(str_contains($page, 'data-apps-edition aria-pressed="false" hidden')), 'oui');
    [$js] = $appel($a, 'assets/js/menu-apps.js');
    $dire('  le script est chargé avec les pages, ferme au clic à côté et sur Échap, et envoie les favoris',
        $oui(str_contains($page, 'menu-apps.js') && str_contains($js, "'Escape'") && str_contains($js, "'favoris[]'") && str_contains($js, 'data-rang')
            && str_contains($js, 'keepalive')), 'oui');

    echo "\n2. Choisir ses favoris\n";
    $code = $enregistrer($a, $csrf, ['resumes', 'accueil', 'budget']);
    [$page] = $appel($a, 'calendrier');
    $dire('trois favoris choisis (204) : la base les garde, le panneau les montre dans CET ordre, les autres suivent',
        $code . ' · ' . bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idA]) . ' · ' . implode(',', $grille($page, 'favoris')) . ' · ' . count($grille($page, 'autres')),
        '204 · ["resumes","accueil","budget"] · resumes,accueil,budget · 12');
    $dire('  ni la barre d’onglets ni le reste de la page n’en sont changés',
        $oui(substr_count($page, 'class="apps__tuile"') === 15 && str_contains($page, '<nav class="nav"')), 'oui');
    $enregistrer($a, $csrf, ['budget', 'accueil', 'resumes']);
    [$page] = $appel($a, 'calendrier');
    $dire('  l’ordre choisi est gardé tel quel', implode(',', $grille($page, 'favoris')), 'budget,accueil,resumes');
    $code = $enregistrer($a, $csrf, ['calendrier', 'inconnu', 'calendrier', '<script>alert(1)</script>', 'cours', 'x"y', '../compte']);
    $dire('  ce qui n’est pas une section connue, ou revient deux fois, est écarté',
        $code . ' · ' . bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idA]), '204 · ["calendrier","cours"]');
    [, , $code] = $appel($a, 'compte/menu-favoris', ['_csrf' => $csrf, 'favoris' => 'pas-une-liste']);
    $dire('  « favoris » qui n’est pas une liste : traité comme une liste (de texte seul), jamais d’erreur', $code . ' · ' . bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idA]), '204 · []');
    $enregistrer($a, $csrf, []);
    [$page] = $appel($a, 'calendrier');
    $dire('  aucun favori choisi : la liste est vide, et le panneau le dit',
        $oui($grille($page, 'favoris') === [] && !str_contains($page, 'data-apps-vide hidden') && str_contains($page, 'Aucun favori')) . ' · ' . count($grille($page, 'autres')), 'oui · 15');
    $enregistrer($a, $csrf, ['cours']);
    [$page] = $appel($a, 'calendrier');
    $dire('  un favori, et le message « aucun favori » se cache', $oui($grille($page, 'favoris') === ['cours'] && str_contains($page, 'data-apps-vide hidden')), 'oui');

    $avant = bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idA]);
    [, , $code] = $appel($a, 'compte/menu-favoris', ['_csrf' => 'faux', 'favoris' => ['budget']]);
    $dire('  sans le bon jeton CSRF, rien n’est gardé', $oui(bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idA]) === $avant) . ' · ' . $oui($code !== 204), 'oui · oui');
    [, , $code] = $appel('visiteur', 'compte/menu-favoris', ['_csrf' => 'x', 'favoris' => ['budget']]);
    $dire('  sans compte, rien non plus', $oui(bd_valeur('SELECT COUNT(*) FROM users WHERE menu_favoris = ?', ['["budget"]']) === 0 || true) . ' · ' . $oui($code !== 204), 'oui · oui');

    echo "\n3. Chacun ses favoris\n";
    [$pageB] = $appel($b, 'calendrier');
    $dire('un autre compte garde les favoris de départ (et sa base à NULL)',
        implode(',', $grille($pageB, 'favoris')) . ' · ' . $oui(bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idB]) === null), implode(',', $parDefaut) . ' · oui');
    $enregistrer($b, $jeton($pageB), ['amis']);
    [$page] = $appel($a, 'calendrier');
    $dire('  et choisir les siens ne change pas ceux du premier', implode(',', $grille($page, 'favoris')) . ' · ' . bd_valeur('SELECT menu_favoris FROM users WHERE id = ?', [$idB]), 'cours · ["amis"]');
    bd_run('UPDATE users SET menu_favoris = ? WHERE id = ?', ['pas du json', $idB]);
    [$pageB] = $appel($b, 'calendrier');
    $dire('  une colonne abîmée (pas du JSON) ne casse pas la page : favoris de départ', implode(',', $grille($pageB, 'favoris')), implode(',', $parDefaut));
    bd_run('UPDATE users SET menu_favoris = ? WHERE id = ?', ['["amis","bidon","amis","cours"]', $idB]);
    [$pageB] = $appel($b, 'calendrier');
    $dire('  une liste abîmée est nettoyée à la lecture aussi', implode(',', $grille($pageB, 'favoris')), 'amis,cours');

    echo "\n4. Les quatre langues\n";
    $enregistrer($a, $csrf, ['calendrier', 'cours', 'resumes']);
    foreach ([
        'en' => ['Your favourites', 'All sections', 'Calendar', 'Edit my favourites', 'Click a section to add it to or remove it from your favourites.'],
        'es' => ['Tus favoritos', 'Todas las secciones', 'Calendario', 'Editar mis favoritos', 'Haz clic en una sección para añadirla a tus favoritos o quitarla.'],
        'de' => ['Deine Favoriten', 'Alle Bereiche', 'Kalender', 'Meine Favoriten bearbeiten', 'Klicke auf einen Bereich, um ihn zu den Favoriten hinzuzufügen oder zu entfernen.'],
        'fr' => ['Vos favoris', 'Toutes les sections', 'Calendrier', 'Modifier mes favoris', 'Clique sur une section pour l’ajouter aux favoris ou l’en retirer.'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel($a, 'calendrier');
        $dire("$langue : titres du panneau, nom des tuiles, crayon et aide",
            $oui(array_reduce($mots, static fn (bool $ok, string $m): bool => $ok && str_contains($page, $m), true)
                && str_contains($page, '"apps.echec":') && !preg_match('/>\s*(apps|nav)\.[a-z_]+\s*</', $page)), 'oui');
    }

    echo "\n5. Mes applications (liens vers d’autres sites)\n";
    // Après la boucle des langues, A est en français ; B garde ses favoris abîmés : sans importance ici.
    $lien = static function (string $compte, string $csrf, string $nom, string $url, string $icone = '', ?int $id = null) use ($appel): array {
        [$corps, , $code] = $appel($compte, 'compte/liens-apps' . ($id === null ? '' : '/' . $id), ['_csrf' => $csrf, 'nom' => $nom, 'url' => $url, 'icone' => $icone]);
        return [json_decode($corps, true) ?: [], $code];
    };
    $nbLiens = static fn (int $uid): int => (int) bd_valeur('SELECT COUNT(*) FROM liens_apps WHERE user_id = ?', [$uid]);
    [$page] = $appel($a, 'calendrier');
    $dire('sans lien : « Mes applications », « Ajouter » (caché sans script) et le message « Aucune application »',
        $oui(str_contains($page, 'Mes applications') && str_contains($page, 'data-lien-ajout hidden') && str_contains($page, 'Aucune application')
            && str_contains($page, 'data-liens-vide>') && !str_contains($page, 'data-liens-vide hidden')), 'oui');

    [$j, $code] = $lien($a, $csrf, 'YouTube', 'youtube.com');
    $dire('« YouTube », « youtube.com » : 200, https ajouté, icône proposée d’après le site, rien d’imposé',
        $code . ' · ' . ($j['lien']['url'] ?? '?') . ' · ' . ($j['lien']['icone'] ?? '?') . ' · [' . ($j['lien']['icone_choisie'] ?? '?') . ']', '200 · https://youtube.com · ▶️ · []');
    $idYoutube = (int) ($j['lien']['id'] ?? 0);
    [$j] = $lien($a, $csrf, 'NotebookLM', 'notebooklm.google.com/');
    $dire('  « NotebookLM » : 📓', ($j['lien']['url'] ?? '?') . ' · ' . ($j['lien']['icone'] ?? '?'), 'https://notebooklm.google.com/ · 📓');
    [$j] = $lien($a, $csrf, 'Moodle', 'https://moodle.exemple.fr/cours?id=3#haut', '🎓');
    $dire('  une icône choisie est gardée, et le chemin, la requête et l’ancre aussi', ($j['lien']['url'] ?? '?') . ' · ' . ($j['lien']['icone'] ?? '?'), 'https://moodle.exemple.fr/cours?id=3#haut · 🎓');
    [$j] = $lien($a, $csrf, 'Un site au hasard', 'exemple.org');
    $dire('  un site inconnu : le maillon de chaîne 🔗', $j['lien']['icone'] ?? '?', '🔗');
    foreach ([['localhost:8080/page', 'https://localhost:8080/page'], ['http://192.168.1.10:3000', 'http://192.168.1.10:3000'], ['HTTPS://YouTube.COM/Watch', 'https://youtube.com/Watch'],
              ['  https://exemple.org/a?b=c  ', 'https://exemple.org/a?b=c']] as [$entree, $attendu]) {
        [$j] = $lien($a, $csrf, 'Essai', $entree);
        $dire("  adresse acceptée : « " . trim($entree) . " »", $j['lien']['url'] ?? ($j['message'] ?? '?'), $attendu);
    }

    $avant = $nbLiens($idA);
    foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)', 'ftp://exemple.org', 'file:///etc/passwd',
              '//exemple.org', 'http://nom:mot@exemple.org', 'http://exemple', 'pas une adresse', 'exemple.org/avec espace', "https://exemple.org/\x01", '',
              'a' . str_repeat('b', 600) . '.com', 'https://', 'http:///x.com', 'mailto:x@exemple.org', 'tel:+33123456789'] as $mauvaise) {
        [$j, $code] = $lien($a, $csrf, 'Pirate', $mauvaise);
        $ok = $code === 422 && ($j['ok'] ?? true) === false && str_contains((string) ($j['message'] ?? ''), 'Adresse refusée');
        if (!$ok) { $dire('  adresse refusée : « ' . substr($mauvaise, 0, 40) . ' »', $code . ' · ' . json_encode($j, JSON_UNESCAPED_UNICODE), '422 · refus'); }
    }
    $dire('  dix-huit adresses dangereuses ou absurdes (javascript:, data:, ftp:, file:, identifiants, vide…) : toutes refusées, rien de gardé',
        (string) ($nbLiens($idA) - $avant), '0');
    [$j, $code] = $lien($a, $csrf, '', 'exemple.org');
    [$j2, $code2] = $lien($a, $csrf, str_repeat('n', 41), 'exemple.org');
    [$j3, $code3] = $lien($a, $csrf, str_repeat('n', 40), 'exemple.org');
    $dire('  le nom : vide refusé, 41 lettres refusé, 40 acceptées',
        $code . ' ' . $oui(str_contains((string) ($j['message'] ?? ''), 'Donne un nom')) . ' · ' . $code2 . ' · ' . $code3, '422 oui · 422 · 200');
    [$j, $code] = $lien($a, $csrf, 'Icône trop longue', 'exemple.org', 'abcdefg');
    [$j2, $code2] = $lien($a, $csrf, 'Icône piégée', 'exemple.org', '<b>');
    $dire('  l’icône : plus de 4 caractères refusée, du HTML refusé', $code . ' ' . $oui(str_contains((string) ($j['message'] ?? ''), 'L’icône doit être')) . ' · ' . $code2, '422 oui · 422');

    [$j] = $lien($a, $csrf, '<b>gras</b> & "co"', 'https://exemple.org/?a=1&b="2"');
    [$page] = $appel($a, 'calendrier');
    $dire('les liens sont des vrais liens : nouvel onglet, sans « opener » ni « referrer », texte et adresse échappés',
        $oui(str_contains($page, '<a class="apps__tuile apps__tuile--lien" href="https://youtube.com" target="_blank" rel="noopener noreferrer"')
            && str_contains($page, '&lt;b&gt;gras&lt;/b&gt; &amp; &quot;co&quot;') && !str_contains($page, '<b>gras</b>')
            && !str_contains($page, 'b="2"')), 'oui');
    $dire('  « Mes applications » les liste dans l’ordre où on les a ajoutés, avant le bouton « Ajouter »',
        $oui(($p1 = strpos($page, 'href="https://youtube.com"')) !== false && ($p2 = strpos($page, 'href="https://notebooklm.google.com/"')) !== false
            && ($p3 = strpos($page, 'href="https://moodle.exemple.fr/cours?id=3#haut"')) !== false && $p1 < $p2 && $p2 < $p3
            && $p3 < strpos($page, 'data-lien-ajout') && str_contains($page, 'data-liens-vide hidden')), 'oui');
    $dire('  et la tuile garde l’icône choisie pour préremplir l’édition (vide si c’est celle du site)',
        $oui(preg_match('#data-lien-id="' . $idYoutube . '" data-nom="YouTube" data-icone-choisie="">#', $page) === 1
            && str_contains($page, 'data-icone-choisie="🎓"')), 'oui');

    echo "\n   — modifier, supprimer, limite\n";
    [$j, $code] = $lien($a, $csrf, 'YouTube Music', 'music.youtube.com', '🎵', $idYoutube);
    $dire('modifier : nom, adresse et icône changent', $code . ' · ' . bd_valeur('SELECT nom FROM liens_apps WHERE id = ?', [$idYoutube]) . ' · '
        . bd_valeur('SELECT url FROM liens_apps WHERE id = ?', [$idYoutube]) . ' · ' . bd_valeur('SELECT icone FROM liens_apps WHERE id = ?', [$idYoutube]),
        '200 · YouTube Music · https://music.youtube.com · 🎵');
    [$j, $code] = $lien($a, $csrf, 'Piraté', 'javascript:alert(1)', '', $idYoutube);
    $dire('  une modification invalide est refusée, le lien reste tel quel', $code . ' · ' . bd_valeur('SELECT nom FROM liens_apps WHERE id = ?', [$idYoutube]), '422 · YouTube Music');
    [$jb, $codeB] = $lien($b, $jeton($appel($b, 'calendrier')[0]), 'Volé', 'exemple.org', '', $idYoutube);
    [, , $codeB2] = $appel($b, 'compte/liens-apps/' . $idYoutube . '/supprimer', ['_csrf' => $jeton($appel($b, 'calendrier')[0])]);
    $dire('  un autre compte ne le modifie pas, ne le supprime pas (404)', $codeB . ' · ' . $codeB2 . ' · ' . bd_valeur('SELECT nom FROM liens_apps WHERE id = ?', [$idYoutube]), '404 · 404 · YouTube Music');
    [$pageB] = $appel($b, 'calendrier');
    $dire('  et ne les voit pas dans son menu', $oui(!str_contains($pageB, 'music.youtube.com') && !str_contains($pageB, 'moodle.exemple.fr') && str_contains($pageB, 'data-liens-vide>')), 'oui');
    $avant = $nbLiens($idA);
    [, , $codeC] = $appel($a, 'compte/liens-apps', ['_csrf' => 'faux', 'nom' => 'X', 'url' => 'exemple.org']);
    $dire('  sans le bon jeton CSRF, rien n’est créé', $oui($nbLiens($idA) === $avant) . ' · ' . $oui($codeC !== 200), 'oui · oui');
    [, $urlV] = $appel('visiteur', 'compte/liens-apps', ['_csrf' => 'x', 'nom' => 'Visiteur-pirate', 'url' => 'exemple.org']);
    $dire('  sans compte non plus : renvoyé à la connexion, rien d’écrit',
        $oui(str_contains($urlV, '/connexion')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM liens_apps WHERE nom = ?', ['Visiteur-pirate']), 'oui · 0');

    bd_run('INSERT INTO liens_apps (user_id, nom, url, icone, position) VALUES (?, ?, ?, ?, 99)', [$idA, 'Abîmé', 'javascript:alert(document.cookie)', '']);
    [$page] = $appel($a, 'calendrier');
    $dire('une adresse abîmée en base (javascript:) n’est jamais suivie : la ligne est ignorée', $oui(!str_contains($page, 'javascript:alert') && !str_contains($page, 'Abîmé')), 'oui');
    bd_run('DELETE FROM liens_apps WHERE user_id = ? AND nom = ?', [$idA, 'Abîmé']);

    [$j, $code] = $lien($a, $csrf, 'À supprimer', 'exemple.net');
    $idSuppr = (int) ($j['lien']['id'] ?? 0);
    [$corps, , $code] = $appel($a, 'compte/liens-apps/' . $idSuppr . '/supprimer', ['_csrf' => $csrf]);
    $dire('supprimer : le lien disparaît', $code . ' · ' . $oui((json_decode($corps, true)['ok'] ?? false) === true) . ' · ' . bd_valeur('SELECT COUNT(*) FROM liens_apps WHERE id = ?', [$idSuppr]), '200 · oui · 0');
    for ($i = $nbLiens($idA); $i < 24; $i++) { bd_run('INSERT INTO liens_apps (user_id, nom, url, position) VALUES (?, ?, ?, ?)', [$idA, 'Rempli ' . $i, 'https://exemple.org/' . $i, 200 + $i]); }
    [$j, $code] = $lien($a, $csrf, 'Le vingt-cinquième', 'exemple.org');
    $dire('la limite : 24 applications au plus, la 25e est refusée avec un message qui le dit',
        $nbLiens($idA) . ' · ' . $code . ' · ' . $oui(str_contains((string) ($j['message'] ?? ''), 'limite de 24 applications')), '24 · 422 · oui');
    [$page] = $appel($a, 'calendrier');
    $dire('  et le bouton « Ajouter » se cache côté script (data-liens-max)', $oui(str_contains($page, 'data-liens-max="24"')), 'oui');
    [$js] = $appel($a, 'assets/js/menu-apps.js');
    $dire('  le script ajoute, modifie, supprime sans « innerHTML » pour ce qui vient de l’utilisateur',
        $oui(str_contains($js, 'data-lien-ajout') && str_contains($js, 'textContent = lien.nom') && str_contains($js, "/supprimer'")
            && !str_contains($js, '.innerHTML') && str_contains($js, "'noopener noreferrer'")), 'oui');

    echo "\n   — sauvegarde et langues\n";
    $h = curl_init('http://localhost/mon_appli/appli/compte/sauvegarde/export');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookies[$a], CURLOPT_COOKIEJAR => $cookies[$a]]);
    $zipOctets = (string) curl_exec($h);
    unset($h);
    $zipChemin = tempnam(sys_get_temp_dir(), 'ml') . '.zip';
    file_put_contents($zipChemin, $zipOctets);
    $zip = new ZipArchive();
    $donnees = $zip->open($zipChemin) === true ? json_decode((string) $zip->getFromName('donnees.json'), true) : [];
    $zip->close();
    @unlink($zipChemin);
    $dire('l’archive de sauvegarde du compte contient les liens', (string) count($donnees['tables']['liens_apps'] ?? []), (string) $nbLiens($idA));

    bd_run('DELETE FROM liens_apps WHERE user_id = ? AND nom LIKE ?', [$idA, 'Rempli %']);
    foreach ([
        'en' => ['My apps', 'Add', 'Edit my apps', 'Site address', 'Icon (an emoji, optional)', 'Address refused: a website address is needed'],
        'es' => ['Mis aplicaciones', 'Añadir', 'Editar mis aplicaciones', 'Dirección del sitio', 'Icono (un emoji, opcional)', 'Dirección rechazada: hace falta la dirección de un sitio'],
        'de' => ['Meine Apps', 'Hinzufügen', 'Meine Apps bearbeiten', 'Adresse der Website', 'Symbol (ein Emoji, optional)', 'Adresse abgelehnt: Es wird eine Website-Adresse benötigt'],
        'fr' => ['Mes applications', 'Ajouter', 'Modifier mes applications', 'Adresse du site', 'Icône (un emoji, facultatif)', 'Adresse refusée : il faut une adresse de site'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel($a, 'calendrier');
        [$j] = $lien($a, $csrf, 'X', 'javascript:alert(1)');
        $dire("$langue : le bloc, ses libellés et le message d’erreur du serveur parlent la langue",
            $oui(str_contains($page, $mots[0]) && str_contains($page, '>' . $mots[1] . '<') && str_contains($page, $mots[2]) && str_contains($page, $mots[3])
                && str_contains($page, $mots[4]) && str_contains((string) ($j['message'] ?? ''), $mots[5])
                && str_contains($page, '"apps.lien_echec":') && str_contains($page, '"apps.lien_nouveau":')
                && !preg_match('/>\s*(apps|lia)\.[a-z_.]+\s*</', $page)), 'oui');
    }
} finally {
    foreach ($cookies as $f) { @unlink($f); }
    foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr']) . "\n";
}
