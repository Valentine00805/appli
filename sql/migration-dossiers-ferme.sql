-- La colonne des dossiers de « Mes cours », ouverte ou fermée.
--
-- Comme le volet des agendas : zéro, elle est ouverte — l'état d'avant.
ALTER TABLE users
    ADD COLUMN dossiers_ferme TINYINT(1) NOT NULL DEFAULT 0;
