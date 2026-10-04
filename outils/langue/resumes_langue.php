<?php
/**
 * Les résumés par IA, de bout en bout, contre un FAUX Gemini local : le choix des documents, l'écriture,
 * la voix, les refus, le cloisonnement entre comptes, les quatre langues. Aucun appel chez Google.
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
$dossierSon = $racine . '/storage/resumes';
$port = 8766;
$journal = sys_get_temp_dir() . '/faux_gemini.log';

@unlink($fichierEssai);
file_put_contents($fichierEssai, "<?php\nreturn ['gemini' => ['adresse' => 'http://127.0.0.1:$port/v1beta/', 'pause_reessai' => 0]];\n");
$serveur = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/faux_gemini.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $journal, 'w'], 2 => ['file', $journal, 'a']], $tuyaux);
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $no, $str, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

$emails = ['resumes-a@exemple-test.fr', 'resumes-b@exemple-test.fr'];
foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ?', [$a]); }
foreach ($emails as $i => $a) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, 'Resumes_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai résumés']);
}
[$idA, $idB] = array_map(static fn (string $a): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$a]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu, fiche_revision) VALUES (?, ?, ?, ?)',
    [$idA, 'Cyber', "Le chiffrement protège la confidentialité des données.\n\nUn pare-feu filtre le trafic.", 'Fiche : penser à la défense en profondeur.']);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idB, 'Cours secret de B', 'Contenu privé de B.']);
$coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
$coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idB]);

$cookies = [$emails[0] => __DIR__ . '/ck_resumes_a.txt', $emails[1] => __DIR__ . '/ck_resumes_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} corps, adresse finale, code */
$appel = static function (string $compte, string $chemin, ?array $post = null, array $entetes = []) use ($cookies): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte], CURLOPT_HTTPHEADER => $entetes]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$dernier = static fn (): array => json_decode((string) @file_get_contents(sys_get_temp_dir() . '/faux_gemini_dernier.json'), true) ?: [];
$idOuvert = static fn (string $url): int => preg_match('/[?&]ouvrir=(\d+)/', $url, $m) === 1 ? (int) $m[1] : 0;
$nbResumes = static fn (int $uid): int => (int) bd_valeur('SELECT COUNT(*) FROM resumes_ia WHERE user_id = ?', [$uid]);
$sons = [];

