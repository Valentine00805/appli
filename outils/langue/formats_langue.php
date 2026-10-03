<?php
/*
 * Les montants, les tailles et les dates : écrits à la façon de la langue du compte,
 * et lus de même quand on les saisit.
 *
 * Avant, « 1 234,50 € » et « 03/10/2026 » étaient écrits en dur, quelle que soit la
 * langue. On vérifie ici :
 *   - les valeurs exactes dans les quatre langues, au caractère près ;
 *   - que le français n'a pas bougé d'un octet (il faut que l'existant reste le même) ;
 *   - qu'une saisie se lit selon la langue (« 1,234 » est un millier en anglais, une
 *     décimale en français), et qu'un relevé de banque garde sa convention ;
 *   - le passage par les vraies pages.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

$racine = dirname(__DIR__, 2);
foreach (['Config', 'Depot', 'Session', 'Langue', 'Auth', 'Requete', 'helpers', 'ReleveCsv', 'ReleveExcel'] as $classe) {
    require_once $racine . '/src/' . $classe . '.php';
}
Config::charger([
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'mon_appli_cours',
             'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'],
    'app' => ['nom' => 'Mes Cours', 'dossier_uploads' => $racine . '/storage/uploads'],
]);

$anomalies = 0;
/** Montre les espaces insécables, qui sinon se ressemblent tous. */
$visible = static fn (string $s): string => str_replace(["\u{202F}", "\u{00A0}"], ['␣f', '␣i'], $s);
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies, $visible): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-46s %s%s\n", $bon ? '✓' : '✗', $quoi, $visible($obtenu),
        $bon ? '' : "\n       attendu : " . $visible($attendu));
};
$nombre = static fn (?float $v): string => $v === null ? 'null' : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

echo "\n1. Un montant, dans chaque langue\n";
$f = "\u{202F}";
$i = "\u{00A0}";
$attendus = [
    'fr' => ["1{$f}234,50{$i}€", "-12,50{$i}€", "0,00{$i}€", "1{$f}235"],
    'en' => ['€1,234.50', '-€12.50', '€0.00', '1,235'],
    'es' => ["1.234,50{$i}€", "-12,50{$i}€", "0,00{$i}€", '1.235'],
    'de' => ["1.234,50{$i}€", "-12,50{$i}€", "0,00{$i}€", '1.235'],
];
foreach ($attendus as $langue => [$grand, $negatif, $zero, $entier]) {
    Langue::imposer($langue);
    $dire("$langue : 1234,5", montant_lisible(1234.5), $grand);
    $dire("$langue :   -12,5", montant_lisible(-12.5), $negatif);
    $dire("$langue :   zéro", montant_lisible(0), $zero);
    $dire("$langue :   sans symbole, sans décimale", montant_lisible(1234.5, false, 0), $entier);
}

echo "\n2. Le français n’a pas bougé d’un octet\n";
Langue::imposer('fr');
// Ce que l'ancienne fonction écrivait, reconstitué à la main.
$ancien = static fn (float $v): string => number_format($v, 2, ',', "\u{202F}") . "\u{00A0}€";
foreach ([0.0, 5.0, 12.5, 999.99, 1234.5, -12.5, -1234567.891, 0.004] as $v) {
    $dire('montant ' . $v, montant_lisible($v), $v === 0.004 ? "0,00{$i}€" : $ancien($v));
}
$dire('la valeur nulle', montant_lisible(null), $ancien(0.0));
$dire('une chaîne numérique', montant_lisible('42.1'), $ancien(42.1));

echo "\n3. Une taille de fichier\n";
foreach (['fr' => ['500 o', '1,5 Ko', '5,0 Mo'], 'en' => ['500 B', '1.5 KB', '5.0 MB'],
          'es' => ['500 B', '1,5 KB', '5,0 MB'], 'de' => ['500 B', '1,5 KB', '5,0 MB']] as $langue => [$a, $b, $c]) {
    Langue::imposer($langue);
    $dire("$langue : 500 octets", taille_lisible(500), $a);
    $dire("$langue :   1536 octets", taille_lisible(1536), $b);
    $dire("$langue :   5 Mo", taille_lisible(5 * 1048576), $c);
}

