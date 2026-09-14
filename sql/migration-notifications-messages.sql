-- Les notifications de messages.
--
-- Pour chaque personne et chacun de ses amis : quand elle a eu la discussion
-- sous les yeux pour la dernière fois (inutile de la prévenir d'un message
-- qu'elle voit arriver), et quand elle a été notifiée pour la dernière fois
-- (plusieurs messages d'affilée ne font vibrer le téléphone qu'une fois).
CREATE TABLE IF NOT EXISTS `discussions_etat` (
  `user_id`     INT UNSIGNED NOT NULL,
  `ami_id`      INT UNSIGNED NOT NULL,
  `regarde_le`  DATETIME     NULL,
  `notifie_le`  DATETIME     NULL,
  PRIMARY KEY (`user_id`, `ami_id`),
  KEY `idx_discussions_ami` (`ami_id`),
  CONSTRAINT `fk_discussions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_discussions_ami`  FOREIGN KEY (`ami_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
