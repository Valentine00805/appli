-- Une fiche de révision pour un dossier de « Mes cours » : ce qu'il faut retenir de tout le dossier, écrit à part des cours qu'il contient (elle ne crée
-- aucun cours et n'en transforme aucun). Vide : le dossier n'a pas de fiche.
--
-- Tant que cette migration n'est pas importée, le site tourne : le bouton « Fiche de révision » n'apparaît pas sur les dossiers.

ALTER TABLE `dossiers`
  ADD COLUMN `fiche_revision` LONGTEXT NULL AFTER `icone`,
  -- Quand la fiche a été enregistrée pour la dernière fois (pour « modifiée le » et le tri de la liste des fiches).
  ADD COLUMN `fiche_modifiee_le` DATETIME NULL AFTER `fiche_revision`;
