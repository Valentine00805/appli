-- Schéma de la base « Mes Cours »
-- Exécuter une seule fois : mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS `mon_appli_cours`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(80)  NOT NULL,
  -- Le nom sous lequel l'application vous appelle ; unique, NULL pour un compte d'avant.
  `pseudo`        VARCHAR(30)  NULL,
  -- La photo de profil, rangée avec les images des discussions.
  `photo_nom`     VARCHAR(64)  NULL,
  `photo_mime`    VARCHAR(40)  NULL,
  `fuseau`        VARCHAR(64)  NOT NULL DEFAULT 'Europe/Paris',
  -- Transcrire ses messages vocaux pendant l'enregistrement.
  `transcription_vocale` TINYINT(1) NOT NULL DEFAULT 1,
  -- Ce qu'on me partage arrive aussi en carte dans la discussion (1), ou seulement dans « Partagés » (0).
  `partages_dans_discussion` TINYINT(1) NOT NULL DEFAULT 1,
  `afficher_miens` TINYINT(1)  NOT NULL DEFAULT 1,
  `couleur_miens`  VARCHAR(7)  NULL,
  `volet_replie`    VARCHAR(190) NULL,
  `volet_ferme`     TINYINT(1)   NOT NULL DEFAULT 0,
  `dossiers_ferme`  TINYINT(1)   NOT NULL DEFAULT 0,
  `vue_calendrier`  VARCHAR(10)   NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  UNIQUE KEY `uniq_users_pseudo` (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matieres` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `enseignant` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_matiere_user_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_matieres_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dossiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '📁',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dossier_user_nom` (`user_id`, `nom`),
  KEY `idx_dossier_position` (`user_id`, `position`),
  KEY `idx_dossier_parent` (`parent_id`),
  CONSTRAINT `fk_dossiers_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_dossiers_parent` FOREIGN KEY (`parent_id`) REFERENCES `dossiers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cours` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `matiere_id` INT UNSIGNED DEFAULT NULL,
  `dossier_id` INT UNSIGNED DEFAULT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `contenu`    LONGTEXT     NULL,
  `fiche_revision` MEDIUMTEXT NULL,
  -- Où l'on en est de sa révision : 0 à réviser, 1 en cours, 2 révisée.
  `etat_revision` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `favori`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cours_user` (`user_id`),
  KEY `idx_cours_matiere` (`matiere_id`),
  KEY `idx_cours_dossier` (`dossier_id`),
  KEY `idx_cours_titre` (`titre`),
  CONSTRAINT `fk_cours_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_cours_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cours_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `nom`     VARCHAR(60)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tag_user_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cours_tag` (
  `cours_id` INT UNSIGNED NOT NULL,
  `tag_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`cours_id`, `tag_id`),
  KEY `idx_cours_tag_tag` (`tag_id`),
  CONSTRAINT `fk_ct_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ct_tag`   FOREIGN KEY (`tag_id`)   REFERENCES `tags`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fichiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  -- Un fichier de la fiche de révision ne figure pas dans les pièces jointes
  -- du cours : c'est la seule différence, tout le reste est commun.
  `pour_fiche` TINYINT(1)   NOT NULL DEFAULT 0,
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke` VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(120) NOT NULL,
  `taille`     INT UNSIGNED NOT NULL,
  -- Où l'on s'est arrêté dans un enregistrement, et sa durée : en secondes.
  `position_lecture` INT UNSIGNED NOT NULL DEFAULT 0,
  `duree_lecture`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fichiers_cours` (`cours_id`),
  KEY `idx_fichiers_fiche` (`cours_id`, `pour_fiche`),
  CONSTRAINT `fk_fichiers_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fichiers_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Types d'évènement, propres à chaque utilisateur et modifiables depuis l'application.
CREATE TABLE IF NOT EXISTS `types_evenement` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `nom`          VARCHAR(60)  NOT NULL,
  `icone`        VARCHAR(16)  NOT NULL DEFAULT '📌',
  `couleur`      CHAR(7)      NOT NULL DEFAULT '#64748b',
  `est_echeance` TINYINT(1)   NOT NULL DEFAULT 0,
  -- Un cours au programme n'est pas une chose à faire : chaque type décide
  -- s'il paraît sur le tableau.
  `au_tableau`   TINYINT(1)   NOT NULL DEFAULT 1,
  `position`     SMALLINT     NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_user_nom` (`user_id`, `nom`),
  KEY `idx_type_user_position` (`user_id`, `position`),
  CONSTRAINT `fk_types_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les séries d'évènements répétés. (voir sql/migration-repetition.sql)
CREATE TABLE IF NOT EXISTS `series_evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `frequence`   ENUM('jour','semaine','quinzaine','mois') NOT NULL,
  `jours`       VARCHAR(20)  NULL,
  `jusqu_au`    DATE         NOT NULL,
  `nombre_voulu` SMALLINT UNSIGNED NULL,
  `occurrences` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_serie_user` (`user_id`),
  CONSTRAINT `fk_serie_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `matiere_id`  INT UNSIGNED DEFAULT NULL,
  `type_id`     INT UNSIGNED DEFAULT NULL,
  `cours_id`    INT UNSIGNED DEFAULT NULL,
  `serie_id`    INT UNSIGNED DEFAULT NULL,
  `copie_de`        INT UNSIGNED NULL,
  -- Ajouté depuis un partage : l'ami qui l'a partagé, et son évènement d'origine.
  `partage_par`     INT UNSIGNED NULL,
  `partage_de`      INT UNSIGNED NULL,
  `titre`       VARCHAR(200) NOT NULL,
  `description` TEXT         NULL,
  `lieu`        VARCHAR(160) NULL,
  `debut`       DATETIME     NOT NULL,
  `fin`         DATETIME     NOT NULL,
  `journee_entiere` TINYINT(1) NOT NULL DEFAULT 0,
  `termine`     TINYINT(1)   NOT NULL DEFAULT 0,
  `etape`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Les délais des rappels, en minutes, du plus lointain au plus proche
  -- (« 1440,15 ») ; vide, pas de rappel.
  `rappels`     VARCHAR(80)  NOT NULL DEFAULT '15',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evt_user_debut` (`user_id`, `debut`),
  KEY `idx_evt_type` (`type_id`),
  KEY `idx_evt_serie` (`serie_id`),
  KEY `idx_copie_de` (`copie_de`),
  KEY `idx_evt_partage_de` (`partage_de`),
  CONSTRAINT `fk_evt_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_evt_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_type`    FOREIGN KEY (`type_id`)    REFERENCES `types_evenement`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_cours`   FOREIGN KEY (`cours_id`)   REFERENCES `cours`(`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_evt_serie`   FOREIGN KEY (`serie_id`)   REFERENCES `series_evenements`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_copie_de`    FOREIGN KEY (`copie_de`)   REFERENCES `evenements`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_partage_par` FOREIGN KEY (`partage_par`) REFERENCES `users`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_evt_partage_de`  FOREIGN KEY (`partage_de`)  REFERENCES `evenements`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget : categories et operations, propres a chaque utilisateur.
CREATE TABLE IF NOT EXISTS `categories_budget` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `nom`              VARCHAR(60)  NOT NULL,
  `icone`            VARCHAR(16)  NOT NULL DEFAULT '💶',
  `couleur`          CHAR(7)      NOT NULL DEFAULT '#64748b',
  `sens`             ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `plafond_mensuel`  DECIMAL(10,2) DEFAULT NULL,
  `position`         SMALLINT     NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_user_nom_sens` (`user_id`, `nom`, `sens`),
  KEY `idx_cat_user_sens` (`user_id`, `sens`, `position`),
  CONSTRAINT `fk_cat_budget_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previsions : charges fixes/revenus reguliers, et soldes saisis a la main.
CREATE TABLE IF NOT EXISTS `recurrences` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `categorie_id` INT UNSIGNED DEFAULT NULL,
  `libelle`      VARCHAR(160)  NOT NULL,
  `montant`      DECIMAL(10,2) NOT NULL,
  `sens`         ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `jour_du_mois` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `moyen`        VARCHAR(40)   DEFAULT NULL,
  `actif`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_user` (`user_id`, `actif`),
  CONSTRAINT `fk_rec_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_budget`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les personnes qui vous remboursent, tenues à part des opérations : une
-- personne existe même sans dépense en cours, et la renommer ne demande pas de
-- reprendre chaque ligne. Les opérations gardent malgré tout le nom écrit,
-- puisque c'est ce qui était vrai le jour de la dépense.
CREATE TABLE IF NOT EXISTS `personnes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personne_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_personne_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Des groupes de personnes, pour lire plusieurs comptes d'un coup. Un groupe ne
-- se choisit pas sur une dépense : celle-ci reste due par une personne, et une
-- seule. Le groupe sert à regarder l'ensemble.
CREATE TABLE IF NOT EXISTS `groupes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groupe_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_groupe_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groupe_personne` (
  `user_id`     INT UNSIGNED NOT NULL,
  `groupe_id`   INT UNSIGNED NOT NULL,
  `personne_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`groupe_id`, `personne_id`),
  KEY `idx_gp_user` (`user_id`),
  KEY `idx_gp_personne` (`personne_id`),
  CONSTRAINT `fk_gp_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_gp_groupe`   FOREIGN KEY (`groupe_id`)   REFERENCES `groupes`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_gp_personne` FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `operations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `categorie_id`   INT UNSIGNED DEFAULT NULL,
  `recurrence_id`  INT UNSIGNED DEFAULT NULL,
  `libelle`        VARCHAR(160)  NOT NULL,
  `montant`        DECIMAL(10,2) NOT NULL,
  `sens`           ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `date_operation` DATE          NOT NULL,
  `moyen`          VARCHAR(40)   DEFAULT NULL,
  `source`         ENUM('manuelle','import') NOT NULL DEFAULT 'manuelle',
  `empreinte`      CHAR(40)      DEFAULT NULL,
  `note`           TEXT          DEFAULT NULL,
  `a_rembourser`       TINYINT(1)    NOT NULL DEFAULT 0,
  `part_rembourser`    DECIMAL(10,2) NULL,
  `rembourse_par`      VARCHAR(80)   NULL,
  `statut_remb`        ENUM('a_reclamer','hors_total','rembourse') NOT NULL DEFAULT 'a_reclamer',
  `date_remboursement` DATE          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_op_user_date` (`user_id`, `date_operation`),
  KEY `idx_op_categorie` (`categorie_id`),
  KEY `idx_op_empreinte` (`user_id`, `empreinte`),
  KEY `idx_op_remboursement` (`user_id`, `a_rembourser`, `statut_remb`),
  KEY `idx_op_recurrence_date` (`recurrence_id`, `date_operation`),
  CONSTRAINT `fk_op_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_categorie`  FOREIGN KEY (`categorie_id`)  REFERENCES `categories_budget`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_op_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `recurrences`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soldes_saisis` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `periode`    CHAR(7)       NOT NULL COMMENT 'AAAA-MM',
  `montant`    DECIMAL(12,2) NOT NULL,
  `note`       VARCHAR(160)  DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_solde_user_periode` (`user_id`, `periode`),
  CONSTRAINT `fk_solde_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglements : trace du solde d'un mois de remboursements.
