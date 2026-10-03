<?php
/*
 * Quelqu'un qui n'a pas de compte lit l'application dans sa langue.
 *
 * Jusqu'ici la langue ne se lisait que dans le compte : connexion, inscription, mot de
 * passe oublié et liens publics s'affichaient toujours en français. Ils suivent maintenant
 * le choix du visiteur (un cookie), sinon son navigateur (Accept-Language), et l'inscription
 * crée le compte dans cette langue.
 *
 * Tout passe par HTTP, sans compte — c'est ce qu'on mesure.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-52s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$base = 'http://localhost/mon_appli/appli/';
$cheminBase = '/mon_appli/appli';

/** Une requête : ni redirection suivie, ni cookie, sauf si on le demande. */
$requete = static function (string $chemin, array $o = []) use ($base): array {
    usleep(300000);
    $h = curl_init($base . $chemin);
    $entetes = isset($o['langue']) ? ['Accept-Language: ' . $o['langue']] : [];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => $o['suivre'] ?? false, CURLOPT_HTTPHEADER => $entetes];
    if (!empty($o['jar'])) {
        $options[CURLOPT_COOKIEJAR] = $o['jar'];
        $options[CURLOPT_COOKIEFILE] = $o['jar'];
    }
    if (isset($o['post'])) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($o['post']);
    }
    curl_setopt_array($h, $options);
    $reponse = (string) curl_exec($h);
    $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);

    return ['code' => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE),
        'tete' => substr($reponse, 0, $taille), 'corps' => substr($reponse, $taille)];
};
$langue = static fn (array $r): string => preg_match('/<html lang="([a-z]+)"/', $r['corps'], $m) === 1 ? $m[1] : '?';
$jeton = static fn (array $r): string => preg_match('/name="_csrf" value="([^"]+)"/', $r['corps'], $m) === 1 ? $m[1] : '';
$entete = static fn (array $r, string $nom): string => preg_match('/^' . preg_quote($nom, '/') . ':\s*(.*)$/mi', $r['tete'], $m) === 1 ? trim($m[1]) : '';

$mdp = 'MotDePasse!2026';
$adresses = ['vis-fr@exemple-test.fr', 'vis-new@exemple-test.fr', 'vis-prop@exemple-test.fr'];
foreach ($adresses as $a) {
    bd_run('DELETE FROM users WHERE email = ?', [$a]);
}
$jars = [];
$jar = static function (string $nom) use (&$jars): string {
    $chemin = __DIR__ . '/ck_vis_' . $nom . '.txt';
    @unlink($chemin);
    $jars[] = $chemin;

    return $chemin;
};

