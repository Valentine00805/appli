-- Une fiche de révision par cours, écrite dans l'application.
-- À exécuter une seule fois : mysql -u root < sql/migration-fiche-revision.sql

USE `mon_appli_cours`;

ALTER TABLE `cours`
  ADD COLUMN `fiche_revision` MEDIUMTEXT DEFAULT NULL AFTER `contenu`;
