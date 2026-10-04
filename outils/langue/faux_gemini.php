<?php
/**
 * Un faux Gemini, pour les essais : « php -S 127.0.0.1:8765 outils/langue/faux_gemini.php ».
 *
 * Il répond comme l'API (generateContent) selon la CLÉ qu'on lui présente — aucune vraie clé, aucun
 * appel chez Google :
 *   cle-bonne   → un texte (ou un son pour un modèle « tts »)
 *   cle-wav     → comme cle-bonne, mais le son arrive déjà dans un fichier WAV
 *   cle-mauvaise → 400 « API key not valid »
 *   cle-quota   → 429 RESOURCE_EXHAUSTED
 *   cle-modele  → 404 pour le premier modèle d'une liste, une réponse pour les suivants
 *   cle-vide    → 200 sans texte (contenu bloqué)
 *   cle-panne   → 503 (toujours)
 *   cle-instable → 503 aux deux premiers appels, puis une réponse
 * Chaque requête reçue est notée dans le dossier temporaire (« faux_gemini_dernier.json »), pour que
 * l'essai vérifie ce qui a vraiment été envoyé — l'adresse, la clé et le corps.
 */
header('Content-Type: application/json');

$chemin = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$corps = (string) file_get_contents('php://input');
$cle = (string) ($_SERVER['HTTP_X_GOOG_API_KEY'] ?? '');
file_put_contents(sys_get_temp_dir() . '/faux_gemini_dernier.json', json_encode([
    'uri' => $_SERVER['REQUEST_URI'], 'cle' => $cle, 'corps' => json_decode($corps, true),
    'query' => (string) ($_SERVER['QUERY_STRING'] ?? ''),
]));

$erreur = static function (int $code, string $statut, string $message): never {
    http_response_code($code);
    echo json_encode(['error' => ['code' => $code, 'status' => $statut, 'message' => $message]]);
    exit;
};

if (!preg_match('#/models/([^:/]+):generateContent$#', (string) $chemin, $m)) {
    $erreur(404, 'NOT_FOUND', 'Chemin inconnu.');
}
$modele = $m[1];
$voix = str_contains($modele, 'tts');

// Combien de fois cette clé a déjà appelé (le compteur est dans le dossier temporaire ; les essais le remettent à zéro).
$compteur = sys_get_temp_dir() . '/faux_gemini_compteur_' . md5($cle) . '.txt';
$appels = (int) @file_get_contents($compteur) + 1;
file_put_contents($compteur, (string) $appels);

// La clé se reconnaît à son début : le champ de l'application exige 20 caractères au moins.
switch (true) {
    case str_starts_with($cle, 'cle-mauvaise'):
        $erreur(400, 'INVALID_ARGUMENT', 'API key not valid. Please pass a valid API key (' . $cle . ').');
    case str_starts_with($cle, 'cle-quota'):
        $erreur(429, 'RESOURCE_EXHAUSTED', 'You exceeded your current quota.');
    case str_starts_with($cle, 'cle-panne'):
        $erreur(503, 'UNAVAILABLE', 'The model is overloaded.');
    case str_starts_with($cle, 'cle-delai'):
        $erreur(504, 'DEADLINE_EXCEEDED', 'The request timed out.');
    case str_starts_with($cle, 'cle-instable'):
        // Surchargé aux deux premiers appels, puis rétabli : de quoi essayer les reprises.
        if ($appels <= 2) {
            $erreur(503, 'UNAVAILABLE', 'The model is overloaded.');
        }
        break;
    case str_starts_with($cle, 'cle-sans-questions'):
        // En panne pour les questions de révision seulement : une série dont un genre manque.
        if (str_contains((string) (json_decode($corps, true)['systemInstruction']['parts'][0]['text'] ?? ''), 'flash cards')) {
            $erreur(503, 'UNAVAILABLE', 'The model is overloaded.');
        }
        break;
    case str_starts_with($cle, 'cle-texte-seul'):
        // Le texte passe, la voix est refusée (limite) : un résumé écrit dont l'audio manque.
        if ($voix) {
            $erreur(429, 'RESOURCE_EXHAUSTED', 'You exceeded your current quota.');
        }
        break;
    case str_starts_with($cle, 'cle-modele'):
        if (in_array($modele, ['gemini-3.8-flash', 'gemini-3.8-flash-tts'], true)) {
            $erreur(404, 'NOT_FOUND', "models/$modele is not found for API version v1beta.");
        }
        break;
    case str_starts_with($cle, 'cle-vide'):
        echo json_encode(['promptFeedback' => ['blockReason' => 'SAFETY']]);
        exit;
    case str_starts_with($cle, 'cle-sans-json'):
    case str_starts_with($cle, 'cle-bonne'):
    case str_starts_with($cle, 'cle-wav'):
        break;
    default:
        $erreur(403, 'PERMISSION_DENIED', 'Clé inconnue du faux serveur.');
}

