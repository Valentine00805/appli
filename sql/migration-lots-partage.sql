-- Un lien public pour plusieurs documents à la fois.
--
-- Le partage à ses amis se fait document par document : chacun a son accès.
-- Un lien, lui, ne peut désigner qu'une seule cible — d'où le « lot » : une
-- poignée de documents rassemblés sous un nom, que le lien montre ensemble.
--
-- Le lot ne copie rien : il désigne. Un document supprimé quitte simplement la
-- page, et supprimer le lot ne touche à aucun document.

CREATE TABLE IF NOT EXISTS `lots_partage` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lot_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_lot_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lots_partage_documents` (
  `lot_id`     INT UNSIGNED NOT NULL,
  `cible_type` VARCHAR(8)   NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`lot_id`, `cible_type`, `cible_id`),
  CONSTRAINT `fk_lot_document_lot` FOREIGN KEY (`lot_id`) REFERENCES `lots_partage`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
