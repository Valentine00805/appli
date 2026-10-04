<?php
/** La clé API Gemini : l'espace dans « Mon compte », le chiffrement, l'absence de fuite, la sauvegarde. */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-64s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$email = 'gemini-a@exemple-test.fr';
$autre = 'gemini-b@exemple-test.fr';
foreach ([$email, $autre] as $a) { bd_run('DELETE FROM users WHERE email = ?', [$a]); }
foreach ([[$email, 'Gemini_a'], [$autre, 'Gemini_b']] as [$a, $pseudo]) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$a, $pseudo, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai gemini']);
}
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
$idAutre = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$autre]);

$ck = __DIR__ . '/ck_gemini.txt';
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

// Une fausse clé, de la forme de celles qu'on délivre (« AIza » + 35 caractères) : elle ne vaut rien chez Google.
$cle1 = 'AIzaSyEssaiBidon0123456789abcdefghijk';
$cle2 = 'AIzaSyAutreEssaiBidon9876543210zyxwvu';
$secret = (static function (): string {
    $c = require dirname(__DIR__, 2) . '/config/parametres.php';
    return (string) base64_decode((string) ($c['securite']['cle_chiffrement'] ?? ''), true);
})();
$dechiffrer = static function (string $stocke, int $uid) use ($secret): string|false {
    $brut = base64_decode($stocke, true);
    return openssl_decrypt(substr($brut, 28), 'aes-256-gcm', $secret, OPENSSL_RAW_DATA,
        substr($brut, 0, 12), substr($brut, 12, 16), 'cles_api:' . $uid . ':gemini');
};

