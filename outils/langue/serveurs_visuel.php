<?php
/*
 * Prépare le contrôle visuel des serveurs : deux comptes d'essai amis, un serveur garni (trois salons, un message, B invité à un second),
 * et la session de A, que le navigateur reprend (aucun mot de passe n'est saisi).
 *
 *     php outils/langue/serveurs_visuel.php            prépare et affiche l'identifiant de session de A et celui de B
 *     php outils/langue/serveurs_visuel.php --efface   retire les comptes d'essai (et leurs serveurs)
 */
require __DIR__ . '/base.php';

$emails = ['srv-vis-a@exemple-test.fr', 'srv-vis-b@exemple-test.fr'];
$efface = static function () use ($emails): void {
    foreach ($emails as $e) {
        bd_run('DELETE s FROM serveurs s JOIN users u ON u.id = s.cree_par WHERE u.email = ? AND u.email LIKE ?', [$e, '%@exemple-test.fr']);
        bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
    }
};
$efface();
if (in_array('--efface', $argv, true)) { echo "retirés\n"; return; }

foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, ['Alma', 'Bastien'][$i], password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai']);
}
[$a, $b] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())", [$a, $b, min($a, $b), max($a, $b)]);

$base = 'http://localhost/mon_appli/appli/';
$session = static function (string $email) use ($base): array {
    $jar = tempnam(sys_get_temp_dir(), 'sv');
    $appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
        usleep(250000);
        $h = curl_init($base . $chemin);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
        if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
        return (string) curl_exec($h);
    };
    $jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    $csrf = $jeton($appel('serveurs'));
    $id = '';
    foreach (file($jar) as $l) { if (str_contains($l, 'MESCOURS_SESSID')) { $id = trim(substr($l, strrpos($l, "\t") + 1)); } }
    return [$appel, $csrf, $id];
};
[$appelA, $csrfA, $idSessionA] = $session($emails[0]);
[, , $idSessionB] = $session($emails[1]);
$appelA('serveurs', ['_csrf' => $csrfA, 'nom' => 'Licence 2 — groupe A', 'icone' => '🎓']);
$s = (int) bd_valeur('SELECT id FROM serveurs WHERE cree_par = ?', [$a]);
$appelA("serveurs/$s/salons", ['_csrf' => $csrfA, 'nom' => 'Cours de maths']);
$appelA("serveurs/$s/salons", ['_csrf' => $csrfA, 'nom' => 'projets']);
$appelA("serveurs/$s/inviter", ['_csrf' => $csrfA, 'amis' => [$b]]);
$general = (int) bd_valeur('SELECT id FROM conversations WHERE serveur_id = ? AND nom = ?', [$s, 'général']);
$appelA("groupes/$general/messages", ['_csrf' => $csrfA, 'texte' => 'Bienvenue sur le serveur !']);
echo "serveur $s, salon général $general\nA : $idSessionA\nB : $idSessionB\n";
