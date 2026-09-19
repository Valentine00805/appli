-- Les évènements qu'un ami me partage paraissent-ils d'office dans mon calendrier ?
--
-- Un réglage par ami : une ligne ici, et ses évènements partagés s'affichent
-- dans mon calendrier et sur mon accueil, toujours à jour, sans que j'aie à
-- les ajouter un par un. Sans ligne, ils restent dans « Partagés ».

CREATE TABLE IF NOT EXISTS `partages_calendrier` (
  `user_id`    INT UNSIGNED NOT NULL,
  `ami_id`     INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `ami_id`),
  KEY `idx_partages_calendrier_ami` (`ami_id`),
  CONSTRAINT `fk_partages_calendrier_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_partages_calendrier_ami`  FOREIGN KEY (`ami_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
