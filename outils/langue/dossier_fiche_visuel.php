<?php
/*
 * Prépare le contrôle visuel de la fiche de révision d'un dossier : un compte d'essai, un dossier avec des cours, un lien, un renvoi, une image jointe,
 * des cartes, une carte mentale, et la session du compte, que le navigateur reprend (aucun mot de passe n'est saisi).
 *
 *     php outils/langue/dossier_fiche_visuel.php            prépare et affiche l'identifiant de session
 *     php outils/langue/dossier_fiche_visuel.php --efface   retire le compte d'essai (et le fichier joint du disque)
 */
require __DIR__ . '/base.php';

$email = 'dfi-vis@exemple-test.fr';
$racine = dirname(__DIR__, 2);
$efface = static function () use ($email, $racine): void {
    $u = bd_valeur('SELECT id FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
    if ($u !== null) {
        foreach (bd_all('SELECT nom_stocke FROM fichiers WHERE user_id = ?', [$u]) as $f) { @unlink($racine . '/storage/uploads/' . basename((string) $f['nom_stocke'])); }
        bd_run('DELETE FROM users WHERE id = ?', [$u]);
    }
};
$efface();
if (in_array('--efface', $argv, true)) { echo "retiré\n"; return; }

bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$email, 'Alma', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai']);
$u = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$u, 'Architecture logicielle', '#8e24aa']);
$m = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ?', [$u]);
bd_run('INSERT INTO dossiers (user_id, matiere_id, nom, icone) VALUES (?, ?, ?, ?)', [$u, $m, 'Architecture', '🏛️']);
$d = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$u]);
bd_run('INSERT INTO dossiers (user_id, parent_id, nom, icone) VALUES (?, ?, ?, ?)', [$u, $d, 'Annexes', '📎']);
foreach ([['Cours de conception', null], ['Architecture hexagonale', 'Ports et adaptateurs.']] as [$titre, $fiche]) {
    bd_run('INSERT INTO cours (user_id, dossier_id, titre, contenu, fiche_revision) VALUES (?, ?, ?, ?, ?)', [$u, $d, $titre, 'Du texte.', $fiche]);
}
$c = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id LIMIT 1', [$u]);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$u, 'Algèbre linéaire', 'Matrices.']);
bd_run('INSERT INTO cartes (user_id, cours_id, question, reponse, origine, empreinte, boite, revoir_le) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())', [$u, $c, 'Qu\'est-ce qu\'un port ?', 'Une interface.', 'main', md5('p'), 2]);
bd_run('INSERT INTO cartes_mentales (user_id, cours_id, titre, arbre) VALUES (?, ?, ?, ?)', [$u, $c, 'Les couches', json_encode(['t' => 'Couches', 'c' => [['t' => 'Domaine', 'c' => []], ['t' => 'Ports', 'c' => []]]])]);

$base = 'http://localhost/mon_appli/appli/';
$jar = tempnam(sys_get_temp_dir(), 'dv');
$appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
    usleep(250000);
    $h = curl_init($base . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, $post); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $mm) === 1 ? $mm[1] : '';
$appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
$csrf = $jeton($appel('cours'));
$appel("dossiers/$d/revision", ['_csrf' => $csrf, 'fiche_revision' => 'À retenir de tout le dossier : le cœur ne dépend de rien.']);
$appel("dossiers/$d/revision/elements", ['_csrf' => $csrf, 'type' => 'lien', 'url' => 'https://alistair.cockburn.us/hexagonal-architecture/', 'libelle' => 'Article de Cockburn']);
$autre = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$u, 'Algèbre linéaire']);
$appel("dossiers/$d/revision/elements", ['_csrf' => $csrf, 'type' => 'cours', 'cible' => $autre, 'libelle' => 'Pour les matrices de passage']);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$temp = tempnam(sys_get_temp_dir(), 'dv');
file_put_contents($temp, $png);
$appel("dossiers/$d/revision/fichiers", ['_csrf' => $csrf, 'fichiers[]' => new CURLFile($temp, 'image/png', 'schema-hexagonal.png')]);
@unlink($temp);
// Un quizz : une page .html dont le script écrit dans la page (pour voir qu'il tourne, enfermé)
$quizz = '<!doctype html><html><body style="font-family:sans-serif"><h2>Quizz d\'essai</h2><p id="etat">script inactif</p><button onclick="document.getElementById(\'r\').textContent=\'Bravo, bonne réponse !\'">Répondre : 2 + 2 = 4</button><p id="r"></p>'
    . '<script>document.getElementById("etat").textContent="script actif — origine : " + (location.origin==="null"?"à part (enfermée)":location.origin);</script></body></html>';
$tq = tempnam(sys_get_temp_dir(), 'dv');
file_put_contents($tq, $quizz);
$appel("dossiers/$d/revision/fichiers", ['_csrf' => $csrf, 'fichiers[]' => new CURLFile($tq, 'text/html', 'quizz-architecture.html')]);
@unlink($tq);

$session = '';
foreach (file($jar) as $l) { if (str_contains($l, 'MESCOURS_SESSID')) { $session = trim(substr($l, strrpos($l, "\t") + 1)); } }
@unlink($jar);
echo "dossier $d\nsession $session\n";
