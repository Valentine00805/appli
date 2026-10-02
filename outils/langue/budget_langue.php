<?php
/** Le budget, l'import, les prévisions et les dossiers, dans une autre langue. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-56s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'bud-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Bud_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai budget']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_bud.txt';
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

    echo "\n1. Le budget et ses moyens de paiement\n";
    $budget = $appel('budget');
    $dire('les moyens de paiement, traduits',
        $oui(str_contains($budget, '>Card</option>') && str_contains($budget, '>Cash</option>')
            && str_contains($budget, '>Direct debit</option>')), 'oui');
    $dire('  et la valeur envoyée reste une clé stable',
        $oui(str_contains($budget, 'value="carte"') && str_contains($budget, 'value="prelevement"')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($budget, '>Espèces</option>') && !str_contains($budget, '>Prélèvement</option>')), 'oui');

    echo "\n2. Une opération\n";
    $csrfB = $jeton($budget);
    $sansLibelle = $appel('budget/operations', ['_csrf' => $csrfB, 'libelle' => '', 'montant' => '10', 'date_operation' => date('Y-m-d')]);
    $dire('sans libellé : « Say what this entry is for. »',
        $oui(str_contains($sansLibelle, 'Say what this entry is for.')), 'oui');
    $mauvais = $appel('budget/operations', ['_csrf' => $csrfB, 'libelle' => 'Test', 'montant' => 'abc', 'date_operation' => date('Y-m-d')]);
    $dire('un montant illisible : le refus est en anglais',
        $oui(str_contains($mauvais, 'The amount is not valid.')), 'oui');
    $ok = $appel('budget/operations', ['_csrf' => $csrfB, 'libelle' => 'Groceries', 'montant' => '12,50',
        'date_operation' => date('Y-m-d'), 'sens' => 'depense', 'moyen' => 'carte']);
    $dire('« Expense of … saved. »', $oui(str_contains($ok, 'Expense of')), 'oui');
    $dire('  et le moyen est relu traduit dans la liste',
        $oui(str_contains($ok, '· Card')), 'oui');
    $dire('  la clé est bien ce qui part en base',
        (string) bd_valeur('SELECT moyen FROM operations WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]), 'carte');

    echo "\n3. Les catégories\n";
    $categories = $appel('budget/categories');
    $dire('le titre : « Budget categories »',
        $oui(str_contains($categories, '<title>Budget categories')), 'oui');
    $csrfC = $jeton($categories);
    $sansNom = $appel('budget/categories', ['_csrf' => $csrfC, 'nom' => '']);
    $dire('sans nom : « The category name is required. »',
        $oui(str_contains($sansNom, 'The category name is required.')), 'oui');

    echo "\n4. Les prévisions et l’import\n";
    $previsions = $appel('budget/previsions');
    $dire('le titre suit la langue', $oui(str_contains($previsions, '<title>Forecast —')), 'oui');
    $csrfP = $jeton($previsions);
    $solde = $appel('budget/previsions/solde', ['_csrf' => $csrfP, 'periode' => date('Y-m'), 'montant' => 'xyz']);
    $dire('un solde illisible : « The balance is not valid. »',
        $oui(str_contains($solde, 'The balance is not valid.')), 'oui');
    $import = $appel('budget/import');
    $dire('l’import : « Import a statement »',
        $oui(str_contains($import, '<title>Import a statement')), 'oui');
    $sansFichier = $appel('budget/import', ['_csrf' => $jeton($import)]);
    $dire('sans fichier : « Choose a file to import. »',
        $oui(str_contains($sansFichier, 'Choose a file to import.')), 'oui');

    echo "\n5. Les dossiers\n";
    $dossiers = $appel('organisation/dossiers');
    $csrfD = $jeton($dossiers);
    $sansNomD = $appel('dossiers', ['_csrf' => $csrfD, 'nom' => '']);
    $dire('sans nom : « Give your folder a name. »',
        $oui(str_contains($sansNomD, 'Give your folder a name.')), 'oui');
    $cree = $appel('dossiers', ['_csrf' => $csrfD, 'nom' => 'Semester 1']);
    $dire('« Folder “Semester 1” created. »', $oui(str_contains($cree, 'created.')), 'oui');

    echo "\n6. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $fr = $appel('budget');
    $dire('les moyens de paiement, de nouveau en français',
        $oui(str_contains($fr, '>Espèces</option>') && str_contains($fr, 'value="especes"')), 'oui');
    $dire('  et l’opération relue garde sa clé',
        $oui(str_contains($fr, '· Carte')), 'oui');
} finally {
    // Ménage : ce compte d'essai seul, et ce qui en dépend.
    if ($id > 0) {
        foreach (['operations', 'soldes_saisis', 'recurrences', 'personnes', 'groupes',
                  'dossiers', 'cours', 'matieres', 'categories_budget', 'types_evenement'] as $table) {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$id]);
        }
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
