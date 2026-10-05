-- Les liens vers d'autres applications : l'adresse du logo, distincte de l'adresse du site.
--
-- `logo` : l'adresse (http ou https) d'une image que le navigateur affiche en guise d'icône. Vide, l'application prend
-- l'emoji choisi, sinon l'icône du site (voir LienApp::image).
--
-- À exécuter une seule fois (MySQL n'a pas « ADD COLUMN IF NOT EXISTS »).
--   mysql -u root < sql/migration-liens-apps-logo.sql

USE `mon_appli_cours`;

ALTER TABLE `liens_apps`
  ADD COLUMN `logo` VARCHAR(500) NOT NULL DEFAULT '' AFTER `icone`;