echo "\n4. Une date en chiffres\n";
$jour = new DateTimeImmutable('2026-10-03 14:30:00');
foreach (['fr' => '03/10/2026', 'en' => '03/10/2026', 'es' => '03/10/2026', 'de' => '03.10.2026'] as $langue => $attendu) {
    Langue::imposer($langue);
    $dire("$langue : un objet date", date_numerique($jour), $attendu);
}
Langue::imposer('de');
$dire('de :   un horodatage', date_numerique($jour->getTimestamp()), '03.10.2026');
$dire('de :   un texte', date_numerique('2026-10-03 08:00:00'), '03.10.2026');
$dire('de :   jour et mois seuls', date(t('date.jour_mois'), $jour->getTimestamp()), '03.10.');

echo "\n4 bis. Un mois au milieu d’une phrase\n";
/*
 * Une vingtaine d'endroits écrivaient le mois avec strtolower() : « october 2026 » en anglais,
 * « oktober » en allemand, où les mois prennent une majuscule. La règle est celle de la langue.
 */
foreach (['fr' => 'octobre', 'en' => 'October', 'es' => 'octubre', 'de' => 'Oktober'] as $langue => $attendu) {
    Langue::imposer($langue);
    $dire("$langue : le dixième mois", nom_mois_en_phrase(10), $attendu);
}
foreach (['fr', 'en', 'es', 'de'] as $langue) {
    Langue::imposer($langue);
    $dire("$langue : la clé d’un mois de classeur", ReleveExcel::cleDuMois(2) . ' ' . ReleveExcel::cleDuMois(8) . ' ' . ReleveExcel::cleDuMois(12),
        'fevrier aout decembre');
}

echo "\n4 ter. Une date en toutes lettres\n";
/*
 * « 4 Oktober 2026 » : l'allemand veut un point après le jour (« 4. Oktober »), et l'espagnol
 * écrit « 4 de octubre de 2026 ». Le jour et le mois se composaient à la main, à la française.
 */
$longues = [
    'fr' => ['4 octobre 2026', '4 octobre 2026 à 14h30'],
    'en' => ['4 October 2026', '4 October 2026 at 14:30'],
    'es' => ['4 de octubre de 2026', '4 de octubre de 2026 a las 14:30'],
    'de' => ['4. Oktober 2026', '4. Oktober 2026 um 14:30'],
];
foreach ($longues as $langue => [$sans, $avec]) {
    Langue::imposer($langue);
    $dire("$langue : sans l’heure", date_fr('2026-10-04 14:30:00', false), $sans);
    $dire("$langue :   avec l’heure", date_fr('2026-10-04 14:30:00', true), $avec);
}
// L'échéance lointaine : le jour et le mois abrégé, puis l'année.
foreach (['fr' => '/^5 \S+ 2031$/u', 'en' => '/^5 \S+ 2031$/u', 'es' => '/^5 \S+ 2031$/u', 'de' => '/^5\. \S+ 2031$/u'] as $langue => $motif) {
    Langue::imposer($langue);
    $dire("$langue : une échéance en 2031, sa forme", (string) preg_match($motif, echeance_libelle('2031-03-05')), '1');
}

echo "\n5. Une saisie se lit selon la langue\n";
$cas = [
    // [langue, saisie, attendu]
    ['fr', '12,50', 12.5], ['fr', '12.50', 12.5], ['fr', '1 234,50', 1234.5],
    ["fr", "1{$f}234,50{$i}€", 1234.5], ['fr', '1.234', 1.23], ['fr', '1,234', 1.23],
    ['fr', '1.234,56', 1234.56], ['fr', '1,234.56', 1234.56], ['fr', '-12,5', -12.5],
    ['fr', '€12,50', 12.5], ['fr', '1.234.567', null], ['fr', 'abc', null], ['fr', '', null],
    ['en', '1,234', 1234.0], ['en', '1,234.50', 1234.5], ['en', '12,50', 12.5],
    ['en', '1,234,567', 1234567.0], ['en', '1.234', 1.23], ['en', '12,5', 12.5],
    ['de', '1.234', 1234.0], ['de', '1.234,50', 1234.5], ['de', '12.50', 12.5],
    ['de', '12,50', 12.5], ['de', '1.234.567', 1234567.0], ['de', '1,234', 1.23],
    ['es', '1.234', 1234.0], ['es', '1.234,50', 1234.5], ['es', '12,5', 12.5],
    ['de', '1.2,3.4', null], ['en', '1,23,456', null],
];
foreach ($cas as [$langue, $saisie, $attendu]) {
    Langue::imposer($langue);
    $dire("$langue : « " . $visible($saisie) . ' »', $nombre(montant_depuis_saisie($saisie)), $nombre($attendu));
}