CREATE TABLE IF NOT EXISTS `reglements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `periode`        CHAR(7)       NOT NULL COMMENT 'mois réglé, AAAA-MM',
  `personne`       VARCHAR(80)   DEFAULT NULL COMMENT 'NULL = tout le monde',
  `montant`        DECIMAL(10,2) NOT NULL,
  `date_reglement` DATE          NOT NULL,
  `operation_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'la recette créée en retour',
  `lignes`         TEXT          NOT NULL COMMENT 'identifiants des dépenses soldées, en JSON',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reglement` (`user_id`, `periode`, `personne`),
  CONSTRAINT `fk_reglement_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reglement_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Securite : traces des essais de connexion, pour en limiter le rythme.
CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) DEFAULT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `reussie`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentative_email` (`email`, `created_at`),
  KEY `idx_tentative_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listes_taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '',
  `echeance`   DATE         DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_liste_user_nom` (`user_id`, `nom`),
  KEY `idx_listes_echeance` (`user_id`, `echeance`),
  KEY `idx_listes_position` (`user_id`, `position`),
  CONSTRAINT `fk_listes_taches_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `liste_id`   INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `echeance`   DATE         DEFAULT NULL,
  `faite`      TINYINT(1)   NOT NULL DEFAULT 0,
  `faite_le`   DATETIME     DEFAULT NULL,
  `etape`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `note`       VARCHAR(500) DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_taches_user` (`user_id`),
  KEY `idx_taches_liste` (`liste_id`),
  KEY `idx_taches_echeance` (`user_id`, `faite`, `echeance`),
  KEY `idx_taches_position` (`liste_id`, `position`),
  CONSTRAINT `fk_taches_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_taches_liste` FOREIGN KEY (`liste_id`) REFERENCES `listes_taches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qu'on rattache à une fiche de révision sans que ça vienne du cours :
-- des liens web, des renvois vers d'autres cours, des évènements du calendrier.
-- Les fichiers de la fiche, eux, vivent dans « fichiers » avec pour_fiche = 1.
CREATE TABLE IF NOT EXISTS `cartes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `question`   VARCHAR(500) NOT NULL,
  `reponse`    TEXT         NOT NULL,
  -- D'où elle vient : 'cours', 'fiche', 'fichier' ou 'main'.
  `origine`    VARCHAR(20)  NOT NULL DEFAULT 'main',
  `source`     VARCHAR(255) NOT NULL DEFAULT '',
  `empreinte`  CHAR(32)     NOT NULL,
  `boite`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `revoir_le`  DATE         NOT NULL,
  `vues`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reussies`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cartes_question` (`cours_id`, `empreinte`),
  KEY `idx_cartes_revoir` (`user_id`, `revoir_le`),
  CONSTRAINT `fk_cartes_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cartes_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fiche_elements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `type`       ENUM('lien', 'cours', 'evenement') NOT NULL,
  `libelle`    VARCHAR(200) DEFAULT NULL,
  `url`        VARCHAR(2048) DEFAULT NULL,
  -- Une cible par type, chacune avec sa clé étrangère : le cours ou
  -- l'évènement disparu emporte le renvoi, sans laisser de ligne morte.
  `cible_cours_id`     INT UNSIGNED DEFAULT NULL,
  `cible_evenement_id` INT UNSIGNED DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fiche_elements_cours` (`cours_id`, `type`, `position`),
  KEY `idx_fiche_elements_user` (`user_id`),
  CONSTRAINT `fk_fiche_elements_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_cours` FOREIGN KEY (`cible_cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_evt`   FOREIGN KEY (`cible_evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relier un calendrier Outlook à l'application.
