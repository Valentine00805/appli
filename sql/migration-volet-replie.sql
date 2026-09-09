-- Les sections du volet qu'on a repliées.
--
-- Le volet range les agendas par fournisseur ; les replier n'est qu'un
-- affichage, mais il doit tenir. Le formulaire du volet se renvoie tout seul
-- dès qu'on coche un agenda ou qu'on change une couleur : sans mémoire, une
-- section repliée se rouvrirait au premier clic suivant.
--
-- Une liste de clefs séparées par des virgules — « miens », « microsoft »,
-- « google ». Vide ou absente, tout est déplié : c'est l'état d'avant, et
-- celui de qui n'a jamais rien replié.
ALTER TABLE users
    ADD COLUMN volet_replie VARCHAR(190) DEFAULT NULL;
