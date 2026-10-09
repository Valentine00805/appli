<?php
/*
 * Prépare le contrôle visuel des calendriers partagés : deux comptes d'essai amis, deux calendriers (un créé par A, un par B où A
 * entre), quelques évènements ce mois-ci, et la session de A, que le navigateur reprend (aucun mot de passe n'est saisi).
 *
 *     php outils/langue/calendriers_amis_visuel.php            prépare et affiche l'identifiant de session de A
 *     php outils/langue/calendriers_amis_visuel.php --efface   retire les comptes d'essai (et leurs calendriers)
 */
require __DIR__ . '/base.php';

$emails = ['cam-vis-a@exemple-test.fr', 'cam-vis-b@exemple-test.fr'];
$efface = static function () use ($emails): void {
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
};
$efface();
if (in_array('--efface', $argv, true)) { echo "retirés\n"; return; }

foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, ['Alma', 'Bastien'][$i], password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai']);
}
[$a, $b] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())", [$a, $b, min($a, $b), max($a, $b)]);

$base = 'http://localhost/mon_appli/appli/';
$jar = tempnam(sys_get_temp_dir(), 'cv');
$appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
    usleep(250000);
    $h = curl_init($base . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';
$appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $emails[0], 'mot_de_passe' => 'MotDePasse!2026']);
$csrf = $jeton($appel('calendrier'));

$appel('calendriers-amis', ['_csrf' => $csrf, 'nom' => 'Vacances en Italie', 'couleur' => '#3ba55d', 'amis' => [$b]]);
$cal = (int) bd_valeur('SELECT id FROM calendriers_amis WHERE proprietaire_id = ? ORDER BY id DESC LIMIT 1', [$a]);
$mois = date('Y-m');
foreach ([['Réserver les billets', 6, '10:00', '11:00'], ['Départ à Rome', 15, '08:30', '10:00'], ['Visite du Colisée', 17, '14:00', '16:00']] as [$titre, $jour, $de, $fin]) {
    $appel("calendriers-amis/$cal/evenements", ['_csrf' => $csrf, 'titre' => $titre, 'date_debut' => sprintf('%s-%02d', $mois, $jour), 'heure_debut' => $de, 'heure_fin' => $fin, 'lieu' => 'Rome']);
}
// Un second calendrier, créé par B (lui seul) : A y entre.
bd_run('INSERT INTO calendriers_amis (proprietaire_id, nom, couleur) VALUES (?, ?, ?)', [$b, 'Projet de groupe', '#f59e0b']);
$cal2 = (int) bd_valeur('SELECT id FROM calendriers_amis WHERE proprietaire_id = ?', [$b]);
bd_run('INSERT INTO calendrier_amis_membres (calendrier_id, user_id) VALUES (?, ?), (?, ?)', [$cal2, $b, $cal2, $a]);
bd_run('INSERT INTO calendrier_amis_evenements (calendrier_id, auteur_id, titre, debut, fin, journee_entiere) VALUES (?, ?, ?, ?, ?, 1)',
    [$cal2, $b, 'Rendu du rapport', "$mois-21 00:00:00", "$mois-21 23:59:59"]);

$session = '';
foreach (file($jar) as $l) { if (str_contains($l, 'MESCOURS_SESSID')) { $session = trim(substr($l, strrpos($l, "\t") + 1)); } }
echo "calendrier $cal, second $cal2\nMESCOURS_SESSID=$session\n";
