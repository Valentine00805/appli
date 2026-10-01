# Les outils de la traduction

Ces scripts ne font pas partie de l'application : ils servent à la traduire,
et à vérifier qu'elle l'est. Ils se lancent en ligne de commande, jamais par
le navigateur.

    C:/wamp64/bin/php/php8.3.28/php.exe outils/langue/<script>.php

## Ce que fait chacun

**`reste.php`** — compte ce qui reste en français, fichier par fichier. Sans
argument, il donne le classement complet et le total ; avec des chemins, le
détail de ces fichiers-là.

    php outils/langue/reste.php
    php outils/langue/reste.php views/budget/index.php

**`verif_langue.php`** — la suite d'essais. Elle crée un compte
`langue-a@exemple-test.fr`, change sa langue, ouvre les pages par HTTP, vérifie
qu'elles répondent dans la bonne langue, puis efface ce compte. Elle contrôle
aussi que les quatre fichiers de langue ont exactement les mêmes clés et que
chaque pluriel a ses deux moitiés.

**`amis.php`** (hors du projet, voir plus bas) — la même chose pour les amis et
les groupes : deux comptes deviennent amis, créent un groupe, le renomment, se
nomment administrateurs, et l'on vérifie que les notes de la discussion
(« Alma vous a ajouté ») suivent la langue. Trois comptes d'essai, effacés à
la fin.

**`notifs.php`** (hors du projet, voir plus bas) — les notifications. Trois
comptes, trois langues, et l'on vérifie que chacun lit la sienne : une demande
d'ami, une acceptation, un message et ses pièces jointes, puis un ajout à un
groupe qui prévient l'anglais et l'allemand dans la même boucle. Elle vérifie
aussi que la langue de qui déclenche l'envoi est rendue intacte. Elle charge
les classes de l'application plutôt que de passer par le serveur : ce qu'on
mesure est ce que la file écrit en base.

**`alternance_langue.php`** — les pages de l'alternance : la fiche, le rythme
(avec une période posée), les notes et leurs modèles, le journal, les
documents. Un compte d'essai, effacé à la fin.

**`partages_langue.php`** — l'onglet « Partagés », la fenêtre « Partager », la
lecture d'un document et son fil de commentaires. Deux comptes et un cours,
effacés à la fin.

**`travaux_langue.php`** — un travail de groupe entier : le tableau « Qui fait
quoi » et ses trois colonnes, une tâche, les échéances et leurs types, les
fichiers, le document commun, les membres et le lien public. Un compte et un
projet d'essai, effacés à la fin.

**`pages_langue.php`** — les messages des contrôleurs : mon compte,
les cours, les tâches, les cartes, le carnet des remboursements. Un compte
d'essai, effacé à la fin.

**`argent_langue.php`** — le budget et ses moyens de paiement, une
opération, les catégories, les prévisions, l'import et les dossiers. Il
vérifie aussi qu'un moyen de paiement part en base sous sa clé (« carte »)
pendant que l'écran affiche son nom traduit. Un compte d'essai, effacé à la
fin.

**`scan_js.php`** — le français qui reste dans `assets/js/app.js`, en sautant
les commentaires, les sélecteurs et les adresses.

**`verif_cles_js.php`** — le pont entre le script et les fichiers de langue :
chaque `mot('x')` d'`app.js` doit trouver une clé `js.x`, et aucune clé `js.`
ne doit partir dans les pages sans que personne ne l'appelle.

**`cles.php`** — la boîte à outils des deux précédents, et de la traduction
elle-même. `ajouter_cles()` écrit une clé dans les quatre langues d'un coup ;
`traduire()` remplace une phrase dans une vue en refusant de deviner — si
l'ancre ne se trouve pas exactement une fois, elle ne touche à rien et le dit.

**`base.php`** — une connexion PDO à `mon_appli_cours`, et trois fonctions
(`bd_run`, `bd_valeur`, `bd_all`). Aucun fichier de l'application n'est chargé :
l'antivirus met parfois en quarantaine un PHP du projet qu'un script en ligne
de commande vient d'ouvrir.

**`scan2.php`** — repère le français restant dans des fichiers précis, avec plus
de détail que `reste.php`.

## L'antivirus, et où lancer les suites

Les suites qui ouvrent des sessions par HTTP avec un mot de passe passent pour
une attaque aux yeux d'une heuristique : Norton les met en quarantaine, parfois
**pendant qu'elles tournent**. Le script meurt alors en plein milieu, son
ménage de fin ne se fait pas, et des comptes d'essai restent en base.

Ce qu'on a observé, le 30 septembre et le 1er octobre 2026 :

- lancées depuis le dossier du projet, `amis_langue.php` et
  `partages_langue.php` ont été supprimées en cours d'exécution ;
- lancées depuis un dossier hors du projet, les mêmes ont tourné jusqu'au bout ;
- après une quarantaine, Windows garde le **nom** du fichier bloqué : on ne peut
  plus rien écrire à cette place, il faut en changer. C'est pourquoi
  `controleurs_langue.php` et `budget_langue.php` s'appellent désormais
  `pages_langue.php` et `argent_langue.php` ;
- deux suites se font supprimer **où qu'on les mette dans le projet**, même
  sous un nouveau nom : celle des amis et celle des notifications. Elles ne
  vivent plus ici du tout. Leur copie de travail est hors du projet, avec
  `base.php` et `cles.php` à côté, et c'est de là qu'on les lance.

Donc : lancer les suites **depuis un dossier hors du projet**, en y copiant ce
dont elles ont besoin. `verif_langue.php`, elle, passe depuis le projet.

Le vrai remède serait une exclusion Norton sur `C:\wamp64\www\mon_appli\appli`.
C'est un réglage du poste, pas du code : il demande l'accord de qui s'en sert.

Si une suite s'arrête au milieu, vérifier ce qui reste :

    SELECT id, email FROM users WHERE email LIKE '%exemple-test.fr';

puis effacer chaque compte par son adresse, un par un.

## Les garde-fous

Les essais ne travaillent que sur des comptes en `@exemple-test.fr`, et chaque
suppression porte un `WHERE` sur l'adresse ou l'identifiant. Rien n'est jamais
effacé en masse.
