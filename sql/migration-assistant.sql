-- L'assistant IA : des discussions avec Gemini, à la clé de chacun (voir CleApi). Une discussion garde ses tours, pour que
-- le modèle se souvienne de ce qui s'est dit ; elle peut porter sur un cours (son texte est alors envoyé avec les messages).
--
-- `assistant_messages.role` : « user » (la personne) ou « model » (la réponse de Gemini) ; elles s'alternent.
-- `cours_id` : le cours dont on parle, ou NULL ; si le cours est effacé, la discussion reste, sans cours.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-assistant.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `assistant_conversations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NULL,
  `titre`      VARCHAR(120) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assistant_conv_user` (`user_id`, `updated_at`),
  CONSTRAINT `fk_assistant_conv_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assistant_conv_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assistant_messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `role`            ENUM('user', 'model') NOT NULL,
  `texte`           MEDIUMTEXT   NOT NULL,
  `modele`          VARCHAR(60)  NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assistant_msg_conv` (`conversation_id`, `id`),
  CONSTRAINT `fk_assistant_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `assistant_conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
