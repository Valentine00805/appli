-- Un fichier déposé sans en faire un cours : il paraît dans son dossier sous son nom, avec son extension (rapport.pdf), et s'ouvre
-- directement. C'est un cours à part entière en dessous (un titre, un fichier joint) : le drapeau dit seulement comment le montrer.
ALTER TABLE `cours`
  ADD COLUMN `est_fichier` TINYINT(1) NOT NULL DEFAULT 0 AFTER `favori`;
