<?php
/**
 * Les sondages des discussions : la validation de ce qu'on envoie, créer un sondage entre amis et dans un groupe, voter
 * (réponse unique ou plusieurs), le voir bouger chez l'autre, les cloisonnements (un étranger ne vote pas), la suppression,
 * et le texte dans les quatre langues.
 */
require __DIR__ . '/base.php';
require_once dirname(__DIR__, 2) . '/src/Sondages.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-70s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'son-a@exemple-test.fr', 'pseudo' => 'Son_un'],
    'b' => ['email' => 'son-b@exemple-test.fr', 'pseudo' => 'Son_deux'],
    'c' => ['email' => 'son-c@exemple-test.fr', 'pseudo' => 'Son_trois'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$ck = static fn (string $c): string => __DIR__ . '/ck_son_' . $c . '.txt';
foreach (array_keys($comptes) as $c) { @unlink($ck($c)); }

/** @return array{0: int, 1: string} le code HTTP et le corps */
$appel = static function (string $qui, string $chemin, ?array $post = null) use ($ck): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck($qui), CURLOPT_COOKIEFILE => $ck($qui)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $code = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
    unset($h);
    return [$code, $corps];
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$json = static fn (string $corps): array => json_decode($corps, true) ?: [];

$groupe = null;
try {
    $csrf = [];
    foreach ($comptes as $c => $d) {
        [, $p] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $d['email'], 'mot_de_passe' => $mdp]);
        [, $compte] = $appel($c, 'compte');
        $csrf[$c] = $jeton($compte);
    }
    // A est ami de B et de C ; B et C ne le sont pas entre eux ; le groupe réunit A et B.
    foreach (['b', 'c'] as $autre) {
        $appel('a', 'amis/demande', ['_csrf' => $csrf['a'], 'compte' => (string) $id($autre)]);
        $appel($autre, 'amis/' . $id('a') . '/accepter', ['_csrf' => $csrf[$autre]]);
    }
    $appel('a', 'groupes', ['_csrf' => $csrf['a'], 'nom' => 'Essai sondages', 'membres' => [(string) $id('b')]]);
    $groupe = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);

    $creer = static function (string $qui, string $adresse, string $question, array $options, bool $multiple = true, ?string $jeton = null) use ($appel, $csrf, $json): array {
        [$code, $corps] = $appel($qui, $adresse, ['_csrf' => $jeton ?? $csrf[$qui], 'question' => $question, 'options' => $options] + ($multiple ? ['multiple' => '1'] : []));
        return [$code, $json($corps)];
    };
    $voter = static function (string $qui, int $sondage, array $options) use ($appel, $csrf, $json): array {
        [$code, $corps] = $appel($qui, 'sondages/' . $sondage . '/voter', ['_csrf' => $csrf[$qui], 'options' => $options]);
        return [$code, $json($corps)];
    };
    /** Les nombres de votes par option, dans l'ordre : « 1,0,2 ». */
    $nombres = static fn (array $sondage): string => implode(',', array_column($sondage['options'] ?? [], 'nombre'));
    $moi = static fn (array $sondage): string => implode(',', array_map(static fn (array $o): string => $o['moi'] ? '1' : '0', $sondage['options'] ?? []));

    echo "\n1. Ce qu'on envoie est vérifié\n";
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', '', ['Oui', 'Non']);
    $dire('une question vide est refusée', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'Pose une question')), '422 · oui');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', str_repeat('q', 201), ['Oui', 'Non']);
    $dire('  de plus de 200 caractères aussi', (string) $cod, '422');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand ?', ['Lundi']);
    $dire('une seule option : « au moins deux options »', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'au moins deux options')), '422 · oui');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand ?', ['Lundi', '', '  ', 'x' . str_repeat('y', 100)]);
    $dire('  une option de plus de 100 caractères', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), '100 caractères')), '422 · oui');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand ?', array_map(static fn (int $i): string => 'Option ' . $i, range(1, 13)));
    $dire('  treize options : « Douze options au plus »', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'Douze options')), '422 · oui');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand ?', ['Lundi', '  lundi  ']);
    $dire('  deux options identiques (à la casse et aux espaces près)', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'identiques')), '422 · oui');
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand ?', ['Lundi', 'Mardi'], true, 'faux');
    $dire('  sans le bon jeton CSRF', $oui($cod >= 400), 'oui');
    $dire('  rien n\'a été créé par tous ces refus', bd_valeur('SELECT COUNT(*) FROM sondages WHERE createur_id = ?', [$id('a')]), '0');
    [$cod] = $creer('b', 'amis/' . $id('c') . '/sondages', 'On se voit ?', ['Oui', 'Non']);
    $dire('  deux personnes qui ne sont pas amies : refusé', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondages WHERE createur_id = ?', [$id('b')]), '422 · 0');
    [$cod] = $creer('c', 'groupes/' . $groupe . '/sondages', 'Je peux ?', ['Oui', 'Non']);
    $dire('  quelqu\'un qui n\'est pas du groupe : refusé', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondages WHERE createur_id = ?', [$id('c')]), '422 · 0');
    [$propre] = Sondages::valider("  Quand \n\t se voir ?  ", ['Lundi', '', 'Mardi ', "ma\x07rdi"]);
    $dire('  la validation nettoie : espaces, retours, caractères de contrôle', $oui($propre !== null && $propre['question'] === 'Quand se voir ?' && $propre['options'] === ['Lundi', 'Mardi', 'ma rdi']), 'oui');

    echo "\n2. Un sondage entre deux amis\n";
    [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'Quand réviser ?', ['Lundi', '', 'Mardi', 'Mercredi', '']);
    $messageA = (int) ($r['id'] ?? 0);
    $sondage = (int) bd_valeur('SELECT sondage_id FROM messages WHERE id = ?', [$messageA]);
    $dire('A crée un sondage : accepté, avec son message', $cod . ' · ' . $oui($r['fait'] ?? false) . ' · ' . $oui($messageA > 0 && $sondage > 0), '200 · oui · oui');
    $dire('  trois options gardées (les vides ignorées), réponses multiples', bd_valeur('SELECT COUNT(*) FROM sondage_options WHERE sondage_id = ?', [$sondage]) . ' · ' . bd_valeur('SELECT multiple FROM sondages WHERE id = ?', [$sondage]), '3 · 1');
    $dire('  le texte du message est la question (aperçus, recherche, notifications)', (string) bd_valeur('SELECT texte FROM messages WHERE id = ?', [$messageA]), 'Quand réviser ?');
    [, $corps] = $appel('b', 'amis/' . $id('a') . '/messages?apres=0&modifies_depuis=');
    $fil = $json($corps);
    $lu = null;
    foreach ($fil['messages'] ?? [] as $m) { if ($m['id'] === $messageA) { $lu = $m; } }
    $dire('B reçoit le sondage dans le fil (JSON)', $oui($lu !== null && ($lu['sondage']['question'] ?? '') === 'Quand réviser ?' && count($lu['sondage']['options'] ?? []) === 3), 'oui');
    $dire('  sans vote encore : 0,0,0 et personne de coché', ($nombres($lu['sondage'] ?? [])) . ' · ' . $moi($lu['sondage'] ?? []), '0,0,0 · 0,0,0');
    [, $page] = $appel('b', 'amis/' . $id('a'));
    $dire('  la page le montre : bloc, question, options, sans doublon du texte', $oui(str_contains($page, 'class="bulle__sondage"') && str_contains($page, 'Mercredi')
        && substr_count($page, 'Quand réviser ?') >= 1 && !str_contains($page, '<p class="bulle__texte">Quand réviser ?</p>')), 'oui');
    $dire('  le bouton « Créer un sondage » et sa fenêtre sont dans la page', $oui(str_contains($page, 'data-sondage-ouvrir') && str_contains($page, 'data-sondage-dialogue')
        && str_contains($page, 'sondages.js') && str_contains($page, 'Créer un sondage')), 'oui');
    $optionsDe = static fn (array $sd): array => array_column($sd['options'], 'id');
    $o = $optionsDe($lu['sondage']);
    $maintenantA = $json($appel('a', 'amis/' . $id('b') . '/messages?apres=' . $messageA)[1])['maintenant'] ?? '';

    [$cod, $r] = $voter('b', $sondage, [$o[0], $o[1]]);
    $dire('B vote pour deux options (réponses multiples)', $cod . ' · ' . $nombres($r['sondage'] ?? []) . ' · ' . $moi($r['sondage'] ?? []), '200 · 1,1,0 · 1,1,0');
    [, $corps] = $appel('a', 'amis/' . $id('b') . '/messages?apres=' . $messageA . '&modifies_depuis=' . urlencode($maintenantA));
    $chezA = $json($corps);
    $vu = null;
    foreach ($chezA['reactions'] ?? [] as $x) { if ($x['id'] === $messageA) { $vu = $x['sondage'] ?? null; } }
    $dire('  A le voit bouger sans recharger (relevé des changements)', $oui($vu !== null) . ' · ' . $nombres($vu ?? []) . ' · ' . $moi($vu ?? []), 'oui · 1,1,0 · 0,0,0');
    $dire('  et sait qui a voté quoi (infobulle)', (string) ($vu['options'][0]['qui'] ?? ''), $comptes['b']['pseudo']);
    [, $r] = $voter('a', $sondage, [$o[0]]);
    $dire('A vote aussi : le compte monte, et « Vous » passe en tête', $nombres($r['sondage'] ?? []) . ' · ' . ($r['sondage']['options'][0]['qui'] ?? ''), '2,1,0 · Vous, ' . $comptes['b']['pseudo']);
    [, $r] = $voter('b', $sondage, [$o[1], $o[2]]);
    $dire('B change d\'avis : ses réponses remplacent les précédentes', $nombres($r['sondage'] ?? []) . ' · ' . $moi($r['sondage'] ?? []), '1,1,1 · 0,1,1');
    [, $r] = $voter('b', $sondage, []);
    $dire('  et peut retirer son vote', $nombres($r['sondage'] ?? []) . ' · ' . ($r['sondage']['votants'] ?? '?'), '1,0,0 · 1');
    [$cod, $r] = $voter('b', $sondage, [$o[0], $o[0]]);
    $dire('  une réponse envoyée deux fois ne compte qu\'une fois', $cod . ' · ' . $nombres($r['sondage'] ?? []), '200 · 2,0,0');
    [$cod, $r] = $voter('b', $sondage, [999999999]);
    $dire('une réponse qui n\'existe pas : refusée', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'n’existe pas')), '422 · oui');
    $dire('  un vote ne se prend pas sur l\'option d\'un autre sondage', bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ?', [$sondage]), '2');

    echo "\n   — réponse unique\n";
    [, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', 'On commande ?', ['Pizza', 'Sushi'], false);
    $messageU = (int) ($r['id'] ?? 0);
    $unique = (int) bd_valeur('SELECT sondage_id FROM messages WHERE id = ?', [$messageU]);
    $ou = array_map('intval', array_column(bd_all('SELECT id FROM sondage_options WHERE sondage_id = ? ORDER BY position', [$unique]), 'id'));
    [$cod, $r] = $voter('b', $unique, [$ou[0], $ou[1]]);
    $dire('deux réponses à un sondage à réponse unique : refusé', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'qu’une réponse')), '422 · oui');
    [, $r] = $voter('b', $unique, [$ou[0]]);
    [, $r] = $voter('b', $unique, [$ou[1]]);
    $dire('  voter une autre option déplace la voix', $nombres($r['sondage'] ?? []) . ' · ' . $moi($r['sondage'] ?? []), '0,1 · 0,1');

    echo "\n3. Qui ne voit pas ne vote pas\n";
    [$cod, $r] = $voter('c', $sondage, [$o[0]]);
    $dire('un autre ami de A, qui n\'est pas dans la discussion', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'n’existe plus')), '404 · oui');
    $dire('  son vote n\'est pas enregistré', bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE user_id = ?', [$id('c')]), '0');
    [$cod] = $voter('c', 999999999, [1]);
    $dire('un sondage qui n\'existe pas', (string) $cod, '404');
    [$cod, $corps] = $appel('b', 'sondages/' . $sondage . '/voter', ['_csrf' => 'faux', 'options' => [$o[2]]]);
    $dire('sans le bon jeton CSRF : rien', $oui($cod >= 400) . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ?', [$sondage]), 'oui · 2');
    [$cod, $corps] = $appel('b', 'amis/messages/' . $messageA . '/modifier', ['_csrf' => $csrf['b'], 'texte' => 'Autre']);
    $dire('on ne modifie pas la question d\'un sondage (même l\'auteur : A le tente ci-dessous)', $oui(true), 'oui');
    [$cod, $corps] = $appel('a', 'amis/messages/' . $messageA . '/modifier', ['_csrf' => $csrf['a'], 'texte' => 'Autre question']);
    $dire('  « Un sondage ne se modifie pas »', $oui(str_contains($corps, 'ne se modifie pas')) . ' · ' . bd_valeur('SELECT texte FROM messages WHERE id = ?', [$messageA]), 'oui · Quand réviser ?');
    $appel('b', 'amis/' . $id('a') . '/messages', ['_csrf' => $csrf['b'], 'texte' => 'Bonne idée', 'reponse_a' => (string) $messageA]);
    [, $corps] = $appel('a', 'amis/' . $id('b') . '/messages?apres=' . $messageU);
    $reponse = null;
    foreach ($json($corps)['messages'] ?? [] as $m) { if (($m['texte'] ?? '') === 'Bonne idée') { $reponse = $m; } }
    $dire('répondre à un sondage : la citation le reconnaît', (string) ($reponse['reponse']['extrait'] ?? ''), '📊 Sondage · Quand réviser ?');
    [, $liste] = $appel('a', 'amis');
    $dire('l\'aperçu de la liste des discussions reconnaît un sondage', $oui(str_contains($liste, '📊 Sondage') || str_contains($liste, 'Bonne idée')), 'oui');

    echo "\n4. Supprimer un sondage\n";
    [$cod, $corps] = $appel('a', 'amis/messages/' . $messageU . '/supprimer', ['_csrf' => $csrf['a'], 'portee' => 'tous']);
    $dire('A le supprime pour tous : le sondage, ses options et ses votes s\'en vont', bd_valeur('SELECT COUNT(*) FROM sondages WHERE id = ?', [$unique]) . ' · '
        . bd_valeur('SELECT COUNT(*) FROM sondage_options WHERE sondage_id = ?', [$unique]) . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ?', [$unique]), '0 · 0 · 0');
    [$cod, $r] = $voter('b', $unique, [$ou[0]]);
    $dire('  voter dessus ensuite : « n\'existe plus »', $cod . ' · ' . $oui(str_contains((string) ($r['message'] ?? ''), 'n’existe plus')), '404 · oui');
    $dire('  le message reste, vidé, marqué supprimé', bd_valeur('SELECT supprime_le IS NOT NULL FROM messages WHERE id = ?', [$messageU]) . ' · ' . bd_valeur('SELECT sondage_id IS NULL FROM messages WHERE id = ?', [$messageU]), '1 · 1');
    [$cod] = $appel('b', 'amis/messages/' . $messageA . '/supprimer', ['_csrf' => $csrf['b'], 'portee' => 'moi']);
    [$cod, $r] = $voter('b', $sondage, [$o[0]]);
    $dire('B cache le sondage pour lui : il ne peut plus y voter', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondages WHERE id = ?', [$sondage]), '404 · 1');

    echo "\n5. Un sondage dans un groupe\n";
    [$cod, $r] = $creer('a', 'groupes/' . $groupe . '/sondages', 'Quel jour pour le projet ?', ['Jeudi', 'Vendredi', 'Samedi'], true);
    $messageG = (int) ($r['id'] ?? 0);
    $sg = (int) bd_valeur('SELECT sondage_id FROM conversation_messages WHERE id = ?', [$messageG]);
    $dire('A crée un sondage dans le groupe', $cod . ' · ' . $oui($sg > 0) . ' · ' . bd_valeur('SELECT canal FROM sondages WHERE id = ?', [$sg]), '200 · oui · groupes');
    [, $corps] = $appel('b', 'groupes/' . $groupe . '/messages?apres=0&modifies_depuis=');
    $lu = null;
    foreach ($json($corps)['messages'] ?? [] as $m) { if ($m['id'] === $messageG) { $lu = $m; } }
    $dire('  B le reçoit dans le fil du groupe, avec le nom de l\'auteur', $oui($lu !== null && ($lu['sondage']['question'] ?? '') === 'Quel jour pour le projet ?') . ' · ' . ($lu['auteur'] ?? ''), 'oui · ' . $comptes['a']['pseudo']);
    $og = $optionsDe($lu['sondage']);
    $maintenantG = $json($appel('a', 'groupes/' . $groupe . '/messages?apres=' . $messageG)[1])['maintenant'] ?? '';
    [, $r] = $voter('b', $sg, [$og[2]]);
    $dire('B vote', $nombres($r['sondage'] ?? []) . ' · ' . $moi($r['sondage'] ?? []), '0,0,1 · 0,0,1');
    [, $corps] = $appel('a', 'groupes/' . $groupe . '/messages?apres=' . $messageG . '&modifies_depuis=' . urlencode($maintenantG));
    $vu = null;
    foreach ($json($corps)['reactions'] ?? [] as $x) { if ($x['id'] === $messageG) { $vu = $x['sondage'] ?? null; } }
    $dire('  A voit le vote arriver dans le groupe', $nombres($vu ?? []) . ' · ' . ($vu['options'][2]['qui'] ?? ''), '0,0,1 · ' . $comptes['b']['pseudo']);
    [$cod, $r] = $voter('c', $sg, [$og[0]]);
    $dire('C (ami de A, mais pas du groupe) ne peut pas voter', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE user_id = ?', [$id('c')]), '404 · 0');
    [, $page] = $appel('a', 'groupes/' . $groupe);
    $dire('la page du groupe porte le bouton, la fenêtre et le bloc du sondage', $oui(str_contains($page, 'data-sondage-ouvrir') && str_contains($page, 'class="bulle__sondage"')
        && str_contains($page, '/groupes/' . $groupe . '/sondages"')), 'oui');
    [, $liste] = $appel('a', 'amis');
    $dire('l\'aperçu du groupe dans la liste : « 📊 Sondage »', $oui(str_contains($liste, '📊 Sondage')), 'oui');
    [$cod] = $appel('b', 'groupes/messages/' . $messageG . '/supprimer', ['_csrf' => $csrf['b'], 'portee' => 'moi']);
    [$cod, $r] = $voter('b', $sg, [$og[0]]);
    $dire('B cache le message pour lui : il ne vote plus', (string) $cod, '404');
    [$cod] = $appel('a', 'groupes/messages/' . $messageG . '/supprimer', ['_csrf' => $csrf['a'], 'portee' => 'tous']);
    $dire('A le supprime pour tous : le sondage disparaît', bd_valeur('SELECT COUNT(*) FROM sondages WHERE id = ?', [$sg]) . ' · ' . bd_valeur('SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ?', [$sg]), '0 · 0');

    echo "\n6. Le texte, dans les quatre langues\n";
    foreach ([
        'en' => ['Create a poll', 'Ask a question of 200 characters at most.', 'Allow multiple answers', '"son.consigne_plusieurs":'],
        'es' => ['Crear una encuesta', 'Escribe una pregunta de 200 caracteres como máximo.', 'Permitir varias respuestas', '"son.consigne_plusieurs":'],
        'de' => ['Umfrage erstellen', 'Stelle eine Frage mit höchstens 200 Zeichen.', 'Mehrere Antworten erlauben', '"son.consigne_plusieurs":'],
        'fr' => ['Créer un sondage', 'Pose une question de 200 caractères au plus.', 'Autoriser plusieurs réponses', '"son.consigne_plusieurs":'],
    ] as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf['a'], 'langue' => $langue]);
        [, $page] = $appel('a', 'amis/' . $id('b'));
        [$cod, $r] = $creer('a', 'amis/' . $id('b') . '/sondages', '', ['Oui', 'Non']);
        $dire("$langue : fenêtre, consigne, interrupteur, message d'erreur du serveur", $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[2]) && str_contains($page, $mots[3])
            && str_contains((string) ($r['message'] ?? ''), $mots[1]) && !preg_match('/>\s*(son|msg)\.[a-z_.]+\s*</', $page)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : seulement les comptes d'essai, le groupe d'essai, et ce qui en dépend.
    if ($groupe !== null && $groupe > 0) {
        bd_run('DELETE FROM conversations WHERE id = ?', [$groupe]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
        @unlink($ck($c));
    }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'son-_@exemple-test.fr'");
    $orphelins = (int) bd_valeur('SELECT COUNT(*) FROM sondages WHERE createur_id NOT IN (SELECT id FROM users)');
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . ' · sondages orphelins ' . $orphelins . "\n";
}
