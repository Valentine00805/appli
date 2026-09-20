-- Un lien d'abonnement au rythme d'alternance : Outlook, Google et les autres
-- agendas le relisent tout seuls, et suivent les périodes qu'on change.
-- Le jeton se renouvelle d'un clic, ce qui coupe l'ancien lien.

ALTER TABLE `alternance_contrat`
  ADD COLUMN `jeton_ics` CHAR(32) NULL AFTER `soutenance`,
  ADD UNIQUE KEY `uniq_alternance_contrat_jeton` (`jeton_ics`);
