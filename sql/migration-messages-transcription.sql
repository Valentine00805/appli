-- La transcription des messages vocaux.
--
-- Le navigateur de qui enregistre écrit ce qu'il entend pendant
-- l'enregistrement ; le texte part avec le message vocal. NULL : pas de
-- transcription (navigateur qui ne sait pas faire, ou rien entendu).
ALTER TABLE messages
    ADD COLUMN audio_transcription TEXT NULL AFTER audio_duree;
