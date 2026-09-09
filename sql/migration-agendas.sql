-- Un agenda distant, quel qu'il soit — Outlook, Google, un autre demain.
--
-- Les tables ne portaient qu'un fournisseur dans leur nom. Ajouter Google
-- aurait voulu dire quatre tables de plus et deux mille cinq cents lignes en
-- double, avec la certitude qu'une correction finirait par n'être faite que
-- d'un côté. Elles deviennent communes, et une colonne dit de qui vient la
-- ligne.
--
-- Rien n'est perdu : les tables sont renommées, pas recréées, et tout ce
-- qu'elles contenaient est marqué « microsoft ».

RENAME TABLE `outlook_comptes`     TO `agenda_comptes`,
             `outlook_calendriers` TO `agenda_calendriers`,
             `outlook_liens`       TO `agenda_liens`,
             `outlook_envois`      TO `agenda_envois`;

-- Le fournisseur, partout. « microsoft » par défaut : c'est ce qui existait.
ALTER TABLE `agenda_comptes`
  ADD COLUMN `fournisseur` VARCHAR(20) NOT NULL DEFAULT 'microsoft' AFTER `user_id`;
ALTER TABLE `agenda_calendriers`
  ADD COLUMN `fournisseur` VARCHAR(20) NOT NULL DEFAULT 'microsoft' AFTER `user_id`;
ALTER TABLE `agenda_liens`
  ADD COLUMN `fournisseur` VARCHAR(20) NOT NULL DEFAULT 'microsoft' AFTER `user_id`;
ALTER TABLE `agenda_envois`
  ADD COLUMN `fournisseur` VARCHAR(20) NOT NULL DEFAULT 'microsoft' AFTER `user_id`;

-- Un compte par fournisseur, et non plus un seul : c'est tout l'objet du
-- changement — pouvoir relier Google en plus d'Outlook, ou à la place.
--
-- L'index sur user_id doit exister avant qu'on ne retire la clé primaire :
-- c'est elle qui servait d'appui à la clé étrangère vers les comptes.
ALTER TABLE `agenda_comptes` ADD KEY `idx_compte_user` (`user_id`);
ALTER TABLE `agenda_comptes` DROP PRIMARY KEY;
ALTER TABLE `agenda_comptes` ADD PRIMARY KEY (`user_id`, `fournisseur`);

-- Les clés d'unicité tiennent compte du fournisseur : deux agendas différents
-- peuvent employer le même identifiant sans se confondre.
--
-- Chaque fois, l'index d'appui de la clé étrangère est posé d'abord :
-- MySQL refuse de retirer celui dont une contrainte se sert.
ALTER TABLE `agenda_calendriers` ADD KEY `idx_cal_user2` (`user_id`);
ALTER TABLE `agenda_calendriers` DROP INDEX `uniq_calendrier`;
ALTER TABLE `agenda_calendriers`
  ADD UNIQUE KEY `uniq_calendrier` (`user_id`, `fournisseur`, `empreinte`);

ALTER TABLE `agenda_liens` ADD KEY `idx_lien_user2` (`user_id`);
ALTER TABLE `agenda_liens` DROP INDEX `uniq_outlook_id`;
ALTER TABLE `agenda_liens`
  ADD UNIQUE KEY `uniq_lien_distant` (`user_id`, `fournisseur`, `outlook_id`);

ALTER TABLE `agenda_envois` ADD KEY `idx_envoi_user2` (`user_id`);
ALTER TABLE `agenda_envois` DROP INDEX `uniq_envoi`;
ALTER TABLE `agenda_envois`
  ADD UNIQUE KEY `uniq_envoi` (`user_id`, `fournisseur`, `sorte`, `source_id`);

-- Google exige le calendrier pour modifier ou supprimer un évènement ;
-- Microsoft s'en passe. On le retient des deux côtés, c'est le seul moyen
-- d'écrire le même code pour les deux.
ALTER TABLE `agenda_envois`
  ADD COLUMN `calendrier_id` TEXT NULL AFTER `outlook_id`;

-- « outlook_id » nommait le fournisseur autant que la chose. Ce sont des
-- identifiants distants, quel que soit celui qui les a émis.
ALTER TABLE `agenda_liens`  CHANGE `outlook_id` `distant_id` VARCHAR(255) NOT NULL;
ALTER TABLE `agenda_envois` CHANGE `outlook_id` `distant_id` VARCHAR(512) NOT NULL;
