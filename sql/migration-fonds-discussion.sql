-- Le fond d'écran d'une conversation.
--
-- Un par paire de comptes, le même pour les deux : l'un le choisit, l'autre le
-- voit aussitôt. L'image, réencodée comme les photos des messages, est rangée
-- dans storage/messages. Il survit à un retrait des amis, comme les messages.
CREATE TABLE IF NOT EXISTS `fonds_discussion` (
  `petit_id`   INT UNSIGNED NOT NULL,
  `grand_id`   INT UNSIGNED NOT NULL,
  `image_nom`  VARCHAR(64)  NOT NULL,
  `image_mime` VARCHAR(40)  NOT NULL,
  `choisi_par` INT UNSIGNED NULL,
  `choisi_le`  DATETIME     NOT NULL,
  PRIMARY KEY (`petit_id`, `grand_id`),
  KEY `idx_fonds_grand` (`grand_id`),
  KEY `idx_fonds_choisi_par` (`choisi_par`),
  CONSTRAINT `fk_fonds_petit`      FOREIGN KEY (`petit_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fonds_grand`      FOREIGN KEY (`grand_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fonds_choisi_par` FOREIGN KEY (`choisi_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
