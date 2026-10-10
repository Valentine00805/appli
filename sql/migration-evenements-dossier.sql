-- Un évènement peut renvoyer à un dossier de cours, comme il renvoie à un cours : « Révisions du semestre » → le dossier « Semestre 1 ».
-- Un évènement lie un cours OU un dossier, jamais les deux (le formulaire n'en propose qu'un). Supprimer le dossier retire le lien.
ALTER TABLE `evenements`
  ADD COLUMN `dossier_id` INT UNSIGNED NULL AFTER `cours_id`,
  ADD KEY `idx_evt_dossier` (`dossier_id`),
  ADD CONSTRAINT `fk_evt_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE SET NULL;