echo "\n6. Un relevé de banque garde sa convention\n";
// La langue de la page change ; le fichier de la banque, non. Un montant s'arrondit au
// centime : « 1,234 » lu comme une décimale donne 1,23, comme avant.
$lire = new ReflectionMethod(ReleveCsv::class, 'montant');
foreach (['en', 'de', 'fr'] as $langue) {
    Langue::imposer($langue);
    $dire("$langue : « 1,234 » dans un relevé", $nombre($lire->invoke(null, '1,234')), '1.23');
    $dire("$langue :   « 1.234 » dans un relevé", $nombre($lire->invoke(null, '1.234')), '1.23');
    $dire("$langue :   « 1.234,56 » dans un relevé", $nombre($lire->invoke(null, '1.234,56')), '1234.56');
}

/* ------------------------------------------------------------------ par les pages */

$mdp = 'MotDePasse!2026';
$email = 'fmt-a@exemple-test.fr';
Database::run('DELETE FROM users WHERE email = ?', [$email]);
Database::run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Fmt_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai formats']);
$id = (int) Database::valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_fmt.txt';
@unlink($ck);
$appel = static function (string $chemin, ?array $post = null) use ($ck): string {
    usleep(320000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) ? $m[1] : '';
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

try {
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => $mdp]);
    $csrf = $jeton($appel('compte'));
    $aujourdhui = date('Y-m-d');

    echo "\n7. Par la page du budget, en anglais\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'en']);
    $budget = $appel('budget');
    // « 1,234.50 » tapé à l'anglaise doit être compris, et non refusé.
    $appel('budget/operations', ['_csrf' => $jeton($budget), 'libelle' => 'Laptop', 'montant' => '1,234.50',
        'date_operation' => $aujourdhui, 'sens' => 'depense']);
    $dire('« 1,234.50 » est enregistré 1234.50',
        (string) Database::valeur('SELECT montant FROM operations WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]),
        '1234.50');
    $page = $appel('budget');
    $dire('la page l’écrit « €1,234.50 »', $oui(str_contains($page, '€1,234.50')), 'oui');
    // Le mois de la période : « October 2026 », pas « october 2026 ».
    Langue::imposer('en');
    $periode = nom_mois_en_phrase((int) date('n')) . ' ' . date('Y');
    $dire('  la période : « ' . $periode . ' »', $oui(str_contains($page, $periode)), 'oui');
    $dire('  et pas en minuscule', $oui(!str_contains($page, mb_strtolower($periode))), 'oui');
    $dire('  et plus à la française', $oui(!str_contains($page, "1{$f}234,50")), 'oui');

    echo "\n8. La même page, en allemand\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'de']);
    $page = $appel('budget');
    $dire('la page l’écrit « 1.234,50 € »', $oui(str_contains($page, "1.234,50{$i}€")), 'oui');
    // Le titre de la semaine : « 28. Sept. – 4. Okt. 2026 », le point après chaque jour.
    $semaine = $appel('calendrier?vue=semaine');
    preg_match('/<h2 class="cal-titre">([^<]+)<\/h2>/u', $semaine, $titre);
    $dire('  le titre de la semaine : « ' . ($titre[1] ?? '?') . ' »',
        $oui(preg_match('/\d{1,2}\.(?: \S+)? – \d{1,2}\. \S+ \d{4}/u', $titre[1] ?? '') === 1), 'oui');
    Langue::imposer('de');
    $periode = nom_mois_en_phrase((int) date('n')) . ' ' . date('Y');
    $dire('  la période : « ' . $periode . ' »', $oui(str_contains($page, $periode)), 'oui');
    $dire('  et pas en minuscule', $oui(!str_contains($page, mb_strtolower($periode))), 'oui');

    echo "\n9. Et en français, comme avant\n";
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'fr']);
    $page = $appel('budget');
    $dire('la page l’écrit « 1 234,50 € »', $oui(str_contains($page, "1{$f}234,50{$i}€")), 'oui');
    Langue::imposer('fr');
    $periode = nom_mois_en_phrase((int) date('n')) . ' ' . date('Y');
    $dire('  la période : « ' . $periode . ' », en minuscule', $oui(str_contains($page, $periode)), 'oui');
    $dire('  et le montant en base n’a pas bougé',
        (string) Database::valeur('SELECT montant FROM operations WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]),
        '1234.50');
} finally {
    // Ménage : ce compte d'essai seul, et ce qu'il a semé.
    if ($id > 0) {
        foreach (['operations', 'categories_budget', 'cours', 'matieres', 'types_evenement'] as $table) {
            Database::run("DELETE FROM $table WHERE user_id = ?", [$id]);
        }
        Database::run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants '
        . (int) Database::valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
