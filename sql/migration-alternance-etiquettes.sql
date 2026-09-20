-- Des étiquettes sur les notes d'alternance : « sécurité », « qualité »,
-- « outils »… On retrouve alors d'un clic tout ce qui parle du même sujet.

ALTER TABLE `alternance_notes`
  ADD COLUMN `etiquettes` VARCHAR(200) NULL AFTER `epinglee`;
