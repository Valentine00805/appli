-- Migration : règlement d'un mois de remboursements.
--
-- Quand un mois est déclaré remboursé, ses lignes passent au statut « remboursé »
-- et une recette du même montant est créée dans les opérations du mois suivant :
-- l'argent rendu revient bien sur le compte. Cette table garde la trace du
-- règlement pour pouvoir l'annuler proprement.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-reglements.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `reglements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `periode`        CHAR(7)       NOT NULL COMMENT 'mois réglé, AAAA-MM',
  `personne`       VARCHAR(80)   DEFAULT NULL COMMENT 'NULL = tout le monde',
  `montant`        DECIMAL(10,2) NOT NULL,
  `date_reglement` DATE          NOT NULL,
  `operation_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'la recette créée en retour',
  `lignes`         TEXT          NOT NULL COMMENT 'identifiants des dépenses soldées, en JSON',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reglement` (`user_id`, `periode`, `personne`),
  CONSTRAINT `fk_reglement_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reglement_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reglements') AS table_creee;
