-- Migration : suivi des dépenses à se faire rembourser.
--
-- Cinq colonnes s'ajoutent aux opérations :
--   * a_rembourser       : la case à cocher ;
--   * part_rembourser    : montant réclamé s'il diffère de la dépense (essence
--                          partagée en deux, par exemple). NULL = tout ;
--   * rembourse_par      : qui doit rembourser (parents, un ami…) ;
--   * statut_remb        : à réclamer, mis de côté hors total, ou réglé ;
--   * date_remboursement : quand le remboursement a eu lieu.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-remboursements.sql

USE `mon_appli_cours`;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'operations' AND COLUMN_NAME = 'a_rembourser');
SET @sql := IF(@c = 0,
    "ALTER TABLE `operations`
       ADD COLUMN `a_rembourser`       TINYINT(1)    NOT NULL DEFAULT 0 AFTER `note`,
       ADD COLUMN `part_rembourser`    DECIMAL(10,2) NULL     AFTER `a_rembourser`,
       ADD COLUMN `rembourse_par`      VARCHAR(80)   NULL     AFTER `part_rembourser`,
       ADD COLUMN `statut_remb`        ENUM('a_reclamer','hors_total','rembourse')
                                       NOT NULL DEFAULT 'a_reclamer' AFTER `rembourse_par`,
       ADD COLUMN `date_remboursement` DATE          NULL     AFTER `statut_remb`",
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'operations' AND INDEX_NAME = 'idx_op_remboursement');
SET @sql := IF(@i = 0,
    'CREATE INDEX `idx_op_remboursement` ON `operations` (`user_id`, `a_rembourser`, `statut_remb`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'operations'
    AND COLUMN_NAME IN ('a_rembourser','part_rembourser','rembourse_par','statut_remb','date_remboursement'))
  AS colonnes_ajoutees;
