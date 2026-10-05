<?php
/**
 * Les photos de profil qu'un clic agrandit : marquées (« data-zoom-avatar », au clavier aussi) dans les réglages du compte, le profil
 * d'un ami et les réglages d'un groupe — et seulement quand il y a une photo, jamais là où l'avatar est dans un lien ou une case
 * (en-tête et liste de discussions, choix des amis à ajouter). Le script de la visionneuse est chargé avec toutes les pages.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-72s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'avt-a@exemple-test.fr', 'pseudo' => 'Avt_un'],
    'b' => ['email' => 'avt-b@exemple-test.fr', 'pseudo' => 'Avt_deux'],     // a une photo
    'c' => ['email' => 'avt-c@exemple-test.fr', 'pseudo' => 'Avt_trois'],    // n'en a pas
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$ck = static fn (string $c): string => __DIR__ . '/ck_avt_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): string {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    unset($h);
    return $corps;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
/** Les avatars zoomables d'une page. */
$zoomables = static fn (string $html): int => substr_count($html, 'data-zoom-avatar');

$groupe = null;
try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        $p = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        $csrf[$c] = $jeton($appel($c, 'compte'));
    }
    foreach (['b', 'c'] as $autre) {
        $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $id($autre)]);
        $appel($autre, 'amis/' . $id('a') . '/accepter', ['_csrf' => $csrf[$autre]]);
    }
    $appel('a', 'groupes', ['_csrf' => $csrf['a'], 'nom' => 'Groupe photo', 'membres' => [(string) $id('b'), (string) $id('c')]]);
    $groupe = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);

    echo "\n1. Sans photo, rien à agrandir\n";
    $profil = $appel('a', 'amis/' . $id('b') . '/profil?fenetre=1');
    $dire('le profil d\'un ami sans photo : pas d\'avatar zoomable', (string) $zoomables($profil), '0');
    $compte = $appel('a', 'compte');
    $dire('  mes réglages sans photo, ni mes amis sans photo', (string) $zoomables($compte), '0');
    $reglages = $appel('a', 'groupes/' . $groupe . '/reglages?fenetre=1');
    $dire('  les réglages d\'un groupe sans photo', (string) $zoomables($reglages), '0');

    echo "\n2. Avec une photo\n";
    // Une photo « posée » en base suffit à marquer l'avatar (le fichier n'est lu que quand le navigateur le demande).
    bd_run('UPDATE users SET photo_nom = ? WHERE id = ?', [str_repeat('b', 32) . '.jpg', $id('b')]);
    bd_run('UPDATE users SET photo_nom = ? WHERE id = ?', [str_repeat('a', 32) . '.jpg', $id('a')]);
    bd_run('UPDATE conversations SET photo_nom = ?, photo_mime = ? WHERE id = ?', [str_repeat('c', 32) . '.jpg', 'image/jpeg', $groupe]);
    $profil = $appel('a', 'amis/' . $id('b') . '/profil?fenetre=1');
    $dire('le profil d\'un ami : sa photo s\'agrandit (bouton, étiquette, au clavier)',
        $oui(preg_match('#<span class="avatar avatar--grand avatar--photo avatar--zoomable"[^>]*data-zoom-avatar role="button" tabindex="0" aria-label="Agrandir la photo"#', $profil) === 1), 'oui');
    $compte = $appel('a', 'compte');
    $dire('mes réglages : ma photo, et celles de mes amis (listes du calendrier et des partages)',
        $oui(preg_match('#avatar--apercu avatar--photo avatar--zoomable#', $compte) === 1) . ' · ' . $zoomables($compte), 'oui · 3');
    $reglages = $appel('a', 'groupes/' . $groupe . '/reglages?fenetre=1');
    $dire('les réglages d\'un groupe : la photo du groupe (en tête, et dans la zone de réglage) et celles des membres',
        $oui(preg_match('#avatar--groupe avatar--grand avatar--photo avatar--zoomable#', $reglages) === 1
            && preg_match('#avatar--groupe avatar--apercu avatar--photo avatar--zoomable#', $reglages) === 1) . ' · ' . $zoomables($reglages), 'oui · 4');
    $dire('  celui qui n\'a pas de photo n\'est pas zoomable : trois membres, deux zoomables',
        substr_count($reglages, 'data-compte-avatar') . ' · ' . substr_count($reglages, 'avatar--zoomable" data-compte-avatar'), '3 · 2');

    echo "\n3. Là où l'avatar est dans un lien ou une case, un clic ne l'agrandit pas\n";
    $conversation = $appel('a', 'amis/' . $id('b'));
    $dire('la conversation : en-tête (lien vers le profil) et liste des discussions', (string) $zoomables($conversation), '0');
    $groupePage = $appel('a', 'groupes/' . $groupe);
    $dire('  et celle d\'un groupe', (string) $zoomables($groupePage), '0');
    $nouvelle = $appel('a', 'groupes/' . $groupe . '/reglages?fenetre=1');
    $dire('  le choix des amis à ajouter (cases à cocher) : aucun avatar zoomable parmi eux',
        $oui(preg_match('#<label class="groupe-choix__ami">[^<]*<input[^>]*>\s*<span class="avatar[^"]*"[^>]*data-zoom-avatar#', $nouvelle) !== 1), 'oui');

    echo "\n4. Le script\n";
    $page = $appel('a', 'compte');
    $dire('chargé avec toutes les pages', $oui(preg_match('#assets/js/zoom-avatar\.js\?v=\d+#', $page) === 1), 'oui');
    $script = $appel('a', 'assets/js/zoom-avatar.js');
    $dire('  il écoute le clic et le clavier, ouvre une visionneuse fermée par sa croix, et n\'écrit rien avec « innerHTML »',
        $oui(str_contains($script, "'[data-zoom-avatar]'") && str_contains($script, "addEventListener('keydown'") && str_contains($script, 'showModal')
            && str_contains($script, "addEventListener('cancel'") && !str_contains($script, 'innerHTML')), 'oui');

    echo "\n5. Les quatre langues\n";
    foreach (['en' => 'Enlarge the photo', 'es' => 'Ampliar la foto', 'de' => 'Foto vergrößern', 'fr' => 'Agrandir la photo'] as $langue => $texte) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => $langue]);
        $profil = $appel('a', 'amis/' . $id('b') . '/profil?fenetre=1');
        $dire("$langue : « $texte »", $oui(str_contains($profil, 'aria-label="' . $texte . '"') && !str_contains($profil, 'prf.agrandir_photo')), 'oui');
    }
    $termine = true;
} finally {
    if ($groupe !== null && $groupe > 0) {
        bd_run('DELETE FROM conversations WHERE id = ?', [$groupe]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'avt-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
