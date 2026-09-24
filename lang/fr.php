<?php
declare(strict_types=1);

/**
 * Le français : la langue de référence.
 *
 * Une clé absente d'une autre langue s'y lit, plutôt que de laisser un trou.
 * Les accolades marquent ce qui change : « Bonjour {nom} ».
 */
return [
    // Les dates : les mois, leurs abréviations, les jours.
    'mois' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
               'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
    'mois_courts' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                      'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
    'jours' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
    'date.mois_minuscule' => '1',
    'date.a' => 'à',

    // Le planning d'une journée, sur l'accueil comme au calendrier.
    'planning.aujourdhui' => 'Aujourd’hui',
    'planning.ajouter' => '+ ajouter',
    'planning.rien' => 'Rien de prévu à cette heure-là.',

    // La navigation, en haut de chaque page.
    'nav.accueil' => 'Accueil',
    'nav.calendrier' => 'Calendrier',
    'nav.cours' => 'Mes cours',
    'nav.partages' => 'Partagés',
    'nav.revision' => 'Révision',
    'nav.cartes' => 'Cartes',
    'nav.taches' => 'Tâches',
    'nav.tableau' => 'Tableau',
    'nav.alternance' => 'Alternance',
    'nav.groupes' => 'Groupes',
    'nav.budget' => 'Budget',
    'nav.organisation' => 'Organisation',
    'nav.amis' => 'Amis',
    'nav.rechercher' => 'Rechercher…',
    'nav.rechercher_aide' => 'Rechercher partout : cours, calendrier, notes, journal, tâches',
    'nav.compte' => 'Mon compte',
    'nav.deconnexion' => 'Déconnexion',
    'nav.connexion' => 'Connexion',
    'nav.ouvrir_menu' => 'Ouvrir le menu',
    'nav.aller_contenu' => 'Aller au contenu',
    'nav.haut_de_page' => 'Remonter en haut de la page',
    'nav.nouveaux' => '{n} nouveau(x)',
    'nav.en_attente' => '{n} en attente',
    'nav.invitations' => '{n} invitation(s)',

    // L'accueil.
    'accueil.bonjour' => 'Bonjour {nom} 👋',
    'accueil.nous_sommes' => 'Nous sommes le {date}.',
    'accueil.revision_du_jour' => '{duree} de révision aujourd’hui',
    'accueil.serie' => ', {jours} jours d’affilée 🔥',
    'accueil.reviser' => '🎯 Réviser 25 min',
    'accueil.nouveau_cours' => '+ Nouveau cours',
    'accueil.nouvel_evenement' => '+ Nouvel évènement',
    'accueil.mes_taches' => 'Mes tâches',
    'accueil.taches_en_attente' => '{n} tâche(s) en attente, sans échéance proche.',
    'accueil.voir_listes' => 'Voir mes listes',
    'accueil.rien_a_faire' => 'Rien à faire dans les jours qui viennent.',
    'accueil.ouvrir_listes' => 'Ouvrir mes listes',
    'accueil.examens' => 'Examens & devoirs',
    'accueil.aucune_echeance' => 'Aucune échéance enregistrée.',
    'accueil.aujourdhui' => 'aujourd’hui',
    'accueil.demain' => 'demain',
    'accueil.cours' => '📘 Cours',
    'accueil.fiche_revision' => '📝 Révision',
    'accueil.alternance' => 'Mon alternance',
    'accueil.travaux' => 'Travaux de groupe',
    'accueil.modifier' => 'Modifier',

    // Mon compte.
    'compte.titre' => 'Mon compte',
    'compte.inscrit_le' => 'inscrit le {date}',
    'compte.stat_cours' => 'cours',
    'compte.stat_matieres' => 'matières',
    'compte.stat_evenements' => 'évènements',
    'compte.stat_fichiers' => 'fichiers',

    // L'apparence et la langue.
    'apparence.titre' => '🎨 Apparence',
    'apparence.auto' => 'Comme mon appareil',
    'apparence.auto_aide' => 'Claire le jour, sombre le soir : l’application suit le réglage de votre téléphone ou de votre ordinateur.',
    'apparence.clair' => 'Claire',
    'apparence.clair_aide' => 'Toujours claire, quel que soit l’appareil.',
    'apparence.sombre' => 'Sombre',
    'apparence.sombre_aide' => 'Toujours sombre — reposante le soir, et plus douce sur un écran OLED.',
    'apparence.enregistree' => 'Apparence : {nom}.',
    'langue.titre' => '🌐 Langue',
    'langue.aide' => 'Celle de l’application : menus, boutons et messages. Ce que vous écrivez — cours, notes, messages — n’est pas traduit.',
    'langue.enregistree' => 'Langue : {nom}.',
    'langue.partielle' => 'Certaines pages sont encore en français ; elles se traduisent petit à petit.',

    // Les boutons et mots qui reviennent partout.
    'commun.modifier' => '✎ Modifier',
    'commun.enregistrer' => 'Enregistrer',
    'commun.annuler' => 'Annuler',
    'commun.fermer' => 'Fermer',
    'commun.supprimer' => 'Supprimer',
    'commun.retour' => '← Retour',
];
