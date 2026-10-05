-- Les sondages des discussions (entre amis et de groupe).
--
-- Un sondage est un message de la discussion : la ligne de `messages` (amis) ou de `conversation_messages` (groupe) porte
-- `sondage_id`, et son `texte` est la question — la recherche, les aperçus et les notifications la lisent comme n'importe
-- quel texte. Les options et les votes sont à part.
--
-- `canal` dit où il vit. Pour un groupe, `conversation_id` ; pour deux amis, `createur_id` et `destinataire_id`. Ainsi un
-- sondage disparaît avec le groupe ou avec l'un des deux comptes.
--
-- À exécuter une seule fois (MySQL n'a pas « ADD COLUMN IF NOT EXISTS »).
--   mysql -u root < sql/migration-sondages.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `sondages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `canal`           ENUM('amis', 'groupes') NOT NULL,
  `conversation_id` INT UNSIGNED NULL,
  `createur_id`     INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NULL,
  `question`        VARCHAR(200) NOT NULL,
  `multiple`        TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sondages_conversation` (`conversation_id`),
  KEY `idx_sondages_createur` (`createur_id`),
  KEY `idx_sondages_destinataire` (`destinataire_id`),
  CONSTRAINT `fk_sondages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sondages_createur`     FOREIGN KEY (`createur_id`)     REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_sondages_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sondage_options` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sondage_id` INT UNSIGNED NOT NULL,
  `texte`      VARCHAR(100) NOT NULL,
  `position`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sondage_options_sondage` (`sondage_id`, `position`),
  CONSTRAINT `fk_sondage_options_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un vote : une personne, une option. Dans un sondage à réponse unique, il n'y en a qu'un par personne ; dans un sondage à
-- plusieurs réponses, autant qu'elle en coche.
CREATE TABLE IF NOT EXISTS `sondage_votes` (
  `option_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `sondage_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`option_id`, `user_id`),
  KEY `idx_sondage_votes_sondage` (`sondage_id`, `user_id`),
  KEY `idx_sondage_votes_user` (`user_id`),
  CONSTRAINT `fk_sondage_votes_option`  FOREIGN KEY (`option_id`)  REFERENCES `sondage_options`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sondage_votes_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)           ON DELETE CASCADE,
  CONSTRAINT `fk_sondage_votes_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `messages`
  ADD COLUMN `sondage_id` INT UNSIGNED NULL AFTER `partage_id`,
  ADD KEY `idx_messages_sondage` (`sondage_id`),
  ADD CONSTRAINT `fk_messages_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`) ON DELETE SET NULL;

ALTER TABLE `conversation_messages`
  ADD COLUMN `sondage_id` INT UNSIGNED NULL AFTER `partage_id`,
  ADD KEY `idx_conversation_messages_sondage` (`sondage_id`),
  ADD CONSTRAINT `fk_conversation_messages_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages`(`id`) ON DELETE SET NULL;
