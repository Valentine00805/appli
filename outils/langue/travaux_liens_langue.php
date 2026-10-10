<?php
/**
 * Les cours et dossiers liés à un travail de groupe : lier (les siens seulement, une fois), la lecture par les membres — et par eux seuls
 * (pas un inconnu, pas un invité qui n'a pas accepté, pas ce qui n'est pas lié) —, l'ajout à son espace (la copie), retirer un lien
 * (celui qui l'a fait, ou un administrateur), l'accès qui s'en va avec l'appartenance, et les quatre langues.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-72s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'tli-a@exemple-test.fr', 'pseudo' => 'Tli_un'],      // administrateur du groupe
    'b' => ['email' => 'tli-b@exemple-test.fr', 'pseudo' => 'Tli_deux'],    // membre
    'c' => ['email' => 'tli-c@exemple-test.fr', 'pseudo' => 'Tli_trois'],   // invité, n'a pas accepté
    'd' => ['email' => 'tli-d@exemple-test.fr', 'pseudo' => 'Tli_quatre'],  // étranger au groupe
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$ck = static fn (string $c): string => __DIR__ . '/ck_tli_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

/** @return array{0: int, 1: string} */
$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $code = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
    unset($h);
    return [$code, $corps];
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$projet = null;
try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
    }
    // Le groupe : A l'administre, B en est membre, C est invité (pas encore membre), D n'y est pas.
    bd_run('INSERT INTO projets (nom, cree_par) VALUES (?, ?)', ['Groupe de liens', $id('a')]);
    $projet = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id('a'), 'Groupe de liens']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projet, $id('a'), 'admin', 'membre']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projet, $id('b'), 'membre', 'membre']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut, invite_par) VALUES (?, ?, ?, ?, ?)', [$projet, $id('c'), 'membre', 'invite', $id('a')]);

    // A : un cours (avec matière), un cours privé, un dossier avec un cours dedans. B : un cours.
    bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$id('a'), 'Architecture', '#059669']);
    $matiere = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ?', [$id('a')]);
    bd_run('INSERT INTO cours (user_id, matiere_id, titre, contenu) VALUES (?, ?, ?, ?)', [$id('a'), $matiere, 'Cours du groupe', 'Le sujet du cours lié.']);
    $coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours du groupe']);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('a'), 'Cours privé de A', 'Pas pour le groupe.']);
    $coursPrive = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours privé de A']);
    bd_run('INSERT INTO dossiers (user_id, nom, icone) VALUES (?, ?, ?)', [$id('a'), 'Dossier du groupe', '📂']);
    $dossierA = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$id('a')]);
    bd_run('INSERT INTO cours (user_id, dossier_id, titre, contenu) VALUES (?, ?, ?, ?)', [$id('a'), $dossierA, 'Cours dans le dossier', 'Contenu rangé dans le dossier.']);
    $coursDansDossier = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours dans le dossier']);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('b'), 'Cours de B', 'Écrit par B.']);
    $coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$id('b')]);

    $lier = static fn (string $qui, string $choix) => $appel($qui, 'travaux/' . $projet . '/liens', ['_csrf' => $csrf[$qui], 'lien' => $choix]);
    $nbLiens = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id = ?', [$projet]);

    echo "\n1. L'onglet « Cours »\n";
    [$cod, $page] = $appel('a', 'travaux/' . $projet . '/cours');
    $dire('l\'onglet et son contenu : « Lier un cours ou un dossier », mes cours et mes dossiers',
        $cod . ' · ' . $oui(preg_match('#</span>\s*Cours\s*</a>#', $page) === 1 && str_contains($page, 'Lier un cours ou un dossier') && str_contains($page, 'value="cours:' . $coursA . '"')
            && str_contains($page, 'value="dossier:' . $dossierA . '"') && str_contains($page, 'Aucun cours ni dossier lié')), '200 · oui');
    [$cod] = $appel('d', 'travaux/' . $projet . '/cours');
    $dire('  un étranger au groupe n\'y entre pas', (string) $cod, '404');
    [$cod] = $appel('c', 'travaux/' . $projet . '/cours');
    $dire('  ni un invité qui n\'a pas accepté', (string) $cod, '404');

    echo "\n2. Lier : les siens, une seule fois\n";
    [, $r] = $lier('a', 'cours:' . $coursA);
    $dire('A lie son cours', $nbLiens() . ' · ' . $oui(str_contains($r, 'Cours lié') && str_contains($r, 'Cours du groupe')), '1 · oui');
    $dire('  la matière se voit sur la liste', $oui(preg_match('#background:\#059669[^>]*>Architecture</span>#', $r) === 1), 'oui');
    [, $r] = $lier('a', 'dossier:' . $dossierA);
    $dire('A lie son dossier', $nbLiens() . ' · ' . $oui(str_contains($r, 'Dossier lié') && str_contains($r, 'Dossier du groupe')), '2 · oui');
    [, $r] = $lier('a', 'cours:' . $coursA);
    $dire('le même élément une seconde fois : refusé', $nbLiens() . ' · ' . $oui(str_contains($r, 'déjà lié')), '2 · oui');
    [, $r] = $lier('a', 'cours:' . $coursB);
    $dire('le cours d\'un autre : refusé', $nbLiens() . ' · ' . $oui(str_contains($r, 'tes propres cours')), '2 · oui');
    [, $r] = $lier('a', 'fiche:' . $coursA);
    [, $r2] = $lier('a', 'cours:abc');
    $dire('un type inconnu, ou un identifiant illisible : refusé', (string) $nbLiens(), '2');
    $lier('d', 'cours:' . $coursPrive);
    $lier('c', 'cours:' . $coursPrive);
    $dire('un étranger, ou un invité, ne lie rien', (string) $nbLiens(), '2');
    [$cod] = $appel('a', 'travaux/' . $projet . '/liens', ['_csrf' => 'faux', 'lien' => 'cours:' . $coursPrive]);
    $dire('sans le bon jeton CSRF : rien', (string) $nbLiens(), '2');

    echo "\n3. Qui peut lire\n";
    [, $lu] = $appel('b', 'partages/cours/' . $coursA);
    $dire('B (membre) lit le cours lié, avec sa matière et le bouton pour l\'avoir chez lui',
        $oui(str_contains($lu, 'Le sujet du cours lié.') && str_contains($lu, 'Architecture') && str_contains($lu, 'Copier dans mes cours')), 'oui');
    [, $lu] = $appel('b', 'partages/cours/' . $coursDansDossier);
    $dire('  et un cours rangé dans le dossier lié', $oui(str_contains($lu, 'Contenu rangé dans le dossier.')), 'oui');
    [, $lu] = $appel('b', 'partages/dossiers/' . $dossierA);
    $dire('  et le dossier, avec son contenu', $oui(str_contains($lu, 'Cours dans le dossier')), 'oui');
    [, $lu] = $appel('b', 'partages/cours/' . $coursPrive);
    $dire('  mais pas un cours de A qui n\'est pas lié', $oui(!str_contains($lu, 'Pas pour le groupe.')), 'oui');
    [, $lu] = $appel('d', 'partages/cours/' . $coursA);
    $dire('un étranger ne lit pas le cours lié', $oui(!str_contains($lu, 'Le sujet du cours lié.')), 'oui');
    [, $lu] = $appel('c', 'partages/cours/' . $coursA);
    $dire('  un invité non plus, tant qu\'il n\'a pas accepté', $oui(!str_contains($lu, 'Le sujet du cours lié.')), 'oui');
    [, $lu] = $appel('d', 'partages/dossiers/' . $dossierA);
    $dire('  ni le dossier', $oui(!str_contains($lu, 'Cours dans le dossier')), 'oui');
    bd_run('UPDATE projet_membres SET statut = ? WHERE projet_id = ? AND user_id = ?', ['membre', $projet, $id('c')]);
    [, $lu] = $appel('c', 'partages/cours/' . $coursA);
    $dire('  l\'invité qui accepte (devient membre) le lit', $oui(str_contains($lu, 'Le sujet du cours lié.')), 'oui');
    bd_run('UPDATE projet_membres SET statut = ? WHERE projet_id = ? AND user_id = ?', ['invite', $projet, $id('c')]);
    [, $recus] = $appel('b', 'partages');
    $dire('l\'onglet « Partagés avec moi » de B n\'est pas encombré : le lien de groupe n\'y figure pas', $oui(!str_contains($recus, 'Cours du groupe')), 'oui');

    echo "\n4. Ajouter à son espace\n";
    [, $page] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('la page de B : « Ajouter à mes cours » pour ce qui n\'est pas à lui', $oui(substr_count($page, 'Ajouter à mes cours') >= 2 && str_contains($page, 'Ajouter à mes cours (dossier)')), 'oui');
    [, $pageA] = $appel('a', 'travaux/' . $projet . '/cours');
    $dire('  celle de A : « C\'est le tien », pas de bouton d\'ajout', $oui(str_contains($pageA, 'C’est le tien') && !str_contains($pageA, 'Ajouter à mes cours')), 'oui');
    $appel('b', 'partages/cours/' . $coursA . '/copier', ['_csrf' => $csrf['b']]);
    $dire('B ajoute le cours à son espace', bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ? AND titre = ?', [$id('b'), 'Cours du groupe']) . ' · '
        . bd_valeur('SELECT contenu FROM cours WHERE user_id = ? AND titre = ?', [$id('b'), 'Cours du groupe']), '1 · Le sujet du cours lié.');
    $appel('b', 'partages/dossiers/' . $dossierA . '/copier', ['_csrf' => $csrf['b']]);
    $dire('  et le dossier, avec ce qu\'il contient', bd_valeur('SELECT COUNT(*) FROM dossiers WHERE user_id = ? AND nom = ?', [$id('b'), 'Dossier du groupe']) . ' · '
        . bd_valeur('SELECT COUNT(*) FROM cours c JOIN dossiers d ON d.id = c.dossier_id WHERE d.user_id = ? AND c.titre = ?', [$id('b'), 'Cours dans le dossier']), '1 · 1');
    $appel('d', 'partages/cours/' . $coursA . '/copier', ['_csrf' => $csrf['d']]);
    $dire('un étranger ne peut pas l\'ajouter', bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$id('d')]), '0');

    echo "\n   — ce qui est déjà copié n'est plus proposé\n";
    $copieDuCours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND partage_de = ?', [$id('b'), $coursA]);
    $dire('la copie garde l\'identifiant de son origine (et sa nature)', $oui($copieDuCours > 0) . ' · ' . bd_valeur('SELECT partage_nature FROM cours WHERE id = ?', [$copieDuCours]), 'oui · cours');
    [, $pageB] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('onglet « Cours » de B : « ✓ Déjà dans tes cours » + « Ouvrir ma copie », plus de bouton d\'ajout pour ce qu\'il a copié',
        $oui(substr_count($pageB, '✓ Déjà dans tes cours') === 1 && substr_count($pageB, '✓ Dossier déjà chez toi') === 1
            && str_contains($pageB, '/cours/' . $copieDuCours . '"') && !str_contains($pageB, 'Ajouter à mes cours')), 'oui');
    [, $lu] = $appel('b', 'partages/cours/' . $coursA);
    $dire('la page du cours partagé : le même constat, sans « Copier dans mes cours »',
        $oui(str_contains($lu, '✓ Déjà dans tes cours') && str_contains($lu, 'Ouvrir ma copie') && str_contains($lu, '/cours/' . $copieDuCours . '"') && !str_contains($lu, 'Copier dans mes cours')), 'oui');
    [, $lu] = $appel('b', 'partages/dossiers/' . $dossierA);
    $dire('  et celle du dossier', $oui(str_contains($lu, '✓ Dossier déjà chez toi') && !str_contains($lu, 'Copier le dossier chez moi')), 'oui');
    [, $retour] = $appel('b', 'partages/cours/' . $coursA . '/copier', ['_csrf' => $csrf['b']]);
    $dire('recopier quand même (formulaire ancien ou forcé) : refusé, pas de doublon',
        bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ? AND titre = ?', [$id('b'), 'Cours du groupe']) . ' · ' . $oui(str_contains($retour, 'déjà dans tes cours')), '1 · oui');
    $appel('b', 'partages/dossiers/' . $dossierA . '/copier', ['_csrf' => $csrf['b']]);
    $dire('  idem pour le dossier', (string) bd_valeur('SELECT COUNT(*) FROM dossiers WHERE user_id = ? AND nom LIKE ?', [$id('b'), 'Dossier du groupe%']), '1');
    $dire('  le dossier copié garde son origine ; son cours aussi', bd_valeur('SELECT COUNT(*) FROM dossiers WHERE user_id = ? AND partage_de = ?', [$id('b'), $dossierA]) . ' · '
        . bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ? AND partage_de = ?', [$id('b'), $coursDansDossier]), '1 · 1');
    // Une copie d'avant la colonne : même titre et même texte, sans origine → reconnue, et l'identifiant lui est donné.
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('b'), 'Cours du groupe lié 2', 'Texte recopié à la main.']);
    $ancien = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('b'), 'Cours du groupe lié 2']);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('a'), 'Cours du groupe lié 2', 'Texte recopié à la main.']);
    $coursA2 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours du groupe lié 2']);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('a'), 'Cours du groupe lié 3', 'Un texte différent.']);
    $coursA3 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours du groupe lié 3']);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('b'), 'Cours du groupe lié 3', 'Mon propre cours, de même titre.']);
    $homonyme = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND contenu = ?', [$id('b'), 'Mon propre cours, de même titre.']);
    $lier('a', 'cours:' . $coursA2);
    $lier('a', 'cours:' . $coursA3);
    [, $pageB] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('une copie d\'avant (même titre, même texte, sans origine) est reconnue ; un homonyme au texte différent ne l\'est pas',
        $oui(substr_count($pageB, '✓ Déjà dans tes cours') === 2 && substr_count($pageB, 'Ajouter à mes cours') === 1 && str_contains($pageB, '/cours/' . $ancien . '"')), 'oui');
    $dire('  elle reçoit l\'identifiant de l\'origine (plus de dépendance au titre) ; l\'homonyme reste libre',
        bd_valeur('SELECT partage_de FROM cours WHERE id = ?', [$ancien]) . ' · ' . $oui(bd_valeur('SELECT partage_de FROM cours WHERE id = ?', [$homonyme]) === null), $coursA2 . ' · oui');
    $dire('  et l\'ayant modifiée depuis, elle reste reconnue', $oui((function () use ($ancien, $appel, $projet): bool {
        bd_run('UPDATE cours SET titre = ?, contenu = ? WHERE id = ?', ['Renommé chez B', 'Autre texte', $ancien]);
        [, $p] = $appel('b', 'travaux/' . $projet . '/cours');
        return substr_count($p, '✓ Déjà dans tes cours') === 2;
    })()), 'oui');
    // Une fiche copiée est distincte du cours copié de la même origine.
    bd_run('UPDATE cours SET fiche_revision = ? WHERE id = ?', ['La fiche de A.', $coursA]);
    bd_run('INSERT INTO partages_amis (proprietaire_id, destinataire_id, cible_type, cible_id, droit) VALUES (?, ?, ?, ?, ?)', [$id('a'), $id('b'), 'fiche', $coursA, 'lecture']);
    [, $fiche] = $appel('b', 'partages/fiches/' . $coursA);
    $dire('la fiche du même cours reste à copier (ce n\'est pas la même chose que le cours)', $oui(str_contains($fiche, 'Copier dans mes cours') && !str_contains($fiche, '✓ Déjà dans tes cours')), 'oui');
    $appel('b', 'partages/fiches/' . $coursA . '/copier', ['_csrf' => $csrf['b']]);
    [, $fiche] = $appel('b', 'partages/fiches/' . $coursA);
    $dire('  copiée, elle le dit à son tour, et ouvre la fiche copiée',
        bd_valeur('SELECT partage_nature FROM cours WHERE user_id = ? AND partage_de = ? AND partage_nature = ?', [$id('b'), $coursA, 'fiche']) . ' · '
        . $oui(str_contains($fiche, '✓ Déjà dans tes cours') && str_contains($fiche, '/revision/')), 'fiche · oui');
    [, $hors] = $appel('d', 'partages/cours/' . $coursA);
    $dire('un étranger au groupe, lui, ne voit toujours rien', $oui(!str_contains($hors, 'Ouvrir ma copie') && !str_contains($hors, 'Le sujet du cours lié.')), 'oui');
    // Les deux cours liés pour ces essais ne comptent pas dans la suite.
    bd_run('DELETE FROM projet_liens WHERE projet_id = ? AND type = ? AND cible_id IN (?, ?)', [$projet, 'cours', $coursA2, $coursA3]);
    bd_run('DELETE FROM partages_amis WHERE cible_type = ? AND cible_id = ?', ['fiche', $coursA]);

    echo "\n   — l'aperçu de ses propres éléments, tel que le voient les autres\n";
    [, $page] = $appel('a', 'travaux/' . $projet . '/cours');
    $dire('dans la liste, mes éléments s\'ouvrent en aperçu (« apercu=1 »), ceux des autres normalement',
        $oui(str_contains($page, '/partages/cours/' . $coursA . '?apercu=1') && str_contains($page, '/partages/dossiers/' . $dossierA . '?apercu=1')), 'oui');
    [, $pageB] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('  B n\'a pas ce paramètre sur les éléments de A', $oui(!str_contains($pageB, 'apercu=1')), 'oui');
    [, $apercu] = $appel('a', 'partages/cours/' . $coursA . '?apercu=1&fenetre=1');
    $dire('A ouvre son cours en aperçu : le rendu d\'un cours partagé, en lecture seule, de quoi ouvrir le vrai',
        $oui(str_contains($apercu, 'Le sujet du cours lié.') && str_contains($apercu, 'Partagé par') && str_contains($apercu, 'Ouvrir le mien')
            && !str_contains($apercu, '<header class="entete"')), 'oui');
    // Le cours est dans un projet : son propriétaire peut le modifier d'ici (tout le groupe le fait), sans passer par sa page.
    $dire('  avec l\'éditeur (le cours est dans un projet), sans « Copier dans mes cours », sans « Supprimer ce cours »',
        $oui(str_contains($apercu, 'edition-contenu') && !str_contains($apercu, 'Copier dans mes cours') && !str_contains($apercu, 'Supprimer ce cours')), 'oui');
    [, $reel] = $appel('a', 'partages/cours/' . $coursA);
    $dire('  sans « apercu », ouvrir son propre document mène toujours à sa vraie page', $oui(str_contains($reel, 'Supprimer ce cours')), 'oui');
    [, $dossierApercu] = $appel('a', 'partages/dossiers/' . $dossierA . '?apercu=1&fenetre=1');
    $dire('  l\'aperçu du dossier : son contenu, dont les cours restent en aperçu', $oui(str_contains($dossierApercu, 'Cours dans le dossier')
        && str_contains($dossierApercu, '/partages/cours/' . $coursDansDossier . '?apercu=1') && !str_contains($dossierApercu, 'Copier le dossier chez moi')), 'oui');
    [, $autre] = $appel('b', 'partages/cours/' . $coursA . '?apercu=1&fenetre=1');
    $dire('  B avec le même paramètre : sa vue normale (qui dit qu\x27il l\x27a déjà copié)', $oui(str_contains($autre, '✓ Déjà dans tes cours') && !str_contains($autre, 'Copier dans mes cours') && !str_contains($autre, 'Ouvrir le mien')), 'oui');

    echo "\n5. Retirer un lien\n";
    $lier('b', 'cours:' . $coursB);
    $dire('B lie un de ses cours', (string) $nbLiens(), '3');
    $lienA = (int) bd_valeur('SELECT id FROM projet_liens WHERE projet_id = ? AND type = ? AND cible_id = ?', [$projet, 'cours', $coursA]);
    $lienB = (int) bd_valeur('SELECT id FROM projet_liens WHERE projet_id = ? AND type = ? AND cible_id = ?', [$projet, 'cours', $coursB]);
    [, $r] = $appel('b', 'travaux/liens/' . $lienA . '/supprimer', ['_csrf' => $csrf['b']]);
    $dire('B ne retire pas le lien de A (il n\'est pas administrateur)', $nbLiens() . ' · ' . $oui(str_contains($r, 'administrateur du groupe')), '3 · oui');
    $appel('d', 'travaux/liens/' . $lienB . '/supprimer', ['_csrf' => $csrf['d']]);
    $dire('  un étranger non plus', (string) $nbLiens(), '3');
    $appel('a', 'travaux/liens/' . $lienB . '/supprimer', ['_csrf' => $csrf['a']]);
    $dire('A (administrateur) retire le lien de B ; le cours de B est intact', $nbLiens() . ' · ' . bd_valeur('SELECT COUNT(*) FROM cours WHERE id = ?', [$coursB]), '2 · 1');
    $lier('b', 'cours:' . $coursB);
    $lienB = (int) bd_valeur('SELECT id FROM projet_liens WHERE projet_id = ? AND type = ? AND cible_id = ?', [$projet, 'cours', $coursB]);
    $appel('b', 'travaux/liens/' . $lienB . '/supprimer', ['_csrf' => $csrf['b']]);
    $dire('  B retire le sien lui-même', (string) $nbLiens(), '2');
    $lier('b', 'cours:' . $coursB);
    [, $lu] = $appel('a', 'partages/cours/' . $coursB);
    $dire('le cours lié par B se lit par A', $oui(str_contains($lu, 'Écrit par B.')), 'oui');
    $appel('a', 'travaux/liens/' . (int) bd_valeur('SELECT id FROM projet_liens WHERE projet_id = ? AND cible_id = ? AND type = ?', [$projet, $coursB, 'cours']) . '/supprimer', ['_csrf' => $csrf['a']]);
    [, $lu] = $appel('a', 'partages/cours/' . $coursB);
    $dire('  une fois le lien retiré, plus', $oui(!str_contains($lu, 'Écrit par B.')), 'oui');

    echo "\n6. L'accès suit l'appartenance\n";
    $appel('b', 'travaux/' . $projet . '/quitter', ['_csrf' => $csrf['b']]);
    [, $lu] = $appel('b', 'partages/cours/' . $coursA);
    $dire('B quitte le groupe : il ne lit plus le cours lié', $oui(!str_contains($lu, 'Le sujet du cours lié.')), 'oui');
    $dire('  mais ce qu\'il a ajouté à son espace lui reste', bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ? AND titre = ? AND partage_nature = ?', [$id('b'), 'Cours du groupe', 'cours']), '1');
    bd_run('DELETE FROM cours WHERE id = ?', [$coursA]);
    [$cod, $page] = $appel('a', 'travaux/' . $projet . '/cours');
    $dire('un cours supprimé disparaît de la liste, sans erreur', $cod . ' · ' . $oui(preg_match('#class="fichier__nom"[^>]*>Cours du groupe</a>#', $page) !== 1 && str_contains($page, 'Dossier du groupe')), '200 · oui');

    echo "\n7. Les quatre langues\n";
    foreach ([
        'en' => ['Courses', 'Link a course or a folder', 'The group’s courses and folders'],
        'es' => ['Cursos', 'Vincular un curso o una carpeta', 'Cursos y carpetas del grupo'],
        'de' => ['Kurse', 'Einen Kurs oder Ordner verknüpfen', 'Kurse und Ordner der Gruppe'],
        'fr' => ['Cours', 'Lier un cours ou un dossier', 'Cours et dossiers du groupe'],
    ] as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => $langue]);
        [, $page] = $appel('a', 'travaux/' . $projet . '/cours');
        $dire("$langue : onglet, titres et formulaire", $oui(preg_match('#</span>\s*' . preg_quote($mots[0], '#') . '\s*</a>#', $page) === 1 && str_contains($page, $mots[1]) && str_contains($page, $mots[2])
            && !preg_match('/>\s*tr\.(co|on|err|fl)\.[a-z_.]+\s*</', $page)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : le groupe d'essai, ses liens, puis les comptes (leurs cours, dossiers et copies partent avec eux).
    if ($projet !== null && $projet > 0) {
        bd_run('DELETE FROM projet_liens WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$projet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tli-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
