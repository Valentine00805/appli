-- Colonne « En cours » du tableau kanban.
-- On n'écrase pas « faite » / « termine » : un élément terminé le reste, quel
-- que soit cet indicateur, qui ne sert qu'à distinguer ce qui est commencé.
-- À exécuter une seule fois : mysql -u root < sql/migration-kanban.sql

USE `mon_appli_cours`;

ALTER TABLE `taches`
  ADD COLUMN `en_cours` TINYINT(1) NOT NULL DEFAULT 0 AFTER `faite_le`;

ALTER TABLE `evenements`
  ADD COLUMN `en_cours` TINYINT(1) NOT NULL DEFAULT 0 AFTER `termine`;