try {
    echo "\n1. Le navigateur dit sa langue\n";
    $cas = [
        ['', 'fr'], ['en-GB,en;q=0.9', 'en'], ['de-DE,de;q=0.9,en;q=0.8', 'de'], ['es', 'es'],
        ['ja,zh;q=0.9', 'fr'], ['ja,en;q=0.5', 'en'], ['fr;q=0.2,en;q=0.9', 'en'],
        ['en;q=0,de;q=0.4', 'de'], ['EN-us', 'en'], ['*', 'fr'],
    ];
    foreach ($cas as [$entetes, $attendu]) {
        $r = $requete('connexion', $entetes === '' ? [] : ['langue' => $entetes]);
        $dire('« ' . ($entetes === '' ? '(rien)' : $entetes) . ' »', $langue($r), $attendu);
    }
    $r = $requete('connexion', ['langue' => 'en']);
    $vary = $entete($r, 'Vary');
    $dire('la réponse dépend de la langue : « Vary » le dit',
        $oui(stripos($vary, 'Accept-Language') !== false && stripos($vary, 'Cookie') !== false), 'oui');

    echo "\n2. Les pages sans compte, en anglais\n";
    foreach (['connexion' => 'Mot de passe', 'inscription' => 'Mot de passe', 'mot-de-passe/oublie' => 'Mot de passe'] as $page => $francais) {
        $r = $requete($page, ['langue' => 'en']);
        $dire($page . ' : la page est en anglais', $langue($r), 'en');
        $dire('  et plus un mot de français', $oui(!str_contains($r['corps'], $francais)), 'oui');
        $dire('  le choix des quatre langues y est', (string) substr_count($r['corps'], 'class="choix-langue__bouton"'), '4');
    }
    $r = $requete('connexion', ['langue' => 'en']);
    $dire('la langue courante est marquée', $oui(preg_match('/lang="en"\s+aria-current="true"/', $r['corps']) === 1), 'oui');
    $dire('  et une seule', (string) substr_count($r['corps'], 'aria-current="true"'), '1');
    $dire('le titre : « Log in »', $oui(str_contains($r['corps'], '<title>Log in')), 'oui');

    echo "\n3. Le visiteur choisit sa langue\n";
    $j = $jar('choix');
    $page = $requete('connexion', ['langue' => 'en', 'jar' => $j]);
    $csrf = $jeton($page);
    $r = $requete('langue', ['jar' => $j, 'post' => ['_csrf' => $csrf, 'langue' => 'de', 'retour' => $cheminBase . '/inscription']]);
    $dire('la réponse est une redirection', (string) $r['code'], '302');
    $dire('  vers la page d’où il vient', $entete($r, 'Location'), $cheminBase . '/inscription');
    $cookie = $entete($r, 'Set-Cookie');
    $dire('  et un cookie garde son choix', $oui(str_contains($r['tete'], 'MESCOURS_LANGUE=de')), 'oui');
    $dire('  limité à l’application', $oui(stripos($r['tete'], 'path=' . $cheminBase . '/') !== false), 'oui');
    $dire('  sans être lisible par un script', $oui(stripos($r['tete'], 'httponly') !== false), 'oui');
    $r = $requete('inscription', ['langue' => 'en', 'jar' => $j]);
    $dire('le cookie l’emporte sur le navigateur (en)', $langue($r), 'de');

    $r = $requete('langue', ['jar' => $j, 'post' => ['_csrf' => $csrf, 'langue' => 'xx', 'retour' => $cheminBase . '/connexion']]);
    $dire('une langue inconnue ne change rien', $oui(!str_contains($r['tete'], 'MESCOURS_LANGUE=xx')), 'oui');
    $r = $requete('langue', ['jar' => $j, 'post' => ['_csrf' => $csrf, 'langue' => 'es', 'retour' => 'https://exemple-pirate.fr/']]);
    $dire('un retour vers un autre site est refusé',
        $entete($r, 'Location'), $cheminBase . '/connexion');
    $r = $requete('langue', ['jar' => $j, 'post' => ['_csrf' => $csrf, 'langue' => 'es', 'retour' => '//exemple-pirate.fr/']]);
    $dire('  ni par une double barre', $entete($r, 'Location'), $cheminBase . '/connexion');
    $r = $requete('langue', ['post' => ['langue' => 'es']]);
    $dire('sans jeton CSRF, le choix est refusé', (string) $r['code'], '400');

    echo "\n4. Le compte garde sa langue\n";
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom, langue) VALUES (?, ?, ?, ?, ?)',
        [$adresses[0], 'Vis_fr', password_hash($mdp, PASSWORD_DEFAULT), 'Essai visiteur', 'fr']);
    $j = $jar('compte');
    $page = $requete('connexion', ['langue' => 'en', 'jar' => $j]);
    $csrf = $jeton($page);
    // Le visiteur a choisi l'allemand, son navigateur dit l'anglais, son compte est en français.
    $requete('langue', ['jar' => $j, 'post' => ['_csrf' => $csrf, 'langue' => 'de', 'retour' => $cheminBase . '/connexion']]);
    $requete('connexion', ['langue' => 'en', 'jar' => $j, 'suivre' => true,
        'post' => ['_csrf' => $csrf, 'identifiant' => $adresses[0], 'mot_de_passe' => $mdp]]);
    $r = $requete('compte', ['langue' => 'en', 'jar' => $j]);
    $dire('connecté : la langue du compte l’emporte', $langue($r), 'fr');
    $dire('  et la page du compte n’affiche pas le choix de visiteur',
        $oui(!str_contains($r['corps'], 'class="choix-langue"')), 'oui');
    $dire('  ni l’avis « certaines pages sont encore en français »',
        $oui(!str_contains($r['corps'], 'encore en français')), 'oui');
    $jarContenu = (string) file_get_contents($j);
    $dire('le cookie suit le compte', $oui(preg_match("/MESCOURS_LANGUE\tfr/", $jarContenu) === 1), 'oui');
    $csrfCompte = $jeton($r);
    $requete('deconnexion', ['jar' => $j, 'post' => ['_csrf' => $csrfCompte]]);
    $r = $requete('connexion', ['langue' => 'en', 'jar' => $j]);
    $dire('après la déconnexion, la page reste en français', $langue($r), 'fr');
    // Il change de langue depuis son compte : le cookie suit encore.
    $page = $requete('connexion', ['jar' => $j]);
    $requete('connexion', ['jar' => $j, 'suivre' => true,
        'post' => ['_csrf' => $jeton($page), 'identifiant' => $adresses[0], 'mot_de_passe' => $mdp]]);
    $compte = $requete('compte', ['jar' => $j]);
    $requete('compte/langue', ['jar' => $j, 'post' => ['_csrf' => $jeton($compte), 'langue' => 'es']]);
    $dire('depuis « Mon compte » : le cookie devient « es »',
        $oui(preg_match("/MESCOURS_LANGUE\tes/", (string) file_get_contents($j)) === 1), 'oui');

    echo "\n5. S’inscrire, c’est créer un compte dans sa langue\n";
    $j = $jar('inscription');
    $page = $requete('inscription', ['langue' => 'de', 'jar' => $j]);
    $dire('le formulaire est en allemand', $langue($page), 'de');
    $requete('inscription', ['langue' => 'de', 'jar' => $j, 'suivre' => true, 'post' => [
        '_csrf' => $jeton($page), 'nom' => 'Neue Person', 'pseudo' => 'Vis_neu', 'email' => $adresses[1],
        'mot_de_passe' => $mdp, 'mot_de_passe_confirmation' => $mdp,
    ]]);
    $nouveau = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$adresses[1]]);
    $dire('le compte existe', $oui($nouveau > 0), 'oui');
    $dire('  dans la langue de la page', (string) bd_valeur('SELECT langue FROM users WHERE id = ?', [$nouveau]), 'de');
    $matieres = array_column(bd_all('SELECT nom FROM matieres WHERE user_id = ? ORDER BY id', [$nouveau]), 'nom');
    $dire('  ses matières de départ sont en allemand', implode(' · ', $matieres),
        'Mathematik · Deutsch · Geschichte und Erdkunde · Naturwissenschaften');
    $types = array_column(bd_all('SELECT nom FROM types_evenement WHERE user_id = ? ORDER BY id', [$nouveau]), 'nom');
    $dire('  et ses types d’évènement', implode(' · ', $types), 'Unterricht · Prüfung · Hausaufgabe · Wiederholung · Sonstiges');
    $r = $requete('compte', ['jar' => $j]);
    $dire('  il arrive dans une application en allemand', $langue($r), 'de');

    echo "\n6. Un lien public, ouvert sans compte\n";
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom, langue) VALUES (?, ?, ?, ?, ?)',
        [$adresses[2], 'Vis_prop', password_hash($mdp, PASSWORD_DEFAULT), 'Propriétaire', 'fr']);
    $proprietaire = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$adresses[2]]);
    bd_run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$proprietaire, 'Algèbre linéaire']);
    $coursId = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$proprietaire]);
    $secret = bin2hex(random_bytes(16));
    bd_run('INSERT INTO liens_partage (user_id, cible_type, cible_id, jeton, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
        [$proprietaire, 'cours', $coursId, $secret]);

    $francais = (string) (require dirname(__DIR__, 2) . '/lang/fr.php')['pt.cours_sans_texte'];
    foreach (['en' => (string) (require dirname(__DIR__, 2) . '/lang/en.php')['pt.cours_sans_texte'],
              'de' => (string) (require dirname(__DIR__, 2) . '/lang/de.php')['pt.cours_sans_texte']] as $code => $phrase) {
        $r = $requete('p/' . $secret, ['langue' => $code]);
        $dire("$code : la page publique est dans la langue du visiteur", $langue($r), $code);
        $dire('  elle dit « ' . $phrase . ' »', $oui(str_contains($r['corps'], htmlspecialchars($phrase, ENT_QUOTES))), 'oui');
        $dire('  et plus le français', $oui(!str_contains($r['corps'], htmlspecialchars($francais, ENT_QUOTES))), 'oui');
        $dire('  le visiteur peut changer de langue', (string) substr_count($r['corps'], 'class="choix-langue__bouton"'), '4');
    }
    $r = $requete('p/' . $secret, ['langue' => 'en']);
    $dire('le titre de l’auteur n’est pas traduit', $oui(str_contains($r['corps'], 'Algèbre linéaire')), 'oui');
    $r = $requete('p/' . str_repeat('0', 32), ['langue' => 'es']);
    $dire('un lien mort aussi parle espagnol', $langue($r), 'es');

    echo "\n7. Ce que le navigateur lit avant toute page\n";
    $manifeste = json_decode($requete('manifeste.webmanifest', ['langue' => 'de'])['corps'], true);
    $dire('le manifeste suit le visiteur', (string) ($manifeste['lang'] ?? ''), 'de');
    $r = $requete('hors-ligne', ['langue' => 'en']);
    $dire('la page hors-ligne : « Offline »', $oui(str_contains($r['corps'], '<title>Offline')), 'oui');
} finally {
    // Ménage : ces trois comptes d'essai seuls, et ce qu'ils ont semé.
    foreach ($adresses as $adresse) {
        $qui = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$adresse]);
        if ($qui <= 0) {
            continue;
        }
        bd_run('DELETE FROM liens_partage WHERE user_id = ?', [$qui]);
        foreach (['cours', 'matieres', 'types_evenement', 'categories_budget', 'dossiers'] as $table) {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$qui]);
        }
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$qui, $adresse]);
    }
    foreach ($jars as $chemin) {
        @unlink($chemin);
    }
    $restants = 0;
    foreach ($adresses as $adresse) {
        $restants += (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$adresse]);
    }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
