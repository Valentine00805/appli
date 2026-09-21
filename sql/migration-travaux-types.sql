-- Les types d'échéance d'un travail de groupe se règlent comme les types
-- d'évènement : nom, icône, couleur, rappels, ordre. Chaque projet a les
-- siens, partagés par tous ses membres.

CREATE TABLE IF NOT EXISTS `projet_types` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id` INT UNSIGNED NOT NULL,
  `nom`       VARCHAR(40) NOT NULL,
  `icone`     VARCHAR(16) NOT NULL DEFAULT '📌',
  `couleur`   CHAR(7) NOT NULL DEFAULT '#64748b',
  `rappels`   VARCHAR(80) NOT NULL DEFAULT '1440',
  `position`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_types_nom` (`projet_id`, `nom`),
  CONSTRAINT `fk_projet_types_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projet_echeances`
  ADD COLUMN `type_id` INT UNSIGNED NULL AFTER `projet_id`,
  ADD KEY `idx_projet_echeances_type` (`type_id`),
  ADD CONSTRAINT `fk_projet_echeances_type` FOREIGN KEY (`type_id`) REFERENCES `projet_types`(`id`) ON DELETE SET NULL;

-- Les types de départ, dans chaque projet déjà créé.
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Rendu', '📦', '#dc2626', '2880,1440', 1 FROM `projets`;
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Soutenance', '🎤', '#7c3aed', '1440,60', 2 FROM `projets`;
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT `id`, 'Réunion', '👥', '#0ea5e9', '60,15', 3 FROM `projets`;

-- Les types écrits à la main deviennent de vrais types.
INSERT IGNORE INTO `projet_types` (`projet_id`, `nom`, `icone`, `couleur`, `rappels`, `position`)
  SELECT DISTINCT `projet_id`, `type_nom`, '📌', '#64748b', '1440', 4
    FROM `projet_echeances` WHERE `nature` = 'autre' AND `type_nom` IS NOT NULL;

-- Chaque échéance retrouve son type.
UPDATE `projet_echeances` e JOIN `projet_types` t ON t.`projet_id` = e.`projet_id`
   AND t.`nom` = CASE e.`nature` WHEN 'rendu' THEN 'Rendu' WHEN 'soutenance' THEN 'Soutenance'
                                 WHEN 'reunion' THEN 'Réunion' ELSE e.`type_nom` END
   SET e.`type_id` = t.`id`;

ALTER TABLE `projet_echeances`
  DROP COLUMN `type_nom`,
  DROP COLUMN `nature`;
