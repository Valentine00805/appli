-- Choisir l'agenda qui reçoit les évènements de l'application.
--
-- Jusqu'ici l'application créait « Mes Cours » chez le fournisseur et n'écrivait
-- que là. C'était prudent — son propre calendrier, qu'une erreur de notre part
-- ne pouvait pas atteindre — mais cela mettait ce qu'on crée ici hors de portée
-- de qui regarde un agenda familial partagé.
--
-- La destination devient donc un choix. « calendrier_envoi_id » existait déjà et
-- servait exactement à cela ; il ne manquait que de savoir si elle a été
-- désignée par la personne, ou seulement trouvée par l'application.
--
-- C'est ce que dit cette colonne, et elle sert à une chose précise : un
-- calendrier « Mes Cours » est le reflet de l'application et se retire de la
-- liste qu'on affiche, tandis qu'un calendrier que la personne a choisi reste
-- le sien — on doit continuer à le cocher, le colorier, le masquer.
ALTER TABLE agenda_comptes
    ADD COLUMN envoi_choisi TINYINT(1) NOT NULL DEFAULT 0 AFTER calendrier_envoi_nom;
