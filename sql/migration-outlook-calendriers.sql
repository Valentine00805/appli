-- Suivre plusieurs calendriers Outlook, et pas seulement le principal.
--
-- Un agenda réel est rarement d'un seul tenant : le calendrier personnel, les
-- jours fériés, celui d'un proche ou d'un groupe qu'on a accepté. Microsoft les
-- tient séparés, et l'application doit pouvoir choisir lesquels elle regarde
-- plutôt que d'en imposer un.
CREATE TABLE IF NOT EXISTS `outlook_calendriers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  -- Les identifiants de Microsoft sont longs : on indexe leur empreinte.
  `calendrier_id` TEXT         NOT NULL,
  `empreinte`     CHAR(32)     NOT NULL,
  `nom`           VARCHAR(190) NULL,
  `proprietaire`  VARCHAR(190) NULL,
  -- Le calendrier appartient-il à quelqu'un d'autre ?
  `partage`       TINYINT(1)   NOT NULL DEFAULT 0,
  -- Est-il celui que Microsoft considère comme le calendrier par défaut ?
  `principal`     TINYINT(1)   NOT NULL DEFAULT 0,
  -- L'application le lit-elle ?
  `suivi`         TINYINT(1)   NOT NULL DEFAULT 0,
  `vu_le`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_calendrier` (`user_id`, `empreinte`),
  CONSTRAINT `fk_cal_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce que Microsoft a effectivement accordé.
--
-- Les permissions demandées et les permissions obtenues sont deux choses
-- différentes : une autorisation donnée hier ne couvre pas ce qu'on a ajouté
-- depuis. Les garder permet de dire « il faut réautoriser » au lieu de laisser
-- un appel échouer sans explication.
ALTER TABLE `outlook_comptes`
  ADD COLUMN `permissions` TEXT NULL AFTER `renouvellement`;
