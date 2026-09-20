-- Une session posée au calendrier peut viser plusieurs cours. L'évènement n'en
-- garde qu'un (le principal) ; cette table dit tous ceux qu'on avait prévus,
-- pour que le bouton « Démarrer la session » les retrouve tous.

CREATE TABLE IF NOT EXISTS `evenement_revision_cours` (
  `evenement_id` INT UNSIGNED NOT NULL,
  `cours_id`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`evenement_id`, `cours_id`),
  KEY `idx_evenement_revision_cours_cours` (`cours_id`),
  CONSTRAINT `fk_erc_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
