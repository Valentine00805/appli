<?php
/**
 * Les listes à points à cocher d'un travail de groupe (HTTP + base) : l'onglet « Listes » (créer, renommer, supprimer une liste ; ajouter, cocher,
 * décocher, retirer un point ; qui a coché), les points d'une tâche (dans sa fenêtre, sur sa carte du tableau, avec l'avancement « 2/5 »), les
 * limites, ceux qui n'ont pas la main (pas membre du projet), ce que montre le lien public (en lecture seule), un site dont la migration n'est pas
 * passée (ni onglet ni erreur), et les quatre langues.
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');

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
    'a' => ['email' => 'tlis-a@exemple-test.fr', 'pseudo' => 'Tlis_un'],
    'b' => ['email' => 'tlis-b@exemple-test.fr', 'pseudo' => 'Tlis_deux'],
    'c' => ['email' => 'tlis-c@exemple-test.fr', 'pseudo' => 'Tlis_trois'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

/** Un navigateur = un fichier de cookies. Rend [code, corps, adresse finale]. */
$jar = static fn (string $nom): string => __DIR__ . '/ck_tlis_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null, string $langue = 'fr') use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur), CURLOPT_HTTPHEADER => ['Accept-Language: ' . $langue]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$projets = [];
$renomme = false;
try {
    foreach (['a', 'b', 'c'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    $lienPublic = bin2hex(random_bytes(16));
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Projet des listes', $id('a'), $lienPublic]);
    $p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id('a'), 'Projet des listes']);
    $projets[] = $p;
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?), (?, ?, ?, ?)', [$p, $id('a'), 'admin', 'membre', $p, $id('b'), 'membre', 'membre']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, statut) VALUES (?, ?, ?)', [$p, 'Écrire le rapport', 'a_faire']);
    $tache = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ?', [$p]);
    $csrf = static fn (string $nav, string $chemin): string => $jeton($appel($nav, $chemin)[1]);
    $points = static fn (string $colonne, int $parent): array => bd_all("SELECT id, texte, fait, fait_par FROM projet_points WHERE $colonne = ? ORDER BY position, id", [$parent]);
    $nbListes = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_listes WHERE projet_id = ?', [$GLOBALS['p']]);

    echo "\n1. L'onglet « Listes »\n";
    [$cod, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('le projet a un onglet « Listes », entre « Document commun » et « Membres »',
        $cod . ' · ' . $oui(preg_match('#Document commun\s*</a>\s*(?:<a href="[^"]*/travaux/' . $p . '/livrables"[^>]*>[\s\S]*?</a>\s*)?<a href="[^"]*/travaux/' . $p . '/listes"[^>]*>\s*<span aria-hidden="true">☑️</span> Listes\s*</a>\s*<a href="[^"]*/travaux/' . $p . '/membres"#u', $tableau) === 1), '200 · oui');
    [$cod, $page] = $appel('a', 'travaux/' . $p . '/listes');
    $dire('la page, sans liste : le formulaire « Créer la liste » et l\'état vide',
        $cod . ' · ' . $oui(str_contains($page, 'Créer la liste') && str_contains($page, 'Aucune liste pour l’instant.') && str_contains($page, 'aria-current="page"')), '200 · oui');

    echo "\n2. Créer une liste\n";
    $t = $csrf('a', 'travaux/' . $p . '/listes');
    [, $vide] = $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $t, 'titre' => '   ']);
    $dire('sans titre : « Donnez un titre à la liste. », rien n\'est créé', $oui(str_contains($vide, 'Donnez un titre à la liste.')) . ' · ' . $nbListes(), 'oui · 0');
    [, $creee] = $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $t, 'titre' => 'Matériel à apporter']);
    $dire('« Liste créée. » et la liste est là, vide', $oui(str_contains($creee, 'Liste créée.') && str_contains($creee, 'Matériel à apporter')) . ' · ' . $nbListes(), 'oui · 1');
    $liste = (int) bd_valeur('SELECT id FROM projet_listes WHERE projet_id = ?', [$p]);
    $dire('elle garde qui l\'a créée', (string) bd_valeur('SELECT cree_par FROM projet_listes WHERE id = ?', [$liste]), (string) $id('a'));
    $appel('b', 'travaux/' . $p . '/listes', ['_csrf' => $csrf('b', 'travaux/' . $p . '/listes'), 'titre' => 'Plan du rapport']);
    $dire('un autre membre peut aussi créer une liste', (string) $nbListes(), '2');
    $planDuRapport = (int) bd_valeur('SELECT id FROM projet_listes WHERE projet_id = ? AND titre = ?', [$p, 'Plan du rapport']);

    echo "\n3. Des points dans une liste\n";
    $t = $csrf('a', 'travaux/' . $p . '/listes');
    [, $vide] = $appel('a', 'travaux/listes/' . $liste . '/points', ['_csrf' => $t, 'texte' => '  ']);
    $dire('un point vide : « Écrivez le point à ajouter. »', $oui(str_contains($vide, 'Écrivez le point à ajouter.')) . ' · ' . count($points('liste_id', $liste)), 'oui · 0');
    foreach (['Ordinateur', 'Chargeur', 'Clé USB'] as $texte) {
        $appel('a', 'travaux/listes/' . $liste . '/points', ['_csrf' => $t, 'texte' => $texte]);
    }
    $dire('trois points, dans l\'ordre où on les a écrits', implode(' · ', array_column($points('liste_id', $liste), 'texte')), 'Ordinateur · Chargeur · Clé USB');
    $appel('b', 'travaux/listes/' . $liste . '/points', ['_csrf' => $csrf('b', 'travaux/' . $p . '/listes'), 'texte' => 'Rallonge']);
    $dire('un autre membre en ajoute un quatrième', (string) count($points('liste_id', $liste)), '4');
    [, $page] = $appel('a', 'travaux/' . $p . '/listes');
    $dire('la page les montre, avec des cases à cocher non cochées et « 0/4 »',
        $oui(substr_count($page, 'name="fait" value="1"') === 4 && !str_contains($page, 'value="1" checked') && str_contains($page, '0/4')), 'oui');
    $longs = $appel('a', 'travaux/listes/' . $liste . '/points', ['_csrf' => $t, 'texte' => str_repeat('é', 300)]);
    $dire('un point trop long est coupé à 200 caractères', (string) mb_strlen((string) bd_valeur('SELECT texte FROM projet_points WHERE liste_id = ? ORDER BY id DESC LIMIT 1', [$liste])), '200');
    bd_run('DELETE FROM projet_points WHERE liste_id = ? AND CHAR_LENGTH(texte) = 200', [$liste]);

    echo "\n4. Cocher, décocher\n";
    $lesPoints = $points('liste_id', $liste);
    $ordinateur = (int) $lesPoints[0]['id'];
    $chargeur = (int) $lesPoints[1]['id'];
    $t = $csrf('b', 'travaux/' . $p . '/listes');
    [$cod, $apres, $adresseApres] = $appel('b', 'travaux/points/' . $ordinateur . '/cocher', ['_csrf' => $t, 'fait' => '1']);
    $ligne = bd_all('SELECT fait, fait_par, fait_le FROM projet_points WHERE id = ?', [$ordinateur])[0];
    $dire('b coche « Ordinateur » : fait, par b, avec l\'heure', $ligne['fait'] . ' · ' . $ligne['fait_par'] . ' · ' . $oui($ligne['fait_le'] !== null), '1 · ' . $id('b') . ' · oui');
    $dire('on revient à la page de la liste, qui dit « 1/4 » et qui l\'a coché',
        $cod . ' · ' . $oui(str_ends_with($adresseApres, '/travaux/' . $p . '/listes') && str_contains($apres, '1/4') && str_contains($apres, 'value="1" checked') && str_contains($apres, '✓ Tlis_deux')), '200 · oui');
    $appel('a', 'travaux/points/' . $chargeur . '/cocher', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes'), 'fait' => '1']);
    [, $moitie] = $appel('a', 'travaux/' . $p . '/listes');
    $dire('a coche « Chargeur » : « 2/4 »', $oui(str_contains($moitie, '2/4')), 'oui');
    $appel('a', 'travaux/points/' . $ordinateur . '/cocher', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes')]);
    $ligne = bd_all('SELECT fait, fait_par, fait_le FROM projet_points WHERE id = ?', [$ordinateur])[0];
    $dire('a décoche « Ordinateur » (sans la valeur) : plus fait, plus de nom ni d\'heure', $ligne['fait'] . ' · ' . ($ligne['fait_par'] ?? 'nul') . ' · ' . ($ligne['fait_le'] ?? 'nul'), '0 · nul · nul');
    $dire('en JSON, la case répond sans changer de page',
        (function () use ($appel, $chargeur, $csrf, $p): string {
            $t = $csrf('a', 'travaux/' . $p . '/listes');
            usleep(250000);
            $h = curl_init('http://localhost/mon_appli/appli/travaux/points/' . $chargeur . '/cocher');
            curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $t, 'fait' => '0']),
                CURLOPT_COOKIEFILE => __DIR__ . '/ck_tlis_a.txt', CURLOPT_COOKIEJAR => __DIR__ . '/ck_tlis_a.txt',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']]);
            $r = (string) curl_exec($h);
            unset($h);
            return $r;
        })(), '{"fait":false}');

    echo "\n5. Renommer, retirer un point, supprimer une liste\n";
    $t = $csrf('a', 'travaux/' . $p . '/listes');
    [, $renommee] = $appel('a', 'travaux/listes/' . $liste . '/modifier', ['_csrf' => $t, 'titre' => 'À emporter']);
    $dire('renommée : « Liste renommée. »', $oui(str_contains($renommee, 'Liste renommée.') && str_contains($renommee, 'À emporter')) . ' · ' . bd_valeur('SELECT titre FROM projet_listes WHERE id = ?', [$liste]), 'oui · À emporter');
    [, $sans] = $appel('a', 'travaux/listes/' . $liste . '/modifier', ['_csrf' => $t, 'titre' => '']);
    $dire('sans titre : refusée, le nom reste', $oui(str_contains($sans, 'Donnez un titre à la liste.')) . ' · ' . bd_valeur('SELECT titre FROM projet_listes WHERE id = ?', [$liste]), 'oui · À emporter');
    $rallonge = (int) $points('liste_id', $liste)[3]['id'];
    $appel('b', 'travaux/points/' . $rallonge . '/supprimer', ['_csrf' => $csrf('b', 'travaux/' . $p . '/listes')]);
    $dire('retirer « Rallonge » (par un autre membre)', (string) count($points('liste_id', $liste)), '3');
    $appel('b', 'travaux/listes/' . $planDuRapport . '/supprimer', ['_csrf' => $csrf('b', 'travaux/' . $p . '/listes')]);
    $dire('supprimer « Plan du rapport » (un membre, pas forcément l\'auteur)', $nbListes() . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_listes WHERE id = ?', [$planDuRapport]), '1 · 0');
    $dire('supprimer une liste emporte ses points', (string) bd_valeur('SELECT COUNT(*) FROM projet_points WHERE liste_id = ?', [$planDuRapport]), '0');

    echo "\n5b. Faire d'une liste une tâche\n";
    $t = $csrf('a', 'travaux/' . $p . '/listes');
    $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $t, 'titre' => 'À transformer']);
    $aTransformer = (int) bd_valeur('SELECT id FROM projet_listes WHERE projet_id = ? AND titre = ?', [$p, 'À transformer']);
    foreach (['Premier point', 'Second point'] as $texte) {
        $appel('a', 'travaux/listes/' . $aTransformer . '/points', ['_csrf' => $t, 'texte' => $texte]);
    }
    $premier = (int) $points('liste_id', $aTransformer)[0]['id'];
    $appel('b', 'travaux/points/' . $premier . '/cocher', ['_csrf' => $csrf('b', 'travaux/' . $p . '/listes'), 'fait' => '1']);
    [, $pageListes] = $appel('a', 'travaux/' . $p . '/listes');
    $dire('chaque liste a « Modifier » (un panneau : renommer, supprimer, Annuler) et, à côté, « En faire une tâche » sans ouvrir le panneau, avec confirmation',
        $oui(substr_count($pageListes, 'acces-bascule') >= 2 && substr_count($pageListes, 'En faire une tâche') === 2 && str_contains($pageListes, '✎ Modifier') && str_contains($pageListes, 'acces-panneau__retrait') && str_contains($pageListes, '>Annuler</label>')
            && preg_match('#bloc-liste__entete">.*?bloc-liste__action"\s+data-confirmation="Faire de la liste « À emporter » une tâche[^>]*>.*?acces-bascule#s', $pageListes) === 1), 'oui');
    [$cod] = $appel('c', 'travaux/listes/' . $aTransformer . '/en-tache', ['_csrf' => $csrf('c', 'compte')]);
    $dire('quelqu\'un qui n\'est pas du projet ne peut pas : 404, la liste reste', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_listes WHERE id = ?', [$aTransformer]), '404 · 1');
    $tachesAvant = (int) bd_valeur('SELECT COUNT(*) FROM projet_taches WHERE projet_id = ?', [$p]);
    [$cod, $apres, $adresse] = $appel('a', 'travaux/listes/' . $aTransformer . '/en-tache', ['_csrf' => $t]);
    $nouvelleTache = bd_all('SELECT id, statut, membre_id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'À transformer']);
    $dire('une tâche « À transformer » est créée, sans responsable, « en cours » (un de ses deux points était coché)',
        count($nouvelleTache) . ' · ' . ($nouvelleTache[0]['statut'] ?? '-') . ' · ' . ($nouvelleTache[0]['membre_id'] ?? 'personne') . ' · ' . ((int) bd_valeur('SELECT COUNT(*) FROM projet_taches WHERE projet_id = ?', [$p]) - $tachesAvant), '1 · en_cours · personne · 1');
    $dire('la liste a disparu, on revient au tableau avec « Liste transformée en tâche. »',
        $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_listes WHERE id = ?', [$aTransformer]) . ' · ' . $oui(str_ends_with($adresse, '/travaux/' . $p) && str_contains($apres, 'Liste transformée en tâche.') && str_contains($apres, 'À transformer')), '200 · 0 · oui');
    $reprise = $points('tache_id', (int) $nouvelleTache[0]['id']);
    $dire('ses points sont ceux de la liste, dans l\'ordre, et celui qui était coché le reste (avec qui l\'a coché)',
        implode(' · ', array_column($reprise, 'texte')) . ' · ' . $reprise[0]['fait'] . $reprise[1]['fait'] . ' · ' . $reprise[0]['fait_par'], 'Premier point · Second point · 10 · ' . $id('b'));
    $dire('et la carte du tableau dit « ☑ 1/2 »', $oui(str_contains($apres, '☑ 1/2')), 'oui');
    bd_run('DELETE FROM projet_taches WHERE id = ?', [(int) $nouvelleTache[0]['id']]);

    echo "\n6. Qui n'est pas du projet n'a pas la main\n";
    [$cod] = $appel('c', 'travaux/' . $p . '/listes');
    $dire('c ne voit pas l\'onglet', (string) $cod, '404');
    $t = $csrf('c', 'compte');
    [$cod1] = $appel('c', 'travaux/listes/' . $liste . '/points', ['_csrf' => $t, 'texte' => 'Intrus']);
    [$cod2] = $appel('c', 'travaux/points/' . $chargeur . '/cocher', ['_csrf' => $t]);
    [$cod3] = $appel('c', 'travaux/points/' . $chargeur . '/supprimer', ['_csrf' => $t]);
    [$cod4] = $appel('c', 'travaux/listes/' . $liste . '/modifier', ['_csrf' => $t, 'titre' => 'Piraté']);
    [$cod5] = $appel('c', 'travaux/listes/' . $liste . '/supprimer', ['_csrf' => $t]);
    [$cod6] = $appel('c', 'travaux/taches/' . $tache . '/points', ['_csrf' => $t, 'texte' => 'Intrus']);
    [$cod7] = $appel('c', 'travaux/' . $p . '/listes', ['_csrf' => $t, 'titre' => 'Piratée']);
    $dire('ajouter, cocher, retirer, renommer, supprimer, créer : tout est refusé (404)', implode(' ', [$cod1, $cod2, $cod3, $cod4, $cod5, $cod6, $cod7]), '404 404 404 404 404 404 404');
    $dire('et rien n\'a bougé', count($points('liste_id', $liste)) . ' · ' . $nbListes() . ' · ' . bd_valeur('SELECT titre FROM projet_listes WHERE id = ?', [$liste]) . ' · ' . count($points('tache_id', $tache)), '3 · 1 · À emporter · 0');
    $avantAnonyme = bd_valeur('SELECT fait FROM projet_points WHERE id = ?', [$chargeur]);
    $appel('anonyme', 'travaux/points/' . $chargeur . '/cocher', ['_csrf' => 'x', 'fait' => '1']);
    $dire('sans compte (ni session) : rien ne bouge', $oui(bd_valeur('SELECT fait FROM projet_points WHERE id = ?', [$chargeur]) == $avantAnonyme), 'oui');

    echo "\n7. Les points d'une tâche\n";
    [, $fenetre] = $appel('a', 'travaux/taches/' . $tache . '/modifier?fenetre=1');
    $dire('la fenêtre de la tâche a son bloc « Points à cocher », sans point pour l\'instant',
        $oui(str_contains($fenetre, 'Points à cocher') && str_contains($fenetre, 'action="' . url_essai('travaux/taches/' . $tache . '/points') . '"') && !str_contains($fenetre, 'name="fait"')), 'oui');
    $dire('ses formulaires restent dans la fenêtre sans la fermer (data-envoi-fenetre sans data-fermer-apres)',
        $oui(preg_match('#<form method="post" data-envoi-fenetre action="[^"]*/travaux/taches/' . $tache . '/points"#', $fenetre) === 1), 'oui');
    $t = $jeton($fenetre);
    foreach (['Plan', 'Introduction', 'Conclusion'] as $texte) {
        $appel('a', 'travaux/taches/' . $tache . '/points', ['_csrf' => $t, 'texte' => $texte]);
    }
    $appel('b', 'travaux/taches/' . $tache . '/points', ['_csrf' => $csrf('b', 'travaux/' . $p), 'texte' => 'Relecture']);
    $dire('quatre points sur la tâche, ajoutés par deux membres', implode(' · ', array_column($points('tache_id', $tache), 'texte')), 'Plan · Introduction · Conclusion · Relecture');
    [, $apresAjout] = $appel('a', 'travaux/taches/' . $tache . '/points', ['_csrf' => $t, 'texte' => '  ']);
    $dire('un point vide est refusé', $oui(str_contains($apresAjout, 'Écrivez le point à ajouter.')) . ' · ' . count($points('tache_id', $tache)), 'oui · 4');
    $plan = (int) $points('tache_id', $tache)[0]['id'];
    $retour = '/mon_appli/appli/travaux/' . $p . '?fenetre=1';
    [$cod, $retourTableau, $adresse] = $appel('b', 'travaux/points/' . $plan . '/cocher', ['_csrf' => $csrf('b', 'travaux/' . $p), 'fait' => '1', 'retour' => $retour]);
    $dire('cocher depuis le tableau ramène au tableau', $cod . ' · ' . $oui(str_ends_with($adresse, '/travaux/' . $p . '?fenetre=1')), '200 · oui');
    [, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('la carte montre ses points (cochés ou non) et l\'avancement « ☑ 1/4 »',
        $oui(str_contains($tableau, '☑ 1/4') && substr_count($tableau, 'action="' . url_essai('travaux/points/') ) >= 4 && str_contains($tableau, 'Introduction') && str_contains($tableau, 'cochees--compact')), 'oui');
    $dire('  la carte garde ses cases mais pas le retrait ni le nom (version compacte)', $oui(!str_contains($tableau, 'coche__retirer') && !str_contains($tableau, '✓ Tlis_deux')), 'oui');
    foreach (array_slice($points('tache_id', $tache), 1) as $pt) {
        $appel('a', 'travaux/points/' . $pt['id'] . '/cocher', ['_csrf' => $csrf('a', 'travaux/' . $p), 'fait' => '1']);
    }
    [, $tout] = $appel('a', 'travaux/' . $p);
    $dire('tout coché : « ☑ 4/4 » en vert', $oui(preg_match('#pastille pastille--ok" title="[^"]*">☑ 4/4#', $tout) === 1), 'oui');
    [, $fenetre] = $appel('a', 'travaux/taches/' . $tache . '/modifier?fenetre=1');
    $dire('la fenêtre dit « 4/4 » et qui a coché', $oui(str_contains($fenetre, '4/4') && str_contains($fenetre, '✓ Tlis_un') && str_contains($fenetre, '✓ Tlis_deux')), 'oui');

    echo "\n7b. Des points dès la création de la tâche\n";
    [, $nouvelle] = $appel('a', 'travaux/' . $p . '/taches/nouvelle?fenetre=1');
    $dire('le formulaire de création propose « Points à cocher » (un par ligne)',
        $oui(str_contains($nouvelle, 'name="points"') && str_contains($nouvelle, 'Points à cocher') && str_contains($nouvelle, 'Un point par ligne')), 'oui');
    $appel('a', 'travaux/' . $p . '/taches', ['_csrf' => $jeton($nouvelle), 'titre' => 'Préparer l\'exposé', 'points' => "Choisir le sujet\r\n\r\n  Faire les diapos  \n" . str_repeat('x', 250) . "\n   \nRépéter"]);
    $creee = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Préparer l\'exposé']);
    $lignes = array_column($points('tache_id', $creee), 'texte');
    $dire('les lignes non vides deviennent des points, dans l\'ordre, nettoyées et coupées à 200',
        count($lignes) . ' · ' . $lignes[0] . ' · ' . $lignes[1] . ' · ' . mb_strlen($lignes[2]) . ' · ' . $lignes[3], '4 · Choisir le sujet · Faire les diapos · 200 · Répéter');
    $appel('a', 'travaux/' . $p . '/taches', ['_csrf' => $jeton($nouvelle), 'titre' => 'Par champs', 'points' => ['Premier', '  Second  ', '', 'Troisième']]);
    $parChamps = array_column($points('tache_id', (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Par champs'])), 'texte');
    $dire('le formulaire avec script envoie « points[] » : un champ par point, les vides ignorés', implode(' · ', $parChamps), 'Premier · Second · Troisième');
    $dire('  et il tient la liste côté page (champ d\'ajout, bouton, repli sans script)', $oui(str_contains($nouvelle, 'data-points-nouveaux') && str_contains($nouvelle, 'data-points-ajouter') && str_contains($nouvelle, '<noscript>')), 'oui');
    $appel('a', 'travaux/' . $p . '/taches', ['_csrf' => $jeton($nouvelle), 'titre' => 'Sans points']);
    $sans = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Sans points']);
    $dire('sans rien écrire, la tâche est créée sans point', $sans > 0 ? (string) count($points('tache_id', $sans)) : 'absente', '0');
    $beaucoup = implode("\n", array_map(static fn (int $i): string => 'Point ' . $i, range(1, Travaux_essai_max_points() + 20)));
    $appel('a', 'travaux/' . $p . '/taches', ['_csrf' => $jeton($nouvelle), 'titre' => 'Beaucoup', 'points' => $beaucoup]);
    $dire('au-delà de la limite, les lignes en trop sont laissées', (string) count($points('tache_id', (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Beaucoup']))), (string) Travaux_essai_max_points());

    echo "\n7c. Le statut de la tâche suit ses points\n";
    bd_run('INSERT INTO projet_taches (projet_id, titre, statut) VALUES (?, ?, ?)', [$p, 'Suivi des points', 'a_faire']);
    $suivie = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Suivi des points']);
    $etat = static fn (): string => bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$suivie]) . ($GLOBALS['suivieFaiteLe']() ? '+date' : '');
    $suivieFaiteLe = static fn (): bool => bd_valeur('SELECT fait_le FROM projet_taches WHERE id = ?', [$suivie]) !== null;
    $cocher = static function (int $pointId, bool $fait) use ($appel, $csrf, $p): void {
        $GLOBALS['appel']('a', 'travaux/points/' . $pointId . '/cocher', ['_csrf' => $csrf('a', 'travaux/' . $p), 'fait' => $fait ? '1' : '0']);
    };
    $tc = $csrf('a', 'travaux/' . $p);
    foreach (['Un', 'Deux'] as $texte) {
        $appel('a', 'travaux/taches/' . $suivie . '/points', ['_csrf' => $tc, 'texte' => $texte]);
    }
    [$un, $deux] = array_map(static fn (array $l): int => (int) $l['id'], $points('tache_id', $suivie));
    $dire('deux points pas cochés : la tâche reste « à faire »', $etat(), 'a_faire');
    $cocher($un, true);
    $dire('un point coché sur deux : la tâche passe « en cours »', $etat(), 'en_cours');
    $cocher($deux, true);
    $dire('tous cochés : la tâche est « faite », avec sa date', $etat(), 'fait+date');
    $appel('a', 'travaux/taches/' . $suivie . '/points', ['_csrf' => $tc, 'texte' => 'Trois']);
    $dire('un point de plus sur une tâche faite : elle redevient « en cours » (sans date de fin)', $etat(), 'en_cours');
    $trois = (int) $points('tache_id', $suivie)[2]['id'];
    $cocher($trois, true);
    $cocher($un, false);
    $cocher($deux, false);
    $cocher($trois, false);
    $dire('plus aucun point coché : une tâche « en cours » le reste (on l\'avait peut-être lancée à la main)', $etat(), 'en_cours');
    $appel('a', 'travaux/points/' . $deux . '/supprimer', ['_csrf' => $tc]);
    $appel('a', 'travaux/points/' . $trois . '/supprimer', ['_csrf' => $tc]);
    $cocher($un, true);
    $dire('on retire les points pas cochés : il ne reste que des points cochés, la tâche est « faite »', $etat(), 'fait+date');
    $cocher($un, false);
    $dire('et si l\'on décoche le seul point d\'une tâche faite : elle redevient « à faire »', $etat(), 'a_faire');
    $appel('a', 'travaux/taches/' . $suivie . '/statut', ['_csrf' => $tc, 'statut' => 'fait']);
    $dire('un statut choisi à la main n\'est pas défait tant qu\'on ne touche pas aux points', $etat(), 'fait+date');
    bd_run('DELETE FROM projet_taches WHERE id = ?', [$suivie]);
    $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes'), 'titre' => 'Liste cochée']);
    $cochee = (int) bd_valeur('SELECT id FROM projet_listes WHERE projet_id = ? AND titre = ?', [$p, 'Liste cochée']);
    $appel('a', 'travaux/listes/' . $cochee . '/points', ['_csrf' => $tc, 'texte' => 'Déjà fait']);
    $appel('a', 'travaux/points/' . (int) $points('liste_id', $cochee)[0]['id'] . '/cocher', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes'), 'fait' => '1']);
    $appel('a', 'travaux/listes/' . $cochee . '/en-tache', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes')]);
    $dire('une liste dont tous les points sont cochés, faite tâche : la tâche est « faite »',
        (string) bd_valeur('SELECT statut FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Liste cochée']), 'fait');
    bd_run('DELETE FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, 'Liste cochée']);

    echo "\n8. Les limites\n";
    $maximum = Travaux_essai_max_points();
    $manque = $maximum - (int) bd_valeur('SELECT COUNT(*) FROM projet_points WHERE tache_id = ?', [$tache]);
    for ($i = 0; $i < $manque; $i++) {
        bd_run('INSERT INTO projet_points (tache_id, texte, position, created_at) VALUES (?, ?, ?, NOW())', [$tache, 'Rempli ' . $i, 1000 + $i]);
    }
    [, $plein] = $appel('a', 'travaux/taches/' . $tache . '/points', ['_csrf' => $t, 'texte' => 'De trop']);
    $dire('une tâche a au plus ' . $maximum . ' points', $oui(str_contains($plein, 'Au plus ' . $maximum . ' points ici.')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_points WHERE tache_id = ?', [$tache]), 'oui · ' . $maximum);
    $manque = Travaux_essai_max_listes() - $nbListes();
    for ($i = 0; $i < $manque; $i++) {
        bd_run('INSERT INTO projet_listes (projet_id, titre, cree_par, position, created_at) VALUES (?, ?, ?, ?, NOW())', [$p, 'Remplie ' . $i, $id('a'), 1000 + $i]);
    }
    [, $pleines] = $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $t, 'titre' => 'De trop']);
    $dire('un groupe a au plus ' . Travaux_essai_max_listes() . ' listes', $oui(str_contains($pleines, 'Un groupe a au plus ' . Travaux_essai_max_listes() . ' listes.')) . ' · ' . $nbListes(), 'oui · ' . Travaux_essai_max_listes());
    bd_run("DELETE FROM projet_listes WHERE projet_id = ? AND titre LIKE 'Remplie %'", [$p]);
    bd_run("DELETE FROM projet_points WHERE tache_id = ? AND texte LIKE 'Rempli %'", [$tache]);

    echo "\n9. Le lien public : en lecture seule\n";
    [$cod, $public] = $appel('a', 'g/' . $lienPublic);
    $dire('la liste « À emporter » et ses points cochés ou non, sans formulaire',
        $cod . ' · ' . $oui(str_contains($public, 'À emporter') && str_contains($public, 'Chargeur') && str_contains($public, '☐') && !str_contains($public, 'name="fait"') && !str_contains($public, '/points')), '200 · oui');
    $dire('l\'avancement de la tâche (« ☑ 4/4 ») aussi', $oui(str_contains($public, '☑ 4/4')), 'oui');
    [, $anonyme] = $appel('anonyme', 'g/' . $lienPublic);
    $dire('sans dire son prénom, on ne voit pas les listes', $oui(!str_contains($anonyme, 'À emporter') && !str_contains($anonyme, 'Chargeur')), 'oui');

    echo "\n10. Une tâche supprimée emporte ses points\n";
    bd_run('INSERT INTO projet_taches (projet_id, titre, statut) VALUES (?, ?, ?)', [$p, 'Tâche éphémère', 'a_faire']);
    $ephemere = (int) bd_valeur('SELECT id FROM projet_taches WHERE titre = ? AND projet_id = ?', ['Tâche éphémère', $p]);
    $appel('a', 'travaux/taches/' . $ephemere . '/points', ['_csrf' => $t, 'texte' => 'Éphémère']);
    $avant = (int) bd_valeur('SELECT COUNT(*) FROM projet_points WHERE tache_id = ?', [$ephemere]);
    $appel('a', 'travaux/taches/' . $ephemere . '/supprimer', ['_csrf' => $t]);
    $dire('un point, puis la tâche supprimée : plus de point', $avant . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_points WHERE tache_id = ?', [$ephemere]), '1 · 0');

    echo "\n11. Sans la migration, le site tourne\n";
    bd_run('RENAME TABLE projet_points TO projet_points_essai, projet_listes TO projet_listes_essai');
    $renomme = true;
    [$c1, $page1] = $appel('a', 'travaux/' . $p);
    [$c2, $page2] = $appel('a', 'travaux/taches/' . $tache . '/modifier?fenetre=1');
    [$c3] = $appel('a', 'travaux/' . $p . '/listes');
    [$c4, $page4] = $appel('a', 'g/' . $lienPublic);
    [$c5] = $appel('a', 'travaux/points/' . $chargeur . '/cocher', ['_csrf' => $t, 'fait' => '1']);
    bd_run('RENAME TABLE projet_points_essai TO projet_points, projet_listes_essai TO projet_listes');
    $renomme = false;
    $dire('le tableau, la fenêtre de la tâche et le lien public s\'ouvrent', $c1 . ' ' . $c2 . ' ' . $c4, '200 200 200');
    $dire('  sans onglet « Listes », sans bloc de points, sans avancement',
        $oui(!str_contains($page1, '/listes"') && !str_contains($page2, 'Points à cocher') && !str_contains($page1, '☑') && !str_contains($page4, 'À emporter')), 'oui');
    $dire('  l\'onglet et les actions répondent 404 au lieu de planter', $c3 . ' ' . $c5, '404 404');

    echo "\n12. Les quatre langues\n";
    $attendus = [
        'en' => ['</span> Lists', 'Create the list', 'No lists yet.', 'Add an item…', 'List title', 'Checklist', 'Make it a task'],
        'es' => ['</span> Listas', 'Crear la lista', 'Todavía no hay listas.', 'Añadir un punto…', 'Título de la lista', 'Puntos para marcar', 'Convertir en tarea'],
        'de' => ['</span> Listen', 'Liste erstellen', 'Noch keine Listen.', 'Punkt hinzufügen…', 'Titel der Liste', 'Abhakbare Punkte', 'Zur Aufgabe machen'],
    ];
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => $langue]);
        [, $listesLangue] = $appel('a', 'travaux/' . $p . '/listes');
        [, $tacheLangue] = $appel('a', 'travaux/taches/' . $tache . '/modifier?fenetre=1');
        [, $tableauLangue] = $appel('a', 'travaux/' . $p);
        [, $nouvelleLangue] = $appel('a', 'travaux/' . $p . '/taches/nouvelle?fenetre=1');
        $dire($langue . ' : l\'onglet, la liste, la fenêtre de la tâche',
            $oui(str_contains($tableauLangue, $mots[0]) && str_contains($listesLangue, $mots[1]) && str_contains($tacheLangue, $mots[5]) && str_contains($listesLangue, $mots[3]) && str_contains($listesLangue, $mots[4]) && str_contains($listesLangue, $mots[6])), 'oui');
        $aide = ['en' => 'One item per line', 'es' => 'Un punto por línea', 'de' => 'Ein Punkt pro Zeile'][$langue];
        $dire($langue . ' : la création d\'une tâche propose ses points', $oui(str_contains($nouvelleLangue, $aide) && !str_contains($nouvelleLangue, 'Un point par ligne') && !preg_match('/\btr\.li2\./', $nouvelleLangue)), 'oui');
        $dire($langue . ' : pas de clé brute ni de français resté', $oui(!preg_match('/\btr\.(li2|on)\.[a-z_.]+/', $listesLangue . $tacheLangue . $tableauLangue)
            && !str_contains($listesLangue, 'Créer la liste') && !str_contains($tacheLangue, 'Points à cocher')), 'oui');
        // Une liste vide dans cette langue : les refus aussi
        [, $refus] = $appel('a', 'travaux/' . $p . '/listes', ['_csrf' => $csrf('a', 'travaux/' . $p . '/listes'), 'titre' => ' ']);
        $dire($langue . ' : le refus est traduit', $oui(!str_contains($refus, 'Donnez un titre') && !preg_match('/\btr\.li2\./', $refus) && str_contains($refus, 'flash--erreur')), 'oui');
    }
    $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => 'fr']);
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('RENAME TABLE projet_points_essai TO projet_points, projet_listes_essai TO projet_listes'); } catch (Throwable) {}
    }
    // Ménage : les projets d'essai (leurs tâches, listes et points partent avec eux), puis les comptes.
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach (glob(__DIR__ . '/ck_tlis_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tlis-_@exemple-test.fr'")
        . ' · points restants ' . bd_valeur('SELECT COUNT(*) FROM projet_points WHERE texte IN (?, ?)', ['Intrus', 'Éphémère']) . "\n";
}

/** L'adresse d'une page du site, telle que l'application la met dans ses formulaires (le script d'essai ne charge rien de l'application). */
function url_essai(string $chemin): string
{
    return '/mon_appli/appli/' . $chemin;
}

/** Les plafonds de l'application, lus dans son code (le script d'essai ne charge rien d'elle). */
function Travaux_essai_max_points(): int
{
    return preg_match('/POINTS_MAX = (\d+)/', (string) file_get_contents(dirname(__DIR__, 2) . '/src/Travaux.php'), $m) ? (int) $m[1] : 0;
}

function Travaux_essai_max_listes(): int
{
    return preg_match('/LISTES_MAX = (\d+)/', (string) file_get_contents(dirname(__DIR__, 2) . '/src/Travaux.php'), $m) ? (int) $m[1] : 0;
}
