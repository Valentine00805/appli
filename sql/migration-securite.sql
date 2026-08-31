-- Migration : limitation des tentatives de connexion.
--
-- Chaque essai, réussi ou non, laisse une trace. Trop d'échecs sur un même
-- compte ou depuis une même adresse bloquent temporairement les essais, ce qui
-- rend inutile une attaque par mots de passe successifs.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-securite.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) DEFAULT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `reussie`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentative_email` (`email`, `created_at`),
  KEY `idx_tentative_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tentatives_connexion') AS table_creee;
