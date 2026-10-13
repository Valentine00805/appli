-- Le choix du groupe pour l'en-tête du PDF de son document commun : le titre, le sous-titre, et ce qu'on y ajoute (les noms des membres, la date de la
-- version, un texte libre : cours, professeur, classe…). Un petit texte JSON par projet ; vide : l'en-tête de départ (le nom du projet, « Document commun »).
--
-- Tant que cette migration n'est pas importée, le site tourne : le PDF garde l'en-tête de départ et le réglage n'est pas proposé.

ALTER TABLE `projets`
  ADD COLUMN `pdf_entete` TEXT NULL AFTER `document_le`;
