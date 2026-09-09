-- Choisir, dans le calendrier, quels agendas on regarde.
--
-- Rien à voir avec la synchronisation : « suivi » dit ce que l'application va
-- chercher chez Microsoft, « affiche » dit ce qu'on veut voir à l'écran. On
-- masque l'agenda d'un proche un après-midi sans cesser de le suivre, et sans
-- que rien ne soit effacé ni retéléchargé.
ALTER TABLE `outlook_calendriers`
  ADD COLUMN `affiche` TINYINT(1) NOT NULL DEFAULT 1 AFTER `suivi`;

-- Et ses propres évènements, qu'on veut parfois regarder seuls.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `afficher_miens` TINYINT(1) NOT NULL DEFAULT 1 AFTER `empreinte_envoi`;
