<?php
/*
 * Prépare le contrôle visuel des listes à cocher : un compte d'essai, un travail de groupe avec trois tâches (dont deux à points) et deux listes,
 * et la session du compte, que le navigateur reprend.
 *
 *     php outils/langue/listes_visuel.php            prépare
 *     php outils/langue/listes_visuel.php --efface   retire le compte d'essai (et son projet)
 */
require __DIR__ . '/base.php';

$email = 'lis-vis-a@exemple-test.fr';
$efface = static function () use ($email): void {
    bd_run('DELETE p FROM projets p JOIN users u ON u.id = p.cree_par WHERE u.email = ? AND u.email LIKE ?', [$email, '%@exemple-test.fr']);
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
};
$efface();
if (in_array('--efface', $argv, true)) { echo "retiré\n"; return; }

bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$email, 'Alma', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai']);
$a = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Rapport de labo', $a, bin2hex(random_bytes(16))]);
$p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ?', [$a]);
bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$p, $a]);
$membre = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ?', [$p]);
foreach ([['Rédiger l’introduction', 'a_faire'], ['Préparer les figures', 'en_cours'], ['Relire le plan', 'fait']] as [$titre, $statut]) {
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, statut) VALUES (?, ?, ?, ?)', [$p, $titre, $membre, $statut]);
}
$taches = array_column(bd_all('SELECT id FROM projet_taches WHERE projet_id = ? ORDER BY id', [$p]), 'id');
foreach ([[0, [['Contexte', 1], ['Problématique', 1], ['Annonce du plan', 0], ['Relire à voix haute', 0], ['Vérifier les sources', 0]]],
          [1, [['Graphique 1', 1], ['Graphique 2', 0], ['Légendes', 0]]], [2, [['Plan en trois parties', 1], ['Titres définitifs', 1]]]] as [$i, $pts]) {
    foreach ($pts as $n => [$texte, $fait]) {
        bd_run('INSERT INTO projet_points (tache_id, texte, fait, fait_par, fait_le, position, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$taches[$i], $texte, $fait, $fait ? $a : null, $fait ? date('Y-m-d H:i:s') : null, $n + 1]);
    }
}
foreach ([['Matériel à apporter', [['Blouse', 1], ['Lunettes de protection', 1], ['Cahier de laboratoire', 0], ['Calculatrice', 0]]], ['Questions à poser au professeur', []]] as $n => [$titre, $pts]) {
    bd_run('INSERT INTO projet_listes (projet_id, titre, cree_par, position, created_at) VALUES (?, ?, ?, ?, NOW())', [$p, $titre, $a, $n + 1]);
    $l = (int) bd_valeur('SELECT id FROM projet_listes WHERE projet_id = ? AND titre = ?', [$p, $titre]);
    foreach ($pts as $k => [$texte, $fait]) {
        bd_run('INSERT INTO projet_points (liste_id, texte, fait, fait_par, fait_le, position, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$l, $texte, $fait, $fait ? $a : null, $fait ? date('Y-m-d H:i:s') : null, $k + 1]);
    }
}

$base = 'http://localhost/mon_appli/appli/';
$jar = tempnam(sys_get_temp_dir(), 'lv');
$appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
    usleep(250000);
    $h = curl_init($base . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';
$appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
$session = '';
foreach (file($jar) as $l) { if (str_contains($l, 'MESCOURS_SESSID')) { $session = trim(substr($l, strrpos($l, "\t") + 1)); } }
echo "projet $p\nMESCOURS_SESSID=$session\n";
