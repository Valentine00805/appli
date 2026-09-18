-- Répondre à un commentaire, et l'aimer.
--
-- Les réponses tiennent sur un seul niveau, sous le commentaire qu'elles
-- suivent : répondre à une réponse la range sous le même commentaire. Retirer
-- un commentaire emporte ses réponses et ses « j'aime ».

ALTER TABLE commentaires_partage
    ADD COLUMN reponse_a INT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_commentaire_reponse (reponse_a),
    ADD CONSTRAINT fk_commentaire_reponse FOREIGN KEY (reponse_a) REFERENCES commentaires_partage(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `commentaires_jaime` (
  `commentaire_id` INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `created_at`     DATETIME     NOT NULL,
  PRIMARY KEY (`commentaire_id`, `user_id`),
  KEY `idx_jaime_user` (`user_id`),
  CONSTRAINT `fk_jaime_commentaire` FOREIGN KEY (`commentaire_id`) REFERENCES `commentaires_partage`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jaime_user`        FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)                ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
