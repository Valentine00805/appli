-- Les éléments rattachés à la fiche de révision d'un DOSSIER : fichiers et images, liens, renvois vers d'autres cours, évènements du calendrier.
--
-- Une pièce jointe ou un élément de fiche appartenait toujours à un cours (`cours_id`). Il peut maintenant appartenir à un dossier à la place :
-- `cours_id` devient facultatif et `dossier_id` le remplace. Rien n'est déplacé ni supprimé : les lignes existantes gardent leur cours.
-- Supprimer le dossier emporte ses éléments (et le code efface leurs fichiers du disque).
--
-- Tant que cette migration n'est pas importée, le site tourne : la fiche d'un dossier montre seulement son texte et ce que le dossier contient.
-- À importer APRÈS « migration-dossier-fiche.sql ».

ALTER TABLE `fichiers`
  MODIFY COLUMN `cours_id` INT UNSIGNED NULL,
  ADD COLUMN `dossier_id` INT UNSIGNED NULL AFTER `cours_id`,
  ADD KEY `idx_fichiers_dossier` (`dossier_id`, `pour_fiche`),
  ADD CONSTRAINT `fk_fichiers_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE;

ALTER TABLE `fiche_elements`
  MODIFY COLUMN `cours_id` INT UNSIGNED NULL,
  ADD COLUMN `dossier_id` INT UNSIGNED NULL AFTER `cours_id`,
  ADD KEY `idx_fiche_elements_dossier` (`dossier_id`, `type`, `position`),
  ADD CONSTRAINT `fk_fiche_elements_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE;
