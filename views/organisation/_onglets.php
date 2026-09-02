<?php
/**
 * Sous-navigation de la section Organisation.
 * @var string $onglet  matieres, types, dossiers ou tags
 */
$liens = [
    'matieres' => ['libelle' => 'Matières', 'icone' => '🎨'],
    'types'    => ['libelle' => "Types d'évènement", 'icone' => '🏷️'],
    'dossiers' => ['libelle' => 'Dossiers', 'icone' => '📁'],
    'tags'     => ['libelle' => 'Tags', 'icone' => '#'],
];
?>
<nav class="onglets" aria-label="Sections de l'organisation">
  <?php foreach ($liens as $cle => $lien): ?>
    <a href="<?= url('organisation/' . $cle) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>>
      <span aria-hidden="true"><?= e($lien['icone']) ?></span> <?= e($lien['libelle']) ?>
    </a>
  <?php endforeach; ?>
</nav>
