<?php
/** L'apparence et la langue : l'aperçu ne coûte rien, seul « Valider » enregistre. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-62s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$email = 'theme-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Theme_essai', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai thème']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_theme.txt';
@unlink($ck);
$appel = static function (string $chemin, ?array $post = null) use ($ck): string {
    usleep(350000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    unset($h);
    return $corps;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

try {
    $p = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    $compte = $appel('compte');
    $csrf = $jeton($compte);
    preg_match('/<form[^>]*action="[^"]*compte\/theme"[^>]*>/', $compte, $m);
    $form = $m[0] ?? '';

    echo "\n1. Le formulaire\n";
    $dire('il ne part plus tout seul (pas de data-auto-envoi)', $oui($form !== '' && !str_contains($form, 'data-auto-envoi')), 'oui');
    $dire('  il garde l’aperçu en direct (data-choix-theme)', $oui(str_contains($form, 'data-choix-theme')), 'oui');
    $dire('  « Valider » est un vrai bouton d’envoi, hors <noscript>',
        $oui(preg_match('/<button[^>]*type="submit"[^>]*>Valider<\/button>/', $compte) === 1), 'oui');
    $dire('  « Annuler » referme', $oui(preg_match('/data-reglage-annuler>Annuler<\/button>/', $compte) === 1), 'oui');
    $dire('  l’aide dit que rien n’est enregistré sans validation', $oui(str_contains($compte, 'il n’est enregistré que si vous validez')), 'oui');

    echo "\n2. Rien n'est enregistré sans validation\n";
    $dire('au départ, le thème du compte est « auto »', (string) bd_valeur('SELECT theme FROM users WHERE id = ?', [$id]), 'auto');
    $dire('  la page se lit en « auto »', $oui(str_contains($appel(''), 'data-theme="auto"')), 'oui');

    echo "\n3. Valider\n";
    $r = $appel('compte/theme', ['_csrf' => $csrf, 'theme' => 'sombre']);
    $dire('« Sombre » validé : gardé en base', (string) bd_valeur('SELECT theme FROM users WHERE id = ?', [$id]), 'sombre');
    $dire('  la page suivante est sombre', $oui(str_contains($appel(''), 'data-theme="sombre"')), 'oui');

    foreach (['en' => 'Apply', 'es' => 'Aplicar', 'de' => 'Übernehmen'] as $langue => $mot) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        $page = $appel('compte');
        $dire("  en $langue : le bouton s'appelle « $mot »", $oui(str_contains($page, '>' . $mot . '</button>')), 'oui');
    }

    echo "\n4. La langue : un aperçu, puis « Valider »\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $langue = static fn (): string => (string) bd_valeur('SELECT langue FROM users WHERE id = ?', [$id]);
    $page = $appel('compte');
    preg_match('/<form[^>]*action="[^"]*compte\/langue"[^>]*>/', $page, $m);
    $form = $m[0] ?? '';
    $dire('le formulaire ne part plus tout seul', $oui($form !== '' && !str_contains($form, 'data-auto-envoi') && str_contains($form, 'data-choix-langue')), 'oui');
    $dire('  replié : « Français » se lit, le formulaire est caché', $oui(str_contains($form, ' hidden') && str_contains($page, '🇫🇷 Français')), 'oui');
    $apercu = $appel('compte?apercu_langue=de');
    $dire('« ?apercu_langue=de » : la page s’annonce en allemand',
        $oui(str_contains($apercu, '<html lang="de"') && str_contains($apercu, 'Mein Konto')), 'oui');
    $dire('  mais le compte reste en français', $langue(), 'fr');
    preg_match('/<form[^>]*action="[^"]*compte\/langue"[^>]*>/', $apercu, $m);
    $dire('  le formulaire est ouvert, « Deutsch » est coché',
        $oui(!str_contains($m[0] ?? ' hidden', ' hidden') && preg_match('/value="de" checked/', $apercu) === 1), 'oui');
    $dire('  « Übernehmen » enregistre, « Abbrechen » revient sans aperçu',
        $oui(str_contains($apercu, '>Übernehmen</button>') && preg_match('/<a [^>]*href="[^"]*\/compte#langue"[^>]*>Abbrechen<\/a>/', $apercu) === 1), 'oui');
    $dire('  la page suivante, sans aperçu, est de nouveau en français',
        $oui(str_contains($appel('compte'), '<html lang="fr"')), 'oui');
    $dire('  une langue inconnue est ignorée', $oui(str_contains($appel('compte?apercu_langue=klingon'), '<html lang="fr"')), 'oui');
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'de']);
    $dire('« Valider » : le compte passe en allemand', $langue(), 'de');
    $dire('  et la page suivante le montre', $oui(str_contains($appel('compte'), '<html lang="de"')), 'oui');
} finally {
    bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr']) . "\n";
}
