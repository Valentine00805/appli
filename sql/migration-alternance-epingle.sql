-- Une note d'alternance qu'on garde en haut de la liste : les consignes qu'on
-- relit tout le temps ne doivent pas descendre à mesure qu'on en écrit d'autres.

ALTER TABLE `alternance_notes`
  ADD COLUMN `epinglee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `contenu`;
