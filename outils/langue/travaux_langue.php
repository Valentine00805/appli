<?php
/** Les travaux de groupe dans une autre langue : les pages, les refus, les confirmations. */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');   // l'application tourne à l'heure de Paris : « aujourd'hui » doit être le même jour des deux côtés, même après minuit

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
    $dire('le document, encore vide : l\'éditeur s\'ouvre tout de suite (rien à lire, pas de « Annuler »)',
        $oui(str_contains($document, 'name="document"') && !str_contains($document, 'travaux-document__entete')
            && preg_match('#<a class="bouton bouton--discret" href="[^"]*/document"[^>]*>Cancel</a>#', $document) !== 1), 'oui');
    $versionLue = (int) bd_valeur('SELECT document_version FROM projets WHERE id = ?', [$projet]);
    $apresSave = $appel('travaux/' . $projet . '/document', ['_csrf' => $csrf, 'version' => (string) $versionLue, 'document' => 'Plan du rapport : introduction, méthode.']);
    $dire('après un enregistrement : la lecture du document mis en forme, avec « Modifier » (et plus l\'éditeur)',
        $oui(str_contains($apresSave, 'Plan du rapport : introduction, méthode.') && str_contains($apresSave, 'travaux-document__entete')
            && str_contains($apresSave, 'document?modifier=1') && !str_contains($apresSave, 'name="document"') && str_contains($apresSave, 'Shared document')), 'oui');
    $dire('  la page se rouvre en lecture (sans « ?modifier »), avec ses versions',
        $oui(!str_contains($appel('travaux/' . $projet . '/document'), 'name="document"') && str_contains($appel('travaux/' . $projet . '/document'), 'Earlier versions')), 'oui');
    $edition = $appel('travaux/' . $projet . '/document?modifier=1');
    $dire('  « Modifier » ouvre l\'éditeur, avec « Enregistrer » et « Annuler » qui ramène à la lecture',
        $oui(str_contains($edition, 'name="document"') && str_contains($edition, 'Plan du rapport : introduction, méthode.') && str_contains($edition, '>Save<')
            && preg_match('#<a class="bouton bouton--discret" href="[^"]*/document"[^>]*>Cancel</a>#', $edition) === 1 && !str_contains($edition, 'travaux-document__entete')), 'oui');
    $conflit = $appel('travaux/' . $projet . '/document', ['_csrf' => $csrf, 'version' => (string) ($versionLue - 5), 'document' => 'Ce que je tapais sans le savoir.']);
    $dire('  quelqu\'un a écrit entre-temps : on reste dans l\'éditeur, avec ce qu\'on avait tapé gardé à côté',
        $oui(str_contains($conflit, 'name="document"') && str_contains($conflit, 'Ce que je tapais sans le savoir.') && str_contains($conflit, 'travaux-brouillon')
            && !str_contains($conflit, 'travaux-document__entete')), 'oui');
    $versionNeuve = (int) bd_valeur('SELECT document_version FROM projets WHERE id = ?', [$projet]);
    $appel('travaux/' . $projet . '/document', ['_csrf' => $csrf, 'version' => (string) $versionNeuve, 'document' => 'Plan du rapport : introduction, méthode, résultats.']);
    $avecVersions = $appel('travaux/' . $projet . '/document');
    $dire('les versions précédentes : une tuile cliquable en entier par version, avec la date, l\'avatar et le nom de l\'auteur en évidence',
        $oui(preg_match('#<li class="version-tuile">.*?<a class="version-tuile__date" href="[^"]*/travaux/versions/\d+"[^>]*>[^<]+</a>.*?by <strong class="version-tuile__nom">Trav_essai</strong>#s', $avecVersions) === 1
            && str_contains($avecVersions, 'data-compte-avatar') && str_contains($avecVersions, 'version-tuile__fleche')), 'oui');
    $idVersion = (int) bd_valeur('SELECT id FROM projet_versions WHERE projet_id = ? ORDER BY id DESC LIMIT 1', [$projet]);
    $pageVersion = $appel('travaux/versions/' . $idVersion);
    $dire('une version précédente : le retour au document en pastille (flèche dans un rond), sans la flèche écrite dans le libellé',
        $oui(preg_match('#<a class="retour-pastille" href="[^"]*/travaux/\d+/document"[^>]*>\s*<span class="retour-pastille__fleche" aria-hidden="true">←</span>\s*<span>The current document</span>#', $pageVersion) === 1
            && !str_contains($pageVersion, '← The current document')), 'oui');
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
    // Les tâches à faire qui m'attendent dans ce groupe : les miennes et celles que personne n'a prises.
    $combienMiennes = (int) bd_valeur("SELECT COUNT(*) FROM projet_taches WHERE projet_id = ? AND statut <> 'fait' AND (membre_id = ? OR membre_id IS NULL)", [$projet, $moiMembre]);
    $dire('  rangées par projet : un bloc repliable (ouvert d\'office) avec la pastille du nombre de tâches',
        $oui(preg_match_all('/<details class="tache-projet" open>/', $liste) === 1 && preg_match('/class="tache-projet__compte" title="' . $combienMiennes . ' tasks to do">' . $combienMiennes . '</', $liste) === 1
            && $combienMiennes >= 4 && str_contains($liste, 'Essai de langue')), 'oui');
    // Ce qui presse, dit à côté du nom du projet : « 1 today » (Due today), et « 1 tomorrow » le temps d'une tâche pour demain, pas plus loin.
    $dire('  à côté du projet : ce qui tombe aujourd\'hui est dit (« 1 today »)', $oui(preg_match('/echeance--aujourdhui">1 today</', $liste) === 1), 'oui');
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Due tomorrow', $moiMembre, date('Y-m-d', strtotime('+1 day')), 'a_faire']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?)', [$projet, 'Due in three days', $moiMembre, date('Y-m-d', strtotime('+3 days')), 'a_faire']);
    $listeDemain = $appel('travaux');
    $dire('  ce qui tombe demain aussi (« 1 tomorrow »), mais rien pour une échéance plus lointaine',
        $oui(preg_match('/echeance--demain">1 tomorrow</', $listeDemain) === 1 && preg_match('#<summary>(.*?)</summary>#s', $listeDemain, $entete) === 1
            && !str_contains($entete[1], 'echeance--proche') && !str_contains($entete[1], 'echeance--lointain') && !str_contains($entete[1], 'in 3 days')), 'oui');
    bd_run('DELETE FROM projet_taches WHERE projet_id = ? AND titre IN (?, ?)', [$projet, 'Due tomorrow', 'Due in three days']);
    // Le réglage de « Mon compte » : dépliée d'office, ou repliée.
    $dire('  dépliés d\'office (réglage par défaut)', $oui(preg_match_all('/<details class="tache-projet" open>/', $liste) === 1), 'oui');
    $compteReglage = $appel('compte');
    $dire('  « Mon compte » : la carte du réglage, avec « Expanded » et « Collapsed »',
        $oui(str_contains($compteReglage, 'id="taches-groupes-carte"') && str_contains($compteReglage, 'Group project tasks') && str_contains($compteReglage, 'name="taches_repliees" value="0" checked')
            && str_contains($compteReglage, 'Expanded: all my tasks, project by project') && str_contains($compteReglage, 'Collapsed: only the projects and their task count')), 'oui');
    $appel('compte/taches-groupes', ['_csrf' => $csrf, 'taches_repliees' => '1']);
    $apresRepli = $appel('travaux');
    $dire('  repliés : le bloc du projet n\'est plus ouvert, mais sa pastille et ses tuiles sont là',
        $oui(preg_match_all('/<details class="tache-projet">/', $apresRepli) === 1 && !str_contains($apresRepli, '<details class="tache-projet" open>')
            && preg_match('/class="tache-projet__compte" title="\d+ tasks to do"/', $apresRepli) === 1 && str_contains($apresRepli, 'Mine due')), 'oui');
    $dire('  et le réglage est gardé en base', (string) bd_valeur('SELECT taches_groupes_repliees FROM users WHERE id = ?', [$id]), '1');
    $dire('  « Mon compte » le montre sélectionné (« Collapsed »)', $oui(str_contains($appel('compte'), 'name="taches_repliees" value="1" checked')), 'oui');
    $appel('compte/taches-groupes', ['_csrf' => $csrf, 'taches_repliees' => '2']);
    $appel('compte/taches-groupes', ['_csrf' => 'faux', 'taches_repliees' => '0']);
    $dire('  une valeur inconnue, ou sans le bon jeton CSRF : rien ne change', (string) bd_valeur('SELECT taches_groupes_repliees FROM users WHERE id = ?', [$id]), '1');
    $appel('compte/taches-groupes', ['_csrf' => $csrf, 'taches_repliees' => '0']);
    $dire('  redéplier : les blocs s\'ouvrent de nouveau', $oui(str_contains($appel('travaux'), '<details class="tache-projet" open>')), 'oui');
    $dire('  sous le projet, une tuile ne répète pas le nom du projet',
        $oui(preg_match('/<li class="tache-groupe">(?:(?!<\/li>).)*travaux-projet/s', $liste) !== 1 && substr_count($liste, 'class="tache-groupe"') === $combienMiennes), 'oui');
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
