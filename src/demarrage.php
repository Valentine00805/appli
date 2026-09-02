<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

$racine = dirname(__DIR__);

require $racine . '/src/Config.php';
require $racine . '/src/Database.php';
require $racine . '/src/Session.php';
require $racine . '/src/Auth.php';
require $racine . '/src/LimiteurConnexion.php';
require $racine . '/src/Sauvegarde.php';
require $racine . '/src/Fichiers.php';
require $racine . '/src/ApercuDocument.php';
require $racine . '/src/EditionDocument.php';
require $racine . '/src/ReleveCsv.php';
require $racine . '/src/ClasseurXlsx.php';
require $racine . '/src/SuggestionBudget.php';
require $racine . '/src/ClasseurLecteur.php';
require $racine . '/src/ReleveExcel.php';
require $racine . '/src/helpers.php';
require $racine . '/src/Vue.php';

Config::charger($racine . '/config/config.php');

/* --- Chemin de base de l'application (gère l'installation dans un sous-dossier) --- */
$dossierScript = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$dossierScript = rtrim($dossierScript, '/');
if ($dossierScript === '.' ) {
    $dossierScript = '';
}
define('BASE_PATH_BRUT', $dossierScript);
define(
    'BASE_URL',
    $dossierScript === ''
        ? ''
        : '/' . implode('/', array_map('rawurlencode', explode('/', trim($dossierScript, '/'))))
);

/* --- Route demandée --- */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
if (BASE_PATH_BRUT !== '' && str_starts_with($uri, BASE_PATH_BRUT)) {
    $uri = substr($uri, strlen(BASE_PATH_BRUT));
}
define('ROUTE', trim($uri, '/'));
$methode = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
// HEAD est traité comme GET : navigateurs et outils l'utilisent pour tester une page.
define('METHODE', $methode === 'HEAD' ? 'GET' : $methode);

Session::demarrer();
