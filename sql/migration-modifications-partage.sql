-- L'historique des modifications faites par d'autres dans un document partagé.
--
-- Quand un ami qui a le droit de modification écrit dans un cours ou une
-- fiche, le texte d'avant et celui d'après sont gardés : le propriétaire voit
-- ce qui a été ajouté et ce qui a été retiré. Un fichier joint est noté ; un
-- fichier retiré est mis de côté plutôt qu'effacé, pour que le propriétaire
-- puisse l'ouvrir et le remettre.
--
-- « cible_type » vaut « cours » ou « fiche », « cible_id » est le cours.
-- « nature » vaut « texte », « ajout » ou « retrait ».

CREATE TABLE IF NOT EXISTS `modifications_partage` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cible_type`  VARCHAR(8)   NOT NULL,
  `cible_id`    INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `nature`      VARCHAR(8)   NOT NULL,
  `avant`       MEDIUMTEXT   NULL,
  `apres`       MEDIUMTEXT   NULL,
  -- Le fichier joint, tant qu'il existe.
  `fichier_id`  INT UNSIGNED NULL,
  -- Le fichier retiré, mis de côté : de quoi l'ouvrir et le remettre.
  `nom_origine` VARCHAR(255) NULL,
  `nom_stocke`  VARCHAR(255) NULL,
  `mime`        VARCHAR(120) NULL,
  `taille`      INT UNSIGNED NULL,
  `restaure`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_modification_cible` (`cible_type`, `cible_id`, `created_at`),
  KEY `idx_modification_user` (`user_id`),
  CONSTRAINT `fk_modification_partage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
