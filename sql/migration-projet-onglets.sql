-- Les onglets d'un travail de groupe, au choix du groupe : « Qui fait quoi » et « Membres » sont toujours là, les autres (échéances, fichiers, cours,
-- document commun, livrables, listes) se cochent à la création du projet, et se changent ensuite (l'administrateur). `onglets` garde la liste de ceux
-- qui sont gardés, séparés par des virgules (« fichiers,document ») ; NULL : tous (c'est le cas de tous les projets déjà là).
--
-- Tant que cette migration n'est pas importée, le site tourne : chaque projet a tous ses onglets et le choix n'est pas proposé.

ALTER TABLE `projets`
  ADD COLUMN `onglets` VARCHAR(120) NULL AFTER `pdf_entete`;
