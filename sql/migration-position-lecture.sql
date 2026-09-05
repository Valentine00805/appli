-- Où l'on s'est arrêté dans un enregistrement, et sa durée totale.
-- Les deux en secondes : de quoi reprendre la lecture et en calculer l'avancement.
-- À exécuter une seule fois : mysql -u root < sql/migration-position-lecture.sql

USE `mon_appli_cours`;

ALTER TABLE `fichiers`
  ADD COLUMN `position_lecture` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `taille`,
  ADD COLUMN `duree_lecture`    INT UNSIGNED NOT NULL DEFAULT 0 AFTER `position_lecture`;
