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
require __DIR__ . '/src/Depot.php';
require __DIR__ . '/src/Session.php';
require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/LimiteurConnexion.php';
require __DIR__ . '/src/Courriel.php';
require __DIR__ . '/src/Reinitialisation.php';
require __DIR__ . '/src/Sauvegarde.php';
require __DIR__ . '/src/Fichiers.php';
require __DIR__ . '/src/ApercuDocument.php';
require __DIR__ . '/src/ImagesDocument.php';
require __DIR__ . '/src/EditionDocument.php';
require __DIR__ . '/src/ReleveCsv.php';
require __DIR__ . '/src/ClasseurXlsx.php';
require __DIR__ . '/src/SuggestionBudget.php';
require __DIR__ . '/src/ClasseurLecteur.php';
require __DIR__ . '/src/ReleveExcel.php';
require __DIR__ . '/src/TextePdf.php';
require __DIR__ . '/src/GenerateurCartes.php';
require __DIR__ . '/src/TexteRiche.php';
require __DIR__ . '/src/PdfSimple.php';
require __DIR__ . '/src/ExportPdf.php';
require __DIR__ . '/src/WebPush.php';
require __DIR__ . '/src/Rappels.php';
require __DIR__ . '/src/FileNotifications.php';
require __DIR__ . '/src/Amis.php';
require __DIR__ . '/src/Conversations.php';
require __DIR__ . '/src/Partages.php';
require __DIR__ . '/src/PlanningJour.php';
require __DIR__ . '/src/Fournisseur.php';
require __DIR__ . '/src/FournisseurMicrosoft.php';
require __DIR__ . '/src/FournisseurGoogle.php';
require __DIR__ . '/src/Agenda.php';
require __DIR__ . '/src/LiaisonAgenda.php';
require __DIR__ . '/src/SynchroAgenda.php';
require __DIR__ . '/src/EnvoiAgenda.php';
require __DIR__ . '/src/Requete.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/Vue.php';

require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/AmisController.php';
require __DIR__ . '/controllers/ConversationsController.php';
require __DIR__ . '/controllers/PartagesController.php';
require __DIR__ . '/controllers/CoursController.php';
require __DIR__ . '/controllers/CalendrierController.php';
require __DIR__ . '/controllers/MatieresController.php';
require __DIR__ . '/controllers/DossiersController.php';
require __DIR__ . '/controllers/TypesEvenementController.php';
require __DIR__ . '/controllers/TagsController.php';
require __DIR__ . '/controllers/OrganisationController.php';
require __DIR__ . '/controllers/BudgetController.php';
require __DIR__ . '/controllers/PrevisionsController.php';
require __DIR__ . '/controllers/AgendaController.php';
require __DIR__ . '/controllers/ImportController.php';
require __DIR__ . '/controllers/RemboursementsController.php';
require __DIR__ . '/controllers/SauvegardeController.php';
require __DIR__ . '/controllers/TachesController.php';
require __DIR__ . '/controllers/KanbanController.php';
require __DIR__ . '/controllers/TableauBordController.php';
require __DIR__ . '/controllers/CartesController.php';
require __DIR__ . '/controllers/NotificationsController.php';

