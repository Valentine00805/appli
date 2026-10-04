-- Migration : les cartes mentales d'un cours (écrites à la main, ou proposées par l'IA puis corrigées).
--
-- `arbre` garde la carte en JSON : {"t": "idée centrale", "c": [ {"t": "...", "c": [...], "p": 1}, ... ]}
-- (« t » le texte, « c » les sous-idées, « p » 1 si la branche est repliée).
-- Une carte appartient à un cours : elle disparaît avec lui.
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-cartes-mentales.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `cartes_mentales` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(190) NOT NULL,
  `arbre`      MEDIUMTEXT   NOT NULL,
  `ia`         TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cm_cours` (`cours_id`, `updated_at`),
  CONSTRAINT `fk_cm_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_cm_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
