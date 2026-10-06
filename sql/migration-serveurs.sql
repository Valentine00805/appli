-- Les serveurs, comme sur Discord : un espace nommé, des membres, et des salons où l'on discute.
--
-- Un salon est une discussion de groupe (table `conversations`) rattachée à son serveur par `serveur_id` : il a donc tout ce que
-- les discussions savent faire (images, fichiers, vocaux, réactions, sondages, épingles, recherche). Tous les membres du serveur
-- sont membres de tous ses salons ; entrer ou sortir du serveur les y met ou les en retire (voir Serveurs).
--
-- `serveur_membres.role` : « proprietaire » (un seul, qui peut tout), « admin » (salons, invitations, retraits de membres),
--   « membre ».
-- `serveur_invitations` : personne n'entre dans un serveur sans l'avoir voulu — on invite un ami, qui accepte ou refuse.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-serveurs.sql

USE `mon_appli_cours`;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `serveurs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(60)  NOT NULL,
  `icone`      VARCHAR(16)  NOT NULL DEFAULT '🏰',
  `cree_par`   INT UNSIGNED NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_serveurs_cree_par` (`cree_par`),
  CONSTRAINT `fk_serveurs_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `serveur_membres` (
  `serveur_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `role`       ENUM('proprietaire', 'admin', 'membre') NOT NULL DEFAULT 'membre',
  `rejoint_le` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`serveur_id`, `user_id`),
  KEY `idx_serveur_membres_user` (`user_id`),
  CONSTRAINT `fk_serveur_membres_serveur` FOREIGN KEY (`serveur_id`) REFERENCES `serveurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_serveur_membres_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `serveur_invitations` (
  `serveur_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `invite_par` INT UNSIGNED NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`serveur_id`, `user_id`),
  KEY `idx_serveur_invitations_user` (`user_id`),
  CONSTRAINT `fk_serveur_inv_serveur` FOREIGN KEY (`serveur_id`) REFERENCES `serveurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_serveur_inv_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_serveur_inv_par`     FOREIGN KEY (`invite_par`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un salon : sa discussion, son serveur, et sa place dans la liste. Effacer le serveur efface ses salons (Serveurs::supprimer
-- range d'abord leurs fichiers sur le disque).
ALTER TABLE `conversations`
  ADD COLUMN `serveur_id` INT UNSIGNED NULL AFTER `cree_par`,
  ADD COLUMN `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `serveur_id`,
  ADD KEY `idx_conversations_serveur` (`serveur_id`, `position`),
  ADD CONSTRAINT `fk_conversations_serveur` FOREIGN KEY (`serveur_id`) REFERENCES `serveurs`(`id`) ON DELETE CASCADE;
