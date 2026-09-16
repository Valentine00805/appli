-- Les notes de la discussion.
--
-- Une ligne de messages sans texte, marquée d'un évènement (« fond » : le fond
-- d'écran a changé ; « fond_retire » : il a été retiré). Elle s'affiche au
-- centre de la conversation, pour les deux, et ne se lit pas comme un
-- message : ni réponse, ni réaction, ni épingle, ni « Vu ».
ALTER TABLE messages
    ADD COLUMN evenement VARCHAR(20) NULL AFTER texte;
