-- Le pseudo : le nom sous lequel l'application vous appelle.
--
-- Choisi à l'inscription, modifiable ensuite dans « Mon compte ». Unique, sans
-- tenir compte des majuscules ni des accents (« Valou » et « valóu » sont le
-- même) : la collation de la table s'en charge. NULL pour un compte créé avant.
ALTER TABLE users
    ADD COLUMN pseudo VARCHAR(30) NULL AFTER nom,
    ADD UNIQUE KEY uniq_users_pseudo (pseudo);
