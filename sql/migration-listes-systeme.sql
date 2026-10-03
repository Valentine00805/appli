-- Les listes que l'application tient pour elle-même (« Révisions », « Alternance »).
--
-- Elles se retrouvaient par leur NOM. Or le nom est écrit dans la langue de celui qui la crée, et
-- l'utilisateur peut le changer : à la première traduction ou au premier renommage, la liste
-- devenait introuvable et une seconde naissait, avec la première orpheline.
--
-- Un « rôle » les identifie désormais, et ne change jamais : « revisions » ou « alternance ».
-- NULL pour toutes les autres, c'est-à-dire presque toutes. La clé unique garantit une seule liste
-- par rôle et par compte (MySQL laisse plusieurs NULL passer).
--
-- À exécuter une seule fois : mysql -u root < sql/migration-listes-systeme.sql

USE `mon_appli_cours`;
SET NAMES utf8mb4;

ALTER TABLE `listes_taches`
  ADD COLUMN `role` VARCHAR(16) DEFAULT NULL AFTER `icone`,
  ADD UNIQUE KEY `uniq_liste_user_role` (`user_id`, `role`);

-- Ce que le code retrouvait par le nom, il le retrouve par le rôle : rien ne change pour l'existant.
-- (La comparaison ignore la casse et les accents, comme la clé unique sur le nom : au plus une
-- liste par compte et par nom, donc aucun conflit possible ici.)
UPDATE `listes_taches` SET `role` = 'revisions' WHERE `nom` = 'Révisions' AND `role` IS NULL;
UPDATE `listes_taches` SET `role` = 'alternance' WHERE `nom` = 'Alternance' AND `role` IS NULL;
