-- Annuler une modification faite par un ami dans un document partagé.
--
-- Le propriétaire revient au texte d'avant, ou retire le fichier ajouté ; un
-- fichier retiré se remet déjà (« restaure »). La ligne reste dans
-- l'historique, marquée comme annulée.

ALTER TABLE modifications_partage
    ADD COLUMN annulee TINYINT(1) NOT NULL DEFAULT 0 AFTER restaure;
