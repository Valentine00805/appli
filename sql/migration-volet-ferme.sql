-- Le volet des agendas, ouvert ou fermé.
--
-- Il occupe quatorze rem sur la gauche du calendrier, en permanence. On s'en
-- sert pour décider ce qu'on regarde — un geste qu'on fait rarement — et le
-- reste du temps il prend la place de la grille.
--
-- Zéro, il est ouvert : c'est l'état d'avant, et celui de qui ne s'en occupe
-- pas. Ses sections repliées sont retenues à part, dans « volet_replie » : on
-- retrouve le volet tel qu'on l'avait laissé, y compris dedans.
ALTER TABLE users
    ADD COLUMN volet_ferme TINYINT(1) NOT NULL DEFAULT 0;
