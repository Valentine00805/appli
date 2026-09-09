-- Une couleur par agenda.
--
-- Un évènement venu d'Outlook n'a ni matière ni type : il tombait donc sur le
-- gris de défaut, tous agendas confondus. Six agendas gris côte à côte ne se
-- distinguent plus, et c'est justement pour les distinguer qu'on les suit.
--
-- La couleur ne sert qu'à cela : elle vient après la matière et le type, qui
-- gardent la main quand ils existent.
ALTER TABLE `outlook_calendriers`
  ADD COLUMN `couleur` VARCHAR(7) NULL AFTER `affiche`;

-- Et celle de ses propres évènements, pour ceux qui n'ont ni matière ni type.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `couleur_miens` VARCHAR(7) NULL AFTER `afficher_miens`;

-- Les agendas déjà connus n'ont pas de couleur : on leur en attribue une,
-- prise dans la palette des matières, en tournant. Sans cela le volet en
-- promettrait une que les évènements n'auraient pas — et tout resterait gris.
UPDATE `outlook_calendriers` c
  JOIN (
    SELECT id,
           ELT(1 + (ROW_NUMBER() OVER (
                      PARTITION BY user_id ORDER BY principal DESC, partage, nom, id) % 10),
               '#4f46e5', '#0ea5e9', '#059669', '#65a30d', '#ca8a04',
               '#ea580c', '#dc2626', '#db2777', '#7c3aed', '#475569') AS teinte
      FROM `outlook_calendriers`
  ) t ON t.id = c.id
   SET c.couleur = t.teinte
 WHERE c.couleur IS NULL;
