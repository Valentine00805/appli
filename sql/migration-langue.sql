-- La langue de l'interface, choisie dans « Mon compte ». Le français reste
-- celle de départ ; ce qu'on écrit soi-même (cours, notes, messages) n'est
-- évidemment pas traduit.

ALTER TABLE `users`
  ADD COLUMN `langue` CHAR(2) NOT NULL DEFAULT 'fr' AFTER `theme`;
