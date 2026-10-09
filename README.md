# Mes Cours

Application web d'**études tout-en-un** : ranger ses cours et ses fichiers, réviser (fiches, cartes, résumés,
assistant IA), planifier son année dans un calendrier, tenir ses comptes, suivre son alternance, travailler en
groupe et discuter avec ses amis — dans une seule interface, en **quatre langues** (français, anglais, espagnol,
allemand) et avec un thème clair ou sombre.

PHP 8.3 + MySQL, sans aucune dépendance externe : pas de Composer, pas de CDN, pas de Node. Seules sortent de la
machine les fonctions que vous branchez vous-même (e-mail, Gemini, Outlook, Google).

---

## Fonctionnalités

### Étudier

- **Cours** — un texte riche et autant de pièces jointes qu'on veut (PDF, images, Word, PowerPoint, Excel, texte,
  audio, vidéo, archives ; 200 Mo par fichier), avec aperçu des documents et lecteur audio/vidéo qui reprend là où on
  s'est arrêté. Un cours a une **matière**, des **tags**, peut être rangé dans un **dossier** et marqué en favori ;
  une recherche unique parcourt les cours et le calendrier.
- **Révision** — la fiche de révision de chaque cours, avec un état choisi (à réviser, en cours, révisée), une barre
  d'avancement au total et par matière, des anneaux d'avancement sur les enregistrements, et une version imprimable
  ou exportable en PDF.
- **Cartes de révision** — un paquet par cours, des cartes **proposées** à partir du cours, de sa fiche et de ses
  pièces jointes (c'est vous qui retenez celles qui valent la peine), et des séances selon la méthode de **Leitner**.
- **Cartes mentales** — plusieurs par cours, éditées à la main ou proposées par l'IA.
- **Mode focus** — une session de révision minutée, sur un écran sans rien d'autre ; on mesure le temps réellement
  travaillé, on fixe un objectif et on planifie la suite.
- **Résumés IA** — des résumés écrits par l'IA à partir de vos cours, de vos fichiers ou d'un enregistrement audio,
  qui peuvent aussi produire des cartes mentales et des **diaporamas commentés** à voix de synthèse.
- **Assistant IA** — des discussions avec Gemini, avec un de vos cours en contexte si vous le voulez, et un historique
  en base (compris dans la sauvegarde du compte). Fonctionne avec **votre propre clé Gemini** (voir plus bas), avec un
  guide pas à pas pour l'obtenir.

### Planifier

- **Accueil** — ce qui est prévu aujourd'hui et les 7 prochains jours, les échéances avec compte à rebours, les cours
  récents, les travaux de groupe.
- **Calendrier** — vues mois, semaine et liste ; types d'évènements personnalisables ; couleurs par matière ;
  évènements cochables comme terminés, rattachables à un cours, **répétables**, avec **plusieurs rappels** ; clic sur
  `+` dans une case pour créer. On peut y afficher le calendrier d'amis qui l'ont partagé.
- **Tâches** — des listes, des tâches datées, des sous-tâches, des cases à cocher ; une tâche cochée descend dans les
  « terminées » sans disparaître.
- **Tableau** — un kanban en quatre colonnes qui relit vos sous-tâches et vos évènements (rien n'est recopié, donc rien
  ne se désynchronise).
- **Agendas externes** — **Outlook (Microsoft)** et **Google Agenda** se relient chacun à votre compte, et la
  synchronisation se fait dans les deux sens.
- **Rappels et notifications** — notifications du navigateur ou du téléphone (même application fermée, avec la tâche
  planifiée) pour les évènements, tâches, messages, demandes, groupes, partages, travaux de groupe et journal ; chaque
  sorte se coupe ou se suspend à volonté.

### Collaborer

- **Amis** — recherche par pseudo, demandes, blocage ; discussions à deux avec texte, **photos**, **fichiers**,
  **messages vocaux** (avec transcription si vous la laissez activée), réponses, réactions, modification et
  suppression de messages, **messages épinglés**, recherche dans la conversation, fond d'écran partagé.
- **Sondages** — dans toute discussion : réponse unique ou multiple, résultats en direct.
- **Groupes** — discussions de groupe avec administrateurs, invitations par pseudo, photo et fond du groupe.
- **Serveurs** — façon Discord : un espace avec ses **salons**, ses membres et ses rôles (propriétaire,
  administrateurs, membres), un logo (photo, ou initiales sur la couleur de son choix), des invitations d'amis à
  accepter ; les serveurs se rangent par glisser-déposer dans une barre à gauche de la messagerie.
- **Partages** — partager un cours, un fichier, un évènement ou plusieurs éléments d'un coup avec des amis (lecture
  seule ou avec droit de modifier) ou par **lien public** ; ce qu'on vous partage se copie dans votre espace (l'application
  reconnaît ce qui est déjà copié), et un évènement partagé peut recevoir vos propres rappels et notes.
- **Travaux de groupe** — un projet par groupe de travail, en cinq onglets : qui fait quoi, échéances (placées aussi
  dans le calendrier), fichiers, document commun, membres ; des cours et dossiers liés que chacun peut ajouter à son
  espace, une discussion liée, et un lien public en lecture.

