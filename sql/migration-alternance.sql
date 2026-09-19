-- L'espace alternance : des notes, le rythme école / entreprise, le journal
-- des missions et les documents (contrat, livret, évaluations, rapport).

-- Des notes libres, mises en forme comme un cours.
CREATE TABLE IF NOT EXISTS `alternance_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `contenu`    LONGTEXT     NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_notes_user` (`user_id`, `updated_at`),
  CONSTRAINT `fk_alternance_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le rythme, posé à la main : du … au …, à l'école ou en entreprise.
-- Deux périodes ne se chevauchent jamais : la dernière posée l'emporte.
CREATE TABLE IF NOT EXISTS `alternance_periodes` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `lieu`    ENUM('ecole', 'entreprise') NOT NULL,
  `debut`   DATE         NOT NULL,
  `fin`     DATE         NOT NULL,
  `note`    VARCHAR(200) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_periodes_user` (`user_id`, `debut`),
  CONSTRAINT `fk_alternance_periodes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le journal des missions : une page par semaine, rangée à son lundi.
CREATE TABLE IF NOT EXISTS `alternance_journal` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `semaine`     DATE         NOT NULL,
  `missions`    MEDIUMTEXT   NULL,
  `competences` VARCHAR(500) NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_alternance_journal_semaine` (`user_id`, `semaine`),
  CONSTRAINT `fk_alternance_journal_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les documents, rangés à part des cours (storage/alternance).
CREATE TABLE IF NOT EXISTS `alternance_documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `categorie`   ENUM('contrat', 'livret', 'evaluation', 'rapport', 'autre') NOT NULL DEFAULT 'autre',
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke`  VARCHAR(100) NOT NULL,
  `mime`        VARCHAR(150) NOT NULL,
  `taille`      INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alternance_documents_user` (`user_id`, `categorie`),
  CONSTRAINT `fk_alternance_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
