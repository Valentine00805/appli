-- Les envois d'avant la colonne « calendrier_id ».
--
-- Ils sont partis dans le calendrier d'envoi du compte — c'était le seul
-- possible à l'époque — mais ne le disent pas, et l'empreinte tirée d'un
-- calendrier vide ne concorde avec rien. Le rapprochement les prendrait pour
-- des envois vers un calendrier disparu : il les effacerait là-bas pour les y
-- recréer aussitôt, sous un nouvel identifiant. Rien ne serait perdu, mais
-- sept rendez-vous s'en iraient et reviendraient sans raison.
UPDATE agenda_envois e
  JOIN agenda_comptes c ON c.user_id = e.user_id AND c.fournisseur = e.fournisseur
   SET e.calendrier_id = c.calendrier_envoi_id,
       e.calendrier_empreinte = MD5(c.calendrier_envoi_id)
 WHERE e.calendrier_id IS NULL
   AND c.calendrier_envoi_id IS NOT NULL;