### Gérer sa vie d'étudiant

- **Budget** — opérations, prévisions, remboursements, import de relevés bancaires et d'anciens classeurs Excel,
  catégories avec plafonds, budget proposé (voir plus bas).
- **Alternance** — notes (épinglables, avec création de tâches), **rythme** école/entreprise (périodes, import, flux
  `.ics`), **journal** des missions avec export PDF et suivi des compétences, **documents** classés par catégorie,
  fiche de l'entreprise avec rétroplanning et échéances posées au calendrier.

### Personnaliser et protéger son compte

- **Menu en grille** (9 points) avec des **favoris** réorganisables par glisser-déposer et des **liens vers d'autres
  applications** que vous ajoutez, avec leur logo.
- **Apparence** claire, sombre ou celle de l'appareil ; **langue** choisie par compte ; pseudo, **photo de profil**
  (qu'on peut agrandir d'un clic partout), fuseau horaire.
- **Application installable** sur l'écran d'accueil, avec une page de secours hors ligne.
- **Mot de passe oublié** par e-mail, **sauvegarde et restauration** complètes du compte en un fichier `.zip`.

---

## Installation

1. **Démarrer WAMP** et attendre que l'icône passe au vert (Apache + MySQL actifs).

2. **Créer la base de données.** Deux possibilités :

   - En ligne de commande :

     ```bash
     "C:/wamp64/bin/mysql/mysql8.4.7/bin/mysql" -u root < "C:/wamp64/www/mon_appli/appli/sql/schema.sql"
     ```

   - Ou depuis phpMyAdmin (<http://localhost/phpmyadmin5.2.3/>) : onglet **Importer** → choisir `sql/schema.sql` → **Exécuter**.

   `schema.sql` crée la base `mon_appli_cours` (lignes 4 à 7 : `CREATE DATABASE` et `USE`) puis toutes les tables d'un
   coup : il est à jour de toutes les migrations, **n'en lancez aucune** sur une installation neuve. Chez un hébergeur
   qui vous impose un autre nom de base, supprimez ces quatre lignes dans une copie avant d'importer.

3. **Vérifier les identifiants MySQL** (par défaut WAMP : utilisateur `root`, mot de passe vide).

   Les réglages par défaut sont écrits en haut d'`index.php` (port 3306, jeu de caractères `utf8mb4`, nom de
   l'application…), **sans aucun identifiant de base**. Pour les vôtres, créez `config/parametres.php`, qui a la priorité
   et n'entre pas dans le dépôt puisqu'il porte vos identifiants :

   ```php
   <?php
   return [
       'db' => ['host' => '127.0.0.1', 'name' => 'mon_appli_cours', 'user' => 'root', 'pass' => ''],
   ];
   ```

   Le fichier ne contient que ce qu'il change : chaque section complète celle d'`index.php` au lieu de la remplacer.
   Tous les réglages possibles sont décrits dans [Réglages](#réglages).

4. **Ouvrir l'application** : <http://localhost/mon_appli/appli/>

5. **Créer son compte** depuis la page d'inscription. Quatre matières d'exemple sont créées automatiquement,
   modifiables ensuite.

---

## Utilisation

| Section | À quoi ça sert |
|---|---|
| **Accueil** | Ce qui est prévu aujourd'hui, les 7 prochains jours, les échéances, les cours récents, les travaux de groupe. |
| **Calendrier** | Vue **mois**, **semaine** ou **liste**. Filtres par matière et par type. Clic sur `+` dans une case pour créer un évènement à cette date. Évènements répétables, avec plusieurs rappels, rattachables à un cours, partageables avec un ami. |
| **Mes cours** | Liste filtrable (recherche, matière, tag, dossier, favoris, tri) et création de cours. Chaque cours a un bouton **Révision** qui ouvre sa fiche dans un volet. |
| **Partagés** | Ce que vos amis ont partagé avec vous (cours, fichiers, évènements) et ce que vous partagez, avec les liens publics et leurs droits. |
| **Révision** | Toutes les fiches de révision réunies, groupées par matière, avec un extrait et le compte de ce qui y est rattaché. Recherche (texte, liens, noms de fichiers joints), filtre par matière et tri par date de modification. Une barre d'avancement en tête indique où en sont les révisions, au total et matière par matière — chaque fiche porte un état choisi par vous (à réviser, en cours, révisée), une fiche en cours comptant pour moitié. Chaque enregistrement joint porte un **anneau d'avancement** : le lecteur reprend là où on s'est arrêté, et l'anneau dit quelle part on en a écoutée ou regardée. Un clic ouvre la fiche seule — le titre du cours en tête, sans son contenu ni ses pièces jointes — imprimable en une page propre. Les cours sans fiche sont listés à part. |
| **Cartes** | Les paquets de cartes de révision par cours, les cartes proposées, et les séances de révision (méthode de Leitner). Les cartes mentales se retrouvent dans la fiche du cours. |
| **Résumés** | Résumés écrits par l'IA à partir de vos documents ou d'un audio, cartes mentales et diaporamas commentés. Rien ne part chez Google que ce qui a été coché, et seulement au clic sur « Générer ». |
| **Assistant IA** | Discussions avec Gemini (un cours en contexte si on le souhaite) et leur historique. |
| **Tâches** | Listes, tâches datées, sous-tâches, cases à cocher. |
| **Tableau** | Le kanban de tout ce qu'il y a à faire, en quatre colonnes (à faire, en cours, validation, terminé). |
| **Alternance** | Notes, rythme école/entreprise, journal des missions, documents, fiche de l'entreprise. |
| **Groupes** | Les travaux de groupe : liste de vos projets, invitations, et chaque projet en cinq onglets. |
| **Budget** | Cinq onglets : **Opérations** (recettes et dépenses du mois, tendance sur 12 mois), **Prévisions** (solde de départ, charges fixes, solde prévisionnel reporté de mois en mois), **Remboursements** (ce qu'on vous doit), **Import** (relevé bancaire au format CSV, ou ancien classeur `.xlsx`) et **Catégories** (avec plafond mensuel). |
| **Organisation** | Trois onglets : **Matières** (nom, couleur, enseignant), **Types d'évènement** (nom, icône, couleur, ordre, indicateurs « échéance » et « paraît sur le tableau ») et **Tags** (créer, renommer, fusionner, supprimer). |
| **Amis** | La messagerie : discussions à deux, groupes (repliables), **serveurs** dans la barre de gauche, demandes d'amis et invitations. |
| **Mon compte** | Apparence, langue, clé Gemini, pseudo, photo, fuseau horaire, partage du calendrier, mot de passe, notifications, installation hors ligne, sauvegarde et restauration. |
| **Recherche** | Cherche simultanément dans les cours et dans le calendrier, avec surlignage des termes. |

Le menu se range comme on veut : le bouton à neuf points ouvre une grille avec vos **favoris** puis toutes les
sections, réorganisables par glisser-déposer, plus des **liens vers d'autres applications** que vous ajoutez vous-même.

### Types d'évènement

Cinq types sont créés avec le compte (📘 Cours, 📝 Examen, 🗂️ Devoir, 🔁 Révision,
📌 Autre) et sont entièrement modifiables depuis la page **Types** : renommer,
changer l'icône et la couleur, réordonner, ajouter les vôtres, supprimer.

Un type marqué « échéance » fait apparaître ses évènements sur l'accueil avec un
compte à rebours. Un type qui **paraît sur le tableau** y envoie ses évènements ;
décoché, ils restent au calendrier mais quittent le tableau — c'est le réglage
d'origine de « Cours », un cours au programme n'étant pas une chose à faire.
Supprimer un type ne supprime pas les évènements : ils passent simplement en
« Sans type ».

La couleur d'un évènement au calendrier est celle de sa matière ; à défaut, celle
de son type. Chaque évènement peut être coché comme terminé (☐ / ☑) et rattaché à
un cours, pour retrouver ses notes le jour J.

### Tags

Un cours porte autant de tags qu'on veut, là où il n'a qu'une seule matière.
Un tag saisi dans le champ « Tags » d'un cours est créé s'il n'existe pas ;
les tags déjà connus sont proposés en autocomplétion.

La page **Tags** permet de les créer à l'avance (plusieurs d'un coup, séparés par
des virgules), de les renommer, de **fusionner** deux tags en un seul — pratique
pour réunir deux écritures d'une même idée — et de supprimer d'un clic tous ceux
qui ne sont sur aucun cours. Supprimer un tag ne supprime jamais les cours : il
leur est simplement retiré.

### Budget

La section **Budget** est indépendante des cours : elle sert à tenir ses comptes.
Chaque opération a un intitulé, un montant, une date, un sens (dépense ou recette),
une catégorie et un moyen de paiement. Les montants s'écrivent comme on veut :
`12,50`, `12.50`, `1 234,56` ou `12 €` sont tous acceptés.

La page affiche les totaux du mois (recettes, dépenses, solde), la répartition des
dépenses par catégorie et la tendance des douze derniers mois. Une catégorie de
dépense peut recevoir un **plafond mensuel** : une jauge se remplit et le
dépassement est signalé.

Douze catégories sont créées avec le compte et se modifient depuis l'onglet
**Catégories**. Supprimer une catégorie ne supprime pas les opérations : elles
passent en « Sans catégorie ».

### Remboursements

L'onglet **Remboursements** suit ce que vous avancez pour quelqu'un d'autre.
Une dépense se coche à deux endroits : la case **🧾 À me faire rembourser** du
formulaire d'ajout, pour le décider dès la saisie, et l'icône 🧾 de la liste des
opérations, pour le décider après coup. La case cochée déplie « par qui » et la
part à réclamer ; le formulaire de modification y ajoute le statut et la date de
remboursement. Une dépense décochée reste dans le budget, seul son suivi
disparaît.

Le récapitulatif se lit **un mois à la fois**, comme un relevé mensuel tenu à la
main : les lignes sont **groupées par catégorie avec un sous-total**, suivies du
total du mois. Rien n'est cumulé d'un mois sur l'autre. On passe d'un mois au
suivant avec les flèches, ou directement par les raccourcis en bas de page, qui
rappellent le total de chaque mois renseigné.

Trois choses se règlent ligne par ligne :

- **La part réclamée**, quand elle diffère de ce que vous avez payé — une essence
  partagée en deux se réclame pour la moitié. La colonne affiche alors les deux
  montants.
- **Qui rembourse**, en texte libre, avec les noms déjà utilisés en suggestion.
  Un filtre permet d'éditer un récapitulatif par personne.
- **Le statut** : à réclamer, remboursé (avec sa date), ou **hors total** pour
  garder une ligne sous les yeux sans la compter.

### Déclarer un mois remboursé

Quand l'argent arrive, un bouton **« Oui, X € remboursés »** solde le mois d'un
coup : toutes ses lignes encore à réclamer passent à « remboursé », et **une
recette du même montant est ajoutée aux opérations**, datée par défaut du 1er du
mois suivant. L'argent rendu réapparaît donc dans le budget du mois où il
arrive, et alimente le solde prévisionnel.

La date et la catégorie de cette recette se choisissent au moment de confirmer.
Un mois déjà réglé affiche un bandeau vert avec un lien vers la recette créée,
et un bouton **Annuler le règlement** qui remet les dépenses à réclamer et
supprime la recette.

Un bouton marque aussi d'un coup les seules lignes cochées comme remboursées,
sans créer de recette, pour les remboursements partiels.

Deux façons de sortir le récapitulatif :

- **Exporter en Excel** produit un vrai fichier `.xlsx` pour le mois affiché,
  mis en forme comme un relevé tenu à la main : un bloc par rubrique avec son
  sous-total, le total du mois, et les sections « Pas dans le total » et
  « Reste à rembourser ». Un classeur par mois, comme les fichiers d'origine. Dates et montants y sont de vrais types Excel, calculables.
  Le fichier est généré sans bibliothèque externe.
- **Imprimer** produit une version propre à l'écran, sans boutons ni navigation,
  à enregistrer en PDF.

### Budget proposé

Au bout de trois mois de dépenses classées par catégorie, l'onglet **Catégories**
affiche une proposition de budget pour chaque poste : essence, sorties, courses…
Avant cela, une jauge indique combien de mois manquent.

C'est **une fourchette, pas un chiffre**. La valeur centrale est la médiane des
mois écoulés, moins sensible qu'une moyenne à un mois exceptionnel. La marge est
calculée sur l'écart réel entre les mois : un poste régulier donne une fourchette
serrée, un poste en dents de scie une fourchette large. Un minimum de 10 %
empêche qu'un poste parfaitement stable retombe sur une valeur fixe.

Sont affichés en regard le nombre de mois observés et le minimum et le maximum
réels, pour juger sur pièces. En dessous de cinq mois, l'estimation est signalée
comme fragile. Le mois en cours, forcément incomplet, est exclu du calcul.

Un bouton reprend la valeur conseillée comme **plafond mensuel** de la catégorie,
une par une ou toutes d'un coup ; les plafonds restent modifiables à la main.

### Importer un relevé bancaire

L'onglet **Import** préremplit les opérations du mois à partir du fichier CSV
exporté par votre banque, sans remplacer la saisie manuelle.

Le parcours se fait en deux temps. On dépose le fichier, puis un **aperçu** montre
ce qui a été compris : dates, libellés, montants, sens, et une catégorie devinée
quand le libellé contient le nom d'une des vôtres. Rien n'entre en base avant
validation ; on coche et décoche les lignes, on corrige la correspondance des
colonnes si la détection s'est trompée.

Sont gérés automatiquement : le séparateur (point-virgule, virgule, tabulation),
les dates française ou ISO, un montant signé ou deux colonnes débit/crédit,
l'encodage Windows des exports français, les lignes d'en-tête et le préambule que
certaines banques ajoutent avant le tableau.

Les **doublons sont repérés** par une signature date + montant + libellé :
réimporter un relevé qui chevauche le précédent ne crée pas de lignes en double.
La signature reste celle du relevé d'origine même après modification, ce qui évite
qu'une ligne renommée revienne comme neuve.

Une opération importée est une opération comme une autre : **modifiable et
supprimable**. Un repère 📥 la distingue dans la liste, et un filtre permet de
n'afficher que les lignes importées ou que celles saisies à la main.

### Reprendre ses anciens classeurs

L'onglet **Import** accepte aussi les anciens fichiers de comptes au format
`.xlsx`, ceux tenus à la main avant l'application.

La feuille est lue telle qu'elle est écrite : date, libellé et montant dans les
trois premières colonnes, dépenses réunies en blocs séparés par une ligne vide.
Le nom de la rubrique n'étant pas sur les lignes mais dans les totaux à droite
(« Total essence : », « Total repas pour les parents : »), il en est déduit.

Sont également reconnus :

- **« divisé par 2 »** — seule la moitié est réclamée, et le reliquat d'arrondi
  est reporté pour que le bloc retombe exactement sur votre chiffre ;
- **« pas dans le total » / « à voir avec … »** — le bloc concerné est repris
  avec le statut « hors total », sans contaminer les blocs voisins ;
- plusieurs mois dans une même feuille.

Avant validation, **le total recalculé est comparé à celui inscrit dans votre
feuille**, mois par mois. Un écart est signalé plutôt que corrigé en silence :
il vient en général d'une ligne que vous comptiez autrement.

Les rubriques trouvées se rattachent à vos catégories, ou sont créées. Les
lignes reprises sont cochées « à me faire rembourser », avec le destinataire de
votre choix, et peuvent être marquées comme déjà remboursées.

### Prévisions

L'onglet **Prévisions** répond à une question simple : combien me restera-t-il à
la fin du mois, et le mois d'après ?

Le principe tient en trois temps :

1. **Un solde de départ** saisi une fois, le montant réellement sur le compte.
2. **Les charges fixes et revenus réguliers** (loyer, abonnements, bourse…), avec
   leur jour du mois. Ils sont comptés automatiquement chaque mois.
3. **Les dépenses variables**, ajoutées au fil de l'eau dans l'onglet Opérations.

Le solde prévisionnel se calcule en continu, et **devient le solde de départ du
mois suivant**, qui à son tour alimente le suivant. Un tableau projette les six
prochains mois, et **une courbe du solde** montre la trajectoire : trait plein
sur les mois écoulés, pointillé sur la prévision, ligne rouge au passage sous
zéro. Le graphique est dessiné côté serveur, sans aucune bibliothèque : il
fonctionne hors ligne, s'adapte au thème clair ou sombre et s'imprime net.

Rien n'est figé en base : tout se recalcule à partir du dernier solde saisi.
Corriger une vieille opération met donc à jour toute la chaîne.

Une charge fixe apparaît « à venir » tant qu'elle n'est pas saisie dans les
opérations réelles. Le bouton ✓ la transforme en opération datée du bon jour ;
elle passe alors « saisie » et **n'est plus comptée deux fois**. Le prévisionnel
ne bouge pas au passage.

À tout moment, on peut **forcer le solde d'un mois** pour se recaler sur le vrai
solde bancaire : les mois suivants repartent de cette valeur, ceux d'avant ne
bougent pas.

### Fichiers joints

PDF, images, Word, PowerPoint, Excel, texte, audio, vidéo, archives —
200 Mo maximum par fichier, plusieurs fichiers à la fois.
Ils sont stockés sous un nom aléatoire dans `storage/uploads`, dossier inaccessible
directement depuis le navigateur : ils ne transitent que par une URL qui vérifie
d'abord que le fichier vous appartient.

---

## Plusieurs utilisateurs

Chaque compte a ses propres cours, matières, tags, fichiers, évènements, tâches et discussions avec l'IA ; rien n'est
partagé par défaut et aucun compte ne peut lire les données d'un autre. Ce qu'on **choisit** de partager (un cours, un
évènement, un fichier, un message, un travail de groupe) n'est visible que des personnes désignées — ou de qui détient
le lien public, quand on en crée un.

Pour fermer les inscriptions une fois tout le monde inscrit, dans le fichier de réglages :

```php
'app' => ['inscription_ouverte' => false],
```

Ou, pour les garder ouvertes mais protégées, un code à communiquer aux personnes concernées :

```php
'app' => ['code_inscription' => 'un-code-de-votre-choix'],
```

---

## Réglages

Les valeurs par défaut sont écrites dans `index.php`. Ce qui change d'une installation à l'autre se met dans **un
fichier de réglages**, qui n'entre jamais dans le dépôt et qui ne contient que ce qu'il modifie :

| Où | Quand |
|---|---|
| `config/parametres.php` | Sur votre poste, ou chez un hébergeur si vous n'avez pas mieux. Le dossier `config/` est fermé par le `.htaccess`. |
| `mes-cours-parametres.php`, **au-dessus** du dossier de l'application (jusqu'à trois niveaux) | **Chez un hébergeur** : le fichier est hors du dossier que le serveur publie (`public_html`), aucune adresse ne peut l'atteindre. S'il existe, il est lu à la place de `config/parametres.php`. |

Un fichier qui ne `return` pas un tableau est ignoré, mais l'incident est écrit dans le journal d'erreurs PHP.

```php
<?php
return [
    'db' => [                                   // la base
        'host' => 'localhost', 'name' => '…', 'user' => '…', 'pass' => '…',
        // 'port' => 3306, 'charset' => 'utf8mb4'    (valeurs par défaut)
    ],
    'app' => [
        'nom'                => 'Mes Cours',
        'adresse_publique'   => 'https://exemple.fr',        // sans chemin : pour les liens des e-mails
        'code_inscription'   => '…',                         // code à fournir pour s'inscrire
        // 'inscription_ouverte' => false,                   // plus aucune inscription
        'administrateurs'    => ['vous@exemple.fr'],         // voient l'adresse d'envoi du cron
        // 'taille_max_fichier' => 200 * 1024 * 1024,        // par fichier
    ],
    'securite' => ['cle_chiffrement' => '…'],    // 32 octets en base64 : chiffre les clés d'API (IA)
    'courriel' => [                              // e-mail « mot de passe oublié »
        'serveur' => 'smtp://smtp.gmail.com:587', 'utilisateur' => '…', 'mot_de_passe' => '…',
        // 'expediteur' => '…', 'nom' => '…'          adresse et nom affichés à l'expéditeur
    ],
    'outlook' => ['client_id' => '…', 'locataire' => 'common', 'secret' => '…', 'adresse_retour' => '…'],
    'google'  => ['client_id' => '…', 'secret' => '…', 'adresse_retour' => '…'],
];
```

Générer la clé de chiffrement (une seule fois, à garder avec vos sauvegardes — la perdre rend illisibles les clés
Gemini enregistrées) :

```bash
php -r "echo base64_encode(random_bytes(32));"
```

---

## Services externes (facultatifs)

Rien de ceci n'est nécessaire pour faire tourner l'application : chaque service disparaît proprement tant qu'il n'est pas réglé.

- **E-mail** — sert uniquement au lien de réinitialisation du mot de passe. Gmail (adresse + « mot de passe d'application »,
  port 587) ou la boîte de votre hébergeur (`smtps://…:465`). Sans réglage, en local, les e-mails sont rangés dans
  `storage/courriels` ; en ligne, l'application refuse de faire semblant d'avoir envoyé.
- **Outlook et Google Agenda** — une seule inscription d'application (Azure, Google Cloud) pour toute l'installation ;
  chacun relie ensuite **son** compte. L'adresse de retour déclarée chez le fournisseur doit être identique au caractère
  près à `adresse_retour` : `…/outlook/retour` et `…/agenda/google/retour`. Un secret client est requis en ligne ; il expire
  (Microsoft : 24 mois au plus) et se renouvelle sans coupure en gardant l'ancien jusqu'au remplacement.
- **IA (Gemini)** — chaque utilisateur enregistre **sa propre clé** (*Mon compte → Clé API Gemini*, avec un guide pas à pas
  illustré) ; elle est chiffrée en AES-256-GCM avec `securite.cle_chiffrement`, jamais réaffichée en entier et exclue
  des sauvegardes. Sans cette clé de chiffrement, la saisie est refusée et les fonctions d'IA restent inactives.
- **Notifications** — les clés d'envoi (VAPID) sont créées toutes seules et gardées en base. Pour que les rappels partent
  application fermée, une **tâche planifiée** doit appeler chaque minute l'adresse d'envoi
  (`…/notifications/envoyer?cle=…`) ; cette adresse, qui porte la clé du site, n'est montrée qu'aux comptes listés dans
  `app.administrateurs` (sur votre poste, seulement à l'ordinateur de l'application). Sur ordinateur, le navigateur
  doit pouvoir tourner en arrière-plan pour recevoir une notification fenêtre fermée ; sur Android et iPhone (site ajouté à
  l'écran d'accueil), le système s'en charge.

---

## Mise en ligne

Un **guide pas à pas** (« Mise en ligne - Mes Cours - guide complet », en PDF, hors dépôt) décrit tout le trajet : exigences
de l'hébergeur, envoi des fichiers, base de données, fichier de réglages, HTTPS, tâche cron, e-mails, liaisons Outlook et
Google, IA, sécurité, sauvegardes, vérifications et dépannage. En résumé :

- **PHP 8.3**, **MySQL 8** (MariaDB n'est pas garantie), `mod_rewrite` et `.htaccess` pris en compte, extensions `pdo_mysql`,
  `mbstring`, `gd` (avec WebP), `curl`, `openssl`, `fileinfo`, `zip`, `dom`/`xml`, `iconv` ; `exif` conseillé.
- **Ne pas envoyer** `outils/` (scripts de test) ni `.git/`, et ne jamais envoyer votre `config/parametres.php` local.
  Vérifier la présence des trois `.htaccess` (racine, `config/` et `storage/`) : ils ferment les dossiers privés.
- Importer `sql/schema.sql` sans ses lignes 4 à 7, ou votre base exportée sans `CREATE DATABASE` ni `USE`.
- Régler dans le panneau de l'hébergeur : `upload_max_filesize = 200M`, `post_max_size = 256M`, `max_execution_time` à
  300 s au moins (les résumés audio sont longs), `display_errors = Off`.
- Activer HTTPS (obligatoire : micro, notifications et cookies sécurisés), renseigner `adresse_publique` et un
  `code_inscription`, programmer la tâche cron.

Pour publier l'application côté Google (liaison Google Agenda ouverte à des amis), Google exige des pages publiques de
**confidentialité** et de **conditions d'utilisation** sur votre domaine.

Deux protections s'activent dès que l'application n'est plus consultée depuis la machine elle-même :

- **Les tentatives de connexion sont limitées.** Cinq échecs sur un compte, ou vingt depuis une même adresse, bloquent les
  essais pendant quinze minutes. Une connexion réussie remet le compteur du compte à zéro.
- **L'inscription libre est refusée.** Si l'application répond à autre chose que `localhost` alors que les inscriptions sont
  ouvertes sans code, le formulaire est bloqué et vous explique quoi faire.

### Accès depuis un téléphone sur le réseau local

L'interface est responsive. Pour y accéder depuis un autre appareil du réseau, il faut autoriser Apache à répondre en
dehors de `localhost` (fichier `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf`, directive
`Require local` → `Require ip 192.168.1`), puis ouvrir `http://<ip-du-pc>/mon_appli/appli/`.

⚠️ La connexion se fait alors en HTTP non chiffré : à réserver à un réseau de confiance. Le micro (messages vocaux) et les
notifications exigent HTTPS ou `localhost`.

---

## Sauvegarde

**Mon compte → Sauvegarder mes données** produit un fichier `.zip` contenant tout ce que contient le compte : cours,
fichiers joints, calendrier, tâches, budget, alternance, cartes et fiches de révision, résumés et discussions avec
l'IA. Rangez-le ailleurs que sur la machine qui héberge l'application — un stockage en ligne, une clé USB, un disque
externe. Une copie posée à côté de l'original ne protège de rien.

N'en font **pas** partie : la clé Gemini (une archive se partage, une clé non), et ce qui est partagé entre
personnes — amis, messages, groupes, serveurs. Pour une sauvegarde complète du site, exportez aussi la base et copiez
`storage/`.

La même page permet de **restaurer** une sauvegarde. L'opération remplace toutes les données du compte, elle est donc
protégée par une case à cocher et une confirmation. L'archive est entièrement vérifiée avant que la moindre ligne ne soit
effacée, et la réécriture se fait dans une transaction : si quoi que ce soit échoue, rien n'est modifié. Les anciennes
pièces jointes ne sont supprimées qu'une fois les nouvelles écrites.

La sauvegarde ne dépend d'aucun outil externe — ni `mysqldump`, ni accès à la ligne de commande — ce qui la rend utilisable
sur un hébergement mutualisé.

> Le code est sur GitHub, mais **pas vos données** : `config/parametres.php` et le contenu de `storage/` en sont exclus
> volontairement. Sans cette sauvegarde, une panne vous laisserait une application fonctionnelle et vide.

---

## Structure du projet

```
index.php              Point d'entrée unique, réglages par défaut et table de routage
config/                parametres.php : vos identifiants et réglages (hors dépôt)
src/                   Noyau : base (Database, Depot), Auth, Session (CSRF/flash), Config, Vue, Langue,
                       Fichiers, Sauvegarde, Gemini et CleApi (chiffrement des clés), WebPush, Courriel,
                       fournisseurs Outlook/Google, Serveurs, Travaux, PdfSimple, ClasseurXlsx…
controllers/           Un contrôleur par domaine (cours, calendrier, amis, serveurs, budget, alternance…)
views/                 Gabarits et pages, en PHP pur ; les fenêtres (pop-ups) réutilisent les mêmes fragments
lang/                  Les textes de l'interface : fr.php, en.php, es.php, de.php (mêmes clés partout)
assets/css, assets/js  Feuille de style et scripts (aucune bibliothèque externe)
storage/               Fichiers téléversés et données privées, fermés par Apache :
                       uploads, messages, resumes, alternance, travaux, versions, courriels
sql/schema.sql         Création de la base et de toutes les tables, à jour
sql/migration-*.sql    Migrations, pour mettre à jour une base déjà installée
outils/langue/         Scripts de test et de contrôle (ligne de commande uniquement ; à ne pas mettre en ligne)
```

Chaque URL passe par `index.php` (règle de réécriture dans `.htaccess`), qui la compare à la table de routage et
appelle la méthode de contrôleur correspondante. Les pages s'ouvrent aussi en **fenêtre** (`?fenetre=1`) : le contrôleur
renvoie alors seulement le fragment utile, sans l'en-tête du site.

**Mettre à jour une installation existante** : remplacer le code, puis appliquer dans l'ordre, sur la base, les
`sql/migration-*.sql` qui n'y ont pas encore été passés (`schema.sql` les contient tous : il ne sert qu'aux installations
neuves).

---

## Tests

Les scripts de `outils/langue/` sont des essais de bout en bout : ils créent des comptes jetables
(`…@exemple-test.fr`), ouvrent les pages par HTTP, vérifient le résultat, puis effacent ce qu'ils ont créé. Ils se
lancent en ligne de commande, jamais par le navigateur, et uniquement sur la machine de développement :

```bash
php outils/langue/<nom>.php
```

Ils couvrent les quatre langues, les amis, groupes et serveurs, les notifications, les partages, le budget, l'alternance,
l'assistant et les résumés IA (contre un faux serveur Gemini — aucun appel réel), la lecture des réglages
(`config_langue.php`) et le guide de la clé Gemini. Chacun se termine par un bilan, « Aucune anomalie » ou le nombre
d'anomalies. Le détail de chaque script est dans `outils/langue/LISEZMOI.md`.

Pour que les essais s'adressent à une base précise sans toucher à votre `config/parametres.php`, ils écrivent un
`config/parametres.test.php` temporaire, que l'application ne lit que depuis la machine elle-même, et le retirent à la fin.

---

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt/argon selon la version de PHP), re-hachés automatiquement si
  l'algorithme par défaut change ; **tentatives de connexion limitées**.
