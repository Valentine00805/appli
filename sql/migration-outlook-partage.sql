-- L'inscription chez Microsoft n'appartient plus à chaque personne mais à
-- l'installation : une seule application est déclarée, et chacun y relie son
-- compte. Les deux colonnes qui portaient l'inscription n'ont donc plus lieu
-- d'être ici — elles vivent désormais dans la configuration.
ALTER TABLE `outlook_comptes` DROP COLUMN `client_id`;
ALTER TABLE `outlook_comptes` DROP COLUMN `locataire`;
