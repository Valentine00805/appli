-- Des listes à points à cocher dans un travail de groupe : dans chaque tâche (une liste de sous-tâches) et à part, dans un onglet « Listes »
-- (le matériel à apporter, le plan du rapport, les questions à poser…). Tous les membres qui ont un compte peuvent cocher, ajouter et retirer
-- des points ; on voit qui a coché. Un point appartient à une tâche OU à une liste.
--
-- Tant que ces tables n'existent pas, le site tourne : l'onglet « Listes » et les points des tâches n'apparaissent simplement pas.

CREATE TABLE IF NOT EXISTS `projet_listes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(120) NOT NULL,
  `cree_par`   INT UNSIGNED NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projet_listes_projet` (`projet_id`, `position`),
  CONSTRAINT `fk_projet_listes_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_listes_par`    FOREIGN KEY (`cree_par`)  REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projet_points` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tache_id`   INT UNSIGNED NULL,
  `liste_id`   INT UNSIGNED NULL,
  `texte`      VARCHAR(200) NOT NULL,
  `fait`       TINYINT(1) NOT NULL DEFAULT 0,
  `fait_par`   INT UNSIGNED NULL,
  `fait_le`    DATETIME NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projet_points_tache` (`tache_id`, `position`),
  KEY `idx_projet_points_liste` (`liste_id`, `position`),
  CONSTRAINT `fk_projet_points_tache` FOREIGN KEY (`tache_id`) REFERENCES `projet_taches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_points_liste` FOREIGN KEY (`liste_id`) REFERENCES `projet_listes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projet_points_par`   FOREIGN KEY (`fait_par`) REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
