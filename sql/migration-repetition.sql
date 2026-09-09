-- Les évènements qui se répètent.
--
-- Les occurrences sont écrites une par une, reliées par leur série, plutôt que
-- gardées sous forme de règle à déplier à l'affichage. Une règle aurait obligé
-- chaque endroit qui lit des évènements — le calendrier, le tableau de bord,
-- l'envoi vers Outlook, « Prochainement » — à savoir la déplier, et à ne jamais
-- l'oublier. Une occurrence écrite est un évènement comme un autre : elle se
-- colore, se termine, part dans l'agenda et s'en efface sans code particulier.
--
-- Le prix à payer est une borne : une série a une fin, et un plafond.
CREATE TABLE IF NOT EXISTS `series_evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `frequence`   ENUM('jour','semaine','quinzaine','mois') NOT NULL,
  `jusqu_au`    DATE         NOT NULL,
  `occurrences` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_serie_user` (`user_id`),
  CONSTRAINT `fk_serie_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La série n'est qu'un rattachement : supprimer la série ne doit pas emporter
-- les évènements sans qu'on l'ait demandé, d'où « SET NULL ».
ALTER TABLE `evenements`
  ADD COLUMN `serie_id` INT UNSIGNED NULL AFTER `cours_id`,
  ADD KEY `idx_evt_serie` (`serie_id`),
  ADD CONSTRAINT `fk_evt_serie` FOREIGN KEY (`serie_id`)
    REFERENCES `series_evenements`(`id`) ON DELETE SET NULL;
