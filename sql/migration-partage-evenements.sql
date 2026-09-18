-- Partager un évènement du calendrier.
--
-- Le type « evenement » compte neuf lettres : les colonnes qui nomment ce
-- qu'on partage passent de 8 à 12 caractères. Rien d'autre ne change.

ALTER TABLE partages_amis          MODIFY cible_type   VARCHAR(12) NOT NULL;
ALTER TABLE liens_partage          MODIFY cible_type   VARCHAR(12) NOT NULL;
ALTER TABLE messages               MODIFY partage_type VARCHAR(12) NULL;
ALTER TABLE conversation_messages  MODIFY partage_type VARCHAR(12) NULL;
ALTER TABLE commentaires_partage   MODIFY cible_type   VARCHAR(12) NOT NULL;
ALTER TABLE lots_partage_documents MODIFY cible_type   VARCHAR(12) NOT NULL;
ALTER TABLE modifications_partage  MODIFY cible_type   VARCHAR(12) NOT NULL;
