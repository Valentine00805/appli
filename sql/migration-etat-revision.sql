-- Où l'on en est dans la révision d'un cours : 0 à réviser, 1 en cours, 2 révisée.
-- C'est l'utilisateur qui le dit ; l'application se contente d'en faire un total.
-- À exécuter une seule fois : mysql -u root < sql/migration-etat-revision.sql

USE `mon_appli_cours`;

ALTER TABLE `cours`
  ADD COLUMN `etat_revision` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `fiche_revision`;
