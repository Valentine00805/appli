<?php
/**
 * Sous-navigation de la section Budget.
 * @var string $onglet  operations | previsions | remboursements | personnes |
 *                      import | categories
 */
?>
<nav class="onglets" aria-label="<?= e(t('bud.sections')) ?>">
  <a href="<?= url('budget') ?>"<?= $onglet === 'operations' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">💶</span> <?= e(t('bud.onglet.operations')) ?>
  </a>
  <a href="<?= url('budget/previsions') ?>"<?= $onglet === 'previsions' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📈</span> <?= e(t('bud.onglet.previsions')) ?>
  </a>
  <a href="<?= url('budget/remboursements') ?>"<?= $onglet === 'remboursements' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🧾</span> <?= e(t('bud.onglet.remboursements')) ?>
  </a>
  <a href="<?= url('budget/personnes') ?>"<?= $onglet === 'personnes' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">👥</span> <?= e(t('bud.onglet.personnes')) ?>
  </a>
  <a href="<?= url('budget/import') ?>"<?= $onglet === 'import' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📥</span> <?= e(t('bud.onglet.import')) ?>
  </a>
  <a href="<?= url('budget/categories') ?>"<?= $onglet === 'categories' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🗂️</span> <?= e(t('bud.onglet.categories')) ?>
  </a>
</nav>
