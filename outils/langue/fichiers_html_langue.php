<?php
/**
 * Les pages web (.html) parmi les pièces jointes d'un cours (HTTP + base) : acceptées au dépôt, jamais exécutées — servies dans un bac à sable
 * (« Content-Security-Policy: sandbox », sans script, sans accès au site), aperçu dans un cadre sans permission, téléchargeables telles quelles ;
 * le reste (SVG, type inconnu) garde sa règle d'avant, et un autre compte ne lit rien.
 *
 * Deux comptes d'essai : A (dépose), B (étranger).
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['fhtml-a@exemple-test.fr', 'fhtml-b@exemple-test.fr'];
$dossierStockage = dirname(__DIR__, 2) . '/storage/uploads';
$nettoyer = static function () use ($emails, $dossierStockage): void {
    foreach ($emails as $e) {
        $id = bd_valeur('SELECT id FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
        if ($id === null || $id === false) { continue; }
        foreach (bd_all('SELECT nom_stocke FROM fichiers WHERE user_id = ?', [$id]) as $f) {
            $chemin = $dossierStockage . '/' . basename((string) $f['nom_stocke']);
            if (is_file($chemin)) { @unlink($chemin); }
        }
        bd_run('DELETE FROM users WHERE id = ?', [$id]);
    }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Fhtml_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai html']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Architecture logicielle', null]);
$cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);

$cookies = [$emails[0] => __DIR__ . '/ck_fhtml_a.txt', $emails[1] => __DIR__ . '/ck_fhtml_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
$temporaires = [];
/** @return array{0: string, 1: int, 2: string, 3: array<string, string>} corps, code, adresse finale, en-têtes (en minuscules) */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, $post); }
    $brut = (string) curl_exec($h);
    $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
    $entetes = [];
    foreach (explode("\r\n", substr($brut, 0, $taille)) as $ligne) {
        if (str_contains($ligne, ':')) { [$cle, $valeur] = explode(':', $ligne, 2); $entetes[strtolower(trim($cle))] = trim($valeur); }
    }
    $r = [substr($brut, $taille), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), $entetes];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$fichier = static function (string $nom, string $contenu, string $type = 'text/html') use (&$temporaires): CURLFile {
    $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fhtml_' . bin2hex(random_bytes(4)) . '_' . $nom;
    file_put_contents($chemin, $contenu);
    $temporaires[] = $chemin;
    return new CURLFile($chemin, $type, $nom);
};
$idFichier = static fn (string $nom): int => (int) bd_valeur('SELECT id FROM fichiers WHERE user_id = ? AND nom_origine = ?', [$GLOBALS['idA'], $nom]);

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    [$page] = $appel($a, 'cours/' . $cours);
    $csrf = $jeton($page);

    $malin = "<!doctype html><html><head><meta charset=\"utf-8\"><title>Archi</title><style>h1{color:#c00}</style></head><body>\n"
        . "<h1>Architecture hexagonale</h1><p>Ports et adaptateurs.</p>\n"
        . "<script>fetch('/mon_appli/appli/compte').then(r => r.text()).then(t => { document.title = 'VOLE'; });</script>\n"
        . "<img src=\"https://exemple.invalid/pixel.gif\"><form action=\"https://exemple.invalid/\"><input name=\"x\"></form></body></html>";

    echo "\n1. Le dépôt\n";
    $appel($a, 'cours/' . $cours . '/fichiers', ['_csrf' => $csrf, 'fichiers[0]' => $fichier('architecture-hexagonale.html', $malin), 'fichiers[1]' => $fichier('vieux.htm', '<p>vieux</p>')]);
    $html = $idFichier('architecture-hexagonale.html');
    $htm = $idFichier('vieux.htm');
    $dire('« .html » et « .htm » sont acceptés (plus de « type de fichier non autorisé »)', $oui($html > 0 && $htm > 0), 'oui');
    [$refus] = $appel($a, 'cours/' . $cours . '/fichiers', ['_csrf' => $csrf, 'fichiers[0]' => $fichier('script.php', '<?php echo 1;', 'text/plain'), 'fichiers[1]' => $fichier('appli.exe', 'MZ', 'application/octet-stream')]);
    $dire('mais un .php ou un .exe reste refusé', $oui($idFichier('script.php') === 0 && $idFichier('appli.exe') === 0), 'oui');
    $stocke = (string) bd_valeur('SELECT nom_stocke FROM fichiers WHERE id = ?', [$html]);
    $dire('rangé sous un nom au hasard, avec son extension, dans un dossier que le web ne sert pas', $oui(preg_match('/^[0-9a-f]{32}\.html$/', $stocke) === 1 && is_file($dossierStockage . '/' . $stocke)), 'oui');
    [, $codeDirect] = $appel($a, 'storage/uploads/' . $stocke);
    $dire('et l\'adresse directe du fichier sur le disque est refusée (le web ne le sert pas)', $oui(in_array($codeDirect, [403, 404], true)), 'oui');

    echo "\n2. Le service : enfermé dans un bac à sable\n";
    [$corps, $code, , $e] = $appel($a, 'fichiers/' . $html);
    $csp = $e['content-security-policy'] ?? '';
    $dire('la page est servie (200, text/html, affichée en ligne, sans reniflage de type)', $code . ' · ' . $oui(str_starts_with($e['content-type'] ?? '', 'text/html') && str_starts_with($e['content-disposition'] ?? '', 'inline') && ($e['x-content-type-options'] ?? '') === 'nosniff'), '200 · oui');
    $dire('sa politique : « sandbox » (pas de allow-scripts, pas de allow-same-origin), rien d\'autorisé par défaut, images seulement incluses (data:)',
        $oui(preg_match('/^sandbox;/', $csp) === 1 && !str_contains($csp, 'allow-') && str_contains($csp, "default-src 'none'") && str_contains($csp, 'img-src data:') && !str_contains($csp, 'script-src') && !str_contains($csp, 'https:') && !str_contains($csp, "'self'")), 'oui');
    $dire('le contenu est rendu tel quel (le script est dans le fichier, mais le navigateur ne l\'exécutera pas)', $oui(str_contains($corps, 'Architecture hexagonale') && str_contains($corps, '<script>')), 'oui');
    [$corpsHtm, , , $eHtm] = $appel($a, 'fichiers/' . $htm);
    $dire('« .htm » : même enfermement', $oui(str_starts_with($eHtm['content-security-policy'] ?? '', 'sandbox;') && str_contains($corpsHtm, 'vieux')), 'oui');
    [, $codeT, , $eT] = $appel($a, 'fichiers/' . $html . '?telecharger=1');
    $dire('« ?telecharger=1 » : téléchargé (attachment) avec son nom, pas affiché', $codeT . ' · ' . $oui(str_starts_with($eT['content-disposition'] ?? '', 'attachment') && str_contains($eT['content-disposition'] ?? '', 'architecture-hexagonale.html')), '200 · oui');
    [, $codeB] = $appel($b, 'fichiers/' . $html);
    [, $codeBa] = $appel($b, 'fichiers/' . $html . '/apercu');
    $dire('un autre compte ne lit ni la page ni son aperçu (404)', $codeB . ' · ' . $codeBa, '404 · 404');

    echo "\n3. L'aperçu\n";
    [$apercu, $codeA] = $appel($a, 'fichiers/' . $html . '/apercu');
    $dire('l\'aperçu : la page répond', (string) $codeA, '200');
    $dire('un cadre vers le fichier (enfermé par le serveur), sans lien de référent', $oui(preg_match('#<iframe src="[^"]*/fichiers/' . $html . '" referrerpolicy="no-referrer"#', $apercu) === 1), 'oui');
    $dire('la mention « scripts et liens désactivés », et le script du fichier n\'est pas recopié dans la page', $oui(str_contains($apercu, 'scripts et ses liens sont désactivés')) . ' · ' . $oui(!str_contains($apercu, '<script>fetch')), 'oui · oui');
    $dire('et il dit « page web (HTML) » et propose de télécharger', $oui(str_contains($apercu, 'Page web (HTML)') || str_contains($apercu, 'page web (HTML)')), 'oui');
    [$fenetre] = $appel($a, 'fichiers/' . $html . '/apercu?fenetre=1');
    $dire('dans une fenêtre : le contenu seul, avec le même cadre', $oui(!str_contains($fenetre, '<html') && str_contains($fenetre, 'referrerpolicy="no-referrer"')), 'oui');
    [$fiche] = $appel($a, 'cours/' . $cours);
    $dire('la fiche du cours liste la page web avec son icône 🌐 et le lien vers l\'aperçu', $oui(str_contains($fiche, '🌐') && str_contains($fiche, 'fichiers/' . $html . '/apercu')), 'oui');

    // Dans la FICHE de révision : la page se lit sur place, dans un cadre (le serveur l'enferme)
    $appel($a, 'cours/' . $cours . '/revision/fichiers', ['_csrf' => $csrf, 'fichiers[0]' => $fichier('page-fiche.html', '<p>Dans la fiche</p><script>alert(1)</script>', 'text/html')]);
    $dansFiche = $idFichier('page-fiche.html');
    [$ficheRev] = $appel($a, 'revision/' . $cours . '?fenetre=1');
    $dire('une page web déposée dans la fiche de révision s\'y lit dans un cadre, sans attribut « sandbox » (l\'enfermement vient du serveur), avec sa mention ; la zone de dépôt la cite',
        $oui(preg_match('#<span class="fichier__web">\s*<iframe src="[^"]*/fichiers/' . $dansFiche . '" referrerpolicy="no-referrer" loading="lazy"\s+title="page-fiche.html"></iframe>#', $ficheRev) === 1
            && !str_contains($ficheRev, 'sandbox=') && str_contains($ficheRev, 'Page web en lecture seule') && str_contains($ficheRev, 'page web (.html)') && !str_contains($ficheRev, '<script>alert(1)')), 'oui');
    [, , , $eFiche] = $appel($a, 'fichiers/' . $dansFiche);
    $dire('et ce fichier de la fiche reste servi enfermé (sandbox)', $oui(str_starts_with($eFiche['content-security-policy'] ?? '', 'sandbox;')), 'oui');

    echo "\n4. Ce qui n'a pas changé\n";
    $appel($a, 'cours/' . $cours . '/fichiers', ['_csrf' => $csrf, 'fichiers[0]' => $fichier('dessin.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'image/svg+xml'), 'fichiers[1]' => $fichier('note.txt', 'bonjour', 'text/plain')]);
    [, , , $eSvg] = $appel($a, 'fichiers/' . $idFichier('dessin.svg'));
    $dire('un SVG reste forcé en téléchargement (attachment)', $oui(str_starts_with($eSvg['content-disposition'] ?? '', 'attachment')), 'oui');
    [$txt, , , $eTxt] = $appel($a, 'fichiers/' . $idFichier('note.txt'));
    $dire('un .txt reste affiché en texte, sans bac à sable', $oui(str_starts_with($eTxt['content-type'] ?? '', 'text/plain') && !str_contains($eTxt['content-security-policy'] ?? '', 'sandbox') && $txt === 'bonjour'), 'oui');

    echo "\n5. Les quatre langues\n";
    [$pCompte] = $appel($a, 'compte');
    $tokenCompte = $jeton($pCompte);
    foreach (['en' => 'web page (HTML)', 'es' => 'página web (HTML)', 'de' => 'Webseite (HTML)', 'fr' => 'page web (HTML)'] as $langue => $attendu) {
        $appel($a, 'compte/langue', ['_csrf' => $tokenCompte, 'langue' => $langue]);
        [$pl] = $appel($a, 'fichiers/' . $html . '/apercu');
        $dire($langue . ' : « ' . $attendu . ' » et la mention de sécurité, sans clé brute', $oui(stripos($pl, $attendu) !== false && !preg_match('/\b(fmt|ap)\.html[a-z_]*/', $pl)), 'oui');
    }
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    $nettoyer();
    foreach ($temporaires as $chemin) { @unlink($chemin); }
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'fhtml-_@exemple-test.fr'")
        . ' · fichiers d’essai restants ' . count(glob($dossierStockage . '/*') ?: []) . " dans storage/uploads (dont ceux d'avant)\n";
}
