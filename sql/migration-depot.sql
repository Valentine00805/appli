-- Déposer un évènement dans l'agenda de quelqu'un d'autre.
--
-- Le reste de la synchronisation ne touche jamais l'agenda des autres : ce
-- qu'on y lit, on le lit ; on n'y écrit pas. Le dépôt est l'exception, et il
-- est volontairement borné à un seul geste — ajouter. Une fois déposée, la
-- copie appartient à la personne à qui on l'a donnée : l'application ne la
-- modifiera pas, ne la supprimera pas, ne la relira pas.

-- 1. Savoir où l'on a le droit d'écrire.
--
-- Un agenda partagé « en lecture » se distingue d'un agenda partagé « en
-- modification », et les deux se ressemblent tant qu'on ne l'a pas demandé.
-- Sans cette colonne on proposerait de déposer là où le dépôt sera refusé.
ALTER TABLE agenda_calendriers
    ADD COLUMN peut_ecrire TINYINT(1) NOT NULL DEFAULT 0 AFTER partage;

-- 2. La trace des dépôts.
--
-- Le titre, la date et le nom du calendrier sont recopiés plutôt que joints :
-- la trace doit rester lisible quand l'évènement d'ici a changé de nom, ou
-- qu'il a été supprimé. Elle dit ce qui est parti, pas ce qu'il est devenu.
CREATE TABLE IF NOT EXISTS agenda_depots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    fournisseur VARCHAR(20) NOT NULL DEFAULT 'microsoft',
    evenement_id INT UNSIGNED DEFAULT NULL,
    calendrier_empreinte CHAR(32) NOT NULL,
    calendrier_nom VARCHAR(190) DEFAULT NULL,
    chez VARCHAR(190) DEFAULT NULL,
    distant_id VARCHAR(512) NOT NULL,
    titre VARCHAR(190) NOT NULL,
    debut DATETIME NOT NULL,
    depose_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_depot_user (user_id, fournisseur),
    KEY idx_depot_evenement (evenement_id),
    CONSTRAINT fk_depot_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_depot_evenement FOREIGN KEY (evenement_id) REFERENCES evenements (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
