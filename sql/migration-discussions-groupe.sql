-- Les discussions de groupe.
--
-- Une conversation réunit des amis de celui qui les y ajoute (à ne pas
-- confondre avec les « groupes » de personnes du budget). Ses messages ont
-- leur propre table : tout ce qu'on sait faire à deux (photos, fichiers,
-- vocaux, réponses, réactions, épingles, fond d'écran) s'y retrouve, mais
-- « supprimer pour moi », « lu » et « vu » se comptent par membre.
--
-- Un nouveau membre ne voit que ce qui s'écrit après son arrivée
-- (depuis_message). Qui quitte la conversation, ou en est retiré, n'y voit
-- plus rien.

CREATE TABLE IF NOT EXISTS `conversations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(60)  NOT NULL,
  `cree_par`    INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL,
  -- Le fond d'écran, commun à tous les membres.
  `fond_nom`    VARCHAR(64)  NULL,
  `fond_mime`   VARCHAR(40)  NULL,
  `fond_par`    INT UNSIGNED NULL,
  `fond_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversations_cree_par` (`cree_par`),
  KEY `idx_conversations_fond_par` (`fond_par`),
  CONSTRAINT `fk_conversations_cree_par` FOREIGN KEY (`cree_par`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversations_fond_par` FOREIGN KEY (`fond_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_membres` (
  `conversation_id` INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `role`            ENUM('admin', 'membre') NOT NULL DEFAULT 'membre',
  `rejoint_le`      DATETIME     NOT NULL,
  -- Les messages visibles commencent après celui-ci.
  `depuis_message`  INT UNSIGNED NOT NULL DEFAULT 0,
  -- Le dernier message lu.
  `lu_jusqua`       INT UNSIGNED NOT NULL DEFAULT 0,
  -- La discussion sous les yeux (onglet affiché, fenêtre active) : pas de notification.
  `regarde_le`      DATETIME     NULL,
  PRIMARY KEY (`conversation_id`, `user_id`),
  KEY `idx_conversation_membres_user` (`user_id`),
  CONSTRAINT `fk_conversation_membres_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_membres_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `expediteur_id`   INT UNSIGNED NULL,
  `reponse_a`       INT UNSIGNED NULL,
  `texte`           TEXT         NOT NULL,
  -- Une note plutôt qu'un message : « creation », « ajout », « retrait »,
  -- « depart », « nom », « admin », « fond », « fond_retire ».
  `evenement`       VARCHAR(20)  NULL,
  `evenement_cible` INT UNSIGNED NULL,
  `evenement_texte` VARCHAR(80)  NULL,
  `image_nom`       VARCHAR(64)  NULL,
  `image_mime`      VARCHAR(40)  NULL,
  `image_largeur`   SMALLINT UNSIGNED NULL,
  `image_hauteur`   SMALLINT UNSIGNED NULL,
  `fichier_nom`     VARCHAR(64)  NULL,
  `fichier_origine` VARCHAR(255) NULL,
  `fichier_mime`    VARCHAR(120) NULL,
  `fichier_taille`  INT UNSIGNED NULL,
  `audio_nom`       VARCHAR(64)  NULL,
  `audio_duree`     SMALLINT UNSIGNED NULL,
  `audio_transcription` TEXT     NULL,
  `created_at`      DATETIME     NOT NULL,
  `modifie_le`      DATETIME     NULL,
  `reactions_le`    DATETIME     NULL,
  `supprime_le`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_messages_fil` (`conversation_id`, `id`),
  KEY `idx_conversation_messages_expediteur` (`expediteur_id`, `created_at`),
  KEY `idx_conversation_messages_reponse` (`reponse_a`),
  KEY `idx_conversation_messages_cible` (`evenement_cible`),
  CONSTRAINT `fk_conversation_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_messages_expediteur`   FOREIGN KEY (`expediteur_id`)   REFERENCES `users`(`id`)                 ON DELETE SET NULL,
  CONSTRAINT `fk_conversation_messages_reponse`      FOREIGN KEY (`reponse_a`)       REFERENCES `conversation_messages`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversation_messages_cible`        FOREIGN KEY (`evenement_cible`) REFERENCES `users`(`id`)                 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- « Supprimer pour moi » : le message disparaît pour ce membre seulement.
CREATE TABLE IF NOT EXISTS `conversation_masques` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_conversation_masques_user` (`user_id`),
  CONSTRAINT `fk_conversation_masques_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_masques_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_reactions` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(32)  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`message_id`, `user_id`),
  KEY `idx_conversation_reactions_user` (`user_id`),
  CONSTRAINT `fk_conversation_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_reactions_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_epingles` (
  `user_id`    INT UNSIGNED NOT NULL,
  `message_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`, `message_id`),
  KEY `idx_conversation_epingles_message` (`message_id`),
  CONSTRAINT `fk_conversation_epingles_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)                 ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_epingles_message` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
