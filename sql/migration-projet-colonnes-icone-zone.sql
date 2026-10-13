-- Pour qui a déjà importé la première version de migration-projet-colonnes.sql (phases sans icône ni place au choix) : ajoute à chaque phase
-- son icône (🔹 au départ) et la zone où elle est rangée (« en_cours » : après « En cours », comme avant). À passer UNE fois.
-- Qui n'a pas encore importé migration-projet-colonnes.sql n'en a pas besoin : ce fichier-là est complet.
--
-- Tant que ce fichier n'est pas importé, les phases sont masquées (le site tourne avec ses trois colonnes de base).

ALTER TABLE `projet_colonnes`
  ADD COLUMN `icone` VARCHAR(16) NOT NULL DEFAULT '🔹' AFTER `nom`,
  ADD COLUMN `zone`  ENUM('a_faire','en_cours') NOT NULL DEFAULT 'en_cours' AFTER `icone`;
