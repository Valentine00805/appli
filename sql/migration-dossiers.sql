-- Dossiers de rangement des cours.
-- Un autre axe que les matières : « Semestre 1 », « Stage », « Archives »…
-- Supprimer un dossier ne supprime pas les cours : ils redeviennent sans dossier.
-- À exécuter une seule fois : mysql -u root < sql/migration-dossiers.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `dossiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '📁',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dossier_user_nom` (`user_id`, `nom`),
  KEY `idx_dossier_position` (`user_id`, `position`),
  CONSTRAINT `fk_dossiers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cours`
  ADD COLUMN `dossier_id` INT UNSIGNED DEFAULT NULL AFTER `matiere_id`,
  ADD KEY `idx_cours_dossier` (`dossier_id`),
  ADD CONSTRAINT `fk_cours_dossier` FOREIGN KEY (`dossier_id`)
      REFERENCES `dossiers`(`id`) ON DELETE SET NULL;
