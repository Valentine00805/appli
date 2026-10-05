-- Les favoris du menu en grille de la barre de navigation (comme les applications de Google) : la liste, en JSON, des
-- sections que le compte a choisies — ["calendrier","cours",…]. Vide (NULL) tant qu'il n'a rien choisi : l'application
-- montre alors ses favoris de départ (voir Menu::FAVORIS_PAR_DEFAUT).
--
-- À exécuter une seule fois (MySQL n'a pas « ADD COLUMN IF NOT EXISTS »).
--   mysql -u root < sql/migration-menu-favoris.sql

USE `mon_appli_cours`;

ALTER TABLE `users`
  ADD COLUMN `menu_favoris` VARCHAR(400) NULL DEFAULT NULL AFTER `langue`;
