-- Les images dans les discussions.
--
-- Une image par message, rangée dans storage/messages sous un nom tiré au
-- hasard ; le texte, lui, peut alors rester vide (une photo sans légende).
-- Largeur et hauteur servent à réserver la place avant le chargement, pour
-- que la conversation ne saute pas à chaque image qui arrive.
ALTER TABLE messages
    ADD COLUMN image_nom     VARCHAR(64)       NULL AFTER texte,
    ADD COLUMN image_mime    VARCHAR(40)       NULL AFTER image_nom,
    ADD COLUMN image_largeur SMALLINT UNSIGNED NULL AFTER image_mime,
    ADD COLUMN image_hauteur SMALLINT UNSIGNED NULL AFTER image_largeur;
