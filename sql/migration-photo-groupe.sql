-- La photo de profil d'un groupe : choisie par un membre, vue par tous.
ALTER TABLE conversations
    ADD COLUMN photo_nom  VARCHAR(64) NULL AFTER created_at,
    ADD COLUMN photo_mime VARCHAR(40) NULL AFTER photo_nom;
