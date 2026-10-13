<?php
/**
 * Les colonnes en plus du tableau d'un travail de groupe, les phases du projet (HTTP + base) : seul l'administrateur du groupe les ajoute,
 * renomme, déplace et supprime ; les noms refusés (vide, déjà pris, trop de colonnes) ; le tableau qui les montre entre « En cours » et « Fait » ;
 * une tâche qu'on y range (menu de statut) et qui en sort (« Fait », points tous cochés) ; la suppression qui renvoie ses tâches dans « En cours » ;
 * le récapitulatif PDF qui dit la phase ; un site dont la migration n'est pas passée ; et les quatre langues.
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
    'a' => ['email' => 'tcol-a@exemple-test.fr', 'pseudo' => 'Tcol_Admin'],
    'b' => ['email' => 'tcol-b@exemple-test.fr', 'pseudo' => 'Tcol_Membre'],
    'c' => ['email' => 'tcol-c@exemple-test.fr', 'pseudo' => 'Tcol_Dehors'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$jar = static fn (string $nom): string => __DIR__ . '/ck_tcol_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null) use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur)]);
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
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Projet des colonnes (essai)', $id('a'), $lienPublic = bin2hex(random_bytes(16))]);
    $p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $p;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre'), (?, ?, 'membre', 'membre')", [$p, $id('a'), $p, $id('b')]);
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Autre projet (essai)', $id('a'), bin2hex(random_bytes(16))]);
    $autre = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $autre;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$autre, $id('a')]);
    foreach (['Chercher', 'Rédiger', 'Relire', 'Rendre'] as $titre) {
        bd_run('INSERT INTO projet_taches (projet_id, titre, statut) VALUES (?, ?, ?)', [$p, $titre, 'a_faire']);
    }
    $tacheId = static fn (string $titre): int => (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, $titre]);
    $colonnes = static fn (int $projet = 0): array => array_column(bd_all('SELECT id, nom FROM projet_colonnes WHERE projet_id = ? ORDER BY position, id', [$projet ?: $GLOBALS['p']]), 'nom', 'id');
    $csrf = static fn (string $nav, string $chemin): string => $jeton($GLOBALS['appel']($nav, $chemin)[1]);
    $noms = static fn (): string => implode(' | ', $colonnes());

    echo "\n1. Seul l'administrateur y touche\n";
    [$cod, $page] = $appel('a', 'travaux/' . $p . '/colonnes');
    $dire('l\'administrateur ouvre « Colonnes du tableau » (le formulaire d\'ajout, l\'aide)',
        $cod . ' · ' . $oui(str_contains($page, 'Colonnes du tableau') && str_contains($page, 'name="nom"') && str_contains($page, 'Nouvelle colonne (Recherche') && str_contains($page, 'entre « À faire » et « Fait »') && str_contains($page, 'Placer après') && str_contains($page, 'name="icone"')), '200 · oui');
    [$cod1] = $appel('b', 'travaux/' . $p . '/colonnes');
    [$cod2] = $appel('c', 'travaux/' . $p . '/colonnes');
    $dire('un simple membre : 404 ; quelqu\'un du dehors : 404', $cod1 . ' · ' . $cod2, '404 · 404');
    $tb = $csrf('b', 'travaux/' . $p);
    [$c1] = $appel('b', 'travaux/' . $p . '/colonnes', ['_csrf' => $tb, 'nom' => 'Pirate']);
    $dire('un membre qui tente d\'en ajouter une : refusé, rien n\'est créé', $oui(str_contains($appel('b', 'travaux/' . $p)[1], 'seul l’administrateur') || $noms() === '') . ' · ' . $noms(), 'oui · ');

    echo "\n2. Ajouter, et les noms refusés\n";
    $ta = $csrf('a', 'travaux/' . $p . '/colonnes');
    foreach (['Recherche', 'Rédaction', 'Relecture'] as $nom) {
        [, $apres] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => $nom]);
    }
    $dire('trois phases : « Recherche », « Rédaction », « Relecture », dans l\'ordre', $noms(), 'Recherche | Rédaction | Relecture');
    $dire('« Colonne ajoutée. » et elles sont listées', $oui(str_contains($apres, 'Colonne ajoutée.') && str_contains($apres, '🔹 Recherche') && str_contains($apres, '0 tâche')), 'oui');
    [, $vide] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => '   ']);
    $dire('sans nom : « Donnez un nom à la colonne. »', $oui(str_contains($vide, 'Donnez un nom à la colonne.')) . ' · ' . count($colonnes()), 'oui · 3');
    [, $double] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'rédaction']);
    $dire('un nom déjà pris (majuscules ou non) : « Une colonne porte déjà ce nom. »', $oui(str_contains($double, 'Une colonne porte déjà ce nom.')) . ' · ' . count($colonnes()), 'oui · 3');
    [, $base] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'En cours']);
    $dire('« En cours » (une colonne de base) non plus', $oui(str_contains($base, 'Une colonne porte déjà ce nom.')) . ' · ' . count($colonnes()), 'oui · 3');
    [, $long] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => str_repeat('é', 100)]);
    $dernier = array_values($colonnes())[3] ?? '';
    $dire('un nom trop long est coupé à 40 caractères', (string) mb_strlen($dernier), '40');
    bd_run('DELETE FROM projet_colonnes WHERE projet_id = ? AND nom = ?', [$p, $dernier]);
    $reste = Travaux_essai_colonnes_max() - count($colonnes());
    for ($i = 0; $i < $reste; $i++) {
        bd_run('INSERT INTO projet_colonnes (projet_id, nom, position, created_at) VALUES (?, ?, ?, NOW())', [$p, 'Remplie ' . $i, 100 + $i]);
    }
    [, $trop] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'De trop']);
    $dire('au plus ' . Travaux_essai_colonnes_max() . ' colonnes en plus', $oui(str_contains($trop, 'Un tableau a au plus ' . Travaux_essai_colonnes_max() . ' colonnes en plus.')) . ' · ' . count($colonnes()), 'oui · ' . Travaux_essai_colonnes_max());
    bd_run("DELETE FROM projet_colonnes WHERE projet_id = ? AND nom LIKE 'Remplie %'", [$p]);

    echo "\n3. Le tableau\n";
    [, $tableau] = $appel('a', 'travaux/' . $p);
    preg_match_all('#<section class="kanban__colonne" data-statut="([^"]+)">\s*<h2[^>]*>[^<]*?\s*([^<\n]+?)\s*<span#u', $tableau, $m);
    $ids = array_keys($colonnes());
    $dire('six colonnes, de gauche à droite : À faire, En cours, les trois phases, Fait',
        implode(' · ', $m[1]), 'a_faire · en_cours · col:' . $ids[0] . ' · col:' . $ids[1] . ' · col:' . $ids[2] . ' · fait');
    $dire('  avec leurs noms', implode(' | ', array_map(static fn (string $s): string => trim((string) preg_replace('/^[^\p{L}]+/u', '', $s)), $m[2])), 'À faire | En cours | Recherche | Rédaction | Relecture | Fait');
    $dire('plus de trois colonnes : le tableau défile de côté (6 colonnes)', $oui(str_contains($tableau, 'travaux-kanban travaux-kanban--defile') && str_contains($tableau, '--colonnes:6')), 'oui');
    $dire('l\'administrateur a « + Nouvelle colonne »', $oui(str_contains($tableau, 'href="/mon_appli/appli/travaux/' . $p . '/colonnes" data-fenetre-dessus data-relire-derriere') && str_contains($tableau, '+ Nouvelle colonne')), 'oui');
    [, $tableauB] = $appel('b', 'travaux/' . $p);
    $dire('un simple membre voit les colonnes, mais pas le bouton', $oui(str_contains($tableauB, 'data-statut="col:' . $ids[1] . '"') && !str_contains($tableauB, '+ Nouvelle colonne')), 'oui');
    $dire('le menu de statut d\'une carte propose toutes les colonnes',
        $oui(str_contains($tableau, '<option value="col:' . $ids[0] . '">Recherche</option>') && str_contains($tableau, '<option value="a_faire" selected>À faire</option>') && str_contains($tableau, '<option value="fait">Fait</option>')), 'oui');

    echo "\n4. Ranger une tâche dans une phase\n";
    $chercher = $tacheId('Chercher');
    $tt = $jeton($tableau);
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[0]]);
    $ligne = bd_all('SELECT statut, colonne_id, fait_le FROM projet_taches WHERE id = ?', [$chercher])[0];
    $dire('« Chercher » dans « Recherche » : statut « en cours » pour le reste du site, rangée dans la phase', $ligne['statut'] . ' · ' . $ligne['colonne_id'] . ' · ' . ($ligne['fait_le'] ?? 'sans date'), 'en_cours · ' . $ids[0] . ' · sans date');
    [, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('la carte est dans la colonne « Recherche » (et le menu y est sélectionné)',
        $oui(preg_match('#data-statut="col:' . $ids[0] . '".*?id="tache-' . $chercher . '".*?<option value="col:' . $ids[0] . '" selected>#s', $tableau) === 1), 'oui');
    $dire('et le compteur de la colonne dit 1', $oui(preg_match('#data-statut="col:' . $ids[0] . '">\s*<h2[^>]*>.*?kanban__compteur">1<#s', $tableau) === 1), 'oui');
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[2]]);
    $dire('de phase en phase : toujours « en cours »', bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$chercher]) . ' · ' . bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$chercher]), 'en_cours · ' . $ids[2]);
    $appel('b', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tb, 'statut' => 'col:' . $ids[1]]);
    $dire('un simple membre range aussi une tâche (comme pour les autres colonnes)', (string) bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$chercher]), (string) $ids[1]);
    bd_run('INSERT INTO projet_colonnes (projet_id, nom, position, created_at) VALUES (?, ?, 1, NOW())', [$autre, 'Ailleurs']);
    $ailleurs = (int) bd_valeur('SELECT id FROM projet_colonnes WHERE projet_id = ?', [$autre]);
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ailleurs]);
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:999999']);
    $dire('une colonne d\'un autre projet, ou qui n\'existe pas : refusé, la tâche ne bouge pas', (string) bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$chercher]), (string) $ids[1]);
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'fait']);
    $ligne = bd_all('SELECT statut, colonne_id, fait_le FROM projet_taches WHERE id = ?', [$chercher])[0];
    $dire('vers « Fait » : elle quitte la phase, avec sa date de fin', $ligne['statut'] . ' · ' . ($ligne['colonne_id'] ?? 'sans phase') . ' · ' . $oui($ligne['fait_le'] !== null), 'fait · sans phase · oui');
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[0]]);
    $dire('et de « Fait » à une phase : « en cours », sans date de fin', bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$chercher]) . ' · ' . $oui(bd_valeur('SELECT fait_le FROM projet_taches WHERE id = ?', [$chercher]) === null), 'en_cours · oui');
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'en_cours']);
    $dire('vers « En cours » (la colonne de base) : elle quitte la phase', bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$chercher]) . ' · ' . ((bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$chercher])) ?? 'sans phase'), 'en_cours · sans phase');

    echo "\n5. Une tâche d'une phase, et ses points à cocher\n";
    $rediger = $tacheId('Rédiger');
    $appel('a', 'travaux/taches/' . $rediger . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[1]]);
    foreach (['Plan', 'Texte'] as $texte) {
        $appel('a', 'travaux/taches/' . $rediger . '/points', ['_csrf' => $tt, 'texte' => $texte]);
    }
    $dire('ajouter un point à une tâche rangée dans une phase la laisse là (« en cours » reste « en cours »)',
        bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$rediger]) . ' · ' . bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$rediger]), 'en_cours · ' . $ids[1]);
    $pts = array_column(bd_all('SELECT id FROM projet_points WHERE tache_id = ? ORDER BY position', [$rediger]), 'id');
    $appel('a', 'travaux/points/' . $pts[0] . '/cocher', ['_csrf' => $tt, 'fait' => '1']);
    $dire('un point sur deux coché : elle reste dans sa phase', (string) bd_valeur('SELECT colonne_id FROM projet_taches WHERE id = ?', [$rediger]), (string) $ids[1]);
    $appel('a', 'travaux/points/' . $pts[1] . '/cocher', ['_csrf' => $tt, 'fait' => '1']);
    $ligne = bd_all('SELECT statut, colonne_id FROM projet_taches WHERE id = ?', [$rediger])[0];
    $dire('tous cochés : elle est « faite » et quitte la phase', $ligne['statut'] . ' · ' . ($ligne['colonne_id'] ?? 'sans phase'), 'fait · sans phase');

    echo "\n6. Renommer, déplacer, supprimer\n";
    [$cod1] = $appel('b', 'travaux/colonnes/' . $ids[0] . '/modifier', ['_csrf' => $tb, 'nom' => 'Piratée']);
    $dire('un simple membre ne renomme pas (404)', $cod1 . ' · ' . $colonnes()[$ids[0]], '404 · Recherche');
    [, $renommee] = $appel('a', 'travaux/colonnes/' . $ids[0] . '/modifier', ['_csrf' => $ta, 'nom' => 'Documentation']);
    $dire('l\'administrateur renomme : « Colonne renommée. »', $oui(str_contains($renommee, 'Colonne renommée.')) . ' · ' . $colonnes()[$ids[0]], 'oui · Documentation');
    [, $memeNom] = $appel('a', 'travaux/colonnes/' . $ids[0] . '/modifier', ['_csrf' => $ta, 'nom' => 'documentation']);
    $dire('la rebaptiser avec son propre nom (autres majuscules) est permis', $oui(str_contains($memeNom, 'Colonne renommée.')) . ' · ' . $colonnes()[$ids[0]], 'oui · documentation');
    [, $prise] = $appel('a', 'travaux/colonnes/' . $ids[0] . '/modifier', ['_csrf' => $ta, 'nom' => 'Relecture']);
    $dire('mais pas celui d\'une autre', $oui(str_contains($prise, 'Une colonne porte déjà ce nom.')) . ' · ' . $colonnes()[$ids[0]], 'oui · documentation');
    $appel('a', 'travaux/colonnes/' . $ids[0] . '/deplacer', ['_csrf' => $ta, 'sens' => 'droite']);
    $dire('« documentation » d\'un cran vers la droite', $noms(), 'Rédaction | documentation | Relecture');
    $appel('a', 'travaux/colonnes/' . $ids[0] . '/deplacer', ['_csrf' => $ta, 'sens' => 'gauche']);
    $appel('a', 'travaux/colonnes/' . $ids[0] . '/deplacer', ['_csrf' => $ta, 'sens' => 'gauche']);
    $dire('puis deux crans vers la gauche : « documentation » passe par-dessus « En cours »', $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE id = ?', [$ids[0]]), 'documentation | Rédaction | Relecture · a_faire');
    $appel('a', 'travaux/colonnes/' . $ids[0] . '/deplacer', ['_csrf' => $ta, 'sens' => 'gauche']);
    $dire('au bord (juste après « À faire »), on n\'avance plus à gauche', $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE id = ?', [$ids[0]]), 'documentation | Rédaction | Relecture · a_faire');
    $appel('b', 'travaux/colonnes/' . $ids[0] . '/deplacer', ['_csrf' => $tb, 'sens' => 'droite']);
    $dire('un simple membre ne déplace pas', $noms(), 'documentation | Rédaction | Relecture');
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[2]]);
    $appel('a', 'travaux/taches/' . $tacheId('Relire') . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[2]]);
    [, $pageSup] = $appel('a', 'travaux/' . $p . '/colonnes');
    $dire('la page dit « 2 tâches » pour « Relecture », et la confirmation de suppression promet « En cours »',
        $oui(str_contains($pageSup, '2 tâches') && str_contains($pageSup, 'data-confirmation="Supprimer la colonne « Relecture » ? Ses tâches repassent dans « En cours ».')), 'oui');
    [$cod1] = $appel('b', 'travaux/colonnes/' . $ids[2] . '/supprimer', ['_csrf' => $tb]);
    $dire('un simple membre ne supprime pas (404)', $cod1 . ' · ' . count($colonnes()), '404 · 3');
    [, $supprimee] = $appel('a', 'travaux/colonnes/' . $ids[2] . '/supprimer', ['_csrf' => $ta]);
    $dire('l\'administrateur supprime « Relecture » : « Colonne supprimée. »', $oui(str_contains($supprimee, 'Colonne supprimée.')) . ' · ' . $noms(), 'oui · documentation | Rédaction');
    $dire('ses deux tâches repassent dans « En cours » de base (pas perdues)',
        bd_valeur('SELECT COUNT(*) FROM projet_taches WHERE projet_id = ? AND statut = \'en_cours\' AND colonne_id IS NULL AND titre IN (\'Chercher\', \'Relire\')', [$p]) . ' tâches', '2 tâches');

    echo "\n6b. Choisir l'icône et la place\n";
    $ta = $csrf('a', 'travaux/' . $p . '/colonnes');
    $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'Idées', 'icone' => '💡', 'apres' => 'a_faire']);
    $idees = (int) array_search('Idées', $colonnes(), true);
    $dire('« Idées » rangée juste après « À faire », avec l\'icône 💡 : avant « documentation »', $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE id = ?', [$idees]) . ' · ' . bd_valeur('SELECT icone FROM projet_colonnes WHERE id = ?', [$idees]),
        'Idées | documentation | Rédaction · a_faire · 💡');
    $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'Cible', 'icone' => '<b>x</b>', 'apres' => 'col:' . $ids[1]]);
    $cible = (int) array_search('Cible', $colonnes(), true);
    $dire('« Cible », rangée après « Rédaction », avec une icône qui n\'est pas proposée : l\'icône de départ 🔹', $noms() . ' · ' . bd_valeur('SELECT icone FROM projet_colonnes WHERE id = ?', [$cible]),
        'Idées | documentation | Rédaction | Cible · 🔹');
    $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'Fin', 'icone' => '🏁', 'apres' => 'fait']);
    $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'Fin bis', 'icone' => '🚀']);
    $dire('« après Fait » n\'existe pas, ni rien du tout : tout à droite, avant « Fait »', $noms(), 'Idées | documentation | Rédaction | Cible | Fin | Fin bis');
    $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $ta, 'nom' => 'Au milieu', 'icone' => '🔍', 'apres' => 'en_cours']);
    $dire('« Au milieu » juste après « En cours » : avant « Rédaction », après « documentation » (zone « en cours »)',
        $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE nom = ?', ['Au milieu']), 'Idées | documentation | Au milieu | Rédaction | Cible | Fin | Fin bis · en_cours');
    [, $tableauIcones] = $appel('a', 'travaux/' . $p);
    preg_match_all('#data-statut="([^"]+)">\s*<h2[^>]*>\s*([^\s<]+)#u', $tableauIcones, $mi);
    $dire('le tableau : À faire, Idées, documentation, En cours, Au milieu, Rédaction, Cible, Fin, Fin bis, Fait — avec leurs icônes',
        implode(' ', $mi[2]), '⬜ 💡 🔹 ⏳ 🔍 🔹 🔹 🏁 🚀 ✅');
    $dire('  dans cet ordre', implode(' ', array_map(static fn (string $c): string => str_starts_with($c, 'col:') ? 'phase' : $c, $mi[1])), 'a_faire phase phase en_cours phase phase phase phase phase fait');
    $dire('le menu de statut d\'une carte montre aussi leurs icônes', $oui(str_contains($tableauIcones, '>💡 Idées</option>') || str_contains($tableauIcones, '>Idées</option>')), 'oui');
    [, $gestion] = $appel('a', 'travaux/' . $p . '/colonnes');
    $dire('la fenêtre des colonnes montre aussi les trois colonnes de base, « Colonne fixe » (sans réglage)',
        (string) substr_count($gestion, 'Colonne fixe'), '3');
    $dire('  et ← est grisé pour la première phase, → pour la dernière (et seulement elles)',
        (string) preg_match_all('/aria-label="Déplacer vers (?:la gauche|la droite)" disabled>/', $gestion), '2');
    $appel('a', 'travaux/colonnes/' . $idees . '/deplacer', ['_csrf' => $ta, 'sens' => 'gauche']);
    $dire('« Idées » ne va pas avant « À faire »', $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE id = ?', [$idees]), 'Idées | documentation | Au milieu | Rédaction | Cible | Fin | Fin bis · a_faire');
    $appel('a', 'travaux/colonnes/' . $idees . '/deplacer', ['_csrf' => $ta, 'sens' => 'droite']);
    $appel('a', 'travaux/colonnes/' . $idees . '/deplacer', ['_csrf' => $ta, 'sens' => 'droite']);
    $dire('deux crans à droite : par-dessus « documentation » puis « En cours » — elle passe en zone « en cours »',
        $noms() . ' · ' . bd_valeur('SELECT zone FROM projet_colonnes WHERE id = ?', [$idees]), 'documentation | Idées | Au milieu | Rédaction | Cible | Fin | Fin bis · en_cours');
    [, $change] = $appel('a', 'travaux/colonnes/' . $cible . '/modifier', ['_csrf' => $ta, 'nom' => 'Cible', 'icone' => '🏁']);
    $dire('on change l\'icône d\'une phase (même nom) : « Colonne renommée. » et 🏁', $oui(str_contains($change, 'Colonne renommée.')) . ' · ' . bd_valeur('SELECT icone FROM projet_colonnes WHERE id = ?', [$cible]), 'oui · 🏁');
    $appel('a', 'travaux/colonnes/' . $cible . '/modifier', ['_csrf' => $ta, 'nom' => 'Cible', 'icone' => '🚫']);
    $dire('une icône qui n\'est pas proposée ne change rien (🏁 reste)', (string) bd_valeur('SELECT icone FROM projet_colonnes WHERE id = ?', [$cible]), '🏁');
    $appel('b', 'travaux/colonnes/' . $cible . '/modifier', ['_csrf' => $tb, 'nom' => 'Cible', 'icone' => '🔒']);
    $dire('un simple membre ne change pas l\'icône', (string) bd_valeur('SELECT icone FROM projet_colonnes WHERE id = ?', [$cible]), '🏁');
    foreach (['Idées', 'Au milieu', 'Fin', 'Fin bis', 'Cible'] as $nomASupprimer) {
        $appel('a', 'travaux/colonnes/' . array_search($nomASupprimer, $colonnes(), true) . '/supprimer', ['_csrf' => $ta]);
    }
    $dire('remis comme avant', $noms(), 'documentation | Rédaction');

    echo "\n7. Le récapitulatif PDF dit la phase\n";
    $appel('a', 'travaux/taches/' . $tacheId('Rendre') . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[1]]);
    [$cod, $pdf] = $appel('a', 'travaux/' . $p . '/recapitulatif');
    $texte = '';
    preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $flux);
    foreach ($flux[1] as $brut) {
        $contenu = @gzuncompress($brut);
        if ($contenu !== false && preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', $contenu, $mm)) {
            $texte .= implode("\n", array_map(static fn (string $s): string => (string) mb_convert_encoding(strtr($s, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\']), 'UTF-8', 'Windows-1252'), $mm[1])) . "\n";
        }
    }
    $dire('la tâche « Rendre », rangée dans « Rédaction », est dite « Rédaction » (pas « En cours »)', $cod . ' · ' . $oui(preg_match('/Rendre\nRédaction\n/u', $texte) === 1), '200 · oui');
    [$cod, $public] = $appel('anonyme', 'g/' . $lienPublic);
    $dire('le lien public, lui, montre toujours les tâches (une tâche dans une phase compte « en cours »)', $oui(str_contains($public, 'Rendre') || str_contains($public, 'name="prenom"')), 'oui');

    echo "\n8. Sans la migration, le site tourne\n";
    bd_run('RENAME TABLE projet_colonnes TO projet_colonnes_essai');
    $renomme = true;
    [$c1, $page1] = $appel('a', 'travaux/' . $p);
    [$c2] = $appel('a', 'travaux/' . $p . '/colonnes');
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'col:' . $ids[0]]);
    $appel('a', 'travaux/taches/' . $chercher . '/statut', ['_csrf' => $tt, 'statut' => 'fait']);
    $statutSansMigration = (string) bd_valeur('SELECT statut FROM projet_taches WHERE id = ?', [$chercher]);
    [$c3] = $appel('a', 'travaux/' . $p . '/recapitulatif');
    bd_run('RENAME TABLE projet_colonnes_essai TO projet_colonnes');
    $renomme = false;
    $dire('le tableau s\'ouvre, avec ses trois colonnes de base et sans « Nouvelle colonne »',
        $c1 . ' · ' . $oui(substr_count($page1, 'class="kanban__colonne"') === 3 && !str_contains($page1, '+ Nouvelle colonne') && !str_contains($page1, 'travaux-kanban--defile')), '200 · oui');
    $dire('« Colonnes du tableau » répond 404, changer de statut marche toujours, le PDF aussi', $c2 . ' · ' . $statutSansMigration . ' · ' . $c3, '404 · fait · 200');

    echo "\n9. Les quatre langues\n";
    $attendus = [
        'en' => ['Board columns', '+ New column', 'Column name', 'Add the column', 'between “To do” and “Done”', 'Delete the column', 'Place after', 'Icon'],
        'es' => ['Columnas del tablero', '+ Nueva columna', 'Nombre de la columna', 'Añadir la columna', 'entre «Por hacer» y «Hecho»', 'Eliminar la columna', 'Colocar después de', 'Icono'],
        'de' => ['Spalten des Bretts', '+ Neue Spalte', 'Name der Spalte', 'Spalte hinzufügen', 'zwischen „Zu tun“ und „Erledigt“', 'Spalte löschen', 'Einfügen nach', 'Symbol'],
    ];
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => $langue]);
        [, $pageLangue] = $appel('a', 'travaux/' . $p . '/colonnes');
        [, $tableauLangue] = $appel('a', 'travaux/' . $p);
        [, $refus] = $appel('a', 'travaux/' . $p . '/colonnes', ['_csrf' => $csrf('a', 'travaux/' . $p . '/colonnes'), 'nom' => ' ']);
        $manque = array_values(array_filter([$mots[0], $mots[2], $mots[3], $mots[4], $mots[5], $mots[6], $mots[7]], static fn (string $m): bool => !str_contains($pageLangue, $m)));
        $dire($langue . ' : la page des colonnes et le bouton du tableau', ($manque === [] ? 'oui' : 'manque : ' . implode(' | ', $manque)) . ' · ' . $oui(str_contains($tableauLangue, $mots[1])), 'oui · oui');
        $dire($langue . ' : ni clé brute ni français resté (page, tableau, refus)',
            $oui(!preg_match('/\btr\.c[ol]\.[a-z_.]+/', $pageLangue . $tableauLangue . $refus) && !str_contains($pageLangue, 'Nom de la colonne') && !str_contains($tableauLangue, '+ Nouvelle colonne') && !str_contains($refus, 'Donnez un nom') && str_contains($refus, 'flash--erreur')), 'oui');
    }
    $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => 'fr']);

    echo "\n10. Les clés de langue\n";
    [, $cours] = $appel('a', 'travaux/' . $p . '/cours');
    $dire('l\'onglet « Cours » garde son aide (les clés « tr.co.* » sont les siennes)', $oui(str_contains($cours, 'Lie un de tes cours ou de tes dossiers') && !str_contains($cours, 'Placez-les où vous voulez') && !str_contains($cours, 'Colonnes du tableau')), 'oui');
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        preg_match_all("/^    '([^']+)' =>/m", (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php'), $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : aucune clé écrite deux fois (la dernière écraserait la première en silence)', $doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5)), 'aucune');
    }
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('RENAME TABLE projet_colonnes_essai TO projet_colonnes'); } catch (Throwable) {}
    }
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach (glob(__DIR__ . '/ck_tcol_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tcol-_@exemple-test.fr'")
        . ' · colonnes restantes ' . bd_valeur("SELECT COUNT(*) FROM projet_colonnes WHERE nom IN ('Ailleurs', 'documentation', 'Rédaction')") . "\n";
}

/** Le plafond de colonnes, lu dans le code de l'application (le script d'essai ne charge rien d'elle). */
function Travaux_essai_colonnes_max(): int
{
    return preg_match('/COLONNES_MAX = (\d+)/', (string) file_get_contents(dirname(__DIR__, 2) . '/src/Travaux.php'), $m) ? (int) $m[1] : 0;
}
