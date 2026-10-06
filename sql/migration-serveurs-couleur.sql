-- La couleur du fond des initiales d'un serveur sans photo (#rrggbb). NULL : une couleur déduite du nom, comme avant.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-serveurs-couleur.sql

USE `mon_appli_cours`;
SET NAMES utf8mb4;

ALTER TABLE `serveurs`
  ADD COLUMN `couleur` CHAR(7) NULL AFTER `photo_mime`;
