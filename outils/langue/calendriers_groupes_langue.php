<?php
/**
 * Les groupes de calendriers du volet (HTTP + base) : créer un groupe (nom, calendriers choisis — jamais ceux des autres), le voir dans le volet (pliable,
 * avec son compteur), le modifier (renommer, ranger, sortir un calendrier), un calendrier dans un seul groupe à la fois, plier et déplier (retenu),
 * supprimer sans rien perdre, que chacun ne voit et ne touche que les siens, la page où l'on revient, le plafond, un site dont la migration n'est pas
 * passée, et les quatre langues.
 *
 * Trois comptes d'essai : A (crée les calendriers et les groupes), B (membre d'un calendrier de A), C (étranger).
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

$emails = ['cgr-a@exemple-test.fr', 'cgr-b@exemple-test.fr', 'cgr-c@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) {
        bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
    }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Cgr_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai groupes']);
}
[$idA, $idB, $idC] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
    [$idA, $idB, min($idA, $idB), max($idA, $idB)]);

$cookies = array_combine($emails, array_map(static fn (string $c): string => __DIR__ . '/ck_cgr_' . $c . '.txt', ['a', 'b', 'c']));
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} corps, adresse finale, code */
$appel = static function (string $compte, string $chemin, ?array $post = null, array $entetes = []) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $entetes,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$nbGroupes = static fn (int $u): int => (int) bd_valeur('SELECT COUNT(*) FROM calendrier_groupes WHERE user_id = ?', [$u]);
$groupeDe = static fn (int $u, string $nom): int => (int) bd_valeur('SELECT id FROM calendrier_groupes WHERE user_id = ? AND nom = ?', [$u, $nom]);
$rangeDans = static fn (int $u, int $g): string => implode(',', array_map(
    static fn (array $l): string => (string) $l['nom'],
    bd_all('SELECT c.nom FROM calendrier_amis_membres m JOIN calendriers_amis c ON c.id = m.calendrier_id WHERE m.user_id = ? AND m.groupe_id = ? ORDER BY c.nom', [$u, $g])));
$bloc = static fn (string $page, string $nom): string => preg_match('#<details class="cal-volet__sous"[^>]*>\s*<summary class="cal-volet__sous-titre">\s*<span class="cal-volet__sous-nom">' . preg_quote($nom, '#') . '</span>(.*?)</details>#su', $page, $m) ? $m[0] : '';
$renomme = false;

