-- Migration : import de relevés bancaires.
--
-- Deux colonnes s'ajoutent aux opérations :
--   * source    : distingue une saisie manuelle d'une ligne importée ;
--   * empreinte : signature date + montant + libellé, pour repérer un doublon
--                 si le même relevé est importé deux fois.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-import.sql

USE `mon_appli_cours`;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND COLUMN_NAME = 'source');
SET @sql := IF(@col = 0,
    "ALTER TABLE `operations`
       ADD COLUMN `source` ENUM('manuelle','import') NOT NULL DEFAULT 'manuelle' AFTER `moyen`",
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND COLUMN_NAME = 'empreinte');
SET @sql := IF(@col = 0,
    'ALTER TABLE `operations` ADD COLUMN `empreinte` CHAR(40) NULL AFTER `source`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND INDEX_NAME = 'idx_op_empreinte');
SET @sql := IF(@idx = 0,
    'CREATE INDEX `idx_op_empreinte` ON `operations` (`user_id`, `empreinte`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'operations' AND COLUMN_NAME IN ('source','empreinte')) AS colonnes_ajoutees;
