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

**`listes_langue.php`** — les listes que l'application tient pour elle-même (« Révisions »,
« Alternance »). Elles se retrouvent par leur **rôle**, non par leur nom : la liste survit à un
changement de langue et à un renommage, une liste faite à la main — ou venue d'une archive d'avant
le rôle — est adoptée plutôt que doublée, la clé unique tient, et lire ne crée rien. Puis par les
vraies pages : les messages citent le nom de la liste chez *cet* utilisateur, et poser deux fois les
mêmes révisions, dans deux langues, ne crée aucune tâche en double. Six comptes d'essai, effacés à
la fin.

**`titres_langue.php`** — le `<title>` de chacune des 44 pages, dans les quatre
langues. Un titre identique au français est une anomalie, sauf quand il s'écrit
vraiment pareil (« Notifications » en anglais). Un titre est une chaîne seule,
souvent sans accent, que les scanners ne regardent pas : quatre (« Accueil »,
« Cartes », « Tableau », « Recherche ») étaient restés en français partout.
Un compte d'essai, effacé à la fin.

**`visiteur_langue.php`** — quelqu'un **sans compte** lit l'application dans sa
langue : connexion, inscription, mot de passe oublié, liens publics, manifeste,
page hors-ligne. Elle vérifie la lecture d'un en-tête `Accept-Language`
(`en-GB,en;q=0.9`, les qualités, `q=0`, une langue qu'on ne parle pas), le choix
par cookie et ses garde-fous (langue inconnue, retour vers un autre site, jeton
CSRF absent), qu'un compte garde sa langue, et qu'une inscription crée le
compte — et ses matières de départ — dans la langue de la page. Trois comptes
d'essai, effacés à la fin.

**`formats_langue.php`** — les montants, les tailles de fichier et les dates,
écrits à la façon de la langue du compte et lus de même. Elle vérifie les
valeurs exactes dans les quatre langues au caractère près (« 1 234,50 € »,
« €1,234.50 », « 1.234,50 € »), que le **français n'a pas bougé d'un octet**,
qu'une saisie se lit selon la langue (« 1,234 » est un millier en anglais, une
décimale en français), qu'un relevé de banque garde sa convention, puis passe
par la page du budget. Un compte d'essai, effacé à la fin.

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