- Jeton **CSRF** obligatoire sur toutes les actions qui modifient des données.
- Requêtes **préparées** partout : aucune donnée n'est concaténée dans du SQL.
- Toutes les sorties HTML sont échappées.
- Chaque requête vérifie que la ligne appartient bien à l'utilisateur connecté, ou qu'elle lui a été partagée.
- **Clés d'API chiffrées** (AES-256-GCM, clé du site hors dépôt) et jamais réaffichées en entier ; exclues des sauvegardes.
- Téléversements filtrés par extension, renommés aléatoirement, servis avec `X-Content-Type-Options: nosniff` et forcés
  en téléchargement sauf types sûrs.
- Dossiers `config/`, `src/`, `controllers/`, `views/`, `storage/`, `sql/`, `outils/` ainsi que `.git/` interdits par
  Apache ; en-têtes `X-Frame-Options` et `Referrer-Policy`.
- L'adresse d'envoi des rappels (porteuse de la clé du cron) n'est visible que des administrateurs désignés dans les réglages.
- L'inscription libre est refusée en dehors de la machine locale ; un code d'inscription ou la fermeture complète des
  inscriptions la remplacent.

---

## Dépannage

**« Impossible de se connecter à la base de données »**
WAMP n'est pas démarré, la base n'a pas été importée, ou les identifiants du fichier de réglages sont faux (section `db` :
`host`, `name`, `user`, `pass`). Le détail de l'erreur est écrit dans le journal d'erreurs PHP. En ligne, vérifiez aussi que
l'hôte n'est pas `127.0.0.1` alors que l'hébergeur impose `localhost`, et inversement.