--
-- Une ligne par compte : de quoi parler à Microsoft (l'identifiant de
-- l'application inscrite, les jetons) et de quoi savoir où l'on en est de la
-- synchronisation (le calendrier suivi, le repère de la dernière lecture).
--
-- Ces jetons ouvrent l'agenda du compte relié : la table est délibérément
-- laissée hors des sauvegardes exportables, pour qu'une archive partagée ne
-- les emporte pas.
CREATE TABLE IF NOT EXISTS `agenda_comptes` (
  `user_id`        INT UNSIGNED NOT NULL,
  `fournisseur`    VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `compte`         VARCHAR(190) NULL,
  `jeton`          TEXT         NULL,
  `renouvellement` TEXT         NULL,
  `permissions`    TEXT         NULL,
  `expire_le`      DATETIME     NULL,
  `calendrier_id`  VARCHAR(255) NULL,
  `calendrier_nom` VARCHAR(190) NULL,
  `calendrier_envoi_id`  VARCHAR(512) NULL,
  `calendrier_envoi_nom` VARCHAR(190) NULL,
  `envoi_choisi`         TINYINT(1)   NOT NULL DEFAULT 0,
  -- Le repère que Microsoft rend pour ne relire que ce qui a changé.
  `delta`          TEXT         NULL,
  `synchro_le`     DATETIME     NULL,
  `envoi_le`       DATETIME     NULL,
  `empreinte_envoi` VARCHAR(64) NULL,
  `souci`          TEXT         NULL,
  `souci_le`       DATETIME     NULL,
  `cree_le`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `fournisseur`),
  CONSTRAINT `fk_outlook_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qui relie un évènement d'ici à son jumeau là-bas.
--
-- Sans ce lien, une synchronisation ne saurait pas distinguer « un évènement
-- nouveau » de « un évènement déjà connu qui a changé », et les doublerait à
-- chaque passage.
CREATE TABLE IF NOT EXISTS `agenda_liens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `evenement_id` INT UNSIGNED NULL,
  `distant_id`   VARCHAR(255) NOT NULL,
  `calendrier`   CHAR(32)     NULL,
  -- La version connue de part et d'autre, pour repérer ce qui a bougé.
  `etag`         VARCHAR(255) NULL,
  `empreinte`    CHAR(32)     NULL,
  `maj_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lien_distant` (`user_id`, `fournisseur`, `distant_id`),
  KEY `idx_outlook_evenement` (`evenement_id`),
  CONSTRAINT `fk_lien_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lien_evenement` FOREIGN KEY (`evenement_id`)
    REFERENCES `evenements`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les calendriers Outlook d'un compte, et ceux que l'application lit.
-- (voir sql/migration-outlook-calendriers.sql)
CREATE TABLE IF NOT EXISTS `agenda_calendriers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `calendrier_id` TEXT         NOT NULL,
  `empreinte`     CHAR(32)     NOT NULL,
  `nom`           VARCHAR(190) NULL,
  `proprietaire`  VARCHAR(190) NULL,
  `partage`       TINYINT(1)   NOT NULL DEFAULT 0,
  `peut_ecrire`   TINYINT(1)   NOT NULL DEFAULT 0,
  `principal`     TINYINT(1)   NOT NULL DEFAULT 0,
  `suivi`         TINYINT(1)   NOT NULL DEFAULT 0,
  `affiche`       TINYINT(1)   NOT NULL DEFAULT 1,
  `couleur`       VARCHAR(7)   NULL,
  `vu_le`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_calendrier` (`user_id`, `fournisseur`, `empreinte`),
  CONSTRAINT `fk_cal_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les agendas où part chaque évènement.
-- (voir sql/migration-agendas-multiples.sql)
--
-- L'empreinte vide désigne « Mes évènements », le calendrier de
-- l'application. Aucune ligne veut dire la même chose : c'est le cas de tout
-- ce qui a été écrit avant que le choix existe.
CREATE TABLE IF NOT EXISTS `evenement_agendas` (
  `evenement_id` INT UNSIGNED NOT NULL,
  `empreinte`    CHAR(32)     NOT NULL DEFAULT '',
  PRIMARY KEY (`evenement_id`, `empreinte`),
  CONSTRAINT `fk_evt_agenda` FOREIGN KEY (`evenement_id`)
    REFERENCES `evenements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Ce que l'application a écrit dans Outlook.
-- (voir sql/migration-outlook-envoi.sql)
CREATE TABLE IF NOT EXISTS `agenda_envois` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `sorte`      ENUM('evenement','tache') NOT NULL,
  `source_id`  INT UNSIGNED NOT NULL,
  `distant_id` VARCHAR(512) NOT NULL,
  `calendrier_id` TEXT       NULL,
  `calendrier_empreinte` CHAR(32) NOT NULL DEFAULT '',
  `empreinte`  CHAR(32)     NULL,
  `maj_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_envoi` (`user_id`, `fournisseur`, `sorte`, `source_id`, `calendrier_empreinte`),
  CONSTRAINT `fk_envoi_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les appareils abonnés : l'adresse que leur service de notifications nous a
-- donnée, et les deux clés pour leur écrire. L'empreinte de l'adresse sert de
-- clé unique — une adresse peut dépasser ce qu'un index accepte.
CREATE TABLE IF NOT EXISTS `abonnements_push` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `point_final` VARCHAR(1024) NOT NULL,
  `empreinte`   CHAR(64)      NOT NULL,
  `cle_p256dh`  VARCHAR(120)  NOT NULL,
  `cle_auth`    VARCHAR(40)   NOT NULL,
  `appareil`    VARCHAR(190)  DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dernier_envoi` DATETIME    DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_abonnement` (`empreinte`),
  KEY `idx_abonnements_user` (`user_id`),
  CONSTRAINT `fk_abonnements_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les rappels déjà partis : un par objet et par moment. Un évènement déplacé
-- change de moment, et son rappel repartira ; deux envois simultanés, eux,
-- butent sur la clé unique.
CREATE TABLE IF NOT EXISTS `rappels_envoyes` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   INT UNSIGNED NOT NULL,
  `nature`    ENUM('evenement', 'tache', 'liste') NOT NULL,
  `objet_id`  INT UNSIGNED NOT NULL,
  `moment`    DATETIME     NOT NULL,
  `envoye_le` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rappel` (`user_id`, `nature`, `objet_id`, `moment`),
  CONSTRAINT `fk_rappels_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les réglages de l'installation elle-même : les clés VAPID, la clé de l'adresse
-- d'envoi. En base plutôt qu'en fichier : l'antivirus du poste met des fichiers
-- du projet en quarantaine, et une clé perdue désabonnerait tous les appareils.
CREATE TABLE IF NOT EXISTS `reglages_application` (
  `cle`    VARCHAR(64) NOT NULL,
  `valeur` TEXT        NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les amis et leurs messages.
--
-- Une amitié relie deux comptes : qui a demandé, et à qui. Tant que l'autre
-- n'a pas accepté, elle est « en attente » et rien ne s'échange. La paire est
-- aussi rangée du plus petit au plus grand identifiant (petit_id, grand_id) :
-- A→B et B→A sont la même amitié, et la clé unique l'empêche d'exister deux
-- fois. (Des colonnes calculées auraient fait l'affaire, mais MySQL refuse la
-- suppression en cascade sur les colonnes dont elles dépendent.)
CREATE TABLE IF NOT EXISTS `amities` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demandeur_id`    INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  `petit_id`        INT UNSIGNED NOT NULL,
  `grand_id`        INT UNSIGNED NOT NULL,
  `statut`          ENUM('attente', 'acceptee') NOT NULL DEFAULT 'attente',
  `created_at`      DATETIME     NOT NULL,
  `acceptee_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_amitie_paire` (`petit_id`, `grand_id`),
  KEY `idx_amitie_destinataire` (`destinataire_id`, `statut`),
  KEY `idx_amitie_demandeur` (`demandeur_id`, `statut`),
  CONSTRAINT `fk_amitie_demandeur`    FOREIGN KEY (`demandeur_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_amitie_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_amitie_paire` CHECK (`petit_id` = LEAST(`demandeur_id`, `destinataire_id`)
                                   AND `grand_id` = GREATEST(`demandeur_id`, `destinataire_id`)
                                   AND `demandeur_id` <> `destinataire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les messages entre deux amis. Les heures sont en temps universel : deux amis
-- n'ont pas forcément le même fuseau, chacun les lit dans le sien.
CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expediteur_id`   INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  -- Le message auquel celui-ci répond (la citation disparaît avec lui).
  `reponse_a`       INT UNSIGNED NULL,
  `texte`           TEXT         NOT NULL,
  -- Une image jointe, rangée dans storage/messages ; le texte peut alors être vide.
  `image_nom`       VARCHAR(64)       NULL,
  `image_mime`      VARCHAR(40)       NULL,
  `image_largeur`   SMALLINT UNSIGNED NULL,
  `image_hauteur`   SMALLINT UNSIGNED NULL,
  -- Un fichier joint (PDF, document, audio…), rangé au même endroit sous un nom tiré au hasard.
  `fichier_nom`     VARCHAR(64)  NULL,
  `fichier_origine` VARCHAR(255) NULL,
  `fichier_mime`    VARCHAR(120) NULL,
  `fichier_taille`  INT UNSIGNED NULL,
  -- Un message vocal enregistré dans le navigateur, et sa durée en secondes.
  `audio_nom`       VARCHAR(64)       NULL,
  `audio_duree`     SMALLINT UNSIGNED NULL,
  -- Ce que le navigateur a entendu pendant l'enregistrement.
  `audio_transcription` TEXT NULL,
  -- Un cours ou un fichier partagé : la carte du message.
  `partage_type`    VARCHAR(12)  NULL,
  `partage_id`      INT UNSIGNED NULL,
  -- Une note de la discussion (« fond », « fond_retire ») plutôt qu'un message.
  `evenement`       VARCHAR(20)  NULL,
  `created_at`      DATETIME     NOT NULL,
  -- La dernière modification du texte par qui l'a écrit.
  `modifie_le`      DATETIME     NULL,
  -- Le dernier changement de ses réactions, pour les pages déjà ouvertes.
  `reactions_le`    DATETIME     NULL,
  `lu_le`           DATETIME     NULL,
  -- Supprimé pour les deux par qui l'a écrit (contenu vidé), ou caché d'un seul côté.
  `supprime_le`         DATETIME   NULL,
  `masque_expediteur`   TINYINT(1) NOT NULL DEFAULT 0,
  `masque_destinataire` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_messages_fil` (`expediteur_id`, `destinataire_id`, `id`),
  KEY `idx_messages_non_lus` (`destinataire_id`, `lu_le`),
  KEY `idx_messages_reponse` (`reponse_a`),
  KEY `idx_messages_modifies` (`expediteur_id`, `destinataire_id`, `modifie_le`),
  KEY `idx_messages_reactions` (`expediteur_id`, `destinataire_id`, `reactions_le`),
  CONSTRAINT `fk_messages_expediteur`   FOREIGN KEY (`expediteur_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_reponse`      FOREIGN KEY (`reponse_a`)       REFERENCES `messages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les notifications de messages.
--
-- Pour chaque personne et chacun de ses amis : quand elle a eu la discussion
-- sous les yeux pour la dernière fois (inutile de la prévenir d'un message
-- qu'elle voit arriver), et quand elle a été notifiée pour la dernière fois
-- (plusieurs messages d'affilée ne font vibrer le téléphone qu'une fois).
CREATE TABLE IF NOT EXISTS `discussions_etat` (
  `user_id`     INT UNSIGNED NOT NULL,
  `ami_id`      INT UNSIGNED NOT NULL,
  `regarde_le`  DATETIME     NULL,
  `notifie_le`  DATETIME     NULL,
  PRIMARY KEY (`user_id`, `ami_id`),
  KEY `idx_discussions_ami` (`ami_id`),
  CONSTRAINT `fk_discussions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_discussions_ami`  FOREIGN KEY (`ami_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La file des notifications : messages, demandes d'ami, acceptations.
--
-- Comme les rappels d'évènements, une notification est d'abord écrite ici,
-- puis envoyée — tout de suite, et sinon par l'adresse d'envoi que la tâche
-- planifiée appelle chaque minute (ou par la page ouverte). Un envoi raté
-- (connexion coupée, service indisponible) est ainsi retenté, pendant une
-- heure, au lieu d'être perdu. « envoi_en_cours » empêche deux passages
-- simultanés d'envoyer la même notification deux fois.
CREATE TABLE IF NOT EXISTS `notifications_file` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED  NOT NULL,
  `nature`          VARCHAR(20)   NOT NULL,
  `titre`           VARCHAR(190)  NOT NULL,
  `corps`           VARCHAR(500)  NOT NULL,
  `adresse`         VARCHAR(255)  NOT NULL,
  `etiquette`       VARCHAR(64)   NOT NULL,
  `cree_le`         DATETIME      NOT NULL,
  `envoi_en_cours`  DATETIME      NULL,
  `tentatives`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `envoye_le`       DATETIME      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_attente` (`user_id`, `envoye_le`, `cree_le`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloquer un compte.
--
-- Qui bloque met fin à l'amitié et à toute demande en cours ; le compte bloqué
-- ne peut plus le trouver par son pseudo, ni lui écrire, ni le redemander en
-- ami — sans qu'on le lui dise. Débloquer retire la ligne, sans refaire
-- l'amitié.
CREATE TABLE IF NOT EXISTS `blocages` (
  `bloqueur_id` INT UNSIGNED NOT NULL,
  `bloque_id`   INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`bloqueur_id`, `bloque_id`),
  KEY `idx_blocages_bloque` (`bloque_id`),
  CONSTRAINT `fk_blocages_bloqueur` FOREIGN KEY (`bloqueur_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blocages_bloque`   FOREIGN KEY (`bloque_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réagir à un message avec un emoji.
--
-- Une réaction par personne et par message : en choisir une autre la
-- remplace, recliquer la même l'enlève. « reactions_le » sur le message note
-- le dernier changement, pour qu'une page déjà ouverte de l'autre côté
-- reprenne les réactions au relevé suivant.
CREATE TABLE IF NOT EXISTS `reactions` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(32)  NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_reactions_user` (`user_id`),
  CONSTRAINT `fk_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reactions_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Épingler des messages.
--
-- Une épingle est personnelle : on épingle pour soi, l'autre ne le voit pas.
-- Elle disparaît avec le message ou avec le compte.
CREATE TABLE IF NOT EXISTS `epingles` (
  `user_id`    INT UNSIGNED NOT NULL,
  `message_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `message_id`),
  KEY `idx_epingles_message` (`message_id`),
  CONSTRAINT `fk_epingles_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_epingles_message` FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le fond d'écran d'une conversation, le même pour les deux amis.
CREATE TABLE IF NOT EXISTS `fonds_discussion` (
  `petit_id`   INT UNSIGNED NOT NULL,
  `grand_id`   INT UNSIGNED NOT NULL,
  `image_nom`  VARCHAR(64)  NOT NULL,
  `image_mime` VARCHAR(40)  NOT NULL,
  `choisi_par` INT UNSIGNED NULL,
  `choisi_le`  DATETIME     NOT NULL,
  PRIMARY KEY (`petit_id`, `grand_id`),
  KEY `idx_fonds_grand` (`grand_id`),
  KEY `idx_fonds_choisi_par` (`choisi_par`),
  CONSTRAINT `fk_fonds_petit`      FOREIGN KEY (`petit_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fonds_grand`      FOREIGN KEY (`grand_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fonds_choisi_par` FOREIGN KEY (`choisi_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les discussions de groupe.
--
-- Une conversation réunit des amis de celui qui les y ajoute (à ne pas
-- confondre avec les « groupes » de personnes du budget). Ses messages ont
-- leur propre table : tout ce qu'on sait faire à deux (photos, fichiers,
-- vocaux, réponses, réactions, épingles, fond d'écran) s'y retrouve, mais
-- « supprimer pour moi », « lu » et « vu » se comptent par membre.
--
-- Un nouveau membre ne voit que ce qui s'écrit après son arrivée
-- (depuis_message). Qui quitte la conversation, ou en est retiré, n'y voit
-- plus rien.

CREATE TABLE IF NOT EXISTS `conversations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(60)  NOT NULL,
  `cree_par`    INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL,
  -- Le fond d'écran, commun à tous les membres.
  `fond_nom`    VARCHAR(64)  NULL,
  `fond_mime`   VARCHAR(40)  NULL,
  `fond_par`    INT UNSIGNED NULL,
  `fond_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversations_cree_par` (`cree_par`),
  KEY `idx_conversations_fond_par` (`fond_par`),
  CONSTRAINT `fk_conversations_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversations_fond_par` FOREIGN KEY (`fond_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_membres` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `role`            ENUM('admin', 'membre') NOT NULL DEFAULT 'membre',
  `rejoint_le`      DATETIME     NOT NULL,
  -- Les messages visibles commencent après celui-ci.
  `depuis_message`  INT UNSIGNED NOT NULL DEFAULT 0,
  -- Le dernier message lu.
  `lu_jusqua`       INT UNSIGNED NOT NULL DEFAULT 0,
  -- La discussion sous les yeux (onglet affiché, fenêtre active) : pas de notification.
  `regarde_le`      DATETIME     NULL,
  PRIMARY KEY (`conversation_id`, `user_id`),
  KEY `idx_conversation_membres_user` (`user_id`),
  CONSTRAINT `fk_conversation_membres_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_membres_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `expediteur_id`   INT UNSIGNED NULL,
  `reponse_a`       INT UNSIGNED NULL,
  `texte`           TEXT         NOT NULL,
  -- Une note plutôt qu'un message : « creation », « ajout », « retrait »,
  -- « depart », « nom », « admin », « fond », « fond_retire ».
  `evenement`       VARCHAR(20)  NULL,
  `evenement_cible` INT UNSIGNED NULL,
  `evenement_texte` VARCHAR(80)  NULL,
  `image_nom`       VARCHAR(64)  NULL,
  `image_mime`      VARCHAR(40)  NULL,
  `image_largeur`   SMALLINT UNSIGNED NULL,
  `image_hauteur`   SMALLINT UNSIGNED NULL,
  `fichier_nom`     VARCHAR(64)  NULL,
  `fichier_origine` VARCHAR(255) NULL,
  `fichier_mime`    VARCHAR(120) NULL,
  `fichier_taille`  INT UNSIGNED NULL,
  `audio_nom`       VARCHAR(64)  NULL,
  `audio_duree`     SMALLINT UNSIGNED NULL,
  `audio_transcription` TEXT     NULL,
  `partage_type`    VARCHAR(12)  NULL,
  `partage_id`      INT UNSIGNED NULL,
  `created_at`      DATETIME     NOT NULL,
  `modifie_le`      DATETIME     NULL,
  `reactions_le`    DATETIME     NULL,
  `supprime_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_messages_fil` (`conversation_id`, `id`),
  KEY `idx_conversation_messages_expediteur` (`expediteur_id`, `created_at`),
  KEY `idx_conversation_messages_reponse` (`reponse_a`),
  KEY `idx_conversation_messages_cible` (`evenement_cible`),
  CONSTRAINT `fk_conversation_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_messages_expediteur`   FOREIGN KEY (`expediteur_id`)   REFERENCES `users`(`id`)                 ON DELETE SET NULL,
  CONSTRAINT `fk_conversation_messages_reponse`      FOREIGN KEY (`reponse_a`)       REFERENCES `conversation_messages`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversation_messages_cible`        FOREIGN KEY (`evenement_cible`) REFERENCES `users`(`id`)                 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- « Supprimer pour moi » : le message disparaît pour ce membre seulement.
CREATE TABLE IF NOT EXISTS `conversation_masques` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_conversation_masques_user` (`user_id`),
  CONSTRAINT `fk_conversation_masques_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_masques_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_reactions` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(32)  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_conversation_reactions_user` (`user_id`),
  CONSTRAINT `fk_conversation_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_reactions_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_epingles` (
  `user_id`    INT UNSIGNED NOT NULL,
  `message_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `message_id`),
  KEY `idx_conversation_epingles_message` (`message_id`),
  CONSTRAINT `fk_conversation_epingles_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_epingles_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les invitations dans une discussion de groupe.
--
-- Un administrateur ajoute directement ses amis ; une autre personne, trouvée
-- par son pseudo, reçoit une invitation qu'elle accepte ou refuse. Personne
-- n'entre dans un groupe sans l'avoir voulu.
CREATE TABLE IF NOT EXISTS `conversation_invitations` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `invite_par`      INT UNSIGNED NULL,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`conversation_id`, `user_id`),
  KEY `idx_conversation_invitations_user` (`user_id`),
  KEY `idx_conversation_invitations_par` (`invite_par`),
  CONSTRAINT `fk_conversation_invitations_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_invitations_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_invitations_par`          FOREIGN KEY (`invite_par`)      REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La photo de profil d'un groupe : choisie par un membre, vue par tous.
ALTER TABLE conversations
    ADD COLUMN photo_nom  VARCHAR(64) NULL AFTER created_at,
    ADD COLUMN photo_mime VARCHAR(40) NULL AFTER photo_nom;

-- Les demandes de réinitialisation de mot de passe.
--
-- Le lien envoyé par e-mail porte un jeton ; seule son empreinte (SHA-256) est
-- gardée. Il vaut une heure, une seule fois.
CREATE TABLE IF NOT EXISTS `reinitialisations_mdp` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `jeton_hash` CHAR(64)     NOT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `cree_le`    DATETIME     NOT NULL,
  `expire_le`  DATETIME     NOT NULL,
  `utilise_le` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reinitialisation_jeton` (`jeton_hash`),
  KEY `idx_reinitialisation_user` (`user_id`, `cree_le`),
  KEY `idx_reinitialisation_ip` (`ip`, `cree_le`),
  CONSTRAINT `fk_reinitialisation_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partager un cours ou un fichier, avec ses amis ou par un lien public.
CREATE TABLE IF NOT EXISTS `partages_amis` (
  `destinataire_id` INT UNSIGNED NOT NULL,
  `cible_type`      VARCHAR(12)  NOT NULL,
  `cible_id`        INT UNSIGNED NOT NULL,
  -- Ce que le partage permet : lecture, commentaire ou modification.
  `droit`           VARCHAR(12)  NOT NULL DEFAULT 'lecture',
  -- Le mot qui accompagnait le partage, et quand je l'ai vu.
  `message`         TEXT         NULL,
  `proprietaire_id` INT UNSIGNED NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  `vu_le`           DATETIME     NULL,
  PRIMARY KEY (`destinataire_id`, `cible_type`, `cible_id`),
  KEY `idx_partages_amis_cible` (`cible_type`, `cible_id`),
  KEY `idx_partages_amis_proprietaire` (`proprietaire_id`),
  CONSTRAINT `fk_partages_amis_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_partages_amis_proprietaire` FOREIGN KEY (`proprietaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liens_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cible_type` VARCHAR(12)  NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `jeton`      CHAR(32)     NOT NULL,
  `created_at` DATETIME     NOT NULL,
  `vues`       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lien_jeton` (`jeton`),
  UNIQUE KEY `uniq_lien_cible` (`cible_type`, `cible_id`),
  KEY `idx_lien_user` (`user_id`),
  CONSTRAINT `fk_lien_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lots_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lot_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_lot_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lots_partage_documents` (
  `lot_id`     INT UNSIGNED NOT NULL,
  `cible_type` VARCHAR(12)  NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`lot_id`, `cible_type`, `cible_id`),
  CONSTRAINT `fk_lot_document_lot` FOREIGN KEY (`lot_id`) REFERENCES `lots_partage`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commentaires_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cible_type` VARCHAR(12)  NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  -- La réponse à un commentaire, sur un seul niveau.
  `reponse_a`  INT UNSIGNED NULL,
  `texte`      TEXT         NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commentaire_cible` (`cible_type`, `cible_id`, `created_at`),
  KEY `idx_commentaire_user` (`user_id`),
  KEY `idx_commentaire_reponse` (`reponse_a`),
  CONSTRAINT `fk_commentaire_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commentaire_reponse` FOREIGN KEY (`reponse_a`) REFERENCES `commentaires_partage`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commentaires_jaime` (
  `commentaire_id` INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `created_at`     DATETIME     NOT NULL,
  PRIMARY KEY (`commentaire_id`, `user_id`),
  KEY `idx_jaime_user` (`user_id`),
  CONSTRAINT `fk_jaime_commentaire` FOREIGN KEY (`commentaire_id`) REFERENCES `commentaires_partage`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jaime_user`        FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)                ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- L'historique des modifications faites par d'autres dans un document partagé.
CREATE TABLE IF NOT EXISTS `modifications_partage` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cible_type`  VARCHAR(12)  NOT NULL,
  `cible_id`    INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `nature`      VARCHAR(8)   NOT NULL,
  `avant`       MEDIUMTEXT   NULL,
  `apres`       MEDIUMTEXT   NULL,
  -- Le fichier joint, tant qu'il existe.
  `fichier_id`  INT UNSIGNED NULL,
  -- Le fichier retiré, mis de côté : de quoi l'ouvrir et le remettre.
  `nom_origine` VARCHAR(255) NULL,
  `nom_stocke`  VARCHAR(255) NULL,
  `mime`        VARCHAR(120) NULL,
  `taille`      INT UNSIGNED NULL,
  `restaure`    TINYINT(1)   NOT NULL DEFAULT 0,
  -- Le propriétaire est revenu sur cette modification.
  `annulee`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_modification_cible` (`cible_type`, `cible_id`, `created_at`),
  KEY `idx_modification_user` (`user_id`),
  CONSTRAINT `fk_modification_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les évènements qu'un ami me partage paraissent-ils d'office dans mon calendrier ?
CREATE TABLE IF NOT EXISTS `partages_calendrier` (
  `user_id`    INT UNSIGNED NOT NULL,
  `ami_id`     INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `ami_id`),
  KEY `idx_partages_calendrier_ami` (`ami_id`),
  CONSTRAINT `fk_partages_calendrier_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_partages_calendrier_ami`  FOREIGN KEY (`ami_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partager tout son calendrier « Mes évènements » avec un ami.
CREATE TABLE IF NOT EXISTS `calendriers_partages` (
  `proprietaire_id` INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`proprietaire_id`, `destinataire_id`),
  KEY `idx_calendriers_partages_destinataire` (`destinataire_id`),
  CONSTRAINT `fk_calendriers_partages_proprietaire` FOREIGN KEY (`proprietaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendriers_partages_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- L'espace alternance : des notes, le rythme école / entreprise, le journal
-- des missions et les documents (contrat, livret, évaluations, rapport).

-- Des notes libres, mises en forme comme un cours.
CREATE TABLE IF NOT EXISTS `alternance_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `contenu`    LONGTEXT     NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_notes_user` (`user_id`, `updated_at`),
  CONSTRAINT `fk_alternance_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le rythme, posé à la main : du … au …, à l'école ou en entreprise.
-- Deux périodes ne se chevauchent jamais : la dernière posée l'emporte.
CREATE TABLE IF NOT EXISTS `alternance_periodes` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `lieu`    ENUM('ecole', 'entreprise') NOT NULL,
  `debut`   DATE         NOT NULL,
  `fin`     DATE         NOT NULL,
  `note`    VARCHAR(200) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_periodes_user` (`user_id`, `debut`),
  CONSTRAINT `fk_alternance_periodes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le journal des missions : une page par semaine, rangée à son lundi.
CREATE TABLE IF NOT EXISTS `alternance_journal` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `semaine`     DATE         NOT NULL,
  `missions`    MEDIUMTEXT   NULL,
  `competences` VARCHAR(500) NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_alternance_journal_semaine` (`user_id`, `semaine`),
  CONSTRAINT `fk_alternance_journal_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les documents, rangés à part des cours (storage/alternance).
CREATE TABLE IF NOT EXISTS `alternance_documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `categorie`   ENUM('contrat', 'livret', 'evaluation', 'rapport', 'autre') NOT NULL DEFAULT 'autre',
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke`  VARCHAR(100) NOT NULL,
  `mime`        VARCHAR(150) NOT NULL,
  `taille`      INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_documents_user` (`user_id`, `categorie`),
  CONSTRAINT `fk_alternance_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le rythme ne connaissait que l'école et l'entreprise. Une semaine de congés,
-- un jour férié ou une absence ne sont ni l'une ni l'autre : les compter comme
-- des jours d'entreprise fausserait le bilan et le journal.

ALTER TABLE `alternance_periodes`
  MODIFY `lieu` ENUM('ecole', 'entreprise', 'conges', 'ferie', 'absence') NOT NULL;

-- La fiche de l'alternance : l'entreprise, le tuteur, et les dates du contrat.
-- Une ligne par compte — on ne fait qu'une alternance à la fois.

CREATE TABLE IF NOT EXISTS `alternance_contrat` (
  `user_id`        INT UNSIGNED NOT NULL,
  `entreprise`     VARCHAR(150) NULL,
  `adresse`        VARCHAR(255) NULL,
  `poste`          VARCHAR(150) NULL,
  `tuteur`         VARCHAR(120) NULL,
  `tuteur_email`   VARCHAR(190) NULL,
  `tuteur_tel`     VARCHAR(40)  NULL,
  `referent`       VARCHAR(120) NULL,
  `debut`          DATE         NULL,
  `fin`            DATE         NULL,
  `remise_rapport` DATE         NULL,
  `soutenance`     DATE         NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_alternance_contrat_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le rappel du vendredi : écrire sa semaine dans le journal de l'alternance.
-- Les rappels déjà partis se notent dans la même table que les autres.

ALTER TABLE `rappels_envoyes`
  MODIFY `nature` ENUM('evenement', 'tache', 'liste', 'journal') NOT NULL;

-- Une note d'alternance qu'on garde en haut de la liste : les consignes qu'on
-- relit tout le temps ne doivent pas descendre à mesure qu'on en écrit d'autres.

ALTER TABLE `alternance_notes`
  ADD COLUMN `epinglee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `contenu`;

-- Un lien d'abonnement au rythme d'alternance : Outlook, Google et les autres
-- agendas le relisent tout seuls, et suivent les périodes qu'on change.
-- Le jeton se renouvelle d'un clic, ce qui coupe l'ancien lien.

ALTER TABLE `alternance_contrat`
  ADD COLUMN `jeton_ics` CHAR(32) NULL AFTER `soutenance`,
  ADD UNIQUE KEY `uniq_alternance_contrat_jeton` (`jeton_ics`);

-- Une tâche qui revient : écrire son journal chaque vendredi, envoyer le
-- compte-rendu chaque lundi. Cochée, elle renaît à l'échéance suivante ;
-- rien ne tourne en arrière-plan, c'est le geste de cocher qui la relance.

ALTER TABLE `taches`
  ADD COLUMN `recurrence` ENUM('jour', 'semaine', 'quinzaine', 'mois') NULL AFTER `echeance`;

-- Des étiquettes sur les notes d'alternance : « sécurité », « qualité »,
-- « outils »… On retrouve alors d'un clic tout ce qui parle du même sujet.

ALTER TABLE `alternance_notes`
  ADD COLUMN `etiquettes` VARCHAR(200) NULL AFTER `epinglee`;

-- Les sessions de révision : ce qu'on a révisé, combien de temps, et comment
-- on l'a vécu. Une session est ouverte au démarrage et refermée à la fin ;
-- celle qu'on abandonne sans refermer garde sa durée à zéro et ne compte pas.

CREATE TABLE IF NOT EXISTS `sessions_revision` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NULL,
  `sujet`      VARCHAR(150) NULL,
  `minutes_voulues` SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  `secondes`   INT UNSIGNED NOT NULL DEFAULT 0,
  `pauses`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ressenti`   ENUM('bien', 'moyen', 'dur') NULL,
  `debut`      DATETIME     NOT NULL,
  `fin`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_revision_user` (`user_id`, `debut`),
  CONSTRAINT `fk_sessions_revision_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessions_revision_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- L'objectif de révision de la semaine, en minutes (0 : aucun objectif), et
-- le silence pendant une session : les rappels attendent la fin plutôt que
-- d'interrompre ce pour quoi on s'est justement isolé.

ALTER TABLE `users`
  ADD COLUMN `objectif_revision` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `transcription_vocale`;

ALTER TABLE `sessions_revision`
  ADD COLUMN `ne_pas_deranger` TINYINT(1) NOT NULL DEFAULT 1 AFTER `minutes_voulues`;

-- Une session de révision peut porter sur plusieurs cours, ou sur un dossier
-- entier. Le cours principal reste sur la session (c'est celui qu'on ouvre),
-- et cette table dit tous ceux qu'elle couvre — celui-là compris.

CREATE TABLE IF NOT EXISTS `session_revision_cours` (
  `session_id` INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`session_id`, `cours_id`),
  KEY `idx_session_revision_cours_cours` (`cours_id`),
  CONSTRAINT `fk_src_session` FOREIGN KEY (`session_id`) REFERENCES `sessions_revision`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_src_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une session posée au calendrier peut viser plusieurs cours. L'évènement n'en
-- garde qu'un (le principal) ; cette table dit tous ceux qu'on avait prévus,
-- pour que le bouton « Démarrer la session » les retrouve tous.

CREATE TABLE IF NOT EXISTS `evenement_revision_cours` (
  `evenement_id` INT UNSIGNED NOT NULL,
  `cours_id`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`evenement_id`, `cours_id`),
  KEY `idx_evenement_revision_cours_cours` (`cours_id`),
  CONSTRAINT `fk_erc_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les travaux de groupe : un projet à plusieurs, avec qui fait quoi, les
-- fichiers, un document écrit ensemble, les échéances dans le calendrier de
-- chacun et la discussion du groupe.

CREATE TABLE IF NOT EXISTS `projets` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`             VARCHAR(120) NOT NULL,
  `description`     TEXT NULL,
  `cree_par`        INT UNSIGNED NULL,
  `conversation_id` INT UNSIGNED NULL,
  `document`        MEDIUMTEXT NULL,
  `document_version` INT UNSIGNED NOT NULL DEFAULT 0,
  `document_par`    INT UNSIGNED NULL,
  `document_le`     DATETIME NULL,
  `jeton`           CHAR(32) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projets_jeton` (`jeton`),
  KEY `idx_projets_conversation` (`conversation_id`),
  CONSTRAINT `fk_projets_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projets_document_par` FOREIGN KEY (`document_par`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projets_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un membre a un compte (invité, puis membre quand il accepte), ou n'en a
-- pas : il n'est alors qu'un nom, à qui l'on confie des tâches et qui suit
-- le projet par le lien public.
CREATE TABLE IF NOT EXISTS `projet_membres` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `nom`        VARCHAR(60) NULL,
  `role`       ENUM('admin','membre') NOT NULL DEFAULT 'membre',
  `statut`     ENUM('invite','membre') NOT NULL DEFAULT 'membre',
  `invite_par` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_membres_compte` (`projet_id`, `user_id`),
  KEY `idx_projet_membres_user` (`user_id`, `statut`),
  CONSTRAINT `fk_projet_membres_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_membres_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_membres_par` FOREIGN KEY (`invite_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projet_taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `note`       TEXT NULL,
  `membre_id`  INT UNSIGNED NULL,
  `echeance`   DATE NULL,
  `statut`     ENUM('a_faire','en_cours','fait') NOT NULL DEFAULT 'a_faire',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `cree_par`   INT UNSIGNED NULL,
  `fait_le`    DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projet_taches_projet` (`projet_id`, `statut`, `position`),
  KEY `idx_projet_taches_membre` (`membre_id`),
  CONSTRAINT `fk_projet_taches_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_taches_membre` FOREIGN KEY (`membre_id`) REFERENCES `projet_membres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projet_taches_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projet_fichiers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`   INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke`  VARCHAR(100) NOT NULL,
  `mime`        VARCHAR(150) NOT NULL,
  `taille`      INT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projet_fichiers_projet` (`projet_id`),
  CONSTRAINT `fk_projet_fichiers_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_fichiers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les versions précédentes du document commun, pour revenir en arrière.
CREATE TABLE IF NOT EXISTS `projet_versions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `contenu`    MEDIUMTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projet_versions_projet` (`projet_id`, `id`),
  CONSTRAINT `fk_projet_versions_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_versions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le rendu, la soutenance, les réunions : chacune a sa copie dans le
-- calendrier de chaque membre, tenue à jour par le projet.
CREATE TABLE IF NOT EXISTS `projet_echeances` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`       INT UNSIGNED NOT NULL,
  `nature`          ENUM('rendu','soutenance','reunion','autre') NOT NULL DEFAULT 'rendu',
  `titre`           VARCHAR(160) NOT NULL,
  `lieu`            VARCHAR(160) NULL,
  `debut`           DATETIME NOT NULL,
  `fin`             DATETIME NOT NULL,
  `journee_entiere` TINYINT(1) NOT NULL DEFAULT 0,
  `cree_par`        INT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projet_echeances_projet` (`projet_id`, `debut`),
  CONSTRAINT `fk_projet_echeances_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_echeances_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `evenements`
  ADD COLUMN `projet_echeance_id` INT UNSIGNED NULL AFTER `partage_de`,
  ADD KEY `idx_evt_projet_echeance` (`projet_echeance_id`),
  ADD CONSTRAINT `fk_evt_projet_echeance` FOREIGN KEY (`projet_echeance_id`) REFERENCES `projet_echeances`(`id`) ON DELETE CASCADE;

-- Une échéance de groupe peut porter un type à soi (« Projet », « Oral blanc »…),
-- en plus des types proposés : son nom, quand la nature est « autre ».

ALTER TABLE `projet_echeances`
  ADD COLUMN `type_nom` VARCHAR(40) NULL AFTER `nature`;

-- Les types d'échéance d'un travail de groupe se règlent comme les types
-- d'évènement : nom, icône, couleur, rappels, ordre. Chaque projet a les
-- siens, partagés par tous ses membres.

CREATE TABLE IF NOT EXISTS `projet_types` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id` INT UNSIGNED NOT NULL,
  `nom`       VARCHAR(40) NOT NULL,
  `icone`     VARCHAR(16) NOT NULL DEFAULT '📌',
  `couleur`   CHAR(7) NOT NULL DEFAULT '#64748b',
  `rappels`   VARCHAR(80) NOT NULL DEFAULT '1440',
  `position`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_types_nom` (`projet_id`, `nom`),
  CONSTRAINT `fk_projet_types_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projet_echeances`
  ADD COLUMN `type_id` INT UNSIGNED NULL AFTER `projet_id`,
  ADD KEY `idx_projet_echeances_type` (`type_id`),
  ADD CONSTRAINT `fk_projet_echeances_type` FOREIGN KEY (`type_id`) REFERENCES `projet_types`(`id`) ON DELETE SET NULL;

-- Les types de départ, dans chaque projet déjà créé.
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Rendu', '📦', '#dc2626', '2880,1440', 1 FROM `projets`;
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Soutenance', '🎤', '#7c3aed', '1440,60', 2 FROM `projets`;
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Réunion', '👥', '#0ea5e9', '60,15', 3 FROM `projets`;

-- Les types écrits à la main deviennent de vrais types.
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT DISTINCT `projet_id`, `type_nom`, '📌', '#64748b', '1440', 4
    FROM `projet_echeances` WHERE `nature` = 'autre' AND `type_nom` IS NOT NULL;

-- Chaque échéance retrouve son type.
UPDATE `projet_echeances` e JOIN `projet_types` t ON t.`projet_id` = e.`projet_id`
   AND t.`nom` = CASE e.`nature` WHEN 'rendu' THEN 'Rendu' WHEN 'soutenance' THEN 'Soutenance'
                                 WHEN 'reunion' THEN 'Réunion' ELSE e.`type_nom` END
   SET e.`type_id` = t.`id`;

ALTER TABLE `projet_echeances`
  DROP COLUMN `type_nom`,
  DROP COLUMN `nature`;

-- Choisir ce qu'on reçoit : les sortes de notifications coupées (calendrier,
-- tâches, messages…), séparées par des virgules. Vide : tout arrive — et une
-- sorte ajoutée plus tard arrive aussi, sans qu'on ait à la cocher.

ALTER TABLE `users`
  ADD COLUMN `notifications_coupees` VARCHAR(255) NOT NULL DEFAULT '' AFTER `objectif_revision`;
