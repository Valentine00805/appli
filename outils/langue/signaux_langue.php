<?php
/*
 * Les endroits où le code se demandait « quoi est-ce ? » en comparant du texte
 * affiché. Un libellé traduit faisait alors échouer la comparaison, et la page
 * perdait un bouton ou affichait une date de trop.
 *
 * On vérifie ici que chacun de ces trois endroits se décide sur autre chose
 * que des mots : une liste de tâches, une session de révision, et le « c'est
 * aujourd'hui » de la recherche dans un groupe.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-50s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'sig-a@exemple-test.fr';
$emailB = 'sig-b@exemple-test.fr';
bd_run('DELETE FROM users WHERE email IN (?, ?)', [$email, $emailB]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Sig_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai signaux']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_sig.txt';
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

try {
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => $mdp]);
    $csrf = $jeton($appel('compte'));

    /*
     * Une liste de tâches qui échoit aujourd'hui, avec une sous-tâche : le
     * calendrier montre les deux, et doit savoir laquelle est la liste.
     */
    $aujourdhui = date('Y-m-d');
    bd_run('INSERT INTO listes_taches (user_id, nom, couleur, icone, echeance, position) VALUES (?, ?, ?, ?, ?, 1)',
        [$id, 'Dossier de stage', '#4f46e5', '📋', $aujourdhui]);
    $liste = (int) bd_valeur('SELECT id FROM listes_taches WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    bd_run('INSERT INTO taches (user_id, liste_id, titre, echeance, position) VALUES (?, ?, ?, ?, 1)',
        [$id, $liste, 'Relire le plan', $aujourdhui]);

    foreach (['fr' => 'Tâche principale', 'en' => 'Main task'] as $langue => $attenduType) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        echo "\n" . ($langue === 'fr' ? '1' : '2') . ". Le calendrier en « " . $langue . " »\n";
        // La vue « liste » est celle qui passe par _ligne.php, où se décide tout ceci.
        $cal = $appel('calendrier?vue=liste&mois=' . date('Y-m'));
        $dire('le type de la liste est traduit', $oui(str_contains($cal, $attenduType)), 'oui');
        /*
         * La liste se reconnaît à son infobulle : « Terminer la liste » n'est
         * proposé que si le code a compris qu'il regardait une liste. Avant,
         * la comparaison au libellé français échouait en anglais.
         */
        $attendu = $langue === 'fr' ? 'Terminer toute la liste' : 'Complete the whole list';
        $dire('  et le bouton sait que c’est une liste', $oui(str_contains($cal, $attendu)), 'oui');
    }

    echo "\n3. Une session de révision, en anglais\n";
    // Son titre part en base traduit : le bouton ne peut plus s'y fier.
    $cours = $appel('cours/nouveau');
    $appel('cours/nouveau', ['_csrf' => $jeton($cours), 'titre' => 'Algebra']);
    $coursId = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    $focus = $appel('focus');
    $appel('focus/planifier', ['_csrf' => $jeton($focus), 'cours' => [$coursId],
        'jour' => $aujourdhui, 'heure' => '18:00', 'minutes' => '30']);
    $evt = (int) bd_valeur('SELECT id FROM evenements WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    $dire('le titre est enregistré en anglais',
        (string) bd_valeur('SELECT titre FROM evenements WHERE id = ?', [$evt]), 'Revision: Algebra');
    $dire('  et les cours à réviser sont notés',
        (string) bd_valeur('SELECT COUNT(*) FROM evenement_revision_cours WHERE evenement_id = ?', [$evt]), '1');
    $fiche = $appel('evenements/' . $evt);
    $dire('  « Start the session » est bien proposé',
        $oui(str_contains($fiche, 'Start the session')), 'oui');

    echo "\n4. La recherche dans un groupe, en anglais\n";
    /*
     * « Aujourd'hui » ne se compare plus à un mot : seule l'heure doit sortir.
     * Un groupe exige au moins un ami, d'où le second compte.
     */
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$emailB, 'Sig_deux', password_hash($mdp, PASSWORD_DEFAULT), 'Essai signaux 2']);
    $idB = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$emailB]);
    // La table impose petit_id/grand_id : la paire triée, pour l'unicité.
    bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, acceptee_le)
            VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP())",
        [$id, $idB, min($id, $idB), max($id, $idB)]);

    $groupe = $appel('groupes/nouveau');
    $appel('groupes', ['_csrf' => $jeton($groupe), 'nom' => 'Study group', 'membres' => [$idB]]);
    $conv = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id]);
    $dire('le groupe est créé', $oui($conv > 0), 'oui');
    bd_run('INSERT INTO conversation_messages (conversation_id, expediteur_id, texte, created_at)
            VALUES (?, ?, ?, UTC_TIMESTAMP())', [$conv, $id, 'Chapter four tonight']);
    // La réponse découpe le texte autour du mot cherché : avant, trouvé, après.
    $trouve = json_decode($appel('groupes/' . $conv . '/recherche?q=Chapter'), true);
    $resultat = (array) (((array) ($trouve['resultats'] ?? []))[0] ?? []);
    $dire('le message est trouvé', (string) ($trouve['total'] ?? 0), '1');
    $dire('  l’auteur se dit en anglais', (string) ($resultat['auteur'] ?? ''), 'You');
    /*
     * Le cœur de l'affaire : « quand » ne doit porter que l'heure, et à
     * l'anglaise. Le jour s'y collait dès que la langue n'était plus le
     * français, parce qu'on le comparait au mot « Aujourd'hui ».
     */
    $dire('  et seule l’heure sort, sans le jour',
        $oui(preg_match('/^\d{1,2}:\d{2}$/', (string) ($resultat['quand'] ?? '')) === 1), 'oui');

    echo "\n5. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $dire('la fiche de la session, de nouveau en français',
        $oui(str_contains($appel('evenements/' . $evt), 'Démarrer la session')), 'oui');
    $dire('  et le titre enregistré ne bouge pas',
        (string) bd_valeur('SELECT titre FROM evenements WHERE id = ?', [$evt]), 'Revision: Algebra');
} finally {
    // Ménage : ces deux comptes d'essai seuls, et ce qu'ils ont semé.
    foreach ([$email, $emailB] as $adresse) {
        $qui = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$adresse]);
        if ($qui <= 0) {
            continue;
        }
        bd_run('DELETE FROM evenement_revision_cours WHERE evenement_id IN
                (SELECT id FROM evenements WHERE user_id = ?)', [$qui]);
        bd_run('DELETE FROM conversation_messages WHERE expediteur_id = ?', [$qui]);
        bd_run('DELETE FROM conversations WHERE cree_par = ?', [$qui]);
        bd_run('DELETE FROM amities WHERE demandeur_id = ? OR destinataire_id = ?', [$qui, $qui]);
        foreach (['taches', 'listes_taches', 'evenements', 'cours', 'matieres', 'types_evenement',
                  'categories_budget', 'dossiers', 'conversation_membres'] as $table) {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$qui]);
        }
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$qui, $adresse]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email IN (?, ?)', [$email, $emailB]) . "\n";
}
