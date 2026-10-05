-- Migration : les diaporamas commentés (écrits par l'IA avec la clé Gemini de l'utilisateur, lus à voix haute).
--
-- `diapos` garde les diapositives en JSON : [ {"t": titre, "p": [points], "c": commentaire, "a": nom du fichier voix} ].
-- Le fichier voix (« a ») est un WAV rangé dans storage/resumes/, fabriqué diapositive par diapositive à la demande ;
-- sans lui, la lecture commentée utilise la voix du navigateur.
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-diaporamas.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `diaporamas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(190) NOT NULL,
  `langue`     CHAR(2)      NOT NULL DEFAULT 'fr',
  `sources`    TEXT         NULL,
  `diapos`     MEDIUMTEXT   NOT NULL,
  `modele`     VARCHAR(60)  NULL,
  `voix`       VARCHAR(30)  NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_diaporamas_user_date` (`user_id`, `created_at`),
  CONSTRAINT `fk_diaporamas_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
