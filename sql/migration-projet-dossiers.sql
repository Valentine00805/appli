-- Des dossiers dans l'onglet « Fichiers » d'un travail de groupe, pour ranger les documents (sources, brouillons, diapositives…). Un dossier peut en contenir
-- d'autres (jusqu'à six niveaux). `dossier_id` dit où se range un fichier ; vide : à la racine. Supprimer un dossier fait remonter son contenu d'un cran :
-- rien n'est perdu.
--
-- Tant que cette migration n'est pas importée, le site tourne : les fichiers restent en vrac et « + Nouveau dossier » n'apparaît pas.

CREATE TABLE IF NOT EXISTS `projet_dossiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `parent_id`  INT UNSIGNED NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `cree_par`   INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projet_dossiers_projet` (`projet_id`, `parent_id`),
  CONSTRAINT `fk_projet_dossiers_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_dossiers_parent` FOREIGN KEY (`parent_id`) REFERENCES `projet_dossiers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_dossiers_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projet_fichiers`
  ADD COLUMN `dossier_id` INT UNSIGNED NULL AFTER `livrable_id`,
  ADD KEY `idx_projet_fichiers_dossier` (`dossier_id`),
  ADD CONSTRAINT `fk_projet_fichiers_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `projet_dossiers`(`id`) ON DELETE SET NULL;
