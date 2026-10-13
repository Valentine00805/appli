-- Des colonnes de plus dans le tableau « Qui fait quoi » d'un travail de groupe : les phases du projet (recherche, rédaction, relecture…),
-- que l'administrateur du groupe ajoute (avec l'icône de son choix, où il veut entre « À faire » et « Fait »), renomme, déplace et supprime.
-- Une tâche rangée dans l'une d'elles est « en cours » pour tout le reste du site (statut inchangé) : `colonne_id` dit seulement dans quelle phase.
-- Supprimer une colonne remet ses tâches dans « En cours ».
-- `zone` dit après quelle colonne de base la phase est rangée (« a_faire » : juste après « À faire » ; « en_cours » : après « En cours »),
-- `position` son rang dans cette zone.
--
-- Déjà importé une première version de ce fichier (sans `icone` ni `zone`) ? Passez seulement migration-projet-colonnes-icone-zone.sql.
-- Tant que la migration n'est pas importée, le site tourne : le tableau garde ses trois colonnes et le bouton « Nouvelle colonne » n'apparaît pas.

CREATE TABLE IF NOT EXISTS `projet_colonnes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`  INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(40) NOT NULL,
  `icone`      VARCHAR(16) NOT NULL DEFAULT '🔹',
  `zone`       ENUM('a_faire','en_cours') NOT NULL DEFAULT 'en_cours',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projet_colonnes_projet` (`projet_id`, `zone`, `position`),
  CONSTRAINT `fk_projet_colonnes_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projet_taches`
  ADD COLUMN `colonne_id` INT UNSIGNED NULL AFTER `statut`,
  ADD KEY `idx_projet_taches_colonne` (`colonne_id`),
  ADD CONSTRAINT `fk_projet_taches_colonne` FOREIGN KEY (`colonne_id`) REFERENCES `projet_colonnes`(`id`) ON DELETE SET NULL;
