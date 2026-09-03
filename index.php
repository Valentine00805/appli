<?php
declare(strict_types=1);

/**
 * Point d'entrée unique de l'application « Mes Cours ».
 * Toutes les URL passent par ce fichier (voir .htaccess).
 *
 * Le chargement tient ici plutôt que dans un fichier d'amorçage à part.
 * Norton met en quarantaine, sous le verdict IDP.Generic, le fichier que le
 * serveur se met à exécuter dès qu'il vient d'apparaître : il en a supprimé
 * quatre, sous quatre noms et deux structures différentes. Une copie du même
 * contenu, laissée sur le disque sans jamais être exécutée, n'a pas été
 * touchée — c'est donc bien l'exécution qui déclenche, pas le code. index.php
 * existe depuis le début et n'a jamais été inquiété : ce qu'il contient ne
 * risque rien.
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

/* --- Les classes de l'application --- */
require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/Database.php';
require __DIR__ . '/src/Session.php';
require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/LimiteurConnexion.php';
require __DIR__ . '/src/Sauvegarde.php';
require __DIR__ . '/src/Fichiers.php';
require __DIR__ . '/src/ApercuDocument.php';
require __DIR__ . '/src/EditionDocument.php';
require __DIR__ . '/src/ReleveCsv.php';
require __DIR__ . '/src/ClasseurXlsx.php';
require __DIR__ . '/src/SuggestionBudget.php';
require __DIR__ . '/src/ClasseurLecteur.php';
require __DIR__ . '/src/ReleveExcel.php';
require __DIR__ . '/src/Requete.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/Vue.php';

require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/CoursController.php';
require __DIR__ . '/controllers/CalendrierController.php';
require __DIR__ . '/controllers/MatieresController.php';
require __DIR__ . '/controllers/DossiersController.php';
require __DIR__ . '/controllers/TypesEvenementController.php';
require __DIR__ . '/controllers/TagsController.php';
require __DIR__ . '/controllers/OrganisationController.php';
require __DIR__ . '/controllers/BudgetController.php';
require __DIR__ . '/controllers/PrevisionsController.php';
require __DIR__ . '/controllers/ImportController.php';
require __DIR__ . '/controllers/RemboursementsController.php';
require __DIR__ . '/controllers/SauvegardeController.php';
require __DIR__ . '/controllers/TachesController.php';
require __DIR__ . '/controllers/KanbanController.php';
require __DIR__ . '/controllers/TableauBordController.php';

/*
 * Réglages par défaut : ceux d'une installation WAMP ordinaire. Ils vivent ici
 * plutôt que dans un fichier à part, que l'antivirus du poste a mis en
 * quarantaine quatre fois de suite, emportant l'application avec lui.
 *
 * Pour d'autres identifiants — un hébergeur, un mot de passe MySQL —, créez
 * config/parametres.php avec le même tableau : il a la priorité et reste hors
 * du dépôt.
 */
Config::charger([
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'mon_appli_cours',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'nom'                 => 'Mes Cours',
        'inscription_ouverte' => true,
        // Code à fournir pour créer un compte. Vide, l'inscription est libre —
        // ce qui ne convient qu'en local : depuis le réseau, l'application la
        // refuse et le dit.
        'code_inscription'    => '',
        'dossier_uploads'     => __DIR__ . '/storage/uploads',
        'taille_max_fichier'  => 25 * 1024 * 1024,
        'extensions_autorisees' => [
            'pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods',
            'txt', 'md', 'csv', 'rtf',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'heic',
            'zip',
            'mp3', 'm4a', 'wav', 'ogg', 'oga', 'opus', 'aac', 'weba',
            'mp4', 'm4v', 'webm', 'ogv', 'mov',
        ],
    ],
], __DIR__ . '/config/parametres.php');

