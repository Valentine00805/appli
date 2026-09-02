-- Une quatrième colonne « Validation » : deux booléens ne suffisaient plus.
-- « etape » vaut 0 (à faire), 1 (en cours) ou 2 (validation). Terminé reste
-- porté par « faite » / « termine », qui demeurent la référence ailleurs.
-- À exécuter une seule fois : mysql -u root < sql/migration-kanban-validation.sql

USE `mon_appli_cours`;

ALTER TABLE `taches`     ADD COLUMN `etape` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `en_cours`;
ALTER TABLE `evenements` ADD COLUMN `etape` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `en_cours`;

UPDATE `taches`     SET `etape` = 1 WHERE `en_cours` = 1;
UPDATE `evenements` SET `etape` = 1 WHERE `en_cours` = 1;

ALTER TABLE `taches`     DROP COLUMN `en_cours`;
ALTER TABLE `evenements` DROP COLUMN `en_cours`;
