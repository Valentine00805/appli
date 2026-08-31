# Mes Cours

Application web pour **stocker ses cours** (texte + fichiers joints) et **planifier son année**
(cours, examens, devoirs, révisions) dans un calendrier.

PHP 8 + MySQL, sans aucune dépendance externe : pas de Composer, pas de CDN, tout fonctionne hors ligne.

---

## Installation

1. **Démarrer WAMP** et attendre que l'icône passe au vert (Apache + MySQL actifs).

2. **Créer la base de données.** Deux possibilités :

   - En ligne de commande :

     ```bash
     "C:/wamp64/bin/mysql/mysql8.4.7/bin/mysql" -u root < "C:/wamp64/www/mon_appli/appli/sql/schema.sql"
     ```

   - Ou depuis phpMyAdmin (<http://localhost/phpmyadmin5.2.3/>) : onglet **Importer** → choisir `sql/schema.sql` → **Exécuter**.

3. **Vérifier les identifiants MySQL** dans `config/config.php`
   (par défaut WAMP : utilisateur `root`, mot de passe vide).

4. **Ouvrir l'application** : <http://localhost/mon_appli/appli/>

5. **Créer son compte** depuis la page d'inscription. Quatre matières d'exemple sont
   créées automatiquement, modifiables ensuite.

---

## Utilisation

| Page | À quoi ça sert |
|---|---|
| **Accueil** | Ce qui est prévu aujourd'hui, les 7 prochains jours, les échéances, les cours récents. |
| **Calendrier** | Vue **mois**, **semaine** ou **liste**. Filtres par matière et par type. Clic sur `+` dans une case pour créer un évènement à cette date. |
| **Mes cours** | Liste filtrable (recherche, matière, tag, favoris, tri) et création de cours. |
| **Budget** | Trois onglets : **Opérations** (recettes et dépenses du mois, tendance sur 12 mois), **Prévisions** (solde de départ, charges fixes, solde prévisionnel reporté de mois en mois) et **Catégories** (avec plafond mensuel). |
| **Organisation** | Trois onglets : **Matières** (nom, couleur, enseignant), **Types d'évènement** (nom, icône, couleur, ordre, indicateur « échéance ») et **Tags** (créer, renommer, fusionner, supprimer). |
| **Recherche** | Cherche simultanément dans les cours et dans le calendrier, avec surlignage des termes. |
| **Mon compte** | Statistiques et changement de mot de passe. |

Cinq types sont créés avec le compte (📘 Cours, 📝 Examen, 🗂️ Devoir, 🔁 Révision,
📌 Autre) et sont entièrement modifiables depuis la page **Types** : renommer,
changer l'icône et la couleur, réordonner, ajouter les vôtres, supprimer.

Un type marqué « échéance » fait apparaître ses évènements sur l'accueil avec un
compte à rebours. Supprimer un type ne supprime pas les évènements : ils passent
simplement en « Sans type ».

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
prochains mois.

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
25 Mo maximum par fichier, plusieurs fichiers à la fois.
Ils sont stockés sous un nom aléatoire dans `storage/uploads`, dossier inaccessible
directement depuis le navigateur : ils ne transitent que par une URL qui vérifie
d'abord que le fichier vous appartient.

---

## Plusieurs utilisateurs

Chaque compte a ses propres cours, matières, tags, fichiers et évènements ;
rien n'est partagé et aucun compte ne peut lire les données d'un autre.

Pour fermer les inscriptions une fois tout le monde inscrit, dans `config/config.php` :

```php
'inscription_ouverte' => false,
```

### Accès depuis un téléphone sur le réseau local

L'interface est responsive. Pour y accéder depuis un autre appareil du réseau,
il faut autoriser Apache à répondre en dehors de `localhost` (fichier
`C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf`, directive
`Require local` → `Require ip 192.168.1`), puis ouvrir
`http://<ip-du-pc>/mon_appli/appli/`.

⚠️ La connexion se fait alors en HTTP non chiffré : à réserver à un réseau de confiance.

---

## Sauvegarde

Deux choses à conserver :

- le dossier `storage/uploads` (les fichiers joints) ;
- un export de la base `mon_appli_cours` (phpMyAdmin, <http://localhost/phpmyadmin5.2.3/> → **Exporter**).

---

## Structure du projet

```
index.php              Point d'entrée unique + table de routage
config/config.php      Identifiants MySQL et réglages (taille max, extensions…)
src/                   Noyau : Database, Auth, Session (CSRF/flash), Fichiers, Vue, helpers
controllers/           Un contrôleur par domaine (cours, calendrier, matières, compte)
views/                 Gabarits et pages, en PHP pur
assets/                CSS et JavaScript
storage/uploads/       Fichiers téléversés (jamais servis directement par Apache)
sql/schema.sql         Création de la base et des tables
sql/migration-*.sql    Migrations à appliquer sur une base déjà installée
```

Chaque URL passe par `index.php` (règle de réécriture dans `.htaccess`), qui la
compare à la table de routage et appelle la méthode de contrôleur correspondante.

---

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt/argon selon la version de PHP),
  re-hachés automatiquement si l'algorithme par défaut change.
- Jeton **CSRF** obligatoire sur toutes les actions qui modifient des données.
- Requêtes **préparées** partout : aucune donnée n'est concaténée dans du SQL.
- Toutes les sorties HTML sont échappées.
- Chaque requête vérifie que la ligne appartient bien à l'utilisateur connecté.
- Téléversements filtrés par extension, renommés aléatoirement, servis avec
  `X-Content-Type-Options: nosniff` et forcés en téléchargement sauf types sûrs.
- Dossiers `config/`, `src/`, `controllers/`, `views/`, `storage/`, `sql/` interdits
  par Apache.

---

## Dépannage

**« Impossible de se connecter à la base de données »**
WAMP n'est pas démarré, la base n'a pas été importée, ou les identifiants de
`config/config.php` sont faux.

**Erreur 404 sur toutes les pages sauf l'accueil**
Le module `rewrite_module` d'Apache est désactivé : clic gauche sur l'icône WAMP →
Apache → Modules Apache → cocher `rewrite_module`.

**Un fichier refuse de se téléverser**
Soit son extension n'est pas dans la liste de `config/config.php`, soit il dépasse
la limite de PHP. Les valeurs en place sont `upload_max_filesize = 25M` (par
fichier) et `post_max_size = 128M` (par envoi, ce qui laisse la place à plusieurs
fichiers d'un coup). Elles sont fixées dans le `php.ini` de WAMP
(`C:\wamp64\bin\php\php8.3.28\`, les deux fichiers `php.ini` et
`phpForApache.ini`) et rappelées dans le `.htaccess` de l'application.

Attention au nom du module dans le `.htaccess` : avec Apache 2.4 et PHP 8 c'est
`php_module`. Un bloc `<IfModule mod_php.c>` ne correspond à rien et ses
directives sont ignorées silencieusement.
