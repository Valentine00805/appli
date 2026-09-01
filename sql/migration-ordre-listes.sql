-- Ordre choisi des listes de tâches.
-- À exécuter une seule fois : mysql -u root < sql/migration-ordre-listes.sql

USE `mon_appli_cours`;

ALTER TABLE `listes_taches`
  ADD COLUMN `position` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `echeance`,
  ADD KEY `idx_listes_position` (`user_id`, `position`);

-- Les listes déjà créées gardent leur ordre d'apparition.
SET @rang := 0, @compte := 0;
UPDATE `listes_taches` l
JOIN (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at, id) AS rang
  FROM `listes_taches`
) AS ordre ON ordre.id = l.id
SET l.position = ordre.rang;
