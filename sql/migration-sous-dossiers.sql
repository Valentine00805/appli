-- Sous-dossiers : un dossier peut en contenir d'autres.
-- Supprimer un dossier ne supprime pas ses enfants : ils remontent à la
-- racine, comme ses cours redeviennent sans dossier.
-- À exécuter une seule fois : mysql -u root < sql/migration-sous-dossiers.sql

USE `mon_appli_cours`;

ALTER TABLE `dossiers`
  ADD COLUMN `parent_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
  ADD KEY `idx_dossier_parent` (`parent_id`),
  ADD CONSTRAINT `fk_dossiers_parent` FOREIGN KEY (`parent_id`)
      REFERENCES `dossiers`(`id`) ON DELETE SET NULL;
