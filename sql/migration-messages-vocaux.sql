-- Les messages vocaux.
--
-- L'enregistrement fait par le navigateur (WebM/Opus, Ogg ou MP4/AAC selon
-- lui), rangé dans storage/messages sous un nom tiré au hasard, et sa durée en
-- secondes — un enregistrement du navigateur ne l'annonce pas toujours lui-même.
ALTER TABLE messages
    ADD COLUMN audio_nom   VARCHAR(64)       NULL AFTER fichier_taille,
    ADD COLUMN audio_duree SMALLINT UNSIGNED NULL AFTER audio_nom;
