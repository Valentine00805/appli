-- D'où vient un évènement ajouté depuis un partage.
--
-- « partage_par » : l'ami qui l'a partagé ; « partage_de » : son évènement
-- d'origine, tant qu'il existe. De quoi dire, sur la fiche, « partagé par
-- Max » et y renvoyer.

ALTER TABLE evenements
    ADD COLUMN partage_par INT UNSIGNED NULL AFTER copie_de,
    ADD COLUMN partage_de  INT UNSIGNED NULL AFTER partage_par,
    ADD KEY idx_evt_partage_de (partage_de),
    ADD CONSTRAINT fk_evt_partage_par FOREIGN KEY (partage_par) REFERENCES users(id)      ON DELETE SET NULL,
    ADD CONSTRAINT fk_evt_partage_de  FOREIGN KEY (partage_de)  REFERENCES evenements(id) ON DELETE SET NULL;
