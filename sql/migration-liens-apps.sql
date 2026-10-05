-- Migration : les liens vers d'autres applications (YouTube, NotebookLM…) que chacun range dans le menu en grille de la barre.
--
-- `icone` : un emoji (ou quelques lettres) choisi par l'utilisateur ; vide, l'application en propose un d'après le site.
-- Seules des adresses http(s) sont gardées (voir src/LienApp.php).
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-liens-apps.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `liens_apps` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(40)  NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `icone`      VARCHAR(16)  NOT NULL DEFAULT '',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_liens_apps_user` (`user_id`, `position`, `id`),
  CONSTRAINT `fk_liens_apps_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
