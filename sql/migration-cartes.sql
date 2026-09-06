-- Les cartes de révision d'un cours, et où chacune en est.
--
-- « boite » suit la méthode de Leitner : une carte sue monte d'une boîte et
-- revient plus tard, une carte ratée redescend en boîte 1 et revient le jour
-- même. « revoir_le » dit quand elle se représentera.
--
-- L'empreinte est la question réduite à sa substance : elle empêche qu'une même
-- question soit proposée deux fois pour un cours.

CREATE TABLE IF NOT EXISTS `cartes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `question`   VARCHAR(500) NOT NULL,
  `reponse`    TEXT         NOT NULL,
  -- D'où elle vient : 'cours', 'fiche', 'fichier' ou 'main'.
  `origine`    VARCHAR(20)  NOT NULL DEFAULT 'main',
  `source`     VARCHAR(255) NOT NULL DEFAULT '',
  `empreinte`  CHAR(32)     NOT NULL,
  `boite`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `revoir_le`  DATE         NOT NULL,
  `vues`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reussies`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cartes_question` (`cours_id`, `empreinte`),
  KEY `idx_cartes_revoir` (`user_id`, `revoir_le`),
  CONSTRAINT `fk_cartes_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cartes_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
