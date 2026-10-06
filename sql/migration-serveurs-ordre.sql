-- L'ordre de mes serveurs dans la barre : propre à chaque membre (glisser-déposer). 0 partout tant qu'on n'a rien déplacé :
-- les serveurs se rangent alors par nom ; un serveur qu'on rejoint ou qu'on crée prend la place suivante.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-serveurs-ordre.sql

USE `mon_appli_cours`;
SET NAMES utf8mb4;

ALTER TABLE `serveur_membres`
  ADD COLUMN `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `role`;
