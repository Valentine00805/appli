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

-- Les évènements ajoutés avant cette migration ne savaient pas d'où ils
-- venaient : on les retrouve par l'évènement qui avait été partagé avec leur
-- propriétaire — même titre, mêmes heures.
UPDATE evenements c
  JOIN partages_amis p ON p.destinataire_id = c.user_id AND p.cible_type = 'evenement'
  JOIN evenements o    ON o.id = p.cible_id AND o.user_id = p.proprietaire_id
   SET c.partage_par = o.user_id, c.partage_de = o.id
 WHERE c.partage_par IS NULL AND c.id <> o.id
   AND c.titre = o.titre AND c.debut = o.debut AND c.fin = o.fin;
