-- Un évènement peut renvoyer à PLUSIEURS cours et dossiers (le « + » de « Lier à » dans le formulaire) : « Révisions » → le cours de réseaux, le
-- cours de bases de données et le dossier « Semestre 1 ».
--
-- À passer APRÈS migration-evenements-dossier.sql. Les colonnes « cours_id » et « dossier_id » de « evenements » restent : elles gardent le
-- premier cours et le premier dossier liés (les boutons de l'agenda, le partage d'un évènement, les anciennes sauvegardes les lisent).
-- Cette table, elle, les garde tous. Supprimer le cours ou le dossier retire le lien.

CREATE TABLE IF NOT EXISTS `evenement_liens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evenement_id` INT UNSIGNED NOT NULL,
  `cours_id`     INT UNSIGNED NULL,
  `dossier_id`   INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evenement_liens_evt` (`evenement_id`),
  KEY `idx_evenement_liens_cours` (`cours_id`),
  KEY `idx_evenement_liens_dossier` (`dossier_id`),
  CONSTRAINT `fk_evenement_liens_evt`     FOREIGN KEY (`evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evenement_liens_cours`   FOREIGN KEY (`cours_id`)     REFERENCES `cours`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_evenement_liens_dossier` FOREIGN KEY (`dossier_id`)   REFERENCES `dossiers`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les liens déjà posés (un cours ou un dossier par évènement) passent dans la table.
INSERT INTO `evenement_liens` (`evenement_id`, `cours_id`, `dossier_id`)
SELECT `id`, `cours_id`, NULL FROM `evenements` WHERE `cours_id` IS NOT NULL;
INSERT INTO `evenement_liens` (`evenement_id`, `cours_id`, `dossier_id`)
SELECT `id`, NULL, `dossier_id` FROM `evenements` WHERE `dossier_id` IS NOT NULL;
