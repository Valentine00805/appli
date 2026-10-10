<?php
/**
 * Un fichier dans un travail de groupe (HTTP + base) : « Partager » → « Dans un projet de groupe » depuis le lecteur de fichiers, la lecture
 * seule des membres (et d'eux seuls), retirer le fichier du projet, et « Partager plusieurs », qui met d'un coup des cours, des dossiers et
 * des fichiers dans les projets cochés. Quatre langues.
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
    'a' => ['email' => 'pfi-a@exemple-test.fr', 'pseudo' => 'Pfi_un'],     // administrateur du groupe, propriétaire des fichiers
    'b' => ['email' => 'pfi-b@exemple-test.fr', 'pseudo' => 'Pfi_deux'],   // membre
    'd' => ['email' => 'pfi-d@exemple-test.fr', 'pseudo' => 'Pfi_trois'],  // étranger au groupe
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$ck = static fn (string $c): string => __DIR__ . '/ck_pfi_' . $c . '.txt';
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
    bd_run('INSERT INTO projets (nom, cree_par) VALUES (?, ?)', ['Groupe de fichiers', $id('a')]);
    $projet = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id('a'), 'Groupe de fichiers']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projet, $id('a'), 'admin', 'membre']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projet, $id('b'), 'membre', 'membre']);
    bd_run('INSERT INTO projets (nom, cree_par) VALUES (?, ?)', ['Autre groupe', $id('a')]);
    $projet2 = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id('a'), 'Autre groupe']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projet2, $id('a'), 'admin', 'membre']);

    // A : un fichier seul (le PDF d'une ligne de « cours » marquée fichier), un cours, un dossier. B : un fichier à lui.
    bd_run('INSERT INTO cours (user_id, titre, est_fichier) VALUES (?, ?, 1)', [$id('a'), 'sujet-eval.pdf']);
    $coursFichier = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'sujet-eval.pdf']);
    bd_run('INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 0, ?, ?, ?, 10)',
        [$id('a'), $coursFichier, 'sujet-eval.pdf', 'essai-pfi-1.pdf', 'application/pdf']);
    $fichier = (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$coursFichier]);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$id('a'), 'Cours du lot', 'Texte du cours.']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours du lot']);
    bd_run('INSERT INTO dossiers (user_id, nom, icone) VALUES (?, ?, ?)', [$id('a'), 'Dossier du lot', '📂']);
    $dossier = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$id('a')]);
    bd_run('INSERT INTO cours (user_id, titre, est_fichier) VALUES (?, ?, 1)', [$id('b'), 'fichier-de-b.pdf']);
    $coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$id('b')]);
    bd_run('INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 0, ?, ?, ?, 10)',
        [$id('b'), $coursB, 'fichier-de-b.pdf', 'essai-pfi-2.pdf', 'application/pdf']);
    $fichierB = (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$coursB]);

    $nb = static fn (string $type, int $cible, ?int $p = null): int => (int) bd_valeur(
        'SELECT COUNT(*) FROM projet_liens WHERE type = ? AND cible_id = ?' . ($p === null ? '' : ' AND projet_id = ' . $p), [$type, $cible]);

    echo "\n1. La fenêtre « Partager » d'un fichier\n";
    [$cod, $fen] = $appel('a', 'partager/fichiers/' . $fichier . '?fenetre=1');
    $dire('elle propose « Dans un projet de groupe », avec mes projets', (string) $cod . ' · ' . $oui(str_contains($fen, 'Dans un projet de groupe')
        && str_contains($fen, 'Groupe de fichiers') && str_contains($fen, 'Autre groupe') && str_contains($fen, 'partager/fichiers/' . $fichier . '/projets')), '200 · oui');
    $dire('et dit ce que les membres pourront faire : consulter et télécharger (lecture seule)',
        $oui(str_contains($fen, 'consulter et le télécharger') && !str_contains($fen, 'lisent et le modifient')), 'oui');
    [$cod] = $appel('d', 'partager/fichiers/' . $fichier . '?fenetre=1');
    $dire('le fichier d\'un autre n\'a pas cette fenêtre', (string) $cod, '404');

    echo "\n2. Mettre le fichier dans un projet\n";
    $appel('a', 'partager/fichiers/' . $fichier . '/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet]]);
    $dire('A met son fichier dans le groupe', (string) $nb('fichier', $fichier, $projet), '1');
    $appel('a', 'partager/fichiers/' . $fichier . '/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet]]);
    $dire('une seconde fois : pas de doublon', (string) $nb('fichier', $fichier), '1');
    $appel('a', 'partager/fichiers/' . $fichier . '/projets', ['_csrf' => $csrf['a']]);
    $dire('sans projet coché : rien', (string) $nb('fichier', $fichier), '1');
    [$cod] = $appel('b', 'partager/fichiers/' . $fichier . '/projets', ['_csrf' => $csrf['b'], 'projets' => [$projet]]);
    $dire('B ne met pas le fichier de A dans un projet', (string) $cod . ' · ' . $nb('fichier', $fichier), '404 · 1');
    $appel('d', 'partager/fichiers/' . $fichierB . '/projets', ['_csrf' => $csrf['d'], 'projets' => [$projet]]);
    $dire('un étranger ne met rien dans le groupe', (string) $nb('fichier', $fichierB), '0');
    $appel('b', 'partager/fichiers/' . $fichierB . '/projets', ['_csrf' => $csrf['b'], 'projets' => [$projet2]]);
    $dire('B ne met pas son fichier dans un groupe dont il n\'est pas membre', (string) $nb('fichier', $fichierB), '0');
    $appel('a', 'partager/fichiers/' . $fichier . '/projets', ['_csrf' => 'faux', 'projets' => [$projet2]]);
    $dire('sans le bon jeton CSRF : rien', (string) $nb('fichier', $fichier, $projet2), '0');
    [, $fen] = $appel('a', 'partager/fichiers/' . $fichier . '?fenetre=1');
    $dire('la fenêtre le montre « Déjà dans » le groupe, avec « Retirer du projet »',
        $oui(str_contains($fen, 'Déjà dans') && str_contains($fen, 'Retirer du projet')), 'oui');

    echo "\n3. Ce que voient les membres\n";
    [$cod, $onglet] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('B voit le fichier dans l\'onglet « Cours » du groupe, en lecture seule',
        $cod . ' · ' . $oui(str_contains($onglet, 'sujet-eval.pdf') && str_contains($onglet, 'Lecture seule')), '200 · oui');
    $dire('  il s\'ouvre par le partage, et ne propose pas de l\'ajouter à ses cours',
        $oui(str_contains($onglet, 'partages/fichiers/' . $fichier) && !str_contains($onglet, 'Ajouter à mes cours')), 'oui');
    [$cod, $lu] = $appel('b', 'partages/fichiers/' . $fichier);
    $dire('B ouvre le fichier', $cod . ' · ' . $oui(str_contains($lu, 'sujet-eval.pdf')), '200 · oui');
    [$cod, $lu] = $appel('d', 'partages/fichiers/' . $fichier);
    $dire('un étranger au groupe ne l\'ouvre pas : « pas partagé avec vous », sans le nom du fichier',
        $oui(!str_contains($lu, 'sujet-eval.pdf') && str_contains($lu, 'n’est pas, ou plus, partagé avec vous')), 'oui');
    [, $ongletA] = $appel('a', 'travaux/' . $projet . '/cours');
    $dire('A, propriétaire, le voit aussi et l\'ouvre dans le lecteur de fichiers',
        $oui(str_contains($ongletA, 'sujet-eval.pdf') && str_contains($ongletA, 'fichiers/' . $fichier . '/apercu')), 'oui');
    $dire('et le cours « fichier » qui le contient n\'est pas lié pour autant', (string) $nb('cours', $coursFichier), '0');

    echo "\n4. Le retirer du projet\n";
    $lien = (int) bd_valeur("SELECT id FROM projet_liens WHERE type = 'fichier' AND cible_id = ? AND projet_id = ?", [$fichier, $projet]);
    $appel('d', 'partager/fichiers/' . $fichier . '/projets/' . $lien . '/retirer', ['_csrf' => $csrf['d']]);
    $dire('un étranger ne le retire pas', (string) $nb('fichier', $fichier), '1');
    $appel('a', 'partager/fichiers/' . $fichier . '/projets/' . $lien . '/retirer', ['_csrf' => $csrf['a']]);
    $dire('A le retire du groupe (le fichier reste chez lui)', $nb('fichier', $fichier) . ' · ' . bd_valeur('SELECT COUNT(*) FROM fichiers WHERE id = ?', [$fichier]), '0 · 1');
    [, $lu] = $appel('b', 'partages/fichiers/' . $fichier);
    $dire('B n\'y a plus accès : « pas, ou plus, partagé avec vous »',
        $oui(!str_contains($lu, 'sujet-eval.pdf') && str_contains($lu, 'n’est pas, ou plus, partagé avec vous')), 'oui');

    echo "\n5. « Partager plusieurs » : des cours, des dossiers et des fichiers dans des projets\n";
    [$cod, $lot] = $appel('a', 'partager/plusieurs?fenetre=1');
    $dire('la fenêtre propose « Dans un projet de groupe » et un bouton pour les projets cochés',
        $cod . ' · ' . $oui(str_contains($lot, 'Dans un projet de groupe') && str_contains($lot, 'Ajouter aux projets cochés') && str_contains($lot, 'partager/plusieurs/projets')
            && str_contains($lot, 'Groupe de fichiers')), '200 · oui');
    $appel('a', 'partager/plusieurs/projets', ['_csrf' => $csrf['a'], 'cours' => [$cours], 'dossiers' => [$dossier], 'fichiers' => [$fichier]]);
    $dire('sans projet coché : rien', (string) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id IN (?, ?)', [$projet, $projet2]), '0');
    $appel('a', 'partager/plusieurs/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet]]);
    $dire('sans document coché : rien', (string) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id IN (?, ?)', [$projet, $projet2]), '0');
    $appel('a', 'partager/plusieurs/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet, $projet2], 'cours' => [$cours], 'dossiers' => [$dossier], 'fichiers' => [$fichier]]);
    $dire('un cours, un dossier et un fichier dans deux projets : six liens', (string) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id IN (?, ?)', [$projet, $projet2]), '6');
    $dire('  chacun au bon genre', $nb('cours', $cours) . '|' . $nb('dossier', $dossier) . '|' . $nb('fichier', $fichier), '2|2|2');
    [, $retour] = $appel('a', 'partager/plusieurs/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet], 'cours' => [$cours]]);
    $dire('refaire le même envoi : rien de plus, et on le dit', bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id IN (?, ?)', [$projet, $projet2]) . ' · ' . $oui(str_contains($retour, 'déjà')), '6 · oui');
    $appel('a', 'partager/plusieurs/projets', ['_csrf' => $csrf['a'], 'projets' => [$projet], 'fichiers' => [$fichierB]]);
    $dire('le fichier d\'un autre compte n\'est pas mis dans un projet', (string) $nb('fichier', $fichierB), '0');
    $appel('d', 'partager/plusieurs/projets', ['_csrf' => $csrf['d'], 'projets' => [$projet], 'cours' => [$cours]]);
    $dire('un étranger au groupe ne met rien dedans', (string) $nb('cours', $cours, $projet), '1');
    $appel('a', 'partager/plusieurs/projets', ['_csrf' => 'faux', 'projets' => [$projet], 'dossiers' => [$dossier]]);
    $dire('sans le bon jeton CSRF : rien de plus', (string) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id IN (?, ?)', [$projet, $projet2]), '6');
    [, $ongletB] = $appel('b', 'travaux/' . $projet . '/cours');
    $dire('B voit dans le groupe le cours, le dossier et le fichier',
        $oui(str_contains($ongletB, 'Cours du lot') && str_contains($ongletB, 'Dossier du lot') && str_contains($ongletB, 'sujet-eval.pdf')), 'oui');

    echo "\n6. Les quatre langues\n";
    foreach (['en' => ['Add to the ticked projects', 'view and download'], 'es' => ['Añadir a los proyectos marcados', 'solo lectura'],
        'de' => ['Zu den angekreuzten Projekten hinzufügen', 'nur Lesen'], 'fr' => ['Ajouter aux projets cochés', 'Lecture seule']] as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => $langue]);
        [, $lot] = $appel('a', 'partager/plusieurs?fenetre=1');
        [, $fen] = $appel('a', 'partager/fichiers/' . $fichier . '?fenetre=1');
        $dire("$langue : « Partager plusieurs » est traduit", $oui(str_contains($lot, $mots[0]) && !preg_match('/\bpt\.(projets_lot[a-z_]*|flash_lot[a-z_.]*)/', $lot)), 'oui');
        $dire("$langue : la fenêtre d'un fichier aussi", $oui(mb_stripos($fen, $mots[1]) !== false && !preg_match('/\bpt\.projets_aide[a-z_]*/', $fen)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : les groupes d'essai, leurs liens, puis les comptes (leurs cours, dossiers et fichiers partent avec eux).
    foreach ([$projet ?? null, $projet2 ?? null] as $p) {
        if ($p !== null && $p > 0) {
            bd_run('DELETE FROM projet_liens WHERE projet_id = ?', [$p]);
            bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$p]);
            bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$p, $id('a')]);
        }
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'pfi-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
