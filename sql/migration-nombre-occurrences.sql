-- Terminer une répétition après un nombre de fois, plutôt qu'à une date.
--
-- « Douze séances » se sait d'avance ; la date à laquelle elles se terminent,
-- non — il faudrait la calculer soi-même, en sautant les mois sans 31 et en
-- comptant les jours cochés. C'est le travail de l'application.
--
-- « jusqu_au » reste renseignée dans les deux cas : elle porte alors la date de
-- la dernière occurrence, ce qui garde la colonne lisible et les requêtes
-- inchangées. « nombre_voulu » dit seulement laquelle des deux bornes a été
-- demandée, pour représenter le choix quand on rouvre la série.
ALTER TABLE `series_evenements`
  ADD COLUMN `nombre_voulu` SMALLINT UNSIGNED NULL AFTER `jusqu_au`;
