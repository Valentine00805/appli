<?php
/**
 * Sous-navigation de la section Budget.
 * @var string $onglet  operations | previsions | categories
 */
?>
<nav class="onglets" aria-label="Sections du budget">
  <a href="<?= url('budget') ?>"<?= $onglet === 'operations' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">💶</span> Opérations
  </a>
  <a href="<?= url('budget/previsions') ?>"<?= $onglet === 'previsions' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📈</span> Prévisions
  </a>
  <a href="<?= url('budget/categories') ?>"<?= $onglet === 'categories' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🗂️</span> Catégories
  </a>
</nav>