try {
    [$a, $b, $c] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    $csrf = [];
    foreach ($emails as $e) { [$p] = $appel($e, 'calendrier'); $csrf[$e] = $jeton($p); }
    $poster = static fn (string $compte, string $chemin, array $champs = []): array => $appel($compte, $chemin, ['_csrf' => $csrf[$compte]] + $champs);

    // Trois calendriers pour A (B est membre du troisième), un pour B.
    $calId = static fn (string $nom): int => (int) bd_valeur('SELECT id FROM calendriers_amis WHERE nom = ? AND proprietaire_id IN (?, ?, ?)', [$nom, $idA, $idB, $idC]);
    $poster($a, 'calendriers-amis', ['nom' => 'Cours']);
    $poster($a, 'calendriers-amis', ['nom' => 'Famille']);
    $poster($a, 'calendriers-amis', ['nom' => 'Vacances', 'amis' => [$idB]]);
    $poster($b, 'calendriers-amis', ['nom' => 'Sport de B']);
    [$cours, $famille, $vacances, $sportB] = [$calId('Cours'), $calId('Famille'), $calId('Vacances'), $calId('Sport de B')];

    echo "\n1. Le volet, sans groupe\n";
    [$page] = $appel($a, 'calendrier');
    $dire('« Nouveau groupe » est proposé (fenêtre, avec la page où revenir) ; aucun groupe encore',
        $oui(preg_match('#<a class="cal-volet__nouveau-groupe" href="[^"]*calendriers-amis/groupes/nouveau\?retour=[^"]*" data-fenetre>\s*<span class="cal-volet__nouveau-groupe-icone" aria-hidden="true">📁</span>\s*<span>Nouveau groupe</span>#u', $page) === 1 && !str_contains($page, 'class="cal-volet__sous"')), 'oui');
    $dire('les trois calendriers sont listés comme avant', $oui(str_contains($page, '>Cours</span>') && str_contains($page, '>Famille</span>') && str_contains($page, '>Vacances</span>')), 'oui');

    echo "\n2. Créer un groupe\n";
    [$form] = $appel($a, 'calendriers-amis/groupes/nouveau?fenetre=1&retour=' . rawurlencode('/mon_appli/appli/calendrier?essai=1'));
    $dire('le formulaire : un nom, mes trois calendriers à cocher (pas celui de B), la page où revenir',
        $oui(str_contains($form, 'name="nom"') && str_contains($form, 'value="' . $cours . '"') && str_contains($form, 'value="' . $famille . '"') && str_contains($form, 'value="' . $vacances . '"')
            && !str_contains($form, 'value="' . $sportB . '"') && str_contains($form, 'name="retour" value="/mon_appli/appli/calendrier?essai=1"') && str_contains($form, 'data-envoi-fenetre') && !str_contains($form, '<html')), 'oui');
    $poster($a, 'calendriers-amis/groupes', ['nom' => '   ']);
    $dire('un nom vide est refusé', (string) $nbGroupes($idA), '0');
    [, $adresse] = $poster($a, 'calendriers-amis/groupes', ['nom' => 'Études', 'calendriers' => [$cours, $famille, $sportB, 999999], 'retour' => '/mon_appli/appli/calendrier?essai=2']);
    $etudes = $groupeDe($idA, 'Études');
    $dire('« Études » est créé, avec Cours et Famille — ni le calendrier d\'un autre ni un numéro qui n\'existe pas', $oui($etudes > 0) . ' · ' . $rangeDans($idA, $etudes), 'oui · Cours,Famille');
    $dire('et l\'on revient sur la page du calendrier d\'où l\'on venait', $oui(str_ends_with($adresse, '/calendrier?essai=2')), 'oui');
    $dire('le calendrier de B (dont A n\'est pas membre) n\'est pas touché', (string) bd_valeur('SELECT COALESCE(groupe_id, 0) FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$sportB, $idB]), '0');
    [, $adresse] = $poster($a, 'calendriers-amis/groupes', ['nom' => 'Autre', 'retour' => '//exemple.invalid/piege']);
    $dire('une page de retour qui sort du site est ignorée : on revient au calendrier', $oui(str_contains($adresse, 'localhost/mon_appli/appli/calendrier') && !str_contains($adresse, 'invalid')), 'oui');
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Autre', 'retour' => 'http://exemple.invalid/piege']);
    $dire('même un nom déjà pris (« Autre ») n\'est créé qu\'une fois', (string) $nbGroupes($idA), '2');
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'études']);
    $dire('un nom déjà pris est refusé, majuscules ou non', (string) $nbGroupes($idA), '2');
    bd_run('DELETE FROM calendrier_groupes WHERE user_id = ? AND nom = ?', [$idA, 'Autre']);

    echo "\n3. Le volet, avec un groupe\n";
    [$page] = $appel($a, 'calendrier');
    $blocEtudes = $bloc($page, 'Études');
    $dire('« Études » est un groupe qui se plie (details ouvert), avec son compteur (2)', $oui(preg_match('#<details class="cal-volet__sous" data-groupe="[^"]*/calendriers-amis/groupes/' . $etudes . '/replier" open>#', $blocEtudes) === 1
        && str_contains($blocEtudes, '<span class="cal-volet__sous-nb">2</span>')), 'oui');
    $dire('il contient Cours et Famille — pas Vacances', $oui(str_contains($blocEtudes, '>Cours</span>') && str_contains($blocEtudes, '>Famille</span>') && !str_contains($blocEtudes, '>Vacances</span>')), 'oui');
    $dire('« Vacances », sans groupe, reste dans la liste en dessous', $oui(strpos($page, '>Vacances</span>') > strpos($page, '</details>', (int) strpos($page, 'cal-volet__sous"'))), 'oui');
    $dire('son engrenage ouvre le groupe en fenêtre (avec la page où revenir)', $oui(preg_match('#calendriers-amis/groupes/' . $etudes . '/modifier\?retour=[^"]*" data-fenetre#', $blocEtudes) === 1), 'oui');
    $dire('la case et la couleur d\'un calendrier rangé sont toujours dans le formulaire du volet', $oui(preg_match('#name="sources\[\]"\s+value="ca' . $cours . '"#', $blocEtudes) === 1 && str_contains($blocEtudes, 'name="couleur[ca' . $cours . ']"')), 'oui');

    echo "\n4. Modifier un groupe\n";
    [$formModif] = $appel($a, 'calendriers-amis/groupes/' . $etudes . '/modifier?fenetre=1');
    $dire('le formulaire : son nom, ses calendriers cochés (Cours, Famille), « Enregistrer » et « Supprimer le groupe »',
        $oui(str_contains($formModif, 'value="Études"') && preg_match('#value="' . $cours . '" checked#', $formModif) === 1 && preg_match('#value="' . $famille . '" checked#', $formModif) === 1
            && !preg_match('#value="' . $vacances . '" checked#', $formModif) && str_contains($formModif, '>Enregistrer</button>') && str_contains($formModif, 'Supprimer le groupe')), 'oui');
    $poster($a, 'calendriers-amis/groupes/' . $etudes . '/modifier', ['nom' => 'Fac', 'calendriers' => [$cours, $vacances]]);
    $dire('renommé « Fac » : Cours et Vacances y sont, Famille en est sorti (sans être supprimé)', $oui($groupeDe($idA, 'Fac') === $etudes) . ' · ' . $rangeDans($idA, $etudes) . ' · ' . $oui($calId('Famille') === $famille), 'oui · Cours,Vacances · oui');
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Loisirs', 'calendriers' => [$cours, $famille]]);
    $loisirs = $groupeDe($idA, 'Loisirs');
    $dire('un calendrier n\'est que dans un groupe à la fois : Cours passe dans « Loisirs » et quitte « Fac »', $rangeDans($idA, $loisirs) . ' · ' . $rangeDans($idA, $etudes), 'Cours,Famille · Vacances');
    [$formFac] = $appel($a, 'calendriers-amis/groupes/' . $etudes . '/modifier?fenetre=1');
    $dire('le formulaire de « Fac » dit où sont les autres : « (dans « Loisirs ») »', $oui(str_contains($formFac, '(dans « Loisirs »)')), 'oui');
    $poster($a, 'calendriers-amis/groupes/' . $etudes . '/modifier', ['nom' => 'Loisirs']);
    $dire('renommer un groupe du nom d\'un autre est refusé', $oui($groupeDe($idA, 'Fac') === $etudes), 'oui');
    $poster($a, 'calendriers-amis/groupes/' . $etudes . '/modifier', ['nom' => 'Fac']);
    $dire('enregistrer un groupe sans rien cocher le vide (les calendriers restent)', $rangeDans($idA, $etudes) . ' · ' . $oui(bd_valeur('SELECT COUNT(*) FROM calendrier_amis_membres WHERE user_id = ?', [$idA]) == 3), ' · oui');
    [$page] = $appel($a, 'calendrier');
    $dire('un groupe vide se montre, et dit « Aucun calendrier dans ce groupe. »', $oui(str_contains($bloc($page, 'Fac'), 'Aucun calendrier dans ce groupe.') && str_contains($bloc($page, 'Fac'), '<span class="cal-volet__sous-nb">0</span>')), 'oui');
    $dire('les groupes se suivent par nom (« Fac » avant « Loisirs »)', $oui(strpos($page, '>Fac</span>') < strpos($page, '>Loisirs</span>')), 'oui');

    echo "\n5. Plier et déplier\n";
    [$json, , $codeJson] = $appel($a, 'calendriers-amis/groupes/' . $loisirs . '/replier', ['_csrf' => $csrf[$a], 'replie' => '1'], ['Accept: application/json']);
    $dire('plier « Loisirs » : réponse JSON, retenu en base', $codeJson . ' · ' . $oui(str_contains($json, 'fait')) . ' · ' . bd_valeur('SELECT replie FROM calendrier_groupes WHERE id = ?', [$loisirs]), '200 · oui · 1');
    [$page] = $appel($a, 'calendrier');
    $dire('le volet le rend plié (sans « open »), « Fac » reste ouvert', $oui(preg_match('#data-groupe="[^"]*/' . $loisirs . '/replier">#', $page) === 1 && preg_match('#data-groupe="[^"]*/' . $etudes . '/replier" open>#', $page) === 1), 'oui');
    $appel($a, 'calendriers-amis/groupes/' . $loisirs . '/replier', ['_csrf' => $csrf[$a], 'replie' => '0'], ['Accept: application/json']);
    $dire('le déplier le retient aussi', (string) bd_valeur('SELECT replie FROM calendrier_groupes WHERE id = ?', [$loisirs]), '0');

    echo "\n6. Chacun ses groupes\n";
    [$pageB] = $appel($b, 'calendrier');
    $dire('B ne voit aucun des groupes de A, mais voit « Vacances » (membre) et son propre calendrier', $oui(!str_contains($pageB, 'class="cal-volet__sous"') && str_contains($pageB, '>Vacances</span>') && str_contains($pageB, '>Sport de B</span>')), 'oui');
    [$formB, $adresseB] = $appel($b, 'calendriers-amis/groupes/' . $loisirs . '/modifier?fenetre=1');
    $dire('B ne peut pas ouvrir le groupe de A : pas de formulaire', $oui(!str_contains($formB, 'value="Loisirs"') && !str_contains($formB, '/modifier"')), 'oui');
    $poster($b, 'calendriers-amis/groupes/' . $loisirs . '/modifier', ['nom' => 'Piraté', 'calendriers' => [$vacances]]);
    $poster($b, 'calendriers-amis/groupes/' . $loisirs . '/supprimer');
    $appel($b, 'calendriers-amis/groupes/' . $loisirs . '/replier', ['_csrf' => $csrf[$b], 'replie' => '1'], ['Accept: application/json']);
    $dire('B n\'a pu ni renommer, ni supprimer, ni plier le groupe de A', $groupeDe($idA, 'Loisirs') . ' · ' . $rangeDans($idA, $loisirs) . ' · ' . bd_valeur('SELECT replie FROM calendrier_groupes WHERE id = ?', [$loisirs]), $loisirs . ' · Cours,Famille · 0');
    $poster($b, 'calendriers-amis/groupes', ['nom' => 'Mes sports', 'calendriers' => [$sportB, $vacances, $cours]]);
    $sports = $groupeDe($idB, 'Mes sports');
    $dire('le groupe de B réunit ses calendriers (Sport de B, Vacances), pas « Cours » dont il n\'est pas membre — et ne dérange pas A', $rangeDans($idB, $sports) . ' · ' . $rangeDans($idA, $etudes), 'Sport de B,Vacances · ');
    $dire('le calendrier « Vacances » de A reste dans son groupe à elle, B a le sien', $oui((int) bd_valeur('SELECT groupe_id FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$vacances, $idA]) === 0), 'oui');
    [$formC] = $appel($c, 'calendriers-amis/groupes/nouveau?fenetre=1');
    $dire('un étranger (sans calendrier) crée un groupe vide sans rien toucher ailleurs', $oui(str_contains($formC, 'name="nom"') && str_contains($formC, 'Vous n’avez encore aucun calendrier partagé à ranger.')), 'oui');
    $poster($c, 'calendriers-amis/groupes', ['nom' => 'Vide de C', 'calendriers' => [$cours, $famille]]);
    $dire('et lui poster les calendriers de A n\'y range rien', $nbGroupes($idC) . ' · ' . $rangeDans($idC, $groupeDe($idC, 'Vide de C')) . ' · ' . $rangeDans($idA, $loisirs), '1 ·  · Cours,Famille');

    echo "\n7. Supprimer un groupe\n";
    $poster($a, 'calendriers-amis/groupes/' . $loisirs . '/supprimer', ['retour' => '/mon_appli/appli/calendrier?essai=3']);
    $dire('« Loisirs » disparaît, ses calendriers restent (Cours, Famille) et sont hors groupe', $nbGroupes($idA) . ' · ' . bd_valeur('SELECT COUNT(*) FROM calendrier_amis_membres WHERE user_id = ? AND groupe_id IS NOT NULL', [$idA]) . ' · ' . bd_valeur('SELECT COUNT(*) FROM calendriers_amis WHERE proprietaire_id = ?', [$idA]), '1 · 0 · 3');
    [$page] = $appel($a, 'calendrier');
    $dire('le volet les remet dans la liste', $oui(!str_contains($page, '>Loisirs</span>') && str_contains($page, '>Cours</span>') && str_contains($page, '>Famille</span>')), 'oui');
    [$formSupp, $adresseSupp] = $appel($a, 'calendriers-amis/groupes/' . $loisirs . '/modifier?fenetre=1');
    $dire('le groupe supprimé n\'est plus ouvrable : on est renvoyé au calendrier, sans formulaire', $oui(!str_contains($formSupp, 'cam-groupe-nom') && !str_contains($formSupp, 'groupes/' . $loisirs . '/') && str_contains($adresseSupp, '/calendrier')), 'oui');
    bd_run('DELETE FROM calendrier_groupes WHERE user_id IN (?, ?, ?)', [$idA, $idB, $idC]);

    echo "\n8. Le plafond\n";
    for ($i = 1; $i < CalendriersGroupesEssai::MAX; $i++) { bd_run('INSERT INTO calendrier_groupes (user_id, nom) VALUES (?, ?)', [$idA, 'G' . $i]); }
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Le vingtième']);
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Le vingt et unième']);
    $dire('vingt groupes au plus : le vingtième passe, le suivant est refusé', $nbGroupes($idA) . ' · ' . $oui($groupeDe($idA, 'Le vingtième') > 0) . ' · ' . $oui($groupeDe($idA, 'Le vingt et unième') > 0), CalendriersGroupesEssai::MAX . ' · oui · non');
    bd_run('DELETE FROM calendrier_groupes WHERE user_id = ?', [$idA]);

    echo "\n9. Un site dont la migration n'est pas passée\n";
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Avant']);
    bd_run('RENAME TABLE calendrier_groupes TO calendrier_groupes_essai');
    $renomme = true;
    [$sans, , $codeSans] = $appel($a, 'calendrier');
    $dire('le calendrier se charge, le volet comme avant : ni « Nouveau groupe » ni erreur', $codeSans . ' · ' . $oui(str_contains($sans, '>Cours</span>') && !str_contains($sans, 'cal-volet__nouveau-groupe') && !str_contains($sans, 'Fatal error') && !str_contains($sans, 'Warning:')), '200 · oui');
    [, $adresseSans] = $appel($a, 'calendriers-amis/groupes/nouveau');
    $dire('le formulaire des groupes renvoie au calendrier', $oui(str_contains($adresseSans, '/calendrier')), 'oui');
    [$pageCals, , $codeCals] = $appel($a, 'calendriers-amis');
    $dire('la liste de mes calendriers marche toujours', $codeCals . ' · ' . $oui(str_contains($pageCals, 'Cours')), '200 · oui');
    bd_run('RENAME TABLE calendrier_groupes_essai TO calendrier_groupes');
    $renomme = false;
    bd_run('DELETE FROM calendrier_groupes WHERE user_id = ?', [$idA]);

    echo "\n10. Les quatre langues\n";
    $poster($a, 'calendriers-amis/groupes', ['nom' => 'Langues', 'calendriers' => [$cours]]);
    $attendus = [
        'en' => ['New group', 'New calendar group', 'Calendars in the group', 'Edit the group “Langues”'],
        'es' => ['Nuevo grupo', 'Nuevo grupo de calendarios', 'Calendarios del grupo', 'Modificar el grupo «Langues»'],
        'de' => ['Neue Gruppe', 'Neue Kalendergruppe', 'Kalender der Gruppe', 'Gruppe „Langues“ bearbeiten'],
        'fr' => ['Nouveau groupe', 'Nouveau groupe de calendriers', 'Calendriers du groupe', 'Modifier le groupe « Langues »'],
    ];
    foreach ($attendus as $langue => [$nouveau, $titre, $legende, $gerer]) {
        $poster($a, 'compte/langue', ['langue' => $langue]);
        [$pageL] = $appel($a, 'calendrier');
        [$formL] = $appel($a, 'calendriers-amis/groupes/nouveau?fenetre=1');
        $tout = html_entity_decode($pageL . $formL, ENT_QUOTES, 'UTF-8');
        $manque = array_values(array_filter([$nouveau, $titre, $legende, $gerer], static fn (string $m): bool => !str_contains($tout, $m)));
        $dire($langue . ' : le bouton, la fenêtre et l\'engrenage', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : ni clé brute ni français resté', $oui(!preg_match('/\bcam\.gr\.[a-z_.]+/', $tout) && ($langue === 'fr' || (!str_contains($tout, 'Nouveau groupe') && !str_contains($tout, 'Calendriers du groupe')))), 'oui');
    }
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('RENAME TABLE calendrier_groupes_essai TO calendrier_groupes'); } catch (Throwable) {}
    }
    foreach ($cookies as $f) { @unlink($f); }
    $nettoyer();
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · groupes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM calendrier_groupes g LEFT JOIN users u ON u.id = g.user_id WHERE u.id IS NULL') . "\n";
}

/** Le plafond de groupes par personne, lu dans le code (le script d'essai ne charge rien de l'application). */
final class CalendriersGroupesEssai
{
    public const MAX = 20;
}
