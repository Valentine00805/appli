-- Les sessions de révision : ce qu'on a révisé, combien de temps, et comment
-- on l'a vécu. Une session est ouverte au démarrage et refermée à la fin ;
-- celle qu'on abandonne sans refermer garde sa durée à zéro et ne compte pas.

CREATE TABLE IF NOT EXISTS `sessions_revision` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NULL,
  `sujet`      VARCHAR(150) NULL,
  `minutes_voulues` SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  `secondes`   INT UNSIGNED NOT NULL DEFAULT 0,
  `pauses`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ressenti`   ENUM('bien', 'moyen', 'dur') NULL,
  `debut`      DATETIME     NOT NULL,
  `fin`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_revision_user` (`user_id`, `debut`),
  CONSTRAINT `fk_sessions_revision_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessions_revision_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
