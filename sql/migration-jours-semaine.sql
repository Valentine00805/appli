-- Les jours de la semaine d'une répétition.
--
-- Un cours a rarement lieu une fois par semaine : il tombe le lundi et le
-- jeudi, ou les lundi, mercredi et vendredi. Sans ce choix, il fallait créer
-- autant de séries que de jours, et les modifier une par une ensuite.
--
-- « 1,4 » se lit lundi et jeudi, à la façon de la norme ISO. Vide, la série
-- garde le jour de sa date de départ, ce qui était le comportement d'avant.
ALTER TABLE `series_evenements`
  ADD COLUMN `jours` VARCHAR(20) NULL AFTER `frequence`;
