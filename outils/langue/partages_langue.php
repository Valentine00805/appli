<?php
/** Les partages, dans une autre langue : partager, lire, l'onglet, les commentaires. */
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
    'a' => ['email' => 'partage-a@exemple-test.fr', 'pseudo' => 'Partage_un'],
    'b' => ['email' => 'partage-b@exemple-test.fr', 'pseudo' => 'Partage_deux'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ?', [$d['email']]);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}

$ck = static fn (string $c): string => __DIR__ . '/ck_partage_' . $c . '.txt';
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

$cours = null;
try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
        $appel($c, 'compte/langue', ['_csrf' => $csrf[$c], 'langue' => 'en']);
    }
    // Amis, pour pouvoir se partager quelque chose.
    $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $comptes['b']['id']]);
    $appel('b', 'amis/' . $comptes['a']['id'] . '/accepter', ['_csrf' => $csrf['b']]);

    echo "\n1. L’onglet « Partagés », vide\n";
    [, $recus] = $appel('b', 'partages');
    $dire('le titre, les onglets et l’état vide',
        $oui(str_contains($recus, 'Shared with me') && str_contains($recus, 'What I share')
            && str_contains($recus, 'Nobody has shared a document with you yet.')), 'oui');
    $dire('  et pas un mot de français',
        $oui(!str_contains($recus, 'Partagés avec moi') && !str_contains($recus, 'Ce que je partage')
            && !str_contains($recus, 'Personne ne vous a encore partagé')), 'oui');
    [, $envoyes] = $appel('a', 'partages/envoyes');
    $dire('l’autre volet : « You are not sharing anything yet. »',
        $oui(str_contains($envoyes, 'You are not sharing anything yet.')
            && str_contains($envoyes, 'Share several')), 'oui');

    echo "\n2. Un cours, puis sa fenêtre « Partager »\n";
    $appel('a', 'cours/nouveau', ['_csrf' => $csrf['a'], 'titre' => 'Cours d’essai langue', 'contenu' => 'Du texte.']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$comptes['a']['id']]);
    $dire('le cours est créé', $cours > 0 ? 'oui' : 'non', 'oui');
    [, $fenetre] = $appel('a', 'partager/cours/' . $cours);
    $dire('la fenêtre : titres, droits et aide',
        $oui(str_contains($fenetre, 'With my friends') && str_contains($fenetre, 'With a link')
            && str_contains($fenetre, 'What they will be able to do')
            && str_contains($fenetre, 'Message (optional)')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($fenetre, 'Avec mes amis') && !str_contains($fenetre, 'Avec un lien')
            && !str_contains($fenetre, 'Ce qu’ils pourront faire')), 'oui');

    echo "\n3. Partager, et le message qui suit\n";
    [, $apres] = $appel('a', 'partager/cours/' . $cours . '/amis',
        ['_csrf' => $csrf['a'], 'amis' => [(string) $comptes['b']['id']], 'droit' => 'commentaire', 'texte' => 'Regarde.']);
    $dire('« Shared with 1 person »', $oui(str_contains($apres, 'Shared with 1 person')), 'oui');

    echo "\n4. Lire le partage, de l’autre côté\n";
    [, $liste] = $appel('b', 'partages');
    $dire('la ligne : « Shared course », « New »',
        $oui(str_contains($liste, 'Shared course') && str_contains($liste, '>New<')
            && str_contains($liste, '1 document to read, always up to date')), 'oui');
    [, $lecture] = $appel('b', 'partages/cours/' . $cours);
    $dire('la page : « Shared by », les fichiers, les commentaires',
        $oui(str_contains($lecture, 'Shared by') && str_contains($lecture, 'Attached files')
            && str_contains($lecture, 'Comments') && str_contains($lecture, 'Copy into my courses')), 'oui');
    $dire('  et plus rien en français',
        $oui(!str_contains($lecture, 'Partagé par') && !str_contains($lecture, 'Fichiers joints')
            && !str_contains($lecture, 'Aucun commentaire') && !str_contains($lecture, 'Copier dans mes cours')), 'oui');

    echo "\n5. Commenter, en anglais\n";
    [, $commente] = $appel('b', 'partages/cours/' . $cours . '/commentaires',
        ['_csrf' => $jeton($lecture), 'texte' => 'Nice one.']);
    $dire('« Comment added. »', $oui(str_contains($commente, 'Comment added.')), 'oui');
    [, $fil] = $appel('b', 'partages/cours/' . $cours . '/commentaires');
    $dire('le fil : « Reply », « Like », « Comment »',
        $oui(str_contains($fil, '>Reply</summary>') && str_contains($fil, 'Like')
            && str_contains($fil, '>Comment</button>')), 'oui');

    echo "\n6. Un lien public, et un refus\n";
    [, $lien] = $appel('a', 'partager/cours/' . $cours . '/lien', ['_csrf' => $csrf['a']]);
    $dire('« Link created »', $oui(str_contains($lien, 'Link created:')), 'oui');
    [, $refus] = $appel('a', 'partager/plusieurs/amis', ['_csrf' => $csrf['a'], 'cours' => [(string) $cours]]);
    $dire('sans destinataire : « Choose at least one friend or group. »',
        $oui(str_contains($refus, 'Choose at least one friend or group.')), 'oui');

    echo "\n7. Le français revient\n";
    $appel('b', 'compte/langue', ['_csrf' => $csrf['b'], 'langue' => 'fr']);
    [, $fr] = $appel('b', 'partages/cours/' . $cours);
    $dire('les mêmes pages, en français',
        $oui(str_contains($fr, 'Partagé par') && str_contains($fr, 'Fichiers joints')
            && str_contains($fr, 'Copier dans mes cours')), 'oui');
} finally {
    // Ménage : le cours d'essai, ses partages, puis les deux comptes.
    if ($cours !== null && $cours > 0) {
        bd_run('DELETE FROM commentaires_partage WHERE cible_type = ? AND cible_id = ?', ['cours', $cours]);
        bd_run('DELETE FROM partages_amis WHERE cible_type = ? AND cible_id = ?', ['cours', $cours]);
        bd_run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$cours, $comptes['a']['id']]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$d['id'], $d['email']]); }
        @unlink($ck($c));
    }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'partage-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
