-- Relier un calendrier Outlook à l'application.
--
-- Une ligne par compte : de quoi parler à Microsoft (l'identifiant de
-- l'application inscrite, les jetons) et de quoi savoir où l'on en est de la
-- synchronisation (le calendrier suivi, le repère de la dernière lecture).
--
-- Ces jetons ouvrent l'agenda du compte relié : la table est délibérément
-- laissée hors des sauvegardes exportables, pour qu'une archive partagée ne
-- les emporte pas.
CREATE TABLE IF NOT EXISTS `outlook_comptes` (
  `user_id`        INT UNSIGNED NOT NULL,
  -- L'application inscrite chez Microsoft, que l'utilisateur déclare lui-même.
  `client_id`      VARCHAR(64)  NOT NULL,
  -- « common » accepte les comptes personnels et les comptes d'établissement.
  `locataire`      VARCHAR(64)  NOT NULL DEFAULT 'common',
  `compte`         VARCHAR(190) NULL,
  `jeton`          TEXT         NULL,
  `renouvellement` TEXT         NULL,
  `expire_le`      DATETIME     NULL,
  `calendrier_id`  VARCHAR(255) NULL,
  `calendrier_nom` VARCHAR(190) NULL,
  -- Le repère que Microsoft rend pour ne relire que ce qui a changé.
  `delta`          TEXT         NULL,
  `synchro_le`     DATETIME     NULL,
  `cree_le`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_outlook_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qui relie un évènement d'ici à son jumeau là-bas.
--
-- Sans ce lien, une synchronisation ne saurait pas distinguer « un évènement
-- nouveau » de « un évènement déjà connu qui a changé », et les doublerait à
-- chaque passage.
CREATE TABLE IF NOT EXISTS `outlook_liens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `evenement_id` INT UNSIGNED NULL,
  `outlook_id`   VARCHAR(255) NOT NULL,
  -- La version connue de part et d'autre, pour repérer ce qui a bougé.
  `etag`         VARCHAR(255) NULL,
  `empreinte`    CHAR(32)     NULL,
  `maj_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outlook_id` (`user_id`, `outlook_id`),
  KEY `idx_outlook_evenement` (`evenement_id`),
  CONSTRAINT `fk_lien_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lien_evenement` FOREIGN KEY (`evenement_id`)
    REFERENCES `evenements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
