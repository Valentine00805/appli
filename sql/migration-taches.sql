-- Listes de tâches : listes, tâches, échéances, cases à cocher.
-- À exécuter une seule fois : mysql -u root < sql/migration-taches.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `listes_taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_liste_user_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_listes_taches_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `liste_id`   INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `echeance`   DATE         DEFAULT NULL,
  `faite`      TINYINT(1)   NOT NULL DEFAULT 0,
  `faite_le`   DATETIME     DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_taches_user` (`user_id`),
  KEY `idx_taches_liste` (`liste_id`),
  KEY `idx_taches_echeance` (`user_id`, `faite`, `echeance`),
  CONSTRAINT `fk_taches_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_taches_liste` FOREIGN KEY (`liste_id`) REFERENCES `listes_taches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
