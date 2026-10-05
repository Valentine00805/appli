<?php
/**
 * Un évènement partagé par un ami, et le cours qui va avec : la matière (la voir, l'ajouter à ses matières, la retrouver sur sa copie),
 * le lien vers le cours lié, et ce qu'on peut y régler sans rien modifier — ses rappels (pour soi seul) et ses notes (lisibles par
 * le propriétaire, pas par les autres amis).
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
    'a' => ['email' => 'pev-a@exemple-test.fr', 'pseudo' => 'Pev_un'],      // le propriétaire
    'b' => ['email' => 'pev-b@exemple-test.fr', 'pseudo' => 'Pev_deux'],    // un ami : évènement et cours partagés
    'c' => ['email' => 'pev-c@exemple-test.fr', 'pseudo' => 'Pev_trois'],   // un autre ami : l'évènement seul
    'd' => ['email' => 'pev-d@exemple-test.fr', 'pseudo' => 'Pev_quatre'],  // un inconnu
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$ck = static fn (string $c): string => __DIR__ . '/ck_pev_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

/** @return array{0: int, 1: string, 2: string} le code HTTP, le corps et l'adresse finale */
$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_HTTP_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
    }
    foreach (['b', 'c'] as $autre) {
        $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $id($autre)]);
        $appel($autre, 'amis/' . $id('a') . '/accepter', ['_csrf' => $csrf[$autre]]);
    }

    // A : une matière verte, un cours et deux évènements (l'un lié au cours partagé, l'autre à un cours gardé pour soi).
    bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$id('a'), 'Architecture distribuée', '#059669']);
    $matiere = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ?', [$id('a')]);
    bd_run('INSERT INTO cours (user_id, matiere_id, titre, contenu) VALUES (?, ?, ?, ?)', [$id('a'), $matiere, 'DM pizzeria', 'Le sujet du DM.']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'DM pizzeria']);
    bd_run('INSERT INTO cours (user_id, matiere_id, titre, contenu) VALUES (?, ?, ?, ?)', [$id('a'), $matiere, 'Cours gardé pour moi', 'Privé.']);
    $coursPrive = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours gardé pour moi']);
    $bientot = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    bd_run('INSERT INTO evenements (user_id, matiere_id, cours_id, titre, description, debut, fin) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id('a'), $matiere, $cours, 'Rendre le DM', '<p>Archive zip et rapport pdf.</p>', $bientot, $bientot]);
    $evt = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ? AND titre = ?', [$id('a'), 'Rendre le DM']);
    bd_run('INSERT INTO evenements (user_id, matiere_id, cours_id, titre, debut, fin) VALUES (?, ?, ?, ?, ?, ?)',
        [$id('a'), $matiere, $coursPrive, 'Réviser en secret', '2030-06-01 08:00:00', '2030-06-01 09:00:00']);
    $evtPrive = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ? AND titre = ?', [$id('a'), 'Réviser en secret']);

    // Partage : à B l'évènement et son cours ; à C l'évènement seul ; à B aussi l'évènement dont le cours reste privé.
    $envoi = static fn (string $qui, array $plus = []): array => ['_csrf' => $csrf['a'], 'amis' => [(string) $id($qui)], 'droit' => 'lecture', 'texte' => ''] + $plus;
    $appel('a', 'partager/evenements/' . $evt . '/amis', $envoi('b', ['avec_cours' => '1']));
    $appel('a', 'partager/evenements/' . $evt . '/amis', $envoi('c'));
    $appel('a', 'partager/evenements/' . $evtPrive . '/amis', $envoi('b'));

    echo "\n1. La matière d'un cours partagé\n";
    [, $page] = $appel('b', 'partages/cours/' . $cours);
    $dire('B voit la matière du cours, de sa couleur, avec un bouton pour l\'ajouter à ses matières',
        $oui(preg_match('#class="pastille" style="background:\#059669;[^"]*">Architecture distribuée</span>#', $page) === 1
            && str_contains($page, 'Ajouter à mes matières') && str_contains($page, '/partages/cours/' . $cours . '/matiere"')), 'oui');
    $dire('  il n\'en a pas encore', bd_valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ?', [$id('b')]), '0');
    [, $retour] = $appel('b', 'partages/cours/' . $cours . '/copier', ['_csrf' => $csrf['b']]);
    $copie1 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id('b')]);
    $dire('  copier le cours avant : la copie reste sans matière', $copie1 > 0 ? (string) bd_valeur('SELECT COALESCE(matiere_id, 0) FROM cours WHERE id = ?', [$copie1]) : 'pas de copie', '0');
    [, $apres] = $appel('b', 'partages/cours/' . $cours . '/matiere', ['_csrf' => $csrf['b']]);
    $dire('le bouton : la matière est ajoutée, même nom et même couleur',
        bd_valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ? AND nom = ?', [$id('b'), 'Architecture distribuée']) . ' · '
        . bd_valeur('SELECT couleur FROM matieres WHERE user_id = ?', [$id('b')]), '1 · #059669');
    $dire('  et la page le dit : « ✓ Dans tes matières », plus de bouton',
        $oui(str_contains($apres, '✓ Dans tes matières') && !str_contains($apres, '/matiere"')), 'oui');
    $dire('  le message : « La matière … est ajoutée »', $oui(str_contains($apres, 'est ajoutée à tes matières')), 'oui');
    [, $encore] = $appel('b', 'partages/cours/' . $cours . '/matiere', ['_csrf' => $csrf['b']]);
    $dire('  un second clic ne crée pas de doublon', $oui(str_contains($encore, 'déjà dans tes matières')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ?', [$id('b')]), 'oui · 1');
    $appel('b', 'partages/cours/' . $cours . '/copier', ['_csrf' => $csrf['b']]);
    $copie2 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id('b')]);
    $dire('  copier le cours ensuite : la copie reçoit sa matière à lui', $copie2 !== $copie1
        ? (bd_valeur('SELECT m.nom FROM cours c JOIN matieres m ON m.id = c.matiere_id WHERE c.id = ?', [$copie2]) ?? 'aucune') : 'pas de copie', 'Architecture distribuée');
    [$cod] = $appel('d', 'partages/cours/' . $cours . '/matiere', ['_csrf' => $csrf['d']]);
    $dire('un inconnu ne peut pas l\'ajouter', bd_valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ?', [$id('d')]), '0');
    [$cod, $corps] = $appel('b', 'partages/cours/' . $cours . '/matiere', ['_csrf' => 'faux']);
    $dire('  sans le bon jeton CSRF : refusé', $oui($cod >= 400 || !str_contains($corps, 'est ajoutée')), 'oui');

    echo "\n2. L'évènement partagé : cours lié, matière\n";
    [, $page] = $appel('b', 'partages/evenements/' . $evt);
    $dire('B voit le lien vers le cours lié (partagé aussi)',
        $oui(preg_match('#COURS LIÉ|Cours lié#iu', $page) === 1 && str_contains($page, '/partages/cours/' . $cours . '"') && str_contains($page, '>DM pizzeria</a>')), 'oui');
    $dire('  sa matière : déjà chez lui, donc « ✓ Dans tes matières »', $oui(str_contains($page, '✓ Dans tes matières')), 'oui');
    [, $pageC] = $appel('c', 'partages/evenements/' . $evt);
    $dire('C, à qui le cours n\'est pas partagé : pas de lien vers le cours, mais le bouton pour la matière',
        $oui(!str_contains($pageC, '/partages/cours/') && str_contains($pageC, 'Ajouter à mes matières')), 'oui');
    [, $pagePrive] = $appel('b', 'partages/evenements/' . $evtPrive);
    $dire('un cours que A garde pour lui n\'est pas dévoilé par son évènement', $oui(!str_contains($pagePrive, 'Cours gardé pour moi') && !str_contains($pagePrive, '/partages/cours/' . $coursPrive)), 'oui');
    $appel('c', 'partages/evenements/' . $evt . '/matiere', ['_csrf' => $csrf['c']]);
    $dire('C l\'ajoute à ses matières depuis l\'évènement', bd_valeur('SELECT COUNT(*) FROM matieres WHERE user_id = ? AND nom = ?', [$id('c'), 'Architecture distribuée']), '1');
    $appel('c', 'partages/evenements/' . $evt . '/copier', ['_csrf' => $csrf['c']]);
    $dire('  puis « Ajouter à mon calendrier » : sa copie a la matière',
        (string) (bd_valeur('SELECT m.nom FROM evenements e JOIN matieres m ON m.id = e.matiere_id WHERE e.user_id = ? AND e.partage_de = ?', [$id('c'), $evt]) ?? 'aucune'), 'Architecture distribuée');

    echo "\n3. Mes rappels et mes notes sur l'évènement d'un ami\n";
    $dire('la section est là, pour B (pas de rappel, pas de note)', $oui(str_contains($page, 'Mes rappels et mes notes') && str_contains($page, '/perso"')
        && preg_match('#name="rappels\[\]" value="15" checked#', $page) !== 1), 'oui');
    [, $pageA] = $appel('a', 'evenements/' . $evt . '?fenetre=1');
    $dire('  le propriétaire n\'y est pas : il a son formulaire', $oui(!str_contains($pageA, 'Mes rappels et mes notes')), 'oui');
    $perso = static fn (string $qui, array $champs): array => $appel($qui, 'partages/evenements/' . $evt . '/perso', ['_csrf' => $csrf[$qui]] + $champs);
    [, $retour] = $perso('b', ['rappels' => ['15', '1440', '999', 'x'], 'note' => "Je prends le PDF.\nA relire."]);
    $dire('B règle ses rappels et écrit une note', bd_valeur('SELECT rappels FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('b')]) . ' · '
        . str_replace("\n", '/', (string) bd_valeur('SELECT note FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('b')])), '1440,15 · Je prends le PDF./A relire.');
    $dire('  (les délais inconnus sont écartés) et le message le dit', $oui(str_contains($retour, 'rappels et tes notes sont enregistrés')), 'oui');
    [, $page] = $appel('b', 'partages/evenements/' . $evt);
    $dire('  la page les remontre cochés et remplis', $oui(preg_match('#value="15" checked#', $page) === 1 && preg_match('#value="1440" checked#', $page) === 1
        && str_contains($page, 'Je prends le PDF.')), 'oui');
    $dire('  l\'évènement de A n\'a pas bougé', (string) bd_valeur('SELECT titre FROM evenements WHERE id = ?', [$evt]) . ' · ' . bd_valeur('SELECT rappels FROM evenements WHERE id = ?', [$evt]) . ' · '
        . strip_tags((string) bd_valeur('SELECT description FROM evenements WHERE id = ?', [$evt])), 'Rendre le DM · 15 · Archive zip et rapport pdf.');
    [, $pageA] = $appel('a', 'evenements/' . $evt . '?fenetre=1');
    $dire('A lit la note de B sous les siennes', $oui(str_contains($pageA, 'Notes de Pev_deux') && str_contains($pageA, 'Je prends le PDF.') && str_contains($pageA, 'Archive zip')), 'oui');
    [, $pageC] = $appel('c', 'partages/evenements/' . $evt);
    $dire('  C (autre ami) ne la voit pas', $oui(!str_contains($pageC, 'Je prends le PDF.') && !str_contains($pageC, 'Notes de Pev_deux')), 'oui');
    [, $pageB2] = $appel('b', 'partages/evenements/' . $evt);
    $dire('  et C n\'a pas les rappels de B', (string) bd_valeur('SELECT COUNT(*) FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('c')]), '0');
    $perso('c', ['note' => 'Note de C']);
    [, $pageA] = $appel('a', 'evenements/' . $evt . '?fenetre=1');
    $dire('A lit aussi celle de C, chacune à son nom', $oui(str_contains($pageA, 'Notes de Pev_trois') && str_contains($pageA, 'Note de C')), 'oui');
    [$cod, $retour] = $perso('a', ['note' => 'Moi, propriétaire']);
    $dire('le propriétaire ne passe pas par là', bd_valeur('SELECT COUNT(*) FROM evenement_perso_amis WHERE user_id = ?', [$id('a')]), '0');
    $perso('d', ['note' => 'Intrus', 'rappels' => ['15']]);
    $dire('un inconnu n\'écrit rien sur l\'évènement', bd_valeur('SELECT COUNT(*) FROM evenement_perso_amis WHERE user_id = ?', [$id('d')]), '0');
    $perso('b', ['note' => str_repeat('x', 2001), 'rappels' => ['5']]);
    $dire('une note trop longue est refusée, rien n\'est changé', bd_valeur('SELECT rappels FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('b')]), '1440,15');
    [$cod] = $appel('b', 'partages/evenements/' . $evt . '/perso', ['_csrf' => 'faux', 'note' => 'Faux jeton']);
    $dire('sans le bon jeton CSRF : rien', $oui(str_contains((string) bd_valeur('SELECT note FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('b')]), 'Faux jeton') === false), 'oui');

    echo "\n   — les rappels de B partent, à l'heure de l'évènement de A\n";
    bd_run('INSERT INTO abonnements_push (user_id, point_final, empreinte, cle_p256dh, cle_auth, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        [$id('b'), 'https://exemple-test.fr/push/' . $id('b'), md5('pev' . $id('b')), str_repeat('a', 87), str_repeat('b', 22)]);
    bd_run('INSERT INTO abonnements_push (user_id, point_final, empreinte, cle_p256dh, cle_auth, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        [$id('d'), 'https://exemple-test.fr/push/' . $id('d'), md5('pev' . $id('d')), str_repeat('a', 87), str_repeat('b', 22)]);
    // L'heure d'un évènement est dans le fuseau de son auteur : dix minutes d'ici, comptées dans celui du compte (le même pour les deux).
    $fuseauA = (string) bd_valeur('SELECT fuseau FROM users WHERE id = ?', [$id('a')]);
    $dans10 = (new DateTimeImmutable('+10 minutes', new DateTimeZone($fuseauA)))->format('Y-m-d H:i:s');
    bd_run('UPDATE evenements SET debut = ?, fin = ? WHERE id = ?', [$dans10, $dans10, $evt]);
    [, $corps] = $appel('b', 'notifications/battement', ['_csrf' => $csrf['b']]);
    $bilan = json_decode($corps, true) ?: [];
    $dire('B : l\'évènement de A a lieu dans dix minutes, son rappel « 15 min » est dû', (string) ($bilan['rappels'] ?? '?'), '1');
    [, $corps] = $appel('d', 'notifications/battement', ['_csrf' => $csrf['d']]);
    $dire('  un inconnu, sans rien de réglé : aucun rappel', (string) ((json_decode($corps, true) ?: [])['rappels'] ?? '?'), '0');
    // Un accès retiré : plus de rappel (un rappel déjà inscrit n'est pas redonné : on en pose un nouveau, plus proche).
    bd_run('DELETE FROM rappels_envoyes WHERE user_id = ?', [$id('b')]);
    bd_run('DELETE FROM partages_amis WHERE cible_type = ? AND cible_id = ? AND destinataire_id = ?', ['evenement', $evt, $id('b')]);
    [, $corps] = $appel('b', 'notifications/battement', ['_csrf' => $csrf['b']]);
    $dire('  l\'accès retiré par A : B n\'a plus de rappel pour cet évènement', (string) ((json_decode($corps, true) ?: [])['rappels'] ?? '?'), '0');

    echo "\n4. Tout effacer\n";
    [, $page] = $appel('c', 'partages/evenements/' . $evt);
    $perso('c', ['note' => '', 'rappels' => []]);
    $dire('une note vidée et plus de rappel : la ligne disparaît', bd_valeur('SELECT COUNT(*) FROM evenement_perso_amis WHERE evenement_id = ? AND user_id = ?', [$evt, $id('c')]), '0');

    echo "\n5. Les quatre langues\n";
    foreach ([
        'en' => ['Add to my subjects', 'My reminders and notes', 'My notes'],
        'es' => ['Añadir a mis asignaturas', 'Mis recordatorios y mis notas', 'Mis notas'],
        'de' => ['Zu meinen Fächern hinzufügen', 'Meine Erinnerungen und Notizen', 'Meine Notizen'],
        'fr' => ['Ajouter à mes matières', 'Mes rappels et mes notes', 'Mes notes'],
    ] as $langue => $mots) {
        $appel('d', 'compte/langue', ['_csrf' => $csrf['d'], 'langue' => $langue]);
        // D n'a pas accès : on passe par C, qui a l'évènement et pas la matière.
        bd_run('DELETE FROM matieres WHERE user_id = ?', [$id('c')]);
        $appel('c', 'compte/langue', ['_csrf' => $csrf['c'], 'langue' => $langue]);
        [, $page] = $appel('c', 'partages/evenements/' . $evt);
        $dire("$langue : bouton de la matière, section et libellés", $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[1]) && str_contains($page, $mots[2])
            && !preg_match('/>\s*pt\.[a-z_.]+\s*</', $page)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : seulement les comptes d'essai, et ce qui en dépend (les cours, évènements, matières partent avec leur compte).
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'pev-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
