-- « Mes évènements » n'appartient à aucun agenda.
--
-- Son affichage et sa couleur vivaient sur la ligne du compte Outlook, faute
-- d'ailleurs où les mettre. Dès qu'un second agenda existe, la question devient
-- absurde : ce ne sont pas les évènements d'Outlook ni ceux de Google, ce sont
-- les siens. Ils rejoignent donc la personne.
ALTER TABLE `users`
  ADD COLUMN `afficher_miens` TINYINT(1) NOT NULL DEFAULT 1 AFTER `fuseau`,
  ADD COLUMN `couleur_miens`  VARCHAR(7) NULL AFTER `afficher_miens`;

-- Ce qui avait été choisi côté Outlook est repris, plutôt que perdu.
UPDATE `users` u
  JOIN `agenda_comptes` c ON c.user_id = u.id AND c.fournisseur = 'microsoft'
   SET u.afficher_miens = c.afficher_miens,
       u.couleur_miens  = c.couleur_miens;

ALTER TABLE `agenda_comptes`
  DROP COLUMN `afficher_miens`,
  DROP COLUMN `couleur_miens`;
