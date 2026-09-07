-- Migration : un carnet des personnes qui vous remboursent.
--
-- Jusqu'ici, la liste proposée par les formulaires se relisait à chaque fois
-- dans les opérations elles-mêmes : une personne n'existait qu'aussi longtemps
-- qu'une dépense la nommait, et la renommer aurait demandé de reprendre chaque
-- ligne à la main.
--
-- La table les tient à part. Les opérations gardent leur nom écrit — c'est ce
-- qui était vrai au moment de la dépense, et l'export comme les relevés
-- continuent de s'y référer ; renommer une personne met les deux à jour d'un
-- coup.
--
-- La collation est insensible à la casse et aux accents, comme le reste de la
-- base : « Papa » et « papa » ne peuvent donc pas coexister, ce qui est
-- exactement ce qu'on veut d'un carnet.
--
-- À exécuter une seule fois. Le script ne fait rien s'il est relancé.
--   mysql -u root < sql/migration-personnes.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `personnes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personne_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_personne_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les noms déjà employés entrent au carnet : personne ne doit disparaître de la
-- liste le jour où celle-ci cesse de se relire dans les opérations.
INSERT IGNORE INTO `personnes` (`user_id`, `nom`)
SELECT DISTINCT `user_id`, `rembourse_par`
  FROM `operations`
 WHERE `rembourse_par` IS NOT NULL AND `rembourse_par` <> '';

SELECT COUNT(*) AS personnes_au_carnet FROM `personnes`;