/*
 * Réglages par défaut : ceux d'une installation WAMP ordinaire. Ils vivent ici
 * plutôt que dans un fichier à part, que l'antivirus du poste a mis en
 * quarantaine quatre fois de suite, emportant l'application avec lui.
 *
 * Pour d'autres identifiants — un hébergeur, un mot de passe MySQL, une
 * application Microsoft —, créez config/parametres.php : il n'a besoin d'y
 * écrire que ce qu'il change, le reste vient d'ici. Il a la priorité et
 * reste hors du dépôt.
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
        // L'adresse du site en ligne (« https://exemple.fr », sans chemin) : celle
        // des liens envoyés par e-mail. Vide, elle n'est déduite qu'en local.
        'adresse_publique'    => '',
        'dossier_uploads'     => __DIR__ . '/storage/uploads',
        'taille_max_fichier'  => 200 * 1024 * 1024,
        'extensions_autorisees' => [
            'pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods',
            'txt', 'md', 'csv', 'rtf',
            /*
             * Les carnets OneNote : gardés et retéléchargeables, mais rien de
             * plus. Leur format est fermé, et personne d'autre que OneNote ne
             * sait les ouvrir — pour lire une section dans l'application, il
             * faut l'exporter en PDF ou en Word.
             */
            'one', 'onepkg',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'heic',
            'zip',
            'mp3', 'm4a', 'wav', 'ogg', 'oga', 'opus', 'aac', 'weba',
            'mp4', 'm4v', 'webm', 'ogv', 'mov',
        ],
    ],
    /*
     * La liaison avec Outlook.
     *
     * Une seule application est inscrite chez Microsoft : celle-ci. Chacun y
     * relie ensuite son propre compte, sans rien avoir à inscrire — demander à
     * chaque personne de créer une application Azure n'aurait aucun sens.
     *
     * Tant que « client_id » est vide, la liaison est simplement absente de
     * l'application, et la page le dit.
     *
     * Le secret : en local, l'application se présente en client public et n'en
     * a pas besoin — elle prouve son identité par PKCE. En ligne, sur un
     * serveur qu'on tient, la plateforme « Web » de Microsoft en réclame un :
     * on le pose alors ici, dans config/parametres.php, qui ne va pas au dépôt.
     *
     * L'adresse de retour se déduit de la requête, ce qui suffit en local.
     * En ligne, mieux vaut l'inscrire en clair : ce qu'annonce un navigateur
     * ne se croit pas sur parole, et cette adresse doit correspondre au mot
     * près à celle déclarée chez Microsoft.
     */
    /*
     * La liaison avec Google Agenda.
     *
     * Même principe qu'Outlook : un projet Google Cloud inscrit une fois
     * pour toutes, et chacun y relie son compte. Une différence tout de
     * même — Google exige le secret client même en local, pour un
     * identifiant de type « application web ».
     *
     * L'adresse à déclarer chez Google est « agenda/google/retour ».
     */
    /*
     * L'envoi d'e-mails (le lien d'un mot de passe oublié).
     *
     * Pour Gmail : l'adresse, et un « mot de passe d'application » créé sur
     * myaccount.google.com/apppasswords (validation en deux étapes requise) —
     * jamais le mot de passe du compte. À écrire dans config/parametres.php.
     * Tant que c'est vide, en local, les e-mails sont rangés dans
     * storage/courriels pour qu'on puisse les ouvrir.
     */
    'courriel' => [
        'serveur'      => 'smtp://smtp.gmail.com:587',
        'utilisateur'  => '',
        'mot_de_passe' => '',
        'expediteur'   => '',
        'nom'          => '',
    ],
    'google' => [
        'client_id'      => '',
        'secret'         => '',
        'adresse_retour' => '',
    ],
    'outlook' => [
        'client_id'      => '',
        'locataire'      => 'common',
        'secret'         => '',
        'adresse_retour' => '',
    ],
], __DIR__ . '/config/parametres.php');

/* --- Où l'application est installée, et ce qui lui est demandé --- */
define('BASE_PATH_BRUT', Requete::baseBrute());
define('BASE_URL', Requete::base());
define('ROUTE', Requete::route());
define('METHODE', Requete::methode());

Session::demarrer();

/*
 * L'heure de qui regarde.
 *
 * Les dates sont écrites en heure locale, sans décalage : chacun ne lit que
 * les siennes, et elles n'ont de sens que dans son fuseau. Le régler ici, une
 * fois, suffit à ce que tout le reste — affichage, formulaires, agenda
 * Outlook — tombe juste sans avoir à s'en occuper.
 */
