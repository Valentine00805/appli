-- Répondre à un message, et modifier un message envoyé.
--
-- « reponse_a » désigne le message auquel celui-ci répond ; si ce message
-- disparaît de la base, la réponse reste, sans citation. « modifie_le » note
-- la dernière modification : la bulle affiche alors « modifié », et une page
-- déjà ouverte de l'autre côté reprend le nouveau texte au relevé suivant.
ALTER TABLE messages
    ADD COLUMN reponse_a  INT UNSIGNED NULL AFTER destinataire_id,
    ADD COLUMN modifie_le DATETIME     NULL AFTER created_at,
    ADD KEY idx_messages_reponse (reponse_a),
    ADD KEY idx_messages_modifies (expediteur_id, destinataire_id, modifie_le),
    ADD CONSTRAINT fk_messages_reponse FOREIGN KEY (reponse_a) REFERENCES messages(id) ON DELETE SET NULL;
