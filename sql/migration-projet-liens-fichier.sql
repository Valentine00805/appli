-- Un fichier se met dans un travail de groupe comme un cours ou un dossier : « Partager » → « Dans un projet de groupe », depuis le lecteur
-- de fichiers ou depuis « Partager plusieurs ». Les membres le consultent et le téléchargent (lecture seule) depuis l'onglet « Cours ».
--
-- À passer APRÈS migration-projet-liens.sql. La cible est toujours désignée par son type et son numéro, sans clé étrangère : un fichier
-- effacé disparaît simplement de la liste.

ALTER TABLE `projet_liens`
  MODIFY `type` ENUM('cours', 'dossier', 'fichier') NOT NULL;
