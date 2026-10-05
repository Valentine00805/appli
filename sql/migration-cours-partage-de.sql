-- Un cours copié depuis un partage se souvient du cours d'origine : quand son propriétaire ajoute ensuite à ses matières celle du cours
-- partagé, ses copies la reçoivent aussi (voir Partages::ajouterMatiere).
--
-- `partage_de` : le cours copié. Pas de clé étrangère : le cours d'origine peut disparaître, la copie reste.
--
-- À exécuter une seule fois (MySQL n'a pas « ADD COLUMN IF NOT EXISTS »).
--   mysql -u root < sql/migration-cours-partage-de.sql

USE `mon_appli_cours`;

ALTER TABLE `cours`
  ADD COLUMN `partage_de` INT UNSIGNED NULL AFTER `dossier_id`,
  ADD KEY `idx_cours_partage_de` (`partage_de`);
