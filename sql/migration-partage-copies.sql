-- Reconnaître ce qu'on a déjà copié : une copie garde l'identifiant de son origine, pour ne plus proposer de la refaire (voir
-- Partages::maCopie).
--
-- `cours.partage_nature` : « cours » ou « fiche » — une fiche copiée est un cours à soi dont c'est la fiche ; sans la nature, le cours et
--   sa fiche (deux copies de la même origine) ne se distingueraient pas. Les copies déjà faites (partage_de rempli) sont rangées d'après
--   leur forme : sans texte mais avec une fiche, c'est une fiche.
-- `dossiers.partage_de` : le dossier copié (le dossier de tête ; ses sous-dossiers n'en ont pas besoin).
-- Pas de clé étrangère : l'origine peut disparaître, la copie reste.
--
-- À exécuter une seule fois.
--   mysql -u root < sql/migration-partage-copies.sql

USE `mon_appli_cours`;

ALTER TABLE `cours`
  ADD COLUMN `partage_nature` ENUM('cours', 'fiche') NULL AFTER `partage_de`;

UPDATE `cours`
   SET `partage_nature` = IF(COALESCE(`contenu`, '') = '' AND COALESCE(`fiche_revision`, '') <> '', 'fiche', 'cours')
 WHERE `partage_de` IS NOT NULL;

ALTER TABLE `dossiers`
  ADD COLUMN `partage_de` INT UNSIGNED NULL AFTER `parent_id`,
  ADD KEY `idx_dossiers_partage_de` (`partage_de`);
