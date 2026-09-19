-- Partager tout son calendrier « Mes évènements » avec un ami.
--
-- Une ligne ici : l'ami voit, en lecture, tous les évènements écrits dans
-- l'application et gardés pour soi — ceux d'aujourd'hui comme ceux qu'on
-- ajoutera. Ni les agendas Outlook et Google, ni les évènements qu'un autre
-- ami nous a partagés : ils ne sont pas à nous.

CREATE TABLE IF NOT EXISTS `calendriers_partages` (
  `proprietaire_id` INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`proprietaire_id`, `destinataire_id`),
  KEY `idx_calendriers_partages_destinataire` (`destinataire_id`),
  CONSTRAINT `fk_calendriers_partages_proprietaire` FOREIGN KEY (`proprietaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendriers_partages_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
