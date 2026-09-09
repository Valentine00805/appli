-- Répercuter dans Outlook la suppression faite ici.
--
-- Deux choses manquaient.
--
-- D'abord la mémoire : le lien s'effaçait en cascade avec l'évènement, si bien
-- qu'au moment de prévenir Microsoft il ne restait plus rien à lui nommer. Le
-- lien survit désormais à l'évènement, sans lui — une pierre tombale que la
-- synchronisation suivante ramasse.
--
-- Ensuite l'origine : on ne supprime que dans « Mes Cours » et dans le
-- calendrier principal. Effacer un rendez-vous chez quelqu'un qui a partagé son
-- agenda serait la pire chose que cette application puisse faire, et savoir
-- d'où vient un évènement est la seule façon de s'en garder.
ALTER TABLE `outlook_liens`
  ADD COLUMN `calendrier` CHAR(32) NULL AFTER `outlook_id`;

ALTER TABLE `outlook_liens`
  DROP FOREIGN KEY `fk_lien_evenement`;

ALTER TABLE `outlook_liens`
  ADD CONSTRAINT `fk_lien_evenement` FOREIGN KEY (`evenement_id`)
    REFERENCES `evenements`(`id`) ON DELETE SET NULL;
