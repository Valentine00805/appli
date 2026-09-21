-- Couper des notifications pour un temps : une heure, un jour, une semaine…
-- Passé ce moment (en UTC), elles reviennent d'elles-mêmes. Sans date, la
-- coupure dure jusqu'à ce qu'on la lève.

ALTER TABLE `discussions_etat`
  ADD COLUMN `muette_jusqua` DATETIME NULL AFTER `muette`;

ALTER TABLE `conversation_membres`
  ADD COLUMN `muette_jusqua` DATETIME NULL AFTER `muette`;

-- Chaque sorte coupée peut porter sa fin : « messages@20260922180000 ».
ALTER TABLE `users`
  MODIFY `notifications_coupees` VARCHAR(500) NOT NULL DEFAULT '';
