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
require __DIR__ . '/controllers/TagsController.php';
require __DIR__ . '/controllers/OrganisationController.php';
require __DIR__ . '/controllers/BudgetController.php';
require __DIR__ . '/controllers/PrevisionsController.php';
require __DIR__ . '/controllers/ImportController.php';
require __DIR__ . '/controllers/RemboursementsController.php';
require __DIR__ . '/controllers/SauvegardeController.php';
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
    ['GET',  'compte/sauvegarde',           [SauvegardeController::class, 'index']],
    ['GET',  'compte/sauvegarde/export',    [SauvegardeController::class, 'exporter']],
    ['POST', 'compte/sauvegarde/restaurer', [SauvegardeController::class, 'restaurer']],

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

    ['GET',  'organisation',               [OrganisationController::class, 'index']],
    ['GET',  'organisation/matieres',      [MatieresController::class, 'index']],
    ['GET',  'organisation/types',         [TypesEvenementController::class, 'index']],
    ['GET',  'organisation/tags',          [TagsController::class, 'index']],

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