date_default_timezone_set(Auth::connecte() ? Auth::fuseau() : Auth::FUSEAU_PAR_DEFAUT);

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
    ['GET',  'mot-de-passe/oublie',       [AuthController::class, 'formulaireOubli']],
    ['POST', 'mot-de-passe/oublie',       [AuthController::class, 'demanderReinitialisation']],
    ['GET',  'mot-de-passe/nouveau',      [AuthController::class, 'formulaireNouveau']],
    ['POST', 'mot-de-passe/nouveau',      [AuthController::class, 'reinitialiser']],
    ['POST', 'deconnexion',               [AuthController::class, 'deconnecter']],
    ['GET',  'compte',                    [AuthController::class, 'compte']],
    ['GET',  'notifications',             [NotificationsController::class, 'index']],
    ['GET',  'service-worker.js',         [NotificationsController::class, 'serviceWorker']],
    ['POST', 'notifications/abonnement',  [NotificationsController::class, 'abonner']],
    ['POST', 'notifications/desabonnement', [NotificationsController::class, 'desabonner']],
    ['POST', 'notifications/essai',       [NotificationsController::class, 'essai']],
    ['POST', 'notifications/battement',   [NotificationsController::class, 'battement']],
    ['GET',  'notifications/envoyer',     [NotificationsController::class, 'envoyer']],
    ['POST', 'notifications/envoyer',     [NotificationsController::class, 'envoyer']],
    ['POST', 'compte/mot-de-passe',       [AuthController::class, 'changerMotDePasse']],
    ['POST', 'compte/fuseau',            [AuthController::class, 'changerFuseau']],
    ['POST', 'compte/pseudo',            [AuthController::class, 'changerPseudo']],
    ['POST', 'compte/transcription',     [AuthController::class, 'changerTranscription']],
    ['POST', 'compte/photo',             [AuthController::class, 'changerPhoto']],
    ['POST', 'compte/photo/retirer',     [AuthController::class, 'retirerPhoto']],
    ['GET',  'comptes/{id}/photo',       [AuthController::class, 'photo']],

    // Les amis : chercher un pseudo, les demandes, et les conversations.
    ['GET',  'amis',                      [AmisController::class, 'index']],
    ['POST', 'amis/demande',              [AmisController::class, 'demander']],
    ['POST', 'amis/{id}/accepter',        [AmisController::class, 'accepter']],
    ['POST', 'amis/{id}/retirer',         [AmisController::class, 'retirer']],
    ['POST', 'amis/{id}/bloquer',         [AmisController::class, 'bloquer']],
    ['POST', 'amis/{id}/debloquer',       [AmisController::class, 'debloquer']],
    ['GET',  'amis/{id}',                 [AmisController::class, 'conversation']],
    ['GET',  'amis/{id}/messages',        [AmisController::class, 'nouveaux']],
    ['GET',  'amis/{id}/profil',          [AmisController::class, 'profil']],
    ['GET',  'amis/{id}/recherche',       [AmisController::class, 'rechercher']],
    ['GET',  'amis/{id}/fond',            [AmisController::class, 'fond']],
    ['POST', 'amis/{id}/fond',            [AmisController::class, 'changerFond']],
    ['POST', 'amis/{id}/fond/retirer',    [AmisController::class, 'retirerFond']],
    ['GET',  'amis/images/{id}',          [AmisController::class, 'image']],
    ['GET',  'amis/fichiers/{id}',        [AmisController::class, 'fichier']],
    ['GET',  'amis/vocaux/{id}',          [AmisController::class, 'vocal']],
    ['POST', 'amis/messages/{id}/supprimer', [AmisController::class, 'supprimerMessage']],
    ['POST', 'amis/messages/{id}/modifier', [AmisController::class, 'modifierMessage']],
    ['POST', 'amis/messages/{id}/reaction', [AmisController::class, 'reagir']],
    ['POST', 'amis/messages/{id}/epingle', [AmisController::class, 'epingler']],
    ['POST', 'amis/{id}/messages',        [AmisController::class, 'envoyer']],

    // Les discussions de groupe (à ne pas confondre avec les groupes de personnes du budget).
    ['GET',  'discussions/nouvelle',        [ConversationsController::class, 'nouvelleDiscussion']],
    ['GET',  'groupes/nouveau',             [ConversationsController::class, 'nouveau']],
    ['POST', 'groupes',                     [ConversationsController::class, 'creer']],
    ['GET',  'groupes/{id}',                [ConversationsController::class, 'conversation']],
    ['GET',  'groupes/{id}/reglages',       [ConversationsController::class, 'reglages']],
    ['GET',  'groupes/{id}/messages',       [ConversationsController::class, 'nouveaux']],
    ['POST', 'groupes/{id}/messages',       [ConversationsController::class, 'envoyer']],
    ['GET',  'groupes/{id}/recherche',      [ConversationsController::class, 'rechercher']],
    ['POST', 'groupes/{id}/nom',            [ConversationsController::class, 'renommer']],
    ['POST', 'groupes/{id}/membres',        [ConversationsController::class, 'ajouter']],
    ['POST', 'groupes/{id}/membres/{id}/retirer', [ConversationsController::class, 'retirerMembre']],
    ['POST', 'groupes/{id}/membres/{id}/admin',   [ConversationsController::class, 'nommerAdmin']],
    ['POST', 'groupes/{id}/membres/{id}/membre',  [ConversationsController::class, 'retirerAdmin']],
    ['GET',  'groupes/{id}/chercher',       [ConversationsController::class, 'chercher']],
    ['POST', 'groupes/{id}/inviter',        [ConversationsController::class, 'inviter']],
    ['POST', 'groupes/{id}/invitations/{id}/annuler', [ConversationsController::class, 'annulerInvitation']],
    ['POST', 'groupes/{id}/rejoindre',      [ConversationsController::class, 'rejoindre']],
    ['POST', 'groupes/{id}/refuser',        [ConversationsController::class, 'refuser']],
    ['POST', 'groupes/{id}/quitter',        [ConversationsController::class, 'quitter']],
    ['GET',  'groupes/{id}/photo',          [ConversationsController::class, 'photo']],
    ['POST', 'groupes/{id}/photo',          [ConversationsController::class, 'changerPhoto']],
    ['POST', 'groupes/{id}/photo/retirer',  [ConversationsController::class, 'retirerPhoto']],
    ['GET',  'groupes/{id}/fond',           [ConversationsController::class, 'fond']],
    ['POST', 'groupes/{id}/fond',           [ConversationsController::class, 'changerFond']],
    ['POST', 'groupes/{id}/fond/retirer',   [ConversationsController::class, 'retirerFond']],
    ['GET',  'groupes/images/{id}',         [ConversationsController::class, 'imageMessage']],
    ['GET',  'groupes/fichiers/{id}',       [ConversationsController::class, 'fichier']],
    ['GET',  'groupes/vocaux/{id}',         [ConversationsController::class, 'vocal']],
    ['POST', 'groupes/messages/{id}/supprimer', [ConversationsController::class, 'supprimerMessage']],
    ['POST', 'groupes/messages/{id}/modifier',  [ConversationsController::class, 'modifierMessage']],
    ['POST', 'groupes/messages/{id}/reaction',  [ConversationsController::class, 'reagir']],
    ['POST', 'groupes/messages/{id}/epingle',   [ConversationsController::class, 'epingler']],
    /*
     * Les agendas distants. Le fournisseur est dans l'adresse : chacun a
     * ainsi sa page et son retour d'autorisation, sans une ligne de plus.
     */
    ['GET',  'agenda',                     [AgendaController::class, 'liste']],
    ['POST', 'agenda/synchroniser',        [AgendaController::class, 'synchroniserTout']],
    ['POST', 'agenda/vue',                 [CalendrierController::class, 'vue']],
    ['GET',  'agenda/{mot}',               [AgendaController::class, 'index']],
    ['POST', 'agenda/{mot}/connexion',     [AgendaController::class, 'connexion']],
    ['GET',  'agenda/{mot}/retour',        [AgendaController::class, 'retour']],
    ['POST', 'agenda/{mot}/synchroniser',  [AgendaController::class, 'synchroniser']],
    ['POST', 'agenda/{mot}/calendriers',   [AgendaController::class, 'calendriers']],
    ['POST', 'agenda/{mot}/suivre',        [AgendaController::class, 'suivre']],
    ['POST', 'agenda/{mot}/retirer',       [AgendaController::class, 'retirer']],
    ['POST', 'agenda/{mot}/destination',   [AgendaController::class, 'destination']],
    ['POST', 'agenda/{mot}/retirer-envoi', [AgendaController::class, 'retirerEnvoi']],
    ['POST', 'agenda/{mot}/deconnexion',   [AgendaController::class, 'deconnexion']],
    /*
     * Les adresses d'avant la refonte. Celle du retour n'est pas une
     * commodité : elle est déclarée chez Microsoft, et la changer
     * obligerait chacun à retoucher son inscription.
     */
    ['GET',  'outlook',                    [AgendaController::class, 'ancienLien']],
    ['GET',  'outlook/retour',             [AgendaController::class, 'retourMicrosoft']],

    ['GET',  'compte/sauvegarde',           [SauvegardeController::class, 'index']],
    ['GET',  'compte/sauvegarde/export',    [SauvegardeController::class, 'exporter']],
    ['POST', 'compte/sauvegarde/restaurer', [SauvegardeController::class, 'restaurer']],

    ['GET',  'cours',                     [CoursController::class, 'index']],
    ['GET',  'revision',                  [CoursController::class, 'revisions']],
    ['GET',  'cours/nouveau',             [CoursController::class, 'formulaire']],
    ['POST', 'cours/nouveau',             [CoursController::class, 'creer']],
    ['POST', 'cours/ranger',              [CoursController::class, 'ranger']],
    ['POST', 'cours/{id}/fichiers',       [CoursController::class, 'joindre']],
    ['POST', 'cours/depot',               [CoursController::class, 'deposer']],
    ['POST', 'cours/depot-dossier',       [CoursController::class, 'deposerDossier']],
    ['GET',  'cours/{id}',                [CoursController::class, 'afficher']],
    ['GET',  'cours/{id}/modifier',       [CoursController::class, 'formulaire']],
    ['POST', 'cours/{id}/modifier',       [CoursController::class, 'modifier']],
    ['POST', 'cours/{id}/supprimer',      [CoursController::class, 'supprimer']],
    ['POST', 'cours/{id}/favori',         [CoursController::class, 'basculerFavori']],
    ['POST', 'cours/{id}/contenu',        [CoursController::class, 'enregistrerContenu']],
    ['POST', 'cours/{id}/revision',       [CoursController::class, 'enregistrerRevision']],
    ['POST', 'cours/{id}/revision/fichiers', [CoursController::class, 'joindreFiche']],
    ['POST', 'cours/{id}/revision/elements', [CoursController::class, 'ajouterElement']],
    ['GET',  'revision/{id}',              [CoursController::class, 'fiche']],
    ['GET',  'revision/{id}/pdf',          [CoursController::class, 'pdfFiche']],
    ['POST', 'revision/element/{id}/supprimer', [CoursController::class, 'supprimerElement']],
    ['GET',  'fichiers/{id}',             [CoursController::class, 'telechargerFichier']],
    ['GET',  'fichiers/{id}/apercu',      [CoursController::class, 'apercuFichier']],
    ['GET',  'fichiers/{id}/image',       [CoursController::class, 'imageFichier']],
    ['GET',  'fichiers/{id}/pdf',         [CoursController::class, 'pdfFichier']],
    ['GET',  'cours/{id}/pdf',            [CoursController::class, 'pdfCours']],
    ['GET',  'fichiers/{id}/modifier',    [CoursController::class, 'modifierFichier']],
    ['POST', 'fichiers/{id}/modifier',    [CoursController::class, 'enregistrerFichier']],
    ['POST', 'fichiers/{id}/position',    [CoursController::class, 'positionLecture']],
    ['POST', 'fichiers/{id}/supprimer',   [CoursController::class, 'supprimerFichier']],

    ['GET',  'calendrier',                [CalendrierController::class, 'index']],
    ['POST', 'calendrier/agendas',        [CalendrierController::class, 'sources']],
    ['POST', 'calendrier/volet',          [CalendrierController::class, 'volet']],
    ['POST', 'calendrier/volet-ouvert',   [CalendrierController::class, 'voletOuvert']],
    ['GET',  'evenements/nouveau',        [CalendrierController::class, 'formulaire']],
    ['POST', 'evenements/nouveau',        [CalendrierController::class, 'creer']],
    ['GET',  'evenements/{id}',           [CalendrierController::class, 'voir']],
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
    ['POST', 'dossiers/colonne',           [DossiersController::class, 'fermerColonne']],

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

    ['GET',  'budget/personnes',           [RemboursementsController::class, 'personnesIndex']],
    ['POST', 'budget/personnes',           [RemboursementsController::class, 'personneCreer']],
    ['POST', 'budget/personnes/{id}/renommer',  [RemboursementsController::class, 'personneRenommer']],
    ['POST', 'budget/personnes/{id}/fusionner', [RemboursementsController::class, 'personneFusionner']],
    ['POST', 'budget/personnes/{id}/supprimer', [RemboursementsController::class, 'personneSupprimer']],
    ['POST', 'budget/groupes',                 [RemboursementsController::class, 'groupeCreer']],
    ['POST', 'budget/groupes/{id}/modifier',   [RemboursementsController::class, 'groupeModifier']],
    ['POST', 'budget/groupes/{id}/supprimer',  [RemboursementsController::class, 'groupeSupprimer']],

    ['GET',  'budget/categories',          [BudgetController::class, 'categoriesIndex']],
    ['POST', 'budget/categories',          [BudgetController::class, 'categorieCreer']],
    ['POST', 'budget/categories/{id}/modifier',  [BudgetController::class, 'categorieModifier']],
    ['POST', 'budget/categories/{id}/supprimer', [BudgetController::class, 'categorieSupprimer']],
    ['POST', 'budget/suggestions/appliquer',   [BudgetController::class, 'appliquerSuggestion']],
    ['POST', 'budget/suggestions/{id}/appliquer', [BudgetController::class, 'appliquerSuggestion']],

    ['GET',  'taches',                       [TachesController::class, 'index']],
    ['GET',  'taches/nouvelle',              [TachesController::class, 'nouvelle']],
    ['GET',  'taches/{id}',                  [TachesController::class, 'voir']],
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

    // « cartes/seance » passe avant « cartes/{id} » : sinon le mot serait
    // pris pour un identifiant.
    ['GET',  'cartes',                       [CartesController::class, 'index']],
    ['GET',  'cartes/seance',                [CartesController::class, 'seance']],
    // Depuis l'onglet, le cours est dans le formulaire et non dans l'adresse.
    // Fabriquer une carte se fait depuis l'onglet, jamais depuis un cours :
    // le cours voyage dans le formulaire.
    ['POST', 'cartes/proposer',              [CartesController::class, 'proposer']],
    ['POST', 'cartes/retenir',               [CartesController::class, 'retenir']],
    ['POST', 'cartes/carte',                 [CartesController::class, 'ajouterUne']],
    ['POST', 'cartes/{id}/reponse',          [CartesController::class, 'repondre']],
    ['POST', 'cartes/{id}/modifier',         [CartesController::class, 'modifier']],
    ['POST', 'cartes/{id}/supprimer',        [CartesController::class, 'supprimer']],
    ['GET',  'cours/{id}/cartes',            [CartesController::class, 'paquet']],
    ['POST', 'cours/{id}/cartes/rezero',     [CartesController::class, 'reinitialiser']],
    ['POST', 'cours/{id}/cartes/vider',      [CartesController::class, 'viderPaquet']],

    ['GET',  'tableau',                      [KanbanController::class, 'index']],
    ['POST', 'tableau/deplacer',             [KanbanController::class, 'deplacer']],
    ['POST', 'tableau/note',                 [KanbanController::class, 'noter']],

    ['GET',  'recherche',                 [CoursController::class, 'recherche']],

    // Partager un cours ou un fichier : avec ses amis, ou par un lien public.
    ['GET',  'partages',                            [PartagesController::class, 'index']],
    ['GET',  'partager/{mot}/{id}',                 [PartagesController::class, 'fenetre']],
    ['POST', 'partager/{mot}/{id}/amis',            [PartagesController::class, 'envoyer']],
    ['POST', 'partager/{mot}/{id}/acces/{id}/retirer', [PartagesController::class, 'retirerAcces']],
    ['POST', 'partager/{mot}/{id}/lien',            [PartagesController::class, 'creerLien']],
    ['POST', 'partager/{mot}/{id}/lien/desactiver', [PartagesController::class, 'desactiverLien']],
    ['GET',  'partages/fichiers/{id}/contenu',      [PartagesController::class, 'contenu']],
    ['GET',  'partages/{mot}/{id}',                 [PartagesController::class, 'lire']],
    ['POST', 'partages/{mot}/{id}/copier',          [PartagesController::class, 'copier']],
    ['POST', 'partages/{mot}/{id}/oublier',         [PartagesController::class, 'oublier']],
    ['GET',  'p/{jeton}',                           [PartagesController::class, 'public']],
    ['GET',  'p/{jeton}/cours/{id}',                [PartagesController::class, 'coursPublic']],
    ['GET',  'p/{jeton}/fichiers/{id}',             [PartagesController::class, 'fichierPublic']],
];

