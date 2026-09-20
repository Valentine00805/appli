-- La fiche de l'alternance : l'entreprise, le tuteur, et les dates du contrat.
-- Une ligne par compte — on ne fait qu'une alternance à la fois.

CREATE TABLE IF NOT EXISTS `alternance_contrat` (
  `user_id`        INT UNSIGNED NOT NULL,
  `entreprise`     VARCHAR(150) NULL,
  `adresse`        VARCHAR(255) NULL,
  `poste`          VARCHAR(150) NULL,
  `tuteur`         VARCHAR(120) NULL,
  `tuteur_email`   VARCHAR(190) NULL,
  `tuteur_tel`     VARCHAR(40)  NULL,
  `referent`       VARCHAR(120) NULL,
  `debut`          DATE         NULL,
  `fin`            DATE         NULL,
  `remise_rapport` DATE         NULL,
  `soutenance`     DATE         NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_alternance_contrat_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
