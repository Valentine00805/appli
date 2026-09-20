-- Une session de révision peut porter sur plusieurs cours, ou sur un dossier
-- entier. Le cours principal reste sur la session (c'est celui qu'on ouvre),
-- et cette table dit tous ceux qu'elle couvre — celui-là compris.

CREATE TABLE IF NOT EXISTS `session_revision_cours` (
  `session_id` INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`session_id`, `cours_id`),
  KEY `idx_session_revision_cours_cours` (`cours_id`),
  CONSTRAINT `fk_src_session` FOREIGN KEY (`session_id`) REFERENCES `sessions_revision`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_src_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
