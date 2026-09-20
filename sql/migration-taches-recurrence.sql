-- Une tâche qui revient : écrire son journal chaque vendredi, envoyer le
-- compte-rendu chaque lundi. Cochée, elle renaît à l'échéance suivante ;
-- rien ne tourne en arrière-plan, c'est le geste de cocher qui la relance.

ALTER TABLE `taches`
  ADD COLUMN `recurrence` ENUM('jour', 'semaine', 'quinzaine', 'mois') NULL AFTER `echeance`;
