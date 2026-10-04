-- Migration : les résumés que l'IA (Gemini, avec la clé de l'utilisateur) écrit à partir de ses documents.
--
-- `sources` garde, en JSON, ce qui a été lu (cours, fiches, documents), pour pouvoir le redire ;
-- `contenu` est le texte écrit (Markdown simple) ; `audio_nom` est le fichier son rangé dans
-- storage/resumes/ quand une version audio a été demandée.
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-resumes-ia.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `resumes_ia` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(190) NOT NULL,
  `genre`      VARCHAR(20)  NOT NULL DEFAULT 'resume',
  `longueur`   VARCHAR(10)  NOT NULL DEFAULT 'moyen',
  `langue`     CHAR(2)      NOT NULL DEFAULT 'fr',
  `sources`    TEXT         NULL,
  `contenu`    MEDIUMTEXT   NOT NULL,
  `modele`     VARCHAR(60)  NULL,
  `audio_nom`  VARCHAR(80)  NULL,
  `audio_voix` VARCHAR(30)  NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resumes_user_date` (`user_id`, `created_at`),
  CONSTRAINT `fk_resumes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
