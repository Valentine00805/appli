<?php
/** L'alternance dans une autre langue : les pages, les refus, les confirmations. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-56s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'alt-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Alt_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai alternance']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_alt.txt';
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
$langueDeLaPage = static fn (string $h): string => preg_match('/<html lang="([a-z]+)"/', $h, $m) ? $m[1] : 'absent';

try {
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => $mdp]);
    $csrf = $jeton($appel('compte'));
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);

    echo "\n1. La fiche de l’alternance\n";
    $fiche = $appel('alternance/entreprise');
    $dire('la page s’annonce en anglais', $langueDeLaPage($fiche), 'en');
    $dire('les onglets, en anglais',
        $oui(str_contains($fiche, 'My apprenticeship') && str_contains($fiche, '</span> Schedule')
            && str_contains($fiche, 'Assignment log') && str_contains($fiche, '</span> Documents')), 'oui');
    $dire('  les cartes et les champs',
        $oui(str_contains($fiche, 'The company') && str_contains($fiche, 'My mentor')
            && str_contains($fiche, 'Start of the contract') && str_contains($fiche, 'Save the record')), 'oui');
    $dire('  sans français resté en route',
        $oui(!str_contains($fiche, 'L’entreprise') && !str_contains($fiche, 'Mon tuteur')
            && !str_contains($fiche, 'Enregistrer la fiche')), 'oui');

    echo "\n2. Un refus, puis la fiche enregistrée\n";
    $refus = $appel('alternance/entreprise', ['_csrf' => $csrf, 'debut' => '2026-09-01', 'fin' => '2026-01-01']);
    $dire('fin avant début : le refus est en anglais',
        $oui(str_contains($refus, 'The end of the contract cannot come before its start.')), 'oui');
    $ok = $appel('alternance/entreprise', [
        '_csrf' => $csrf, 'entreprise' => 'Essai SARL', 'debut' => '2026-09-01',
        'fin' => '2027-08-31', 'remise_rapport' => '2027-06-15',
    ]);
    $dire('« Record saved. »', $oui(str_contains($ok, 'Record saved.')), 'oui');
    $dire('  et les dates clés paraissent, traduites',
        $oui(str_contains($ok, 'Key dates') && str_contains($ok, 'Report hand-in')), 'oui');

    echo "\n3. Le rythme\n";
    $rythme = $appel('alternance/rythme');
    $dire('le titre, le formulaire et le bilan',
        $oui(str_contains($rythme, 'School / company schedule') && str_contains($rythme, 'Add a stretch')
            && str_contains($rythme, 'In another calendar app')), 'oui');
    $dire('  les lieux, en anglais',
        $oui(str_contains($rythme, '>🏫 School</label>') && str_contains($rythme, '>🏢 Company</label>')), 'oui');
    $pose = $appel('alternance/rythme', ['_csrf' => $csrf, 'lieu' => 'ecole', 'debut' => '2026-10-05', 'fin' => '2026-10-09']);
    $dire('une période posée : la phrase suit la langue',
        $oui(str_contains($pose, 'At school from') || str_contains($pose, 'At school on')), 'oui');
    $dire('  et le bilan compte en anglais',
        $oui(str_contains($pose, 'Tally') && str_contains($pose, 'of which')), 'oui');
    $refusLieu = $appel('alternance/rythme', ['_csrf' => $csrf, 'lieu' => 'nimporte', 'debut' => '2026-10-05']);
    $dire('un lieu inconnu : « Choose School or Company. »',
        $oui(str_contains($refusLieu, 'Choose School or Company.')), 'oui');

    echo "\n4. Les notes\n";
    $notes = $appel('alternance');
    $dire('la liste vide, en anglais',
        $oui(str_contains($notes, 'Apprenticeship notes') && str_contains($notes, 'No note yet.')
            && str_contains($notes, '+ New note')), 'oui');
    $formulaire = $appel('alternance/notes/nouvelle');
    $dire('le formulaire : modèles compris',
        $oui(str_contains($formulaire, 'Start from a template') && str_contains($formulaire, 'Blank page')
            && str_contains($formulaire, 'Team meeting') && str_contains($formulaire, 'Assignment report')), 'oui');
    $sansTitre = $appel('alternance/notes/nouvelle', ['_csrf' => $csrf, 'titre' => '', 'contenu' => 'x']);
    $dire('sans titre : « Give the note a title. »',
        $oui(str_contains($sansTitre, 'Give the note a title.')), 'oui');

    echo "\n5. Le journal et les documents\n";
    $journal = $appel('alternance/journal');
    $dire('le journal vide',
        $oui(str_contains($journal, 'Assignment log') && str_contains($journal, 'The log is empty.')), 'oui');
    $semaine = $appel('alternance/journal/semaine?semaine=2026-10-05');
    $dire('la page d’une semaine',
        $oui(str_contains($semaine, 'Assignments and tasks done') && str_contains($semaine, 'Skills worked on')
            && str_contains($semaine, 'Save the week')), 'oui');
    $vide = $appel('alternance/journal', ['_csrf' => $csrf, 'semaine' => '2026-10-05', 'missions' => '', 'competences' => '']);
    $dire('rien d’écrit : « Write at least one assignment… »',
        $oui(str_contains($vide, 'Write at least one assignment or one skill.')), 'oui');
    $documents = $appel('alternance/documents');
    $dire('les documents : les rayons en anglais',
        $oui(str_contains($documents, 'Apprenticeship documents') && str_contains($documents, 'Upload')
            && str_contains($documents, 'Apprenticeship logbook') && str_contains($documents, 'Other documents')), 'oui');

    echo "\n6. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $fr = $appel('alternance/rythme');
    $dire('les mêmes pages, en français',
        $oui(str_contains($fr, 'Rythme école / entreprise') && str_contains($fr, 'Poser une période')
            && str_contains($fr, '>🏫 École</label>')), 'oui');
    $dire('  et la période posée se relit en français',
        $oui(str_contains($fr, 'École') && !str_contains($fr, 'Add a stretch')), 'oui');
} finally {
    // Ménage : ce compte d'essai seul, et ce qui en dépend.
    if ($id > 0) {
        bd_run('DELETE FROM alternance_periodes WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM alternance_journal WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM alternance_notes WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM alternance_documents WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM alternance_contrat WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM evenements WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM taches WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM listes_taches WHERE user_id = ?', [$id]);
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email = ?", [$email]) . "\n";
}
