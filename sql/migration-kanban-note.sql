-- Une remarque libre sur une sous-tâche, visible depuis le tableau.
-- Les évènements ont déjà « description » : on la réutilise plutôt que
-- d'ajouter un second champ qui dirait la même chose.
-- À exécuter une seule fois : mysql -u root < sql/migration-kanban-note.sql

USE `mon_appli_cours`;

ALTER TABLE `taches` ADD COLUMN `note` VARCHAR(500) DEFAULT NULL AFTER `etape`;