try {
    $p = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    $compte = $appel('compte');
    $csrf = $jeton($compte);

    echo "\n1. L'espace dans « Mon compte »\n";
    $dire('la clé de chiffrement est en place (32 octets)', (string) strlen($secret), '32');
    $dire('la section « Clé API Gemini » existe, vide', $oui(str_contains($compte, 'id="gemini"')
        && str_contains($compte, 'Clé API Gemini') && str_contains($compte, 'Aucune clé enregistrée')), 'oui');
    $dire('  le champ est un champ de mot de passe, sans remplissage automatique',
        $oui(preg_match('/<input type="password" id="cle_gemini"[^>]*autocomplete="off"/', $compte) === 1), 'oui');
    $dire('  le formulaire est replié (« Ajouter une clé » le déplie)',
        $oui(preg_match('/action="[^"]*compte\/gemini"[^>]*data-reglage-edition hidden/', $compte) === 1
            && str_contains($compte, 'Ajouter une clé')), 'oui');
    $dire('  pas de bouton « Retirer » tant qu’il n’y a pas de clé', $oui(!str_contains($compte, 'compte/gemini/retirer')), 'oui');

    echo "\n2. Une clé refusée\n";
    foreach (['avec des espaces dedans', 'court', "https://exemple.fr/cle?x=1"] as $mauvaise) {
        $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => $mauvaise]);
    }
    $dire('trois saisies absurdes : rien n’est enregistré', bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$id]), '0');
    $dire('  et le message le dit',
        $oui(str_contains($appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'pas valable !']), 'n’a pas l’allure d’une clé API')), 'oui');
    $dire('  sans jeton CSRF : refusé, rien n’est enregistré',
        $oui(($appel('compte/gemini', ['cle_gemini' => $cle1]) !== '') && (string) bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$id]) === '0'), 'oui');

    echo "\n3. Une clé enregistrée\n";
    $r = $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => '  ' . $cle1 . "\n"]);
    $ligne = bd_all('SELECT cle_chiffree, fin FROM cles_api WHERE user_id = ? AND fournisseur = ?', [$id, 'gemini'])[0] ?? null;
    $dire('une ligne en base, avec les quatre derniers caractères', (string) ($ligne['fin'] ?? 'absente'), substr($cle1, -4));
    $dire('  la clé n’est pas dans la base en clair',
        $oui($ligne !== null && !str_contains((string) $ligne['cle_chiffree'], $cle1) && !str_contains((string) $ligne['cle_chiffree'], 'AIza')), 'oui');
    $dire('  elle se déchiffre, entourée d’espaces effacées',
        $ligne === null ? 'absente' : (string) $dechiffrer((string) $ligne['cle_chiffree'], $id), $cle1);
    $dire('  attachée à sa ligne : sous l’identité d’un autre compte, elle ne s’ouvre pas',
        $oui($ligne !== null && $dechiffrer((string) $ligne['cle_chiffree'], $idAutre) === false), 'oui');
    $dire('  le message annonce la fin de la clé, pas la clé',
        $oui(str_contains($r, 'se termine par ' . substr($cle1, -4)) && !str_contains($r, $cle1)), 'oui');
    $compte = $appel('compte');
    $dire('  la page dit « se termine par », et ne contient jamais la clé',
        $oui(str_contains($compte, 'Clé enregistrée · se termine par ' . substr($cle1, -4)) && !str_contains($compte, $cle1)), 'oui');
    $dire('  « Retirer la clé » apparaît', $oui(str_contains($compte, 'compte/gemini/retirer')), 'oui');
    $dire('  le champ n’est pas prérempli', $oui(preg_match('/<input type="password" id="cle_gemini"[^>]* value=/', $compte) === 0), 'oui');

    $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => $cle2]);
    $dire('une seconde saisie remplace la première (une seule ligne)',
        bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$id]) . ' · ' . bd_valeur('SELECT fin FROM cles_api WHERE user_id = ?', [$id]),
        '1 · ' . substr($cle2, -4));

    // Les clés changent de forme : un point, plus de 39 caractères, des guillemets collés avec elles.
    $cle3 = 'AQ.Ab8RN6ExempleBidon_0123456789-abcdefghijklmnopqrstuvwxyz';
    $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => ' "' . $cle3 . '" ']);
    $ligne3 = bd_all('SELECT cle_chiffree, fin FROM cles_api WHERE user_id = ?', [$id])[0] ?? null;
    $dire('une clé plus récente (point, 60 caractères, entre guillemets) est acceptée',
        $ligne3 === null ? 'absente' : (string) $dechiffrer((string) $ligne3['cle_chiffree'], $id), $cle3);
    $r = $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => 'une phrase avec espaces et une adresse https://x.fr/a?b=1']);
    $dire('  le refus indique le nombre de caractères reçus, pas la saisie',
        $oui(preg_match('/reçu : \d+ caractères/', $r) === 1 && !str_contains($r, 'une phrase avec')), 'oui');

    echo "\n4. Chacun la sienne\n";
    $dire('l’autre compte n’a rien', bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$idAutre]), '0');

    echo "\n5. Ni dans la sauvegarde, ni dans les pages vues en d’autres langues\n";
    $archive = $appel('compte/sauvegarde/export');
    // L'archive est un .zip : elle se compresse, on cherche donc dans son contenu, pas dans ses octets.
    $zipTmp = tempnam(sys_get_temp_dir(), 'svg');
    file_put_contents($zipTmp, $archive);
    $zip = new ZipArchive();
    $donnees = $zip->open($zipTmp) === true ? (string) $zip->getFromName('donnees.json') : '';
    $contenuZip = $donnees;
    for ($i = 0; $zip->numFiles > $i; $i++) { $contenuZip .= (string) $zip->getFromIndex($i); }
    $zip->close();
    @unlink($zipTmp);
    $dire('l’archive exportée se lit (zip, donnees.json non vide)', $oui(str_starts_with($archive, 'PK') && strlen($donnees) > 100), 'oui');
    $dire('  elle ne contient ni la clé, ni la table, ni le chiffré',
        $oui(!str_contains($contenuZip, $cle3) && !str_contains($contenuZip, 'cles_api') && !str_contains($contenuZip, 'cle_chiffree')), 'oui');
    foreach (['en' => ['Gemini API key', 'Key saved · ends in'], 'es' => ['Clave API de Gemini', 'Clave guardada · termina en'],
              'de' => ['Gemini-API-Schlüssel', 'Schlüssel gespeichert · endet auf']] as $langue => [$titre, $dit]) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        $page = $appel('compte');
        $dire("  en $langue : « $titre »", $oui(str_contains($page, $titre) && str_contains($page, $dit) && !str_contains($page, $cle3)), 'oui');
    }
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);

    echo "\n6. Retirer\n";
    $appel('compte/gemini/retirer', ['_csrf' => $csrf]);
    $dire('la ligne est effacée de la base', bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$id]), '0');
    $dire('  la page redit « Aucune clé enregistrée »', $oui(str_contains($appel('compte'), 'Aucune clé enregistrée')), 'oui');

    echo "\n7. Un compte effacé emporte sa clé\n";
    $appel('compte/gemini', ['_csrf' => $csrf, 'cle_gemini' => $cle1]);
    bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    $dire('plus aucune ligne pour ce compte', bd_valeur('SELECT COUNT(*) FROM cles_api WHERE user_id = ?', [$id]), '0');
} finally {
    bd_run('DELETE FROM users WHERE email IN (?, ?) AND email LIKE ?', [$email, $autre, '%@exemple-test.fr']);
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr']) . "\n";
}
