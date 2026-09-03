<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

require __DIR__ . '/Config.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Session.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/LimiteurConnexion.php';
require __DIR__ . '/Sauvegarde.php';
require __DIR__ . '/Fichiers.php';
require __DIR__ . '/ApercuDocument.php';
require __DIR__ . '/EditionDocument.php';
require __DIR__ . '/ReleveCsv.php';
require __DIR__ . '/ClasseurXlsx.php';
require __DIR__ . '/SuggestionBudget.php';
require __DIR__ . '/ClasseurLecteur.php';
require __DIR__ . '/ReleveExcel.php';
require __DIR__ . '/Requete.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Vue.php';

Config::charger(__DIR__ . '/../config/config.php');

/* --- Où l'application est installée, et ce qui lui est demandé --- */
define('BASE_PATH_BRUT', Requete::baseBrute());
define('BASE_URL', Requete::base());
define('ROUTE', Requete::route());
define('METHODE', Requete::methode());

Session::demarrer();
