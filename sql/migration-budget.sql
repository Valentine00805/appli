-- Migration : ajout du module « Budget » (recettes, dépenses, catégories).
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-budget.sql

USE `mon_appli_cours`;

-- 1. Catégories de budget -----------------------------------------------------

CREATE TABLE IF NOT EXISTS `categories_budget` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `nom`              VARCHAR(60)  NOT NULL,
  `icone`            VARCHAR(16)  NOT NULL DEFAULT '💶',
  `couleur`          CHAR(7)      NOT NULL DEFAULT '#64748b',
  `sens`             ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `plafond_mensuel`  DECIMAL(10,2) DEFAULT NULL,
  `position`         SMALLINT     NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_user_nom_sens` (`user_id`, `nom`, `sens`),
  KEY `idx_cat_user_sens` (`user_id`, `sens`, `position`),
  CONSTRAINT `fk_cat_budget_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Opérations ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `operations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `categorie_id`   INT UNSIGNED DEFAULT NULL,
  `libelle`        VARCHAR(160)  NOT NULL,
  `montant`        DECIMAL(10,2) NOT NULL,
  `sens`           ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `date_operation` DATE          NOT NULL,
  `moyen`          VARCHAR(40)   DEFAULT NULL,
  `note`           TEXT          DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_op_user_date` (`user_id`, `date_operation`),
  KEY `idx_op_categorie` (`categorie_id`),
  CONSTRAINT `fk_op_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_budget`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Catégories de départ pour chaque compte existant -------------------------

INSERT IGNORE INTO `categories_budget` (`user_id`, `nom`, `icone`, `couleur`, `sens`, `position`)
SELECT u.id, v.nom, v.icone, v.couleur, v.sens, v.position
FROM `users` u
CROSS JOIN (
    SELECT 'Courses'      AS nom, '🛒' AS icone, '#059669' AS couleur, 'depense' AS sens, 1 AS position
    UNION ALL SELECT 'Transport',    '🚌', '#0ea5e9', 'depense', 2
    UNION ALL SELECT 'Logement',     '🏠', '#7c3aed', 'depense', 3
    UNION ALL SELECT 'Sorties',      '🎉', '#db2777', 'depense', 4
    UNION ALL SELECT 'Fournitures',  '✏️', '#ca8a04', 'depense', 5
    UNION ALL SELECT 'Santé',        '💊', '#dc2626', 'depense', 6
    UNION ALL SELECT 'Abonnements',  '📱', '#ea580c', 'depense', 7
    UNION ALL SELECT 'Divers',       '💶', '#64748b', 'depense', 8
    UNION ALL SELECT 'Bourse',       '🎓', '#059669', 'recette', 1
    UNION ALL SELECT 'Salaire',      '💼', '#4f46e5', 'recette', 2
    UNION ALL SELECT 'Aide famille', '👪', '#0ea5e9', 'recette', 3
    UNION ALL SELECT 'Autre',        '💰', '#64748b', 'recette', 4
) v;

-- 4. Contrôle -----------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM `categories_budget`) AS categories_creees,
    (SELECT COUNT(*) FROM `operations`)        AS operations;
