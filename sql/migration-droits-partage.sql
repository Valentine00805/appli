-- Ce qu'un partage permet : lire, commenter, ou modifier.
--
-- « lecture » : ouvrir le document et ses fichiers, rien de plus.
-- « commentaire » : en plus, écrire des commentaires sous le document.
-- « modification » : en plus, écrire dans le document, y ajouter des
--   fichiers et en retirer. Le document reste à son propriétaire, qui peut
--   reprendre le droit à tout moment.
--
-- Les partages déjà faits restent en lecture seule, comme ils l'étaient.

ALTER TABLE partages_amis
    ADD COLUMN droit VARCHAR(12) NOT NULL DEFAULT 'lecture' AFTER cible_id;

CREATE TABLE IF NOT EXISTS `commentaires_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cible_type` VARCHAR(8)   NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `texte`      TEXT         NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commentaire_cible` (`cible_type`, `cible_id`, `created_at`),
  KEY `idx_commentaire_user` (`user_id`),
  CONSTRAINT `fk_commentaire_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
