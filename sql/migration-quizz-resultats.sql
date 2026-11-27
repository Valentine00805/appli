-- Où en est-on d'un quizz (page web .html d'une fiche de révision) : le quizz le signale lui-même, et l'application garde le dernier état connu.
--
--   quizz_total   les questions de la série en cours (0 : le quizz n'a encore rien signalé)
--   quizz_fait    les questions déjà répondues
--   quizz_bonnes  les réponses justes
--
-- Deux pourcentages en découlent : l'avancement (fait / total) et le score (bonnes / total).
-- Rien n'est supprimé : les fichiers existants gardent 0 partout.
--
-- Tant que cette migration n'est pas importée, le site tourne : le quizz se joue, sans pourcentages.

ALTER TABLE `fichiers`
  ADD COLUMN `quizz_total`  INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN `quizz_fait`   INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN `quizz_bonnes` INT UNSIGNED NOT NULL DEFAULT 0;
