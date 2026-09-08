-- Envoyer vers Outlook ce qui est né dans l'application.
--
-- L'application écrit dans un calendrier à elle, « Mes Cours », qu'elle crée
-- chez Microsoft. Rien ne se mêle à l'agenda existant : on l'affiche ou on le
-- masque d'une case dans Outlook, et le supprimer n'emporte que ce que
-- l'application y a mis. Une erreur d'écriture ne peut pas abîmer l'agenda
-- dont on se sert vraiment.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `calendrier_envoi_id`  VARCHAR(512) NULL AFTER `calendrier_nom`,
  ADD COLUMN `calendrier_envoi_nom` VARCHAR(190) NULL AFTER `calendrier_envoi_id`,
  ADD COLUMN `envoi_le`             DATETIME     NULL AFTER `synchro_le`;

-- Ce qui est parti là-bas, et sous quelle forme.
--
-- Sans cette trace, un envoi ne saurait pas distinguer « à créer » de « déjà
-- créé, peut-être modifié », et referait le même évènement à chaque passage.
-- L'empreinte évite d'aller réécrire ce qui n'a pas bougé.
--
-- « sorte » sépare les évènements des échéances de tâches : elles vivent dans
-- des tables différentes et leurs identifiants se recoupent.
CREATE TABLE IF NOT EXISTS `outlook_envois` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `sorte`      ENUM('evenement','tache') NOT NULL,
  `source_id`  INT UNSIGNED NOT NULL,
  `outlook_id` VARCHAR(512) NOT NULL,
  `empreinte`  CHAR(32)     NULL,
  `maj_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_envoi` (`user_id`, `sorte`, `source_id`),
  CONSTRAINT `fk_envoi_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
