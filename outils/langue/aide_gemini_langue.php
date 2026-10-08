<?php
/**
 * Le mode d'emploi « Obtenir une clé API Gemini » (aide/gemini), par le serveur web : réservé aux comptes connectés, six étapes
 * illustrées de schémas, la rubrique de dépannage, le bouton qui l'ouvre depuis la carte « Clé API Gemini » de « Mon compte »
 * (en fenêtre), le lien vers Google AI Studio, et les quatre langues sans clé de traduction brute.
 *
 * La base locale est désignée par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin) : l'essai ne
 * dépend pas du fichier de réglages de l'installation.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$sauvegarde = $fichierEssai . '.avant-essai';
if (is_file($fichierEssai)) { rename($fichierEssai, $sauvegarde); }
file_put_contents($fichierEssai, '<?php return ' . var_export([
    'db' => ['host' => '127.0.0.1', 'name' => 'mon_appli_cours', 'user' => 'root', 'pass' => ''],
], true) . ';');
sleep(3);   // le serveur web garde le fichier compilé quelques secondes (OPcache)

$email = 'aide-gem@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Aide_Gemini', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai aide']);
$cookie = __DIR__ . '/ck_aide_gem.txt';
@unlink($cookie);
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $chemin, ?array $post = null, bool $connecte = true) use ($cookie): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
    if ($connecte) { curl_setopt_array($h, [CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]); }
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

try {
    echo "\n1. Accès\n";
    [$visiteur, , $adresse] = $appel('aide/gemini', null, false);
    $dire('un visiteur non connecté est renvoyé vers la connexion', $oui(str_contains($adresse, 'connexion') && !str_contains($visiteur, 'API keys')), 'oui');
    [$p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    [$page, $code] = $appel('aide/gemini');
    $dire('connecté : la page s\'ouvre (200)', (string) $code, '200');

    echo "\n2. Le contenu\n";
    $dire('six étapes numérotées', (string) preg_match_all('/<li class="carte guide">/', $page), '6');
    $dire('six schémas, chacun avec sa description pour les lecteurs d\'écran', (string) preg_match_all('/<svg class="guide__fig"[^>]*aria-label="[^"]{15,}"/', $page), '6');
    $dire('le lien qui ouvre Google AI Studio dans un nouvel onglet, sans fuite de l\'origine', $oui(str_contains($page, 'href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"')), 'oui');
    $dire('l\'avertissement sur la version gratuite et la vie privée', $oui(str_contains($page, 'Version gratuite et vie privée')), 'oui');
    $dire('l\'avertissement « la clé est secrète »', $oui(str_contains($page, 'Cette clé est secrète')), 'oui');
    $dire('cinq questions de dépannage', (string) substr_count($page, '<dt>'), '5');
    $dire('le renvoi vers « Mon compte » (carte Gemini) et vers l\'assistant', $oui(str_contains($page, 'compte#gemini') && str_contains($page, '/assistant')), 'oui');
    [$fenetre] = $appel('aide/gemini?fenetre=1');
    $dire('en fenêtre : un fragment, sans l\'en-tête du site', $oui(!str_contains($fenetre, '<html') && str_contains($fenetre, 'guide__etapes')), 'oui');

    echo "\n3. Le bouton, dans « Mon compte »\n";
    [$compte] = $appel('compte');
    $dire('la carte « Clé API Gemini » propose le guide, ouvert en fenêtre', $oui((bool) preg_match('/<a class="bouton bouton--discret" href="[^"]*aide\/gemini" data-fenetre>/', $compte)), 'oui');
    $dire('et le formulaire de saisie garde son lien vers Google AI Studio', $oui(str_contains($compte, 'https://aistudio.google.com/apikey')), 'oui');

    echo "\n4. Les quatre langues\n";
    $csrf = $jeton($compte);
    foreach ([
        'en' => ['How to get a Gemini API key', 'Free tier and privacy', 'This key is secret'],
        'es' => ['Cómo obtener una clave de API de Gemini', 'Versión gratuita y privacidad', 'Esta clave es secreta'],
        'de' => ['So erhältst du einen Gemini-API-Schlüssel', 'Kostenlose Stufe und Datenschutz', 'Dieser Schlüssel ist geheim'],
        'fr' => ['Comment obtenir une clé API Gemini', 'Version gratuite et vie privée', 'Cette clé est secrète'],
    ] as $langue => $mots) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$page] = $appel('aide/gemini');
        [$compte] = $appel('compte');
        $dire("$langue : titre, avertissements, et pas de clé de traduction brute (page et bouton)",
            $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[1]) && str_contains($page, $mots[2])
                && !preg_match('/aide\.gemini\.[a-z0-9_]+/', $page . $compte) && str_contains($compte, 'aide/gemini')), 'oui');
    }
    $termine = true;
} finally {
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    @unlink($cookie);
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
