-- Les discussions que chacun épingle en haut de sa liste (Amis) : une personne (un ami) ou un groupe de discussion. Un épinglage est à soi :
-- épingler une discussion ne la change pas pour l'autre. Il disparaît avec le compte ; un ami retiré ou un groupe quitté n'apparaît plus
-- dans la liste, et son épinglage ne se voit donc plus.
--
-- Tant que cette table n'existe pas, le site tourne : la liste n'a simplement pas de section « Épinglées », et l'épinglage répond par un message.

CREATE TABLE IF NOT EXISTS `discussions_epinglees` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `genre`      ENUM('ami', 'groupe') NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_discussions_epinglees` (`user_id`, `genre`, `cible_id`),
  CONSTRAINT `fk_discussions_epinglees_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
