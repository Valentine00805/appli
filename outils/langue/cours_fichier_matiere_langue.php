<?php
/**
 * La matière d'un « simple » cours importé — un fichier gardé comme tel (HTTP + base) : une pastille « ＋ Matière » sur sa carte et un bouton dans son
 * aperçu, une fenêtre pour choisir, et rien d'autre ne change : le fichier ne s'ouvre pas comme un cours, n'en devient pas un, garde son titre.
 * Seules MES matières se proposent ; un cours ordinaire ou celui d'un autre ne se règle pas par ici (404) ; les quatre langues.
 *
 * Deux comptes d'essai : A (le propriétaire), B (un autre compte).
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

$emails = ['cfm-a@exemple-test.fr', 'cfm-b@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, 'Cfm_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai matière']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
foreach ([[$idA, 'Architecture', '#8e24aa'], [$idA, 'Maths', '#1e88e5'], [$idB, 'Matière de B', '#43a047']] as [$u, $nom, $couleur]) {
    bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$u, $nom, $couleur]);
}
$matiere = static fn (int $u, string $nom): int => (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$u, $nom]);
[$archi, $maths, $deB] = [$matiere($idA, 'Architecture'), $matiere($idA, 'Maths'), $matiere($idB, 'Matière de B')];

bd_run('INSERT INTO cours (user_id, titre, contenu, est_fichier) VALUES (?, ?, NULL, 1)', [$idA, 'Cours_Architecture_logicielle.pdf']);
$seul = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND est_fichier = 1', [$idA]);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Un cours ordinaire', 'Du texte.']);
$ordinaire = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND est_fichier = 0', [$idA]);
bd_run('INSERT INTO cours (user_id, titre, contenu, est_fichier) VALUES (?, ?, NULL, 1)', [$idB, 'Fichier de B.pdf']);
$seulB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND est_fichier = 1', [$idB]);
foreach ([[$idA, $seul, 'Cours_Architecture_logicielle.pdf', 'zz_cfm_a.pdf'], [$idB, $seulB, 'Fichier de B.pdf', 'zz_cfm_b.pdf']] as [$u, $c, $nom, $stocke]) {
    bd_run('INSERT INTO fichiers (user_id, cours_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?)', [$u, $c, $nom, $stocke, 'application/pdf', 1150000]);
}
$fichier = (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$seul]);

$cookies = [$emails[0] => __DIR__ . '/ck_cfm_a.txt', $emails[1] => __DIR__ . '/ck_cfm_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$ligne = static fn (int $c): array => bd_all('SELECT matiere_id, titre, contenu, est_fichier, dossier_id FROM cours WHERE id = ?', [$c])[0] ?? [];

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    [$page] = $appel($a, 'cours');
    $csrfA = $jeton($page);
    [$pageB] = $appel($b, 'cours');
    $csrfB = $jeton($pageB);
    $urlMatiere = 'cours/' . $seul . '/matiere';

    echo "\n1. La carte du fichier, dans « Mes cours »\n";
    $carte = preg_match('#<div class="carte cours-carte cours-carte--fichier" data-cours="' . $seul . '">.*?</div>\s*(?:<a class="[^"]*cours-carte__matiere[^"]*".*?</a>)?\s*<a class="cours-carte__cours#s', $page, $m) ? $m[0] : '';
    $dire('sans matière : un lien discret « ＋ Matière » (fenêtre, avec la page où revenir), à côté de « Ouvrir comme un cours »',
        $oui(preg_match('#<a class="cours-carte__matiere cours-carte__matiere--vide" href="[^"]*/cours/' . $seul . '/matiere\?retour=[^"]*" data-fenetre>＋ Matière</a>#u', $page) === 1 && str_contains($page, 'cours/' . $seul . '"')), 'oui');
    $dire('un cours ordinaire n\'a rien de tel (sa matière se règle dans le cours lui-même)', $oui(!str_contains($page, 'cours/' . $ordinaire . '/matiere')), 'oui');

    echo "\n2. La fenêtre\n";
    [$form] = $appel($a, $urlMatiere . '?fenetre=1&retour=' . rawurlencode('/mon_appli/appli/cours?essai=1'));
    $dire('le choix : « Aucune », mes deux matières (pas celle de B), le nom du fichier, la page où revenir',
        $oui(str_contains($form, 'name="matiere_id"') && str_contains($form, '— Aucune —') && str_contains($form, 'value="' . $archi . '"') && str_contains($form, 'value="' . $maths . '"') && !str_contains($form, 'value="' . $deB . '"')
            && str_contains($form, 'Cours_Architecture_logicielle.pdf') && str_contains($form, 'name="retour" value="/mon_appli/appli/cours?essai=1"') && !str_contains($form, '<html') && str_contains($form, 'data-envoi-fenetre') && str_contains($form, 'data-fermer')), 'oui');
    $dire('et il dit que le fichier reste un simple fichier', $oui(str_contains($form, 'Le fichier reste un simple fichier')), 'oui');
    [, $codeB1] = $appel($b, $urlMatiere);
    [, $codeO] = $appel($a, 'cours/' . $ordinaire . '/matiere');
    $dire('le fichier d\'un autre compte, ou un cours ordinaire : 404', $codeB1 . ' · ' . $codeO, '404 · 404');

    echo "\n3. Choisir une matière\n";
    [, , $adresse] = $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $archi, 'retour' => '/mon_appli/appli/cours?essai=2']);
    $avant = $ligne($seul);
    $dire('« Architecture » est enregistrée, et l\'on revient sur la page de « Mes cours » où l\'on était', $avant['matiere_id'] . ' · ' . $oui(str_ends_with($adresse, '/cours?essai=2')), $archi . ' · oui');
    $dire('le fichier reste un fichier : même titre, sans contenu, toujours « seulement fichier », même fichier', $avant['titre'] . ' · ' . var_export($avant['contenu'], true) . ' · ' . $avant['est_fichier'] . ' · ' . bd_valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ? AND id = ?', [$seul, $fichier]), 'Cours_Architecture_logicielle.pdf · NULL · 1 · 1');
    $dire('et l\'utilisateur n\'a toujours que ses deux cours (rien de créé)', (string) bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$idA]), '2');
    [$apres] = $appel($a, 'cours');
    $dire('la carte montre la pastille « Architecture » (lien de modification, couleur de la matière), plus « ＋ Matière »',
        $oui(preg_match('~<a class="pastille cours-carte__matiere" href="[^"]*/cours/' . $seul . '/matiere\?retour=[^"]*" data-fenetre style="background:#8e24aa;[^"]*"\s+title="Changer la matière « Architecture »">Architecture</a>~u', $apres) === 1 && !str_contains($apres, 'cours-carte__matiere--vide')), 'oui');
    [$filtre] = $appel($a, 'cours?matiere=' . $archi);
    [$autreFiltre] = $appel($a, 'cours?matiere=' . $maths);
    $dire('filtrer « Mes cours » par cette matière y retrouve le fichier (pas sous « Maths »)', $oui(str_contains($filtre, 'Cours_Architecture_logicielle.pdf') && !str_contains($autreFiltre, 'Cours_Architecture_logicielle.pdf')), 'oui');
    [, , $adresse2] = $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $maths]);
    $dire('sans « retour » (depuis l\'aperçu) : on revient sur l\'aperçu du fichier, et « Maths » est enregistrée', $oui(str_ends_with($adresse2, '/fichiers/' . $fichier . '/apercu')) . ' · ' . $ligne($seul)['matiere_id'], 'oui · ' . $maths);
    [, , $adresse3] = $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $archi, 'retour' => 'http://exemple.invalid/piege']);
    $dire('une page de retour qui sort du site est ignorée (retour à l\'aperçu)', $oui(!str_contains($adresse3, 'invalid') && str_contains($adresse3, '/apercu')), 'oui');
    $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => '', 'retour' => '/mon_appli/appli/cours']);
    $dire('« Aucune » la retire', var_export($ligne($seul)['matiere_id'], true), 'NULL');

    echo "\n4. Ce qu'on ne peut pas faire\n";
    $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $maths, 'retour' => '/mon_appli/appli/cours']);
    [$refus] = $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $deB]);
    $dire('la matière d\'un autre compte : refusée (« introuvable »), et le fichier garde « Maths »', $oui(str_contains($refus, 'Cette matière est introuvable : rien n’a été changé.')) . ' · ' . $ligne($seul)['matiere_id'], 'oui · ' . $maths);
    $appel($b, $urlMatiere, ['_csrf' => $csrfB, 'matiere_id' => (string) $deB]);
    $appel($a, 'cours/' . $ordinaire . '/matiere', ['_csrf' => $csrfA, 'matiere_id' => (string) $archi]);
    $dire('B ne règle pas le fichier de A, et un cours ordinaire ne se règle pas par ici', $ligne($seul)['matiere_id'] . ' · ' . var_export($ligne($ordinaire)['matiere_id'], true), $maths . ' · NULL');
    [, $codeSansJeton] = $appel($a, $urlMatiere, ['matiere_id' => (string) $archi]);
    $dire('sans le jeton de sécurité (CSRF), la demande est refusée (400) et rien ne change', $ligne($seul)['matiere_id'] . ' · ' . $codeSansJeton, $maths . ' · 400');

    echo "\n5. L'aperçu du fichier\n";
    [$apercu] = $appel($a, 'fichiers/' . $fichier . '/apercu');
    $dire('en page : le bouton « 🏷️ Matière » (avec la page où revenir) et la pastille « Maths » sous le titre',
        $oui(preg_match('~<a class="bouton bouton--secondaire" href="[^"]*/cours/' . $seul . '/matiere\?retour=[^"]*"\s+data-fenetre>\s*🏷️ Matière~u', $apercu) === 1 && preg_match('~<span class="pastille" style="background:#1e88e5;[^"]*">Maths</span>~', $apercu) === 1), 'oui');
    [$apercuFenetre] = $appel($a, 'fichiers/' . $fichier . '/apercu?fenetre=1');
    $dire('dans une fenêtre : le bouton s\'ouvre par-dessus, et relit la page derrière à la fermeture', $oui(str_contains($apercuFenetre, 'data-fenetre-dessus data-relire-derriere') && str_contains($apercuFenetre, '/cours/' . $seul . '/matiere"')), 'oui');
    bd_run('INSERT INTO fichiers (user_id, cours_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?)', [$idA, $ordinaire, 'annexe.pdf', 'zz_cfm_c.pdf', 'application/pdf', 1000]);
    $annexe = (int) bd_valeur('SELECT id FROM fichiers WHERE nom_origine = ?', ['annexe.pdf']);
    [$apercuAnnexe] = $appel($a, 'fichiers/' . $annexe . '/apercu');
    $dire('la pièce jointe d\'un cours ordinaire n\'a pas ce bouton (sa matière est celle du cours)', $oui(!str_contains($apercuAnnexe, 'cours/' . $ordinaire . '/matiere')), 'oui');

    echo "\n6. Les quatre langues\n";
    [$pCompte] = $appel($a, 'compte');
    $tokenCompte = $jeton($pCompte);
    foreach (['en' => ['＋ Subject', 'File subject', 'Subject'], 'es' => ['＋ Asignatura', 'Asignatura del archivo', 'Asignatura'], 'de' => ['＋ Fach', 'Fach der Datei', 'Fach'], 'fr' => ['＋ Matière', 'Matière du fichier', 'Matière']] as $langue => [$ajouter, $titre, $bouton]) {
        $appel($a, 'compte/langue', ['_csrf' => $tokenCompte, 'langue' => $langue]);
        $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => '', 'retour' => '/mon_appli/appli/cours']);
        [$liste] = $appel($a, 'cours');
        [$fen] = $appel($a, $urlMatiere . '?fenetre=1');
        $appel($a, $urlMatiere, ['_csrf' => $csrfA, 'matiere_id' => (string) $maths, 'retour' => '/mon_appli/appli/cours']);
        [$ap] = $appel($a, 'fichiers/' . $fichier . '/apercu');
        $tout = html_entity_decode($liste . $fen . $ap, ENT_QUOTES, 'UTF-8');
        $manque = array_values(array_filter([$ajouter, $titre, '🏷️ ' . $bouton], static fn (string $mot): bool => !str_contains($tout, $mot)));
        $dire($langue . ' : « ' . $ajouter . ' », la fenêtre, le bouton de l\'aperçu', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : pas de clé brute', $oui(!preg_match('/\bcours\.fm\.[a-z_]+/', $tout)), 'oui');
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
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'cfm-_@exemple-test.fr'")
        . ' · fichiers d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM fichiers WHERE nom_stocke LIKE 'zz\\_cfm\\_%'") . "\n";
}
