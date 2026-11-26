-- Choisir quels fichiers, cours et sous-dossiers sont reliés à la fiche de révision d'un DOSSIER.
--
-- Tant qu'aucun choix n'a été fait (`fiche_choix` = 0), la fiche relie tout le dossier, sous-dossiers compris. Dès qu'on choisit (`fiche_choix` = 1),
-- seuls les éléments de `dossier_fiche_liens` sont reliés : un sous-dossier relié l'est avec tout ce qu'il contient.
-- Rien n'est supprimé ailleurs : relier n'est qu'une liste de plus, les cours et les dossiers ne bougent pas.
--
-- Tant que cette migration n'est pas importée, le site tourne : la fiche relie tout le dossier, sans le choix.
-- À importer APRÈS « migration-dossier-fiche.sql » (et de préférence « migration-dossier-fiche-elements.sql »).

ALTER TABLE `dossiers`
  ADD COLUMN `fiche_choix` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fiche_modifiee_le`;

CREATE TABLE IF NOT EXISTS `dossier_fiche_liens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `dossier_id`      INT UNSIGNED NOT NULL,
  -- Un cours (ou un fichier gardé comme tel) OU un sous-dossier, jamais les deux.
  `cours_id`        INT UNSIGNED NULL,
  `sous_dossier_id` INT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dfl_cours` (`dossier_id`, `cours_id`),
  UNIQUE KEY `uniq_dfl_sous` (`dossier_id`, `sous_dossier_id`),
  CONSTRAINT `fk_dfl_user`    FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_dfl_dossier` FOREIGN KEY (`dossier_id`)      REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dfl_cours`   FOREIGN KEY (`cours_id`)        REFERENCES `cours`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_dfl_sous`    FOREIGN KEY (`sous_dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
