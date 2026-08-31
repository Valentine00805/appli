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
| **Matières** | Nom, couleur et enseignant. La couleur sert de repère partout dans l'application. |
| **Recherche** | Cherche simultanément dans les cours et dans le calendrier, avec surlignage des termes. |
| **Mon compte** | Statistiques et changement de mot de passe. |

Types d'évènement : 📘 Cours · 📝 Examen · 🗂️ Devoir · 🔁 Révision · 📌 Autre.
Chaque évènement peut être coché comme terminé (☐ / ☑) et rattaché à un cours,
pour retrouver ses notes le jour J.

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
`upload_max_filesize` / `post_max_size` du `php.ini` de WAMP (25 Mo et 30 Mo par
défaut ici).
