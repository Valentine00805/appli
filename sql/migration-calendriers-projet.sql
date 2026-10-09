-- Le calendrier commun d'un travail de groupe : un calendrier partagé dont les membres sont ceux du projet.
--
-- À passer APRÈS migration-calendriers-amis.sql. Un projet a au plus un calendrier ; effacer le projet efface son calendrier et
-- ses évènements. Ses membres suivent ceux du projet (CalendriersAmis::synchroniserProjet), jamais ceux d'une liste d'amis.

ALTER TABLE `calendriers_amis`
  ADD COLUMN `projet_id` INT UNSIGNED NULL AFTER `proprietaire_id`,
  ADD UNIQUE KEY `uniq_calendriers_amis_projet` (`projet_id`),
  ADD CONSTRAINT `fk_calendriers_amis_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE;
