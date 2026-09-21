-- Couper les notifications d'une seule conversation : à deux (dans l'état de
-- la discussion, propre à chacun) ou en groupe (dans l'adhésion du membre).

ALTER TABLE `discussions_etat`
  ADD COLUMN `muette` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notifie_le`;

ALTER TABLE `conversation_membres`
  ADD COLUMN `muette` TINYINT(1) NOT NULL DEFAULT 0 AFTER `regarde_le`;
