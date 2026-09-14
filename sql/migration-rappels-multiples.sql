-- Plusieurs rappels pour un même évènement.
--
-- Les délais choisis, en minutes, séparés par des virgules et du plus lointain
-- au plus proche : « 1440,15 » prévient la veille et un quart d'heure avant.
-- Vide, pas de rappel. Quinze minutes par défaut, comme avant, y compris pour
-- les évènements venus d'Outlook ou de Google.
ALTER TABLE evenements
    ADD COLUMN rappels VARCHAR(80) NOT NULL DEFAULT '15' AFTER etape;

-- Le délai unique d'avant devient le premier de la liste.
UPDATE evenements SET rappels = IFNULL(CAST(rappel_minutes AS CHAR), '');

ALTER TABLE evenements DROP COLUMN rappel_minutes;
