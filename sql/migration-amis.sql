-- Les amis et leurs messages.
--
-- Une amitié relie deux comptes : qui a demandé, et à qui. Tant que l'autre
-- n'a pas accepté, elle est « en attente » et rien ne s'échange. La paire est
-- aussi rangée du plus petit au plus grand identifiant (petit_id, grand_id) :
-- A→B et B→A sont la même amitié, et la clé unique l'empêche d'exister deux
-- fois. (Des colonnes calculées auraient fait l'affaire, mais MySQL refuse la
-- suppression en cascade sur les colonnes dont elles dépendent.)
CREATE TABLE IF NOT EXISTS `amities` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demandeur_id`    INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  `petit_id`        INT UNSIGNED NOT NULL,
  `grand_id`        INT UNSIGNED NOT NULL,
  `statut`          ENUM('attente', 'acceptee') NOT NULL DEFAULT 'attente',
  `created_at`      DATETIME     NOT NULL,
  `acceptee_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_amitie_paire` (`petit_id`, `grand_id`),
  KEY `idx_amitie_destinataire` (`destinataire_id`, `statut`),
  KEY `idx_amitie_demandeur` (`demandeur_id`, `statut`),
  CONSTRAINT `fk_amitie_demandeur`    FOREIGN KEY (`demandeur_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_amitie_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_amitie_paire` CHECK (`petit_id` = LEAST(`demandeur_id`, `destinataire_id`)
                                   AND `grand_id` = GREATEST(`demandeur_id`, `destinataire_id`)
                                   AND `demandeur_id` <> `destinataire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les messages entre deux amis. Les heures sont en temps universel : deux amis
-- n'ont pas forcément le même fuseau, chacun les lit dans le sien.
CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expediteur_id`   INT UNSIGNED NOT NULL,
  `destinataire_id` INT UNSIGNED NOT NULL,
  `texte`           TEXT         NOT NULL,
  `created_at`      DATETIME     NOT NULL,
  `lu_le`           DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_messages_fil` (`expediteur_id`, `destinataire_id`, `id`),
  KEY `idx_messages_non_lus` (`destinataire_id`, `lu_le`),
  CONSTRAINT `fk_messages_expediteur`   FOREIGN KEY (`expediteur_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
