-- Des groupes de calendriers dans le volet du calendrier : on réunit plusieurs calendriers partagés sous un même titre, qui se plie et se déplie.
-- Le rangement est propre à chacun (un groupe appartient à celui qui l'a fait ; les autres membres du calendrier ne le voient pas).
-- `groupe_id` dit dans quel groupe se range un calendrier pour moi ; vide : hors groupe. Supprimer un groupe ne supprime aucun calendrier.
--
-- Tant que cette migration n'est pas importée, le site tourne : le volet reste comme avant et « + Nouveau groupe » n'apparaît pas.

CREATE TABLE IF NOT EXISTS `calendrier_groupes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(60) NOT NULL,
  -- Le groupe est-il plié dans mon volet ?
  `replie`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_calendrier_groupes_user` (`user_id`),
  CONSTRAINT `fk_calendrier_groupes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `calendrier_amis_membres`
  ADD COLUMN `groupe_id` INT UNSIGNED NULL AFTER `couleur`,
  ADD KEY `idx_calendrier_amis_membres_groupe` (`groupe_id`),
  ADD CONSTRAINT `fk_cam_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `calendrier_groupes`(`id`) ON DELETE SET NULL;
