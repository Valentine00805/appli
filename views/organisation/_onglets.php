<?php
/**
 * Sous-navigation de la section Organisation.
 * @var string $onglet  matieres, types, dossiers ou tags
 */
$liens = [
    'matieres' => ['libelle' => t('orga.matieres'), 'icone' => '🎨'],
    'types'    => ['libelle' => t('orga.types'), 'icone' => '🏷️'],
    'dossiers' => ['libelle' => t('orga.dossiers'), 'icone' => '📁'],
    'tags'     => ['libelle' => t('orga.tags'), 'icone' => '#'],
];
?>
<nav class="onglets" aria-label="<?= e(t('orga.sections')) ?>">
  <?php foreach ($liens as $cle => $lien): ?>
    <a href="<?= url('organisation/' . $cle) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>>
      <span aria-hidden="true"><?= e($lien['icone']) ?></span> <?= e($lien['libelle']) ?>
    </a>
  <?php endforeach; ?>
</nav>
