<?php
/**
 * Les calendriers partagés entre amis, de bout en bout (HTTP + base) : création avec les amis choisis (jamais un étranger),
 * le volet du calendrier, les évènements de chacun, qui voit quoi, qui peut modifier quoi, la case « afficher » et la couleur
 * propres à chaque membre, les retraits, le départ, la suppression, et les quatre langues.
 *
 * Quatre comptes d'essai : A (créateur), B (ami de A, membre), C (ami de A, jamais ajouté), D (étranger).
 *
 * La base locale est désignée par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin) : l'essai ne
 * dépend pas du fichier de réglages de l'installation.
 */
require __DIR__ . '/base.php';

// Les dates de l'essai sont celles de l'application (le fuseau des comptes neufs), pas celles du poste en ligne de commande : après minuit à Paris,
// ce poste (en UTC) est encore la veille, et « aujourd'hui » ne serait plus le même des deux côtés.
date_default_timezone_set('Europe/Paris');

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$sauvegarde = $fichierEssai . '.avant-essai';
if (is_file($fichierEssai)) { rename($fichierEssai, $sauvegarde); }
file_put_contents($fichierEssai, '<?php return ' . var_export([
    'db' => ['host' => '127.0.0.1', 'name' => 'mon_appli_cours', 'user' => 'root', 'pass' => ''],
], true) . ';');
sleep(3);   // le serveur web garde le fichier compilé quelques secondes (OPcache)

$emails = ['cam-a@exemple-test.fr', 'cam-b@exemple-test.fr', 'cam-c@exemple-test.fr', 'cam-d@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) {
        // Les travaux de groupe d'abord : leur calendrier commun part avec eux.
        bd_run('DELETE p FROM projets p JOIN users u ON u.id = p.cree_par WHERE u.email = ? AND u.email LIKE ?', [$e, '%@exemple-test.fr']);
        bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
    }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Cal_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai calendriers']);
}
[$idA, $idB, $idC, $idD] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
foreach ([[$idA, $idB], [$idA, $idC]] as [$x, $y]) {
    bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
        [$x, $y, min($x, $y), max($x, $y)]);
}

$cookies = array_combine($emails, array_map(static fn (string $c): string => __DIR__ . '/ck_cam_' . $c . '.txt', ['a', 'b', 'c', 'd']));
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} corps, adresse finale, code */
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

$calendrier = static fn (): int => (int) bd_valeur('SELECT id FROM calendriers_amis WHERE proprietaire_id = ? ORDER BY id DESC LIMIT 1', [$idA]);
$membres = static fn (int $c): array => array_map('intval', array_column(bd_all('SELECT user_id FROM calendrier_amis_membres WHERE calendrier_id = ? ORDER BY user_id', [$c]), 'user_id'));
$nbEvenements = static fn (int $c): int => (int) bd_valeur('SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ?', [$c]);
$aujourdhui = date('Y-m-d');

