<?php
/**
 * Le chargement des réglages (Config), sans base ni serveur web : où l'application cherche son fichier de réglages, comment il se
 * fond dans les valeurs par défaut, et ce qu'il advient d'un fichier mal écrit. Né d'une panne réelle : un « / » oublié dans
 * Config::fichierLocal faisait chercher « …appliconfig/parametres.php » — le fichier de réglages n'était jamais lu en ligne.
 */
declare(strict_types=1);

$racine = dirname(__DIR__, 2);
require_once $racine . '/src/Config.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';
$norm = static fn (string $chemin): string => str_replace('\\', '/', $chemin);

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cfg_essai_' . getmypid();
$journal = $base . DIRECTORY_SEPARATOR . 'journal.txt';
$nettoyer = static function () use ($base): void {
    if (!is_dir($base) || !str_contains($base, 'cfg_essai_')) { return; }   // jamais ailleurs que dans le dossier d'essai
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $element) { $element->isDir() ? @rmdir($element->getPathname()) : @unlink($element->getPathname()); }
    @rmdir($base);
};

try {
    mkdir($base . '/public_html/config', 0777, true);
    $app = $base . '/public_html';
    ini_set('error_log', $journal);

    echo "\n1. Où chercher le fichier de réglages\n";
    $f = $norm(Config::fichierLocal($app));
    $dire('sans fichier hors du dossier publié : config/parametres.php, avec son « / »', $f, $norm($app) . '/config/parametres.php');
    $dire('le chemin se forme avec un séparateur entre le dossier et « config »', $oui(!str_contains($f, 'public_htmlconfig')), 'oui');
    file_put_contents($base . '/mes-cours-parametres.php', '<?php return [];');
    $f = $norm(Config::fichierLocal($app));
    $dire('avec « mes-cours-parametres.php » dans le dossier parent : c\'est lui', $f, $norm($base) . '/mes-cours-parametres.php');
    unlink($base . '/mes-cours-parametres.php');

    // Application dans un sous-dossier (public_html/appli) : le parent est public_html, publié ; le fichier se range un cran plus haut.
    $sous = $base . '/site/public_html/appli';
    mkdir($sous . '/config', 0777, true);
    $f = $norm(Config::fichierLocal($sous));
    $dire('application dans un sous-dossier, sans fichier hors public : config/parametres.php', $f, $norm($sous) . '/config/parametres.php');
    file_put_contents($base . '/site/mes-cours-parametres.php', '<?php return [];');
    $dire('… avec le fichier au-dessus de public_html : il est trouvé (deux niveaux plus haut)', $norm(Config::fichierLocal($sous)), $norm($base) . '/site/mes-cours-parametres.php');
    file_put_contents($base . '/site/public_html/mes-cours-parametres.php', '<?php return [];');
    $dire('s\'il y en a un aussi dans le parent immédiat, le plus proche l\'emporte', $norm(Config::fichierLocal($sous)), $norm($base) . '/site/public_html/mes-cours-parametres.php');
    unlink($base . '/site/public_html/mes-cours-parametres.php');
    unlink($base . '/site/mes-cours-parametres.php');
    $profond = $base . '/a/b/c/d/appli';
    mkdir($profond, 0777, true);
    file_put_contents($base . '/mes-cours-parametres.php', '<?php return [];');
    $dire('on ne remonte pas plus de trois dossiers (un fichier plus loin est ignoré)', $norm(Config::fichierLocal($profond)), $norm($profond) . '/config/parametres.php');
    unlink($base . '/mes-cours-parametres.php');
    echo "\n2. Le fichier se fond dans les valeurs par défaut\n";
    file_put_contents($app . '/config/parametres.php',
        '<?php return ["db" => ["host" => "localhost", "name" => "b", "user" => "u", "pass" => "p"], "app" => ["code_inscription" => "abc"]];');
    Config::charger(['db' => ['port' => 3306, 'charset' => 'utf8mb4'], 'app' => ['nom' => 'Mes Cours', 'code_inscription' => '']], Config::fichierLocal($app));
    $dire('le fichier apporte hôte, base, utilisateur, mot de passe', implode(',', [Config::get('db', 'host'), Config::get('db', 'name'), Config::get('db', 'user'), Config::get('db', 'pass')]), 'localhost,b,u,p');
    $dire('les défauts gardent le port et le jeu de caractères que le fichier ne dit pas', Config::get('db', 'port') . ' ' . Config::get('db', 'charset'), '3306 utf8mb4');
    $dire('le code d\'inscription du fichier l\'emporte sur le défaut vide', (string) Config::get('app', 'code_inscription'), 'abc');
    $dire('une clé que le fichier ne cite pas garde son défaut', (string) Config::get('app', 'nom'), 'Mes Cours');

    echo "\n3. Un fichier mal écrit ne passe pas inaperçu\n";
    file_put_contents($app . '/config/parametres.php', '<?php $c = ["app" => ["code_inscription" => "abc"]];');
    @unlink($journal);
    Config::charger(['app' => ['code_inscription' => '']], Config::fichierLocal($app));
    $dire('sans « return », le fichier est ignoré (les défauts restent)', (string) Config::get('app', 'code_inscription'), '');
    $dire('… et le journal d\'erreurs le dit', $oui(str_contains((string) @file_get_contents($journal), 'ne renvoie pas un tableau')), 'oui');
    @unlink($journal);
    Config::charger(['app' => ['code_inscription' => 'defaut']], $app . '/config/absent.php');
    $dire('un fichier absent ne fait rien de plus que les défauts (et ne se plaint pas)', Config::get('app', 'code_inscription') . '|' . (is_file($journal) ? 'journal' : 'rien'), 'defaut|rien');

    echo "\n4. index.php n'embarque aucun identifiant de base\n";
    $index = (string) file_get_contents($racine . '/index.php');
    $dire('pas d\'hôte, d\'utilisateur ni de mot de passe de base dans les défauts', $oui(!preg_match("/'(user|pass|name)'\s*=>\s*'[^']/", substr($index, 0, (int) strpos($index, "'app' =>")))), 'oui');
    $dire('mais bien le port et le jeu de caractères (sans eux, « port=0;charset= » fait échouer la connexion)',
        $oui(str_contains($index, "'port'    => 3306") && str_contains($index, "'charset' => 'utf8mb4'")), 'oui');
    $dire('le chemin du fichier passe par Config::fichierLocal', $oui(str_contains($index, 'Config::fichierLocal(__DIR__)')), 'oui');
    $termine = true;
} finally {
    $nettoyer();
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)') . ' · dossier d’essai retiré : ' . (is_dir($base) ? 'NON' : 'oui') . "\n";
}
