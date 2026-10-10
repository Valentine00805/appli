-- Une matière affiliée à un dossier : les cours qu'on y range la reçoivent s'ils n'en ont pas (voir DossiersController::matiereDu).
-- Supprimer la matière la retire du dossier, sans toucher aux cours (ils gardent la leur, ou n'en ont plus : c'est déjà le cas).
ALTER TABLE `dossiers`
  ADD COLUMN `matiere_id` INT UNSIGNED NULL AFTER `parent_id`,
  ADD KEY `idx_dossiers_matiere` (`matiere_id`),
  ADD CONSTRAINT `fk_dossiers_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL;