-- Migration : prévisions budgétaires.
--
-- Deux notions s'ajoutent au budget :
--   * les récurrences   : charges fixes et revenus réguliers, qui reviennent chaque mois ;
--   * les soldes saisis : un solde constaté à la main, qui sert de point de départ.
--
-- Le solde prévisionnel n'est jamais stocké : il se recalcule à partir du dernier
-- solde saisi, en enchaînant les mois. Modifier une opération ancienne met donc
-- automatiquement à jour toutes les prévisions qui en découlent.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-previsions.sql

USE `mon_appli_cours`;

-- 1. Charges fixes et revenus réguliers --------------------------------------

CREATE TABLE IF NOT EXISTS `recurrences` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `categorie_id` INT UNSIGNED DEFAULT NULL,
  `libelle`      VARCHAR(160)  NOT NULL,
  `montant`      DECIMAL(10,2) NOT NULL,
  `sens`         ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `jour_du_mois` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `moyen`        VARCHAR(40)   DEFAULT NULL,
  `actif`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_user` (`user_id`, `actif`),
  CONSTRAINT `fk_rec_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_budget`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Soldes saisis à la main --------------------------------------------------

CREATE TABLE IF NOT EXISTS `soldes_saisis` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `periode`    CHAR(7)       NOT NULL COMMENT 'AAAA-MM',
  `montant`    DECIMAL(12,2) NOT NULL,
  `note`       VARCHAR(160)  DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_solde_user_periode` (`user_id`, `periode`),
  CONSTRAINT `fk_solde_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Lien entre une opération et la récurrence qui l'a produite ---------------
--    Il évite de compter deux fois une charge fixe déjà saisie dans le réel.

SET @colonne := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND COLUMN_NAME = 'recurrence_id'
);
SET @sql := IF(@colonne = 0,
    'ALTER TABLE `operations` ADD COLUMN `recurrence_id` INT UNSIGNED NULL AFTER `categorie_id`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cle := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND CONSTRAINT_NAME = 'fk_op_recurrence'
);
SET @sql := IF(@cle = 0,
    'ALTER TABLE `operations`
       ADD CONSTRAINT `fk_op_recurrence` FOREIGN KEY (`recurrence_id`)
       REFERENCES `recurrences`(`id`) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' AND INDEX_NAME = 'idx_op_recurrence_date'
);
SET @sql := IF(@index = 0,
    'CREATE INDEX `idx_op_recurrence_date` ON `operations` (`recurrence_id`, `date_operation`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Contrôle -----------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM `recurrences`)   AS recurrences,
    (SELECT COUNT(*) FROM `soldes_saisis`) AS soldes_saisis,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations'
        AND COLUMN_NAME = 'recurrence_id')  AS colonne_ajoutee;