if ($voix) {
    // 0,25 s de « la » à 440 Hz, 24 kHz, 16 bits mono.
    $pcm = '';
    for ($i = 0; $i < 6000; $i++) {
        $pcm .= pack('v', (int) (8000 * sin(2 * M_PI * 440 * $i / 24000)) & 0xFFFF);
    }
    $octets = $pcm;
    $mime = 'audio/L16;codec=pcm;rate=24000';
    if (str_starts_with($cle, 'cle-wav')) {
        $taille = strlen($pcm);
        $octets = 'RIFF' . pack('V', 36 + $taille) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16)
            . 'LIST' . pack('V', 4) . 'INFO' . 'data' . pack('V', $taille) . $pcm;   // un bloc LIST avant « data » : l'en-tête n'a pas 44 octets
        $mime = 'audio/wav';
    }
    echo json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($octets)]]]]]]]);
    exit;
}

// Le mode JSON (responseSchema) : trois flash cards, dont une paire vide qu'il faut écarter — sauf pour la clé
// « cle-sans-json », qui répond en prose comme un modèle qui n'aurait pas suivi le format.
$demande = json_decode($corps, true);
// Une carte mentale : le schéma est un OBJET (les flash cards sont une LISTE). Une idée centrale, trois branches.
if (($demande['generationConfig']['responseSchema']['type'] ?? '') === 'OBJECT' && !str_starts_with($cle, 'cle-sans-json')) {
    echo json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode([
        'titre' => 'Cybersécurité',
        'branches' => [
            ['titre' => 'Chiffrement', 'sous_branches' => [
                ['titre' => 'Confidentialité', 'details' => ['Clé secrète']], ['titre' => 'Intégrité']]],
            ['titre' => 'Pare-feu', 'sous_branches' => [['titre' => 'Filtrage du trafic <script>alert(1)</script>']]],
            ['titre' => 'Défense en profondeur'],
        ],
    ], JSON_UNESCAPED_UNICODE)]]]]]]);
    exit;
}
if (($demande['generationConfig']['responseMimeType'] ?? '') === 'application/json' && !str_starts_with($cle, 'cle-sans-json')) {
    echo json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode([
        ['question' => 'Que protège le chiffrement ?', 'reponse' => 'La confidentialité des données.'],
        ['question' => '   ', 'reponse' => 'Une paire sans question.'],
        ['question' => 'Que fait un pare-feu ?', 'reponse' => 'Il filtre le trafic <b>réseau</b>.'],
        ['question' => 'Qu’est-ce que la défense en profondeur ?', 'reponse' => 'Superposer plusieurs protections.'],
    ], JSON_UNESCAPED_UNICODE)]]]]]]);
    exit;
}

$entree = (string) (json_decode($corps, true)['contents'][0]['parts'][0]['text'] ?? '');
echo json_encode(['candidates' => [['content' => ['parts' => [['text' => "## Résumé bidon\n\n- Modèle : $modele\n- Reçu : " . mb_strlen($entree) . " caractères\n\nUne phrase avec **gras** et <script>alert(1)</script>."]]]]]]);
