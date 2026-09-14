-- Les rappels : une notification avant un évènement, et le matin d'une échéance.
--
-- Un évènement dit combien de minutes avant lui on veut être prévenu ; NULL,
-- pas de rappel. Quinze minutes par défaut, y compris pour les évènements venus
-- d'Outlook ou de Google, qui arrivent sans rien dire.
ALTER TABLE evenements
    ADD COLUMN rappel_minutes SMALLINT UNSIGNED NULL DEFAULT 15 AFTER etape;

-- Les appareils abonnés : l'adresse que leur service de notifications nous a
-- donnée, et les deux clés pour leur écrire. L'empreinte de l'adresse sert de
-- clé unique — une adresse peut dépasser ce qu'un index accepte.
CREATE TABLE IF NOT EXISTS `abonnements_push` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `point_final` VARCHAR(1024) NOT NULL,
  `empreinte`   CHAR(64)      NOT NULL,
  `cle_p256dh`  VARCHAR(120)  NOT NULL,
  `cle_auth`    VARCHAR(40)   NOT NULL,
  `appareil`    VARCHAR(190)  DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dernier_envoi` DATETIME    DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_abonnement` (`empreinte`),
  KEY `idx_abonnements_user` (`user_id`),
  CONSTRAINT `fk_abonnements_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les rappels déjà partis : un par objet et par moment. Un évènement déplacé
-- change de moment, et son rappel repartira ; deux envois simultanés, eux,
-- butent sur la clé unique.
CREATE TABLE IF NOT EXISTS `rappels_envoyes` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   INT UNSIGNED NOT NULL,
  `nature`    ENUM('evenement', 'tache', 'liste') NOT NULL,
  `objet_id`  INT UNSIGNED NOT NULL,
  `moment`    DATETIME     NOT NULL,
  `envoye_le` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rappel` (`user_id`, `nature`, `objet_id`, `moment`),
  CONSTRAINT `fk_rappels_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les réglages de l'installation elle-même : les clés VAPID, la clé de l'adresse
-- d'envoi. En base plutôt qu'en fichier : l'antivirus du poste met des fichiers
-- du projet en quarantaine, et une clé perdue désabonnerait tous les appareils.
CREATE TABLE IF NOT EXISTS `reglages_application` (
  `cle`    VARCHAR(64) NOT NULL,
  `valeur` TEXT        NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
