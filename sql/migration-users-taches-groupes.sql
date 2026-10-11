-- Le réglage « Ce que j'ai à faire » (page Groupes) : mes tâches rangées par projet s'ouvrent dépliées (0, comme avant) ou repliées (1).
-- Chacun choisit dans « Mon compte ». Tant que cette colonne n'existe pas, le site tourne : la page Groupes garde ses blocs dépliés.

ALTER TABLE `users`
  ADD COLUMN `taches_groupes_repliees` TINYINT(1) NOT NULL DEFAULT 0;
