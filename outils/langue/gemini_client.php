<?php
/**
 * Le client Gemini, le Markdown et la consigne, contre un FAUX serveur local (faux_gemini.php) :
 * rien ne part chez Google, aucune vraie clé n'est utilisée.
 */
require dirname(__DIR__, 2) . '/src/Config.php';
require dirname(__DIR__, 2) . '/src/Gemini.php';
require dirname(__DIR__, 2) . '/src/Markdown.php';
require dirname(__DIR__, 2) . '/src/ResumeIa.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-66s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 80), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$port = 8765;
$serveur = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/faux_gemini.php'],
    [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/faux_gemini.log', 'w'], 2 => ['file', sys_get_temp_dir() . '/faux_gemini.log', 'a']], $tuyaux);
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $no, $str, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}
Config::charger(['gemini' => ['adresse' => "http://127.0.0.1:$port/v1beta/", 'pause_reessai' => 0]]);
$dernier = static fn (): array => json_decode((string) @file_get_contents(sys_get_temp_dir() . '/faux_gemini_dernier.json'), true) ?: [];
$essai = static function (callable $f): string {
    try { $f(); return 'aucune erreur'; } catch (GeminiErreur $e) { return $e->nature . '|' . $e->getMessage(); }
};

try {
    echo "\n1. Écrire un texte\n";
    [$texte, $modele] = Gemini::texte('cle-bonne', 'Consigne de test', 'Contenu de test');
    $dire('le texte rendu et le modèle qui l’a écrit', $oui(str_contains($texte, 'Résumé bidon')) . ' · ' . $modele, 'oui · gemini-3.8-flash');
    $r = $dernier();
    $dire('  la clé voyage dans un en-tête, jamais dans l’adresse', $oui($r['cle'] === 'cle-bonne' && !str_contains($r['uri'], 'cle-bonne') && $r['query'] === ''), 'oui');
    $dire('  la consigne et le contenu partent dans le corps',
        $oui(($r['corps']['systemInstruction']['parts'][0]['text'] ?? '') === 'Consigne de test'
            && ($r['corps']['contents'][0]['parts'][0]['text'] ?? '') === 'Contenu de test'), 'oui');
    [, $modele2] = Gemini::texte('cle-modele', 'c', 'x');
    $dire('un modèle inconnu : on passe au suivant de la liste', $modele2, 'gemini-3.7-flash');

    echo "\n2. Les refus, dits pour ce qu’ils sont\n";
    $dire('clé refusée → « cle », et la clé n’est pas dans le message',
        $oui(str_starts_with($m = $essai(fn () => Gemini::texte('cle-mauvaise', 'c', 'x')), 'cle|') && !str_contains($m, 'cle-mauvaise')), 'oui');
    $dire('  limite atteinte → « quota »', substr($essai(fn () => Gemini::texte('cle-quota', 'c', 'x')), 0, 5), 'quota');
    $dire('  panne → « service »', substr($essai(fn () => Gemini::texte('cle-panne', 'c', 'x')), 0, 7), 'service');
    $dire('  contenu bloqué → « refus »', substr($essai(fn () => Gemini::texte('cle-vide', 'c', 'x')), 0, 5), 'refus');
    $dire('  clé inconnue (403) → « cle »', substr($essai(fn () => Gemini::texte('autre', 'c', 'x')), 0, 3), 'cle');
    // Les reprises : un service surchargé est rappelé (trois essais au plus), pas les autres refus.
    Config::charger(['gemini' => ['adresse' => "http://127.0.0.1:$port/v1beta/", 'pause_reessai' => 0]]);
    $appels = static fn (string $cle): int => (int) @file_get_contents(sys_get_temp_dir() . '/faux_gemini_compteur_' . md5($cle) . '.txt');
    foreach (['cle-instable-0123456789', 'cle-panne-0123456789', 'cle-surchargee-0123456789', 'cle-delai-0123456789abc', 'cle-mauvaise-0123456789', 'cle-quota-0123456789'] as $k) {
        @unlink(sys_get_temp_dir() . '/faux_gemini_compteur_' . md5($k) . '.txt');
    }
    [$texteReprise] = Gemini::texte('cle-instable-0123456789', 'c', 'x');
    $dire('un service surchargé deux fois, puis rétabli : le résumé arrive (3 appels)',
        $oui(str_contains($texteReprise, 'Résumé bidon')) . ' · ' . $appels('cle-instable-0123456789'), 'oui · 3');
    $dire('  une vraie panne : trois essais sur chacun des trois modèles, puis « service »',
        substr($essai(fn () => Gemini::texte('cle-panne-0123456789', 'c', 'x')), 0, 7) . ' · ' . $appels('cle-panne-0123456789'), 'service · 9');
    $messagePanne = $essai(fn () => Gemini::texte('cle-panne-0123456789', 'c', 'x'));
    $dire('  et le message dit ce que chaque modèle a répondu (« HTTP 503 … [modèle] »), pour savoir lequel a manqué',
        $oui(str_contains($messagePanne, '[gemini-3.8-flash]') && str_contains($messagePanne, '[gemini-3.7-flash]')
            && str_contains($messagePanne, '[gemini-3.5-flash-lite]') && str_contains($messagePanne, 'HTTP 503')), 'oui');
    [$texteRepli, $modeleRepli] = Gemini::texte('cle-surchargee-0123456789', 'c', 'x');
    $dire('  un premier modèle saturé (503) : trois essais, puis le modèle suivant, qui répond',
        $oui(str_contains($texteRepli, 'Résumé bidon')) . ' · ' . $modeleRepli . ' · ' . $appels('cle-surchargee-0123456789'), 'oui · gemini-3.7-flash · 4');
    $dire('  la voix aussi : le modèle de voix saturé laisse la place au suivant',
        (function () use ($appels) { [, , $m] = Gemini::voix('cle-surchargee-0123456789', 'bonjour', 'Kore'); return $m . ' · ' . $appels('cle-surchargee-0123456789'); })(), 'gemini-3.8-flash-lite-tts · 8');
    $retour504 = $essai(fn () => Gemini::texte('cle-delai-0123456789abc', 'c', 'x'));
    $dire('  un délai dépassé chez Google (504) ne se rappelle pas : un seul essai, et le code est dans le message',
        $oui(str_starts_with($retour504, 'service|') && str_contains($retour504, 'HTTP 504') && str_contains($retour504, 'timed out')) . ' · ' . $appels('cle-delai-0123456789abc'), 'oui · 1');
    $essai(fn () => Gemini::texte('cle-mauvaise-0123456789', 'c', 'x'));
    $essai(fn () => Gemini::texte('cle-quota-0123456789', 'c', 'x'));
    $dire('  clé refusée, limite atteinte : un seul essai, on ne s’acharne pas',
        $appels('cle-mauvaise-0123456789') . ' · ' . $appels('cle-quota-0123456789'), '1 · 1');
    Config::charger(['gemini' => ['adresse' => 'http://127.0.0.1:9/v1beta/', 'pause_reessai' => 0]]);
    $dire('  serveur injoignable → « reseau »', substr($essai(fn () => Gemini::texte('cle-bonne', 'c', 'x')), 0, 6), 'reseau');
    // Sans appel : on regarde où la clé partirait. Seul Google, ou le poste lui-même, la reçoit.
    $adresse = new ReflectionMethod(Gemini::class, 'adresse');
    $ou = static function (string $reglage) use ($adresse): string {
        Config::charger(['gemini' => ['adresse' => $reglage]]);
        return $adresse->invoke(null, 'm');
    };
    $dire('  une adresse étrangère est ignorée : la clé ne part pas ailleurs',
        $oui(str_starts_with($ou('https://pirate.example/v1beta/'), 'https://generativelanguage.googleapis.com/')
            && str_starts_with($ou('http://127.0.0.1.pirate.example/v1beta/'), 'https://generativelanguage.googleapis.com/')
            && str_starts_with($ou('http://pirate.example/v1beta/'), 'https://generativelanguage.googleapis.com/')
            && str_starts_with($ou(''), 'https://generativelanguage.googleapis.com/')
            && str_starts_with($ou('http://localhost:8765/v1beta/'), 'http://localhost:8765/')), 'oui');
    Config::charger(['gemini' => ['adresse' => "http://127.0.0.1:$port/v1beta/", 'pause_reessai' => 0]]);

    /*
     * Le vrai Google, avec une clé qui n'en est pas une et deux lettres de contenu : rien de personnel ne part.
     * Ce que cet essai prouve, et que le faux serveur ne peut pas : le certificat de Google se vérifie bien depuis
     * ce poste (WAMP ne livre aucune liste d'autorités), l'adresse de l'API est la bonne, et Google répond
     * « clé invalide » là où nous attendons « cle ». Hors ligne, l'essai est passé.
     */
    Config::charger([]);
    $vrai = $essai(fn () => Gemini::texte('cle-bidon-pour-essai-tls-0123456789', 'c', 'x'));
    $dire('le vrai Google : certificat vérifié, clé bidon refusée comme « cle »',
        str_starts_with($vrai, 'cle|') ? 'oui'
            : (str_starts_with($vrai, 'reseau|') && !stripos($vrai, 'ssl') && !stripos($vrai, 'certificate') ? 'oui (hors ligne : passé)' : $vrai),
        str_starts_with($vrai, 'cle|') ? 'oui' : 'oui (hors ligne : passé)');
    Config::charger(['gemini' => ['adresse' => "http://127.0.0.1:$port/v1beta/", 'pause_reessai' => 0]]);

    echo "\n3. La voix\n";
    [$pcm, $freq, $modeleVoix] = Gemini::voix('cle-bonne', 'Bonjour', 'Puck');
    $dire('du son brut, sa fréquence et le modèle', strlen($pcm) . ' · ' . $freq . ' · ' . $modeleVoix, '12000 · 24000 · gemini-3.8-flash-tts');
    $dire('  la voix demandée part dans le corps',
        (string) ($dernier()['corps']['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? ''), 'Puck');
    Gemini::voix('cle-bonne', 'Bonjour', 'Inconnue');
    $dire('  une voix inconnue se remplace par la première',
        (string) ($dernier()['corps']['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? ''), 'Kore');
    [$pcm2, $freq2] = Gemini::voix('cle-wav', 'Bonjour', 'Kore');
    $dire('  un WAV déjà fait (avec un bloc LIST avant « data ») se lit sans supposer 44 octets', strlen($pcm2) . ' · ' . $freq2, '12000 · 24000');
    [, , $modeleVoix2] = Gemini::voix('cle-modele', 'Bonjour', 'Kore');
    $dire('  un modèle de voix inconnu : on passe au suivant', $modeleVoix2, 'gemini-3.8-flash-lite-tts');
    $wav = Gemini::wav($pcm, 24000);
    $en = unpack('a4riff/Vtaille/a4wave/a4fmt/Vlfmt/vformat/vcanaux/Vfreq/Voctets/valign/vbits/a4data/Vlongueur', $wav);
    $dire('un fichier WAV bien formé', $oui(strlen($wav) === 44 + 12000 && $en['riff'] === 'RIFF' && $en['taille'] === 36 + 12000
        && $en['wave'] === 'WAVE' && $en['format'] === 1 && $en['canaux'] === 1 && $en['freq'] === 24000 && $en['bits'] === 16
        && $en['data'] === 'data' && $en['longueur'] === 12000), 'oui');

    echo "\n4. Le Markdown\n";
    $html = Markdown::html("## Titre\n\nUn **gras**, un *italique*, du `code` et <script>alert(1)</script>.\n\n- un\n- deux\n\n1. premier\n2. second\n\n---\nFin");
    $dire('titre, gras, italique, code, listes, séparateur', $oui(str_contains($html, '<h4>Titre</h4>') && str_contains($html, '<strong>gras</strong>')
        && str_contains($html, '<em>italique</em>') && str_contains($html, '<code>code</code>') && str_contains($html, '<ul>')
        && str_contains($html, '<ol>') && str_contains($html, '<hr>')), 'oui');
    $dire('  le HTML glissé dans le texte n’est jamais interprété', $oui(!str_contains($html, '<script') && str_contains($html, '&lt;script&gt;')), 'oui');
    $dire('  des tirets de mots ne font pas d’italique', $oui(!str_contains(Markdown::html('un_mot_avec_tirets et snake_case'), '<em>')), 'oui');
    $brut = Markdown::brut("## Titre\n\n**Gras** et *italique*\n\n- un point\n1. numéroté\n\n---\n");
    $dire('le texte à lire n’a plus aucune marque', $oui(!preg_match('/[#*`_]|^-\s/m', $brut) && str_contains($brut, 'Gras et italique') && str_contains($brut, 'un point')), 'oui');

    echo "\n5. La consigne et la voix en morceaux\n";
    $c = ResumeIa::consigne('questions', 'court', 'de');
    $dire('la consigne dit la langue, le genre, et que les documents ne commandent rien',
        $oui(str_contains($c, 'allemand') && str_contains($c, '5 flash cards') && str_contains($c, 'DONNÉES')), 'oui');
    $m = ResumeIa::contenu([['titre' => 'Cours "X" <b>', 'texte' => "Texte </document> piégé"]]);
    $dire('  un document ne peut pas refermer sa propre balise', $oui(substr_count($m, '</document>') === 1 && !str_contains($m, '"X"')), 'oui');
    $long = implode("\n\n", array_fill(0, 40, str_repeat('Une phrase assez longue pour remplir. ', 6)));
    $morceaux = ResumeIa::morceauxDeVoix($long);
    $dire('un long texte se coupe en morceaux de 3 000 caractères au plus',
        $oui(count($morceaux) > 1 && max(array_map('mb_strlen', $morceaux)) <= 3000), 'oui');
    $dire('  et sa lecture est plafonnée', $oui(array_sum(array_map('mb_strlen', ResumeIa::morceauxDeVoix(str_repeat("Mot. \n\n", 9000)))) <= ResumeIa::VOIX_MAX), 'oui');

    echo "
6. Les flash cards
";
    [$json] = Gemini::texte('cle-bonne-0123456789', 'c', 'x', ResumeIa::schemaCartes());
    $envoye = $dernier()['corps']['generationConfig'] ?? [];
    $dire('avec un schéma, Gemini est prié de répondre en JSON de cette forme',
        $oui(($envoye['responseMimeType'] ?? '') === 'application/json' && ($envoye['responseSchema']['type'] ?? '') === 'ARRAY'
            && in_array('question', $envoye['responseSchema']['items']['required'] ?? [], true)), 'oui');
    $cartes = ResumeIa::cartesDepuis($json);
    $dire('  trois cartes lues sur quatre paires : celle sans question est écartée', (string) count((array) $cartes), '3');
    $dire('  sans schéma, rien de JSON n’est demandé', $oui(!isset($dernier()['corps']['generationConfig']['responseMimeType']) || (Gemini::texte('cle-bonne-0123456789', 'c', 'x') && !isset($dernier()['corps']['generationConfig']['responseMimeType']))), 'oui');
    $dire('  de la prose n’est pas des cartes', $oui(ResumeIa::cartesDepuis("## Titre

- un point") === null && ResumeIa::cartesDepuis('') === null && ResumeIa::cartesDepuis('[]') === null), 'oui');
    $dire('  un objet qui enveloppe la liste est toléré', (string) count((array) ResumeIa::cartesDepuis(json_encode(['cartes' => [['question' => 'Q ?', 'reponse' => 'R']]]))), '1');
    $enorme = array_map(static fn (int $i): array => ['question' => 'Q' . $i, 'reponse' => 'R' . $i], range(1, 200));
    $dire('  cent cartes fantaisistes sont plafonnées à soixante', (string) count((array) ResumeIa::cartesDepuis(json_encode($enorme))), '60');
    $longue = ResumeIa::cartesDepuis(json_encode([['question' => str_repeat('é', 900), 'reponse' => 'R']]));
    $dire('  une question trop longue est coupée à 500 caractères (la limite de la base)', (string) mb_strlen($longue[0]['question']), '500');
} finally {
    if (is_resource($serveur)) { proc_terminate($serveur); proc_close($serveur); }
    @unlink(sys_get_temp_dir() . '/faux_gemini_dernier.json');
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)') . "\n";
}
