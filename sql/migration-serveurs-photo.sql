-- La photo d'un serveur : son logo, à la place de l'icône (un emoji). Le fichier est rangé comme les photos des groupes.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-serveurs-photo.sql

USE `mon_appli_cours`;
SET NAMES utf8mb4;

ALTER TABLE `serveurs`
  ADD COLUMN `photo_nom`  VARCHAR(64) NULL AFTER `icone`,
  ADD COLUMN `photo_mime` VARCHAR(40) NULL AFTER `photo_nom`;
