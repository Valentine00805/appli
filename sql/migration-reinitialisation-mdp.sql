-- Les demandes de réinitialisation de mot de passe.
--
-- Le lien envoyé par e-mail porte un jeton ; seule son empreinte (SHA-256) est
-- gardée. Il vaut une heure, une seule fois.
CREATE TABLE IF NOT EXISTS `reinitialisations_mdp` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `jeton_hash` CHAR(64)     NOT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `cree_le`    DATETIME     NOT NULL,
  `expire_le`  DATETIME     NOT NULL,
  `utilise_le` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reinitialisation_jeton` (`jeton_hash`),
  KEY `idx_reinitialisation_user` (`user_id`, `cree_le`),
  KEY `idx_reinitialisation_ip` (`ip`, `cree_le`),
  CONSTRAINT `fk_reinitialisation_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
