-- Migration : rendre les types d'évènement modifiables par l'utilisateur.
--
-- Avant : la colonne `evenements`.`type` était un ENUM figé dans le schéma.
-- Après : une table `types_evenement` par utilisateur, référencée par `evenements`.`type_id`.
--
-- À exécuter une seule fois. Le script est écrit pour ne rien casser s'il est relancé.
--   mysql -u root < sql/migration-types-evenement.sql

USE `mon_appli_cours`;

-- 1. La table des types ------------------------------------------------------

CREATE TABLE IF NOT EXISTS `types_evenement` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `nom`          VARCHAR(60)  NOT NULL,
  `icone`        VARCHAR(16)  NOT NULL DEFAULT '📌',
  `couleur`      CHAR(7)      NOT NULL DEFAULT '#64748b',
  `est_echeance` TINYINT(1)   NOT NULL DEFAULT 0,
  `position`     SMALLINT     NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_user_nom` (`user_id`, `nom`),
  KEY `idx_type_user_position` (`user_id`, `position`),
  CONSTRAINT `fk_types_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Les cinq types d'origine, pour chaque compte existant -------------------

INSERT IGNORE INTO `types_evenement` (`user_id`, `nom`, `icone`, `couleur`, `est_echeance`, `position`)
SELECT u.id, v.nom, v.icone, v.couleur, v.est_echeance, v.position
FROM `users` u
CROSS JOIN (
    SELECT 'Cours'    AS nom, '📘' AS icone, '#4f46e5' AS couleur, 0 AS est_echeance, 1 AS position
    UNION ALL SELECT 'Examen',   '📝', '#dc2626', 1, 2
    UNION ALL SELECT 'Devoir',   '🗂️', '#ea580c', 1, 3
    UNION ALL SELECT 'Révision', '🔁', '#059669', 0, 4
    UNION ALL SELECT 'Autre',    '📌', '#64748b', 0, 5
) v;

-- 3. La colonne type_id sur les évènements -----------------------------------

SET @colonne_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements' AND COLUMN_NAME = 'type_id'
);
SET @sql := IF(@colonne_existe = 0,
    'ALTER TABLE `evenements` ADD COLUMN `type_id` INT UNSIGNED NULL AFTER `matiere_id`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cle_existe := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements' AND CONSTRAINT_NAME = 'fk_evt_type'
);
SET @sql := IF(@cle_existe = 0,
    'ALTER TABLE `evenements` ADD CONSTRAINT `fk_evt_type` FOREIGN KEY (`type_id`) REFERENCES `types_evenement`(`id`) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Report des anciens types ENUM vers les nouvelles lignes ------------------

SET @ancienne_colonne := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements' AND COLUMN_NAME = 'type'
);
SET @sql := IF(@ancienne_colonne = 1,
    "UPDATE `evenements` e
     JOIN `types_evenement` t
       ON t.user_id = e.user_id
      AND t.nom = CASE e.type
            WHEN 'cours'    THEN 'Cours'
            WHEN 'examen'   THEN 'Examen'
            WHEN 'devoir'   THEN 'Devoir'
            WHEN 'revision' THEN 'Révision'
            ELSE 'Autre'
          END
     SET e.type_id = t.id
     WHERE e.type_id IS NULL",
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Suppression de l'ancienne colonne ENUM ----------------------------------

SET @sql := IF(@ancienne_colonne = 1,
    'ALTER TABLE `evenements` DROP COLUMN `type`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Contrôle ----------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM `types_evenement`)                          AS types_crees,
    (SELECT COUNT(*) FROM `evenements`)                               AS evenements,
    (SELECT COUNT(*) FROM `evenements` WHERE `type_id` IS NULL)       AS evenements_sans_type;
