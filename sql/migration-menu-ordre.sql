-- L'ordre des tuiles du menu en grille de la barre : chacun peut les ranger à sa façon (glisser-déposer, y compris dans
-- « Toutes les sections »).
--
-- `menu_ordre` : la liste de toutes les sections, dans l'ordre voulu, en JSON — ["accueil","calendrier",…]. NULL tant qu'on
-- n'a rien changé : l'ordre du catalogue (voir Menu::SECTIONS).
--
-- À exécuter une seule fois (MySQL n'a pas « ADD COLUMN IF NOT EXISTS »).
--   mysql -u root < sql/migration-menu-ordre.sql

USE `mon_appli_cours`;

ALTER TABLE `users`
  ADD COLUMN `menu_ordre` VARCHAR(400) NULL DEFAULT NULL AFTER `menu_favoris`;
