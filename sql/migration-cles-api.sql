-- Migration : la clé d'API que chaque utilisateur peut enregistrer (Gemini pour commencer).
--
-- La clé n'est jamais écrite en clair : `cle_chiffree` contient « nonce + étiquette + texte chiffré »
-- (AES-256-GCM, en base64), la clé de chiffrement vivant dans config/parametres.php, hors de la base.
-- `fin` garde les quatre derniers caractères pour pouvoir dire « se termine par … » sans déchiffrer.
-- Cette table ne figure pas dans les sauvegardes exportables : une archive se partage, une clé non.
--
-- À exécuter une seule fois ; relancé, le script ne casse rien.
--   mysql -u root < sql/migration-cles-api.sql

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `cles_api` (
  `user_id`      INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL,
  `cle_chiffree` TEXT         NOT NULL,
  `fin`          CHAR(4)      NOT NULL,
  `cree_le`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `fournisseur`),
  CONSTRAINT `fk_cles_api_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
