<?php
/*
 * Ce que le navigateur lit de l'application avant toute page : le manifeste
 * d'installation et le service worker. Tous deux doivent suivre la langue du
 * compte — c'est le nom sous l'icône, et la langue annoncée des notifications.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-48s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$email = 'inst-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Inst_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai installation']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_inst.txt';
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

    echo "\n1. Le manifeste, en français\n";
    $fr = json_decode($appel('manifeste.webmanifest'), true);
    $dire('la langue annoncée', (string) ($fr['lang'] ?? ''), 'fr');
    $dire('la description', (string) ($fr['description'] ?? ''),
        'Vos cours, vos fichiers et votre planning au même endroit.');
    $dire('les raccourcis', implode(' · ', array_column((array) ($fr['shortcuts'] ?? []), 'name')),
        'Calendrier · Mes cours · Alternance');

    echo "\n2. Le même, en anglais\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);
    $en = json_decode($appel('manifeste.webmanifest'), true);
    $dire('la langue annoncée', (string) ($en['lang'] ?? ''), 'en');
    $dire('la description', (string) ($en['description'] ?? ''),
        'Your courses, your files and your schedule in one place.');
    $dire('les raccourcis', implode(' · ', array_column((array) ($en['shortcuts'] ?? []), 'name')),
        'Calendar · My courses · Apprenticeship');
    $dire('et le nom de l’application ne change pas',
        (string) ($en['name'] ?? ''), (string) ($fr['name'] ?? ''));

    echo "\n3. Le manifeste ne dort plus dans un cache partagé\n";
    // Il varie d'un compte à l'autre : « public » le ferait relire par un autre.
    $tete = '';
    $h = curl_init('http://localhost/mon_appli/appli/manifeste.webmanifest');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck, CURLOPT_TIMEOUT => 30]);
    $tete = (string) curl_exec($h);
    $dire('« Cache-Control: private »', $oui(stripos($tete, 'cache-control: private') !== false), 'oui');
    $dire('  et plus « public »', $oui(stripos($tete, 'cache-control: public') === false), 'oui');

    echo "\n4. Le service worker annonce la langue du compte\n";
    $sw = $appel('service-worker.js');
    $dire('var LANGUE = "en"', $oui(str_contains($sw, 'var LANGUE = "en";')), 'oui');
    $dire('  et la notification s’en sert', $oui(str_contains($sw, 'lang: LANGUE,')), 'oui');
    $dire('  plus de « fr » en dur', $oui(!str_contains($sw, "lang: 'fr'")), 'oui');

    echo "\n5. Le français revient\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $dire('var LANGUE = "fr"', $oui(str_contains($appel('service-worker.js'), 'var LANGUE = "fr";')), 'oui');
    $dire('  et le manifeste suit',
        (string) (json_decode($appel('manifeste.webmanifest'), true)['lang'] ?? ''), 'fr');
} finally {
    // Ménage : ce compte d'essai seul, et ce qui en dépend.
    if ($id > 0) {
        foreach (['cours', 'matieres', 'types_evenement', 'dossiers', 'abonnements_push',
                  'notifications_file'] as $table) {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$id]);
        }
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
