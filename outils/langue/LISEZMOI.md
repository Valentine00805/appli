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

**`amis_langue.php`** — la même chose pour les amis et les groupes :
deux comptes deviennent amis, créent un groupe, le renomment, se nomment
administrateurs, et l'on vérifie que les notes de la discussion (« Alma vous a
ajouté ») suivent la langue. Trois comptes d'essai, effacés à la fin.

**`notifications_langue.php`** — les notifications. Trois
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

**`controleurs_langue.php`** — les messages des contrôleurs : mon compte,
les cours, les tâches, les cartes, le carnet des remboursements. Un compte
d'essai, effacé à la fin.

**`budget_langue.php`** — le budget et ses moyens de paiement, une
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
l'antivirus mettait en quarantaine un PHP du projet qu'un script en ligne de
commande venait d'ouvrir. C'est réglé depuis l'exclusion, mais rien n'oblige
ces trois fonctions à en dépendre.

**`scan2.php`** — repère le français restant dans des fichiers précis, avec plus
de détail que `reste.php`. Comme `reste.php`, il lit le fichier comme du texte
et compte donc les commentaires : préférer `scan3.php`.

**`scan3.php`** — **celui sur lequel se fier.** Il passe par `token_get_all()` :
les commentaires et les blocs de documentation disparaissent pour de bon, et il
ne reste que ce qui peut atteindre un écran. Il écarte ensuite ce qui a l'air
d'une phrase sans jamais s'afficher — SQL, clé de langue, chemin, sélecteur,
attribut HTML, CSS — et dit combien il en a écarté. `--tout` les montre avec
leur motif.

    php outils/langue/scan3.php                 tout le projet
    php outils/langue/scan3.php src/Amis.php    ces fichiers-là
    php outils/langue/scan3.php --tout          avec les faux amis

**`menage.php`** — efface les comptes d'essai restés en base, un par un, par
leur adresse. Sans argument il dit seulement ce qu'il voit ; `--efface` le
fait. Il ne touche qu'à des adresses en `@exemple-test.fr`, et sa suppression
finale porte à la fois sur l'identifiant et sur l'adresse : un vrai compte ne
peut pas entrer dans sa requête.

**`signaux_langue.php`** — les trois endroits où le code se demandait « de quoi
s'agit-il ? » en comparant du texte affiché : une liste de tâches au
calendrier, une session de révision, et le « c'est aujourd'hui » de la
recherche dans un groupe. Elle vérifie que chacun se décide maintenant sur
autre chose que des mots. Deux comptes d'essai, effacés à la fin.

**`installation_langue.php`** — ce que le navigateur lit avant toute page : le
manifeste d'installation (le nom sous l'icône, la description, les raccourcis)
et le service worker. Elle vérifie aussi que le manifeste n'est plus servi en
cache `public`, puisqu'il varie d'un compte à l'autre. Un compte d'essai,
effacé à la fin.

## Ce qui reste en français, et qui doit y rester

La traduction est finie. `scan3.php` signale encore une quarantaine de lignes,
et **chacune est voulue**. Avant de « corriger » l'une d'elles, retrouver sa
catégorie ici :

- **`views/erreurs/base.php`** — la seule page qui doit rester française.
  `t()` demande la langue du compte à la base, et cette page annonce justement
  qu'on ne l'atteint pas. Son en-tête l'explique.
- **Les messages d'`error_log()`** (`src/Courriel.php`, `src/Reinitialisation.php`)
  — ils vont dans le journal du serveur, que lit qui l'administre, pas un compte.
- **Ce qui lit du français au lieu de l'afficher** — les noms de mois de
  `src/PlanningPdf.php` et de `src/ReleveExcel.php`, qui reconnaissent un
  planning PDF et un relevé bancaire ; `Alternance::MOTS_DES_LIEUX`, qui devine
  un lieu depuis un titre d'agenda ; la liste d'accents d'`src/Amis.php`, qui
  sert à normaliser une recherche.
- **`Focus::LISTE`** (« Révisions ») — c'est par ce nom qu'on retrouve la liste
  en base (`WHERE nom = ?`). Le traduire ferait perdre la sienne à qui change
  de langue, et en créerait une seconde. Rien n'empêche de la renommer depuis
  la page des tâches.
- **`Langue::LANGUES`** — « Français », « English », « Español », « Deutsch » :
  chaque langue se nomme dans la sienne, c'est le propre d'un menu de langues.
- **« Mes Cours »**, le nom de l'application, et ce qui en dérive : les
  `PRODID` des fichiers iCalendar, le `/Producer` des PDF, le nom du fichier de
  sauvegarde, l'adresse d'expédition des e-mails.
- **Un commentaire JavaScript dans un `heredoc`** (`NotificationsController`) —
  du code, que le découpage en jetons ne peut pas distinguer d'une chaîne.

### Le piège à connaître

Trois fois dans ce travail, le code se demandait « de quoi s'agit-il ? » en
comparant un **libellé affiché** à un mot écrit en clair. Traduire le libellé
faisait échouer la comparaison, sans rien casser bruyamment : un bouton
disparaissait, une date s'ajoutait, un moyen de paiement partait en base sous
un nom que le contrôle d'entrée refusait ensuite.

La règle qui en sort : **ce qui s'affiche ne sert jamais à décider**. Un
libellé se traduit ; ce qui identifie — une clé, un drapeau, une ligne de
table — reste stable et voyage à part. `signaux_langue.php` garde les trois
endroits sous surveillance.

## L'antivirus

**Depuis le 2 octobre 2026, le dossier du projet est exclu de Norton**, et les
huit suites tournent d'ici jusqu'au bout. Il n'y a rien de particulier à faire.

Avant cette exclusion, les suites qui ouvrent des sessions par HTTP avec un mot
de passe passaient pour une attaque aux yeux d'une heuristique : Norton les
mettait en quarantaine, parfois **pendant qu'elles tournaient**. Le script
mourait alors en plein milieu, son ménage de fin ne se faisait pas, et des
comptes d'essai restaient en base. Huit fichiers y sont passés en trois jours.

Deux choses retenues de ces trois jours, au cas où l'exclusion disparaîtrait :

- une quarantaine laisse le **nom** du fichier bloqué sous Windows — écrire à
  cette place répond « Permission denied », et l'exclusion n'y change rien. Il
  faut vider la quarantaine depuis Norton pour récupérer le nom, ou en prendre
  un autre ;
- lancées depuis un dossier hors du projet, les mêmes suites tournaient jusqu'au
  bout. C'est le recours si le problème revient.

Si une suite s'arrête au milieu, son ménage de fin n'a pas eu lieu. Vérifier
alors ce qui reste :

    SELECT id, email FROM users WHERE email LIKE '%exemple-test.fr';

puis effacer chaque compte par son adresse, un par un.

## Les garde-fous

Les essais ne travaillent que sur des comptes en `@exemple-test.fr`, et chaque
suppression porte un `WHERE` sur l'adresse ou l'identifiant. Rien n'est jamais
effacé en masse.
