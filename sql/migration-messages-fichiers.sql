-- Les fichiers dans les discussions.
--
-- Comme une image, un fichier par message, rangé dans storage/messages sous un
-- nom tiré au hasard. On garde son nom d'origine pour le rendre à qui le
-- télécharge, son type et son poids pour l'annoncer.
ALTER TABLE messages
    ADD COLUMN fichier_nom     VARCHAR(64)  NULL AFTER image_hauteur,
    ADD COLUMN fichier_origine VARCHAR(255) NULL AFTER fichier_nom,
    ADD COLUMN fichier_mime    VARCHAR(120) NULL AFTER fichier_origine,
    ADD COLUMN fichier_taille  INT UNSIGNED NULL AFTER fichier_mime;
