-- L'apparence choisie : claire, sombre, ou celle de l'appareil (« auto »,
-- ce que l'application faisait jusqu'ici).

ALTER TABLE `users`
  ADD COLUMN `theme` ENUM('auto', 'clair', 'sombre') NOT NULL DEFAULT 'auto' AFTER `fuseau`;