try {
    [$a, $b] = $emails;
    [$p] = $appel($a, 'connexion');
    $appel($a, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $a, 'mot_de_passe' => 'MotDePasse!2026']);
    [$p] = $appel($b, 'connexion');
    $appel($b, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $b, 'mot_de_passe' => 'MotDePasse!2026']);
    [$page] = $appel($a, 'resumes');
    $csrf = $jeton($page);

    echo "\n1. Sans clé\n";
    $dire('la page dit d’enregistrer la clé, avec un lien vers « Mon compte »',
        $oui(str_contains($page, 'enregistrer votre clé Gemini') && str_contains($page, '/compte#gemini')), 'oui');
    $dire('  et ne propose aucun formulaire de génération', $oui(!str_contains($page, 'resumes/generer')), 'oui');
    $dire('  « Résumés » est dans le menu', $oui(str_contains($page, '>Résumés</a>')), 'oui');
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
    $dire('  forcer l’envoi sans clé : refusé, rien n’est écrit', $oui(str_contains($r, 'Enregistrez d’abord votre clé Gemini')) . ' · ' . $nbResumes($idA), 'oui · 0');

    echo "\n2. Le formulaire\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    [$page] = $appel($a, 'resumes');
    $dire('l’avertissement sur ce qui part chez Google est avant le bouton',
        $oui(strpos($page, 'est envoyé à Google') !== false && strpos($page, 'est envoyé à Google') < strpos($page, '>Générer</button>')), 'oui');
    $dire('  mes cours sont proposés, pas ceux d’un autre',
        $oui(str_contains($page, 'Cyber') && !str_contains($page, 'Cours secret de B')), 'oui');
    $dire('  trois genres à cocher (le premier d’avance), trois longueurs',
        $oui(substr_count($page, 'name="genres[]"') === 3 && substr_count($page, 'type="radio"') === 0 && substr_count($page, 'name="genres[]" value="resume" checked') === 1 && str_contains($page, 'Questions de révision') && str_contains($page, '>Détaillé</option>')), 'oui');
    [$r] = $appel($a, 'resumes?cours=' . $coursA);
    $dire('  ?cours=… coche le cours d’avance', $oui(preg_match('/value="' . $coursA . '"\s+data-choix-cours checked/', $r) === 1), 'oui');

    echo "\n3. Des demandes refusées\n";
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'genres' => ['resume']]);
    $dire('aucun cours coché', $oui(str_contains($r, 'Choisissez au moins un cours')), 'oui');
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursB]]);
    $dire('  le cours de quelqu’un d’autre est ignoré (jamais lu, jamais envoyé)',
        $oui(str_contains($r, 'Choisissez au moins un cours') && !str_contains(json_encode($dernier()), 'Contenu privé de B')), 'oui');
    [$r, , $code] = $appel($a, 'resumes/generer', ['cours' => [$coursA]]);
    $dire('  sans jeton CSRF', $code . ' · ' . $nbResumes($idA), '400 · 0');

    echo "\n4. Écrire un résumé\n";
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['points'], 'longueur' => 'court']);
    $idResume = $idOuvert($url);
    $ligne = bd_all('SELECT * FROM resumes_ia WHERE id = ?', [$idResume])[0] ?? [];
    $dire('on revient à la liste, qui ouvre aussitôt le résumé en fenêtre (lien « data-ouvrir-auto »), un seul enregistrement',
        $oui($idResume > 0 && parse_url($url, PHP_URL_PATH) === '/mon_appli/appli/resumes'
            && preg_match('/href="[^"]*\/resumes\/' . $idResume . '" data-fenetre data-ouvrir-auto/', $r) === 1) . ' · ' . $nbResumes($idA), 'oui · 1');
    [$r] = $appel($a, 'resumes/' . $idResume);
    $dire('  genre, longueur, modèle et titre',
        ($ligne['genre'] ?? '?') . ' · ' . ($ligne['longueur'] ?? '?') . ' · ' . ($ligne['modele'] ?? '?') . ' · ' . ($ligne['titre'] ?? '?'),
        'points · court · gemini-3.8-flash · Points clés — Cyber');
    $envoye = $dernier();
    $dire('  Gemini a reçu le texte du cours ET de la fiche, balisés, avec la clé de l’utilisateur',
        $oui(($envoye['cle'] ?? '') === 'cle-bonne-0123456789abcdef'
            && str_contains(json_encode($envoye['corps'] ?? [], JSON_UNESCAPED_UNICODE), 'Le chiffrement protège')
            && str_contains(json_encode($envoye['corps'] ?? [], JSON_UNESCAPED_UNICODE), 'défense en profondeur')
            && str_contains((string) ($envoye['corps']['contents'][0]['parts'][0]['text'] ?? ''), '<document titre="Cyber — ')), 'oui');
    $dire('  la consigne demande des points clés, en français',
        $oui(str_contains((string) ($envoye['corps']['systemInstruction']['parts'][0]['text'] ?? ''), '5 points clés')
            && str_contains((string) ($envoye['corps']['systemInstruction']['parts'][0]['text'] ?? ''), 'français')), 'oui');
    $dire('  le texte est rendu en HTML, le script glissé dedans est inerte',
        $oui(str_contains($r, '<strong>gras</strong>') && str_contains($r, '&lt;script&gt;') && !str_contains($r, '<script>alert')), 'oui');
    $dire('  la page dit d’où ça vient, et que l’IA peut se tromper',
        $oui(str_contains($r, 'Écrit à partir de') && str_contains($r, 'Cyber') && str_contains($r, 'peut contenir des erreurs')), 'oui');
    [$liste] = $appel($a, 'resumes');
    $dire('  il figure dans « Mes résumés », et s’y ouvre en fenêtre',
        $oui(preg_match('/href="[^"]*\/resumes\/' . $idResume . '" data-fenetre><strong>Points clés — Cyber/', $liste) === 1), 'oui');
    [$fragment] = $appel($a, 'resumes/' . $idResume . '?fenetre=1');
    $dire('  demandé en fenêtre, c’est un fragment : pas de menu, pas de lien de retour, formulaires faits pour la fenêtre',
        $oui(!str_contains($fragment, '<header class="entete"') && !str_contains($fragment, 'Tous les résumés')
            && str_contains($fragment, 'data-large') && substr_count($fragment, 'data-envoi-fenetre') === 2
            && str_contains($fragment, '<strong>gras</strong>') && str_contains($fragment, '&lt;script&gt;')), 'oui');
    $dire('  appelé directement, c’est la page entière, avec son lien de retour',
        $oui(str_contains($r, '<header class="entete"') && str_contains($r, 'Tous les résumés') && !str_contains($r, 'data-envoi-fenetre')), 'oui');

    echo "\n5. Choisir les documents d’un cours\n";
    $avant = $nbResumes($idA);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'sources' => ['fiche'], 'genres' => ['resume']]);
    $corps = json_encode($dernier()['corps'] ?? [], JSON_UNESCAPED_UNICODE);
    $dire('« la fiche » seule : le cours n’est pas envoyé',
        $oui(str_contains($corps, 'défense en profondeur') && !str_contains($corps, 'Le chiffrement protège')) . ' · ' . ($nbResumes($idA) - $avant), 'oui · 1');
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'sources' => ['cours'], 'genres' => ['questions'], 'longueur' => 'long']);
    $corps = json_encode($dernier()['corps'] ?? [], JSON_UNESCAPED_UNICODE);
    $dire('  « le cours » seul, en questions détaillées',
        $oui(str_contains($corps, 'Le chiffrement protège') && !str_contains($corps, 'défense en profondeur') && str_contains($corps, '20 questions')), 'oui');
    bd_run('UPDATE cours SET contenu = NULL, fiche_revision = NULL WHERE id = ?', [$coursA]);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
    $dire('  un cours sans rien à lire : on le dit, on n’appelle pas Gemini', $oui(str_contains($r, 'Rien à lire dans ce qui est coché')), 'oui');
    bd_run('UPDATE cours SET contenu = ?, fiche_revision = ? WHERE id = ?', ["Le chiffrement protège la confidentialité des données.\n\nUn pare-feu filtre le trafic.", 'Fiche : penser à la défense en profondeur.', $coursA]);

    echo "\n5 bis. Plusieurs genres d’un coup\n";
    $avant = $nbResumes($idA);
    $dejaLa = array_column(bd_all('SELECT id FROM resumes_ia WHERE user_id = ?', [$idA]), 'id');
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['points', 'questions'], 'longueur' => 'court']);
    $nouveaux = bd_all('SELECT id, genre FROM resumes_ia WHERE user_id = ? ORDER BY id', [$idA]);
    $nouveaux = array_values(array_filter($nouveaux, static fn (array $l): bool => !in_array($l['id'], $dejaLa, false)));
    $dire('« points clés » et « questions » cochés : deux résumés, un de chaque genre',
        ($nbResumes($idA) - $avant) . ' · ' . implode(',', array_column($nouveaux, 'genre')), '2 · points,questions');
    $dire('  on arrive sur la liste, qui les montre et dit « 2 résumés écrits »',
        $oui(parse_url($url, PHP_URL_PATH) === '/mon_appli/appli/resumes' && str_contains($r, '2 résumés écrits')
            && substr_count($r, 'Points clés — Cyber') >= 2 && str_contains($r, 'Questions de révision — Cyber')), 'oui');
    $dire('  chacun a sa consigne : le dernier appel demandait des questions', $oui(str_contains((string) ($dernier()['corps']['systemInstruction']['parts'][0]['text'] ?? ''), '5 questions de révision')), 'oui');
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
    $encore = bd_all('SELECT id, genre FROM resumes_ia WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$idA])[0] ?? [];
    $dire('  aucun genre coché : un résumé simple, par défaut', ($encore['genre'] ?? '?') . ' · ' . $oui($idOuvert($url) === (int) ($encore['id'] ?? 0)), 'resume · oui');
    bd_run('DELETE FROM resumes_ia WHERE user_id = ? AND id IN (' . implode(',', array_map('intval', array_merge(array_column($nouveaux, 'id'), [$encore['id'] ?? 0]))) . ')', [$idA]);
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-quota-0123456789abcdef']);
    $avant = $nbResumes($idA);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume', 'points', 'questions']]);
    $dire('  limite atteinte dès le premier : on s’arrête là, un seul message, rien d’écrit',
        $oui(str_contains($r, 'Limite de la clé gratuite atteinte') && substr_count($r, 'Limite de la clé gratuite atteinte') === 1) . ' · ' . ($nbResumes($idA) - $avant), 'oui · 0');
    // Un service surchargé est rappelé : le résumé arrive quand même, sans que l'utilisateur ait rien vu.
    $compteur = sys_get_temp_dir() . '/faux_gemini_compteur_' . md5('cle-instable-0123456789abcdef') . '.txt';
    @unlink($compteur);
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-instable-0123456789abcdef']);
    $avant = $nbResumes($idA);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume']]);
    $dire('  un service surchargé deux fois : le résumé s’écrit à la troisième tentative, sans message d’erreur',
        $oui(str_contains($r, 'Résumé écrit') && !str_contains($r, 'en panne')) . ' · ' . ($nbResumes($idA) - $avant) . ' · ' . (int) @file_get_contents($compteur), 'oui · 1 · 3');
    @unlink($compteur);
    bd_run('DELETE FROM resumes_ia WHERE user_id = ? AND id = (SELECT m FROM (SELECT MAX(id) AS m FROM resumes_ia WHERE user_id = ?) x)', [$idA, $idA]);

    // Une série dont un genre manque : on écrit les autres, et on dit lequel manque.
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-sans-questions-0123456789']);
    $avant = $nbResumes($idA);
    $dejaLa = array_column(bd_all('SELECT id FROM resumes_ia WHERE user_id = ?', [$idA]), 'id');
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume', 'questions']]);
    $dire('  « résumé » écrit, « questions » en panne : le résumé est gardé, et le message nomme le genre manquant',
        $oui(($nbResumes($idA) - $avant) === 1 && str_contains($r, 'Résumé écrit')
            && str_contains($r, '« questions de révision » n’a pas pu être écrit') && str_contains($r, 'en panne ou surchargé')) . ' · '
        . $oui($idOuvert($url) > 0), 'oui · oui');
    bd_run('DELETE FROM resumes_ia WHERE user_id = ? AND id NOT IN (' . implode(',', array_map('intval', $dejaLa ?: [0])) . ')', [$idA]);
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);

    echo "\n6. La voix\n";
    [$r] = $appel($a, 'resumes/' . $idResume);
    $dire('avant : un bouton « Générer l’audio », pas de lecteur',
        $oui(str_contains($r, 'Générer l’audio') && !str_contains($r, '<audio'))
        . ' · ' . $oui(str_contains($r, 'name="voix"') && substr_count($r, '<option value="Kore"') === 1), 'oui · oui');
    [$r] = $appel($a, 'resumes/' . $idResume . '/voix', ['_csrf' => $csrf, 'voix' => 'Puck']);
    $son = (string) bd_valeur('SELECT audio_nom FROM resumes_ia WHERE id = ?', [$idResume]);
    $sons[] = $son;
    $chemin = $dossierSon . '/' . $son;
    $octets = is_file($chemin) ? (string) file_get_contents($chemin) : '';
    $dire('un fichier .wav est rangé hors du site, et la voix est gardée',
        $oui($son !== '' && str_ends_with($son, '.wav') && str_starts_with($octets, 'RIFF') && strlen($octets) > 44)
        . ' · ' . bd_valeur('SELECT audio_voix FROM resumes_ia WHERE id = ?', [$idResume]), 'oui · Puck');
    $dire('  Gemini a reçu le texte SANS marque de mise en forme, et la voix choisie',
        $oui(!preg_match('/[#*`]/', (string) ($dernier()['corps']['contents'][0]['parts'][0]['text'] ?? '#'))
            && ($dernier()['corps']['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? '') === 'Puck'), 'oui');
    $dire('  la page a maintenant un lecteur', $oui(str_contains($r, '<audio controls') && str_contains($r, 'Audio prêt')), 'oui');
    [$corpsAudio, , $code] = $appel($a, 'resumes/' . $idResume . '/audio');
    $dire('  l’audio se sert à son propriétaire', $code . ' · ' . $oui(str_starts_with($corpsAudio, 'RIFF')), '200 · oui');
    [$corpsPartiel, , $code] = $appel($a, 'resumes/' . $idResume . '/audio', null, ['Range: bytes=0-99']);
    $dire('  et par morceaux (de quoi se déplacer dans la lecture)', $code . ' · ' . strlen($corpsPartiel), '206 · 100');
    [, , $code] = $appel($b, 'resumes/' . $idResume . '/audio');
    $dire('  mais pas à un autre compte', (string) $code, '404');
    [, , $code] = $appel($b, 'resumes/' . $idResume);
    $dire('  ni le résumé lui-même', (string) $code, '404');
    [, , $code] = $appel($b, 'resumes/' . $idResume . '/supprimer', ['_csrf' => $jeton((string) $appel($b, 'resumes')[0])]);
    $dire('  ni le supprimer', $code . ' · ' . $nbResumes($idA), '404 · 3');
    $appel($a, 'resumes/' . $idResume . '/voix', ['_csrf' => $csrf, 'voix' => 'Zephyr']);
    $son2 = (string) bd_valeur('SELECT audio_nom FROM resumes_ia WHERE id = ?', [$idResume]);
    $sons[] = $son2;
    $dire('refaire la voix remplace l’ancien fichier',
        $oui($son2 !== $son && !is_file($dossierSon . '/' . $son) && is_file($dossierSon . '/' . $son2)), 'oui');

    // La voix refaite DANS la fenêtre : le formulaire envoie « fenetre », la redirection le garde, la réponse est un fragment.
    [$fragmentVoix, $urlVoix] = $appel($a, 'resumes/' . $idResume . '/voix', ['_csrf' => $csrf, 'voix' => 'Charon', 'fenetre' => '1']);
    $sons[] = (string) bd_valeur('SELECT audio_nom FROM resumes_ia WHERE id = ?', [$idResume]);
    $dire('la voix refaite dans la fenêtre : la réponse est un fragment (lecteur et message), pas une page entière',
        $oui(str_contains($urlVoix, 'fenetre=1') && str_contains($fragmentVoix, '<audio controls') && str_contains($fragmentVoix, 'Audio prêt')
            && !str_contains($fragmentVoix, '<header class="entete"')) . ' · ' . bd_valeur('SELECT audio_voix FROM resumes_ia WHERE id = ?', [$idResume]), 'oui · Charon');
    $son2 = (string) bd_valeur('SELECT audio_nom FROM resumes_ia WHERE id = ?', [$idResume]);

    echo "\n6 bis. L’audio demandé avec le résumé\n";
    [$page] = $appel($a, 'resumes');
    $dire('la case « Audio » est dans « Ce que je veux », avec le choix de la voix et l’attente annoncée',
        $oui(preg_match('/name="audio" value="1"/', $page) === 1 && str_contains($page, 'id="voix_lot"')
            && str_contains($page, 'data-attente-audio="Écriture et enregistrement en cours')), 'oui');
    $dejaLa = array_column(bd_all('SELECT id FROM resumes_ia WHERE user_id = ?', [$idA]), 'id');
    $nouveaux = static function () use ($idA, &$dejaLa): array {
        $l = bd_all('SELECT id, genre, audio_nom, audio_voix FROM resumes_ia WHERE user_id = ? ORDER BY id', [$idA]);
        return array_values(array_filter($l, static fn (array $x): bool => !in_array($x['id'], $dejaLa, false)));
    };
    $menage = static function () use ($idA, &$dejaLa, $dossierSon): void {
        foreach (bd_all('SELECT id, audio_nom FROM resumes_ia WHERE user_id = ?', [$idA]) as $l) {
            if (!in_array($l['id'], $dejaLa, false)) {
                if ($l['audio_nom'] !== null) { @unlink($dossierSon . '/' . basename((string) $l['audio_nom'])); }
                bd_run('DELETE FROM resumes_ia WHERE id = ? AND user_id = ?', [$l['id'], $idA]);
            }
        }
    };

    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume'], 'audio' => '1', 'voix' => 'Fenrir']);
    $n = $nouveaux();
    $octets = isset($n[0]['audio_nom']) && is_file($dossierSon . '/' . $n[0]['audio_nom']) ? (string) file_get_contents($dossierSon . '/' . $n[0]['audio_nom']) : '';
    $dire('« résumé » + audio : un résumé, son fichier WAV, la voix choisie',
        count($n) . ' · ' . $oui(str_starts_with($octets, 'RIFF') && strlen($octets) > 44) . ' · ' . ($n[0]['audio_voix'] ?? '?'), '1 · oui · Fenrir');
    $dire('  le message dit « avec son audio », et le lecteur est dans la fenêtre',
        $oui(str_contains($r, 'Résumé écrit, avec son audio.')) . ' · ' . $oui(str_contains((string) $appel($a, 'resumes/' . ($n[0]['id'] ?? 0) . '?fenetre=1')[0], '<audio controls')), 'oui · oui');
    $dire('  Gemini a lu le texte sans marque de mise en forme, avec la voix demandée',
        $oui(!preg_match('/[#*`]/', (string) ($dernier()['corps']['contents'][0]['parts'][0]['text'] ?? '#'))
            && ($dernier()['corps']['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? '') === 'Fenrir'), 'oui');
    $menage();

    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'audio' => '1']);
    $n = $nouveaux();
    $dire('  « Audio » coché seul : un résumé simple, lu à voix haute', ($n[0]['genre'] ?? '?') . ' · ' . $oui(isset($n[0]['audio_nom'])), 'resume · oui');
    $menage();

    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['points', 'questions'], 'audio' => '1']);
    $n = $nouveaux();
    $dire('  deux genres + audio : deux résumés, chacun avec sa voix',
        count($n) . ' · ' . $oui(count(array_filter($n, static fn (array $x): bool => $x['audio_nom'] !== null)) === 2 && str_contains($r, '2 résumés écrits, avec leur audio')), '2 · oui');
    $menage();

    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume']]);
    $n = $nouveaux();
    $dire('  sans la case : pas d’audio (c’est un choix, jamais un défaut)', count($n) . ' · ' . $oui(!isset($n[0]['audio_nom'])), '1 · oui');
    $menage();

    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-texte-seul-0123456789abc']);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume'], 'audio' => '1']);
    $n = $nouveaux();
    $dire('la voix refusée (limite) : le texte est gardé, sans audio, et on dit comment le demander ensuite',
        count($n) . ' · ' . $oui(!isset($n[0]['audio_nom'])) . ' · ' . $oui(str_contains($r, 'L’audio de « résumé » n’a pas pu être généré')
            && str_contains($r, 'Limite de la clé gratuite atteinte') && str_contains($r, 'Générer l’audio')), '1 · oui · oui');
    $menage();

    // Le budget de temps épuisé : le texte est écrit, la voix laissée, et le message le dit (sans accuser Google).
    file_put_contents($fichierEssai, "<?php\nreturn ['gemini' => ['adresse' => 'http://127.0.0.1:$port/v1beta/', 'pause_reessai' => 0, 'budget_audio' => 0]];\n");
    sleep(4);   // OPcache ne relit « parametres.test.php » que toutes les deux secondes : on lui laisse le temps de le voir changer
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['resume'], 'audio' => '1']);
    $n = $nouveaux();
    $dire('le budget de temps épuisé : texte écrit, voix laissée « faute de temps »',
        count($n) . ' · ' . $oui(!isset($n[0]['audio_nom'])) . ' · ' . $oui(str_contains($r, 'faute de temps') && !str_contains($r, 'Limite de la clé')), '1 · oui · oui');
    $menage();
    file_put_contents($fichierEssai, "<?php\nreturn ['gemini' => ['adresse' => 'http://127.0.0.1:$port/v1beta/', 'pause_reessai' => 0]];\n");
    sleep(4);   // idem : la suite ne doit plus voir « budget_audio »

    echo "\n7. Les refus de Google, dits clairement\n";
    foreach ([
        ['cle-quota-0123456789abcdef', 'Limite de la clé gratuite atteinte'],
        ['cle-mauvaise-0123456789abc', 'Google refuse cette clé'],
        ['cle-panne-0123456789abcdef', 'en panne ou surchargé. Réessayez dans un moment. (Réponse de Google : HTTP 503'],
        ['cle-vide-0123456789abcdefg', 'a refusé de traiter ce contenu'],
    ] as [$cleEssai, $message]) {
        $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => $cleEssai]);
        $avant = $nbResumes($idA);
        [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
        $dire(substr($cleEssai, 0, 12) . '… → « ' . $message . ' »',
            $oui(str_contains($r, $message) && !str_contains($r, $cleEssai)) . ' · ' . ($nbResumes($idA) - $avant), 'oui · 0');
    }
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-modele-0123456789abcdef']);
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
    $dire('un modèle inconnu : le suivant écrit le résumé', (string) bd_valeur('SELECT modele FROM resumes_ia WHERE id = ?', [$idOuvert($url)]), 'gemini-2.5-flash');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-quota-0123456789abcdef']);
    [$r] = $appel($a, 'resumes/' . $idResume . '/voix', ['_csrf' => $csrf, 'voix' => 'Kore']);
    $dire('la voix refusée par la limite : message clair, l’ancien audio reste',
        $oui(str_contains($r, 'Limite de la clé gratuite atteinte') && is_file($dossierSon . '/' . $son2)), 'oui');

    echo "\n8. Les quatre langues\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    foreach ([
        'en' => ['AI summaries', 'Write a summary', 'anglais'],
        'es' => ['Resúmenes con IA', 'Escribir un resumen', 'espagnol'],
        'de' => ['KI-Zusammenfassungen', 'Eine Zusammenfassung schreiben', 'allemand'],
    ] as $langue => [$titre, $demander, $nomLangue]) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel($a, 'resumes');
        [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
        $dire("$langue : page, bouton, et consigne donnée à Gemini en « $nomLangue »",
            $oui(str_contains($page, $titre) && str_contains($page, $demander)
                && str_contains((string) ($dernier()['corps']['systemInstruction']['parts'][0]['text'] ?? ''), $nomLangue)
                && bd_valeur('SELECT langue FROM resumes_ia WHERE id = ?', [$idOuvert($url)]) === $langue), 'oui');
    }
    $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);

    echo "\n9. Effacer\n";
    [$r] = $appel($a, 'resumes/' . $idResume . '/supprimer', ['_csrf' => $csrf]);
    $dire('le résumé et son audio disparaissent',
        $oui((string) bd_valeur('SELECT COUNT(*) FROM resumes_ia WHERE id = ?', [$idResume]) === '0' && !is_file($dossierSon . '/' . $son2)
            && str_contains($r, 'Résumé effacé')), 'oui');

    echo "\n10. Le vrai Google, par le serveur web (clé bidon, texte fictif)\n";
    // Le faux serveur n'est plus dans le chemin : c'est ce que fera le premier vrai essai. Sans rien de personnel :
    // la clé n'est pas une clé, le cours est celui de l'essai. Ce que ça prouve : le certificat de Google se
    // vérifie depuis PHP tel qu'Apache le lance (c'est là que « Impossible de joindre Google » était né).
    @unlink($fichierEssai);
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bidon-pour-essai-tls-0123456789']);
    $avant = $nbResumes($idA);
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA]]);
    $dire('Google refuse la clé bidon, et l’application le dit (pas « Impossible de joindre »)',
        str_contains($r, 'Google refuse cette clé') ? 'oui' : (str_contains($r, 'Impossible de joindre Google') && !stripos($r, 'certificate') ? 'oui (hors ligne : passé)' : substr(strip_tags($r), 0, 80)),
        str_contains($r, 'Google refuse cette clé') ? 'oui' : 'oui (hors ligne : passé)');
    $dire('  rien d’écrit, et la clé bidon n’apparaît nulle part', ($nbResumes($idA) - $avant) . ' · ' . $oui(!str_contains($r, 'cle-bidon-pour-essai')), '0 · oui');
} finally {
    foreach (array_filter($sons) as $s) { @unlink($dossierSon . '/' . $s); }
    // Les fichiers son des comptes d'essai encore rangés (un essai interrompu) : retrouvés par la base.
    foreach (bd_all('SELECT audio_nom FROM resumes_ia WHERE user_id IN (?, ?) AND audio_nom IS NOT NULL', [$idA ?? 0, $idB ?? 0]) as $l) {
        @unlink($dossierSon . '/' . basename((string) $l['audio_nom']));
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
