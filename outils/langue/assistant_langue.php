<?php
/**
 * L'assistant IA, de bout en bout, contre un FAUX Gemini local : sans clé, la première question et la suite (l'historique part avec
 * chaque message), le cours dont on parle (le sien seulement), les refus (message vide ou trop long, jeton, discussion d'un autre),
 * les pannes de Gemini (rien d'écrit), renommer et supprimer, les bornes de l'historique, les quatre langues. Aucun appel chez Google.
 *
 * Le faux serveur est lancé ici ; l'application est redirigée vers lui par « config/parametres.test.php » (un fichier que Config ne
 * lit que depuis le poste, et que cette suite retire à la fin).
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

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$port = 8767;
$journal = sys_get_temp_dir() . '/faux_gemini_assistant.log';
@unlink($fichierEssai);
file_put_contents($fichierEssai, "<?php\nreturn ['gemini' => ['adresse' => 'http://127.0.0.1:$port/v1beta/', 'pause_reessai' => 0]];\n");
$serveur = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/faux_gemini.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $journal, 'w'], 2 => ['file', $journal, 'a']], $tuyaux);
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $no, $str, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

$emails = ['ia-a@exemple-test.fr', 'ia-b@exemple-test.fr'];
foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Assistant_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai assistant']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu, fiche_revision) VALUES (?, ?, ?, ?)',
    [$idA, 'Réseaux', "Le modèle OSI compte sept couches.\n\nLa couche transport gère TCP et UDP.", 'Fiche : retenir les sept couches.']);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idB, 'Cours secret de B', 'Contenu privé de B.']);
$coursA = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
$coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idB]);

$cookies = [$emails[0] => __DIR__ . '/ck_ia_a.txt', $emails[1] => __DIR__ . '/ck_ia_b.txt'];
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
$nbDiscussions = static fn (int $uid): int => (int) bd_valeur('SELECT COUNT(*) FROM assistant_conversations WHERE user_id = ?', [$uid]);
$nbMessages = static fn (int $uid): int => (int) bd_valeur('SELECT COUNT(*) FROM assistant_messages m JOIN assistant_conversations c ON c.id = m.conversation_id WHERE c.user_id = ?', [$uid]);
$json = ['Accept: application/json'];

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    [$page] = $appel($a, 'assistant');
    $csrf = $jeton($page);
    [$pageB] = $appel($b, 'assistant');
    $csrfB = $jeton($pageB);

    echo "\n1. Sans clé\n";
    $dire('la page dit d\'enregistrer la clé Gemini, avec un lien vers « Mon compte »',
        $oui(str_contains($page, 'enregistrer votre clé Gemini') && str_contains($page, '/compte#gemini')), 'oui');
    $dire('  le formulaire est là, mais grisé ; le script n\'est pas chargé', $oui(str_contains($page, 'data-ia-formulaire') && preg_match('/<textarea[^>]*disabled/', $page) === 1
        && !str_contains($page, 'assistant.js') && !str_contains($page, 'data-assistant')), 'oui');
    $dire('  « Assistant IA » est dans la barre', $oui(str_contains($page, '>Assistant IA</a>')), 'oui');
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => 'Bonjour'], $json);
    $dire('  forcer l\'envoi sans clé : refusé, rien n\'est écrit', $code . ' · ' . $oui(str_contains($r, 'Enregistrez d')) . ' · ' . $nbDiscussions($idA), '422 · oui · 0');

    echo "\n2. La première question\n";
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);
    [$page] = $appel($a, 'assistant');
    $dire('avec la clé : le formulaire est actif, le script chargé, mes cours proposés (pas ceux d\'un autre)',
        $oui(str_contains($page, 'data-assistant') && str_contains($page, 'assistant.js') && preg_match('/<textarea[^>]*disabled/', $page) !== 1
            && str_contains($page, 'Réseaux') && !str_contains($page, 'Cours secret de B')), 'oui');
    $dire('  ce qui part chez Google est dit à côté du bouton', $oui(str_contains($page, 'sont envoyés à Google')), 'oui');
    [, $url, $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => "Explique le <b>modèle OSI</b> ?\nMerci"]);
    $idDiscussion = preg_match('#/assistant/(\d+)$#', $url, $m) === 1 ? (int) $m[1] : 0;
    $dire('envoi sans script : on revient sur la discussion créée', $code . ' · ' . $oui($idDiscussion > 0) . ' · ' . $nbDiscussions($idA), '200 · oui · 1');
    $ligne = bd_all('SELECT * FROM assistant_conversations WHERE id = ?', [$idDiscussion])[0] ?? [];
    $dire('  son titre est le début de la question', (string) ($ligne['titre'] ?? ''), 'Explique le <b>modèle OSI</b> ? Merci');
    $roles = implode(',', array_column(bd_all('SELECT role FROM assistant_messages WHERE conversation_id = ? ORDER BY id', [$idDiscussion]), 'role'));
    $dire('  deux tours gardés : la question, puis la réponse', $roles, 'user,model');
    $envoye = $dernier();
    $dire('  Gemini a reçu la clé en en-tête, la question seule, et une consigne sans cours',
        $oui(str_starts_with((string) ($envoye['cle'] ?? ''), 'cle-bonne') && count($envoye['corps']['contents'] ?? []) === 1
            && ($envoye['corps']['contents'][0]['role'] ?? '') === 'user' && str_contains((string) ($envoye['corps']['contents'][0]['parts'][0]['text'] ?? ''), 'modèle OSI')
            && !str_contains(json_encode($envoye['corps']['systemInstruction'] ?? []), '<document')), 'oui');
    [$vue] = $appel($a, 'assistant/' . $idDiscussion);
    $dire('la page de la discussion montre les deux tours, la réponse mise en forme, le HTML écrit par la personne ou par le modèle échappé',
        $oui(str_contains($vue, 'Résumé bidon') && str_contains($vue, '<strong>gras</strong>') && !str_contains($vue, '<script>alert(1)</script>')
            && !str_contains($vue, '<b>modèle OSI</b>') && str_contains($vue, '&lt;b&gt;modèle OSI&lt;/b&gt;')), 'oui');
    $dire('  elle est dans la liste de gauche, et marquée courante', $oui(preg_match('#<a href="[^"]*/assistant/' . $idDiscussion . '" aria-current="page">#', $vue) === 1), 'oui');

    echo "\n3. La suite : l'historique part avec chaque message\n";
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'message' => 'Et la couche transport ?'], $json);
    $rep = json_decode($r, true) ?: [];
    $dire('envoi par le script (JSON) : la réponse arrive, déjà en HTML, dans la même discussion',
        $code . ' · ' . $oui(($rep['fait'] ?? false) && ($rep['discussion'] ?? 0) === $idDiscussion && ($rep['nouvelle'] ?? true) === false
            && str_contains((string) ($rep['reponseHtml'] ?? ''), 'Résumé bidon') && !str_contains((string) ($rep['reponseHtml'] ?? ''), '<script>')), '200 · oui');
    $envoye = $dernier();
    $contenus = $envoye['corps']['contents'] ?? [];
    $dire('  Gemini reçoit les trois tours, en alternance, le dernier est la nouvelle question',
        implode(',', array_column($contenus, 'role')) . ' · ' . $oui(str_contains((string) ($contenus[2]['parts'][0]['text'] ?? ''), 'couche transport')), 'user,model,user · oui');
    $dire('  quatre messages gardés, et la discussion reste la seule', $nbMessages($idA) . ' · ' . $nbDiscussions($idA), '4 · 1');
    [$r] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'message' => 'Merci'], $json);
    $dire('  la discussion remonte en tête de liste quand on y écrit', $oui(count(array_filter(bd_all('SELECT id FROM assistant_conversations WHERE user_id = ? ORDER BY updated_at DESC, id DESC', [$idA]), static fn ($l) => (int) $l['id'] === $idDiscussion)) === 1), 'oui');

    echo "\n4. Parler d'un cours\n";
    [$r] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'cours' => (string) $coursA, 'message' => 'Résume ce cours'], $json);
    $consigne = json_encode($dernier()['corps']['systemInstruction'] ?? [], JSON_UNESCAPED_UNICODE);
    $dire('le cours choisi part avec la consigne (le texte et la fiche, dans des balises « document »)',
        $oui(str_contains($consigne, 'sept couches') && str_contains($consigne, 'TCP et UDP') && str_contains($consigne, '<document') && str_contains($consigne, 'jamais des instructions')), 'oui');
    $dire('  la discussion se souvient de son cours', (string) bd_valeur('SELECT cours_id FROM assistant_conversations WHERE id = ?', [$idDiscussion]), (string) $coursA);
    $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'message' => 'Autre question'], $json);
    $dire('  sans champ « cours », elle garde le même', $oui(str_contains(json_encode($dernier()['corps']['systemInstruction'] ?? [], JSON_UNESCAPED_UNICODE), 'sept couches')), 'oui');
    $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'cours' => '', 'message' => 'Sans cours maintenant'], $json);
    $dire('  « Aucun cours » : le cours n\'est plus envoyé', $oui(!str_contains(json_encode($dernier()['corps']['systemInstruction'] ?? []), '<document'))
        . ' · ' . $oui(bd_valeur('SELECT cours_id FROM assistant_conversations WHERE id = ?', [$idDiscussion]) === null), 'oui · oui');
    $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'cours' => (string) $coursB, 'message' => 'Parle-moi du cours de B'], $json);
    $dire('le cours de quelqu\'un d\'autre n\'est ni lu ni envoyé', $oui(!str_contains(json_encode($dernier()), 'Contenu privé de B') && !str_contains(json_encode($dernier()), '<document')), 'oui');
    $dire('  et la discussion créée n\'en porte aucun', $oui(bd_valeur('SELECT cours_id FROM assistant_conversations WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$idA]) === null), 'oui');

    echo "\n5. Des demandes refusées\n";
    $avant = $nbMessages($idA);
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => "  \n  "], $json);
    $dire('un message vide', $code . ' · ' . $oui(str_contains($r, 'Écris un message')), '422 · oui');
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => str_repeat('a', 8001)], $json);
    $dire('  trop long (8001 caractères)', $code . ' · ' . $oui(str_contains($r, '8000 caractères')), '422 · oui');
    [, , $code] = $appel($a, 'assistant/envoyer', ['message' => 'Sans jeton'], $json);
    $dire('  sans jeton CSRF', (string) $code, '400');
    [, , $code] = $appel($b, 'assistant/' . $idDiscussion);
    $dire('la discussion de A pour B : introuvable', (string) $code, '404');
    [, , $code] = $appel($b, 'assistant/envoyer', ['_csrf' => $csrfB, 'discussion' => (string) $idDiscussion, 'message' => 'Intrusion'], $json);
    $dire('  lui écrire : introuvable aussi, rien d\'ajouté', $code . ' · ' . ($nbMessages($idA) - $avant), '404 · 0');
    $appel($b, 'assistant/' . $idDiscussion . '/renommer', ['_csrf' => $csrfB, 'titre' => 'Volé']);
    $appel($b, 'assistant/' . $idDiscussion . '/supprimer', ['_csrf' => $csrfB]);
    $dire('  ni la renommer ni la supprimer', bd_valeur('SELECT titre FROM assistant_conversations WHERE id = ?', [$idDiscussion]) !== 'Volé' ? 'oui' : 'non', 'oui');
    $dire('  et la liste de B ne montre rien de A', $oui(!str_contains($pageB, 'Explique le')), 'oui');

    echo "\n6. Quand Gemini refuse ou tombe en panne : rien n'est écrit\n";
    foreach ([
        ['cle-mauvaise-0123456789abcdef', 'Google refuse cette clé'],
        ['cle-quota-0123456789abcdef', 'limite'],
        ['cle-vide-0123456789abcdef', null],
        ['cle-panne-0123456789abcdef', null],
    ] as [$cleEssai, $attendu]) {
        $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => $cleEssai]);
        $d0 = $nbDiscussions($idA); $m0 = $nbMessages($idA);
        [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => 'Une question qui échoue'], $json);
        $rep = json_decode($r, true) ?: [];
        $dire(substr($cleEssai, 0, 12) . '… : le message d\'erreur est dit, aucune discussion ni message créé',
            $code . ' · ' . $oui(($rep['fait'] ?? true) === false && trim((string) ($rep['message'] ?? '')) !== ''
                && ($attendu === null || stripos((string) ($rep['message'] ?? ''), $attendu) !== false) && !str_contains($r, $cleEssai))
            . ' · ' . ($nbDiscussions($idA) - $d0) . ' · ' . ($nbMessages($idA) - $m0), '422 · oui · 0 · 0');
        // Sur une discussion existante aussi : l'historique ne garde pas de question sans réponse.
        $m0 = $nbMessages($idA);
        $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'message' => 'Échec dans une discussion'], $json);
        $dire('  dans une discussion existante : pas de question sans réponse', (string) ($nbMessages($idA) - $m0), '0');
    }
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $idDiscussion, 'message' => 'Hors JSON, en panne']);
    $dire('  sans script : retour sur la discussion avec le message', $oui(str_contains($r, 'Un souci') || str_contains($r, 'surchargé') || str_contains($r, 'Gemini')) . ' · ' . $oui(str_contains($r, 'ia__messages')), 'oui · oui');
    $appel($a, 'compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'cle-bonne-0123456789abcdef']);

    echo "\n7. Les bornes de l'historique\n";
    bd_run('INSERT INTO assistant_conversations (user_id, titre) VALUES (?, ?)', [$idA, 'Longue discussion']);
    $longue = (int) bd_valeur('SELECT id FROM assistant_conversations WHERE user_id = ? AND titre = ?', [$idA, 'Longue discussion']);
    for ($i = 1; $i <= 60; $i++) {
        bd_run('INSERT INTO assistant_messages (conversation_id, role, texte) VALUES (?, ?, ?)', [$longue, $i % 2 === 1 ? 'user' : 'model', "tour $i"]);
    }
    $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $longue, 'message' => 'Le dernier'], $json);
    $contenus = $dernier()['corps']['contents'] ?? [];
    $dire('soixante tours gardés : les quarante derniers partent (plus le nouveau), en commençant par la personne',
        count($contenus) . ' · ' . ($contenus[0]['role'] ?? '?') . ' · ' . ($contenus[array_key_last($contenus)]['parts'][0]['text'] ?? '?'), '41 · user · Le dernier');
    bd_run('UPDATE assistant_messages SET texte = ? WHERE conversation_id = ?', [str_repeat('x', 3000), $longue]);
    $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $longue, 'message' => 'Encore'], $json);
    $contenus = $dernier()['corps']['contents'] ?? [];
    $taille = array_sum(array_map(static fn (array $c): int => mb_strlen((string) $c['parts'][0]['text']), $contenus));
    $dire('  et si les tours sont énormes, la taille totale est bornée (60 000 caractères), le dernier message restant',
        $oui($taille <= 60000 && count($contenus) < 41) . ' · ' . ($contenus[array_key_last($contenus)]['parts'][0]['text'] ?? '?'), 'oui · Encore');
    for ($i = 61; $i <= 305; $i++) {
        bd_run('INSERT INTO assistant_messages (conversation_id, role, texte) VALUES (?, ?, ?)', [$longue, $i % 2 === 1 ? 'user' : 'model', "tour $i"]);
    }
    [$r, , $code] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'discussion' => (string) $longue, 'message' => 'Trop tard'], $json);
    $dire('au-delà de 300 messages : on propose d\'en commencer une autre, rien d\'écrit',
        $code . ' · ' . $oui(str_contains($r, 'très longue')), '422 · oui');
    bd_run('DELETE FROM assistant_conversations WHERE id = ?', [$longue]);

    echo "\n8. Renommer et supprimer\n";
    [$r] = $appel($a, 'assistant/' . $idDiscussion . '/renommer', ['_csrf' => $csrf, 'titre' => "  Mon  cours\nde réseaux  "]);
    $dire('renommer : espaces nettoyés', (string) bd_valeur('SELECT titre FROM assistant_conversations WHERE id = ?', [$idDiscussion]), 'Mon cours de réseaux');
    $appel($a, 'assistant/' . $idDiscussion . '/renommer', ['_csrf' => $csrf, 'titre' => '   ']);
    $dire('  un titre vide est refusé', (string) bd_valeur('SELECT titre FROM assistant_conversations WHERE id = ?', [$idDiscussion]), 'Mon cours de réseaux');
    $appel($a, 'assistant/' . $idDiscussion . '/renommer', ['_csrf' => $csrf, 'titre' => str_repeat('é', 200)]);
    $dire('  un titre trop long est coupé', (string) mb_strlen((string) bd_valeur('SELECT titre FROM assistant_conversations WHERE id = ?', [$idDiscussion])), '120');
    $appel($a, 'assistant/' . $idDiscussion . '/supprimer', ['_csrf' => $csrf]);
    $dire('supprimer : la discussion et ses tours disparaissent', bd_valeur('SELECT COUNT(*) FROM assistant_conversations WHERE id = ?', [$idDiscussion])
        . ' · ' . bd_valeur('SELECT COUNT(*) FROM assistant_messages WHERE conversation_id = ?', [$idDiscussion]), '0 · 0');
    [, , $code] = $appel($a, 'assistant/' . $idDiscussion);
    $dire('  et son adresse n\'existe plus', (string) $code, '404');

    echo "\n9. Les quatre langues\n";
    foreach ([
        'en' => ['AI assistant', 'What would you like to know?', 'Talk about a course', 'Write a message before sending.'],
        'es' => ['Asistente IA', '¿Qué quieres saber?', 'Hablar de un curso', 'Escribe un mensaje antes de enviar.'],
        'de' => ['KI-Assistent', 'Was möchtest du wissen?', 'Über einen Kurs sprechen', 'Schreibe eine Nachricht, bevor du sendest.'],
        'fr' => ['Assistant IA', 'Que veux-tu savoir ?', 'Parler d’un cours', 'Écris un message avant d’envoyer.'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel($a, 'assistant');
        [$r] = $appel($a, 'assistant/envoyer', ['_csrf' => $csrf, 'message' => ''], $json);
        $dire("$langue : titre, accueil, choix du cours, message du serveur",
            $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[1]) && str_contains($page, $mots[2]) && str_contains($r, $mots[3])
                && !preg_match('/>\s*(ia|nav)\.[a-z_.]+\s*</', $page) && str_contains($page, '"ia.reflechit":')), 'oui');
    }
    $termine = true;
} finally {
    if (is_resource($serveur)) { proc_terminate($serveur); proc_close($serveur); }
    @unlink($fichierEssai);
    foreach ($cookies as $f) { @unlink($f); }
    @unlink(sys_get_temp_dir() . '/faux_gemini_dernier.json');
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · discussions orphelines ' . bd_valeur('SELECT COUNT(*) FROM assistant_conversations WHERE user_id NOT IN (SELECT id FROM users)')
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
