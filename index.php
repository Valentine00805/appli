<?php
declare(strict_types=1);

/**
 * Point d'entrée unique de l'application « Mes Cours ».
 * Toutes les URL passent par ce fichier (voir .htaccess).
 */

require __DIR__ . '/src/bootstrap.php';

require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/CoursController.php';
require __DIR__ . '/controllers/CalendrierController.php';
require __DIR__ . '/controllers/MatieresController.php';
require __DIR__ . '/controllers/TypesEvenementController.php';
require __DIR__ . '/controllers/TableauBordController.php';

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

    ['GET',  'cours',                     [CoursController::class, 'index']],
    ['GET',  'cours/nouveau',             [CoursController::class, 'formulaire']],
    ['POST', 'cours/nouveau',             [CoursController::class, 'creer']],
    ['GET',  'cours/{id}',                [CoursController::class, 'afficher']],
    ['GET',  'cours/{id}/modifier',       [CoursController::class, 'formulaire']],
    ['POST', 'cours/{id}/modifier',       [CoursController::class, 'modifier']],
    ['POST', 'cours/{id}/supprimer',      [CoursController::class, 'supprimer']],
    ['POST', 'cours/{id}/favori',         [CoursController::class, 'basculerFavori']],
    ['GET',  'fichiers/{id}',             [CoursController::class, 'telechargerFichier']],
    ['POST', 'fichiers/{id}/supprimer',   [CoursController::class, 'supprimerFichier']],

    ['GET',  'calendrier',                [CalendrierController::class, 'index']],
    ['GET',  'evenements/nouveau',        [CalendrierController::class, 'formulaire']],
    ['POST', 'evenements/nouveau',        [CalendrierController::class, 'creer']],
    ['GET',  'evenements/{id}/modifier',  [CalendrierController::class, 'formulaire']],
    ['POST', 'evenements/{id}/modifier',  [CalendrierController::class, 'modifier']],
    ['POST', 'evenements/{id}/supprimer', [CalendrierController::class, 'supprimer']],
    ['POST', 'evenements/{id}/termine',   [CalendrierController::class, 'basculerTermine']],

    ['GET',  'matieres',                  [MatieresController::class, 'index']],
    ['POST', 'matieres',                  [MatieresController::class, 'creer']],
    ['POST', 'matieres/{id}/modifier',    [MatieresController::class, 'modifier']],
    ['POST', 'matieres/{id}/supprimer',   [MatieresController::class, 'supprimer']],

    ['GET',  'types',                      [TypesEvenementController::class, 'index']],
    ['POST', 'types',                      [TypesEvenementController::class, 'creer']],
    ['POST', 'types/{id}/modifier',        [TypesEvenementController::class, 'modifier']],
    ['POST', 'types/{id}/supprimer',       [TypesEvenementController::class, 'supprimer']],
    ['POST', 'types/{id}/deplacer',        [TypesEvenementController::class, 'deplacer']],

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
