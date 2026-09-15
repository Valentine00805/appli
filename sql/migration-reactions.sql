-- Réagir à un message avec un emoji.
--
-- Une réaction par personne et par message : en choisir une autre la
-- remplace, recliquer la même l'enlève. « reactions_le » sur le message note
-- le dernier changement, pour qu'une page déjà ouverte de l'autre côté
-- reprenne les réactions au relevé suivant.
CREATE TABLE IF NOT EXISTS `reactions` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(32)  NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_reactions_user` (`user_id`),
  CONSTRAINT `fk_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reactions_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE messages
    ADD COLUMN reactions_le DATETIME NULL AFTER modifie_le,
    ADD KEY idx_messages_reactions (expediteur_id, destinataire_id, reactions_le);
