-- Savoir sans rien demander qu'il y a quelque chose à envoyer.
--
-- Poser un drapeau à chaque création d'évènement ou de tâche aurait obligé à
-- toucher tous les contrôleurs qui les écrivent — et à ne jamais en oublier un,
-- aujourd'hui comme dans six mois. On préfère constater l'écart : une empreinte
-- de ce qui devrait être là-bas, comparée à celle de la dernière fois.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `empreinte_envoi` VARCHAR(64) NULL AFTER `envoi_le`;
