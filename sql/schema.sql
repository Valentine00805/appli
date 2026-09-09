-- Schéma de la base « Mes Cours »
-- Exécuter une seule fois : mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS `mon_appli_cours`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mon_appli_cours`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(80)  NOT NULL,
  `fuseau`        VARCHAR(64)  NOT NULL DEFAULT 'Europe/Paris',
  `afficher_miens` TINYINT(1)  NOT NULL DEFAULT 1,
  `couleur_miens`  VARCHAR(7)  NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matieres` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `enseignant` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_matiere_user_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_matieres_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dossiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '📁',
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dossier_user_nom` (`user_id`, `nom`),
  KEY `idx_dossier_position` (`user_id`, `position`),
  KEY `idx_dossier_parent` (`parent_id`),
  CONSTRAINT `fk_dossiers_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_dossiers_parent` FOREIGN KEY (`parent_id`) REFERENCES `dossiers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cours` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `matiere_id` INT UNSIGNED DEFAULT NULL,
  `dossier_id` INT UNSIGNED DEFAULT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `contenu`    LONGTEXT     NULL,
  `fiche_revision` MEDIUMTEXT NULL,
  -- Où l'on en est de sa révision : 0 à réviser, 1 en cours, 2 révisée.
  `etat_revision` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `favori`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cours_user` (`user_id`),
  KEY `idx_cours_matiere` (`matiere_id`),
  KEY `idx_cours_dossier` (`dossier_id`),
  KEY `idx_cours_titre` (`titre`),
  CONSTRAINT `fk_cours_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_cours_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cours_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `nom`     VARCHAR(60)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tag_user_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cours_tag` (
  `cours_id` INT UNSIGNED NOT NULL,
  `tag_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`cours_id`, `tag_id`),
  KEY `idx_cours_tag_tag` (`tag_id`),
  CONSTRAINT `fk_ct_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ct_tag`   FOREIGN KEY (`tag_id`)   REFERENCES `tags`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fichiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  -- Un fichier de la fiche de révision ne figure pas dans les pièces jointes
  -- du cours : c'est la seule différence, tout le reste est commun.
  `pour_fiche` TINYINT(1)   NOT NULL DEFAULT 0,
  `nom_origine` VARCHAR(255) NOT NULL,
  `nom_stocke` VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(120) NOT NULL,
  `taille`     INT UNSIGNED NOT NULL,
  -- Où l'on s'est arrêté dans un enregistrement, et sa durée : en secondes.
  `position_lecture` INT UNSIGNED NOT NULL DEFAULT 0,
  `duree_lecture`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fichiers_cours` (`cours_id`),
  KEY `idx_fichiers_fiche` (`cours_id`, `pour_fiche`),
  CONSTRAINT `fk_fichiers_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fichiers_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Types d'évènement, propres à chaque utilisateur et modifiables depuis l'application.
CREATE TABLE IF NOT EXISTS `types_evenement` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `nom`          VARCHAR(60)  NOT NULL,
  `icone`        VARCHAR(16)  NOT NULL DEFAULT '📌',
  `couleur`      CHAR(7)      NOT NULL DEFAULT '#64748b',
  `est_echeance` TINYINT(1)   NOT NULL DEFAULT 0,
  -- Un cours au programme n'est pas une chose à faire : chaque type décide
  -- s'il paraît sur le tableau.
  `au_tableau`   TINYINT(1)   NOT NULL DEFAULT 1,
  `position`     SMALLINT     NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_user_nom` (`user_id`, `nom`),
  KEY `idx_type_user_position` (`user_id`, `position`),
  CONSTRAINT `fk_types_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les séries d'évènements répétés. (voir sql/migration-repetition.sql)
CREATE TABLE IF NOT EXISTS `series_evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `frequence`   ENUM('jour','semaine','quinzaine','mois') NOT NULL,
  `jours`       VARCHAR(20)  NULL,
  `jusqu_au`    DATE         NOT NULL,
  `nombre_voulu` SMALLINT UNSIGNED NULL,
  `occurrences` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_serie_user` (`user_id`),
  CONSTRAINT `fk_serie_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `matiere_id`  INT UNSIGNED DEFAULT NULL,
  `type_id`     INT UNSIGNED DEFAULT NULL,
  `cours_id`    INT UNSIGNED DEFAULT NULL,
  `serie_id`    INT UNSIGNED DEFAULT NULL,
  `titre`       VARCHAR(200) NOT NULL,
  `description` TEXT         NULL,
  `lieu`        VARCHAR(160) NULL,
  `debut`       DATETIME     NOT NULL,
  `fin`         DATETIME     NOT NULL,
  `journee_entiere` TINYINT(1) NOT NULL DEFAULT 0,
  `agenda_cible`    CHAR(32)     DEFAULT NULL,
  `termine`     TINYINT(1)   NOT NULL DEFAULT 0,
  `etape`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evt_user_debut` (`user_id`, `debut`),
  KEY `idx_evt_type` (`type_id`),
  KEY `idx_evt_serie` (`serie_id`),
  CONSTRAINT `fk_evt_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_evt_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_type`    FOREIGN KEY (`type_id`)    REFERENCES `types_evenement`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evt_cours`   FOREIGN KEY (`cours_id`)   REFERENCES `cours`(`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_evt_serie`   FOREIGN KEY (`serie_id`)   REFERENCES `series_evenements`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget : categories et operations, propres a chaque utilisateur.
CREATE TABLE IF NOT EXISTS `categories_budget` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `nom`              VARCHAR(60)  NOT NULL,
  `icone`            VARCHAR(16)  NOT NULL DEFAULT '💶',
  `couleur`          CHAR(7)      NOT NULL DEFAULT '#64748b',
  `sens`             ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `plafond_mensuel`  DECIMAL(10,2) DEFAULT NULL,
  `position`         SMALLINT     NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_user_nom_sens` (`user_id`, `nom`, `sens`),
  KEY `idx_cat_user_sens` (`user_id`, `sens`, `position`),
  CONSTRAINT `fk_cat_budget_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previsions : charges fixes/revenus reguliers, et soldes saisis a la main.
CREATE TABLE IF NOT EXISTS `recurrences` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `categorie_id` INT UNSIGNED DEFAULT NULL,
  `libelle`      VARCHAR(160)  NOT NULL,
  `montant`      DECIMAL(10,2) NOT NULL,
  `sens`         ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `jour_du_mois` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `moyen`        VARCHAR(40)   DEFAULT NULL,
  `actif`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_user` (`user_id`, `actif`),
  CONSTRAINT `fk_rec_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_budget`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les personnes qui vous remboursent, tenues à part des opérations : une
-- personne existe même sans dépense en cours, et la renommer ne demande pas de
-- reprendre chaque ligne. Les opérations gardent malgré tout le nom écrit,
-- puisque c'est ce qui était vrai le jour de la dépense.
CREATE TABLE IF NOT EXISTS `personnes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personne_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_personne_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Des groupes de personnes, pour lire plusieurs comptes d'un coup. Un groupe ne
-- se choisit pas sur une dépense : celle-ci reste due par une personne, et une
-- seule. Le groupe sert à regarder l'ensemble.
CREATE TABLE IF NOT EXISTS `groupes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(80) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groupe_nom` (`user_id`, `nom`),
  CONSTRAINT `fk_groupe_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groupe_personne` (
  `user_id`     INT UNSIGNED NOT NULL,
  `groupe_id`   INT UNSIGNED NOT NULL,
  `personne_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`groupe_id`, `personne_id`),
  KEY `idx_gp_user` (`user_id`),
  KEY `idx_gp_personne` (`personne_id`),
  CONSTRAINT `fk_gp_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_gp_groupe`   FOREIGN KEY (`groupe_id`)   REFERENCES `groupes`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_gp_personne` FOREIGN KEY (`personne_id`) REFERENCES `personnes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `operations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `categorie_id`   INT UNSIGNED DEFAULT NULL,
  `recurrence_id`  INT UNSIGNED DEFAULT NULL,
  `libelle`        VARCHAR(160)  NOT NULL,
  `montant`        DECIMAL(10,2) NOT NULL,
  `sens`           ENUM('depense','recette') NOT NULL DEFAULT 'depense',
  `date_operation` DATE          NOT NULL,
  `moyen`          VARCHAR(40)   DEFAULT NULL,
  `source`         ENUM('manuelle','import') NOT NULL DEFAULT 'manuelle',
  `empreinte`      CHAR(40)      DEFAULT NULL,
  `note`           TEXT          DEFAULT NULL,
  `a_rembourser`       TINYINT(1)    NOT NULL DEFAULT 0,
  `part_rembourser`    DECIMAL(10,2) NULL,
  `rembourse_par`      VARCHAR(80)   NULL,
  `statut_remb`        ENUM('a_reclamer','hors_total','rembourse') NOT NULL DEFAULT 'a_reclamer',
  `date_remboursement` DATE          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_op_user_date` (`user_id`, `date_operation`),
  KEY `idx_op_categorie` (`categorie_id`),
  KEY `idx_op_empreinte` (`user_id`, `empreinte`),
  KEY `idx_op_remboursement` (`user_id`, `a_rembourser`, `statut_remb`),
  CONSTRAINT `fk_op_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_categorie`  FOREIGN KEY (`categorie_id`)  REFERENCES `categories_budget`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_op_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `recurrences`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soldes_saisis` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `periode`    CHAR(7)       NOT NULL COMMENT 'AAAA-MM',
  `montant`    DECIMAL(12,2) NOT NULL,
  `note`       VARCHAR(160)  DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_solde_user_periode` (`user_id`, `periode`),
  CONSTRAINT `fk_solde_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglements : trace du solde d'un mois de remboursements.
CREATE TABLE IF NOT EXISTS `reglements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `periode`        CHAR(7)       NOT NULL COMMENT 'mois réglé, AAAA-MM',
  `personne`       VARCHAR(80)   DEFAULT NULL COMMENT 'NULL = tout le monde',
  `montant`        DECIMAL(10,2) NOT NULL,
  `date_reglement` DATE          NOT NULL,
  `operation_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'la recette créée en retour',
  `lignes`         TEXT          NOT NULL COMMENT 'identifiants des dépenses soldées, en JSON',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reglement` (`user_id`, `periode`, `personne`),
  CONSTRAINT `fk_reglement_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reglement_operation` FOREIGN KEY (`operation_id`) REFERENCES `operations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Securite : traces des essais de connexion, pour en limiter le rythme.
CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) DEFAULT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `reussie`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentative_email` (`email`, `created_at`),
  KEY `idx_tentative_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listes_taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `nom`        VARCHAR(120) NOT NULL,
  `couleur`    CHAR(7)      NOT NULL DEFAULT '#4f46e5',
  `icone`      VARCHAR(8)   NOT NULL DEFAULT '',
  `echeance`   DATE         DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_liste_user_nom` (`user_id`, `nom`),
  KEY `idx_listes_echeance` (`user_id`, `echeance`),
  KEY `idx_listes_position` (`user_id`, `position`),
  CONSTRAINT `fk_listes_taches_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `liste_id`   INT UNSIGNED NOT NULL,
  `titre`      VARCHAR(200) NOT NULL,
  `echeance`   DATE         DEFAULT NULL,
  `faite`      TINYINT(1)   NOT NULL DEFAULT 0,
  `faite_le`   DATETIME     DEFAULT NULL,
  `etape`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `note`       VARCHAR(500) DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_taches_user` (`user_id`),
  KEY `idx_taches_liste` (`liste_id`),
  KEY `idx_taches_echeance` (`user_id`, `faite`, `echeance`),
  KEY `idx_taches_position` (`liste_id`, `position`),
  CONSTRAINT `fk_taches_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_taches_liste` FOREIGN KEY (`liste_id`) REFERENCES `listes_taches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qu'on rattache à une fiche de révision sans que ça vienne du cours :
-- des liens web, des renvois vers d'autres cours, des évènements du calendrier.
-- Les fichiers de la fiche, eux, vivent dans « fichiers » avec pour_fiche = 1.
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

CREATE TABLE IF NOT EXISTS `fiche_elements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `cours_id`   INT UNSIGNED NOT NULL,
  `type`       ENUM('lien', 'cours', 'evenement') NOT NULL,
  `libelle`    VARCHAR(200) DEFAULT NULL,
  `url`        VARCHAR(2048) DEFAULT NULL,
  -- Une cible par type, chacune avec sa clé étrangère : le cours ou
  -- l'évènement disparu emporte le renvoi, sans laisser de ligne morte.
  `cible_cours_id`     INT UNSIGNED DEFAULT NULL,
  `cible_evenement_id` INT UNSIGNED DEFAULT NULL,
  `position`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fiche_elements_cours` (`cours_id`, `type`, `position`),
  KEY `idx_fiche_elements_user` (`user_id`),
  CONSTRAINT `fk_fiche_elements_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cours` FOREIGN KEY (`cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_cours` FOREIGN KEY (`cible_cours_id`) REFERENCES `cours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiche_elements_cible_evt`   FOREIGN KEY (`cible_evenement_id`) REFERENCES `evenements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relier un calendrier Outlook à l'application.
--
-- Une ligne par compte : de quoi parler à Microsoft (l'identifiant de
-- l'application inscrite, les jetons) et de quoi savoir où l'on en est de la
-- synchronisation (le calendrier suivi, le repère de la dernière lecture).
--
-- Ces jetons ouvrent l'agenda du compte relié : la table est délibérément
-- laissée hors des sauvegardes exportables, pour qu'une archive partagée ne
-- les emporte pas.
CREATE TABLE IF NOT EXISTS `agenda_comptes` (
  `user_id`        INT UNSIGNED NOT NULL,
  `fournisseur`    VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `compte`         VARCHAR(190) NULL,
  `jeton`          TEXT         NULL,
  `renouvellement` TEXT         NULL,
  `permissions`    TEXT         NULL,
  `expire_le`      DATETIME     NULL,
  `calendrier_id`  VARCHAR(255) NULL,
  `calendrier_nom` VARCHAR(190) NULL,
  `calendrier_envoi_id`  VARCHAR(512) NULL,
  `calendrier_envoi_nom` VARCHAR(190) NULL,
  `envoi_choisi`         TINYINT(1)   NOT NULL DEFAULT 0,
  -- Le repère que Microsoft rend pour ne relire que ce qui a changé.
  `delta`          TEXT         NULL,
  `synchro_le`     DATETIME     NULL,
  `envoi_le`       DATETIME     NULL,
  `empreinte_envoi` VARCHAR(64) NULL,
  `souci`          TEXT         NULL,
  `souci_le`       DATETIME     NULL,
  `cree_le`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `fournisseur`),
  CONSTRAINT `fk_outlook_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce qui relie un évènement d'ici à son jumeau là-bas.
--
-- Sans ce lien, une synchronisation ne saurait pas distinguer « un évènement
-- nouveau » de « un évènement déjà connu qui a changé », et les doublerait à
-- chaque passage.
CREATE TABLE IF NOT EXISTS `agenda_liens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `evenement_id` INT UNSIGNED NULL,
  `distant_id`   VARCHAR(255) NOT NULL,
  `calendrier`   CHAR(32)     NULL,
  -- La version connue de part et d'autre, pour repérer ce qui a bougé.
  `etag`         VARCHAR(255) NULL,
  `empreinte`    CHAR(32)     NULL,
  `maj_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lien_distant` (`user_id`, `fournisseur`, `distant_id`),
  KEY `idx_outlook_evenement` (`evenement_id`),
  CONSTRAINT `fk_lien_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lien_evenement` FOREIGN KEY (`evenement_id`)
    REFERENCES `evenements`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les calendriers Outlook d'un compte, et ceux que l'application lit.
-- (voir sql/migration-outlook-calendriers.sql)
CREATE TABLE IF NOT EXISTS `agenda_calendriers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `calendrier_id` TEXT         NOT NULL,
  `empreinte`     CHAR(32)     NOT NULL,
  `nom`           VARCHAR(190) NULL,
  `proprietaire`  VARCHAR(190) NULL,
  `partage`       TINYINT(1)   NOT NULL DEFAULT 0,
  `peut_ecrire`   TINYINT(1)   NOT NULL DEFAULT 0,
  `principal`     TINYINT(1)   NOT NULL DEFAULT 0,
  `suivi`         TINYINT(1)   NOT NULL DEFAULT 0,
  `affiche`       TINYINT(1)   NOT NULL DEFAULT 1,
  `couleur`       VARCHAR(7)   NULL,
  `vu_le`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_calendrier` (`user_id`, `fournisseur`, `empreinte`),
  CONSTRAINT `fk_cal_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce que l'application a écrit dans Outlook.
-- (voir sql/migration-outlook-envoi.sql)
CREATE TABLE IF NOT EXISTS `agenda_envois` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `fournisseur`  VARCHAR(20)  NOT NULL DEFAULT 'microsoft',
  `sorte`      ENUM('evenement','tache') NOT NULL,
  `source_id`  INT UNSIGNED NOT NULL,
  `distant_id` VARCHAR(512) NOT NULL,
  `calendrier_id` TEXT       NULL,
  `empreinte`  CHAR(32)     NULL,
  `maj_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_envoi` (`user_id`, `fournisseur`, `sorte`, `source_id`),
  CONSTRAINT `fk_envoi_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
