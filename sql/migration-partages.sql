-- Partager un cours ou un fichier.
--
-- Avec ses amis : un accès en lecture, qui paraît dans « Partagés avec moi »,
-- et une carte envoyée dans la discussion (messages.partage_*).
-- Avec n'importe qui : un lien public, qu'on peut désactiver.
-- « cible_type » vaut « cours » ou « fichier ».

CREATE TABLE IF NOT EXISTS `partages_amis` (
  `destinataire_id` INT UNSIGNED NOT NULL,
  `cible_type`      VARCHAR(8)   NOT NULL,
  `cible_id`        INT UNSIGNED NOT NULL,
  `proprietaire_id` INT UNSIGNED NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`destinataire_id`, `cible_type`, `cible_id`),
  KEY `idx_partages_amis_cible` (`cible_type`, `cible_id`),
  KEY `idx_partages_amis_proprietaire` (`proprietaire_id`),
  CONSTRAINT `fk_partages_amis_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_partages_amis_proprietaire` FOREIGN KEY (`proprietaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liens_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cible_type` VARCHAR(8)   NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `jeton`      CHAR(32)     NOT NULL,
  `created_at` DATETIME     NOT NULL,
  `vues`       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lien_jeton` (`jeton`),
  UNIQUE KEY `uniq_lien_cible` (`cible_type`, `cible_id`),
  KEY `idx_lien_user` (`user_id`),
  CONSTRAINT `fk_lien_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE messages
    ADD COLUMN partage_type VARCHAR(8)   NULL AFTER audio_transcription,
    ADD COLUMN partage_id   INT UNSIGNED NULL AFTER partage_type;

ALTER TABLE conversation_messages
    ADD COLUMN partage_type VARCHAR(8)   NULL AFTER audio_transcription,
    ADD COLUMN partage_id   INT UNSIGNED NULL AFTER partage_type;
