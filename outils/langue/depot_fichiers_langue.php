<?php
/**
 * Déposer des fichiers dans « Mes cours » (HTTP + base) : un cours par fichier (le titre sans l'extension), ou seulement les fichiers —
 * le cours porte alors le nom entier, extension comprise (rapport.pdf), et paraît dans la liste comme un fichier qui s'ouvre d'un clic.
 * Les deux chemins d'envoi (le paquet du script, l'envoi ordinaire), le dossier d'arrivée, la liste, et les quatre langues.
 *
 * La base locale est désignée par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin) : l'essai ne dépend pas du
 * fichier de réglages de l'installation.
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

$email = 'depot-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Depot_A', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai dépôt']);
$idA = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO dossiers (user_id, nom) VALUES (?, ?)', [$idA, 'Architecture']);
$dossier = (int) bd_valeur('SELECT id FROM dossiers WHERE user_id = ?', [$idA]);

$cookie = __DIR__ . '/ck_depot.txt';
@unlink($cookie);
$fichiersTemp = [];
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $chemin, ?array $post = null) use ($cookie): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
    // Un tableau à plat : multipart quand il porte un fichier (CURLFile), sinon un envoi ordinaire.
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, $post); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$nouveauFichier = static function (string $nom, string $contenu) use (&$fichiersTemp): CURLFile {
    $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'depot-' . bin2hex(random_bytes(4)) . '.txt';
    file_put_contents($chemin, $contenu);
    $fichiersTemp[] = $chemin;
    return new CURLFile($chemin, 'text/plain', $nom);
};
$cours = static fn (string $titre): ?array => bd_all('SELECT id, titre, est_fichier, dossier_id FROM cours WHERE user_id = ? AND titre = ?', [$idA, $titre])[0] ?? null;

try {
    [$p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $email, 'mot_de_passe' => 'MotDePasse!2026']);
    [$page] = $appel('cours');
    $csrf = $jeton($page);

    echo "\n1. Le paquet du script (cours/depot-dossier)\n";
    [$json] = $appel('cours/depot-dossier', ['_csrf' => $csrf, 'dossier' => (string) $dossier, 'mode' => 'fichiers',
        'fichiers[0]' => $nouveauFichier('note.txt', 'bonjour'), 'chemins[0]' => 'note.txt']);
    $reponse = json_decode($json, true) ?: [];
    $dire('« seulement les fichiers » : un fichier créé', (string) ($reponse['cours'] ?? '?'), '1');
    $c = $cours('note.txt');
    $dire('le cours porte le nom entier, extension comprise, et le drapeau « fichier »', $c === null ? 'absent' : $c['titre'] . '|' . $c['est_fichier'], 'note.txt|1');
    $dire('il est rangé dans le dossier d\'arrivée', (string) ($c['dossier_id'] ?? ''), (string) $dossier);
    $dire('et son fichier est joint', (string) bd_valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [(int) ($c['id'] ?? 0)]), '1');

    $appel('cours/depot-dossier', ['_csrf' => $csrf, 'dossier' => (string) $dossier,
        'fichiers[0]' => $nouveauFichier('rapport.txt', 'texte'), 'chemins[0]' => 'rapport.txt']);
    $c = $cours('rapport');
    $dire('sans choix (ou « un cours par fichier ») : un vrai cours, titre sans extension', $c === null ? 'absent' : $c['titre'] . '|' . $c['est_fichier'], 'rapport|0');

    $appel('cours/depot-dossier', ['_csrf' => $csrf, 'dossier' => (string) $dossier, 'mode' => 'n\'importe quoi',
        'fichiers[0]' => $nouveauFichier('autre.txt', 'x'), 'chemins[0]' => 'autre.txt']);
    $c = $cours('autre');
    $dire('un mode inconnu ne crée pas de fichier seul : c\'est un cours', $c === null ? 'absent' : $c['titre'] . '|' . $c['est_fichier'], 'autre|0');

    echo "\n2. L'envoi ordinaire (cours/depot)\n";
    $appel('cours/depot', ['_csrf' => $csrf, 'dossier' => (string) $dossier, 'mode' => 'fichiers', 'fichiers[0]' => $nouveauFichier('plan.txt', 'plan')]);
    $c = $cours('plan.txt');
    $dire('« seulement les fichiers » par l\'envoi ordinaire aussi', $c === null ? 'absent' : $c['titre'] . '|' . $c['est_fichier'], 'plan.txt|1');

    echo "\n3. La liste du dossier\n";
    [$liste] = $appel('cours?dossier=' . $dossier);
    $dire('deux fichiers gardés comme tels s\'y lisent sous leur nom, avec leur extension', $oui(substr_count($liste, 'cours-carte--fichier') === 2 && str_contains($liste, 'note.txt') && str_contains($liste, 'plan.txt')), 'oui');
    $idFichier = (int) bd_valeur('SELECT f.id FROM fichiers f JOIN cours c ON c.id = f.cours_id WHERE c.id = ?', [(int) $cours('note.txt')['id']]);
    $dire('et s\'ouvrent directement (l\'aperçu du fichier)', $oui(str_contains($liste, 'fichiers/' . $idFichier . '/apercu')), 'oui');
    $dire('avec, dessous, de quoi l\'ouvrir comme un cours', $oui(str_contains($liste, 'cours/' . (int) $cours('note.txt')['id']) && str_contains($liste, 'Ouvrir comme un cours')), 'oui');
    $dire('le vrai cours reste une carte de cours, avec son extrait', $oui(str_contains($liste, 'cours/' . (int) $cours('rapport')['id']) && substr_count($liste, 'cours-carte__extrait') >= 1), 'oui');
    $dire('chaque carte garde de quoi être glissée vers un autre dossier (data-cours)', $oui(substr_count($liste, 'data-cours="') >= 4), 'oui');
    [$ouvert] = $appel('fichiers/' . $idFichier . '/apercu?fenetre=1');
    $dire('l\'aperçu s\'ouvre', $oui(str_contains($ouvert, 'bonjour')), 'oui');

    echo "\n4. Les quatre langues\n";
    foreach (['en' => 'Open as a course', 'es' => 'Abrir como curso', 'de' => 'Als Kurs öffnen', 'fr' => 'Ouvrir comme un cours'] as $langue => $mot) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$liste] = $appel('cours?dossier=' . $dossier);
        $dire("$langue : le libellé est traduit, sans clé brute", $oui(str_contains($liste, $mot) && !str_contains($liste, 'cours.ouvrir_comme_cours')), 'oui');
    }
    $termine = true;
} finally {
    // Les fichiers déposés vivent aussi sur le disque : on les retire avant les lignes qui les désignent.
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
