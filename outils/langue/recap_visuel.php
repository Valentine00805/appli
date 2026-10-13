<?php
/*
 * Prépare le contrôle visuel du récapitulatif PDF d'un travail de groupe : un compte d'essai, un projet de trois semaines avec quatre membres
 * (dont un sans compte) et une vingtaine de tâches aux dates étalées, et la session du compte (aucun mot de passe n'est saisi).
 *
 *     php outils/langue/recap_visuel.php            prépare, affiche le projet et l'identifiant de session
 *     php outils/langue/recap_visuel.php --efface   retire le compte d'essai (et son projet)
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');
$email = 'rec-vis-a@exemple-test.fr';
$efface = static function () use ($email): void {
    bd_run('DELETE p FROM projets p JOIN users u ON u.id = p.cree_par WHERE u.email = ? AND u.email LIKE ?', [$email, '%@exemple-test.fr']);
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
};
$efface();
if (in_array('--efface', $argv, true)) { echo "retiré\n"; return; }

bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$email, 'Alma', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai']);
$a = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
$jour = static fn (int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
bd_run('INSERT INTO projets (nom, cree_par, jeton, created_at) VALUES (?, ?, ?, ?)', ['Rapport de laboratoire : les étoiles', $a, bin2hex(random_bytes(16)), $jour(-20) . ' 09:00:00']);
$p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ?', [$a]);
bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$p, $a]);
foreach (['Bastien', 'Chloé', 'Dimitri (sans compte)'] as $nom) {
    bd_run("INSERT INTO projet_membres (projet_id, user_id, nom, role, statut) VALUES (?, NULL, ?, 'membre', 'membre')", [$p, $nom]);
}
$m = array_column(bd_all('SELECT id, nom FROM projet_membres WHERE projet_id = ? ORDER BY id', [$p]), 'id');
$taches = [
    // titre, membre, créée (jours), faite (jours)
    ['Choisir le sujet', 0, -20, -19], ['Lire les articles de référence', 1, -20, -15], ['Écrire le plan', 0, -19, -16], ['Préparer le matériel', 2, -18, -14],
    ['Réaliser les mesures du premier jour', 3, -14, -12], ['Réaliser les mesures du deuxième jour', 3, -14, -11], ['Saisir les résultats dans le tableur', 2, -12, -9],
    ['Tracer les graphiques', 1, -10, -7], ['Rédiger l\'introduction', 0, -10, -6], ['Rédiger la méthode', 2, -9, -5], ['Analyser les résultats', 1, -8, -4],
    ['Rédiger la discussion', 0, -8, -3], ['Relire la méthode', 3, -6, -2], ['Faire la bibliographie', 2, -6, -2], ['Mettre en page le rapport', null, -5, -1],
    ['Préparer le diaporama', 1, -4, -1], ['Répéter la présentation', 0, -3, 0], ['Imprimer le rapport', null, -2, 0], ['Envoyer le rapport au professeur', 0, -1, 0],
];
foreach ($taches as [$titre, $qui, $cree, $fait]) {
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, statut, created_at, fait_le) VALUES (?, ?, ?, ?, ?, ?)',
        [$p, $titre, $qui === null ? null : $m[$qui], 'fait', $jour($cree) . ' 10:00:00', $jour($fait) . ' 18:00:00']);
}

// Des points à cocher sur quelques tâches (cochés par Alma, ou par personne d'autre : le compte de l'essai est le seul qui se connecte).
foreach (['Écrire le plan' => ['Titres', 'Ordre des parties', 'Relecture'], 'Tracer les graphiques' => ['Axes', 'Légendes'], 'Préparer le diaporama' => ['Plan des diapos', 'Visuels', 'Notes']] as $titre => $pts) {
    $tid = (int) bd_valeur('SELECT id FROM projet_taches WHERE projet_id = ? AND titre = ?', [$p, $titre]);
    foreach ($pts as $k => $texte) {
        bd_run('INSERT INTO projet_points (tache_id, texte, fait, fait_par, fait_le, position, created_at) VALUES (?, ?, 1, ?, ?, ?, ?)', [$tid, $texte, $a, $jour(-10 + $k) . ' 15:00:00', $k + 1, $jour(-18) . ' 10:00:00']);
    }
}

$base = 'http://localhost/mon_appli/appli/';
$jar = tempnam(sys_get_temp_dir(), 'rv');
$appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
    usleep(250000);
    $h = curl_init($base . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $x) === 1 ? $x[1] : '';
$appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
if (isset($argv[1]) && $argv[1] !== '--efface') {
    file_put_contents($argv[1], $appel('travaux/' . $p . '/recapitulatif'));
    echo "PDF écrit dans " . $argv[1] . "\n";
}
$session = '';
foreach (file($jar) as $l) { if (str_contains($l, 'MESCOURS_SESSID')) { $session = trim(substr($l, strrpos($l, "\t") + 1)); } }
echo "projet $p\nMESCOURS_SESSID=$session\n";
