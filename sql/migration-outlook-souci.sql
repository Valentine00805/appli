-- Garder trace du dernier échec de synchronisation.
--
-- La synchronisation tourne aussi toute seule, en arrière-plan. Un échec y
-- était jusqu'ici parfaitement muet : rien à l'écran, rien dans un journal,
-- et quelqu'un pouvait attendre des jours des évènements qui ne venaient plus.
--
-- Le dernier souci est donc retenu, et effacé dès que ça remarche.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `souci`    TEXT     NULL AFTER `envoi_le`,
  ADD COLUMN `souci_le` DATETIME NULL AFTER `souci`;
