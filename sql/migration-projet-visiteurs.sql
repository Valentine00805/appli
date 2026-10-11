-- Le lien public d'un travail de groupe demande un prénom avant de montrer le projet : « sans ça, on n'y accède pas ». Chaque personne qui
-- entre est gardée ici — son prénom, quand elle est arrivée, sa dernière visite — pour que les membres voient qui est passé par le lien.
--
-- `empreinte` : le condensé (SHA-256) du jeton que le navigateur du visiteur garde dans un cookie ; le jeton lui-même n'est pas conservé.
-- Fermer le lien, ou en créer un autre, efface ces lignes : un cookie d'avant ne donne plus accès à rien.
--
-- Tant que cette table n'existe pas, le site tourne : le lien public s'ouvre sans demander de prénom, comme avant.

CREATE TABLE IF NOT EXISTS `projet_visiteurs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`       INT UNSIGNED NOT NULL,
  `prenom`          VARCHAR(60)  NOT NULL,
  `empreinte`       CHAR(64)     NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  `derniere_visite` DATETIME     NOT NULL,
  `visites`         INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_projet_visiteurs_empreinte` (`empreinte`),
  KEY `idx_projet_visiteurs_projet` (`projet_id`, `created_at`),
  CONSTRAINT `fk_projet_visiteurs_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