**Le fichier de réglages n'est pas pris en compte**
Il doit se nommer exactement `config/parametres.php` (ou `mes-cours-parametres.php` au-dessus de l'application), commencer
par `<?php` et se terminer par un `return [ … ];` valide. Un fichier qui ne renvoie pas un tableau est ignoré et signalé dans
le journal d'erreurs.

**Erreur 404 sur toutes les pages sauf l'accueil**
Le module `rewrite_module` d'Apache est désactivé : clic gauche sur l'icône WAMP → Apache → Modules Apache → cocher
`rewrite_module`. Chez un hébergeur, le `.htaccess` doit avoir été envoyé (les fichiers commençant par un point sont
parfois masqués par le gestionnaire de fichiers).

**« Inscription impossible » ou formulaire d'inscription bloqué**
En ligne, l'inscription libre est refusée volontairement : définissez `app.code_inscription`, ou fermez-la avec
`app.inscription_ouverte` à `false`.

**Un fichier refuse de se téléverser**
Soit son extension n'est pas dans la liste `app.extensions_autorisees` (voir `index.php`), soit il dépasse la limite de PHP. Les
valeurs visées sont `upload_max_filesize = 200M` (par fichier) et `post_max_size = 256M` (par envoi). Sur WAMP, elles se fixent
dans le `php.ini` (`C:\wamp64\bin\php\php8.3.28\`, les fichiers `php.ini` et `phpForApache.ini`) ; chez un hébergeur, dans
le panneau de configuration PHP — les directives `php_value` du `.htaccess` y sont ignorées par LiteSpeed.

Attention au nom du module dans le `.htaccess` : avec Apache 2.4 et PHP 8 c'est `php_module`. Un bloc
`<IfModule mod_php.c>` ne correspond à rien et ses directives sont ignorées silencieusement.

**Le lien du courriel « mot de passe oublié » est faux (chemin répété, `/appli/appli`)**
`app.adresse_publique` ne doit contenir que le domaine (`https://exemple.fr`), jamais le dossier de l'application.

**Aucun courriel n'arrive**
Contrôlez les réglages `courriel` (Gmail : un *mot de passe d'application*, pas celui du compte, avec la validation en deux
étapes) et regardez dans les indésirables. En local, sans réglage, les messages sont rangés dans `storage/courriels`.

**Outlook ou Google refusent la liaison**
L'adresse de retour déclarée chez le fournisseur doit être identique au caractère près à celle des réglages. Pour Outlook,
c'est la **valeur** du secret qu'il faut recopier, pas son identifiant (erreur `AADSTS7000215`). Pour Google, tant que
l'application est en mode « test », seules les adresses ajoutées comme testeurs peuvent se relier, et l'accès expire au
bout de sept jours.

**Les notifications n'arrivent pas fenêtre fermée**
La tâche planifiée doit appeler l'adresse d'envoi chaque minute. Sur ordinateur, le navigateur doit pouvoir rester actif en
arrière-plan (Chrome : « Continuer à exécuter des applications en arrière-plan »), et la notification doit avoir été
autorisée dans le profil utilisé.

**Les fonctions d'IA répondent qu'il manque une clé**
Chaque utilisateur doit enregistrer sa clé Gemini dans *Mon compte*, et l'installation doit avoir une
`securite.cle_chiffrement` (voir [Réglages](#réglages)). Le bouton « Comment obtenir une clé » de cette carte ouvre le guide.
