-- Une échéance sur la liste elle-même : la tâche principale a sa propre date.
-- À exécuter une seule fois : mysql -u root < sql/migration-echeance-liste.sql

USE `mon_appli_cours`;

ALTER TABLE `listes_taches`
  ADD COLUMN `echeance` DATE DEFAULT NULL AFTER `icone`,
  ADD KEY `idx_listes_echeance` (`user_id`, `echeance`);
