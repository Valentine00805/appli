-- Une échéance de groupe peut porter un type à soi (« Projet », « Oral blanc »…),
-- en plus des types proposés : son nom, quand la nature est « autre ».

ALTER TABLE `projet_echeances`
  ADD COLUMN `type_nom` VARCHAR(40) NULL AFTER `nature`;