try {
    [$a, $b, $c, $d] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    $csrf = [];
    foreach ($emails as $e) { [$p] = $appel($e, 'calendrier'); $csrf[$e] = $jeton($p); }
    $poster = static fn (string $compte, string $chemin, array $champs = []): array => $appel($compte, $chemin, ['_csrf' => $csrf[$compte]] + $champs);

    echo "\n1. Le volet du calendrier\n";
    [$page] = $appel($a, 'calendrier');
    $dire('le volet existe même sans agenda relié, avec sa section « Calendriers partagés »', $oui(str_contains($page, 'cal-volet') && str_contains($page, 'Calendriers partagés')), 'oui');
    $dire('et le lien pour en créer un, ouvert en fenêtre', $oui(str_contains($page, 'href="' . 'http://localhost/mon_appli/appli/calendriers-amis/nouveau"') || str_contains($page, 'calendriers-amis/nouveau" data-fenetre')), 'oui');

    echo "\n2. Créer un calendrier\n";
    [$fenetre] = $appel($a, 'calendriers-amis/nouveau?fenetre=1');
    $dire('le formulaire propose un nom, une couleur et mes amis (A, pas D)', $oui(str_contains($fenetre, 'name="nom"') && str_contains($fenetre, 'name="couleur"')
        && str_contains($fenetre, 'value="' . $idB . '"') && str_contains($fenetre, 'value="' . $idC . '"') && !str_contains($fenetre, 'value="' . $idD . '"')), 'oui');
    $poster($a, 'calendriers-amis', ['nom' => '   ']);
    $dire('un nom vide est refusé', (string) bd_valeur('SELECT COUNT(*) FROM calendriers_amis WHERE proprietaire_id = ?', [$idA]), '0');
    $poster($a, 'calendriers-amis', ['nom' => str_repeat('x', 81)]);
    $dire('un nom trop long est refusé', (string) bd_valeur('SELECT COUNT(*) FROM calendriers_amis WHERE proprietaire_id = ?', [$idA]), '0');
    // D n'est pas ami de A : envoyé à la main, il n'entre pas.
    $poster($a, 'calendriers-amis', ['nom' => 'Vacances à trois', 'couleur' => '#3ba55d', 'amis' => [$idB, $idD]]);
    $cal = $calendrier();
    $dire('le calendrier est créé', $oui($cal > 0), 'oui');
    $dire('ses membres : le créateur et l\'ami choisi — pas l\'étranger, pas l\'ami non choisi', implode(',', $membres($cal)), implode(',', [$idA, $idB]));
    $dire('sa couleur est enregistrée', (string) bd_valeur('SELECT couleur FROM calendriers_amis WHERE id = ?', [$cal]), '#3ba55d');

    echo "\n3. Qui le voit\n";
    foreach ([[$a, true], [$b, true], [$c, false], [$d, false]] as [$compte, $voit]) {
        [$page] = $appel($compte, 'calendrier');
        $dire('le volet de ' . substr($compte, 4, 1) . ' ' . ($voit ? 'liste' : 'ne liste pas') . ' « Vacances à trois »', $oui(str_contains($page, 'Vacances à trois')), $oui($voit));
    }
    [$reglages] = $appel($b, 'calendriers-amis/' . $cal . '?fenetre=1');
    $dire('un membre ouvre les réglages du calendrier (sans le formulaire du créateur)', $oui(str_contains($reglages, 'Vacances à trois') && !str_contains($reglages, '/modifier') && str_contains($reglages, '/quitter')), 'oui');
    [$reglagesA] = $appel($a, 'calendriers-amis/' . $cal . '?fenetre=1');
    $dire('le créateur y trouve le formulaire, l\'ajout d\'amis et la suppression', $oui(str_contains($reglagesA, '/modifier') && str_contains($reglagesA, '/membres') && str_contains($reglagesA, '/supprimer') && str_contains($reglagesA, 'value="' . $idC . '"')), 'oui');
    [$refus, $adresse] = $appel($c, 'calendriers-amis/' . $cal . '?fenetre=1');
    $dire('un ami non ajouté est renvoyé, sans rien lire du calendrier', $oui(!str_contains($refus, 'Vacances à trois') || str_contains($adresse, 'calendriers-amis') && !str_contains($adresse, '/' . $cal)), 'oui');
    [$refus] = $appel($d, 'calendriers-amis/' . $cal . '?fenetre=1');
    $dire('un étranger non plus', $oui(!str_contains($refus, 'Vacances à trois') && !str_contains($refus, 'calendriers-amis/' . $cal . '/')), 'oui');

    echo "\n4. Les évènements\n";
    [$formulaire] = $appel($b, 'calendriers-amis/' . $cal . '/evenements/nouveau?fenetre=1');
    $dire('le formulaire d\'un évènement (titre, dates, heures, lieu, notes)', $oui(str_contains($formulaire, 'name="titre"') && str_contains($formulaire, 'name="date_debut"') && str_contains($formulaire, 'name="heure_debut"') && str_contains($formulaire, 'name="description"')), 'oui');
    $poster($b, 'calendriers-amis/' . $cal . '/evenements', ['titre' => '', 'date_debut' => $aujourdhui]);
    $dire('un titre vide est refusé', (string) $nbEvenements($cal), '0');
    $poster($b, 'calendriers-amis/' . $cal . '/evenements', ['titre' => 'Fin avant début', 'date_debut' => $aujourdhui, 'heure_debut' => '15:00', 'heure_fin' => '14:00']);
    $dire('une fin avant le début est refusée', (string) $nbEvenements($cal), '0');
    $poster($b, 'calendriers-amis/' . $cal . '/evenements', ['titre' => 'Billets Lisbonne-B', 'date_debut' => $aujourdhui, 'heure_debut' => '10:00', 'heure_fin' => '11:30', 'lieu' => 'Gare', 'description' => 'Réserver']);
    $evB = (int) bd_valeur('SELECT id FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$cal, 'Billets Lisbonne-B']);
    $dire('B ajoute un évènement', $oui($evB > 0), 'oui');
    $dire('il est à son nom, avec ses heures', (string) bd_valeur('SELECT CONCAT(auteur_id, \'|\', TIME(debut), \'|\', TIME(fin)) FROM calendrier_amis_evenements WHERE id = ?', [$evB]), $idB . '|10:00:00|11:30:00');
    $poster($a, 'calendriers-amis/' . $cal . '/evenements', ['titre' => 'Hôtel Lisbonne-A', 'date_debut' => $aujourdhui, 'journee_entiere' => '1']);
    $evA = (int) bd_valeur('SELECT id FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$cal, 'Hôtel Lisbonne-A']);
    $dire('A ajoute le sien, sur la journée entière', (string) bd_valeur('SELECT CONCAT(journee_entiere, \'|\', TIME(debut), \'|\', TIME(fin)) FROM calendrier_amis_evenements WHERE id = ?', [$evA]), '1|00:00:00|23:59:59');
    foreach ([[$a, true], [$b, true], [$c, false], [$d, false]] as [$compte, $voit]) {
        [$page] = $appel($compte, 'calendrier?vue=liste&date=' . $aujourdhui);
        $dire('le calendrier de ' . substr($compte, 4, 1) . ($voit ? ' montre' : ' ne montre pas') . ' les deux évènements',
            $oui(str_contains($page, 'Billets Lisbonne-B') && str_contains($page, 'Hôtel Lisbonne-A')), $oui($voit));
    }
    [$accueil] = $appel($b, '');
    $dire('l\'accueil de B les montre aussi (ce qui est prévu aujourd\'hui)', $oui(str_contains($accueil, 'Billets Lisbonne-B')), 'oui');
    [$fiche] = $appel($a, 'calendriers-amis/evenements/' . $evB . '?fenetre=1');
    $dire('la fiche dit le lieu, les notes et qui l\'a ajouté (A le lit, B l\'a écrit)', $oui(str_contains($fiche, 'Gare') && str_contains($fiche, 'Réserver') && str_contains($fiche, 'Cal_1')), 'oui');
    [$fiche] = $appel($c, 'calendriers-amis/evenements/' . $evB . '?fenetre=1');
    $dire('un non-membre ne lit pas la fiche', $oui(!str_contains($fiche, 'Gare')), 'oui');

    echo "\n5. Qui peut modifier quoi\n";
    $poster($b, 'calendriers-amis/evenements/' . $evA, ['titre' => 'Piraté', 'date_debut' => $aujourdhui, 'heure_debut' => '09:00', 'heure_fin' => '10:00']);
    $dire('B ne peut pas modifier l\'évènement de A', (string) bd_valeur('SELECT titre FROM calendrier_amis_evenements WHERE id = ?', [$evA]), 'Hôtel Lisbonne-A');
    $poster($b, 'calendriers-amis/evenements/' . $evA . '/supprimer');
    $dire('ni le supprimer', (string) $nbEvenements($cal), '2');
    [$fiche] = $appel($b, 'calendriers-amis/evenements/' . $evA . '?fenetre=1');
    $dire('sa fiche, pour B, n\'a ni « Modifier » ni « Supprimer »', $oui(!str_contains($fiche, '/modifier') && !str_contains($fiche, '/supprimer')), 'oui');
    $poster($b, 'calendriers-amis/evenements/' . $evB, ['titre' => 'Billets Lisbonne-B2', 'date_debut' => $aujourdhui, 'heure_debut' => '10:30', 'heure_fin' => '12:00']);
    $dire('B modifie le sien', (string) bd_valeur('SELECT CONCAT(titre, \'|\', TIME(debut)) FROM calendrier_amis_evenements WHERE id = ?', [$evB]), 'Billets Lisbonne-B2|10:30:00');
    $poster($a, 'calendriers-amis/evenements/' . $evB, ['titre' => 'Billets Lisbonne-B3', 'date_debut' => $aujourdhui, 'heure_debut' => '10:30', 'heure_fin' => '12:00']);
    $dire('A, qui gère le calendrier, modifie celui de B', (string) bd_valeur('SELECT titre FROM calendrier_amis_evenements WHERE id = ?', [$evB]), 'Billets Lisbonne-B3');
    $poster($c, 'calendriers-amis/evenements/' . $evB, ['titre' => 'Intrus', 'date_debut' => $aujourdhui]);
    $dire('C, qui n\'est pas membre, ne modifie rien', (string) bd_valeur('SELECT titre FROM calendrier_amis_evenements WHERE id = ?', [$evB]), 'Billets Lisbonne-B3');
    $poster($c, 'calendriers-amis/' . $cal . '/evenements', ['titre' => 'Intrus', 'date_debut' => $aujourdhui]);
    $dire('ni n\'y ajoute un évènement', (string) $nbEvenements($cal), '2');
    $poster($b, 'calendriers-amis/' . $cal . '/modifier', ['nom' => 'Renommé par B']);
    $dire('B ne renomme pas le calendrier', (string) bd_valeur('SELECT nom FROM calendriers_amis WHERE id = ?', [$cal]), 'Vacances à trois');
    $poster($b, 'calendriers-amis/' . $cal . '/membres', ['amis' => [$idC]]);
    $dire('B n\'ajoute personne', implode(',', $membres($cal)), implode(',', [$idA, $idB]));
    $poster($b, 'calendriers-amis/' . $cal . '/supprimer');
    $dire('B ne supprime pas le calendrier', (string) $oui($calendrier() === $cal), 'oui');

    echo "\n6. Afficher, masquer, colorier : chacun pour soi\n";
    $cle = 'ca' . $cal;
    $poster($b, 'calendrier/agendas', ['sources' => ['miens'], 'couleur' => [$cle => '#112233']]);
    $dire('B décoche le calendrier : sa ligne passe à « masqué »', (string) bd_valeur('SELECT affiche FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$cal, $idB]), '0');
    $dire('celle de A ne bouge pas', (string) bd_valeur('SELECT affiche FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$cal, $idA]), '1');
    $dire('B garde sa couleur à lui', (string) bd_valeur('SELECT couleur FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$cal, $idB]), '#112233');
    $dire('la couleur de A et celle du calendrier ne changent pas', (string) bd_valeur('SELECT CONCAT(c.couleur, \'|\', COALESCE(m.couleur, \'-\')) FROM calendriers_amis c JOIN calendrier_amis_membres m ON m.calendrier_id = c.id AND m.user_id = ? WHERE c.id = ?', [$idA, $cal]), '#3ba55d|-');
    [$page] = $appel($b, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('masqué, le calendrier n\'apparaît plus chez B (ni sur son accueil)', $oui(!str_contains($page, 'Billets Lisbonne-B3') && !str_contains($page, 'Hôtel Lisbonne-A')), 'oui');
    [$accueil] = $appel($b, '');
    $dire('l\'accueil de B ne les montre plus', $oui(!str_contains($accueil, 'Billets Lisbonne-B3')), 'oui');
    [$page] = $appel($a, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('chez A, ils y sont toujours', $oui(str_contains($page, 'Billets Lisbonne-B3') && str_contains($page, 'Hôtel Lisbonne-A')), 'oui');
    $poster($b, 'calendrier/agendas', ['sources' => ['miens', $cle]]);
    [$page] = $appel($b, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('B recoche : tout revient', $oui(str_contains($page, 'Billets Lisbonne-B3') && str_contains($page, 'Hôtel Lisbonne-A')), 'oui');
    $poster($b, 'calendrier/agendas', ['sources' => ['miens', $cle], 'couleur' => [$cle => '#3ba55d']]);
    $dire('choisir de nouveau la couleur du créateur retire la couleur personnelle', (string) bd_valeur('SELECT COALESCE(couleur, \'-\') FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$cal, $idB]), '-');
    [$page] = $appel($b, 'calendrier?vue=liste&date=' . $aujourdhui . '&matiere=999999');
    $dire('un filtre par matière écarte ces évènements, qui n\'ont pas de matière', $oui(!str_contains($page, 'Billets Lisbonne-B3')), 'oui');

    echo "\n7. Membres : ajouter, retirer, quitter\n";
    $poster($a, 'calendriers-amis/' . $cal . '/membres', ['amis' => [$idC, $idD]]);
    $dire('A ajoute C (un ami) ; D, l\'étranger, reste dehors', implode(',', $membres($cal)), implode(',', [$idA, $idB, $idC]));
    [$page] = $appel($c, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('C voit maintenant les évènements', $oui(str_contains($page, 'Billets Lisbonne-B3')), 'oui');
    $poster($a, 'calendriers-amis/' . $cal . '/membres/' . $idA . '/retirer');
    $dire('le créateur ne se retire pas lui-même', (string) count($membres($cal)), '3');
    $poster($c, 'calendriers-amis/' . $cal . '/membres/' . $idB . '/retirer');
    $dire('un membre n\'en retire pas un autre', (string) count($membres($cal)), '3');
    $poster($a, 'calendriers-amis/' . $cal . '/membres/' . $idC . '/retirer');
    $dire('A retire C', implode(',', $membres($cal)), implode(',', [$idA, $idB]));
    [$page] = $appel($c, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('C ne voit plus rien', $oui(!str_contains($page, 'Billets Lisbonne-B3')), 'oui');
    $poster($a, 'calendriers-amis/' . $cal . '/quitter');
    $dire('le créateur ne quitte pas son calendrier', (string) count($membres($cal)), '2');
    $poster($b, 'calendriers-amis/' . $cal . '/quitter');
    $dire('B quitte le calendrier', implode(',', $membres($cal)), (string) $idA);
    $dire('ses évènements restent au calendrier', (string) $nbEvenements($cal), '2');
    [$page] = $appel($b, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('et il n\'apparaît plus chez B', $oui(!str_contains($page, 'Billets Lisbonne-B3') && !str_contains($page, 'Vacances à trois')), 'oui');

    echo "\n8. Supprimer\n";
    $poster($a, 'calendriers-amis/' . $cal . '/membres', ['amis' => [$idB]]);
    $poster($a, 'calendriers-amis/evenements/' . $evB . '/supprimer');
    $dire('A supprime l\'évènement de B', (string) $nbEvenements($cal), '1');
    $poster($a, 'calendriers-amis/' . $cal . '/supprimer');
    $dire('A supprime le calendrier', (string) bd_valeur('SELECT COUNT(*) FROM calendriers_amis WHERE id = ?', [$cal]), '0');
    $dire('ses membres et ses évènements partent avec lui', (string) bd_valeur('SELECT (SELECT COUNT(*) FROM calendrier_amis_membres WHERE calendrier_id = ?) + (SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ?)', [$cal, $cal]), '0');

    echo "\n8 bis. Le calendrier commun d'un travail de groupe\n";
    $poster($a, 'travaux', ['nom' => 'Rapport de labo', 'amis' => [$idB]]);
    $projet = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$idA]);
    $calP = static fn (): int => (int) bd_valeur('SELECT id FROM calendriers_amis WHERE projet_id = ?', [$projet]);
    [$membresProjet] = $appel($a, 'travaux/' . $projet . '/membres?fenetre=1');
    $dire('l\'onglet Membres propose de créer le calendrier commun', $oui(str_contains($membresProjet, 'travaux/' . $projet . '/calendrier')), 'oui');
    $poster($b, 'travaux/' . $projet . '/calendrier');
    $dire('un simple invité (pas encore membre) ne peut pas le créer', (string) $calP(), '0');
    $poster($a, 'travaux/' . $projet . '/calendrier');
    $dire('A, membre du projet, le crée', $oui($calP() > 0), 'oui');
    $dire('il porte le nom du projet, et seuls les membres du projet y sont (B n\'a pas encore accepté)',
        (string) bd_valeur('SELECT nom FROM calendriers_amis WHERE projet_id = ?', [$projet]) . '|' . implode(',', $membres($calP())), 'Rapport de labo|' . $idA);
    $poster($a, 'travaux/' . $projet . '/calendrier');
    $dire('un projet n\'a qu\'un calendrier', (string) bd_valeur('SELECT COUNT(*) FROM calendriers_amis WHERE projet_id = ?', [$projet]), '1');
    $poster($b, 'travaux/' . $projet . '/rejoindre');
    $dire('B accepte l\'invitation : il entre dans le calendrier', implode(',', $membres($calP())), implode(',', [$idA, $idB]));
    [$membresProjet] = $appel($b, 'travaux/' . $projet . '/membres?fenetre=1');
    $dire('l\'onglet Membres le montre, avec ses boutons', $oui(str_contains($membresProjet, 'calendriers-amis/' . $calP()) && str_contains($membresProjet, 'evenements/nouveau?agenda=ca' . $calP()) && !str_contains($membresProjet, 'travaux/' . $projet . '/calendrier')), 'oui');
    $poster($b, 'calendriers-amis/' . $calP() . '/evenements', ['titre' => 'Séance de labo', 'date_debut' => $aujourdhui]);
    [$page] = $appel($a, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('un évènement de B est dans le calendrier de A', $oui(str_contains($page, 'Séance de labo')), 'oui');
    $poster($a, 'travaux/' . $projet . '/taches', ['titre' => 'Rédiger la conclusion', 'membre_id' => '', 'echeance' => $aujourdhui]);
    $poster($a, 'travaux/' . $projet . '/taches', ['titre' => 'Relire le plan', 'membre_id' => (string) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$projet, $idB]), 'echeance' => $aujourdhui]);
    $poster($a, 'travaux/' . $projet . '/taches', ['titre' => 'Sans date', 'membre_id' => '', 'echeance' => '']);
    foreach ([1, 2, 3] as $n) {
        $poster($a, 'travaux/' . $projet . '/taches', ['titre' => 'Extra ' . $n, 'membre_id' => '', 'echeance' => '']);
    }
    [$accueil] = $appel($a, '');
    $dire('l\'accueil montre quatre tâches de groupe, et déroule les suivantes sous « ＋ 1 autre tâche »',
        $oui(substr_count($accueil, 'travaux-mes-taches') === 2 && str_contains($accueil, 'travaux-suite') && str_contains($accueil, '＋ 1 autre tâche')), 'oui');
    foreach ([[$a, 'A'], [$b, 'B']] as [$compte, $qui]) {
        [$page] = $appel($compte, 'calendrier?vue=liste&date=' . $aujourdhui);
        $dire("les tâches du projet qui ont une échéance sont dans le calendrier de $qui, une seule fois",
            $oui(substr_count($page, 'Relire le plan') === 1 && str_contains($page, 'Rédiger la conclusion (sans personne)') && substr_count($page, 'Rédiger la conclusion') === 1 && !str_contains($page, 'Sans date')), 'oui');
    }
    [$accueil] = $appel($b, '');
    $dire('la tâche confiée à B (sans être la sienne pour A) est aussi sur l\'accueil de B', $oui(str_contains($accueil, 'Relire le plan')), 'oui');
    [$page] = $appel($c, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('un non-membre ne les voit pas', $oui(!str_contains($page, 'Relire le plan')), 'oui');
    $poster($b, 'calendrier/agendas', ['sources' => ['miens']]);
    [$page] = $appel($b, 'calendrier?vue=liste&date=' . $aujourdhui);
    $dire('B masque le calendrier : les tâches disparaissent avec lui', $oui(!str_contains($page, 'Relire le plan') && !str_contains($page, 'Rédiger la conclusion')), 'oui');
    $poster($b, 'calendrier/agendas', ['sources' => ['miens', 'ca' . $calP()]]);
    $poster($b, 'calendriers-amis/' . $calP() . '/modifier', ['nom' => 'Renommé par B']);
    $dire('B, simple membre du projet, ne gère pas le calendrier', (string) bd_valeur('SELECT nom FROM calendriers_amis WHERE projet_id = ?', [$projet]), 'Rapport de labo');
    $poster($a, 'calendriers-amis/' . $calP() . '/modifier', ['nom' => 'Labo — agenda', 'couleur' => '#14b8a6']);
    $dire('A, administrateur du projet, le renomme', (string) bd_valeur('SELECT nom FROM calendriers_amis WHERE projet_id = ?', [$projet]), 'Labo — agenda');
    $poster($a, 'calendriers-amis/' . $calP() . '/membres', ['amis' => [$idC]]);
    $dire('on n\'y ajoute personne à la main : les membres sont ceux du projet', implode(',', $membres($calP())), implode(',', [$idA, $idB]));
    $poster($a, 'calendriers-amis/' . $calP() . '/membres/' . $idB . '/retirer');
    $dire('on n\'y retire personne non plus', implode(',', $membres($calP())), implode(',', [$idA, $idB]));
    $poster($b, 'calendriers-amis/' . $calP() . '/quitter');
    $dire('ni ne le quitte sans quitter le projet', implode(',', $membres($calP())), implode(',', [$idA, $idB]));
    [$reglages] = $appel($a, 'calendriers-amis/' . $calP() . '?fenetre=1');
    $dire('ses réglages renvoient au projet, sans ajout d\'amis ni départ', $oui(str_contains($reglages, 'travaux/' . $projet . '/membres') && !str_contains($reglages, 'name="amis[]"') && !str_contains($reglages, '/quitter')), 'oui');
    echo "\n8 ter. Les documents du projet liés à un évènement\n";
    $evLabo = (int) bd_valeur('SELECT id FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$calP(), 'Séance de labo']);
    // Un évènement qui n'est pas encore passé, quelle que soit l'heure de l'essai : l'onglet Échéances ne montre que ce qui vient.
    bd_run('UPDATE calendrier_amis_evenements SET fin = ? WHERE id = ?', [date('Y-m-d', strtotime('+1 day')) . ' 23:00:00', $evLabo]);
    bd_run("INSERT INTO cours (user_id, titre) VALUES (?, 'Cours de chimie')", [$idA]);
    $coursProjet = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Cours de chimie']);
    bd_run("INSERT INTO cours (user_id, titre) VALUES (?, 'Cours hors projet')", [$idA]);
    $coursHors = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Cours hors projet']);
    $poster($a, 'travaux/' . $projet . '/liens', ['lien' => 'cours:' . $coursProjet]);
    bd_run("INSERT INTO projet_fichiers (projet_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 'protocole.pdf', 'xx-essai', 'application/pdf', 10)", [$projet, $idA]);
    $fichierProjet = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE projet_id = ?', [$projet]);
    bd_run("INSERT INTO projet_echeances (projet_id, titre, debut, fin, cree_par) VALUES (?, 'Rendu du compte-rendu', ?, ?, ?)", [$projet, $aujourdhui . ' 18:00:00', $aujourdhui . ' 19:00:00', $idA]);
    $echeance = (int) bd_valeur('SELECT id FROM projet_echeances WHERE projet_id = ?', [$projet]);
    $liens = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_evenement_liens WHERE projet_id = ?', [$projet]);

    [$fiche] = $appel($b, 'calendriers-amis/evenements/' . $evLabo . '?fenetre=1');
    $dire('la fiche d\'un évènement du calendrier commun propose de lier un document du projet (cours, fichier — pas un cours hors projet)',
        $oui(str_contains($fiche, 'value="cours:' . $coursProjet . '"') && str_contains($fiche, 'value="fichier:' . $fichierProjet . '"') && !str_contains($fiche, 'value="cours:' . $coursHors . '"')), 'oui');
    $poster($b, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'evenement', 'evenement_id' => $evLabo, 'lien' => 'cours:' . $coursProjet]);
    $poster($a, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'evenement', 'evenement_id' => $evLabo, 'lien' => 'fichier:' . $fichierProjet]);
    $dire('B lie le cours, A lie le fichier', (string) $liens(), '2');
    $poster($b, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'evenement', 'evenement_id' => $evLabo, 'lien' => 'cours:' . $coursProjet]);
    $dire('le même document ne se lie pas deux fois', (string) $liens(), '2');
    $poster($a, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'evenement', 'evenement_id' => $evLabo, 'lien' => 'cours:' . $coursHors]);
    $dire('un cours qui n\'est pas dans le projet ne se lie pas', (string) $liens(), '2');
    $poster($c, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'evenement', 'evenement_id' => $evLabo, 'lien' => 'fichier:' . $fichierProjet]);
    $dire('un non-membre du projet ne lie rien', (string) $liens(), '2');
    [$fiche] = $appel($a, 'calendriers-amis/evenements/' . $evLabo . '?fenetre=1');
    $dire('la fiche liste les deux documents liés', $oui(str_contains($fiche, 'Cours de chimie') && str_contains($fiche, 'protocole.pdf')), 'oui');

    [$ongletCours] = $appel($a, 'travaux/' . $projet . '/cours?fenetre=1');
    $dire('l\'onglet Cours dit à quel évènement le cours est lié', $oui(str_contains($ongletCours, 'Lié à') && str_contains($ongletCours, 'Séance de labo')), 'oui');
    [$ongletFichiers] = $appel($a, 'travaux/' . $projet . '/fichiers?fenetre=1');
    $dire('l\'onglet Fichiers, pour le fichier', $oui(str_contains($ongletFichiers, 'Lié à') && str_contains($ongletFichiers, 'Séance de labo')), 'oui');
    [$ongletEcheances] = $appel($a, 'travaux/' . $projet . '/echeances?fenetre=1');
    $dire('l\'onglet Échéances montre l\'évènement du calendrier commun, avec ses 2 documents', $oui(str_contains($ongletEcheances, 'Séance de labo') && str_contains($ongletEcheances, '2 documents')), 'oui');

    $poster($a, 'travaux/' . $projet . '/evenements-liens', ['evenement_type' => 'echeance', 'evenement_id' => $echeance, 'lien' => 'fichier:' . $fichierProjet]);
    [$pageEcheance] = $appel($b, 'travaux/echeances/' . $echeance . '/modifier?fenetre=1');
    $dire('une échéance du projet se lie aussi, et sa page liste le fichier', $oui(str_contains($pageEcheance, 'protocole.pdf') && $liens() === 3), 'oui');
    [$ongletFichiers] = $appel($a, 'travaux/' . $projet . '/fichiers?fenetre=1');
    $dire('le fichier dit alors ses deux évènements', $oui(str_contains($ongletFichiers, 'Séance de labo') && str_contains($ongletFichiers, 'Rendu du compte-rendu')), 'oui');

    // Écrire dans le calendrier commun depuis « ＋ » : on ne choisit que les documents du projet.
    [$formulaire] = $appel($a, 'evenements/nouveau?fenetre=1&agenda=ca' . $calP());
    $dire('le formulaire du « ＋ » du calendrier d\'un projet ne propose que les documents du projet (pas mes autres cours)',
        $oui(str_contains($formulaire, 'value="cours:' . $coursProjet . '"') && str_contains($formulaire, 'value="fichier:' . $fichierProjet . '"')
            && !str_contains($formulaire, ':' . $coursHors . '"') && !str_contains($formulaire, 'name="cours_id"')), 'oui');
    $poster($a, 'evenements/nouveau', ['titre' => 'Réunion de rendu', 'date_debut' => date('Y-m-d', strtotime('+2 day')), 'heure_debut' => '10:00', 'heure_fin' => '11:00',
        'agendas' => ['ca' . $calP()], 'documents' => ['cours:' . $coursProjet, 'cours:' . $coursHors, 'fichier:' . $fichierProjet]]);
    $evRendu = (int) bd_valeur('SELECT id FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$calP(), 'Réunion de rendu']);
    $dire('créé ainsi, l\'évènement est lié aux documents cochés du projet — pas à un cours hors projet glissé à la main',
        implode(',', array_column(bd_all("SELECT CONCAT(cible_type, ':', cible_id) AS c FROM projet_evenement_liens WHERE projet_id = ? AND evenement_type = 'evenement' AND evenement_id = ? ORDER BY id", [$projet, $evRendu]), 'c')),
        'cours:' . $coursProjet . ',fichier:' . $fichierProjet);
    $poster($a, 'calendriers-amis/evenements/' . $evRendu . '/supprimer');

    // « Partager » un cours : le mettre dans le projet, ou l'en retirer.
    [$partage] = $appel($a, 'partager/cours/' . $coursHors . '?fenetre=1');
    $dire('la fenêtre « Partager » d\'un cours propose le projet de groupe', $oui(str_contains($partage, 'Dans un projet de groupe') && str_contains($partage, 'value="' . $projet . '"')), 'oui');
    $poster($a, 'partager/cours/' . $coursHors . '/projets', ['projets' => [$projet]]);
    $dire('le cours est dans le projet', (string) bd_valeur("SELECT COUNT(*) FROM projet_liens WHERE projet_id = ? AND type = 'cours' AND cible_id = ?", [$projet, $coursHors]), '1');
    [$partage] = $appel($a, 'partager/cours/' . $coursHors . '?fenetre=1');
    $dire('la fenêtre dit qu\'il y est déjà, avec de quoi l\'en retirer', $oui(str_contains($partage, 'Déjà dans') && str_contains($partage, '/projets/') && !str_contains($partage, 'name="projets[]" value="' . $projet . '"')), 'oui');
    [$ongletCours] = $appel($b, 'travaux/' . $projet . '/cours?fenetre=1');
    $dire('B, membre du projet, le voit dans l\'onglet Cours', $oui(str_contains($ongletCours, 'Cours hors projet')), 'oui');
    // Lié à un projet, le cours se modifie par tout le groupe — sur l'original, que chacun voit changer.
    $contenuDe = static fn (): string => (string) bd_valeur('SELECT COALESCE(contenu, \'\') FROM cours WHERE id = ?', [$coursHors]);
    $poster($b, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'Écrit par B dans le projet']);
    $dire('B, membre du projet, modifie le cours de A (l\'original)', $contenuDe(), 'Écrit par B dans le projet');
    [$lu] = $appel($b, 'partages/cours/' . $coursHors . '?fenetre=1');
    $dire('sa page dit que tout le groupe peut le modifier ici', $oui(str_contains($lu, 'tout le groupe peut le modifier ici') && str_contains($lu, 'name="contenu"')), 'oui');
    $poster($c, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'Écrit par un intrus']);
    $dire('un compte hors du projet ne le modifie pas', $contenuDe(), 'Écrit par B dans le projet');
    // Deux personnes écrivent en même temps : la seconde à enregistrer n'écrase pas la première.
    $baseLue = static function (string $html): string { return preg_match('/name="base" value="([0-9a-f]{32})"/', $html, $m) === 1 ? $m[1] : ''; };
    [$luParB] = $appel($b, 'partages/cours/' . $coursHors . '?fenetre=1');
    $baseB = $baseLue($luParB);
    $dire('l\'éditeur d\'un cours partagé porte l\'empreinte du texte lu', $oui($baseB === md5($contenuDe())), 'oui');
    $poster($a, 'cours/' . $coursHors . '/contenu', ['contenu' => 'Version de A']);
    $dire('A (propriétaire, sans empreinte : ancien formulaire) enregistre', $contenuDe(), 'Version de A');
    // L'envoi suit la redirection : la réponse EST la page rouverte, avec le message et le texte gardé (montrés une seule fois).
    [$luParB] = $poster($b, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'Version de B', 'base' => $baseB]);
    $dire('B enregistre avec l\'empreinte d\'avant la modification de A : rien n\'est écrit', $contenuDe(), 'Version de A');
    $dire('sa page dit pourquoi, montre le texte actuel et SA version gardée dessous', $oui(str_contains($luParB, 'pendant que vous l’éditiez') && str_contains($luParB, 'Votre version, non enregistrée') && str_contains($luParB, 'Version de B') && str_contains($luParB, 'Version de A')), 'oui');
    [$luParB] = $appel($b, 'partages/cours/' . $coursHors . '?fenetre=1');
    $dire('et ne la montre qu\'une fois', $oui(!str_contains($luParB, 'Votre version, non enregistrée')), 'oui');
    $poster($b, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'Version de B, reprise', 'base' => $baseLue($luParB)]);
    $dire('repris avec l\'empreinte à jour, B enregistre', $contenuDe(), 'Version de B, reprise');
    // Dans l'autre sens : le propriétaire écrit après qu'un ami a enregistré.
    [$pageA] = $appel($a, 'cours/' . $coursHors . '?fenetre=1');
    $baseA = $baseLue($pageA);
    $dire('la page du propriétaire porte, elle aussi, l\'empreinte', $oui($baseA === md5($contenuDe())), 'oui');
    $poster($b, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'B change encore', 'base' => $baseA]);
    [$pageA] = $poster($a, 'cours/' . $coursHors . '/contenu', ['contenu' => 'A écrase ?', 'base' => $baseA]);
    $dire('A, qui avait lu avant le dernier changement de B, ne l\'écrase pas', $contenuDe(), 'B change encore');
    $dire('sa page garde ce qu\'il avait tapé, à côté du texte actuel', $oui(str_contains($pageA, 'Votre version, non enregistrée') && str_contains($pageA, 'A écrase ?') && str_contains($pageA, 'B change encore')), 'oui');
    $poster($a, 'cours/' . $coursHors . '/contenu', ['contenu' => 'Écrit par B dans le projet']);

    // La fiche de révision partagée en modification suit la même règle.
    bd_run("INSERT INTO partages_amis (proprietaire_id, destinataire_id, cible_type, cible_id, droit) VALUES (?, ?, 'fiche', ?, 'modification')", [$idA, $idB, $coursHors]);
    $ficheDe = static fn (): string => (string) bd_valeur('SELECT COALESCE(fiche_revision, \'\') FROM cours WHERE id = ?', [$coursHors]);
    $poster($a, 'cours/' . $coursHors . '/revision', ['fiche_revision' => 'Fiche de A']);
    [$ficheB] = $appel($b, 'partages/fiches/' . $coursHors . '?fenetre=1');
    $baseFiche = $baseLue($ficheB);
    $poster($a, 'cours/' . $coursHors . '/revision', ['fiche_revision' => 'Fiche de A, mise à jour']);
    [$ficheB] = $poster($b, 'partages/fiches/' . $coursHors . '/contenu', ['contenu' => 'Fiche de B', 'base' => $baseFiche]);
    $dire('fiche : B, qui avait lu avant la mise à jour de A, ne l\'écrase pas, et retrouve ce qu\'il avait tapé', $ficheDe() . '|' . $oui(str_contains($ficheB, 'Votre version, non enregistrée') && str_contains($ficheB, 'Fiche de B')), 'Fiche de A, mise à jour|oui');
    [$pageFiche] = $appel($a, 'revision/' . $coursHors . '?fenetre=1');
    $baseFicheA = $baseLue($pageFiche);
    $poster($b, 'partages/fiches/' . $coursHors . '/contenu', ['contenu' => 'Fiche de B v2', 'base' => $baseLue($ficheB)]);
    [$pageFiche] = $poster($a, 'cours/' . $coursHors . '/revision', ['fiche_revision' => 'Fiche de A écrase ?', 'base' => $baseFicheA]);
    $dire('fiche : le propriétaire, lui non plus, n\'écrase pas le texte d\'un ami, et garde le sien à côté', $ficheDe() . '|' . $oui(str_contains($pageFiche, 'Votre version, non enregistrée') && str_contains($pageFiche, 'Fiche de A écrase ?')), 'Fiche de B v2|oui');
    bd_run("DELETE FROM partages_amis WHERE cible_type = 'fiche' AND cible_id = ?", [$coursHors]);

    $poster($c, 'partager/cours/' . $coursHors . '/projets', ['projets' => [$projet]]);
    $dire('un autre compte ne partage pas le cours de A', (string) bd_valeur("SELECT COUNT(*) FROM projet_liens WHERE projet_id = ? AND type = 'cours'", [$projet]), '2');
    $lienCours = (int) bd_valeur("SELECT id FROM projet_liens WHERE projet_id = ? AND type = 'cours' AND cible_id = ?", [$projet, $coursHors]);
    $poster($a, 'partager/cours/' . $coursProjet . '/projets/' . $lienCours . '/retirer');
    $dire('le numéro d\'un lien d\'un autre cours ne retire rien', (string) bd_valeur("SELECT COUNT(*) FROM projet_liens WHERE projet_id = ? AND type = 'cours'", [$projet]), '2');
    $poster($a, 'partager/cours/' . $coursHors . '/projets/' . $lienCours . '/retirer');
    $dire('A retire le cours du projet', (string) bd_valeur("SELECT COUNT(*) FROM projet_liens WHERE projet_id = ? AND type = 'cours' AND cible_id = ?", [$projet, $coursHors]), '0');
    $poster($b, 'partages/cours/' . $coursHors . '/contenu', ['contenu' => 'Encore B, hors projet']);
    $dire('retiré du projet, B ne peut plus le modifier', $contenuDe(), 'Écrit par B dans le projet');

    $lienA = (int) bd_valeur("SELECT id FROM projet_evenement_liens WHERE projet_id = ? AND evenement_type = 'evenement' AND cible_type = 'fichier'", [$projet]);
    $poster($b, 'travaux/evenements-liens/' . $lienA . '/supprimer');
    $dire('B (ni l\'auteur du lien ni administrateur) ne le retire pas', (string) $liens(), '3');
    $poster($a, 'travaux/evenements-liens/' . $lienA . '/supprimer');
    $dire('A le retire', (string) $liens(), '2');
    $poster($a, 'calendriers-amis/evenements/' . $evLabo . '/supprimer');
    $dire('supprimer l\'évènement efface aussi ses liens', (string) bd_valeur("SELECT COUNT(*) FROM projet_evenement_liens WHERE projet_id = ? AND evenement_type = 'evenement' AND evenement_id = ?", [$projet, $evLabo]), '0');
    $nbEvenements = static fn (int $c): int => (int) bd_valeur('SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ?', [$c]);
    $poster($b, 'calendriers-amis/' . $calP() . '/evenements', ['titre' => 'Séance de labo', 'date_debut' => $aujourdhui]);

    $poster($a, 'travaux/' . $projet . '/inviter', ['amis' => [$idC]]);
    $poster($c, 'travaux/' . $projet . '/rejoindre');
    $dire('un nouveau membre du projet entre dans le calendrier', implode(',', $membres($calP())), implode(',', [$idA, $idB, $idC]));
    $membreC = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$projet, $idC]);
    $poster($a, 'travaux/membres/' . $membreC . '/retirer');
    $dire('retiré du projet, il sort du calendrier', implode(',', $membres($calP())), implode(',', [$idA, $idB]));
    $poster($a, 'travaux/' . $projet . '/quitter');
    $dire('le créateur quitte le projet : le calendrier reste, et passe à B', implode(',', $membres($calP())) . '|' . (int) bd_valeur('SELECT proprietaire_id FROM calendriers_amis WHERE projet_id = ?', [$projet]), $idB . '|' . $idB);
    $dire('ses évènements restent', (string) bd_valeur('SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ?', [$calP()]), '1');
    $poster($b, 'travaux/' . $projet . '/supprimer');
    $dire('le projet supprimé, son calendrier et ses évènements partent avec lui', (string) bd_valeur('SELECT (SELECT COUNT(*) FROM calendriers_amis WHERE projet_id = ?) + (SELECT COUNT(*) FROM calendrier_amis_evenements e JOIN calendriers_amis c ON c.id = e.calendrier_id WHERE c.projet_id = ?)', [$projet, $projet]), '0');

    echo "\n9. Quand un compte disparaît\n";
    $poster($a, 'calendriers-amis', ['nom' => 'Projet', 'amis' => [$idB]]);
    $cal2 = $calendrier();
    $poster($b, 'calendriers-amis/' . $cal2 . '/evenements', ['titre' => 'Rendu', 'date_debut' => $aujourdhui]);
    bd_run('DELETE FROM users WHERE id = ?', [$idB]);
    $dire('le compte de B supprimé, ses évènements restent, sans auteur', (string) bd_valeur('SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ? AND auteur_id IS NULL', [$cal2]), '1');
    [$fiche] = $appel($a, 'calendriers-amis/evenements/' . (int) bd_valeur('SELECT id FROM calendrier_amis_evenements WHERE calendrier_id = ?', [$cal2]) . '?fenetre=1');
    $dire('et A peut toujours les lire', $oui(str_contains($fiche, 'Rendu')), 'oui');

    echo "\n9 bis. Depuis le bouton « Évènement » du calendrier\n";
    $demain = date('Y-m-d', strtotime('+1 day'));
    $cle2 = 'ca' . $cal2;
    $perso = static fn (string $titre): int => (int) bd_valeur('SELECT COUNT(*) FROM evenements WHERE user_id = ? AND titre = ?', [$idA, $titre]);
    $partage = static fn (string $titre): int => (int) bd_valeur('SELECT COUNT(*) FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$cal2, $titre]);
    [$formulaire] = $appel($a, 'evenements/nouveau?fenetre=1');
    $dire('le formulaire propose le calendrier partagé parmi les agendas où envoyer', $oui(str_contains($formulaire, 'value="' . $cle2 . '"') && str_contains($formulaire, 'Projet')), 'oui');
    [$formulaire] = $appel($a, 'evenements/nouveau?fenetre=1&agenda=' . $cle2);
    $dire('ouvert depuis le « ＋ » du calendrier : le formulaire dit où va l\'évènement (Projet), sans « Mes évènements »', $oui(str_contains($formulaire, 'cam-pastille') && str_contains($formulaire, 'Projet') && !str_contains($formulaire, 'Mes évènements')), 'oui');
    $dire('… sans le choix des agendas : le calendrier partagé est imposé',
        $oui(!str_contains($formulaire, 'name="agendas[]" value=""') && preg_match('/type="hidden" name="agendas\[\]" value="' . $cle2 . '"/', $formulaire) === 1 && !str_contains($formulaire, 'type="checkbox" name="agendas[]"')), 'oui');
    [$page] = $appel($a, 'calendrier');
    $dire('le « ＋ » du volet mène à ce formulaire complet', $oui(str_contains($page, 'evenements/nouveau?agenda=' . $cle2)), 'oui');
    [$formulaire] = $appel($c, 'evenements/nouveau?fenetre=1&agenda=' . $cle2);
    $dire('un non-membre ne coche rien en passant l\'adresse à la main', $oui(!str_contains($formulaire, $cle2)), 'oui');
    [$formulaire] = $appel($c, 'evenements/nouveau?fenetre=1');
    $dire('un non-membre ne le voit pas dans le sien', $oui(!str_contains($formulaire, 'value="' . $cle2 . '"')), 'oui');
    $poster($a, 'evenements/nouveau', ['titre' => 'Seul partagé', 'date_debut' => $demain, 'heure_debut' => '10:00', 'heure_fin' => '11:00', 'lieu' => 'Salle 3', 'description' => 'Apporter les notes', 'agendas' => [$cle2]]);
    $dire('coché seul : l\'évènement est dans le calendrier partagé', (string) $partage('Seul partagé'), '1');
    $dire('… et pas dans « Mes évènements »', (string) $perso('Seul partagé'), '0');
    $dire('il garde ses heures, son lieu et ses notes en texte', (string) bd_valeur('SELECT CONCAT(TIME(debut), \'|\', lieu, \'|\', description, \'|\', auteur_id) FROM calendrier_amis_evenements WHERE calendrier_id = ? AND titre = ?', [$cal2, 'Seul partagé']), '10:00:00|Salle 3|Apporter les notes|' . $idA);
    $poster($a, 'evenements/nouveau', ['titre' => 'Les deux', 'date_debut' => $demain, 'heure_debut' => '12:00', 'heure_fin' => '13:00', 'agendas' => ['', $cle2]]);
    $dire('avec « Mes évènements » : dans les deux', $partage('Les deux') . '|' . $perso('Les deux'), '1|1');
    $poster($a, 'evenements/nouveau', ['titre' => 'Série partagée', 'date_debut' => $demain, 'heure_debut' => '14:00', 'heure_fin' => '15:00', 'repetition' => 'semaine', 'fin_type' => 'nombre', 'repeter_nombre' => '3', 'agendas' => [$cle2]]);
    $dire('une répétition : une occurrence par date dans le calendrier partagé, rien chez soi', $partage('Série partagée') . '|' . $perso('Série partagée'), '3|0');
    $poster($c, 'evenements/nouveau', ['titre' => 'Intrus partagé', 'date_debut' => $demain, 'heure_debut' => '10:00', 'heure_fin' => '11:00', 'agendas' => [$cle2]]);
    $dire('un non-membre ne peut pas écrire dans ce calendrier par ce chemin', (string) $partage('Intrus partagé'), '0');
    $dire('… son évènement tombe alors sur son propre calendrier', (string) bd_valeur('SELECT COUNT(*) FROM evenements WHERE user_id = ? AND titre = ?', [$idC, 'Intrus partagé']), '1');
    [$page] = $appel($a, 'calendrier?vue=liste&date=' . $demain);
    $dire('le calendrier de A montre l\'évènement partagé', $oui(str_contains($page, 'Seul partagé')), 'oui');

    echo "\n10. Les quatre langues\n";
    $csrfA = $csrf[$a];
    foreach ([
        'en' => ['Shared calendars', 'New calendar'],
        'es' => ['Calendarios compartidos', 'Nuevo calendario'],
        'de' => ['Geteilte Kalender', 'Neuer Kalender'],
        'fr' => ['Calendriers partagés', 'Nouveau calendrier'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $csrfA, 'langue' => $langue]);
        [$page] = $appel($a, 'calendrier');
        [$liste] = $appel($a, 'calendriers-amis?fenetre=1');
        [$nouveau] = $appel($a, 'calendriers-amis/nouveau?fenetre=1');
        [$reglages] = $appel($a, 'calendriers-amis/' . $cal2 . '?fenetre=1');
        [$formulaire] = $appel($a, 'calendriers-amis/' . $cal2 . '/evenements/nouveau?fenetre=1');
        $tout = $page . $liste . $nouveau . $reglages . $formulaire;
        $dire("$langue : les titres sont traduits, et aucune clé de traduction brute",
            $oui(str_contains($page, $mots[0]) && str_contains($liste, $mots[1]) && !preg_match('/\bcam\.[a-z0-9_.]+/', $tout)), 'oui');
    }
    $termine = true;
} finally {
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    foreach ($cookies as $f) { @unlink($f); }
    $nettoyer();
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · calendriers d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM calendriers_amis c LEFT JOIN users u ON u.id = c.proprietaire_id WHERE u.id IS NULL')
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
