-- Les documents d'un travail de groupe liés à un de ses évènements : un cours, un dossier ou un fichier du projet, rattaché à un
-- évènement du calendrier commun ou à une échéance du projet. On le voit sur l'évènement, et sur le document.
--
-- À passer APRÈS migration-calendriers-projet.sql. L'évènement et le document sont désignés par leur type et leur numéro, sans clé
-- étrangère (deux tables d'origine possibles) : une ligne dont l'évènement ou le document a disparu est simplement ignorée à la lecture.

CREATE TABLE IF NOT EXISTS `projet_evenement_liens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`      INT UNSIGNED NOT NULL,
  `evenement_type` ENUM('evenement', 'echeance') NOT NULL,
  `evenement_id`   INT UNSIGNED NOT NULL,
  `cible_type`     ENUM('cours', 'dossier', 'fichier') NOT NULL,
  `cible_id`       INT UNSIGNED NOT NULL,
  `ajoute_par`     INT UNSIGNED NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_evenement_liens` (`evenement_type`, `evenement_id`, `cible_type`, `cible_id`),
  KEY `idx_projet_evenement_liens_cible` (`projet_id`, `cible_type`, `cible_id`),
  CONSTRAINT `fk_projet_evenement_liens_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_evenement_liens_par`    FOREIGN KEY (`ajoute_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
