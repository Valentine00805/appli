-- Recevoir ce qu'on me partage : dans la discussion, ou seulement dans « Partagés ».
--
-- « partages_dans_discussion » : 1, chaque partage arrive aussi en carte dans
-- la discussion (comme jusqu'ici) ; 0, il n'arrive que dans l'onglet
-- « Partagés », avec une notification.
-- Le message qui accompagne un partage est gardé avec lui, pour se lire dans
-- l'onglet ; « vu_le » dit si je l'ai déjà vu — l'onglet compte les autres.

ALTER TABLE users
    ADD COLUMN partages_dans_discussion TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE partages_amis
    ADD COLUMN message TEXT     NULL AFTER droit,
    ADD COLUMN vu_le   DATETIME NULL AFTER created_at;

-- Ce qu'on a déjà reçu avant cette migration n'est plus une nouveauté.
UPDATE partages_amis SET vu_le = created_at WHERE vu_le IS NULL;
