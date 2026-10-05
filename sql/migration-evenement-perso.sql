-- Ce que chacun peut régler sur un évènement qu'un ami lui a partagé, sans toucher à l'évènement de l'ami : ses propres rappels, et
-- ses notes (lisibles par lui et par le propriétaire de l'évènement, pas par les autres personnes à qui il est partagé).
--
-- `rappels` : les délais en minutes, comme pour un évènement (« 1440,15 »). Vide, pas de rappel.
-- `note`    : le texte de ses notes.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-evenement-perso.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `evenement_perso_amis` (
  `evenement_id` INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `rappels`      VARCHAR(80)  NOT NULL DEFAULT '',
  `note`         TEXT         NULL,
  `updated_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`evenement_id`, `user_id`),
  KEY `idx_evenement_perso_user` (`user_id`),
  CONSTRAINT `fk_evenement_perso_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evenement_perso_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
