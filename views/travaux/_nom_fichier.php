<?php
/**
 * Le nom d'un fichier du groupe, en lien : l'aperçu (dans une fenêtre) quand le type s'y prête, sinon le fichier lui-même, dans un onglet.
 *
 * @var array $f  le fichier (id, nom_origine, mime…)
 * @var bool $dansUneFenetre
 */
$nom = (string) $f['nom_origine'];
$apercu = ApercuDocument::possible($nom) || Fichiers::estMedia((string) $f['mime'], $nom);
?>
<?php if ($apercu): ?>
  <a class="fichier__nom" href="<?= url('travaux/fichiers/' . (int) $f['id'] . '/apercu') ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= e($nom) ?></a>
<?php else: ?>
  <a class="fichier__nom" href="<?= url('travaux/fichiers/' . (int) $f['id']) ?>" target="_blank" rel="noopener"><?= e($nom) ?></a>
<?php endif; ?>