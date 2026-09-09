-- L'agenda où part chaque évènement, choisi au moment de l'écrire.
--
-- Jusqu'ici tout ce qui naissait dans l'application partait au même endroit :
-- « Mes Cours », chez chaque fournisseur relié. Un réglage unique convenait
-- tant qu'il n'y avait qu'une destination possible ; il ne convient plus dès
-- qu'on veut mettre un rendez-vous chez soi et le suivant dans l'agenda que la
-- famille regarde.
--
-- Vide, la colonne veut dire « Mes évènements » : le comportement d'avant,
-- inchangé pour tout ce qui existe déjà. Sinon, elle porte l'empreinte d'un
-- calendrier — le même identifiant que partout ailleurs, md5 de l'identifiant
-- distant — et l'évènement ne part que là, chez ce fournisseur-là.
ALTER TABLE evenements
    ADD COLUMN agenda_cible CHAR(32) DEFAULT NULL AFTER journee_entiere;

-- Le dépôt de copies figées disparaît : écrire dans un agenda qu'on a le droit
-- de modifier vaut mieux qu'y déposer quelque chose qu'on ne pourra jamais
-- corriger. La table n'a jamais servi.
DROP TABLE IF EXISTS agenda_depots;
