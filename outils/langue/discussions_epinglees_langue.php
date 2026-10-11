<?php
/**
 * Épingler une discussion en haut de la liste des amis (HTTP + base) : le bouton de chaque ligne, la section « Épinglées » (en tête, avant
 * « Messages » et « Groupes », de la plus récente à la plus ancienne), désépingler, ce qui est refusé (un non-ami, un groupe dont on n'est pas
 * membre, un genre inconnu, le jeton CSRF, la limite), le retour d'où l'on vient (jamais une adresse extérieure), un épinglage qui reste à soi,
 * et les quatre langues.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'epi-a@exemple-test.fr', 'pseudo' => 'Epi_un'],      // celui qui épingle
    'b' => ['email' => 'epi-b@exemple-test.fr', 'pseudo' => 'Epi_deux'],    // ami, message ancien
    'c' => ['email' => 'epi-c@exemple-test.fr', 'pseudo' => 'Epi_trois'],   // ami, message récent
    'd' => ['email' => 'epi-d@exemple-test.fr', 'pseudo' => 'Epi_quatre'],  // pas ami
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$ck = static fn (string $c): string => __DIR__ . '/ck_epi_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

/** @return array{0: int, 1: string, 2: string} code, corps, adresse finale */
$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_HTTP_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$groupe = null;
$groupeEtranger = null;
try {
    $csrf = [];
    foreach (['a', 'b'] as $c) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p[1] ?? $p), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    foreach (['a', 'b'] as $c) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
    }
    $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => 'en']);

    foreach (['b', 'c'] as $ami) {
        bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$id('a'), $id($ami), min($id('a'), $id($ami)), max($id('a'), $id($ami))]);
    }
    // Un message ancien avec B, un récent avec C : sans épinglage, C est devant B.
    bd_run('INSERT INTO messages (expediteur_id, destinataire_id, texte, created_at) VALUES (?, ?, ?, ?), (?, ?, ?, ?)',
        [$id('b'), $id('a'), 'Message ancien de B', date('Y-m-d H:i:s', strtotime('-3 days')), $id('c'), $id('a'), 'Message récent de C', date('Y-m-d H:i:s', strtotime('-1 hour'))]);
    // Un groupe dont A est membre, un autre qui ne le concerne pas.
    foreach ([['Groupe épinglable', 'a', $id('a')], ['Groupe étranger', 'd', $id('d')]] as [$nom, , $createur]) {
        bd_run('INSERT INTO conversations (nom, cree_par, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$nom, $createur]);
    }
    $groupe = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? AND nom = ?', [$id('a'), 'Groupe épinglable']);
    $groupeEtranger = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? AND nom = ?', [$id('d'), 'Groupe étranger']);
    bd_run('INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, ?, UTC_TIMESTAMP()), (?, ?, ?, UTC_TIMESTAMP())',
        [$groupe, $id('a'), 'admin', $groupeEtranger, $id('d'), 'admin']);

    $epingle = static fn (string $qui, string $genre, int $cible): bool => bd_valeur(
        'SELECT COUNT(*) FROM discussions_epinglees WHERE user_id = ? AND genre = ? AND cible_id = ?', [$id($qui), $genre, $cible]) > 0;
    $envoyer = static fn (string $qui, array $plus) => $GLOBALS['appel']($qui, 'discussions/epingler', ['_csrf' => $GLOBALS['csrf'][$qui]] + $plus);
    $GLOBALS['appel'] = $appel; $GLOBALS['csrf'] = $csrf;
    $position = static fn (string $page, string $quoi): int|false => strpos($page, $quoi);

    echo "\n1. La liste, avant d'épingler\n";
    [$cod, $liste] = $appel('a', 'amis');
    $dire('chaque discussion a son menu « ⋮ » (trois points) avec « Pin to the top of the list », et pas de section « Pinned »',
        $cod . ' · ' . $oui(substr_count($liste, '<details class="ami-menu">') === 3 && substr_count($liste, 'name="epingle" value="1"') === 3
            && substr_count($liste, 'Pin to the top of the list') === 3 && !str_contains($liste, 'data-memoire="amis-epinglees"')
            && !str_contains($liste, 'name="epingle" value="0"') && str_contains($liste, 'aria-label="Options for Epi_deux"')), '200 · oui');
    $dire('le menu offre aussi « View the profile » (un ami) ou « Group settings » (un groupe), qui s\'ouvrent en fenêtre',
        $oui(preg_match('#<a class="ami-menu__item" role="menuitem" data-fenetre\s+href="[^"]*/amis/' . $id('b') . '/profil">\s*<span aria-hidden="true">👤</span>\s*View the profile#', $liste) === 1
            && preg_match('#href="[^"]*/groupes/' . $groupe . '/reglages">\s*<span aria-hidden="true">⚙️</span>\s*Group settings#', $liste) === 1), 'oui');
    $dire('sans épinglage : la plus récente (C) devant B',
        $oui($position($liste, 'Epi_trois') !== false && $position($liste, 'Epi_deux') !== false && $position($liste, 'Epi_trois') < $position($liste, 'Epi_deux')), 'oui');

    echo "\n2. Épingler un ami\n";
    [, $apres, $adresse] = $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '1', 'retour' => 'amis']);
    $dire('B est épinglé en base', $oui($epingle('a', 'ami', $id('b'))), 'oui');
    $dire('la section « Pinned » est en tête, avant « Messages », et B y est (pas de doublon plus bas)',
        $oui(($ps = $position($apres, 'data-memoire="amis-epinglees"')) !== false && ($ms = $position($apres, 'data-memoire="amis-messages"')) !== false && $ps < $ms
            && substr_count($apres, '<span class="ami__pseudo">Epi_deux<') === 1 && preg_match('#amis-epinglees.*?ami__pseudo">Epi_deux.*?amis-messages#s', $apres) === 1), 'oui');
    $dire('et le menu de B propose maintenant « Unpin » (et plus « Pin to the top »)',
        $oui(preg_match('#ami__pseudo">Epi_deux<.*?name="epingle" value="0".*?Unpin#s', $apres) === 1
            && !preg_match('#ami__pseudo">Epi_deux<(?:(?!</li>).)*Pin to the top of the list#s', $apres)), 'oui');
    $dire('B passe devant C, bien que son message soit plus ancien', $oui($position($apres, 'Epi_deux') < $position($apres, 'Epi_trois')), 'oui');

    echo "\n3. Épingler un groupe\n";
    $envoyer('a', ['genre' => 'groupe', 'cible' => (string) $groupe, 'epingle' => '1', 'retour' => 'amis']);
    [, $deux] = $appel('a', 'amis');
    $dire('le groupe est épinglé aussi, dans la même section (amis et groupes mêlés)',
        $oui($epingle('a', 'groupe', $groupe) && preg_match('#amis-epinglees(.*?)amis-messages#s', $deux, $sp) === 1
            && str_contains($sp[1], 'ami__pseudo">Epi_deux<') && str_contains($sp[1], 'ami__pseudo">Groupe épinglable<')
            && substr_count($deux, '<span class="ami__pseudo">Groupe épinglable<') === 1), 'oui');
    $dire('la section « Groupes » dit simplement qu\'elle est vide, sans « aucun groupe »', $oui(preg_match('#amis-groupes.*?</details>#s', $deux, $sg) === 1 && !str_contains($sg[0], 'amis-liste')), 'oui');

    echo "\n4. Désépingler\n";
    $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '0', 'retour' => 'amis']);
    $envoyer('a', ['genre' => 'groupe', 'cible' => (string) $groupe, 'epingle' => '0', 'retour' => 'amis']);
    [, $trois] = $appel('a', 'amis');
    $dire('plus rien d\'épinglé : base vide, plus de section, l\'ordre par date revient',
        $oui(!$epingle('a', 'ami', $id('b')) && !$epingle('a', 'groupe', $groupe) && !str_contains($trois, 'data-memoire="amis-epinglees"')
            && $position($trois, 'Epi_trois') < $position($trois, 'Epi_deux')), 'oui');

    echo "\n5. Ce qui est refusé\n";
    [, $refus] = $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('d'), 'epingle' => '1', 'retour' => 'amis']);
    $dire('un compte qui n\'est pas un ami : refusé, avec son message', $oui(!$epingle('a', 'ami', $id('d')) && str_contains($refus, 'does not exist, or it is not one of yours')), 'oui');
    $envoyer('a', ['genre' => 'groupe', 'cible' => (string) $groupeEtranger, 'epingle' => '1', 'retour' => 'amis']);
    $dire('un groupe dont on n\'est pas membre : refusé', $oui(!$epingle('a', 'groupe', $groupeEtranger)), 'oui');
    $envoyer('a', ['genre' => 'serveur', 'cible' => (string) $id('b'), 'epingle' => '1', 'retour' => 'amis']);
    $dire('un genre inconnu : refusé', (string) bd_valeur('SELECT COUNT(*) FROM discussions_epinglees WHERE user_id = ?', [$id('a')]), '0');
    $GLOBALS['appel']('a', 'discussions/epingler', ['_csrf' => 'faux', 'genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '1']);
    $dire('sans le bon jeton CSRF : rien', (string) bd_valeur('SELECT COUNT(*) FROM discussions_epinglees WHERE user_id = ?', [$id('a')]), '0');
    for ($i = 0; $i < 10; $i++) { bd_run("INSERT INTO discussions_epinglees (user_id, genre, cible_id) VALUES (?, 'ami', ?)", [$id('a'), 900000 + $i]); }
    [, $limite] = $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '1', 'retour' => 'amis']);
    $dire('au-delà de dix : refusé, avec la limite dite', $oui(!$epingle('a', 'ami', $id('b')) && str_contains($limite, 'at most 10 conversations')), 'oui');
    bd_run("DELETE FROM discussions_epinglees WHERE user_id = ? AND cible_id >= 900000", [$id('a')]);

    echo "\n6. Un épinglage reste à soi, et on revient d'où l'on vient\n";
    $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '1', 'retour' => 'amis']);
    [, $chezB] = $appel('b', 'amis');
    $dire('B ne voit rien d\'épinglé de ce que A a épinglé', $oui(!str_contains($chezB, 'data-memoire="amis-epinglees"')), 'oui');
    [, $chezC] = $appel('a', 'amis/' . $id('c'));
    $dire('la conversation ouverte garde sa liste, avec la section « Pinned » (et B dedans)', $oui(str_contains($chezC, 'data-memoire="amis-epinglees"') && str_contains($chezC, 'data-chat')), 'oui');
    [, , $fin] = $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('c'), 'epingle' => '1', 'retour' => 'amis/' . $id('c')]);
    $dire('« retour » : on revient dans la conversation d\'où l\'on épingle', $oui(str_ends_with($fin, '/amis/' . $id('c'))), 'oui');
    [, , $finExterne] = $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('c'), 'epingle' => '0', 'retour' => 'http://exemple.invalide/pirate']);
    $dire('une adresse extérieure n\'est jamais suivie : retour à la liste', $oui(str_contains($finExterne, 'localhost') && str_ends_with($finExterne, '/amis')), 'oui');
    $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '0', 'retour' => 'amis']);

    echo "\n7. Les quatre langues\n";
    $envoyer('a', ['genre' => 'ami', 'cible' => (string) $id('b'), 'epingle' => '1', 'retour' => 'amis']);
    foreach (['en' => ['Pinned', 'Pin to the top of the list'], 'es' => ['Fijadas', 'Fijar arriba en la lista'], 'de' => ['Angeheftet', 'Oben in der Liste anheften'],
        'fr' => ['Épinglées', 'Épingler en haut de la liste']] as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => $langue]);
        [, $page] = $appel('a', 'amis');
        $dire("$langue : la section et le bouton sont traduits, sans clé brute",
            $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[1]) && !preg_match('/\bami\.(epingl|desepingl)[a-z_]*/', $page)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : les groupes d'essai, puis les comptes (leurs amitiés, messages et épinglages partent avec eux).
    foreach ([$groupe, $groupeEtranger] as $g) {
        if ($g !== null && $g > 0) { bd_run('DELETE FROM conversations WHERE id = ?', [$g]); }
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'epi-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
