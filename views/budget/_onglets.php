<?php
/**
 * Sous-navigation de la section Budget.
 * @var string $onglet  operations | previsions | remboursements | import | categories
 */
?>
<nav class="onglets" aria-label="Sections du budget">
  <a href="<?= url('budget') ?>"<?= $onglet === 'operations' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">💶</span> Opérations
  </a>
  <a href="<?= url('budget/previsions') ?>"<?= $onglet === 'previsions' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📈</span> Prévisions
  </a>
  <a href="<?= url('budget/remboursements') ?>"<?= $onglet === 'remboursements' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🧾</span> Remboursements
  </a>
  <a href="<?= url('budget/import') ?>"<?= $onglet === 'import' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📥</span> Import
  </a>
  <a href="<?= url('budget/categories') ?>"<?= $onglet === 'categories' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🗂️</span> Catégories
  </a>
</nav>
