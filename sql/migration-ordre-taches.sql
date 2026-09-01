-- Ordre choisi des sous-tâches, à l'intérieur de leur liste.
-- À exécuter une seule fois : mysql -u root < sql/migration-ordre-taches.sql

USE `mon_appli_cours`;

ALTER TABLE `taches`
  ADD COLUMN `position` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `faite_le`,
  ADD KEY `idx_taches_position` (`liste_id`, `position`);

-- Les sous-tâches existantes conservent l'ordre qu'elles avaient à l'écran :
-- échéance la plus proche d'abord, sans date à la fin.
UPDATE `taches` t
JOIN (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY liste_id
           ORDER BY echeance IS NULL, echeance, created_at, id
         ) AS rang
  FROM `taches`
) AS ordre ON ordre.id = t.id
SET t.position = ordre.rang;
