-- Un type d'évènement décide s'il paraît sur le tableau.
-- Un cours au programme n'est pas une chose à faire : il n'a rien à y faire.
-- À exécuter une seule fois : mysql -u root < sql/migration-type-au-tableau.sql

USE `mon_appli_cours`;

ALTER TABLE `types_evenement`
  ADD COLUMN `au_tableau` TINYINT(1) NOT NULL DEFAULT 1 AFTER `est_echeance`;

-- Les types déjà nommés « Cours » sortent du tableau, ce qui était la demande.
-- Tous les autres y restent : le réglage se change depuis Organisation → Types.
UPDATE `types_evenement` SET `au_tableau` = 0 WHERE `nom` = 'Cours';
