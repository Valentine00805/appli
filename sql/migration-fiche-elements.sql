-- Ce qu'on rattache à une fiche de révision sans que ça vienne du cours :
-- des fichiers à elle, des liens web, d'autres cours, des évènements.
-- À exécuter une seule fois : mysql -u root < sql/migration-fiche-elements.sql

USE `mon_appli_cours`;

-- Un fichier marqué « pour la fiche » ne s'affiche plus dans les pièces
-- jointes du cours : c'est la seule différence, tout le reste est commun.
ALTER TABLE `fichiers`
  ADD COLUMN `pour_fiche` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cours_id`,
  ADD KEY `idx_fichiers_fiche` (`cours_id`, `pour_fiche`);

DROP TABLE IF EXISTS `fiche_elements`;

CREATE TABLE `fiche_elements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `type`       ENUM('lien', 'cours', 'evenement') NOT NULL,
  `libelle`    VARCHAR(200) DEFAULT NULL,
  `url`        VARCHAR(2048) DEFAULT NULL,
  -- Une cible par type, chacune avec sa clé étrangère : le cours ou
  -- l'évènement disparu emporte le renvoi, sans laisser de ligne morte.
  `cible_cours_id`     INT UNSIGNED DEFAULT NULL,
  `cible_evenement_id` INT UNSIGNED DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fiche_elements_cours` (`cours_id`, `type`, `position`),
  KEY `idx_fiche_elements_user` (`user_id`),
  CONSTRAINT `fk_fiche_elements_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_cours` FOREIGN KEY (`cible_cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_evt`   FOREIGN KEY (`cible_evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
