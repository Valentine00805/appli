-- Les calendriers partagés : un calendrier à part, ouvert aux seuls amis que son créateur choisit.
--
-- À ne pas confondre avec « calendriers_partages », qui ouvre « Mes évènements » en lecture à un ami : ici le calendrier a son nom,
-- sa couleur, ses membres, et ses propres évènements, écrits par tous les membres.
--
--   calendriers_amis            le calendrier : son créateur (qui le gère), son nom, sa couleur
--   calendrier_amis_membres     qui y est — le créateur compris — et ce que chacun en voit (affiche, couleur à soi)
--   calendrier_amis_evenements  les évènements, avec leur auteur

CREATE TABLE IF NOT EXISTS `calendriers_amis` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proprietaire_id` INT UNSIGNED NOT NULL,
  `nom`            VARCHAR(80)  NOT NULL,
  `couleur`        CHAR(7)      NOT NULL DEFAULT '#6d5dfc',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_calendriers_amis_proprietaire` (`proprietaire_id`),
  CONSTRAINT `fk_calendriers_amis_proprietaire` FOREIGN KEY (`proprietaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendrier_amis_membres` (
  `calendrier_id` INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  -- Ce calendrier paraît-il dans mon calendrier ? (la case du volet)
  `affiche`       TINYINT(1)   NOT NULL DEFAULT 1,
  -- Ma couleur pour lui, si elle n'est pas celle du créateur.
  `couleur`       CHAR(7)      NULL,
  `rejoint_le`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`calendrier_id`, `user_id`),
  KEY `idx_calendrier_amis_membres_user` (`user_id`),
  CONSTRAINT `fk_cam_calendrier` FOREIGN KEY (`calendrier_id`) REFERENCES `calendriers_amis`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cam_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendrier_amis_evenements` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `calendrier_id`   INT UNSIGNED NOT NULL,
  -- Qui l'a écrit ; si son compte disparaît, l'évènement reste au calendrier.
  `auteur_id`       INT UNSIGNED NULL,
  `titre`           VARCHAR(200) NOT NULL,
  `description`     TEXT         NULL,
  `lieu`            VARCHAR(160) NULL,
  `debut`           DATETIME     NOT NULL,
  `fin`             DATETIME     NOT NULL,
  `journee_entiere` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cae_calendrier_debut` (`calendrier_id`, `debut`),
  CONSTRAINT `fk_cae_calendrier` FOREIGN KEY (`calendrier_id`) REFERENCES `calendriers_amis`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cae_auteur`     FOREIGN KEY (`auteur_id`)     REFERENCES `users`(`id`)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
