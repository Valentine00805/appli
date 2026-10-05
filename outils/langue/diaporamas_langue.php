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
    $dire('  boutons : précédente, lecture commentée, suivante, voix (navigateur / Gemini), vitesse, transcription',
        $oui(str_contains($popup, 'data-dia-action="precedent"') && str_contains($popup, 'data-dia-action="lire"') && str_contains($popup, 'data-dia-action="suivant"')
            && str_contains($popup, 'value="navigateur"') && str_contains($popup, 'value="gemini"') && str_contains($popup, 'data-dia-vitesse')
            && str_contains($popup, 'data-dia-action="transcription"') && str_contains($popup, 'Lecture commentée')), 'oui');
    $dire('  la transcription : un panneau (caché jusqu’au clic) avec « Copier le texte », « Télécharger (.txt) », son aide et le cadre du texte',
        $oui(str_contains($popup, 'data-dia-trans hidden') && str_contains($popup, 'data-dia-trans-texte') && str_contains($popup, 'data-dia-action="copier"')
            && str_contains($popup, 'data-dia-action="telecharger"') && str_contains($popup, 'Copier le texte') && str_contains($popup, 'Télécharger (.txt)')
            && str_contains($popup, 'phrase dite est surlignée') && !str_contains($popup, 'data-dia-commentaire')), 'oui');
    $dire('  le lecteur reçoit le titre et un nom de fichier propre pour la transcription',
        $oui(str_contains($popup, 'data-titre="Diaporama commenté — Cyber"') && str_contains($popup, 'data-nom-fichier="Diaporama_commenté_Cyber"')), 'oui');
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
    $dire('  et la transcription : surlignage de la phrase dite, copie (presse-papiers), téléchargement .txt, départ à une phrase',
        $oui(str_contains($jsDia, 'surligner(') && str_contains($jsDia, 'clipboard.writeText') && str_contains($jsDia, "type: 'text/plain;charset=utf-8'")
            && str_contains($jsDia, 'phrasePourFraction') && str_contains($jsDia, 'jouer(k)') && str_contains($jsDia, 'ontimeupdate')), 'oui');

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
        'en' => [['Narrated slideshow', 'slides, read aloud', 'My slideshows'], ['Narrated playback', 'Browser (free, instant)', 'Make the Gemini voice', 'Speed', 'Transcript', 'Copy the text', 'Download (.txt)']],
        'es' => [['Presentación comentada', 'diapositivas, leídas en voz alta', 'Mis presentaciones'], ['Lectura comentada', 'Navegador (gratuita, inmediata)', 'Crear la voz de Gemini', 'Velocidad', 'Transcripción', 'Copiar el texto', 'Descargar (.txt)']],
        'de' => [['Kommentierte Präsentation', 'Folien, die laut vorgelesen werden', 'Meine Präsentationen'], ['Kommentierte Wiedergabe', 'Browser (kostenlos, sofort)', 'Gemini-Stimme erzeugen', 'Tempo', 'Transkript', 'Text kopieren', 'Herunterladen (.txt)']],
        'fr' => [['Diaporama commenté', 'des diapositives, lues à voix haute', 'Mes diaporamas'], ['Lecture commentée', 'Navigateur (gratuite, immédiate)', 'Fabriquer la voix Gemini', 'Vitesse', 'Transcription', 'Copier le texte', 'Télécharger (.txt)']],
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

    echo "\n7 bis. Dans la fiche de révision\n";
    require_once dirname(__DIR__, 2) . '/src/TextePdf.php';
    $sansEspaces = static fn (string $t): string => (string) preg_replace('/\s+/u', '', $t);
    $coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idB]);
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Autre cours', 'Un autre cours, pas lu par le diaporama.']);
    $coursAutre = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Autre cours']);
    $nbLiens = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM diaporama_cours WHERE diaporama_id = ?', [$id]);

    foreach ([
        'en' => ['Revision sheet', 'Add to the revision sheet', '🎞️ Slideshows', 'No slideshow in this sheet.', 'Create a slideshow in “AI summaries” →'],
        'es' => ['Ficha de repaso', 'Añadir a la ficha de repaso', '🎞️ Presentaciones', 'Ninguna presentación en esta ficha.', 'Crear una presentación en «Resúmenes con IA» →'],
        'de' => ['Lernblatt', 'Zum Lernblatt hinzufügen', '🎞️ Präsentationen', 'Keine Präsentation in diesem Lernblatt.', 'Eine Präsentation unter „KI-Zusammenfassungen“ erstellen →'],
        'fr' => ['Fiche de révision', 'Ajouter à la fiche de révision', '🎞️ Diaporamas', 'Aucun diaporama dans cette fiche.', 'Créer un diaporama dans « Résumés IA » →'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$popup] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
        [$fiche] = $appel($a, 'revision/' . $coursA);
        $dire("$langue : la page du diaporama (fiche de révision) et le rayon de la fiche parlent la langue",
            $oui(str_contains($popup, $mots[0]) && str_contains($popup, $mots[1]) && str_contains($fiche, $mots[2])
                && str_contains($fiche, $mots[3]) && str_contains($fiche, $mots[4])), 'oui');
    }

    [$popup] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
    $dire('la page du diaporama propose de le ranger dans la fiche de Cyber, de télécharger le PDF et de le joindre',
        $oui(str_contains($popup, 'data-dia-fiche') && str_contains($popup, 'Ajouter à la fiche de révision — Cyber')
            && str_contains($popup, 'Télécharger en PDF') && str_contains($popup, 'Joindre le PDF à ma fiche de révision — Cyber')
            && !str_contains($popup, 'Retirer de la fiche')) . ' · ' . $nbLiens(), 'oui · 0');
    [$r, $url] = $appel($a, 'diaporamas/' . $id . '/fiche', ['_csrf' => $jeton($popup), 'cours' => $coursA]);
    $dire('« Ajouter » : le diaporama est rangé dans la fiche du cours, et la page le dit',
        $oui(str_contains($r, 'Diaporama ajouté à la fiche de révision de « Cyber »') && str_contains($url, '/diaporamas/' . $id)) . ' · ' . $nbLiens(), 'oui · 1');
    [$fiche] = $appel($a, 'revision/' . $coursA);
    $dire('  la fiche de révision a son rayon « Diaporamas » : le lien s’ouvre en fenêtre, avec le nombre de diapositives',
        $oui(str_contains($fiche, 'data-diaporamas-fiche') && preg_match('#<a href="[^"]*/diaporamas/' . $id . '" data-fenetre>Diaporama commenté — Cyber</a>#', $fiche) === 1
            && str_contains($fiche, '3 diapositives') && str_contains($fiche, 'name="retour" value="fiche"')), 'oui');
    [$popup] = $appel($a, 'diaporamas/' . $id . '?fenetre=1');
    $dire('  et la page du diaporama dit où il est rangé, avec « Retirer de la fiche » (plus de bouton « Ajouter »)',
        $oui(str_contains($popup, 'Dans la fiche de') && str_contains($popup, 'Retirer de la fiche') && !str_contains($popup, 'Ajouter à la fiche de révision')), 'oui');
    [$r] = $appel($a, 'diaporamas/' . $id . '/fiche', ['_csrf' => $jeton($popup), 'cours' => $coursA]);
    $dire('  le ranger deux fois ne le range qu’une fois (message « déjà dans la fiche »)',
        $oui(str_contains($r, 'est déjà dans la fiche de « Cyber »')) . ' · ' . $nbLiens(), 'oui · 1');
    foreach (['un cours à soi mais que le diaporama n’a pas lu' => $coursAutre, 'le cours d’un autre compte' => $coursB, 'un numéro absurde' => 999999999] as $quoi => $cible) {
        [$r] = $appel($a, 'diaporamas/' . $id . '/fiche', ['_csrf' => $csrf, 'cours' => $cible]);
        $dire("  refusé : $quoi", $oui(str_contains($r, 'choisissez un des cours que ce diaporama a lus')) . ' · ' . $nbLiens(), 'oui · 1');
    }
    [, , $code] = $appel($b, 'diaporamas/' . $id . '/fiche', ['_csrf' => $csrfB, 'cours' => $coursA]);
    [, , $code2] = $appel($b, 'diaporamas/' . $id . '/fiche/retirer', ['_csrf' => $csrfB, 'cours' => $coursA]);
    $dire('  un autre compte ne range ni ne retire rien', $code . ' · ' . $code2 . ' · ' . $nbLiens(), '404 · 404 · 1');

    [$corps, , $code, $type] = $appel($a, 'diaporamas/' . $id . '/pdf');
    $texte = $sansEspaces((string) (function () use ($corps) {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf'); file_put_contents($tmp, $corps);
        $t = TextePdf::extraire($tmp); @unlink($tmp);
        return $t;
    })());
    $dire('le PDF du diaporama : un vrai PDF, avec le titre, les titres des diapositives, leurs points et leurs commentaires',
        $code . ' · ' . $oui(str_starts_with($corps, '%PDF-') && str_contains($type, 'application/pdf')
            && str_contains($texte, 'Diaporamacommenté—Cyber') && str_contains($texte, '1.Pourquoichiffrer?') && str_contains($texte, '2.Lepare-feu')
            && str_contains($texte, 'Intégritédesdonnées') && str_contains($texte, 'Unpare-feufiltreletraficréseau') && str_contains($texte, '3.Enconclusion')
            && str_contains($texte, 'Défenseenprofondeur')), '200 · oui');
    $dire('  le script glissé dans le texte n’y est qu’un texte', $oui(!str_contains($corps, '/JavaScript') && !str_contains($corps, '/JS')), 'oui');
    [, , $code] = $appel($b, 'diaporamas/' . $id . '/pdf');
    $dire('  un autre compte ne le télécharge pas', (string) $code, '404');

    $avantF = (int) bd_valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ? AND pour_fiche = 1', [$coursA]);
    [$r] = $appel($a, 'diaporamas/' . $id . '/pdf-fiche', ['_csrf' => $csrf, 'cours' => $coursA]);
    $joints = bd_all('SELECT nom_origine, nom_stocke, mime, taille FROM fichiers WHERE cours_id = ? AND pour_fiche = 1 ORDER BY id DESC', [$coursA]);
    $chemin = $racine . '/storage/uploads/' . basename((string) ($joints[0]['nom_stocke'] ?? 'absent'));
    $dire('« Joindre le PDF » : un fichier de la fiche (pas du cours), de type PDF, bien rangé, et la page le dit',
        (count($joints) - $avantF) . ' · ' . ($joints[0]['nom_origine'] ?? '?') . ' · ' . ($joints[0]['mime'] ?? '?') . ' · '
        . $oui(is_file($chemin) && (int) $joints[0]['taille'] === filesize($chemin) && str_starts_with((string) file_get_contents($chemin), '%PDF-')
            && str_contains($r, 'PDF joint à la fiche de révision de « Cyber »')), '1 · Diaporama commenté — Cyber.pdf · application/pdf · oui');
    $appel($a, 'diaporamas/' . $id . '/pdf-fiche', ['_csrf' => $csrf, 'cours' => $coursA]);
    $noms = array_column(bd_all('SELECT nom_origine FROM fichiers WHERE cours_id = ? AND pour_fiche = 1', [$coursA]), 'nom_origine');
    $dire('  un second ajout reçoit un numéro', $oui(in_array('Diaporama commenté — Cyber (2).pdf', $noms, true)), 'oui');
    [$r] = $appel($a, 'diaporamas/' . $id . '/pdf-fiche', ['_csrf' => $csrf, 'cours' => $coursAutre]);
    [, , $code] = $appel($b, 'diaporamas/' . $id . '/pdf-fiche', ['_csrf' => $csrfB, 'cours' => $coursA]);
    $dire('  refusé pour un cours que le diaporama n’a pas lu, et pour un autre compte',
        $oui(str_contains($r, 'choisissez un des cours que ce diaporama a lus')) . ' · ' . $code . ' · ' . bd_valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$coursAutre]), 'oui · 404 · 0');

    [$fiche] = $appel($a, 'revision/' . $coursA);
    [$r, $url] = $appel($a, 'diaporamas/' . $id . '/fiche/retirer', ['_csrf' => $jeton($fiche), 'cours' => $coursA, 'retour' => 'fiche']);
    $dire('« Retirer » depuis la fiche : on revient à la fiche, qui n’a plus le diaporama — lequel n’est pas effacé',
        $oui(str_contains($url, '/revision/' . $coursA) && str_contains($r, 'Aucun diaporama dans cette fiche.') && str_contains($r, 'retiré de la fiche'))
        . ' · ' . $nbLiens() . ' · ' . $nbDia(), 'oui · 0 · 1');
    $appel($a, 'diaporamas/' . $id . '/fiche', ['_csrf' => $csrf, 'cours' => $coursA]);   // de nouveau rangé : il disparaîtra avec le diaporama

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
    $dire('  et son rangement dans la fiche disparaît avec lui', (string) bd_valeur('SELECT COUNT(*) FROM diaporama_cours WHERE diaporama_id = ?', [$id]), '0');
} finally {
    // Les fichiers voix des comptes d'essai encore rangés (un essai interrompu) : retrouvés par la base.
    foreach (bd_all('SELECT diapos FROM diaporamas WHERE user_id IN (?, ?)', [$idA ?? 0, $idB ?? 0]) as $ligne) {
        foreach ((array) json_decode((string) $ligne['diapos'], true) as $d) {
            if (isset($d['a'])) { @unlink($dossierSon . '/' . basename((string) $d['a'])); }
        }
    }
    foreach (array_filter($sons) as $s) { @unlink($dossierSon . '/' . basename($s)); }
    // Les PDF joints à une fiche d'essai : des fichiers de disque que la base, en cascade, oublie.
    foreach (bd_all('SELECT nom_stocke FROM fichiers WHERE user_id IN (?, ?)', [$idA ?? 0, $idB ?? 0]) as $pj) {
        @unlink($racine . '/storage/uploads/' . basename((string) $pj['nom_stocke']));
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
