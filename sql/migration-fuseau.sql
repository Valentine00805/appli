-- Le fuseau horaire de chacun.
--
-- L'application raisonnait en heure de Paris pour tout le monde. Tant qu'elle
-- vivait sur un poste, cela ne se voyait pas ; ouverte à tous, elle décalerait
-- en silence les rendez-vous de qui vit ailleurs — et une donnée fausse qui ne
-- se signale pas est le pire des défauts.
--
-- Les dates restent écrites en heure locale, sans décalage : chacun ne lit que
-- les siennes, et elles n'ont de sens que dans son fuseau. Changer de fuseau
-- réinterprète donc ce qui est déjà là, ce que l'écran annonce avant de le faire.
ALTER TABLE `users`
  ADD COLUMN `fuseau` VARCHAR(64) NOT NULL DEFAULT 'Europe/Paris' AFTER `nom`;
