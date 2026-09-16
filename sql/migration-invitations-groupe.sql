-- Les invitations dans une discussion de groupe.
--
-- Un administrateur ajoute directement ses amis ; une autre personne, trouvée
-- par son pseudo, reçoit une invitation qu'elle accepte ou refuse. Personne
-- n'entre dans un groupe sans l'avoir voulu.
CREATE TABLE IF NOT EXISTS `conversation_invitations` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `invite_par`      INT UNSIGNED NULL,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`conversation_id`, `user_id`),
  KEY `idx_conversation_invitations_user` (`user_id`),
  KEY `idx_conversation_invitations_par` (`invite_par`),
  CONSTRAINT `fk_conversation_invitations_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_invitations_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_invitations_par`          FOREIGN KEY (`invite_par`)      REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