**`relecture.php`** — le contrôle **mécanique** de la qualité des textes anglais,
espagnols et allemands, à lancer après toute retouche de `lang/*.php`. Il compare chaque phrase à
son original français et signale : une variable `{n}` ou une balise qui a changé, un reste de
français (un accent dans l'anglais, « pour », « avec »…), une phrase identique au français, une
ponctuation finale ou un emoji de tête qui diffère, une espace avant « : ; ! ? » (habitude française,
sauf dans le texte aligné de la sauvegarde et le tableau CSV à coller), des guillemets ou des
apostrophes à la mauvaise forme, le vouvoiement (« Sie », « usted »), un « ? » espagnol sans « ¿ »,
une phrase dont la longueur s'écarte trop de l'original. Il sort avec le code 1 s'il y a des
signalements, dont certains sont des faux amis voulus à lire plutôt qu'à corriger d'office.

    php outils/langue/relecture.php             tout, en détail
    php outils/langue/relecture.php de          une langue
    php outils/langue/relecture.php --resume    le nombre de signalements par catégorie

### Ce que la relecture ne remplace pas

Elle trouve ce qui se compte. Elle ne trouve pas qu'une phrase est **correcte mais fausse de ton** :
« Sage hallo » ou « Say hello » sont justes, et pourtant un locuteur natif dirait peut-être autre
chose ; elle ne sait pas non plus si « Lernblatt » est le mot qu'un lycéen allemand emploierait
pour une fiche de révision. La relecture manuelle de toutes les clés (3 895 × trois langues) a
corrigé des fautes de vocabulaire, de registre, de grammaire (« 1 more months »), des calques du
français (« poser », « partir », « renvoi ») et des genres supposés (« ihm », « er » pour quelqu'un
dont on ne sait rien) — mais c'est la relecture d'une seule personne, qui n'est native d'aucune des
trois langues. **Une relecture par un locuteur natif de chacune reste la seule façon de trancher
l'idiome et le ton.**

Choix de style tenus partout : anglais britannique (« colour », « Log in »), espagnol en « tú »
(« curso », « Alternancia », jamais « compartición »), allemand en « du » (« Lernblatt »,
« Frist », « Typ » pour un type, « anheften » pour épingler, jamais « anpinnen »). Le français, lui,
vouvoie : c'est l'original, on n'y a pas touché.

## Ce qui reste en français, et qui doit y rester

La traduction est complète : chaque chaîne existe dans les quatre langues, et elle a été relue
(voir ci-dessus). `scan3.php` signale encore une quarantaine de lignes,
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
- **`Focus::NOM_HISTORIQUE`** et **`Alternance::NOM_HISTORIQUE`** (« Révisions », « Alternance ») —
  le nom français que portaient ces listes avant d'avoir un rôle. Il ne sert qu'à reconnaître,
  et à adopter, une liste venue d'une archive ancienne.
- **`Langue::LANGUES`** — « Français », « English », « Español », « Deutsch » :
  chaque langue se nomme dans la sienne, c'est le propre d'un menu de langues.
- **« Mes Cours »**, le nom de l'application, et ce qui en dérive : les
  `PRODID` des fichiers iCalendar, le `/Producer` des PDF, le nom du fichier de
  sauvegarde, l'adresse d'expédition des e-mails.
- **Un commentaire JavaScript dans un `heredoc`** (`NotificationsController`) —
  du code, que le découpage en jetons ne peut pas distinguer d'une chaîne.

### D'où vient la langue d'une page

`Langue::courante()` cherche, dans cet ordre :

1. **le compte**, s'il y en a un connecté (`users.langue`) ;
2. **le choix du visiteur**, gardé dans le cookie `MESCOURS_LANGUE` ;
3. **son navigateur**, par l'en-tête `Accept-Language` ;
4. le **français**.

Le sélecteur de langue des pages sans compte (`views/_choix_langue.php`) poste
vers `POST /langue`, qui pose le cookie et revient à la page d'où l'on vient.
Le cookie est purement fonctionnel : il ne retient que ce choix, pour un an.
Il suit aussi le compte — posé à la connexion, à l'inscription et à chaque
changement de langue dans « Mon compte » — pour que la page de connexion reste
dans la langue de celui qui vient de se déconnecter.

Les réponses qui en dépendent portent `Vary: Accept-Language, Cookie`.

Une inscription crée le compte dans la langue de la page, et ses matières, types
d'évènement et catégories de départ de même.

### Les formats de chaque langue

Les séparateurs, la place du symbole et l'ordre d'une date vivent dans les
fichiers de langue (`fmt.decimal`, `fmt.milliers`, `fmt.monnaie`, `fmt.taille.*`,
`date.courte`, `date.jour_mois`), pas dans le code. Pour en changer un, c'est
une ligne par langue — rien d'autre à toucher.

Un choix à connaître : **l'anglais suit l'usage britannique** (`d/m/Y`, « €1,234.50 »),
l'application comptant en euros. Pour le format américain, `date.courte` vaut
`m/d/Y` dans `lang/en.php`.

Les dates en toutes lettres suivent la même règle (`date.longue`, `date.jour_mois_court`,
`date.plage_mois`) : « 4 octobre 2026 », « 4 October 2026 », « 4 de octubre de 2026 »,
« 4. Oktober 2026 » — le point de l'ordinal allemand, le « de » espagnol.

Côté code : `montant_lisible()`, `taille_lisible()` et `date_numerique()` (dans
`src/helpers.php`) ; côté script, `nombreLocal()` et les clés `js.taille.*`.
La saisie d'un montant passe par `montant_depuis_saisie()`, qui lit selon la
langue en cours ; `ReleveCsv` lui impose le français, parce que le fichier d'une
banque ne change pas avec la langue de la page.

### Le contrôle visuel

Les essais lisent le HTML ; ils ne voient pas une mise en page. L'allemand est souvent
30 % plus long que le français : un bouton qui ne tient plus, un titre qui sort de
l'écran. Pour le vérifier :

1. `php outils/langue/controle_visuel_prepare.php` — crée un compte d'essai garni de
   données (des titres longs, c'est ce qui déborde) et affiche l'identifiant de sa
   session. Le mot de passe de personne n'est saisi.
2. Dans le navigateur, sur une page de l'application : poser ce cookie
   (`document.cookie = "MESCOURS_SESSID=…; path=/mon_appli/appli"`) et recharger.
3. Coller `controle_visuel.js`, puis `await controleVisuel(375, [...pages])`. Chaque page
   s'ouvre dans un iframe de la largeur demandée ; le mesureur rend ce qui déborde, ce qui
   est coupé, et les boutons passés sur deux lignes.
4. Changer la langue du compte en base entre deux mesures, et ne regarder que ce qui
   apparaît dans la seconde. Puis **regarder une capture** : le mesureur ne voit ni un
   chevauchement, ni une hiérarchie qui se brouille.
5. `php outils/langue/menage.php --efface` supprime le compte.

Fait le 4 octobre 2026 : 43 pages × 4 langues, à 375, 768 et 1280 px. Un seul vrai
débordement, qui existait déjà en français : la vue semaine du calendrier (le groupe
flèches + titre ne se repliait pas, et « Ganztägig » était coupé). Corrigé.

### Ce que `scan3.php` ne voit pas

`scan3.php` ne signale une chaîne que si elle porte un accent ou un mot
grammatical du français. Il laisse donc passer, et c'est arrivé :

- les phrases **sans accent ni mot de liaison** (« Code d'inscription incorrect »,
  « Mois invalide », « Nouveau mot de passe ») ;
- les **guillemets français assemblés à la main** (`'« ' . $x . ' »'`) — utiliser
  `guillemets()` ;
- les **attributs HTML statiques** (`<optgroup label="Dépenses">`) : `strip_tags`
  les efface avant l'examen ;
- les **noms de mois passés par `strtolower()`** : « october 2026 », « oktober » —
  utiliser `nom_mois_en_phrase()`, qui suit `date.mois_minuscule`.

Quand on ajoute un libellé, ne pas se fier au silence du scanner : chercher
aussi à la main, ou relire avec un détecteur plus large (toute chaîne à deux
mots d'au moins trois lettres).

### Les listes que l'application crée pour vous

« Révisions » et « Alternance » naissent au premier usage. Leur nom est écrit dans la langue du
compte à cet instant (`liste.revisions`, `liste.alternance`), puis c'est une donnée comme une autre :
on peut la renommer, et un changement de langue ne la retraduit pas.

Ce qui les identifie est la colonne **`listes_taches.role`** (`revisions`, `alternance`, `NULL` pour
toutes les autres), unique par compte. `liste_systeme()` et `nom_liste_systeme()`, dans
`src/helpers.php`, sont les deux seuls points d'entrée : ne jamais retrouver une liste par son nom.

- Migration : `sql/migration-listes-systeme.sql`, à exécuter une fois sur une base déjà installée.
  `schema.sql` porte la colonne pour une installation neuve.
- Une liste du même nom, sans rôle, est **adoptée** (on lui donne son rôle) au premier besoin :
  c'est ce qui absorbe une liste faite à la main, ou une archive d'avant le rôle.
- Si le nom de départ est déjà pris par l'autre liste de l'application, la nouvelle s'appelle
  « Nom (2) ».
- Les tâches de révision espacée portent un titre traduit (`foc.revoir`) et se reconnaissent sous
  les quatre écritures : changer de langue ne les repose pas en double.
- **Limite connue** : les étapes d'alternance (`Alternance::poserTaches`) se reconnaissent par leur
  titre, tel qu'il est écrit au moment de la pose. Reposer les mêmes étapes après un changement de
  langue en crée une seconde série — visible, et supprimable depuis la liste.

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

## Ces outils ne sont pas joignables par le web

Le `.htaccess` du projet répond 404 à tout ce qui passe par `outils/` et par `.git`. Ce
n'était pas le cas : jusqu'au 4 octobre 2026, `menage.php`, `reste.php` et `base.php`
répondaient 200 à une adresse, et le dépôt git était lisible (`.git/HEAD`, `.git/config`).
Sur un site hébergé depuis ce dossier, n'importe qui aurait pu lancer ces scripts — certains
créent et effacent des comptes — ou télécharger tout le code et son historique.

Les scripts se lancent en ligne de commande (`php outils/langue/….php`), que la règle ne
touche pas. Si un jour on ne parvient plus à les ouvrir par HTTP, c'est voulu.

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

**`theme_langue.php`** — l'apparence et la langue : leurs formulaires n'ont plus d'envoi automatique, « Valider » est un
vrai bouton, rien n'est enregistré avant lui, et le bouton se lit dans les quatre langues. L'aperçu
en direct et « Annuler » sont du JavaScript : ils se vérifient dans un navigateur. Un compte d'essai,
effacé à la fin.

Pour la langue, l'aperçu est servi par le serveur (`compte?apercu_langue=xx`), seul à savoir traduire :
choisir une langue rouvre « Mon compte » dedans, formulaire ouvert, sans rien écrire en base ; « Valider »
enregistre, « Annuler » est un simple lien vers la page sans aperçu. Une valeur inconnue est ignorée.

**`gemini_langue.php`** — l'espace « Clé API Gemini » de « Mon compte » : le champ (mot de passe, jamais
prérempli), le refus d'une saisie absurde et d'un envoi sans jeton CSRF, la clé chiffrée en base (jamais
en clair, attachée à sa ligne), le message qui ne cite que ses quatre derniers caractères, une clé par
compte, l'absence de la clé dans l'archive de sauvegarde (ouverte et lue : c'est un zip), le retrait, et
la disparition de la clé avec le compte. Deux comptes d'essai, effacés à la fin.

**`faux_gemini.php`** — un faux Gemini pour les essais (`php -S 127.0.0.1:8765 outils/langue/faux_gemini.php`). Il répond
comme l'API selon le début de la clé qu'on lui présente (`cle-bonne…`, `cle-quota…`, `cle-mauvaise…`, `cle-panne…`,
`cle-modele…`, `cle-vide…`, `cle-wav…`) et note chaque requête reçue. Aucune vraie clé, aucun appel chez Google.

**`gemini_client.php`** — le client `src/Gemini.php`, le Markdown et la consigne, contre ce faux serveur : la clé part dans
un en-tête et jamais dans l'adresse, les refus se classent (clé, quota, modèle, panne, réseau, contenu bloqué), un modèle
inconnu passe au suivant, une adresse étrangère est ignorée (la clé ne part que chez Google ou sur le poste), le son brut
se met dans un WAV bien formé et un WAV déjà fait se lit sans supposer 44 octets d'en-tête, le HTML glissé dans un texte
n'est jamais interprété.

**`resumes_langue.php`** — l'espace « Résumés » de bout en bout, toujours contre le faux serveur (l'application y est
redirigée par `config/parametres.test.php`, que `Config` ne lit que depuis le poste et que la suite retire) : sans clé,
le formulaire et son avertissement, les demandes refusées (aucun cours, cours d'un autre, sans jeton), ce que Gemini reçoit
(texte du cours, fiche, balisage, consigne, clé), le choix des sources, la voix (fichier rangé, lecture par morceaux,
remplacement), le cloisonnement entre comptes, les refus de Google dits clairement, les quatre langues, l'effacement.

**`cartes_mentales_langue.php`** — les cartes mentales d'un cours, de bout en bout (même faux serveur que les résumés). Elles
se créent depuis « Résumés IA » (case « Carte mentale », ou carte vierge) et se retrouvent dans la fiche de révision :
la fiche (rayon, lien, pas de bouton), la page Résumés (sans clé, avec clé), la création vierge, l'enregistrement et surtout le nettoyage de ce qui arrive
(texte sur une ligne, idées vides écartées, 120 lettres, 6 niveaux, 250 idées, JSON invalide refusé, jeton CSRF), le
cloisonnement entre comptes, les cartes écrites par l'IA (ce que Gemini reçoit, le schéma d'objet, le HTML glissé dedans
échappé, une carte seule, avec un résumé, pour deux cours, un modèle qui répond en prose, une clé refusée), la suppression (et en cascade avec le cours), l'aller-retour par
la sauvegarde du compte, les quatre langues. L'éditeur lui-même (assets/js/carte-mentale.js) se contrôle dans un navigateur :
il n'a pas de test automatique.

**`diaporamas_langue.php`** — les diaporamas commentés, de bout en bout contre le faux serveur : sans clé, l'écriture depuis
« Résumés IA » (case « Diaporama commenté », ce que Gemini reçoit, le schéma, la longueur → nombre de diapositives, la
diapositive vide écartée, le HTML glissé réduit à du texte, avec un résumé dans la même demande), le lecteur (page entière et
fenêtre, plan en texte sans JavaScript), la voix fabriquée une diapositive à la fois (fichier WAV, remplacement, l'ancien
effacé), le cloisonnement entre comptes, une limite atteinte ou une réponse en prose, les quatre langues, l'effacement et
celui des voix sur le disque, et le rangement dans la fiche de révision (rayon « Diaporamas », ajout / retrait, refus des cours non lus
ou d'un autre compte, PDF téléchargé et joint, quatre langues, disparition avec le diaporama). Le lecteur (assets/js/diaporama.js : lecture par la voix du navigateur, repli sans voix,
boucle de fabrication) se contrôle dans un navigateur : il n'a pas de test automatique.

**`menu_langue.php`** — le menu en grille de la barre (comme les applications de Google) : ses 15 tuiles (chacune mène à la route de
l'onglet du même nom), les favoris de départ, les favoris choisis (ordre gardé, clés inconnues et doublons écartés, JSON abîmé sans
conséquence), le jeton CSRF, le cloisonnement entre comptes, les quatre langues. L'édition au crayon et la fermeture se contrôlent
dans un navigateur (assets/js/menu-apps.js n'a pas de test automatique). Même suite : la section « Mes applications » — liens vers d'autres sites
ajoutés par chacun : adresses acceptées (https ajouté, hôte en minuscules) et surtout REFUSÉES (javascript:, data:, ftp:, file:, identifiants dans
l'adresse, vide…), limites (40 lettres, 24 liens), texte échappé, nouvel onglet sans opener, modifier / supprimer, cloisonnement entre comptes,
ligne abîmée en base ignorée, sauvegarde du compte, quatre langues.

**`sondages_langue.php`** — les sondages des discussions (entre amis et de groupe) : validation de ce qu'on envoie (question vide ou trop longue, moins de deux ou plus de douze options,
option trop longue, doublons, caractères de contrôle, jeton CSRF), créer entre amis et dans un groupe, refus hors amitié et hors groupe, voter (plusieurs réponses ou
une seule, changer d'avis, retirer son vote, réponse inexistante), le vote visible chez l'autre par le relevé des changements, qui a voté quoi, cloisonnement (un étranger,
un message caché ou supprimé ne vote pas), sondage non modifiable, citation et aperçu de liste, suppression pour tous (options et votes effacés avec lui), quatre langues.
Le dessin des options et la fenêtre de création (assets/js/sondages.js) se contrôlent dans un navigateur : ils n'ont pas de test automatique.

**`partages_evenements_langue.php`** — l'évènement qu'un ami partage, et son cours : la matière d'un cours partagé (puce de sa couleur, bouton « Ajouter à mes matières », pas de doublon,
inconnu refusé, jeton CSRF), la copie du cours ou de l'évènement qui reprend la matière quand on l'a déjà, le lien vers le cours lié (seulement s'il est partagé aussi, jamais un cours gardé
pour soi), « Mes rappels et mes notes » (délais inconnus écartés, note trop longue refusée, le propriétaire et les inconnus exclus), la note lisible par le propriétaire mais pas par
les autres amis, l'évènement du propriétaire intact, les rappels qui partent à l'heure (via notifications/battement avec un faux appareil abonné) et cessent quand l'accès est retiré, quatre langues.
