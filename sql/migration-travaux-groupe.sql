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
