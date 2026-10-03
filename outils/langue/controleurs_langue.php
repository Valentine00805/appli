<?php
/** Les messages des contrôleurs : cours, tâches, cartes, compte, remboursements. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-56s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'ctrl-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Ctrl_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai contrôleurs']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_ctrl.txt';
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
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);

    echo "\n1. Mon compte\n";
    $compte = $appel('compte');
    $dire('le titre de la page suit la langue',
        $oui(str_contains($compte, '<title>My account')), 'oui');
    $meme = $appel('compte/pseudo', ['_csrf' => $csrf, 'pseudo' => 'Ctrl_essai']);
    $dire('le même pseudo : « Your username stays »',
        $oui(str_contains($meme, 'Your username stays')), 'oui');
    $fuseau = $appel('compte/fuseau', ['_csrf' => $csrf, 'fuseau' => 'Nulle/Part']);
    $dire('un fuseau inconnu : « This time zone does not exist. »',
        $oui(str_contains($fuseau, 'This time zone does not exist.')), 'oui');
    $mdpCourt = $appel('compte/mot-de-passe', ['_csrf' => $csrf, 'mot_de_passe_actuel' => $mdp, 'nouveau_mot_de_passe' => 'court', 'nouveau_mot_de_passe_confirmation' => 'court']);
    $dire('un mot de passe court : le refus est en anglais',
        $oui(str_contains($mdpCourt, 'The new password must be at least 8 characters.')), 'oui');

    echo "\n2. Les cours\n";
    $sansTitre = $appel('cours/nouveau', ['_csrf' => $csrf, 'titre' => '']);
    $dire('sans titre : « The title is required. »',
        $oui(str_contains($sansTitre, 'The title is required.')), 'oui');
    $appel('cours/nouveau', ['_csrf' => $csrf, 'titre' => 'Course for the test']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    $contenu = $appel('cours/' . $cours . '/contenu', ['_csrf' => $csrf, 'contenu' => 'Some text.']);
    $dire('le contenu enregistré : « Course content saved. »',
        $oui(str_contains($contenu, 'Course content saved.')), 'oui');
    $vide = $appel('cours/' . $cours . '/contenu', ['_csrf' => $csrf, 'contenu' => '']);
    $dire('le contenu vidé : « Course content emptied. »',
        $oui(str_contains($vide, 'Course content emptied.')), 'oui');

    echo "\n3. Les tâches\n";
    $sansNom = $appel('taches/listes', ['_csrf' => $csrf, 'nom' => '']);
    $dire('une liste sans nom : « Give your list a name. »',
        $oui(str_contains($sansNom, 'Give your list a name.')), 'oui');
    $appel('taches/listes', ['_csrf' => $csrf, 'nom' => 'My list']);
    $doublon = $appel('taches/listes', ['_csrf' => $csrf, 'nom' => 'My list']);
    $dire('une liste en double : « You already have a list named »',
        $oui(str_contains($doublon, 'You already have a list named')), 'oui');
    $liste = (int) bd_valeur('SELECT id FROM listes_taches WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    $sansTexte = $appel('taches', ['_csrf' => $csrf, 'liste_id' => (string) $liste, 'titre' => '']);
    $dire('une tâche sans texte : « Write what needs doing. »',
        $oui(str_contains($sansTexte, 'Write what needs doing.')), 'oui');

    echo "\n4. Les cartes\n";
    $sansCours = $appel('cartes/proposer', ['_csrf' => $csrf]);
    $dire('sans cours coché : « Choose at least one course. »',
        $oui(str_contains($sansCours, 'Choose at least one course.')), 'oui');
    $sansQuestion = $appel('cartes/carte', ['_csrf' => $csrf, 'cours' => (string) $cours, 'question' => '', 'reponse' => '']);
    $dire('sans question : « A card needs a question and an answer. »',
        $oui(str_contains($sansQuestion, 'A card needs a question and an answer.')), 'oui');

    echo "\n5. Les remboursements\n";
    $personnes = $appel('budget/personnes');
    $dire('le titre : « People and groups »',
        $oui(str_contains($personnes, '<title>People and groups')), 'oui');
    $csrfP = $jeton($personnes);
    $sansNomP = $appel('budget/personnes', ['_csrf' => $csrfP, 'nom' => '']);
    $dire('sans nom : « Write a name. »', $oui(str_contains($sansNomP, 'Write a name.')), 'oui');
    $appel('budget/personnes', ['_csrf' => $csrfP, 'nom' => 'Alex']);
    $deja = $appel('budget/personnes', ['_csrf' => $csrfP, 'nom' => 'Alex']);
    $dire('en double : « “Alex” is already in the address book. »',
        $oui(str_contains($deja, 'is already in the address book')), 'oui');
    $groupe = $appel('budget/groupes', ['_csrf' => $csrfP, 'nom' => 'Flatmates']);
    $dire('un groupe créé : « Group “Flatmates” created. »',
        $oui(str_contains($groupe, 'created. Tick who belongs to it.')), 'oui');

    echo "\n6. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $fr = $appel('cours/nouveau', ['_csrf' => $csrf, 'titre' => '']);
    $dire('le même refus, en français',
        $oui(str_contains($fr, 'Le titre est obligatoire.')), 'oui');
} finally {
    // Ménage : ce compte d'essai seul, et ce qui en dépend.
    if ($id > 0) {
        bd_run('DELETE FROM cartes WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM cours WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM taches WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM listes_taches WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM personnes WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM groupes WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM matieres WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM categories_budget WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM types_evenement WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
