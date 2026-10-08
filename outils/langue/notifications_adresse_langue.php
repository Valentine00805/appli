<?php
/**
 * L'adresse d'envoi des rappels (celle qu'appelle la tâche cron, avec la clé du site) : réservée aux administrateurs.
 *
 * Trois cas, par le serveur web : (1) deux comptes ordinaires, aucun administrateur déclaré — depuis cet ordinateur, la carte
 * reste visible (c'est l'usage en local) ; (2) un administrateur déclaré (« app.administrateurs », écrit sans tenir compte des
 * majuscules) : lui seul la voit, l'autre compte n'en voit ni la carte ni la clé ; (3) la liste est vide : retour au cas 1.
 *
 * Le réglage est posé par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin). La base y est aussi
 * désignée : l'essai ne dépend pas du fichier de réglages de l'installation.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-78s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$sauvegarde = $fichierEssai . '.avant-essai';
if (is_file($fichierEssai)) { rename($fichierEssai, $sauvegarde); }
$poser = static function (array $administrateurs) use ($fichierEssai): void {
    file_put_contents($fichierEssai, '<?php return ' . var_export([
        'db' => ['host' => '127.0.0.1', 'name' => 'mon_appli_cours', 'user' => 'root', 'pass' => ''],
        'app' => ['administrateurs' => $administrateurs],
    ], true) . ';');
    // Le serveur web garde le fichier compilé quelques secondes (OPcache) : on lui laisse le temps de le relire.
    sleep(3);
};

$emails = ['adr-a@exemple-test.fr', 'adr-b@exemple-test.fr'];
foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Adresse_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai adresse']);
}
$cookies = [$emails[0] => __DIR__ . '/ck_adr_a.txt', $emails[1] => __DIR__ . '/ck_adr_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): string {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    unset($h);
    return $corps;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$voit = static fn (string $page): bool => str_contains($page, 'id="adresse-envoi"');
$cle = static fn (string $page): bool => str_contains($page, 'notifications/envoyer?cle=');   // l'adresse du cron, clé comprise

try {
    [$a, $b] = $emails;
    $poser([]);
    foreach ($emails as $e) {
        $appel($e, 'connexion', ['_csrf' => $jeton($appel($e, 'connexion')), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }

    echo "\n1. Aucun administrateur déclaré (essai depuis cet ordinateur)\n";
    $pa = $appel($a, 'notifications'); $pb = $appel($b, 'notifications');
    $dire('depuis l\'ordinateur de l\'application, la carte reste visible (A et B)', $oui($voit($pa) && $voit($pb)), 'oui');

    echo "\n2. Un administrateur déclaré (son adresse écrite avec des majuscules)\n";
    $poser([strtoupper($a)]);
    $pa = $appel($a, 'notifications'); $pb = $appel($b, 'notifications');
    $dire('l\'administrateur voit la carte, avec l\'adresse d\'envoi', $oui($voit($pa) && $cle($pa)), 'oui');
    $dire('l\'autre compte ne voit pas la carte', $oui(!$voit($pb)), 'oui');
    $dire('… ni l\'adresse d\'envoi, ni la clé, nulle part dans la page', $oui(!$cle($pb) && !str_contains($pb, 'envoyer?cle=')), 'oui');
    $dire('le reste de la page (appareils, choix des notifications) reste là pour lui', $oui(str_contains($pb, 'name="recevoir[]"') || str_contains($pb, 'notifications/choix')), 'oui');
    $fb = $appel($b, 'notifications?fenetre=1');
    $dire('dans la fenêtre aussi (depuis « Mon compte »), l\'autre compte ne voit rien', $oui(!$voit($fb) && !$cle($fb)), 'oui');

    echo "\n3. Plusieurs administrateurs, puis plus aucun\n";
    $poser([$b, $a]);
    $dire('les deux voient la carte', $oui($voit($appel($a, 'notifications')) && $voit($appel($b, 'notifications'))), 'oui');
    $poser(['quelqu-un-d-autre@exemple-test.fr']);
    $dire('une liste qui ne contient ni A ni B : aucun des deux ne la voit (même en local)', $oui(!$voit($appel($a, 'notifications')) && !$voit($appel($b, 'notifications'))), 'oui');
    $poser([]);
    $dire('liste vidée : retour à la visibilité locale', $oui($voit($appel($a, 'notifications'))), 'oui');
    $termine = true;
} finally {
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    foreach ($cookies as $f) { @unlink($f); }
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
