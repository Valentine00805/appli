-- Migration : des groupes de personnes, pour lire plusieurs comptes d'un coup.
--
-- Un groupe ne se choisit pas sur une dépense : celle-ci reste due par une
-- personne, et une seule. Le groupe sert à regarder — « combien me doivent mes
-- colocataires, tous ensemble » — sur la page des remboursements.
--
-- La table de liaison porte son user_id, comme les autres tables du compte :
-- toutes les requêtes de l'application vérifient le propriétaire, et la
-- sauvegarde s'en sert pour retrouver ses lignes.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-groupes.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `groupes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groupe_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_groupe_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groupe_personne` (
  `user_id`     INT UNSIGNED NOT NULL,
  `groupe_id`   INT UNSIGNED NOT NULL,
  `personne_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`groupe_id`, `personne_id`),
  KEY `idx_gp_user` (`user_id`),
  KEY `idx_gp_personne` (`personne_id`),
  CONSTRAINT `fk_gp_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_gp_groupe`   FOREIGN KEY (`groupe_id`)   REFERENCES `groupes`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_gp_personne` FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT (SELECT COUNT(*) FROM `groupes`) AS groupes,
       (SELECT COUNT(*) FROM `groupe_personne`) AS appartenances;
