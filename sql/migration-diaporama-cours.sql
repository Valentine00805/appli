-- Migration : les diaporamas commentés rangés dans la fiche de révision d'un cours.
--
-- Un diaporama peut avoir été écrit à partir de plusieurs cours, et se ranger dans la fiche de chacun : une ligne par
-- couple (diaporama, cours). Retirer un diaporama d'une fiche n'efface pas le diaporama ; effacer le diaporama (ou le
-- cours) efface le rangement.
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-diaporama-cours.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `diaporama_cours` (
  `diaporama_id` INT UNSIGNED NOT NULL,
  `cours_id`     INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`diaporama_id`, `cours_id`),
  KEY `idx_diaporama_cours_cours` (`cours_id`, `created_at`),
  CONSTRAINT `fk_dc_diaporama` FOREIGN KEY (`diaporama_id`) REFERENCES `diaporamas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dc_cours`     FOREIGN KEY (`cours_id`)     REFERENCES `cours`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_dc_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
