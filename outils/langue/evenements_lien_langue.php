<?php
/**
 * Un évènement lié à des cours et des dossiers (HTTP + base) : les lignes « Lier à » du formulaire (le genre, puis la liste du genre, et le « + »
 * qui en ajoute), l'enregistrement à la création, en modifiant et sur une série, ce qui est refusé (le dossier ou le cours d'un autre compte,
 * un doublon), la fiche de l'évènement, la liste du calendrier, la sauvegarde-restauration, la suppression d'un cours ou d'un dossier, et les
 * quatre langues.
 *
 * La base locale est désignée par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin).
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');

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

$emails = ['evlien-a@exemple-test.fr', 'evlien-b@exemple-test.fr'];
foreach ($emails as $i => $e) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'EvLien_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai lien']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
bd_run('INSERT INTO dossiers (user_id, nom) VALUES (?, ?), (?, ?)', [$idA, 'Semestre un', $idB, 'Dossier étranger']);
$dossier = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$idA]);
$dossierB = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$idB]);
bd_run('INSERT INTO cours (user_id, titre) VALUES (?, ?), (?, ?)', [$idA, 'Cours de réseaux', $idB, 'Cours étranger']);
$cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idA]);
$coursB = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ?', [$idB]);
bd_run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$idA, 'Cours de bases de données']);
$cours2 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Cours de bases de données']);

$cookie = __DIR__ . '/ck_evlien.txt';
@unlink($cookie);
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $chemin, ?array $post = null) use ($cookie): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . ltrim($chemin, '/'));
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
    // Des champs imbriqués (« lien[0][genre] ») passent par http_build_query ; un envoi de fichier garde le tableau tel quel.
    if ($post !== null) {
        $aPlat = array_filter($post, static fn (mixed $v): bool => is_array($v)) !== [];
        curl_setopt($h, CURLOPT_POST, true);
        curl_setopt($h, CURLOPT_POSTFIELDS, $aPlat ? http_build_query($post) : $post);
    }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$demain = date('Y-m-d', strtotime('+1 day'));
$evenement = static fn (string $titre): ?array => bd_all('SELECT id, cours_id, dossier_id FROM evenements WHERE user_id = ? AND titre = ?', [$idA, $titre])[0] ?? null;
$liens = static fn (string $titre): string => ($e = $evenement($titre)) === null ? 'absent' : ($e['cours_id'] ?? '-') . '|' . ($e['dossier_id'] ?? '-');
$champs = static fn (string $titre, array $plus = []): array => $plus + ['titre' => $titre, 'date_debut' => $GLOBALS['demain'], 'heure_debut' => '10:00', 'heure_fin' => '11:00'];
$GLOBALS['demain'] = $demain;

try {
    [$p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $emails[0], 'mot_de_passe' => 'MotDePasse!2026']);
    [$formulaire] = $appel('evenements/nouveau?fenetre=1');
    $csrf = $jeton($formulaire);

    echo "\n1. Le formulaire\n";
    $dire('d\'abord le genre (aucun, un cours, un dossier), sous « Lier à »',
        $oui(str_contains($formulaire, 'name="lien[0][genre]"') && str_contains($formulaire, 'Lier à') && str_contains($formulaire, '>Un cours<') && str_contains($formulaire, '>Un dossier<')), 'oui');
    $dire('puis une liste par genre, toutes deux cachées et désactivées tant que rien n\'est lié',
        $oui(preg_match('/name="lien\[0\]\[cours\]"[^>]*hidden disabled/', $formulaire) === 1 && preg_match('/name="lien\[0\]\[dossier\]"[^>]*hidden disabled/', $formulaire) === 1), 'oui');
    $dire('chaque liste ne propose que son genre — mes cours d\'un côté, mes dossiers de l\'autre, rien d\'un autre compte',
        $oui(preg_match('/name="lien\[0\]\[cours\]".*?<\/select>/s', $formulaire, $lc) === 1 && preg_match('/name="lien\[0\]\[dossier\]".*?<\/select>/s', $formulaire, $ld) === 1
            && str_contains($lc[0], 'value="' . $cours . '"') && str_contains($lc[0], 'value="' . $cours2 . '"') && !str_contains($lc[0], 'Semestre un')
            && str_contains($ld[0], 'value="' . $dossier . '"') && !str_contains($ld[0], 'Cours de réseaux')
            && !str_contains($formulaire, 'Cours étranger') && !str_contains($formulaire, 'Dossier étranger')), 'oui');
    $dire('un « + » pour lier d\'autres cours ou dossiers, avec son modèle de ligne',
        $oui(str_contains($formulaire, 'data-lien-ajouter') && str_contains($formulaire, 'Lier un autre cours ou dossier') && str_contains($formulaire, 'name="lien[__I__][genre]"')), 'oui');
    $dire('et une croix pour retirer une ligne', $oui(str_contains($formulaire, 'data-lien-retirer')), 'oui');
    [$prerempli] = $appel('evenements/nouveau?fenetre=1&dossier=' . $dossier);
    $dire('« ?dossier= » présélectionne le genre « dossier » et le dossier, et montre sa liste',
        $oui(preg_match('/<option value="dossier" selected>/', $prerempli) === 1 && preg_match('/<option value="' . $dossier . '" selected>/', $prerempli) === 1
            && preg_match('/name="lien\[0\]\[dossier\]"[^>]*hidden/', $prerempli) !== 1 && preg_match('/name="lien\[0\]\[cours\]"[^>]*hidden disabled/', $prerempli) === 1), 'oui');
    echo "\n2. Enregistrer\n";
    $appel('evenements/nouveau', $champs('Révisions du semestre', ['_csrf' => $csrf, 'lien' => [['genre' => 'dossier', 'dossier' => (string) $dossier]]]));
    $dire('lié à un dossier : le dossier est gardé, pas de cours', $liens('Révisions du semestre'), '-|' . $dossier);
    $appel('evenements/nouveau', $champs('Contrôle de réseaux', ['_csrf' => $csrf, 'lien' => [['genre' => 'cours', 'cours' => (string) $cours]]]));
    $dire('lié à un cours : le cours est gardé, pas de dossier', $liens('Contrôle de réseaux'), $cours . '|-');
    $appel('evenements/nouveau', $champs('Sans lien', ['_csrf' => $csrf, 'lien' => [['genre' => '']]]));
    $dire('sans lien : ni l\'un ni l\'autre', $liens('Sans lien'), '-|-');
    $appel('evenements/nouveau', $champs('Dossier d\'un autre', ['_csrf' => $csrf, 'lien' => [['genre' => 'dossier', 'dossier' => (string) $dossierB]]]));
    $dire('le dossier d\'un autre compte est ignoré', $liens('Dossier d\'un autre'), '-|-');
    $appel('evenements/nouveau', $champs('Cours d\'un autre', ['_csrf' => $csrf, 'lien' => [['genre' => 'cours', 'cours' => (string) $coursB]]]));
    $dire('le cours d\'un autre compte aussi', $liens('Cours d\'un autre'), '-|-');
    $appel('evenements/nouveau', $champs('Ancien formulaire', ['_csrf' => $csrf, 'cours_id' => (string) $cours]));
    $dire('l\'ancien champ « cours_id » reste lu', $liens('Ancien formulaire'), $cours . '|-');
    $appel('evenements/nouveau', $champs('Genre sans liste', ['_csrf' => $csrf, 'lien' => [['genre' => 'cours', 'cours' => '', 'dossier' => (string) $dossier]]]));
    $dire('le genre choisi décide : « un cours » sans cours ne prend pas le dossier de l\'autre liste', $liens('Genre sans liste'), '-|-');
    $appel('evenements/nouveau', $champs('Série de révisions', ['_csrf' => $csrf, 'lien' => [['genre' => 'dossier', 'dossier' => (string) $dossier]], 'repetition' => 'semaine', 'fin_type' => 'nombre', 'repeter_nombre' => '3']));
    $dire('une série : chaque occurrence garde le dossier', (string) bd_valeur('SELECT COUNT(*) FROM evenements WHERE user_id = ? AND titre = ? AND dossier_id = ?', [$idA, 'Série de révisions', $dossier]), '3');

    echo "\n2b. Plusieurs liens\n";
    $nbLiens = static fn (string $titre): string => (string) bd_valeur('SELECT COUNT(*) FROM evenement_liens l JOIN evenements e ON e.id = l.evenement_id WHERE e.user_id = ? AND e.titre = ?', [$idA, $titre]);
    $appel('evenements/nouveau', $champs('Plusieurs liens', ['_csrf' => $csrf, 'lien' => [
        ['genre' => 'cours', 'cours' => (string) $cours],
        ['genre' => 'dossier', 'dossier' => (string) $dossier],
        ['genre' => 'cours', 'cours' => (string) $cours2],
        ['genre' => 'cours', 'cours' => (string) $cours],            // un doublon
        ['genre' => 'dossier', 'dossier' => (string) $dossierB],     // le dossier d'un autre compte
        ['genre' => 'cours', 'cours' => (string) $coursB],           // le cours d'un autre compte
    ]]));
    $dire('deux cours et un dossier sont gardés, sans doublon ni lien d\'un autre compte', $nbLiens('Plusieurs liens'), '3');
    $dire('les colonnes de l\'évènement gardent le premier cours et le premier dossier', $liens('Plusieurs liens'), $cours . '|' . $dossier);
    $idPlusieurs = (int) $evenement('Plusieurs liens')['id'];
    [$fichePlus] = $appel('evenements/' . $idPlusieurs . '?fenetre=1');
    $dire('la fiche les montre tous : les deux cours et le dossier',
        $oui(str_contains($fichePlus, 'Cours de réseaux') && str_contains($fichePlus, 'Cours de bases de données') && str_contains($fichePlus, 'Semestre un')), 'oui');
    [$editionPlus] = $appel('evenements/' . $idPlusieurs . '/modifier?fenetre=1');
    $dire('le formulaire de modification a une ligne par lien, chacune sélectionnée',
        $oui(preg_match_all('/data-lien-ligne/', $editionPlus) === 4   // trois lignes et le modèle
            && preg_match('/name="lien\[0\]\[genre\]".*?<option value="cours" selected>/s', $editionPlus) === 1
            && preg_match('/<option value="' . $cours2 . '" selected>/', $editionPlus) === 1
            && preg_match('/<option value="' . $dossier . '" selected>/', $editionPlus) === 1), 'oui');
    $appel('evenements/nouveau', $champs('Série multiple', ['_csrf' => $csrf, 'lien' => [
        ['genre' => 'cours', 'cours' => (string) $cours2], ['genre' => 'dossier', 'dossier' => (string) $dossier]],
        'repetition' => 'semaine', 'fin_type' => 'nombre', 'repeter_nombre' => '3']));
    $dire('une série : chaque occurrence garde ses deux liens', $nbLiens('Série multiple'), '6');
    $appel('evenements/nouveau', $champs('À modifier', ['_csrf' => $csrf, 'lien' => [
        ['genre' => 'cours', 'cours' => (string) $cours], ['genre' => 'dossier', 'dossier' => (string) $dossier]]]));
    $idModif = (int) $evenement('À modifier')['id'];
    $appel('evenements/' . $idModif . '/modifier', $champs('À modifier', ['_csrf' => $csrf, 'lien' => [['genre' => 'cours', 'cours' => (string) $cours2]]]));
    $dire('en modifiant, les liens sont remplacés (un seul reste)', $nbLiens('À modifier') . '|' . $liens('À modifier'), '1|' . $cours2 . '|-');
    $appel('evenements/' . $idModif . '/modifier', $champs('À modifier', ['_csrf' => $csrf, 'lien' => [['genre' => '']]]));
    $dire('puis plus aucun', $nbLiens('À modifier') . '|' . $liens('À modifier'), '0|-|-');
    $appel('evenements/' . $idModif . '/modifier', $champs('À modifier', ['_csrf' => $csrf]));
    $dire('un envoi sans aucune ligne ne lie rien non plus', $nbLiens('À modifier') . '|' . $liens('À modifier'), '0|-|-');

    echo "\n3. Modifier\n";
    $idEvt = (int) $evenement('Sans lien')['id'];
    $appel('evenements/' . $idEvt . '/modifier', $champs('Sans lien', ['_csrf' => $csrf, 'lien' => [['genre' => 'dossier', 'dossier' => (string) $dossier]]]));
    $dire('on lui donne un dossier', $liens('Sans lien'), '-|' . $dossier);
    $appel('evenements/' . $idEvt . '/modifier', $champs('Sans lien', ['_csrf' => $csrf, 'lien' => [['genre' => 'cours', 'cours' => (string) $cours]]]));
    $dire('puis un cours : le dossier s\'efface', $liens('Sans lien'), $cours . '|-');
    [$edition] = $appel('evenements/' . $idEvt . '/modifier?fenetre=1');
    $dire('le formulaire de modification sélectionne le cours lié', $oui(preg_match('/<option value="cours" selected>/', $edition) === 1 && preg_match('/<option value="' . $cours . '" selected>/', $edition) === 1), 'oui');
    $appel('evenements/' . $idEvt . '/modifier', $champs('Sans lien', ['_csrf' => $csrf, 'lien' => [['genre' => '']]]));
    $dire('puis plus rien', $liens('Sans lien'), '-|-');
    $idDossier = (int) $evenement('Révisions du semestre')['id'];
    [$edition] = $appel('evenements/' . $idDossier . '/modifier?fenetre=1');
    $dire('l\'évènement lié à un dossier le montre sélectionné', $oui(preg_match('/<option value="dossier" selected>/', $edition) === 1 && preg_match('/<option value="' . $dossier . '" selected>/', $edition) === 1 && !preg_match('/<option value="cours" selected>/', $edition)), 'oui');

    echo "\n4. Ce qu'on voit\n";
    [$fiche] = $appel('evenements/' . $idDossier . '?fenetre=1');
    $dire('la fiche de l\'évènement : « Dossier lié », avec un lien vers la liste des cours de ce dossier',
        $oui(str_contains($fiche, 'Dossier lié') && str_contains($fiche, 'Semestre un') && str_contains($fiche, 'cours?dossier=' . $dossier)), 'oui');
    [$ficheCours] = $appel('evenements/' . (int) $evenement('Contrôle de réseaux')['id'] . '?fenetre=1');
    $dire('celle d\'un évènement lié à un cours n\'a pas de ligne « Dossier lié »', $oui(!str_contains($ficheCours, 'Dossier lié') && str_contains($ficheCours, 'Cours de réseaux')), 'oui');
    [$liste] = $appel('calendrier?vue=liste&date=' . $demain);
    $dire('la liste du calendrier montre le dossier (📁) et un bouton pour l\'ouvrir', $oui(str_contains($liste, '📁 Semestre un') && str_contains($liste, 'cours?dossier=' . $dossier)), 'oui');
    $dire('et le cours (📘) comme avant', $oui(str_contains($liste, '📘 Cours de réseaux')), 'oui');

    echo "\n5. Sauvegarde, restauration, suppression du dossier\n";
    [$archive] = $appel('compte/sauvegarde/export');
    $zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evlien-' . bin2hex(random_bytes(4)) . '.zip';
    file_put_contents($zip, $archive);
    [$pageSv] = $appel('compte/sauvegarde');
    $appel('compte/sauvegarde/restaurer', ['_csrf' => $jeton($pageSv), 'confirmation' => '1', 'archive' => new CURLFile($zip, 'application/zip', 'sauvegarde.zip')]);
    @unlink($zip);
    $dire('sauvegardé puis restauré, l\'évènement garde son dossier (par son nom)',
        (string) bd_valeur('SELECT d.nom FROM evenements e JOIN dossiers d ON d.id = e.dossier_id WHERE e.user_id = ? AND e.titre = ?', [$idA, 'Révisions du semestre']), 'Semestre un');
    $dire('sauvegardé puis restauré, « Plusieurs liens » garde ses trois liens', $nbLiens('Plusieurs liens'), '3');
    $dire('et les colonnes suivent (premier cours, premier dossier)', $oui((bool) bd_valeur('SELECT COUNT(*) FROM evenements WHERE user_id = ? AND titre = ? AND cours_id IS NOT NULL AND dossier_id IS NOT NULL', [$idA, 'Plusieurs liens'])), 'oui');
    bd_run('DELETE FROM cours WHERE user_id = ? AND titre = ?', [$idA, 'Cours de bases de données']);
    $dire('supprimer un cours retire son lien seulement', $nbLiens('Plusieurs liens'), '2');
    $appel('dossiers/' . (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$idA]) . '/supprimer', ['_csrf' => $csrf]);
    $dire('et celui du dossier supprimé s\'en va aussi', $nbLiens('Plusieurs liens'), '1');
    $dire('supprimer le dossier retire le lien, l\'évènement reste', (string) bd_valeur('SELECT CONCAT(COUNT(*), \'|\', COALESCE(MAX(dossier_id), \'-\')) FROM evenements WHERE user_id = ? AND titre = ?', [$idA, 'Révisions du semestre']), '1|-');

    echo "\n6. Les quatre langues\n";
    bd_run('INSERT INTO dossiers (user_id, nom) VALUES (?, ?)', [$idA, 'Autre dossier']);
    foreach (['en' => ['Link to', 'A folder'], 'es' => ['Vincular a', 'Una carpeta'], 'de' => ['Verknüpfen mit', 'Ein Ordner'],
        'fr' => ['Lier à', 'Un dossier']] as $langue => $mots) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$vue] = $appel('evenements/nouveau?fenetre=1');
        $dire("$langue : le formulaire est traduit, sans clé brute", $oui(str_contains($vue, $mots[0]) && str_contains($vue, '>' . $mots[1] . '<') && !preg_match('/\bevtf\.(lien_[a-z_]+|choisir_[a-z]+)/', $vue)), 'oui');
    }
    $termine = true;
} finally {
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    @unlink($cookie);
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
