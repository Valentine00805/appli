-- Schéma de la base « Mes Cours »
-- Exécuter une seule fois : mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS `mon_appli_cours`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
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

CREATE TABLE IF NOT EXISTS `cours` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `matiere_id` INT UNSIGNED DEFAULT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `contenu`    LONGTEXT     NULL,
  `favori`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cours_user` (`user_id`),
  KEY `idx_cours_matiere` (`matiere_id`),
  KEY `idx_cours_titre` (`titre`),
  CONSTRAINT `fk_cours_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_cours_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL
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
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke` VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(120) NOT NULL,
  `taille`     INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fichiers_cours` (`cours_id`),
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
  `position`     SMALLINT     NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_user_nom` (`user_id`, `nom`),
  KEY `idx_type_user_position` (`user_id`, `position`),
  CONSTRAINT `fk_types_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `matiere_id`  INT UNSIGNED DEFAULT NULL,
  `type_id`     INT UNSIGNED DEFAULT NULL,
  `cours_id`    INT UNSIGNED DEFAULT NULL,
  `titre`       VARCHAR(200) NOT NULL,
  `description` TEXT         NULL,
  `lieu`        VARCHAR(160) NULL,
  `debut`       DATETIME     NOT NULL,
  `fin`         DATETIME     NOT NULL,
  `journee_entiere` TINYINT(1) NOT NULL DEFAULT 0,
  `termine`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evt_user_debut` (`user_id`, `debut`),
  KEY `idx_evt_type` (`type_id`),
  CONSTRAINT `fk_evt_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_evt_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_type`    FOREIGN KEY (`type_id`)    REFERENCES `types_evenement`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_cours`   FOREIGN KEY (`cours_id`)   REFERENCES `cours`(`id`)    ON DELETE SET NULL
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