foreach ($routes as [$methode, $motif, $action]) {
    if ($methode !== METHODE) {
        continue;
    }
    /*
     * Deux sortes de trous dans un motif : « {id} » pour un entier,
     * « {mot} » pour un nom sans accent — le fournisseur d'un agenda —,
     * « {jeton} » pour le code d'un lien de partage.
     * Chacun garde sa nature jusqu'à l'action : un identifiant reste un
     * entier, un mot reste une chaîne.
     */
    $sortes = [];
    $regex = '#^';
    foreach (preg_split('#(\{id\}|\{mot\}|\{jeton\})#', $motif, -1, PREG_SPLIT_DELIM_CAPTURE) as $bout) {
        if ($bout === '{id}')  { $regex .= '(\d+)';   $sortes[] = 'id';  continue; }
        if ($bout === '{mot}') { $regex .= '([a-z]+)'; $sortes[] = 'mot'; continue; }
        if ($bout === '{jeton}') { $regex .= '([0-9a-f]{32})'; $sortes[] = 'jeton'; continue; }
        $regex .= preg_quote($bout, '#');
    }
    $regex .= '$#';

    if (preg_match($regex, ROUTE, $captures) === 1) {
        array_shift($captures);
        $arguments = [];
        foreach ($captures as $rang => $valeur) {
            $arguments[] = ($sortes[$rang] ?? 'id') === 'id' ? (int) $valeur : $valeur;
        }
        [$classe, $methodeAction] = $action;
        $controleur = new $classe();
        $controleur->$methodeAction(...$arguments);
        exit;
    }
}

http_response_code(404);
Vue::afficher('erreurs/404', [], 'Page introuvable');
