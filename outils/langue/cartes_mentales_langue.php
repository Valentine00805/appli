<?php
/**
 * Les cartes mentales, de bout en bout : créer, enregistrer, nettoyer ce qui arrive, les limites, la carte écrite
 * par l'IA (contre un FAUX Gemini local), le cloisonnement entre comptes, les quatre langues.
 *
 * Le faux serveur est lancé ici ; l'application est redirigée vers lui par « config/parametres.test.php »
 * (un fichier que Config ne lit que depuis le poste, et que cette suite retire à la fin).
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-66s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 90), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$port = 8767;
$journal = sys_get_temp_dir() . '/faux_gemini_cm.log';

@unlink($fichierEssai);
file_put_contents($fichierEssai, "<?php\nreturn ['gemini' => ['adresse' => 'http://127.0.0.1:$port/v1beta/', 'pause_reessai' => 0]];\n");
$serveur = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/faux_gemini.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $journal, 'w'], 2 => ['file', $journal, 'a']], $tuyaux);
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $no, $str, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}
sleep(4);   // OPcache met un moment à voir « parametres.test.php »

$emails = ['cm-a@exemple-test.fr', 'cm-b@exemple-test.fr'];
foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
foreach ($emails as $i => $a) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, 'CarteMentale_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai cartes mentales']);
}
[$idA, $idB] = array_map(static fn (string $a): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$a]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu, fiche_revision) VALUES (?, ?, ?, ?)',
    [$idA, 'Cyber', "Le chiffrement protège la confidentialité des données.\n\nUn pare-feu filtre le trafic.", 'Fiche : penser à la défense en profondeur.']);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idB, 'Cours secret de B', 'Contenu privé de B.']);
$coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
$coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idB]);

$cookies = [$emails[0] => __DIR__ . '/ck_cm_a.txt', $emails[1] => __DIR__ . '/ck_cm_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} corps, adresse finale, code */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$dernier = static fn (): array => json_decode((string) @file_get_contents(sys_get_temp_dir() . '/faux_gemini_dernier.json'), true) ?: [];
// L'identifiant d'une carte, dans l'adresse de l'éditeur ou dans « Résumés IA » (« ?carte=… », qui l'ouvre en fenêtre).
$idCarte = static fn (string $url): int => preg_match('#/cartes-mentales/(\d+)$#', $url, $m) === 1 || preg_match('/[?&]carte=(\d+)/', $url, $m) === 1 ? (int) $m[1] : 0;
$nbCartes = static fn (int $cours): int => (int) bd_valeur('SELECT COUNT(*) FROM cartes_mentales WHERE cours_id = ?', [$cours]);
$arbreDe = static fn (int $id): array => json_decode((string) bd_valeur('SELECT arbre FROM cartes_mentales WHERE id = ?', [$id]), true) ?: [];
/** @return array{0: string, 1: int} */
$enregistrer = static function (string $compte, int $id, string $csrf, mixed $arbre, ?string $titre = null) use ($appel): array {
    $post = ['_csrf' => $csrf, 'arbre' => is_string($arbre) ? $arbre : json_encode($arbre, JSON_UNESCAPED_UNICODE)];
    if ($titre !== null) { $post['titre'] = $titre; }
    [$corps, , $code] = $appel($compte, 'cartes-mentales/' . $id, $post);
    return [$corps, $code];
};

