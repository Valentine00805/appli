-- Bloquer un compte.
--
-- Qui bloque met fin à l'amitié et à toute demande en cours ; le compte bloqué
-- ne peut plus le trouver par son pseudo, ni lui écrire, ni le redemander en
-- ami — sans qu'on le lui dise. Débloquer retire la ligne, sans refaire
-- l'amitié.
CREATE TABLE IF NOT EXISTS `blocages` (
  `bloqueur_id` INT UNSIGNED NOT NULL,
  `bloque_id`   INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`bloqueur_id`, `bloque_id`),
  KEY `idx_blocages_bloque` (`bloque_id`),
  CONSTRAINT `fk_blocages_bloqueur` FOREIGN KEY (`bloqueur_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blocages_bloque`   FOREIGN KEY (`bloque_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
