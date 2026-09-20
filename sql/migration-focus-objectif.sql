-- L'objectif de révision de la semaine, en minutes (0 : aucun objectif), et
-- le silence pendant une session : les rappels attendent la fin plutôt que
-- d'interrompre ce pour quoi on s'est justement isolé.

ALTER TABLE `users`
  ADD COLUMN `objectif_revision` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `transcription_vocale`;

ALTER TABLE `sessions_revision`
  ADD COLUMN `ne_pas_deranger` TINYINT(1) NOT NULL DEFAULT 1 AFTER `minutes_voulues`;
