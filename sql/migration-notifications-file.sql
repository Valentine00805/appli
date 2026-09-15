-- La file des notifications : messages, demandes d'ami, acceptations.
--
-- Comme les rappels d'évènements, une notification est d'abord écrite ici,
-- puis envoyée — tout de suite, et sinon par l'adresse d'envoi que la tâche
-- planifiée appelle chaque minute (ou par la page ouverte). Un envoi raté
-- (connexion coupée, service indisponible) est ainsi retenté, pendant une
-- heure, au lieu d'être perdu. « envoi_en_cours » empêche deux passages
-- simultanés d'envoyer la même notification deux fois.
CREATE TABLE IF NOT EXISTS `notifications_file` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED  NOT NULL,
  `nature`          VARCHAR(20)   NOT NULL,
  `titre`           VARCHAR(190)  NOT NULL,
  `corps`           VARCHAR(500)  NOT NULL,
  `adresse`         VARCHAR(255)  NOT NULL,
  `etiquette`       VARCHAR(64)   NOT NULL,
  `cree_le`         DATETIME      NOT NULL,
  `envoi_en_cours`  DATETIME      NULL,
  `tentatives`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `envoye_le`       DATETIME      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_attente` (`user_id`, `envoye_le`, `cree_le`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
