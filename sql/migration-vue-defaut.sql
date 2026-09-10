-- La vue du calendrier qu'on retrouve en arrivant.
--
-- Le mois s'imposait à tous. Il convient à qui prend du recul sur son
-- trimestre, moins à qui vit sa semaine heure par heure : celui-là commençait
-- chaque visite par un clic pour arriver là où il voulait être.
--
-- Vide, c'est le mois — le comportement d'avant, pour qui ne s'en préoccupe
-- pas. Le paramètre d'adresse « ?vue= » garde toujours le dernier mot : on
-- change de vue en un clic sans que cela devienne un choix définitif.
ALTER TABLE users
    ADD COLUMN vue_calendrier VARCHAR(10) DEFAULT NULL;
