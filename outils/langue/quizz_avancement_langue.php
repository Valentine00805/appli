<?php
/**
 * L'avancement d'un quizz (HTTP + base) : une page web (.html) rangée dans la fiche d'un cours ou d'un dossier signale où l'on en est (questions faites,
 * bonnes réponses, total de la série) ; l'application en tire deux pourcentages — l'avancement et le score — et garde le dernier état. Le score rejoint
 * l'anneau de la fiche et celui de sa carte dans « Révision ». Validation des nombres, chacun les siens (404), pas de jeton (400), un site dont la
 * migration n'est pas passée, et les quatre langues.
 *
 * Le script de la page (le cadre qui envoie un « postMessage ») ne se teste pas ici : il se voit dans le navigateur.
 *
 * Deux comptes d'essai : A (le propriétaire), B (un autre compte).
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');

$anomalies = 0;
$termine = false;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['qza-a@exemple-test.fr', 'qza-b@exemple-test.fr'];
$racine = dirname(__DIR__, 2);
$nettoyer = static function () use ($emails, $racine): void {
    foreach ($emails as $e) {
        $u = bd_valeur('SELECT id FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
        if ($u === null || $u === false) { continue; }
        foreach (bd_all('SELECT nom_stocke FROM fichiers WHERE user_id = ?', [$u]) as $f) { @unlink($racine . '/storage/uploads/' . basename((string) $f['nom_stocke'])); }
        bd_run('DELETE FROM users WHERE id = ?', [$u]);
    }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, 'Qza_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai quizz']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)', [$idA, 'Cours de UML', 'Des diagrammes.']);
$cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
bd_run('INSERT INTO dossiers (user_id, nom, icone) VALUES (?, ?, ?)', [$idA, 'UML', '📐']);
$dossier = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$idA]);

$cookies = [$emails[0] => __DIR__ . '/ck_qza_a.txt', $emails[1] => __DIR__ . '/ck_qza_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
$temporaires = [];
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post); }
    $r = [(string) curl_exec($h), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
/** Dépose un fichier (nom, contenu) dans la fiche : adresse du dépôt. */
$deposer = static function (string $compte, string $chemin, string $nom, string $contenu, string $csrf) use ($cookies, &$temporaires): int {
    usleep(250000);
    $temp = tempnam(sys_get_temp_dir(), 'qza');
    $temporaires[] = $temp;
    file_put_contents($temp, $contenu);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte],
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['_csrf' => $csrf, 'fichiers[]' => new CURLFile($temp, 'text/html', $nom)]]);
    curl_exec($h);
    $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    unset($h);
    return $code;
};
$ligne = static fn (string $nom, int $u): ?array => bd_all('SELECT * FROM fichiers WHERE user_id = ? AND nom_origine = ?', [$u, $nom])[0] ?? null;
$etat = static fn (int $id): string => implode('/', array_map('strval', array_values(bd_all('SELECT quizz_fait, quizz_bonnes, quizz_total FROM fichiers WHERE id = ?', [$id])[0] ?? [])));
$renomme = false;

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    $csrfA = $jeton($appel($a, 'cours')[0]);
    $csrfB = $jeton($appel($b, 'cours')[0]);

    $quizz = '<!doctype html><html><body><h1>QCM UML</h1><script>/* le quizz */</script></body></html>';
    $deposer($a, 'cours/' . $cours . '/revision/fichiers', 'QCM UML.html', $quizz, $csrfA);
    $deposer($a, 'cours/' . $cours . '/revision/fichiers', 'notes.txt', 'des notes', $csrfA);
    $qc = $ligne('QCM UML.html', $idA);
    $notes = $ligne('notes.txt', $idA);
    $idQ = (int) ($qc['id'] ?? 0);
    $idN = (int) ($notes['id'] ?? 0);
    $deposer($a, 'dossiers/' . $dossier . '/revision/fichiers', 'QCM dossier.html', $quizz, $csrfA);
    $idD = (int) ($ligne('QCM dossier.html', $idA)['id'] ?? 0);
    $pj = $deposer($a, 'cours/' . $cours . '/fichiers', 'piece-jointe.html', $quizz, $csrfA);
    $idPj = (int) ($ligne('piece-jointe.html', $idA)['id'] ?? 0);
    $dire('mise en place : deux quizz (un dans la fiche du cours, un dans celle du dossier), un .txt, une pièce jointe .html du cours (hors fiche)',
        $oui($idQ > 0 && $idN > 0 && $idD > 0 && $idPj > 0 && (int) $qc['pour_fiche'] === 1) . ' · ' . $etat($idQ), 'oui · 0/0/0');

    echo "\n1. Avant le premier signal\n";
    [$fiche] = $appel($a, 'revision/' . $cours . '?fenetre=1');
    $dire('la fiche montre les deux mesures du quizz, vides : « Avancement » (pas encore joué) et « Score »',
        $oui(str_contains($fiche, 'data-quizz-mesures="' . $idQ . '"') && str_contains($fiche, 'data-quizz-url="/mon_appli/appli/fichiers/' . $idQ . '/quizz"')
            && str_contains($fiche, '<strong>Avancement</strong>') && str_contains($fiche, '<strong>Score</strong>') && str_contains($fiche, 'pas encore joué')
            && substr_count($fiche, 'anneau--inconnu') >= 2 && !str_contains($fiche, 'data-avancement="quizz-' . $idQ . '"')), 'oui');
    $dire('et de quoi le faire signaler : les modèles de texte pour le script, le code à coller (« postMessage ») et son explication',
        $oui(str_contains($fiche, 'data-modele-fait="Questions faites : {fait} / {total}"') && str_contains($fiche, 'data-modele-bonnes="Bonnes réponses : {bonnes}"')
            && str_contains($fiche, '<pre class="quizz__code"') && str_contains($fiche, 'parent.postMessage({ quizz: true, fait: fait, bonnes: bonnes, total: total }, "*")')
            && str_contains($fiche, 'Faire signaler son avancement par le quizz')), 'oui');
    $dire('le .txt n\'a pas de mesure de quizz', $oui(substr_count($fiche, 'data-quizz-mesures=') === 1), 'oui');

    echo "\n2. Le quizz signale où il en est\n";
    [, $c1] = $appel($a, 'fichiers/' . $idQ . '/quizz', ['_csrf' => $csrfA, 'fait' => 15, 'bonnes' => 11, 'total' => 30]);
    $dire('15 questions faites, 11 bonnes, sur 30 : gardé (204)', $c1 . ' · ' . $etat($idQ), '204 · 15/11/30');
    [$fiche2] = $appel($a, 'revision/' . $cours . '?fenetre=1');
    $dire('la fiche montre 50 % d\'avancement et 37 % de score (11/30), avec « Questions faites : 15 / 30 » et « Bonnes réponses : 11 »',
        $oui(preg_match('~data-quizz-avancement>.*?>50<span class="anneau__pourcent">%</span>.*?Questions faites : 15 / 30~s', $fiche2) === 1
            && preg_match('~data-quizz-score data-avancement="quizz-' . $idQ . '">.*?>37<span class="anneau__pourcent">%</span>.*?Bonnes réponses : 11~s', $fiche2) === 1), 'oui');
    $dire('le score rejoint l\'anneau de toute la fiche (37 %, un seul élément suivi)',
        $oui(preg_match('~data-total-fiche>.*?>37<span class="anneau__pourcent">%</span>~s', $fiche2) === 1), 'oui');
    [$revision] = $appel($a, 'revision');
    $dire('et celui de la carte de la fiche dans « Révision » (37 %, « 1 élément »)',
        $oui(preg_match('~href="[^"]*/revision/' . $cours . '" data-fenetre>.*?fiche-carte__ecoute.*?>37<span class="anneau__pourcent">%</span>.*?1 élément~s', $revision) === 1), 'oui');
    $appel($a, 'fichiers/' . $idQ . '/quizz', ['_csrf' => $csrfA, 'fait' => 30, 'bonnes' => 27, 'total' => 30]);
    $dire('un nouveau signal remplace le précédent (dernier état gardé) : 30 / 27 / 30', $etat($idQ), '30/27/30');
    [$fiche3] = $appel($a, 'revision/' . $cours . '?fenetre=1');
    $dire('100 % d\'avancement, 90 % de score', $oui(preg_match('~data-quizz-avancement>.*?>100<span~s', $fiche3) === 1 && preg_match('~data-quizz-score data-avancement=.*?>90<span~s', $fiche3) === 1), 'oui');

    echo "\n3. Des nombres sans sens ne changent rien\n";
    $avant = $etat($idQ);
    $refus = [];
    foreach ([
        'plus de bonnes que de faites' => ['fait' => 5, 'bonnes' => 9, 'total' => 30],
        'plus de faites que de questions' => ['fait' => 31, 'bonnes' => 5, 'total' => 30],
        'total nul' => ['fait' => 0, 'bonnes' => 0, 'total' => 0],
        'total énorme' => ['fait' => 1, 'bonnes' => 1, 'total' => 5000000],
        'négatif' => ['fait' => -1, 'bonnes' => 0, 'total' => 10],
        'du texte' => ['fait' => 'abc', 'bonnes' => 0, 'total' => 10],
        'un décimal' => ['fait' => '1.5', 'bonnes' => 1, 'total' => 10],
        'des champs manquants' => ['fait' => 3],
    ] as $quoi => $nombres) {
        [, $code] = $appel($a, 'fichiers/' . $idQ . '/quizz', ['_csrf' => $csrfA] + $nombres);
        $refus[] = $code;
    }
    $dire('huit envois absurdes : tous refusés (422), l\'état gardé ne bouge pas', implode(',', $refus) . ' · ' . $oui($etat($idQ) === $avant), '422,422,422,422,422,422,422,422 · oui');
    [, $cN] = $appel($a, 'fichiers/' . $idN . '/quizz', ['_csrf' => $csrfA, 'fait' => 1, 'bonnes' => 1, 'total' => 2]);
    [, $cPj] = $appel($a, 'fichiers/' . $idPj . '/quizz', ['_csrf' => $csrfA, 'fait' => 1, 'bonnes' => 1, 'total' => 2]);
    $dire('un .txt de la fiche, ou une pièce jointe .html du cours hors fiche : refusés (422), sans rien écrire',
        $cN . ' · ' . $cPj . ' · ' . $etat($idN) . ' · ' . $etat($idPj), '422 · 422 · 0/0/0 · 0/0/0');

    echo "\n4. Chacun les siens\n";
    [, $cB] = $appel($b, 'fichiers/' . $idQ . '/quizz', ['_csrf' => $csrfB, 'fait' => 1, 'bonnes' => 1, 'total' => 2]);
    [, $cSans] = $appel($a, 'fichiers/' . $idQ . '/quizz', ['fait' => 1, 'bonnes' => 1, 'total' => 2]);
    [, $cAbsent] = $appel($a, 'fichiers/999999999/quizz', ['_csrf' => $csrfA, 'fait' => 1, 'bonnes' => 1, 'total' => 2]);
    $dire('B n\'écrit pas sur le quizz de A (404) ; sans jeton CSRF : 400 ; un fichier absent : 404 ; l\'état de A ne bouge pas',
        $cB . ' · ' . $cSans . ' · ' . $cAbsent . ' · ' . $oui($etat($idQ) === $avant), '404 · 400 · 404 · oui');

    echo "\n5. Dans la fiche d'un dossier\n";
    [$dos] = $appel($a, 'dossiers/' . $dossier . '/revision?fenetre=1');
    $dire('la fiche du dossier montre aussi les mesures du quizz (même rubrique)', $oui(str_contains($dos, 'data-quizz-mesures="' . $idD . '"') && str_contains($dos, '🎯 Quizz')), 'oui');
    [, $cD] = $appel($a, 'fichiers/' . $idD . '/quizz', ['_csrf' => $csrfA, 'fait' => 4, 'bonnes' => 2, 'total' => 8]);
    $dire('il signale 4 faites, 2 bonnes, sur 8 : gardé', $cD . ' · ' . $etat($idD), '204 · 4/2/8');
    [$dos2] = $appel($a, 'dossiers/' . $dossier . '/revision?fenetre=1');
    $dire('la page du dossier : 50 % d\'avancement, 25 % de score, et l\'anneau de toute la fiche à 25 %',
        $oui(preg_match('~data-quizz-avancement>.*?>50<span~s', $dos2) === 1 && preg_match('~data-quizz-score data-avancement=.*?>25<span~s', $dos2) === 1 && preg_match('~data-total-fiche>.*?>25<span~s', $dos2) === 1), 'oui');
    [$rev2] = $appel($a, 'revision');
    $dire('et la carte du dossier dans « Révision » porte le même anneau (25 %)',
        $oui(preg_match('~href="[^"]*/dossiers/' . $dossier . '/revision" data-fenetre>.*?fiche-carte__ecoute.*?>25<span class="anneau__pourcent">%</span>~s', $rev2) === 1), 'oui');

    echo "\n6. Un site dont la migration n'est pas passée\n";
    bd_run('ALTER TABLE fichiers CHANGE quizz_total quizz_total_essai INT UNSIGNED NOT NULL DEFAULT 0');
    $renomme = true;
    [$sans, $codeSans] = $appel($a, 'revision/' . $cours . '?fenetre=1');
    [$sansRev, $codeSansRev] = $appel($a, 'revision');
    [, $codeSansPost] = $appel($a, 'fichiers/' . $idQ . '/quizz', ['_csrf' => $csrfA, 'fait' => 1, 'bonnes' => 1, 'total' => 2]);
    $dire('la fiche s\'ouvre (200) avec le quizz mais sans mesures ni code à coller ; la liste « Révision » aussi ; l\'envoi répond 404',
        $codeSans . ' · ' . $oui(str_contains($sans, 'quizz__cadre') && !str_contains($sans, 'data-quizz-mesures') && !str_contains($sans, 'quizz__code') && !str_contains($sans, 'Fatal error')) . ' · ' . $codeSansRev . ' · ' . $oui(!str_contains($sansRev, 'Fatal error') && !str_contains($sansRev, 'Warning:')) . ' · ' . $codeSansPost,
        '200 · oui · 200 · oui · 404');
    bd_run('ALTER TABLE fichiers CHANGE quizz_total_essai quizz_total INT UNSIGNED NOT NULL DEFAULT 0');
    $renomme = false;

    echo "\n7. Les quatre langues\n";
    [$pCompte] = $appel($a, 'compte');
    $tokenCompte = $jeton($pCompte);
    foreach ([
        'en' => ['Progress', 'Score', 'Questions answered: 30 / 30', 'Correct answers: 27', 'Have the quiz report its progress'],
        'es' => ['Avance', 'Puntuación', 'Preguntas respondidas: 30 / 30', 'Respuestas correctas: 27', 'Hacer que el cuestionario indique su avance'],
        'de' => ['Fortschritt', 'Ergebnis', 'Beantwortete Fragen: 30 / 30', 'Richtige Antworten: 27', 'Das Quiz seinen Fortschritt melden lassen'],
        'fr' => ['Avancement', 'Score', 'Questions faites : 30 / 30', 'Bonnes réponses : 27', 'Faire signaler son avancement par le quizz'],
    ] as $langue => $mots) {
        $appel($a, 'compte/langue', ['_csrf' => $tokenCompte, 'langue' => $langue]);
        [$page] = $appel($a, 'revision/' . $cours . '?fenetre=1');
        $manque = array_values(array_filter($mots, static fn (string $mot): bool => !str_contains(html_entity_decode($page, ENT_QUOTES, 'UTF-8'), $mot)));
        $dire($langue . ' : avancement, score, les deux textes, le titre de l\'aide', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : pas de clé brute', $oui(!preg_match('/\bfiche\.quizz[a-z_.]*/', $page)), 'oui');
    }
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents($racine . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('ALTER TABLE fichiers CHANGE quizz_total_essai quizz_total INT UNSIGNED NOT NULL DEFAULT 0'); } catch (Throwable) {}
    }
    $nettoyer();
    foreach ($temporaires as $f) { @unlink($f); }
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'qza-_@exemple-test.fr'")
        . ' · colonne présente : ' . (bd_valeur("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'fichiers' AND column_name = 'quizz_total'") ? 'oui' : 'NON') . "\n";
}
