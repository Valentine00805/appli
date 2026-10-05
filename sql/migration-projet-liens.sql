-- Les cours et dossiers liés à un travail de groupe : un membre y range un de ses cours ou dossiers ; les autres membres le lisent
-- et peuvent l'ajouter à leur espace (la même copie que pour un document partagé, voir Partages::copier).
--
-- `type` / `cible_id` : un « cours » ou un « dossier » de `ajoute_par` (pas de clé étrangère sur la cible : elle est polymorphe ; une
-- cible effacée disparaît de la liste). L'accès suit l'appartenance : un membre (invitation acceptée) lit ce qui est lié, quitter le
-- groupe retire l'accès (voir Partages::droitDirect).
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-projet-liens.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `projet_liens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `type`       ENUM('cours', 'dossier') NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `ajoute_par` INT UNSIGNED NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_liens_cible` (`projet_id`, `type`, `cible_id`),
  KEY `idx_projet_liens_cible` (`type`, `cible_id`),
  KEY `idx_projet_liens_par` (`ajoute_par`),
  CONSTRAINT `fk_projet_liens_projet` FOREIGN KEY (`projet_id`)  REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_liens_par`    FOREIGN KEY (`ajoute_par`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
