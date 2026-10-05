<?php
/** Les travaux de groupe dans une autre langue : les pages, les refus, les confirmations. */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-56s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'trav-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Trav_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai travaux']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_trav.txt';
@unlink($ck);
$appel = static function (string $chemin, ?array $post = null) use ($ck): string {
    usleep(320000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) ? $m[1] : '';

$projet = null;
$invitation = null;
try {
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => $mdp]);
    $csrf = $jeton($appel('compte'));
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);

    echo "\n1. La liste, vide\n";
    $liste = $appel('travaux');
    $dire('le titre, l’aide et l’état vide',
        $oui(str_contains($liste, 'Group projects') && str_contains($liste, '+ New group project')
            && str_contains($liste, 'No group project yet.')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($liste, 'Travaux de groupe') && !str_contains($liste, 'Aucun travail de groupe')), 'oui');
    $formulaire = $appel('travaux/nouveau');
    $dire('le formulaire de création',
        $oui(str_contains($formulaire, 'New group project') && str_contains($formulaire, 'The topic, the instructions')
            && str_contains($formulaire, 'Invite friends')), 'oui');

    echo "\n2. Un projet, et son tableau\n";
    $cree = $appel('travaux', ['_csrf' => $csrf, 'nom' => 'Essai de langue', 'description' => 'Un sujet.']);
    $dire('« Group project created. »', $oui(str_contains($cree, 'Group project created.')), 'oui');
    $projet = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id]);
    $dire('le projet est en base', $projet > 0 ? 'oui' : 'non', 'oui');
    $taches = $appel('travaux/' . $projet);
    $dire('les onglets du projet',
        $oui(str_contains($taches, 'Who does what') && str_contains($taches, 'Deadlines')
            && str_contains($taches, 'Shared document') && str_contains($taches, '</span> Members')), 'oui');
    $dire('  les trois colonnes, en anglais',
        $oui(str_contains($taches, '⬜ To do') && str_contains($taches, '⏳ Under way')
            && str_contains($taches, '✅ Done') && str_contains($taches, 'Nothing here.')), 'oui');
    $dire('  les filtres et l’état vide',
        $oui(str_contains($taches, '>All</a>') && str_contains($taches, '>Mine</a>')
            && str_contains($taches, 'No task yet.')), 'oui');

    echo "\n3. Une tâche\n";
    $nouvelle = $appel('travaux/' . $projet . '/taches/nouvelle');
    $dire('le formulaire : « Who takes it », « Nobody for now »',
        $oui(str_contains($nouvelle, 'Who takes it') && str_contains($nouvelle, 'Nobody for now')
            && str_contains($nouvelle, 'New task')), 'oui');
    $ajoutee = $appel('travaux/' . $projet . '/taches', ['_csrf' => $csrf, 'titre' => 'Write the intro']);
    $dire('« Task added. »', $oui(str_contains($ajoutee, 'Task added.')), 'oui');
    $sansTitre = $appel('travaux/' . $projet . '/taches', ['_csrf' => $csrf, 'titre' => '']);
    $dire('sans titre : « Give the task a title. »',
        $oui(str_contains($sansTitre, 'Give the task a title.')), 'oui');

    echo "\n4. Les échéances et leurs types\n";
    $echeances = $appel('travaux/' . $projet . '/echeances');
    $dire('l’état vide et les boutons',
        $oui(str_contains($echeances, 'No upcoming deadline.') && str_contains($echeances, '+ New deadline')
            && str_contains($echeances, 'Deadline types')), 'oui');
    $types = $appel('travaux/' . $projet . '/types');
    $dire('les types : le titre et l’état vide',
        $oui(str_contains($types, 'Deadline types') && str_contains($types, '+ New type')), 'oui');
    $champs = $appel('travaux/' . $projet . '/echeances/nouvelle');
    $dire('les champs d’une échéance',
        $oui(str_contains($champs, '>Day</label>') && str_contains($champs, 'Length (min)')
            && str_contains($champs, '(empty: all day)')), 'oui');

    echo "\n5. Les fichiers, le document, les membres\n";
    $fichiers = $appel('travaux/' . $projet . '/fichiers');
    $dire('les fichiers : l’état vide et le dépôt',
        $oui(str_contains($fichiers, 'No file yet') && str_contains($fichiers, 'Drop your files here')
            && str_contains($fichiers, '>Upload</button>')), 'oui');
    $document = $appel('travaux/' . $projet . '/document');
    $dire('le document : le titre et les versions',
        $oui(str_contains($document, 'Shared document') && str_contains($document, 'Earlier versions')), 'oui');
    $membres = $appel('travaux/' . $projet . '/membres');
    $dire('les membres : les cartes et les réglages',
        $oui(str_contains($membres, 'Invite friends') && str_contains($membres, 'Add someone without an account')
            && str_contains($membres, 'Public link') && str_contains($membres, 'Project settings')
            && str_contains($membres, 'Leave the project')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($membres, 'Inviter des amis') && !str_contains($membres, 'Lien public')
            && !str_contains($membres, 'Quitter le projet')), 'oui');

    echo "\n6. Une personne sans compte\n";
    $sans = $appel('travaux/' . $projet . '/sans-compte', ['_csrf' => $csrf, 'nom' => 'Alex']);
    $dire('« “Alex” added: hand them tasks… »', $oui(str_contains($sans, '“Alex” added')), 'oui');
    $sansNom = $appel('travaux/' . $projet . '/sans-compte', ['_csrf' => $csrf, 'nom' => '']);
    $dire('sans nom : « Give the person’s name. »',
        $oui(str_contains($sansNom, 'Give the person’s name.')), 'oui');

    echo "\n   — accepter une invitation : le groupe s'ouvre dans une fenêtre\n";
    bd_run('INSERT INTO projets (nom, cree_par) VALUES (?, ?)', ['Invitation d’essai', $id]);
    $invitation = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id, 'Invitation d’essai']);
    bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut, invite_par) VALUES (?, ?, ?, ?, ?)', [$invitation, $id, 'membre', 'invite', $id]);
    $apres = $appel('travaux/' . $invitation . '/rejoindre', ['_csrf' => $csrf]);
    $dire('accepter : on reste sur la liste, qui rouvre le groupe dans une fenêtre (« data-ouvrir-auto »)',
        $oui(str_contains($apres, 'data-fenetre data-ouvrir-auto') && str_contains($apres, '/travaux/' . $invitation . '"')
            && str_contains($apres, 'Open the group “Invitation d’essai”')), 'oui');
    $dire('  et il est bien membre', (string) bd_valeur('SELECT statut FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$invitation, $id]), 'membre');
    $sans = $appel('travaux');
    $dire('  la liste seule (sans « ouvrir ») ne rouvre rien', $oui(!str_contains($sans, 'data-ouvrir-auto')), 'oui');
    $etranger = $appel('travaux?ouvrir=999999999');
    $dire('  « ouvrir » pour un groupe qui n\'est pas à moi : rien ne s\'ouvre', $oui(!str_contains($etranger, 'data-ouvrir-auto')), 'oui');
    $dejaFait = $appel('travaux/' . $invitation . '/rejoindre', ['_csrf' => $csrf]);
    $dire('  accepter deux fois : refusé, rien ne s\'ouvre', $oui(!str_contains($dejaFait, 'data-ouvrir-auto')), 'oui');

    echo "\n   — l'échéance d'une tâche de groupe, dans le calendrier\n";
    $moiMembre = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$projet, $id]);
    $alex = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND nom = ?', [$projet, 'Alex']);
    $mois15 = date('Y-m-15');
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Mine due', $moiMembre, $mois15, 'a_faire']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Done due', $moiMembre, $mois15, 'fait']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Alex due', $alex, $mois15, 'a_faire']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, NULL, ?, ?)', [$projet, 'Nobody due', $mois15, 'a_faire']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, NULL, ?)', [$projet, 'Mine undated', $moiMembre, 'a_faire']);
    $calendrier = $appel('calendrier?date=' . $mois15);
    $dire('une tâche de groupe qu\'on m\'a confiée, avec échéance, est dans le calendrier',
        $oui(str_contains($calendrier, 'Mine due') && str_contains($calendrier, '/travaux/' . $projet . '"')), 'oui');
    $dire('  avec son type « Group task · le groupe », et les faites aussi (barrées)',
        $oui(str_contains($calendrier, 'Group task · Essai de langue') && str_contains($calendrier, 'Done due')), 'oui');
    $dire('  celle que personne n\'a prise y est aussi (pour tout le groupe), et son titre le précise',
        $oui(str_contains($calendrier, 'Nobody due (unassigned)') && !str_contains($calendrier, 'Mine due (unassigned)')), 'oui');
    $dire('  mais pas celle d\'un autre membre, ni celle sans échéance',
        $oui(!str_contains($calendrier, 'Alex due') && !str_contains($calendrier, 'Mine undated')), 'oui');
    $demain = date('Y-m-d');
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Due today', $moiMembre, $demain, 'a_faire']);
    $accueil = $appel('');
    $dire('  et dans la journée de l\'accueil quand elle tombe aujourd\'hui', $oui(str_contains($accueil, 'Due today')), 'oui');
    $dire('  la carte « Travaux de groupe » de l\'accueil : les miennes, et celles de personne, précisées ; pas celle d\'Alex',
        $oui(str_contains($accueil, 'Mine due') && str_contains($accueil, 'Nobody due (unassigned)') && !str_contains($accueil, 'Alex due')
            && !str_contains($accueil, 'Mine due (unassigned)')), 'oui');
    $liste = $appel('travaux');
    $dire('  et la colonne « à faire » de la liste des travaux', $oui(str_contains($liste, 'Nobody due (unassigned)') && !str_contains($liste, 'Alex due')), 'oui');
    $apres = $appel('calendrier?date=' . date('Y-m-15', strtotime('+2 months')));
    $dire('  un autre mois ne l\'affiche pas', $oui(!str_contains($apres, 'Mine due')), 'oui');

    echo "\n7. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $fr = $appel('travaux/' . $projet);
    $dire('les mêmes pages, en français',
        $oui(str_contains($fr, 'Qui fait quoi') && str_contains($fr, '>À faire')
            && str_contains($fr, 'Répartition')), 'oui');
    $termine = true;
} finally {
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    if ($invitation !== null && $invitation > 0) {
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$invitation]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$invitation, $id]);
    }
    // Ménage : ce projet d'essai et son compte, rien d'autre.
    if ($projet !== null && $projet > 0) {
        bd_run('DELETE FROM projet_taches WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_echeances WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_types WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_fichiers WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_versions WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$projet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$projet, $id]);
    }
    if ($id > 0) {
        bd_run('DELETE FROM evenements WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