try {
    [$a, $b] = $emails;
    [$p] = $appel($a, 'connexion');
    $appel($a, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $a, 'mot_de_passe' => 'MotDePasse!2026']);
    [$p] = $appel($b, 'connexion');
    $appel($b, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $b, 'mot_de_passe' => 'MotDePasse!2026']);
    [$fiche] = $appel($a, 'revision/' . $coursA);
    $csrf = $jeton($fiche);

    echo "\n1. La fiche, sans carte et sans clé\n";
    $dire('la fiche a son rayon « Cartes mentales », vide, avec un lien vers « Résumés IA » (on y crée, ici on retrouve)',
        $oui(str_contains($fiche, 'data-cartes-mentales') && str_contains($fiche, 'Aucune carte mentale pour ce cours.')
            && str_contains($fiche, '/resumes?cours=' . $coursA)), 'oui');
    $dire('  et aucun bouton de création dans la fiche',
        $oui(!str_contains($fiche, 'Nouvelle carte mentale') && !str_contains($fiche, 'Générer avec l’IA')), 'oui');
    [$resumes] = $appel($a, 'resumes');
    $dire('« Résumés IA » propose la carte vierge (sans clé) et la liste de ses cartes, vide',
        $oui(str_contains($resumes, 'Une carte mentale à remplir soi-même') && str_contains($resumes, 'action="/mon_appli/appli/cartes-mentales"')
            && str_contains($resumes, 'Aucune carte mentale pour l’instant.')), 'oui');
    $dire('  sans clé : pas de case « Carte mentale » (le formulaire d’écriture n’y est pas)', $oui(!str_contains($resumes, 'value="carte"')), 'oui');
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['carte']]);
    $dire('  et même en forçant la demande : « Il faut d’abord enregistrer votre clé », rien d’écrit',
        $oui(str_contains($r, 'enregistrer votre clé Gemini')) . ' · ' . $nbCartes($coursA), 'oui · 0');

    echo "\n2. Créer une carte vierge (depuis « Résumés IA »)\n";
    [$r, $url] = $appel($a, 'cartes-mentales', ['_csrf' => $csrf, 'cours' => $coursA]);
    $id = $idCarte($url);
    $dire('on revient à « Résumés IA », qui dit que la carte est créée et l’ouvre aussitôt en fenêtre',
        $oui($id > 0 && str_contains($url, '/resumes?carte=') && str_contains($r, 'Carte mentale créée.')
            && preg_match('#href="[^"]*/cartes-mentales/' . $id . '" data-fenetre data-ouvrir-auto#', $r) === 1) . ' · ' . $nbCartes($coursA), 'oui · 1');
    [$popup] = $appel($a, 'cartes-mentales/' . $id . '?fenetre=1');
    $dire('  demandée en fenêtre : un fragment (sans bandeau, sans lien de retour), large, avec son éditeur et sans script',
        $oui(str_contains($popup, 'data-carte-mentale') && !str_contains($popup, '<header class="entete"') && !str_contains($popup, 'Retour à la fiche')
            && str_contains($popup, 'data-large data-document') && !str_contains($popup, 'carte-mentale.js')
            && substr_count($popup, 'data-envoi-fenetre') === 1), 'oui');
    [$r] = $appel($a, 'cartes-mentales/' . $id);
    $dire('  demandée seule : la page entière, avec le lien de retour et le script de l’éditeur',
        $oui(str_contains($r, '<header class="entete"') && str_contains($r, '← Retour à la fiche de Cyber') && str_contains($r, 'carte-mentale.js')
            && !str_contains($r, 'data-envoi-fenetre')), 'oui');
    $dire('  l’idée centrale est le titre du cours, la carte n’est pas « écrite par l’IA »',
        ($arbreDe($id)['t'] ?? '?') . ' · ' . bd_valeur('SELECT ia FROM cartes_mentales WHERE id = ?', [$id]) . ' · '
        . $oui(!str_contains($r, 'écrite par l’IA')), 'Cyber · 0 · oui');
    $dire('  la page donne l’arbre, le jeton, les limites, et le plan en liste (pour qui n’a pas JavaScript)',
        $oui(str_contains($r, 'data-arbre="') && str_contains($r, 'data-jeton="') && str_contains($r, 'data-max-noeuds="250"')
            && str_contains($r, '<li>Cyber</li>') && str_contains($r, 'carte-mentale.js')), 'oui');
    [$js] = $appel($a, 'assets/js/carte-mentale.js');
    $dire('  le script de l’éditeur est servi', $oui(str_contains($js, 'data-carte-mentale')), 'oui');
    $csrfCarte = $jeton($r);
    [$fiche] = $appel($a, 'revision/' . $coursA);
    $dire('  la fiche la liste, avec son nombre d’idées',
        $oui(str_contains($fiche, 'href="/mon_appli/appli/cartes-mentales/' . $id . '"') && str_contains($fiche, '1 idée')), 'oui');

    echo "\n3. Enregistrer\n";
    $arbre = ['t' => 'Cyber', 'c' => [
        ['t' => 'Chiffrement', 'c' => [['t' => 'Symétrique', 'c' => []], ['t' => 'Asymétrique', 'c' => []]], 'p' => 1],
        ['t' => 'Pare-feu <script>alert(1)</script>', 'c' => []],
    ]];
    [, $code] = $enregistrer($a, $id, $csrfCarte, $arbre, 'Ma carte');
    $relu = $arbreDe($id);
    $dire('une carte à deux niveaux est gardée (204), avec son titre et sa branche repliée',
        $code . ' · ' . bd_valeur('SELECT titre FROM cartes_mentales WHERE id = ?', [$id]) . ' · ' . count($relu['c'] ?? []) . ' · ' . ($relu['c'][0]['p'] ?? 0),
        '204 · Ma carte · 2 · 1');
    [$r] = $appel($a, 'cartes-mentales/' . $id);
    $dire('  rouverte : le titre, le plan, et le script du texte échappé partout',
        $oui(str_contains($r, '<h1 data-cm-titre-page>Ma carte</h1>') && str_contains($r, '<li>Symétrique</li>') && str_contains($r, '3 idées') === false
            && !str_contains($r, '<script>alert(1)</script>') && str_contains($r, '&lt;script&gt;alert(1)&lt;/script&gt;')), 'oui');
    $dire('  elle compte bien ses idées (le total des idées, repliées ou non)', $oui(str_contains($r, '5 idées')), 'oui');

    [, $code] = $enregistrer($a, $id, $csrfCarte, $arbre);
    $dire('  sans titre envoyé, l’ancien titre reste', $code . ' · ' . bd_valeur('SELECT titre FROM cartes_mentales WHERE id = ?', [$id]), '204 · Ma carte');
    [, $code] = $enregistrer($a, $id, $csrfCarte, $arbre, '   ');
    $dire('  un titre vide revient à l’idée centrale', $code . ' · ' . bd_valeur('SELECT titre FROM cartes_mentales WHERE id = ?', [$id]), '204 · Cyber');

    echo "\n4. Ce qui arrive est nettoyé\n";
    $sale = ['t' => "  Centre \n  multi   ligne  ", 'c' => [
        ['t' => '', 'c' => [['t' => 'orpheline', 'c' => []]]],
        ['t' => str_repeat('é', 300), 'c' => [], 'p' => 1],
        ['t' => 'ok', 'c' => 'pas une liste'],
        'pas un noeud',
        ['t' => 'plie sans enfant', 'c' => [], 'p' => 1],
    ]];
    [, $code] = $enregistrer($a, $id, $csrfCarte, $sale);
    $relu = $arbreDe($id);
    $dire('texte sur une ligne, idée vide écartée (avec ses enfants), texte coupé à 120, « replié » sans enfant retiré',
        $code . ' · ' . ($relu['t'] ?? '?') . ' · ' . count($relu['c'] ?? []) . ' · ' . mb_strlen($relu['c'][0]['t'] ?? '') . ' · '
        . $oui(!isset($relu['c'][0]['p']) && !isset($relu['c'][1]['p'])), '204 · Centre multi ligne · 3 · 120 · oui');
    $profond = ['t' => 'n0', 'c' => []];
    $cur = &$profond;
    for ($i = 1; $i <= 12; $i++) { $cur['c'][] = ['t' => 'n' . $i, 'c' => []]; $cur = &$cur['c'][0]; }
    unset($cur);
    [, $code] = $enregistrer($a, $id, $csrfCarte, $profond);
    $niveaux = 0; $n = $arbreDe($id);
    while (!empty($n['c'])) { $n = $n['c'][0]; $niveaux++; }
    $dire('une carte trop profonde est coupée à 6 niveaux sous l’idée centrale', $code . ' · ' . $niveaux, '204 · 6');
    $large = ['t' => 'centre', 'c' => []];
    for ($i = 0; $i < 400; $i++) { $large['c'][] = ['t' => 'idée ' . $i, 'c' => []]; }
    [, $code] = $enregistrer($a, $id, $csrfCarte, $large);
    $dire('une carte de 400 idées est coupée à 250 (le centre compris)', $code . ' · ' . count($arbreDe($id)['c'] ?? []), '204 · 249');
    foreach ([['pas du json', 'texte'], ['[1,2]', 'liste'], ['{"c":[]}', 'sans texte central'], ['', 'vide'], ['{"t":"   ","c":[]}', 'texte central blanc']] as [$mauvais, $quoi]) {
        $avant = bd_valeur('SELECT arbre FROM cartes_mentales WHERE id = ?', [$id]);
        [, $code] = $enregistrer($a, $id, $csrfCarte, $mauvais);
        $dire("  refusé (422), la carte reste telle quelle : $quoi", $code . ' · ' . $oui(bd_valeur('SELECT arbre FROM cartes_mentales WHERE id = ?', [$id]) === $avant), '422 · oui');
    }
    $avant = bd_valeur('SELECT arbre FROM cartes_mentales WHERE id = ?', [$id]);
    [, , $code] = $appel($a, 'cartes-mentales/' . $id, ['_csrf' => 'faux', 'arbre' => json_encode(['t' => 'piraté', 'c' => []])]);
    $dire('  sans le bon jeton CSRF, rien n’est gardé', $oui(bd_valeur('SELECT arbre FROM cartes_mentales WHERE id = ?', [$id]) === $avant) . ' · ' . $code, 'oui · ' . $code);

    echo "\n5. Le cloisonnement entre comptes\n";
    [$pageB] = $appel($b, 'revision/' . $coursB);
    $csrfB = $jeton($pageB);
    [, , $code] = $appel($b, 'cartes-mentales/' . $id);
    $dire('un autre compte ne voit pas la carte', (string) $code, '404');
    [, $code] = $enregistrer($b, $id, $csrfB, ['t' => 'volé', 'c' => []]);
    $dire('  ne la modifie pas', $code . ' · ' . ($arbreDe($id)['t'] ?? '?'), '404 · centre');
    [, , $code] = $appel($b, 'cartes-mentales/' . $id . '/supprimer', ['_csrf' => $csrfB]);
    $dire('  ne la supprime pas', $code . ' · ' . $nbCartes($coursA), '404 · 1');
    [, , $code] = $appel($b, 'cartes-mentales', ['_csrf' => $csrfB, 'cours' => $coursA]);
    $dire('  n’en crée pas dans le cours d’un autre', $code . ' · ' . $nbCartes($coursA), '404 · 1');
    $appel($b, 'compte/gemini', ['_csrf' => $csrfB, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    $appel($b, 'resumes/generer', ['_csrf' => $csrfB, 'cours' => [$coursA], 'genres' => ['carte']]);
    $dire('  ni n’en demande à l’IA pour le cours d’un autre (même avec une clé)',
        $nbCartes($coursA) . ' · ' . bd_valeur('SELECT COUNT(*) FROM cartes_mentales WHERE user_id = ?', [$idB]), '1 · 0');
    echo "\n6. Les cartes écrites par l’IA (depuis « Résumés IA »)\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    [$resumes] = $appel($a, 'resumes');
    $dire('avec une clé : la case « Carte mentale » parmi les genres, et ce que la carte devient',
        $oui(str_contains($resumes, 'value="carte"') && str_contains($resumes, 'rangée dans sa fiche de révision')), 'oui');
    $nbResumes = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM resumes_ia WHERE user_id = ?', [$idA]);
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['carte']]);
    $idIa = $idCarte($url);
    $ia = $arbreDe($idIa);
    [$popup] = $appel($a, 'cartes-mentales/' . $idIa . '?fenetre=1');
    $dire('une carte seule : « Résumés IA » le dit (« écrite par l’IA », à relire) et l’ouvre en fenêtre, sans résumé écrit',
        $oui($idIa > 0 && $idIa !== $id && str_contains($r, 'écrite par l’IA') && str_contains($r, 'relis-la et corrige-la')
            && str_contains($url, '/resumes?carte=') && str_contains($r, 'data-ouvrir-auto'))
        . ' · ' . $nbCartes($coursA) . ' · ' . $nbResumes(), 'oui · 2 · 0');
    $dire('  l’idée centrale, 3 branches, et leurs sous-branches et détails',
        ($ia['t'] ?? '?') . ' · ' . count($ia['c'] ?? []) . ' · ' . count($ia['c'][0]['c'] ?? []) . ' · ' . ($ia['c'][0]['c'][0]['c'][0]['t'] ?? '?'),
        'Cybersécurité · 3 · 2 · Clé secrète');
    $dire('  le texte venu de l’IA est du texte : le script glissé dedans est échappé',
        $oui(!str_contains($popup, '<script>alert(1)') && str_contains($popup, 'Filtrage du trafic &lt;script&gt;')), 'oui');
    $envoye = $dernier();
    $texteEnvoye = json_encode($envoye['corps'] ?? [], JSON_UNESCAPED_UNICODE);
    $dire('  Gemini a reçu le cours ET sa fiche, avec la clé de l’utilisateur, et la consigne « carte mentale » en français',
        $oui(($envoye['cle'] ?? '') === 'cle-bonne-0123456789abcdef' && str_contains($texteEnvoye, 'Le chiffrement protège')
            && str_contains($texteEnvoye, 'défense en profondeur')
            && str_contains((string) ($envoye['corps']['systemInstruction']['parts'][0]['text'] ?? ''), 'carte mentale')
            && str_contains((string) ($envoye['corps']['systemInstruction']['parts'][0]['text'] ?? ''), 'français')), 'oui');
    $dire('  il a été invité à répondre en JSON, selon un schéma d’objet', $oui(
        ($envoye['corps']['generationConfig']['responseMimeType'] ?? '') === 'application/json'
        && ($envoye['corps']['generationConfig']['responseSchema']['type'] ?? '') === 'OBJECT'), 'oui');
    [$fiche] = $appel($a, 'revision/' . $coursA);
    $dire('  la fiche de révision liste les deux cartes, la seconde marquée « écrite par l’IA »',
        $oui(str_contains($fiche, '/cartes-mentales/' . $idIa . '"') && str_contains($fiche, '/cartes-mentales/' . $id . '"')
            && substr_count($fiche, 'écrite par l’IA') === 1), 'oui');
    [$resumes] = $appel($a, 'resumes');
    $dire('  et « Résumés IA » les liste aussi, avec leur cours',
        $oui(str_contains($resumes, '/cartes-mentales/' . $idIa . '"') && str_contains($resumes, '/revision/' . $coursA . '"')), 'oui');

    // Un résumé ET une carte dans la même demande ; puis deux cours à la fois.
    $avant = [$nbResumes(), $nbCartes($coursA)];
    [, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['points', 'carte']]);
    $dire('un résumé ET une carte : le résumé s’ouvre (liste), la carte est rangée dans la fiche du cours',
        $oui(str_contains($url, '/resumes?ouvrir=')) . ' · ' . ($nbResumes() - $avant[0]) . ' · ' . ($nbCartes($coursA) - $avant[1]), 'oui · 1 · 1');
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Réseaux', 'Un cours sur les réseaux.']);
    $coursR = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Réseaux']);
    $avant = [$nbResumes(), $nbCartes($coursA), $nbCartes($coursR)];
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA, $coursR], 'genres' => ['carte']]);
    $dire('deux cours cochés : une carte par cours, chacune dans sa fiche, et on revient à la liste',
        ($nbCartes($coursA) - $avant[1]) . ' · ' . ($nbCartes($coursR) - $avant[2]) . ' · ' . ($nbResumes() - $avant[0]) . ' · '
        . $oui(!str_contains($url, 'ouvrir=') && str_contains($r, '2 cartes mentales écrites par l’IA')), '1 · 1 · 0 · oui');
    $recu = json_encode($dernier()['corps'] ?? [], JSON_UNESCAPED_UNICODE);
    $dire('  chaque carte est écrite à partir de son cours seul (le dernier appel ne contient pas l’autre cours)',
        $oui(str_contains($recu, 'réseaux') && !str_contains($recu, 'Le chiffrement protège')), 'oui');
    bd_run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$coursR, $idA]);
    bd_run('DELETE FROM cartes_mentales WHERE cours_id = ? AND id NOT IN (?, ?)', [$coursA, $id, $idIa]);
    bd_run('DELETE FROM resumes_ia WHERE user_id = ?', [$idA]);

    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-sans-json-0123456789abc']);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['carte']]);
    $dire('un modèle qui répond en prose : « pas de carte exploitable pour Cyber », rien d’écrit',
        $oui(str_contains($r, 'n’a pas rendu de carte exploitable pour « Cyber »')) . ' · ' . $nbCartes($coursA), 'oui · 2');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-mauvaise-0123456789abc']);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['carte']]);
    $dire('une clé refusée par Google : le message de la clé, rien d’écrit',
        $oui(str_contains($r, 'refusée') || str_contains($r, 'clé')) . ' · ' . $nbCartes($coursA), 'oui · 2');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    echo "\n7. Supprimer\n";
    [$r] = $appel($a, 'cartes-mentales/' . $idIa);
    [$r, $url] = $appel($a, 'cartes-mentales/' . $idIa . '/supprimer', ['_csrf' => $jeton($r)]);
    $dire('la carte disparaît, et l’on revient à la fiche du cours',
        $oui(str_contains($r, 'Carte mentale supprimée.') && str_contains($url, '/revision/' . $coursA)) . ' · ' . $nbCartes($coursA), 'oui · 1');
    [, $url] = $appel($a, 'cartes-mentales', ['_csrf' => $csrf, 'cours' => $coursA]);
    $idF = $idCarte($url);
    [$popup] = $appel($a, 'cartes-mentales/' . $idF . '?fenetre=1');
    [$r, $url] = $appel($a, 'cartes-mentales/' . $idF . '/supprimer', ['_csrf' => $jeton($popup), 'fenetre' => '1']);
    $dire('effacée depuis la fenêtre : la fiche du cours la remplace, en fragment (la fenêtre reste ouverte)',
        $oui(str_contains($url, '/revision/' . $coursA) && str_contains($url, 'fenetre=1') && !str_contains($r, '<header class="entete"')
            && str_contains($r, 'data-cartes-mentales')) . ' · ' . $nbCartes($coursA), 'oui · 1');
    bd_run('DELETE FROM cours WHERE id = ? AND user_id = ?', [$coursA, $idA]);
    $dire('et une carte disparaît avec son cours', (string) bd_valeur('SELECT COUNT(*) FROM cartes_mentales WHERE user_id = ?', [$idA]), '0');
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Cyber', 'Le chiffrement protège.']);
    $coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);

    echo "\n8. Les quatre langues\n";
    foreach ([
        'en' => [['Mind maps', 'No mind map for this course yet.', 'Create a mind map in “AI summaries” →'],
                 ['A mind map to fill in yourself', 'filed in its revision sheet', 'New mind map']],
        'es' => [['Mapas mentales', 'Aún no hay mapas mentales para este curso.', 'Crear un mapa mental en «Resúmenes con IA» →'],
                 ['Un mapa mental para rellenar tú mismo', 'guardado en su ficha de repaso', 'Nuevo mapa mental']],
        'de' => [['Mindmaps', 'Noch keine Mindmap für diesen Kurs.', 'Eine Mindmap unter „KI-Zusammenfassungen“ erstellen →'],
                 ['Eine Mindmap zum Selbstausfüllen', 'abgelegt in seinem Lernblatt', 'Neue Mindmap']],
        'fr' => [['Cartes mentales', 'Aucune carte mentale pour ce cours.', 'Créer une carte mentale dans « Résumés IA » →'],
                 ['Une carte mentale à remplir soi-même', 'rangée dans sa fiche de révision', 'Nouvelle carte mentale']],
    ] as $langue => [$motsFiche, $motsResumes]) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        bd_run('DELETE FROM cartes_mentales WHERE cours_id = ?', [$coursA]);   // chaque langue repart d'un rayon vide
        [$fiche] = $appel($a, 'revision/' . $coursA);
        $dire("$langue : le rayon de la fiche, son message vide et son lien vers « Résumés »",
            $oui(array_reduce($motsFiche, static fn (bool $ok, string $m): bool => $ok && str_contains($fiche, $m), true)), 'oui');
        [$resumes] = $appel($a, 'resumes');
        $dire("$langue : « Résumés » — la case du genre, la carte vierge et son bouton",
            $oui(array_reduce($motsResumes, static fn (bool $ok, string $m): bool => $ok && str_contains($resumes, $m), true)), 'oui');
        [$r, $url] = $appel($a, 'cartes-mentales', ['_csrf' => $jeton($resumes), 'cours' => $coursA]);
        $idL = $idCarte($url);
        [$r] = $appel($a, 'cartes-mentales/' . $idL);
        $dire("$langue : l’éditeur parle la langue (retour, titre, boutons, aide, phrases du script)",
            $oui(str_contains($r, 'data-cm-action="enfant"') && $idL > 0
                && str_contains($r, ['fr' => '← Retour à la fiche de Cyber', 'en' => '← Back to the revision sheet of Cyber',
                    'es' => '← Volver a la ficha de repaso de Cyber', 'de' => '← Zurück zum Lernblatt von Cyber'][$langue])
                && str_contains($r, ['fr' => 'Titre de la carte', 'en' => 'Map title', 'es' => 'Título del mapa', 'de' => 'Titel der Mindmap'][$langue])
                && str_contains($r, '"cm.nouvelle_idee":"' . ['fr' => 'Nouvelle idée', 'en' => 'New idea', 'es' => 'Nueva idea', 'de' => 'Neue Idee'][$langue] . '"')), 'oui');
    }
    $dire('  et aucune clé « cm.… » n’est restée telle quelle (clé nue à l’écran)',
        $oui(!preg_match('/>\s*(cm|js\.cm)\.[a-z_.]+\s*</', $r)), 'oui');

    echo "\n9. Les sauvegardes du compte\n";
    bd_run('DELETE FROM cartes_mentales WHERE cours_id = ?', [$coursA]);   // celle de la dernière langue
    $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    [$fiche] = $appel($a, 'revision/' . $coursA);
    [$r, $url] = $appel($a, 'cartes-mentales', ['_csrf' => $csrf, 'cours' => $coursA]);
    $idS = $idCarte($url);
    $enregistrer($a, $idS, $jeton($r), ['t' => 'Centre', 'c' => [['t' => 'Branche', 'c' => [['t' => 'Détail', 'c' => []]], 'p' => 1]]], 'Sauvée');
    $h = curl_init('http://localhost/mon_appli/appli/compte/sauvegarde/export');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookies[$a], CURLOPT_COOKIEJAR => $cookies[$a]]);
    $zipOctets = (string) curl_exec($h);
    unset($h);
    $zipChemin = tempnam(sys_get_temp_dir(), 'cm') . '.zip';
    file_put_contents($zipChemin, $zipOctets);
    $zip = new ZipArchive();
    $donnees = $zip->open($zipChemin) === true ? json_decode((string) $zip->getFromName('donnees.json'), true) : [];
    $zip->close();
    $dire('l’archive contient la carte (titre et arbre)',
        count($donnees['tables']['cartes_mentales'] ?? []) . ' · ' . ($donnees['tables']['cartes_mentales'][0]['titre'] ?? '?'), '1 · Sauvée');
    bd_run('DELETE FROM cartes_mentales WHERE user_id = ?', [$idA]);
    [$page] = $appel($a, 'compte/sauvegarde');
    $h = curl_init('http://localhost/mon_appli/appli/compte/sauvegarde/restaurer');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => $cookies[$a], CURLOPT_COOKIEJAR => $cookies[$a],
        CURLOPT_POSTFIELDS => ['_csrf' => $jeton($page), 'confirmation' => '1', 'archive' => new CURLFile($zipChemin, 'application/zip', 'sauvegarde.zip')]]);
    curl_exec($h);
    unset($h);
    @unlink($zipChemin);
    $restauree = bd_all('SELECT cm.titre, cm.arbre, c.titre AS cours FROM cartes_mentales cm JOIN cours c ON c.id = cm.cours_id WHERE cm.user_id = ?', [$idA]);
    $dire('restaurée : la carte revient, rattachée à son cours, avec sa branche repliée',
        count($restauree) . ' · ' . ($restauree[0]['titre'] ?? '?') . ' · ' . ($restauree[0]['cours'] ?? '?') . ' · '
        . (json_decode((string) ($restauree[0]['arbre'] ?? '{}'), true)['c'][0]['p'] ?? 0), '1 · Sauvée · Cyber · 1');

    echo "\n10. La carte en image, dans la fiche de révision\n";
    $idM = (int) bd_valeur('SELECT id FROM cartes_mentales WHERE user_id = ?', [$idA]);
    $coursM = (int) bd_valeur('SELECT cours_id FROM cartes_mentales WHERE id = ?', [$idM]);
    [$page] = $appel($a, 'cartes-mentales/' . $idM);
    $csrfM = $jeton($page);
    $dire('l’éditeur propose « Ajouter à la fiche de révision », et sait où envoyer l’image',
        $oui(str_contains($page, 'data-cm-action="fiche"') && str_contains($page, 'Ajouter à la fiche de révision')
            && str_contains($page, 'data-url-image="/mon_appli/appli/cartes-mentales/' . $idM . '/image"')), 'oui');
    [$js] = $appel($a, 'assets/js/carte-mentale.js');
    $dire('  et le script dessine la carte sur une toile, l’envoie en PNG avec le jeton', $oui(str_contains($js, "toBlob(") && str_contains($js, "'image/png'") && str_contains($js, 'URL_IMAGE')), 'oui');

    $png = static function (int $largeur, int $hauteur): string {
        $im = imagecreatetruecolor($largeur, $hauteur);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagepng($im);
        return (string) ob_get_clean();
    };
    // Un PNG qui ne fait que DIRE ses dimensions (signature + en-tête IHDR) : de quoi tester le refus d'une image trop
    // grande sans en fabriquer une — 5000 × 5000 points feraient exploser la mémoire du script, pas celle du serveur.
    $enteteSeul = static function (int $largeur, int $hauteur): string {
        $bloc = static fn (string $type, string $donnees): string => pack('N', strlen($donnees)) . $type . $donnees . pack('N', crc32($type . $donnees));
        return "\x89PNG\r\n\x1a\n" . $bloc('IHDR', pack('NNCCCCC', $largeur, $hauteur, 8, 2, 0, 0, 0)) . $bloc('IEND', '');
    };
    $jpeg = static function (): string {
        $im = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($im);
        return (string) ob_get_clean();
    };
    /** Envoie une image comme le fait le script (multipart), et rend le code de la réponse. */
    $envoyer = static function (string $compte, int $id, string $csrf, ?string $octets, string $nom = 'carte-mentale.png') use ($cookies): int {
        usleep(300000);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $octets ?? '');
        $post = ['_csrf' => $csrf];
        if ($octets !== null) { $post['image'] = new CURLFile($tmp, 'image/png', $nom); }
        $h = curl_init('http://localhost/mon_appli/appli/cartes-mentales/' . $id . '/image');
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookies[$compte], CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_POSTFIELDS => $post]);
        curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        unset($h);
        @unlink($tmp);
        return $code;
    };
    $jointes = static fn (): array => bd_all('SELECT id, nom_origine, nom_stocke, mime, taille, pour_fiche FROM fichiers WHERE cours_id = ? ORDER BY id', [$coursM]);

    $code = $envoyer($a, $idM, $csrfM, $png(40, 20));
    $lignes = $jointes();
    $chemin = $racine . '/storage/uploads/' . basename((string) ($lignes[0]['nom_stocke'] ?? 'absent'));
    $infos = is_file($chemin) ? getimagesize($chemin) : false;
    $dire('une image PNG est jointe (204) : un fichier de la fiche, de type image/png, bien rangé et de la bonne taille',
        $code . ' · ' . count($lignes) . ' · ' . ($lignes[0]['mime'] ?? '?') . ' · ' . ($lignes[0]['pour_fiche'] ?? '?') . ' · '
        . $oui($infos !== false && $infos[0] === 40 && $infos[1] === 20 && (int) $lignes[0]['taille'] === filesize($chemin)
            && str_starts_with((string) file_get_contents($chemin), "\x89PNG")), '204 · 1 · image/png · 1 · oui');
    $dire('  son nom dit ce que c’est : « Carte mentale — » et le titre de la carte', (string) ($lignes[0]['nom_origine'] ?? '?'), 'Carte mentale — Sauvée.png');
    $envoyer($a, $idM, $csrfM, $png(40, 20));
    $noms = array_column($jointes(), 'nom_origine');
    $dire('  un second ajout reçoit un numéro (deux images ne se confondent pas)', $oui(in_array('Carte mentale — Sauvée (2).png', $noms, true)), 'oui');
    [$fiche] = $appel($a, 'revision/' . $coursM);
    $dire('  la fiche de révision la montre parmi ses fichiers et images', $oui(str_contains($fiche, 'Carte mentale — Sauvée.png')), 'oui');
    $idImage = (int) $lignes[0]['id'];
    $dire('  dans la fiche, cliquer l’image ouvre son aperçu dans une fenêtre (plus dans un nouvel onglet)',
        $oui(preg_match('#<a href="[^"]*/fichiers/' . $idImage . '/apercu" data-fenetre\s+title="Ouvrir l’image en grand"#', $fiche) === 1), 'oui');
    [$apercu] = $appel($a, 'fichiers/' . $idImage . '/apercu?fenetre=1');
    $dire('  l’aperçu en fenêtre : un fragment avec la barre de zoom (− + ajuster 1:1) et sans script à exécuter',
        $oui(str_contains($apercu, 'data-zoom-image') && !str_contains($apercu, '<header class="entete"') && !str_contains($apercu, 'zoom-image.js')
            && str_contains($apercu, 'data-zoom-action="moins"') && str_contains($apercu, 'data-zoom-action="plus"')
            && str_contains($apercu, 'data-zoom-action="ajuster"') && str_contains($apercu, 'data-zoom-action="reel"')
            && str_contains($apercu, 'data-zoom-cadre') && str_contains($apercu, 'Ajuster à la largeur')), 'oui');
    [$apercuPage] = $appel($a, 'fichiers/' . $idImage . '/apercu');
    [$jsZoom] = $appel($a, 'assets/js/zoom-image.js');
    $dire('  la page entière charge le script du zoom (que app.js appelle sur ce qu’il pose en fenêtre)',
        $oui(str_contains($apercuPage, 'zoom-image.js') && str_contains($jsZoom, 'initialiserZoomImage')
            && str_contains((string) $appel($a, 'assets/js/app.js')[0], 'initialiserZoomImage')), 'oui');
    // Les fenêtres : celle du dessus (une image ou une carte ouverte depuis une fiche déjà en fenêtre) doit, elle aussi,
    // animer ce qu'elle reçoit — un oubli qui laissait l'image sans barre de zoom. Et toutes ont un bouton plein écran.
    [$jsApp] = $appel($a, 'assets/js/app.js');
    [$cssApp] = $appel($a, 'assets/css/app.css');
    $dire('  la fenêtre du dessus anime ce qu’elle reçoit (zoom d’image, éditeur de carte), comme la principale',
        $oui(str_contains($jsApp, 'window.initialiserZoomImage(corpsDessus)') && str_contains($jsApp, 'window.initialiserCarteMentale(corpsDessus)')
            && str_contains($jsApp, 'window.initialiserZoomImage(corps)') && str_contains($jsApp, 'window.initialiserCarteMentale(corps)')), 'oui');
    $dire('  chaque fenêtre a son bouton « plein écran », qui se réinitialise à la fermeture',
        $oui(substr_count($jsApp, 'brancherPlein(') === 2 && substr_count($jsApp, 'reglerPlein(fenetre, false)') === 1
            && substr_count($jsApp, 'reglerPlein(dessus, false)') === 1 && str_contains($cssApp, '.fenetre--plein')), 'oui');
    [$pageAvecMots] = $appel($a, 'revision/' . $coursM);
    $dire('  le bouton parle la langue de la page (clés « js.fenetre.plein » / « reduire »)',
        $oui(str_contains($pageAvecMots, '"fenetre.plein":"Plein écran"') && str_contains($pageAvecMots, '"fenetre.reduire":"Quitter le plein écran"')), 'oui');
    foreach (['en' => 'Fit to width', 'es' => 'Ajustar al ancho', 'de' => 'An Breite anpassen', 'fr' => 'Ajuster à la largeur'] as $langue => $mot) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$apercu] = $appel($a, 'fichiers/' . $idImage . '/apercu?fenetre=1');
        $dire("  $langue : la barre de zoom parle la langue", $oui(str_contains($apercu, $mot) && !preg_match('/>\s*ap\.zoom_[a-z]+\s*</', $apercu)), 'oui');
    }

    $avant = count($jointes());
    foreach ([
        'un JPEG déguisé en PNG' => [$jpeg(), 422],
        'du texte nommé .png' => ['pas une image', 422],
        'un PNG trop large (9000 px)' => [$enteteSeul(9000, 1), 422],
        'un PNG trop grand (5000 × 5000 points, 25 millions)' => [$enteteSeul(5000, 5000), 422],
        'pas d’image du tout' => [null, 400],
    ] as $quoi => [$octets, $attendu]) {
        $code = $envoyer($a, $idM, $csrfM, $octets);
        $dire("  refusé : $quoi", $code . ' · ' . count($jointes()), $attendu . ' · ' . $avant);
    }
    $code = $envoyer($a, $idM, 'faux', $png(10, 10));
    $dire('  sans le bon jeton CSRF, rien n’est rangé', $oui(count($jointes()) === $avant) . ' · ' . $oui($code !== 204), 'oui · oui');
    [$pageB] = $appel($b, 'compte');
    $code = $envoyer($b, $idM, $jeton($pageB), $png(10, 10));
    $dire('  un autre compte ne peut pas joindre d’image à la carte d’un autre', $code . ' · ' . count($jointes()), '404 · ' . $avant);
} finally {
    foreach (bd_all('SELECT nom_stocke FROM fichiers WHERE user_id IN (?, ?)', [$idA ?? 0, $idB ?? 0]) as $rangee) {
        @unlink($racine . '/storage/uploads/' . basename((string) $rangee['nom_stocke']));
    }
    if (is_resource($serveur)) { proc_terminate($serveur); proc_close($serveur); }
    @unlink($fichierEssai);
    foreach ($cookies as $f) { @unlink($f); }
    @unlink(sys_get_temp_dir() . '/faux_gemini_dernier.json');
    foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
