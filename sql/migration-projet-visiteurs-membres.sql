-- Quelqu'un qui entre par le lien public devient d'office « membre sans compte » du projet : un nom à qui confier des tâches, que les administrateurs
-- retirent quand ils veulent (« Retirer »). `membre_id` relie la personne entrée au membre qui la représente ; `membre_cree` dit si c'est le lien
-- qui l'a créé (alors le retirer de la liste « Entrés par le lien » retire aussi ce membre) ou si c'était un nom sans compte déjà là (alors il reste).
-- Retirer le membre garde la personne dans la liste du lien : son accès ne change pas, et elle n'est pas re-créée comme membre à son retour.
--
-- À passer APRÈS migration-projet-visiteurs-appareils.sql. Tant que cette migration n'est pas importée, entrer par le lien ne crée pas de membre.
-- Les personnes déjà entrées sont rattachées au membre sans compte de même nom s'il existe, sinon elles deviennent membres (30 par projet au plus).

ALTER TABLE `projet_visiteurs`
  ADD COLUMN `membre_id`    INT UNSIGNED NULL AFTER `prenom`,
  ADD COLUMN `membre_cree`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `membre_id`,
  ADD KEY `idx_projet_visiteurs_membre` (`membre_id`),
  ADD CONSTRAINT `fk_projet_visiteurs_membre` FOREIGN KEY (`membre_id`) REFERENCES `projet_membres`(`id`) ON DELETE SET NULL;

-- Un nom sans compte déjà là : c'est lui.
UPDATE `projet_visiteurs` v
  JOIN `projet_membres` m ON m.`projet_id` = v.`projet_id` AND m.`user_id` IS NULL AND m.`nom` = v.`prenom`
   SET v.`membre_id` = m.`id`
 WHERE v.`membre_id` IS NULL;

-- Les autres deviennent membres sans compte (si le projet n'est pas plein).
INSERT INTO `projet_membres` (`projet_id`, `user_id`, `nom`, `role`, `statut`, `created_at`)
SELECT v.`projet_id`, NULL, v.`prenom`, 'membre', 'membre', v.`created_at`
  FROM `projet_visiteurs` v
 WHERE v.`membre_id` IS NULL
   AND (SELECT COUNT(*) FROM `projet_membres` x WHERE x.`projet_id` = v.`projet_id`) < 30;

UPDATE `projet_visiteurs` v
  JOIN `projet_membres` m ON m.`projet_id` = v.`projet_id` AND m.`user_id` IS NULL AND m.`nom` = v.`prenom`
   SET v.`membre_id` = m.`id`, v.`membre_cree` = 1
 WHERE v.`membre_id` IS NULL;
