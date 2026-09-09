-- Se réapproprier un évènement venu de l'agenda de quelqu'un d'autre.
--
-- Un rendez-vous lu dans l'agenda familial appartient à l'agenda familial : le
-- modifier ici ne le change pas là-bas, et la lecture suivante rétablit sa
-- version. Le cocher pour l'un de ses propres agendas ne faisait donc rien —
-- et ne le disait pas.
--
-- Il en fait maintenant une copie, qui est à soi : elle vit dans
-- l'application, part dans les agendas qu'on lui désigne, et se corrige comme
-- n'importe quel évènement écrit ici. L'original, lui, ne bouge pas d'un
-- iota — ni son contenu, ni sa couleur, ni son appartenance.
--
-- Cette colonne retient d'où vient la copie. Elle sert à ne pas la faire deux
-- fois, et à le dire quand on rouvre l'original. Si celui-ci disparaît — la
-- famille l'a supprimé —, la copie reste : elle ne lui appartenait plus.
ALTER TABLE evenements
    ADD COLUMN copie_de INT UNSIGNED DEFAULT NULL AFTER serie_id,
    ADD KEY idx_copie_de (copie_de),
    ADD CONSTRAINT fk_copie_de FOREIGN KEY (copie_de)
        REFERENCES evenements (id) ON DELETE SET NULL;