/* --- Où l'application est installée, et ce qui lui est demandé --- */
define('BASE_PATH_BRUT', Requete::baseBrute());
define('BASE_URL', Requete::base());
define('ROUTE', Requete::route());
define('METHODE', Requete::methode());

Session::demarrer();

/**
 * Table de routage : [méthode, motif, action].
 * Le motif accepte {id} pour un entier.
 */
$routes = [
    ['GET',  '',                          [TableauBordController::class, 'index']],

    ['GET',  'inscription',               [AuthController::class, 'formulaireInscription']],
    ['POST', 'inscription',               [AuthController::class, 'inscrire']],
    ['GET',  'connexion',                 [AuthController::class, 'formulaireConnexion']],
    ['POST', 'connexion',                 [AuthController::class, 'connecter']],
    ['POST', 'deconnexion',               [AuthController::class, 'deconnecter']],
    ['GET',  'compte',                    [AuthController::class, 'compte']],
    ['POST', 'compte/mot-de-passe',       [AuthController::class, 'changerMotDePasse']],
    ['GET',  'compte/sauvegarde',           [SauvegardeController::class, 'index']],
    ['GET',  'compte/sauvegarde/export',    [SauvegardeController::class, 'exporter']],
    ['POST', 'compte/sauvegarde/restaurer', [SauvegardeController::class, 'restaurer']],

    ['GET',  'cours',                     [CoursController::class, 'index']],
    ['GET',  'cours/nouveau',             [CoursController::class, 'formulaire']],
    ['POST', 'cours/nouveau',             [CoursController::class, 'creer']],
    ['POST', 'cours/ranger',              [CoursController::class, 'ranger']],
    ['POST', 'cours/{id}/fichiers',       [CoursController::class, 'joindre']],
    ['POST', 'cours/depot',               [CoursController::class, 'deposer']],
    ['GET',  'cours/{id}',                [CoursController::class, 'afficher']],
    ['GET',  'cours/{id}/modifier',       [CoursController::class, 'formulaire']],
    ['POST', 'cours/{id}/modifier',       [CoursController::class, 'modifier']],
    ['POST', 'cours/{id}/supprimer',      [CoursController::class, 'supprimer']],
    ['POST', 'cours/{id}/favori',         [CoursController::class, 'basculerFavori']],
    ['POST', 'cours/{id}/revision',       [CoursController::class, 'enregistrerRevision']],
    ['POST', 'cours/{id}/revision/fichiers', [CoursController::class, 'joindreFiche']],
    ['POST', 'cours/{id}/revision/elements', [CoursController::class, 'ajouterElement']],
    ['POST', 'revision/{id}/supprimer',   [CoursController::class, 'supprimerElement']],
    ['GET',  'fichiers/{id}',             [CoursController::class, 'telechargerFichier']],
    ['GET',  'fichiers/{id}/apercu',      [CoursController::class, 'apercuFichier']],
    ['GET',  'fichiers/{id}/modifier',    [CoursController::class, 'modifierFichier']],
    ['POST', 'fichiers/{id}/modifier',    [CoursController::class, 'enregistrerFichier']],
    ['POST', 'fichiers/{id}/supprimer',   [CoursController::class, 'supprimerFichier']],

    ['GET',  'calendrier',                [CalendrierController::class, 'index']],
    ['GET',  'evenements/nouveau',        [CalendrierController::class, 'formulaire']],
    ['POST', 'evenements/nouveau',        [CalendrierController::class, 'creer']],
    ['GET',  'evenements/{id}/modifier',  [CalendrierController::class, 'formulaire']],
    ['POST', 'evenements/{id}/modifier',  [CalendrierController::class, 'modifier']],
    ['POST', 'evenements/{id}/supprimer', [CalendrierController::class, 'supprimer']],
    ['POST', 'evenements/{id}/termine',   [CalendrierController::class, 'basculerTermine']],

    ['GET',  'organisation',               [OrganisationController::class, 'index']],
    ['GET',  'organisation/matieres',      [MatieresController::class, 'index']],
    ['GET',  'organisation/types',         [TypesEvenementController::class, 'index']],
    ['GET',  'organisation/tags',          [TagsController::class, 'index']],
    ['GET',  'organisation/dossiers',      [DossiersController::class, 'index']],

    ['POST', 'dossiers',                   [DossiersController::class, 'creer']],
    ['POST', 'dossiers/{id}/modifier',     [DossiersController::class, 'modifier']],
    ['POST', 'dossiers/{id}/supprimer',    [DossiersController::class, 'supprimer']],
    ['POST', 'dossiers/{id}/deplacer',     [DossiersController::class, 'deplacer']],
    ['POST', 'dossiers/ranger',            [DossiersController::class, 'ranger']],

    // Anciennes adresses, conservées pour les liens déjà enregistrés.
    ['GET',  'matieres',                  [OrganisationController::class, 'ancienneAdresse']],
    ['GET',  'types',                     [OrganisationController::class, 'ancienneAdresse']],
    ['GET',  'tags',                      [OrganisationController::class, 'ancienneAdresse']],
    ['POST', 'matieres',                  [MatieresController::class, 'creer']],
    ['POST', 'matieres/{id}/modifier',    [MatieresController::class, 'modifier']],
    ['POST', 'matieres/{id}/supprimer',   [MatieresController::class, 'supprimer']],

    ['GET',  'types',                      [TypesEvenementController::class, 'index']],
    ['POST', 'types',                      [TypesEvenementController::class, 'creer']],
    ['POST', 'types/{id}/modifier',        [TypesEvenementController::class, 'modifier']],
    ['POST', 'types/{id}/supprimer',       [TypesEvenementController::class, 'supprimer']],
    ['POST', 'types/{id}/deplacer',        [TypesEvenementController::class, 'deplacer']],

    ['GET',  'tags',                       [TagsController::class, 'index']],
    ['POST', 'tags',                       [TagsController::class, 'creer']],
    ['POST', 'tags/nettoyer',              [TagsController::class, 'nettoyer_inutilises']],
    ['POST', 'tags/{id}/modifier',         [TagsController::class, 'modifier']],
    ['POST', 'tags/{id}/fusionner',        [TagsController::class, 'fusionner']],
    ['POST', 'tags/{id}/supprimer',        [TagsController::class, 'supprimer']],

    ['GET',  'budget',                     [BudgetController::class, 'index']],
    ['POST', 'budget/operations',          [BudgetController::class, 'creer']],
    ['GET',  'budget/operations/{id}/modifier',  [BudgetController::class, 'formulaire']],
    ['POST', 'budget/operations/{id}/modifier',  [BudgetController::class, 'modifier']],
    ['POST', 'budget/operations/{id}/supprimer', [BudgetController::class, 'supprimer']],
    ['GET',  'budget/previsions',          [PrevisionsController::class, 'index']],
    ['POST', 'budget/previsions/solde',    [PrevisionsController::class, 'enregistrerSolde']],
    ['POST', 'budget/previsions/solde/supprimer', [PrevisionsController::class, 'supprimerSolde']],
    ['POST', 'budget/previsions/recurrences',     [PrevisionsController::class, 'creerRecurrence']],
    ['POST', 'budget/previsions/recurrences/{id}/modifier',  [PrevisionsController::class, 'modifierRecurrence']],
    ['POST', 'budget/previsions/recurrences/{id}/supprimer', [PrevisionsController::class, 'supprimerRecurrence']],
    ['POST', 'budget/previsions/recurrences/{id}/pointer',   [PrevisionsController::class, 'pointer']],
    ['POST', 'budget/previsions/pointer-tout',    [PrevisionsController::class, 'pointerTout']],

    ['GET',  'budget/remboursements',      [RemboursementsController::class, 'index']],
    ['GET',  'budget/remboursements/export', [RemboursementsController::class, 'exporter']],
    ['POST', 'budget/remboursements/regler', [RemboursementsController::class, 'reglerLot']],
    ['POST', 'budget/remboursements/regler-mois', [RemboursementsController::class, 'reglerMois']],
    ['POST', 'budget/remboursements/reglements/{id}/annuler', [RemboursementsController::class, 'annulerReglement']],
    ['POST', 'budget/remboursements/{id}/modifier', [RemboursementsController::class, 'modifier']],
    ['POST', 'operations/{id}/rembourser', [RemboursementsController::class, 'basculer']],

    ['GET',  'budget/import',              [ImportController::class, 'formulaire']],
    ['POST', 'budget/import',              [ImportController::class, 'analyser']],
    ['GET',  'budget/import/apercu',       [ImportController::class, 'apercu']],
    ['POST', 'budget/import/confirmer',    [ImportController::class, 'confirmer']],
    ['POST', 'budget/import/abandonner',   [ImportController::class, 'abandonner']],

    ['GET',  'budget/categories',          [BudgetController::class, 'categoriesIndex']],
    ['POST', 'budget/categories',          [BudgetController::class, 'categorieCreer']],
    ['POST', 'budget/categories/{id}/modifier',  [BudgetController::class, 'categorieModifier']],
    ['POST', 'budget/categories/{id}/supprimer', [BudgetController::class, 'categorieSupprimer']],
    ['POST', 'budget/suggestions/appliquer',   [BudgetController::class, 'appliquerSuggestion']],
    ['POST', 'budget/suggestions/{id}/appliquer', [BudgetController::class, 'appliquerSuggestion']],

    ['GET',  'taches',                       [TachesController::class, 'index']],
    ['POST', 'taches',                       [TachesController::class, 'creer']],
    ['POST', 'taches/listes',                [TachesController::class, 'creerListe']],
    ['POST', 'taches/listes/{id}/modifier',  [TachesController::class, 'modifierListe']],
    ['POST', 'taches/listes/{id}/supprimer', [TachesController::class, 'supprimerListe']],
    ['POST', 'taches/listes/{id}/cocher',    [TachesController::class, 'basculerListe']],
    ['POST', 'taches/listes/{id}/deplacer',  [TachesController::class, 'deplacerListe']],
    ['POST', 'taches/listes/ordre',          [TachesController::class, 'reordonnerListes']],
    ['POST', 'taches/ranger',                [TachesController::class, 'rangerTache']],
    ['POST', 'taches/ordre',                 [TachesController::class, 'reordonnerTaches']],
    ['POST', 'taches/listes/{id}/vider',     [TachesController::class, 'viderTerminees']],
    ['POST', 'taches/{id}/cocher',           [TachesController::class, 'basculer']],
    ['POST', 'taches/{id}/modifier',         [TachesController::class, 'modifier']],
    ['POST', 'taches/{id}/supprimer',        [TachesController::class, 'supprimer']],

    ['GET',  'tableau',                      [KanbanController::class, 'index']],
    ['POST', 'tableau/deplacer',             [KanbanController::class, 'deplacer']],
    ['POST', 'tableau/note',                 [KanbanController::class, 'noter']],

    ['GET',  'recherche',                 [CoursController::class, 'recherche']],
];

foreach ($routes as [$methode, $motif, $action]) {
    if ($methode !== METHODE) {
        continue;
    }
    $parties = array_map(static fn (string $p): string => preg_quote($p, '#'), explode('{id}', $motif));
    $regex = '#^' . implode('(\d+)', $parties) . '$#';
    if (preg_match($regex, ROUTE, $captures) === 1) {
        array_shift($captures);
        [$classe, $methodeAction] = $action;
        $controleur = new $classe();
        $controleur->$methodeAction(...array_map('intval', $captures));
        exit;
    }
}

http_response_code(404);
Vue::afficher('erreurs/404', [], 'Page introuvable');
