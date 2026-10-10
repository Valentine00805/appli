<?php
/**
 * Une matière affiliée à un dossier (HTTP + base) : la choisir à la création ou en modifiant, la reprise par un sous-dossier, le don aux cours
 * de la branche qui n'en ont pas (jamais d'écrasement), et les cours qui entrent dans le dossier — par le formulaire, un dépôt de fichiers,
 * un import de dossier, un glisser-déposer, un changement de dossier — qui en prennent la matière quand ils n'en ont pas. Les quatre langues.
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

$email = 'dosmat-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'DosMat_A', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai dossiers']);
$idA = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?), (?, ?, ?)', [$idA, 'Informatique', '#0ea5e9', $idA, 'Mathématiques', '#dc2626']);
$mInfo = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$idA, 'Informatique']);
$mMaths = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$idA, 'Mathématiques']);

$cookie = __DIR__ . '/ck_dosmat.txt';
@unlink($cookie);
$fichiersTemp = [];
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $chemin, ?array $post = null) use ($cookie): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . ltrim($chemin, '/'));
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, $post); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$nouveauFichier = static function (string $nom, string $contenu) use (&$fichiersTemp): CURLFile {
    $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dosmat-' . bin2hex(random_bytes(4)) . '.txt';
    file_put_contents($chemin, $contenu);
    $fichiersTemp[] = $chemin;
    return new CURLFile($chemin, 'text/plain', $nom);
};
$dossier = static fn (string $nom): ?array => bd_all('SELECT id, matiere_id, parent_id FROM dossiers WHERE user_id = ? AND nom = ?', [$idA, $nom])[0] ?? null;
$matiereDossier = static fn (string $nom): string => (string) (($d = $dossier($nom)) === null ? 'absent' : ($d['matiere_id'] ?? '-'));
$cours = static fn (string $titre): ?array => bd_all('SELECT id, matiere_id, dossier_id FROM cours WHERE user_id = ? AND titre = ?', [$idA, $titre])[0] ?? null;
$matiereCours = static fn (string $titre): string => (string) (($c = $cours($titre)) === null ? 'absent' : ($c['matiere_id'] ?? '-'));
$nouveauCours = static function (string $titre, ?int $dossierId, ?int $matiere) use ($idA): void {
    bd_run('INSERT INTO cours (user_id, matiere_id, dossier_id, titre) VALUES (?, ?, ?, ?)', [$idA, $matiere, $dossierId, $titre]);
};

try {
    [$p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    [$page] = $appel('organisation/dossiers');
    $csrf = $jeton($page);
    $dire('la page « Dossiers » propose la matière à la création', $oui(str_contains($page, 'name="matiere_id"') && str_contains($page, 'Informatique')), 'oui');

    echo "\n1. Créer un dossier avec une matière\n";
    $appel('dossiers', ['_csrf' => $csrf, 'nom' => 'Info', 'matiere_id' => (string) $mInfo, 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('le dossier garde la matière choisie', $matiereDossier('Info'), (string) $mInfo);
    $appel('dossiers', ['_csrf' => $csrf, 'nom' => 'Sous-info', 'parent_id' => (string) $dossier('Info')['id'], 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('un sous-dossier créé sans choix reprend la matière de son parent', $matiereDossier('Sous-info'), (string) $mInfo);
    $appel('dossiers', ['_csrf' => $csrf, 'nom' => 'Algèbre', 'parent_id' => (string) $dossier('Info')['id'], 'matiere_id' => (string) $mMaths, 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('mais sa propre matière, choisie, l\'emporte', $matiereDossier('Algèbre'), (string) $mMaths);
    $appel('dossiers', ['_csrf' => $csrf, 'nom' => 'Libre', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('un dossier sans matière n\'en a pas', $matiereDossier('Libre'), '-');
    $appel('dossiers', ['_csrf' => $csrf, 'nom' => 'Piégé', 'matiere_id' => '99999999', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('une matière qui n\'existe pas (ou d\'un autre compte) est ignorée', $matiereDossier('Piégé'), '-');

    echo "\n2. Donner la matière aux cours du dossier qui n'en ont pas\n";
    $nouveauCours('c1 sans matière', (int) $dossier('Info')['id'], null);
    $nouveauCours('c2 en maths', (int) $dossier('Info')['id'], $mMaths);
    $nouveauCours('c3 dans le sous-dossier', (int) $dossier('Sous-info')['id'], null);
    $nouveauCours('c4 libre', (int) $dossier('Libre')['id'], null);
    [$retour] = $appel('dossiers/' . $dossier('Libre')['id'] . '/modifier', ['_csrf' => $csrf, 'nom' => 'Libre', 'parent_id' => '', 'matiere_id' => (string) $mMaths, 'appliquer_matiere' => '1', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('Libre reçoit les maths, et son cours sans matière aussi', $matiereDossier('Libre') . '|' . $matiereCours('c4 libre'), $mMaths . '|' . $mMaths);
    $dire('le message dit combien de cours l\'ont reçue', $oui(str_contains($retour, '1 cours a reçu la matière du dossier')), 'oui');
    $appel('dossiers/' . $dossier('Info')['id'] . '/modifier', ['_csrf' => $csrf, 'nom' => 'Info', 'parent_id' => '', 'matiere_id' => (string) $mInfo, 'appliquer_matiere' => '1', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('Info donne l\'informatique à ses cours sans matière (et à ceux de sa branche)', $matiereCours('c1 sans matière') . '|' . $matiereCours('c3 dans le sous-dossier'), $mInfo . '|' . $mInfo);
    $dire('un cours qui avait déjà sa matière la garde', $matiereCours('c2 en maths'), (string) $mMaths);
    $dire('et un sous-dossier qui avait déjà la sienne aussi', $matiereDossier('Algèbre'), (string) $mMaths);

    echo "\n3. Ce qui ne touche pas à la matière\n";
    $nouveauCours('c5 ajouté après', (int) $dossier('Libre')['id'], null);
    $appel('dossiers/' . $dossier('Libre')['id'] . '/modifier', ['_csrf' => $csrf, 'nom' => 'Libre', 'parent_id' => '', 'matiere_id' => (string) $mMaths, 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('sans « appliquer », un cours déjà là sans matière n\'en reçoit pas', $matiereCours('c5 ajouté après'), '-');
    $appel('dossiers/' . $dossier('Libre')['id'] . '/modifier', ['_csrf' => $csrf, 'nom' => 'Libre renommé', 'parent_id' => '', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('l\'ancien formulaire (sans champ matière) renomme sans effacer la matière', $matiereDossier('Libre renommé'), (string) $mMaths);
    $appel('dossiers/' . $dossier('Libre renommé')['id'] . '/modifier', ['_csrf' => $csrf, 'nom' => 'Libre renommé', 'parent_id' => '', 'matiere_id' => '', 'icone' => '📁', 'couleur' => '#4f46e5']);
    $dire('choisir « aucune matière » la retire du dossier', $matiereDossier('Libre renommé'), '-');
    $dire('sans toucher aux cours', $matiereCours('c4 libre'), (string) $mMaths);

    echo "\n4. Un cours qui entre dans le dossier en prend la matière\n";
    $idInfo = (string) $dossier('Info')['id'];
    $appel('cours/depot', ['_csrf' => $csrf, 'dossier' => $idInfo, 'fichiers[0]' => $nouveauFichier('depose.txt', 'a')]);
    $dire('un fichier déposé (un cours par fichier)', $matiereCours('depose'), (string) $mInfo);
    $appel('cours/depot', ['_csrf' => $csrf, 'dossier' => $idInfo, 'mode' => 'fichiers', 'fichiers[0]' => $nouveauFichier('seul.txt', 'b')]);
    $dire('un fichier gardé seul', $matiereCours('seul.txt'), (string) $mInfo);
    $appel('cours/depot-dossier', ['_csrf' => $csrf, 'dossier' => $idInfo, 'fichiers[0]' => $nouveauFichier('importe.txt', 'c'), 'chemins[0]' => 'Nouveau/importe.txt']);
    $dire('un dossier importé : son sous-dossier reprend la matière, et son cours aussi', $matiereDossier('Nouveau') . '|' . $matiereCours('importe'), $mInfo . '|' . $mInfo);
    $nouveauCours('c6 glissé', null, null);
    $nouveauCours('c7 glissé en maths', null, $mMaths);
    $appel('cours/ranger', ['_csrf' => $csrf, 'cours' => (string) $cours('c6 glissé')['id'], 'dossier' => $idInfo]);
    $appel('cours/ranger', ['_csrf' => $csrf, 'cours' => (string) $cours('c7 glissé en maths')['id'], 'dossier' => $idInfo]);
    $dire('glissé dans le dossier : le cours sans matière la reçoit, l\'autre garde la sienne', $matiereCours('c6 glissé') . '|' . $matiereCours('c7 glissé en maths'), $mInfo . '|' . $mMaths);
    $appel('cours/nouveau', ['_csrf' => $csrf, 'titre' => 'c8 formulaire', 'dossier_id' => $idInfo, 'contenu' => '']);
    $dire('le formulaire d\'un nouveau cours, sans matière choisie', $matiereCours('c8 formulaire'), (string) $mInfo);
    $appel('cours/nouveau', ['_csrf' => $csrf, 'titre' => 'c9 formulaire maths', 'dossier_id' => $idInfo, 'matiere_id' => (string) $mMaths, 'contenu' => '']);
    $dire('… et avec une matière choisie, c\'est elle', $matiereCours('c9 formulaire maths'), (string) $mMaths);
    $nouveauCours('c10 à déplacer', null, null);
    $appel('cours/' . $cours('c10 à déplacer')['id'] . '/modifier', ['_csrf' => $csrf, 'titre' => 'c10 à déplacer', 'dossier_id' => $idInfo, 'matiere_id' => '', 'contenu' => '']);
    $dire('un cours qui change de dossier (sans matière) prend celle du nouveau', $matiereCours('c10 à déplacer'), (string) $mInfo);
    $appel('cours/' . $cours('c10 à déplacer')['id'] . '/modifier', ['_csrf' => $csrf, 'titre' => 'c10 à déplacer', 'dossier_id' => '', 'matiere_id' => (string) $mInfo, 'contenu' => '']);   // le formulaire rouvre avec la matière du cours
    $dire('sorti du dossier (la matière du formulaire inchangée), le cours la garde', $matiereCours('c10 à déplacer'), (string) $mInfo);

    echo "\n5. L'affichage\n";
    [$page] = $appel('organisation/dossiers');
    $dire('la page « Dossiers » montre la matière du dossier (pastille) et la propose en modification', $oui(str_contains($page, 'Mathématiques') && str_contains($page, 'name="appliquer_matiere"') && preg_match('/<option value="' . $mMaths . '" selected>/', $page) === 1), 'oui');
    [$liste] = $appel('cours');
    $dire('le menu de la colonne liste bien les matières du compte, pas seulement « Aucune matière »', $oui(preg_match('/id="mat-colonne-\d+".*?<\/select>/s', $liste, $sel) === 1 && str_contains($sel[0], 'Informatique') && str_contains($sel[0], 'Mathématiques')), 'oui');
    $dire('la colonne des cours propose aussi la matière (renommage et création rapide)', $oui(substr_count($liste, 'name="matiere_id"') >= 2 && str_contains($liste, 'name="appliquer_matiere" value="1"')), 'oui');
    // La sauvegarde et sa restauration gardent la matière des dossiers (les numéros changent, pas le lien).
    [$archive] = $appel('compte/sauvegarde/export');
    $zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dosmat-' . bin2hex(random_bytes(4)) . '.zip';
    file_put_contents($zip, $archive);
    $fichiersTemp[] = $zip;
    [$pageSv] = $appel('compte/sauvegarde');
    $appel('compte/sauvegarde/restaurer', ['_csrf' => $jeton($pageSv), 'confirmation' => '1', 'archive' => new CURLFile($zip, 'application/zip', 'sauvegarde.zip')]);
    $nomMatiere = static fn (string $nom): string => (string) (bd_valeur('SELECT m.nom FROM dossiers d JOIN matieres m ON m.id = d.matiere_id WHERE d.user_id = ? AND d.nom = ?', [$idA, $nom]) ?? '-');
    $dire('sauvegardé puis restauré, un dossier garde sa matière (par son nom)', $nomMatiere('Info') . '|' . $nomMatiere('Algèbre'), 'Informatique|Mathématiques');
    $mMaths = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$idA, 'Mathématiques']);
    bd_run('DELETE FROM matieres WHERE id = ?', [$mMaths]);
    $dire('supprimer une matière la retire des dossiers', $matiereDossier('Algèbre'), '-');

    echo "\n6. Les quatre langues\n";
    foreach (['en' => ['Subject', 'No subject'], 'es' => ['Asignatura', 'Ninguna asignatura'], 'de' => ['Fach', 'Kein Fach'], 'fr' => ['Matière', 'Aucune matière']] as $langue => $mots) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$vue] = $appel('organisation/dossiers');
        $dire("$langue : les libellés sont traduits, sans clé brute", $oui(str_contains($vue, '>' . $mots[0] . '<') && str_contains($vue, $mots[1]) && !preg_match('/\bdos\.(matiere|appliquer)[a-z_.]*/', $vue)), 'oui');
    }
    $termine = true;
} finally {
    foreach (bd_all('SELECT f.nom_stocke FROM fichiers f JOIN cours c ON c.id = f.cours_id WHERE c.user_id = ?', [$idA]) as $f) {
        @unlink($racine . '/storage/uploads/' . basename((string) $f['nom_stocke']));
    }
    foreach ($fichiersTemp as $f) { @unlink($f); }
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    @unlink($cookie);
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
