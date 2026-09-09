-- Un évènement peut partir dans plusieurs agendas à la fois.
--
-- La colonne « agenda_cible » n'en désignait qu'un. Une table le dit mieux :
-- un évènement, plusieurs destinations, et la contrainte de clef primaire
-- empêche d'y inscrire deux fois le même agenda.
--
-- L'empreinte vide veut dire « Mes évènements » — le calendrier de
-- l'application. Aucune ligne veut dire la même chose : c'est le cas de tout
-- ce qui existe déjà, et rien ne change pour eux.
CREATE TABLE IF NOT EXISTS evenement_agendas (
    evenement_id INT UNSIGNED NOT NULL,
    empreinte CHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (evenement_id, empreinte),
    CONSTRAINT fk_evt_agenda FOREIGN KEY (evenement_id)
        REFERENCES evenements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE evenements DROP COLUMN agenda_cible;

-- Ce qui est parti là-bas se comptait par évènement et par compte. Il se
-- compte désormais aussi par calendrier : le même rendez-vous peut avoir une
-- copie dans deux agendas du même compte, et chacune a son identifiant, son
-- empreinte, sa vie. Sans cela la seconde écraserait la première.
--
-- Le calendrier était déjà retenu en clair ; il l'est aussi en empreinte, car
-- un TEXT n'entre pas dans une clef unique.
ALTER TABLE agenda_envois
    ADD COLUMN calendrier_empreinte CHAR(32) NOT NULL DEFAULT '' AFTER calendrier_id;

UPDATE agenda_envois
   SET calendrier_empreinte = MD5(COALESCE(calendrier_id, ''))
 WHERE calendrier_empreinte = '';

ALTER TABLE agenda_envois
    DROP INDEX uniq_envoi,
    ADD UNIQUE KEY uniq_envoi (user_id, fournisseur, sorte, source_id, calendrier_empreinte);
