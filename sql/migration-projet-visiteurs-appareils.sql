-- Un prénom = une personne, par projet. Quelqu'un qui a fermé son navigateur et revient par le lien en redonnant son prénom ne fait plus une
-- seconde ligne : le lien lui demande si c'est bien lui (et le retrouve), ou s'il s'agit d'une autre personne du même prénom (qui précise alors le
-- sien). Une même personne peut avoir plusieurs appareils : leurs jetons passent dans `projet_visiteur_appareils`.
--
-- À passer APRÈS migration-projet-visiteurs.sql. Les doublons déjà là (même prénom, majuscules et accents ne comptant pas) sont fondus en une seule
-- ligne, la plus ancienne ; leurs appareils sont gardés. Tant que cette migration n'est pas importée, le lien public s'ouvre sans demander de
-- prénom, comme avant la première migration.

CREATE TABLE IF NOT EXISTS `projet_visiteur_appareils` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visiteur_id` INT UNSIGNED NOT NULL,
  `empreinte`   CHAR(64)     NOT NULL,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_visiteur_appareils_empreinte` (`empreinte`),
  KEY `idx_projet_visiteur_appareils_visiteur` (`visiteur_id`),
  CONSTRAINT `fk_projet_visiteur_appareils_visiteur` FOREIGN KEY (`visiteur_id`) REFERENCES `projet_visiteurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaque jeton déjà donné devient un appareil de sa personne.
INSERT INTO `projet_visiteur_appareils` (`visiteur_id`, `empreinte`, `created_at`)
SELECT `id`, `empreinte`, `created_at` FROM `projet_visiteurs`;

-- Les doublons : les appareils de chacun passent à la ligne la plus ancienne du même prénom, qui garde le total des visites ; les autres lignes partent.
UPDATE `projet_visiteur_appareils` a
  JOIN `projet_visiteurs` v ON v.`id` = a.`visiteur_id`
  JOIN (SELECT `projet_id`, `prenom`, MIN(`id`) AS `garde` FROM `projet_visiteurs` GROUP BY `projet_id`, `prenom`) g
    ON g.`projet_id` = v.`projet_id` AND g.`prenom` = v.`prenom`
   SET a.`visiteur_id` = g.`garde`;

UPDATE `projet_visiteurs` k
  JOIN (SELECT g.`garde`, SUM(v.`visites`) AS `total`, MAX(v.`derniere_visite`) AS `derniere`
          FROM `projet_visiteurs` v
          JOIN (SELECT `projet_id`, `prenom`, MIN(`id`) AS `garde` FROM `projet_visiteurs` GROUP BY `projet_id`, `prenom`) g
            ON g.`projet_id` = v.`projet_id` AND g.`prenom` = v.`prenom`
         GROUP BY g.`garde`) s ON s.`garde` = k.`id`
   SET k.`visites` = s.`total`, k.`derniere_visite` = s.`derniere`;

DELETE v FROM `projet_visiteurs` v
  JOIN `projet_visiteurs` k ON k.`projet_id` = v.`projet_id` AND k.`prenom` = v.`prenom` AND k.`id` < v.`id`;

-- Le jeton n'est plus sur la personne, mais sur ses appareils ; et un prénom ne se prend qu'une fois par projet.
ALTER TABLE `projet_visiteurs`
  DROP INDEX `uniq_projet_visiteurs_empreinte`,
  DROP COLUMN `empreinte`,
  ADD UNIQUE KEY `uniq_projet_visiteurs_prenom` (`projet_id`, `prenom`);
