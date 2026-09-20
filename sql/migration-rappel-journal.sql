-- Le rappel du vendredi : écrire sa semaine dans le journal de l'alternance.
-- Les rappels déjà partis se notent dans la même table que les autres.

ALTER TABLE `rappels_envoyes`
  MODIFY `nature` ENUM('evenement', 'tache', 'liste', 'journal') NOT NULL;
