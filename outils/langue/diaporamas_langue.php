<?php
/**
 * Les diaporamas commentés, de bout en bout, contre un FAUX Gemini local : l'écriture depuis « Résumés IA », le lecteur
 * (page entière et fenêtre), la voix fabriquée diapositive par diapositive, le cloisonnement entre comptes, les quatre
 * langues. Aucun appel chez Google.
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
$port = 8768;
$journal = sys_get_temp_dir() . '/faux_gemini_dia.log';

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

$emails = ['dia-a@exemple-test.fr', 'dia-b@exemple-test.fr'];
foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
foreach ($emails as $i => $a) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, 'Diaporama_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai diaporamas']);
}
[$idA, $idB] = array_map(static fn (string $a): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$a]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu, fiche_revision) VALUES (?, ?, ?, ?)',
    [$idA, 'Cyber', "Le chiffrement protège la confidentialité des données.\n\nUn pare-feu filtre le trafic.", 'Fiche : penser à la défense en profondeur.']);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idB, 'Cours secret de B', 'Contenu privé de B.']);
$coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);

$cookies = [$emails[0] => __DIR__ . '/ck_dia_a.txt', $emails[1] => __DIR__ . '/ck_dia_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int, 3: string} corps, adresse finale, code, type de contenu */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_CONTENT_TYPE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$dernier = static fn (): array => json_decode((string) @file_get_contents(sys_get_temp_dir() . '/faux_gemini_dernier.json'), true) ?: [];
$idDia = static fn (string $url): int => preg_match('/[?&]diaporama=(\d+)/', $url, $m) === 1 || preg_match('#/diaporamas/(\d+)$#', $url, $m) === 1 ? (int) $m[1] : 0;
$nbDia = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM diaporamas WHERE user_id = ?', [$idA]);
$diapos = static fn (int $id): array => json_decode((string) bd_valeur('SELECT diapos FROM diaporamas WHERE id = ?', [$id]), true) ?: [];
$voix = static function (string $compte, int $id, string $csrf, mixed $n, string $v = 'Kore') use ($appel): array {
    [$corps, , $code] = $appel($compte, 'diaporamas/' . $id . '/voix', ['_csrf' => $csrf, 'n' => $n, 'voix' => $v]);
    return [json_decode($corps, true) ?: [], $code];
};
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
    $dire('« Résumés IA » liste ses diaporamas (vide) ; pas de case « Diaporama commenté » sans formulaire d’écriture',
        $oui(str_contains($page, 'Mes diaporamas') && str_contains($page, 'Aucun diaporama pour l’instant.') && !str_contains($page, 'value="diaporama"')), 'oui');
    [$r] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['diaporama']]);
    $dire('  forcer la demande : « Il faut d’abord enregistrer votre clé », rien d’écrit',
        $oui(str_contains($r, 'enregistrer votre clé Gemini')) . ' · ' . $nbDia(), 'oui · 0');

    echo "\n2. Écrire un diaporama\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    [$page] = $appel($a, 'resumes');
    $dire('avec une clé : la case « Diaporama commenté » parmi les genres, avec son aide',
        $oui(substr_count($page, 'name="genres[]" value="diaporama"') === 1 && str_contains($page, 'Diaporama commenté') && str_contains($page, 'des diapositives, lues à voix haute')), 'oui');
    $nbResumes = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM resumes_ia WHERE user_id = ?', [$idA]);
    [$r, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['diaporama'], 'longueur' => 'moyen']);
    $id = $idDia($url);
    $dire('un diaporama seul : « Résumés IA » le dit et l’ouvre en fenêtre (lien « data-ouvrir-auto »), sans résumé écrit',
        $oui($id > 0 && str_contains($url, '/resumes?diaporama=') && str_contains($r, 'Diaporama écrit par l’IA : 3 diapositives')
            && preg_match('#href="[^"]*/diaporamas/' . $id . '" data-fenetre data-ouvrir-auto#', $r) === 1) . ' · ' . $nbDia() . ' · ' . $nbResumes(), 'oui · 1 · 0');
    $lignes = $diapos($id);
    $dire('  3 diapositives (la vide est écartée) ; sans commentaire, les points se disent ; le HTML glissé n’est que du texte',
        count($lignes) . ' · ' . ($lignes[0]['t'] ?? '?') . ' · ' . ($lignes[2]['c'] ?? '?') . ' · '
        . $oui(!str_contains(json_encode($lignes, JSON_UNESCAPED_UNICODE), '<') && ($lignes[0]['p'][1] ?? '') === 'Intégrité des données'),
        '3 · Pourquoi chiffrer ? · Défense en profondeur · oui');
    $dire('  titre, langue et modèle', bd_valeur('SELECT titre FROM diaporamas WHERE id = ?', [$id]) . ' · ' . bd_valeur('SELECT langue FROM diaporamas WHERE id = ?', [$id])
        . ' · ' . bd_valeur('SELECT modele FROM diaporamas WHERE id = ?', [$id]), 'Diaporama commenté — Cyber · fr · gemini-3.8-flash');
    $envoye = $dernier();
    $texteEnvoye = json_encode($envoye['corps'] ?? [], JSON_UNESCAPED_UNICODE);
    $consigne = (string) ($envoye['corps']['systemInstruction']['parts'][0]['text'] ?? '');
    $dire('  Gemini a reçu le cours ET sa fiche, avec la clé de l’utilisateur ; consigne : 10 diapositives, voix « dite », en français',
        $oui(($envoye['cle'] ?? '') === 'cle-bonne-0123456789abcdef' && str_contains($texteEnvoye, 'Le chiffrement protège') && str_contains($texteEnvoye, 'défense en profondeur')
            && str_contains($consigne, 'exactement 10 diapositives') && str_contains($consigne, 'DIT à voix haute') && str_contains($consigne, 'français')), 'oui');
    $dire('  réponse demandée en JSON, selon un schéma d’objet à « diapositives »',
        $oui(($envoye['corps']['generationConfig']['responseMimeType'] ?? '') === 'application/json'
            && isset($envoye['corps']['generationConfig']['responseSchema']['properties']['diapositives'])), 'oui');
    foreach (['court' => 6, 'long' => 16] as $longueur => $n) {
        $avant = $nbDia();
        $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['diaporama'], 'longueur' => $longueur]);
        $dire("  la longueur « $longueur » demande $n diapositives",
            $oui(str_contains((string) ($dernier()['corps']['systemInstruction']['parts'][0]['text'] ?? ''), "exactement $n diapositives")) . ' · ' . ($nbDia() - $avant), 'oui · 1');
    }
    bd_run('DELETE FROM diaporamas WHERE user_id = ? AND id <> ?', [$idA, $id]);

    [$avantR, $avantD] = [$nbResumes(), $nbDia()];
    [, $url] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['points', 'diaporama']]);
    $dire('un résumé ET un diaporama dans la même demande : le résumé s’ouvre, le diaporama est dans la liste',
        $oui(str_contains($url, '/resumes?ouvrir=')) . ' · ' . ($nbResumes() - $avantR) . ' · ' . ($nbDia() - $avantD), 'oui · 1 · 1');
    bd_run('DELETE FROM diaporamas WHERE user_id = ? AND id <> ?', [$idA, $id]);
    bd_run('DELETE FROM resumes_ia WHERE user_id = ?', [$idA]);
    [$page] = $appel($a, 'resumes');
    $dire('  la liste de « Résumés IA » montre le diaporama, avec son nombre de diapositives',
        $oui(preg_match('#href="[^"]*/diaporamas/' . $id . '" data-fenetre><strong>Diaporama commenté — Cyber</strong>#', $page) === 1 && str_contains($page, '3 diapositives')), 'oui');

    echo "\n3. Le lecteur\n";
    [$popup, , $code] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
    $dire('en fenêtre : un fragment large (sans bandeau, sans lien de retour), les diapositives pour le script, sans script à exécuter',
        $code . ' · ' . $oui(str_contains($popup, 'data-diaporama') && !str_contains($popup, '<header class="entete"') && !str_contains($popup, '← Tous les résumés')
            && str_contains($popup, 'data-large data-document') && !str_contains($popup, 'diaporama.js')
            && str_contains($popup, 'data-envoi-fenetre') && str_contains($popup, 'data-diapos="')), '200 · oui');
    $dire('  et le plan en texte (pour qui n’a pas JavaScript), avec les commentaires',
        $oui(str_contains($popup, 'Pourquoi chiffrer ?') && str_contains($popup, 'Le pare-feu') && str_contains($popup, 'Un pare-feu filtre le trafic réseau')), 'oui');
    $dire('  le script reçoit « a : false » (pas encore de voix Gemini) et ne reçoit pas de nom de fichier',
        $oui(str_contains($popup, '&quot;a&quot;:false') && !str_contains($popup, '.wav')), 'oui');
    $dire('  boutons : précédente, lecture commentée, suivante, voix (navigateur / Gemini), vitesse, commentaire',
        $oui(str_contains($popup, 'data-dia-action="precedent"') && str_contains($popup, 'data-dia-action="lire"') && str_contains($popup, 'data-dia-action="suivant"')
            && str_contains($popup, 'value="navigateur"') && str_contains($popup, 'value="gemini"') && str_contains($popup, 'data-dia-vitesse')
            && str_contains($popup, 'data-dia-action="commentaire"') && str_contains($popup, 'Lecture commentée')), 'oui');
    $dire('  avec une clé : le choix de la voix Gemini et « Fabriquer la voix Gemini »',
        $oui(substr_count($popup, '<option value="Kore"') === 1 && str_contains($popup, 'Fabriquer la voix Gemini')), 'oui');
    [$entier] = $appel($a, 'diaporamas/' . $id);
    [$jsDia] = $appel($a, 'assets/js/diaporama.js');
    [$jsApp] = $appel($a, 'assets/js/app.js');
    $dire('  la page entière : lien de retour, script du lecteur (que app.js appelle sur ce qu’il pose en fenêtre)',
        $oui(str_contains($entier, '<header class="entete"') && str_contains($entier, '← Tous les résumés') && str_contains($entier, 'diaporama.js')
            && str_contains($jsDia, 'initialiserDiaporama') && str_contains($jsApp, 'window.initialiserDiaporama(corps)')
            && str_contains($jsApp, 'window.initialiserDiaporama(corpsDessus)') && !str_contains($entier, 'data-envoi-fenetre')), 'oui');
    $dire('  le script lit phrase par phrase, a un repli sans voix, et fait taire un lecteur dont la fenêtre se ferme',
        $oui(str_contains($jsDia, 'SpeechSynthesisUtterance') && str_contains($jsDia, 'attendre(') && str_contains($jsDia, 'MutationObserver')), 'oui');

    echo "\n4. Le cloisonnement entre comptes\n";
    [$pageB] = $appel($b, 'resumes');
    $csrfB = $jeton($pageB);
    [, , $code] = $appel($b, 'diaporamas/' . $id);
    $dire('un autre compte ne voit pas le diaporama', (string) $code, '404');
    [$j, $code] = $voix($b, $id, $csrfB, 0);
    $dire('  ne lui fait pas fabriquer de voix', $code . ' · ' . $oui(($j['ok'] ?? true) === false) . ' · ' . $oui(!isset($diapos($id)[0]['a'])), '404 · oui · oui');
    [, , $code] = $appel($b, 'diaporamas/' . $id . '/audio/0');
    $dire('  n’écoute rien', (string) $code, '404');
    [, , $code] = $appel($b, 'diaporamas/' . $id . '/supprimer', ['_csrf' => $csrfB]);
    $dire('  ne l’efface pas', $code . ' · ' . $nbDia(), '404 · 1');
    [$lu] = $appel($b, 'resumes');
    $dire('  et sa liste ne le montre pas', $oui(!str_contains($lu, 'Diaporama commenté — Cyber')), 'oui');

    echo "\n5. La voix, une diapositive à la fois\n";
    [$j, $code] = $voix($a, $id, $csrf, 0, 'Puck');
    $d = $diapos($id);
    $fichier = $dossierSon . '/' . ($d[0]['a'] ?? 'absent');
    $sons[] = $d[0]['a'] ?? '';
    $dire('la voix de la diapositive 1 : fichier WAV rangé hors du site, nom inscrit dans le diaporama, voix retenue',
        $code . ' · ' . $oui(($j['ok'] ?? false) === true && ($j['n'] ?? -1) === 0) . ' · '
        . $oui(preg_match('/^[0-9a-f]{24}\.wav$/', (string) ($d[0]['a'] ?? '')) === 1 && is_file($fichier) && str_starts_with((string) file_get_contents($fichier), 'RIFF')) . ' · '
        . bd_valeur('SELECT voix FROM diaporamas WHERE id = ?', [$id]), '200 · oui · oui · Puck');
    $dire('  les autres diapositives n’ont pas encore de voix', $oui(!isset($d[1]['a']) && !isset($d[2]['a'])), 'oui');
    [$j, ] = $voix($a, $id, $csrf, 1);
    [$j2, ] = $voix($a, $id, $csrf, 2);
    $d = $diapos($id);
    foreach ($d as $rang) { $sons[] = $rang['a'] ?? ''; }
    $dire('  les suivantes s’ajoutent sans effacer les précédentes', $oui(isset($d[0]['a'], $d[1]['a'], $d[2]['a']) && count(array_unique(array_column($d, 'a'))) === 3), 'oui');
    [$corps, , $code, $type] = $appel($a, 'diaporamas/' . $id . '/audio/1');
    $dire('  la voix s’écoute : audio/wav, pour son propriétaire', $code . ' · ' . $type . ' · ' . $oui(str_starts_with($corps, 'RIFF')), '200 · audio/wav · oui');
    [, , $code] = $appel($a, 'diaporamas/' . $id . '/audio/9');
    $dire('  une diapositive qui n’existe pas : introuvable', (string) $code, '404');
    $ancien = $d[0]['a'];
    [$j, ] = $voix($a, $id, $csrf, 0, 'Kore');
    $d = $diapos($id);
    $sons[] = $d[0]['a'] ?? '';
    $dire('  refaire une voix remplace le fichier (l’ancien est effacé du disque)',
        $oui(($d[0]['a'] ?? $ancien) !== $ancien && !is_file($dossierSon . '/' . $ancien) && is_file($dossierSon . '/' . $d[0]['a'])), 'oui');
    $dire('  la page rouverte sait qu’il y a des voix', $oui(substr_count((string) $appel($a, 'diaporamas/' . $id . '?fenetre=1')[0], '&quot;a&quot;:true') === 3), 'oui');

    foreach ([['n trop grand', 9, 422], ['n absent', null, 422], ['n pas un nombre', 'abc', 422]] as [$quoi, $n, $attendu]) {
        $avant = json_encode($diapos($id));
        [$j, $code] = $voix($a, $id, $csrf, $n ?? '');
        $dire("  refusé : $quoi", $code . ' · ' . $oui(($j['ok'] ?? true) === false) . ' · ' . $oui(json_encode($diapos($id)) === $avant), "$attendu · oui · oui");
    }
    $avant = json_encode($diapos($id));
    [$j, $code] = $voix($a, $id, 'faux', 0);
    $dire('  sans le bon jeton CSRF, rien n’est fabriqué', $oui($code !== 200 && json_encode($diapos($id)) === $avant), 'oui');

    echo "\n6. Quand Google refuse\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-quota-0123456789abcdef']);
    $avant = json_encode($diapos($id));
    [$j, $code] = $voix($a, $id, $csrf, 1);
    $dire('une limite atteinte : une réponse claire en JSON (pas d’erreur brute), rien de changé',
        $code . ' · ' . $oui(($j['ok'] ?? true) === false && ($j['nature'] ?? '') === 'quota' && ($j['message'] ?? '') !== '') . ' · ' . $oui(json_encode($diapos($id)) === $avant), '502 · oui · oui');
    [$vide] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['diaporama']]);
    $dire('  et l’écriture d’un diaporama dit la même chose, sans rien écrire', $oui(str_contains($vide, 'limite') || str_contains($vide, 'quota')) . ' · ' . $nbDia(), 'oui · 1');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-sans-json-0123456789abc']);
    [$vide] = $appel($a, 'resumes/generer', ['_csrf' => $csrf, 'cours' => [$coursA], 'genres' => ['diaporama']]);
    $dire('un modèle qui répond en prose : « pas de diaporama exploitable », rien d’écrit', $oui(str_contains($vide, 'n’a pas rendu de diaporama exploitable')) . ' · ' . $nbDia(), 'oui · 1');
    $appel($a, 'compte/gemini/retirer', ['_csrf' => $csrf]);
    [$j, $code] = $voix($a, $id, $csrf, 1);
    $dire('sans clé : la fabrication de voix répond « clé manquante » (409), rien de changé',
        $code . ' · ' . ($j['nature'] ?? '?') . ' · ' . $oui(json_encode($diapos($id)) === $avant), '409 · cle · oui');
    [$sansCle] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
    $dire('  le lecteur propose alors d’ajouter la clé (et la voix du navigateur reste là)',
        $oui(str_contains($sansCle, '/compte#gemini') && !str_contains($sansCle, 'data-dia-generer') && str_contains($sansCle, 'value="navigateur"')), 'oui');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);

    echo "\n7. Les quatre langues\n";
    foreach ([
        'en' => [['Narrated slideshow', 'slides, read aloud', 'My slideshows'], ['Narrated playback', 'Browser (free, instant)', 'Make the Gemini voice', 'Speed']],
        'es' => [['Presentación comentada', 'diapositivas, leídas en voz alta', 'Mis presentaciones'], ['Lectura comentada', 'Navegador (gratuita, inmediata)', 'Crear la voz de Gemini', 'Velocidad']],
        'de' => [['Kommentierte Präsentation', 'Folien, die laut vorgelesen werden', 'Meine Präsentationen'], ['Kommentierte Wiedergabe', 'Browser (kostenlos, sofort)', 'Gemini-Stimme erzeugen', 'Tempo']],
        'fr' => [['Diaporama commenté', 'des diapositives, lues à voix haute', 'Mes diaporamas'], ['Lecture commentée', 'Navigateur (gratuite, immédiate)', 'Fabriquer la voix Gemini', 'Vitesse']],
    ] as $langue => [$motsListe, $motsLecteur]) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel($a, 'resumes');
        $dire("$langue : « Résumés » — la case du genre, son aide et la liste",
            $oui(array_reduce($motsListe, static fn (bool $ok, string $m): bool => $ok && str_contains($page, $m), true)), 'oui');
        [$popup] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
        $dire("$langue : le lecteur parle la langue (boutons, voix, vitesse, phrases du script)",
            $oui(array_reduce($motsLecteur, static fn (bool $ok, string $m): bool => $ok && str_contains($popup, $m), true)
                && str_contains($page, '"dia.diapo":"' . ['fr' => 'Diapositive {i} sur {n}', 'en' => 'Slide {i} of {n}', 'es' => 'Diapositiva {i} de {n}', 'de' => 'Folie {i} von {n}'][$langue] . '"')
                && !preg_match('/>\s*(dia|ria\.genre\.diaporama)[a-z_.]*\s*</', $popup)), 'oui');
    }

    echo "\n8. Effacer\n";
    $fichiers = array_filter(array_column($diapos($id), 'a'));
    [$popup] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
    [$r, $url] = $appel($a, 'diaporamas/' . $id . '/supprimer', ['_csrf' => $jeton($popup), 'fenetre' => '1']);
    clearstatcache(true);   // PHP garde en mémoire le dernier fichier testé : sans cela, il serait encore « là »
    $presents = array_filter($fichiers, static fn (string $f): bool => is_file($dossierSon . '/' . $f));
    // La liste n'a pas de fragment : le serveur répond par la page entière, et le script de la fenêtre recharge la page derrière.
    $dire('effacé depuis la fenêtre : retour à « Résumés IA » ; le diaporama et ses voix ont disparu',
        $oui(str_contains($url, '/resumes') && str_contains($r, '<header class="entete"')) . ' · ' . $nbDia() . ' · ' . count($presents), 'oui · 0 · 0');
    if ($presents !== []) { echo "      (restent sur le disque : " . implode(', ', $presents) . " ; les trois du diaporama : " . implode(', ', $fichiers) . ")
"; }
    [$apres] = $appel($a, 'resumes');
    $dire('  et son message le dit', $oui(str_contains($apres, 'Diaporama supprimé.') || str_contains($r, 'Diaporama supprimé.')), 'oui');
} finally {
    // Les fichiers voix des comptes d'essai encore rangés (un essai interrompu) : retrouvés par la base.
    foreach (bd_all('SELECT diapos FROM diaporamas WHERE user_id IN (?, ?)', [$idA ?? 0, $idB ?? 0]) as $ligne) {
        foreach ((array) json_decode((string) $ligne['diapos'], true) as $d) {
            if (isset($d['a'])) { @unlink($dossierSon . '/' . basename((string) $d['a'])); }
        }
    }
    foreach (array_filter($sons) as $s) { @unlink($dossierSon . '/' . basename($s)); }
    if (is_resource($serveur)) { proc_terminate($serveur); proc_close($serveur); }
    @unlink($fichierEssai);
    foreach ($cookies as $f) { @unlink($f); }
    @unlink(sys_get_temp_dir() . '/faux_gemini_dernier.json');
    foreach ($emails as $a) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$a, '%@exemple-test.fr']); }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
