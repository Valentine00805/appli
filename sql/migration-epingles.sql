-- Épingler des messages.
--
-- Une épingle est personnelle : on épingle pour soi, l'autre ne le voit pas.
-- Elle disparaît avec le message ou avec le compte.
CREATE TABLE IF NOT EXISTS `epingles` (
  `user_id`    INT UNSIGNED NOT NULL,
  `message_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `message_id`),
  KEY `idx_epingles_message` (`message_id`),
  CONSTRAINT `fk_epingles_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_epingles_message` FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
