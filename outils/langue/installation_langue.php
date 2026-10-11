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

    echo "\n1b. Le logo : l'icône de l'application et l'image de l'en-tête\n";
    $icones = (array) ($fr['icons'] ?? []);
    $chemin = static fn (array $i): string => (string) preg_replace('#^.*/mon_appli/appli/#', '', (string) $i['src']);
    $dire('le manifeste annonce trois icônes : 192 et 512 (any), 512 découpable (maskable)',
        implode(' · ', array_map(static fn (array $i): string => $i['sizes'] . ' ' . $i['purpose'], $icones)), '192x192 any · 512x512 any · 512x512 maskable');
    foreach ($icones as $icone) {
        $image = $appel($chemin($icone));
        $infos = $image === '' ? false : getimagesizefromstring($image);
        $dire('  ' . $icone['purpose'] . ' ' . $icone['sizes'] . ' : un PNG de la bonne taille (' . $chemin($icone) . ')',
            $infos === false ? 'illisible' : $infos[0] . 'x' . $infos[1] . ' ' . $infos['mime'], $icone['sizes'] . ' image/png');
    }
    $plein = imagecreatefromstring($appel($chemin($icones[2])));
    $dire('l\'icône découpable est pleine (aucun coin transparent)',
        $oui($plein !== false && ((imagecolorat($plein, 0, 0) >> 24) & 0x7F) === 0 && ((imagecolorat($plein, 511, 511) >> 24) & 0x7F) === 0), 'oui');
    $coins = imagecreatefromstring($appel($chemin($icones[1])));
    $dire('l\'icône ordinaire est le logo seul, sur fond transparent (coins et centre du livre : le coin est transparent, le milieu opaque)',
        $oui($coins !== false && ((imagecolorat($coins, 0, 0) >> 24) & 0x7F) === 127 && ((imagecolorat($coins, 256, 256) >> 24) & 0x7F) < 10), 'oui');
    $pageAccueil = $appel('');
    $dire('l\'en-tête montre le logo (une image) et plus l\'emoji',
        $oui(preg_match('#<img class="marque__icone" src="[^"]*assets/img/logo\.png\?v=\d+"#', $pageAccueil) === 1 && !str_contains($pageAccueil, 'class="marque__icone" aria-hidden')), 'oui');
    $dire('l\'onglet du navigateur (favicon) et l\'icône iOS aussi',
        $oui(preg_match('#<link rel="icon" type="image/png" href="[^"]*assets/img/logo\.png#', $pageAccueil) === 1 && str_contains($pageAccueil, 'rel="apple-touch-icon" href="') && str_contains($pageAccueil, 'icone-masque-512.png')), 'oui');
    $dire('les icônes du manifeste portent la date de leur fichier (« ?v= ») : un nouveau logo change leur adresse',
        $oui(count(array_filter($icones, static fn (array $i): bool => preg_match('#icone-[a-z0-9-]+\.png\?v=\d{9,}$#', (string) $i['src']) === 1)) === 3
            && preg_match('#apple-touch-icon" href="[^"]*icone-masque-512\.png\?v=\d{9,}#', $pageAccueil) === 1), 'oui');
    $dire('le fichier du logo se sert', $oui(strlen($appel('assets/img/logo.png')) > 1000), 'oui');
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

    echo "\n6. L’œil du champ de mot de passe\n";
    /*
     * mot-de-passe.js écrivait « Afficher le mot de passe » en dur. Il lit
     * maintenant window.MOTS, que la page doit poser — y compris les pages sans
     * compte (connexion, inscription), qui n'en posaient aucune.
     */
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);
    $compte = $appel('compte');
    $dire('connecté, en anglais : « Show password »',
        $oui(str_contains($compte, '"mdp.afficher":"Show password"')), 'oui');
    $dire('  et « Hide password »', $oui(str_contains($compte, '"mdp.masquer":"Hide password"')), 'oui');
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);

    // Sans cookie : la page de connexion telle que la voit un visiteur.
    usleep(320000);
    $visiteur = curl_init('http://localhost/mon_appli/appli/connexion');
    curl_setopt_array($visiteur, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $connexion = (string) curl_exec($visiteur);
    $dire('la page de connexion pose window.MOTS', $oui(str_contains($connexion, 'window.MOTS')), 'oui');
    $dire('  avec les mots de l’œil', $oui(str_contains($connexion, '"mdp.afficher":"Afficher le mot de passe"')), 'oui');
    $dire('  et charge bien le script', $oui(str_contains($connexion, 'mot-de-passe.js')), 'oui');
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
