-- Supprimer un message : pour soi, ou pour les deux.
--
-- « Pour moi » cache le message d'un seul côté (masque_expediteur ou
-- masque_destinataire) : l'autre le voit toujours. « Pour tout le monde »,
-- réservé à qui l'a écrit, vide le message — texte, image, fichier — et note
-- l'heure dans supprime_le : les deux côtés voient « Message supprimé ».
ALTER TABLE messages
    ADD COLUMN supprime_le         DATETIME   NULL AFTER lu_le,
    ADD COLUMN masque_expediteur   TINYINT(1) NOT NULL DEFAULT 0 AFTER supprime_le,
    ADD COLUMN masque_destinataire TINYINT(1) NOT NULL DEFAULT 0 AFTER masque_expediteur;
