-- La photo de profil d'un compte : montrée à la place de son initiale.
ALTER TABLE users
    ADD COLUMN photo_nom  VARCHAR(64) NULL AFTER pseudo,
    ADD COLUMN photo_mime VARCHAR(40) NULL AFTER photo_nom;
