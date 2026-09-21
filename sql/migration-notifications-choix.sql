-- Choisir ce qu'on reçoit : les sortes de notifications coupées (calendrier,
-- tâches, messages…), séparées par des virgules. Vide : tout arrive — et une
-- sorte ajoutée plus tard arrive aussi, sans qu'on ait à la cocher.

ALTER TABLE `users`
  ADD COLUMN `notifications_coupees` VARCHAR(255) NOT NULL DEFAULT '' AFTER `objectif_revision`;
