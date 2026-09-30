<?php
/** Les amis, les groupes et leurs notes, dans une autre langue. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-56s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'amis-a@exemple-test.fr', 'pseudo' => 'Amis_un'],
    'b' => ['email' => 'amis-b@exemple-test.fr', 'pseudo' => 'Amis_deux'],
    'c' => ['email' => 'amis-c@exemple-test.fr', 'pseudo' => 'Amis_trois'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ?', [$d['email']]);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}

$ck = static fn (string $c): string => __DIR__ . '/ck_amis_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): array {
    usleep(350000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    return [(int) curl_getinfo($h, CURLINFO_HTTP_CODE), $corps];
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$groupe = null;
try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
        $appel($c, 'compte/langue', ['_csrf' => $csrf[$c], 'langue' => 'en']);
    }

    echo "\n1. La page « Amis » en anglais\n";
    [, $page] = $appel('a', 'amis');
    $dire('le titre, l’aide et l’état vide',
        $oui(str_contains($page, 'Friends') && !str_contains($page, 'Mes discussions')
            && !str_contains($page, 'Ajouter quelqu’un')), 'oui');

    echo "\n2. Devenir amis\n";
    $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $comptes['b']['id']]);
    [, $chezB] = $appel('b', 'amis');
    $dire('la demande arrive, en anglais', $oui(!str_contains($chezB, 'Accepter') && str_contains($chezB, 'Accept')), 'oui');
    $appel('b', 'amis/' . $comptes['a']['id'] . '/accepter', ['_csrf' => $csrf['b']]);
    $dire('les deux sont amis', bd_valeur('SELECT statut FROM amities WHERE (petit_id = ? AND grand_id = ?) OR (petit_id = ? AND grand_id = ?)',
        [min($comptes['a']['id'], $comptes['b']['id']), max($comptes['a']['id'], $comptes['b']['id']),
         max($comptes['a']['id'], $comptes['b']['id']), min($comptes['a']['id'], $comptes['b']['id'])]) ?? 'aucune', 'acceptee');

    echo "\n3. La conversation à deux\n";
    [, $fil] = $appel('a', 'amis/' . $comptes['b']['id']);
    $dire('le fil : l’état vide, la saisie et le bouton',
        $oui(str_contains($fil, 'No message yet. Write the first one!')
            && str_contains($fil, 'placeholder="Write to ' . $comptes['b']['pseudo'] . '…"')
            && str_contains($fil, '>Send</button>')), 'oui');
    $dire('  et plus un mot de français',
        $oui(!str_contains($fil, 'Aucun message pour l’instant') && !str_contains($fil, 'Écrire ')
            && !str_contains($fil, 'Profil, photos et fichiers') && !str_contains($fil, 'Messages épinglés')), 'oui');
    [, $profil] = $appel('a', 'amis/' . $comptes['b']['id'] . '/profil');
    $dire('le profil : les sections et les boutons',
        $oui(str_contains($profil, 'Photos') && str_contains($profil, 'Files')
            && str_contains($profil, 'Remove ' . $comptes['b']['pseudo'] . ' from my friends')
            && str_contains($profil, 'Block ' . $comptes['b']['pseudo'])), 'oui');
    $dire('  sans « Retirer » ni « Bloquer » en français',
        $oui(!str_contains($profil, 'de mes amis') && !str_contains($profil, 'Aucune photo échangée')), 'oui');

    echo "\n4. Un groupe, et ses notes\n";
    $appel('a', 'groupes', ['_csrf' => $csrf['a'], 'nom' => 'Essai langue', 'membres' => [(string) $comptes['b']['id']]]);
    $groupe = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$comptes['a']['id']]);
    $dire('le groupe est créé', $groupe > 0 ? 'oui' : 'non', 'oui');
    [, $filGroupe] = $appel('a', 'groupes/' . $groupe);
    $dire('« You created the group » chez celui qui l’a fait',
        $oui(str_contains($filGroupe, 'You created the group “Essai langue”')), 'oui');
    // Amis_trois devient ami d'Amis_un, puis entre dans le groupe : là, une note d'ajout est écrite.
    $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $comptes['c']['id']]);
    $appel('c', 'amis/' . $comptes['a']['id'] . '/accepter', ['_csrf' => $csrf['c']]);
    $appel('a', 'groupes/' . $groupe . '/membres', ['_csrf' => $csrf['a'], 'membres' => [(string) $comptes['c']['id']]]);
    [, $filGroupe] = $appel('a', 'groupes/' . $groupe);
    $dire('  et « You added » pour le membre ajouté',
        $oui(str_contains($filGroupe, 'You added ' . $comptes['c']['pseudo'])), 'oui');
    [, $chezC] = $appel('c', 'groupes/' . $groupe);
    $dire('« Amis_un added you » de l’autre côté',
        $oui(str_contains($chezC, $comptes['a']['pseudo'] . ' added you')), 'oui');
    $dire('  la saisie s’adresse « to the group »',
        $oui(str_contains($chezC, 'placeholder="Write to the group…"')), 'oui');

    echo "\n5. Les réglages du groupe\n";
    $appel('a', 'groupes/' . $groupe . '/nom', ['_csrf' => $csrf['a'], 'nom' => 'Essai renommé']);
    $appel('a', 'groupes/' . $groupe . '/membres/' . $comptes['b']['id'] . '/admin', ['_csrf' => $csrf['a']]);
    [, $reglages] = $appel('a', 'groupes/' . $groupe . '/reglages');
    $dire('la page : les cartes et les actions',
        $oui(str_contains($reglages, 'Group picture') && str_contains($reglages, 'Group name')
            && str_contains($reglages, 'Members') && str_contains($reglages, 'Leave the group')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($reglages, 'Photo du groupe') && !str_contains($reglages, 'Quitter le groupe')
            && !str_contains($reglages, 'Administrateur') && !str_contains($reglages, 'Ajouter des amis')), 'oui');
    [, $filApres] = $appel('b', 'groupes/' . $groupe);
    $dire('« renamed the group » et « made you an administrator »',
        $oui(str_contains($filApres, 'renamed the group “Essai renommé”')
            && str_contains($filApres, 'made you an administrator')), 'oui');

    echo "\n6. Un refus, en anglais\n";
    [, $refus] = $appel('a', 'groupes/' . $groupe . '/nom', ['_csrf' => $csrf['a'], 'nom' => '']);
    $dire('un nom vide : « Give the group a name. »', $oui(str_contains($refus, 'Give the group a name.')), 'oui');

    echo "\n7. Le français revient\n";
    $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => 'fr']);
    [, $fr] = $appel('a', 'groupes/' . $groupe);
    $dire('les mêmes notes, en français',
        $oui(str_contains($fr, 'Vous avez créé le groupe « Essai langue »')
            && str_contains($fr, 'Vous avez renommé le groupe « Essai renommé »')
            && str_contains($fr, 'Vous avez nommé ' . $comptes['b']['pseudo'] . ' administrateur')), 'oui');
} finally {
    // Ménage : seulement les deux comptes d'essai, et ce qui en dépend.
    if ($groupe !== null && $groupe > 0) {
        bd_run('DELETE FROM conversation_messages WHERE conversation_id = ?', [$groupe]);
        bd_run('DELETE FROM conversation_membres WHERE conversation_id = ?', [$groupe]);
        bd_run('DELETE FROM conversation_invitations WHERE conversation_id = ?', [$groupe]);
        bd_run('DELETE FROM conversations WHERE id = ?', [$groupe]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$d['id'], $d['email']]); }
        @unlink($ck($c));
    }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'amis-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
