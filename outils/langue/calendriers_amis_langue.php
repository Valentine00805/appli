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
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
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
