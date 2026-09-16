-- Le réglage de la transcription des messages vocaux.
--
-- Activée par défaut. Coupée, le navigateur n'écoute plus pendant
-- l'enregistrement (Chrome et Edge envoient le son à Google ou Microsoft pour
-- le reconnaître), et le serveur ignore toute transcription envoyée.
ALTER TABLE users
    ADD COLUMN transcription_vocale TINYINT(1) NOT NULL DEFAULT 1 AFTER fuseau;
