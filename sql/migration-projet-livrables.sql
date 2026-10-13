-- Les livrables d'un travail de groupe : les documents finaux à rendre (le rapport, le diaporama, les annexes…). Chaque livrable a un titre, une date de
-- rendu, une consigne, ses documents — des fichiers du projet marqués par `livrable_id` : ils n'apparaissent plus dans l'onglet « Fichiers » ni sur le lien
-- public — et l'état « rendu » (qui l'a rendu, quand).
-- Supprimer un livrable (l'administrateur du groupe) supprime ses documents.
--
-- Tant que cette migration n'est pas importée, le site tourne : l'onglet « Livrables » n'apparaît simplement pas.

CREATE TABLE IF NOT EXISTS `projet_livrables` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(120) NOT NULL,
  `note`       VARCHAR(500) NULL,
  `echeance`   DATE NULL,
  `rendu_le`   DATETIME NULL,
  `rendu_par`  INT UNSIGNED NULL,
  `cree_par`   INT UNSIGNED NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projet_livrables_projet` (`projet_id`, `echeance`),
  CONSTRAINT `fk_projet_livrables_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_livrables_rendu_par` FOREIGN KEY (`rendu_par`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projet_livrables_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projet_fichiers`
  ADD COLUMN `livrable_id` INT UNSIGNED NULL AFTER `projet_id`,
  ADD KEY `idx_projet_fichiers_livrable` (`livrable_id`),
  ADD CONSTRAINT `fk_projet_fichiers_livrable` FOREIGN KEY (`livrable_id`) REFERENCES `projet_livrables`(`id`) ON DELETE SET NULL;
