<?php
/**
 * Sous-navigation de l'espace alternance, et où l'on en est aujourd'hui.
 *
 * @var string $onglet  notes | rythme | journal | documents
 * @var array|null $situation  ce que rend Alternance::situation()
 */
$lieu = static fn (array $p): string => Alternance::LIEUX[$p['lieu']]['icone'] . ' ' . Alternance::LIEUX[$p['lieu']]['dans'];
?>
<nav class="onglets" aria-label="Sections de l’alternance">
  <a href="<?= url('alternance/entreprise') ?>"<?= $onglet === 'entreprise' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🏢</span> Mon alternance
  </a>
  <a href="<?= url('alternance') ?>"<?= $onglet === 'notes' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🗒️</span> Notes
  </a>
  <a href="<?= url('alternance/rythme') ?>"<?= $onglet === 'rythme' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🔁</span> Rythme
  </a>
  <a href="<?= url('alternance/journal') ?>"<?= $onglet === 'journal' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📓</span> Journal des missions
  </a>
  <a href="<?= url('alternance/documents') ?>"<?= $onglet === 'documents' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📁</span> Documents
  </a>
</nav>

<?php if ($situation !== null): ?>
  <?php $maintenant = $situation['maintenant']; $ensuite = $situation['ensuite']; ?>
  <p class="alternance-situation<?= $maintenant !== null ? ' alternance-situation--' . e($maintenant['lieu']) : '' ?>">
    <?php if ($maintenant !== null): ?>
      Aujourd’hui : <strong><?= e($lieu($maintenant)) ?></strong>
      <?= $maintenant['fin'] === date('Y-m-d') ? '(dernier jour)' : 'jusqu’au ' . e(Alternance::jourCourt($maintenant['fin'])) ?>
      <?php if ($maintenant['note']): ?><span class="discret">· <?= e($maintenant['note']) ?></span><?php endif; ?>
    <?php endif; ?>
    <?php if ($ensuite !== null): ?>
      <span class="alternance-situation__ensuite">
        <?= $maintenant !== null ? 'Puis' : 'Prochaine période :' ?>
        <?= e($lieu($ensuite)) ?> à partir du <?= e(rtrim(Alternance::jourCourt($ensuite['debut']), '.')) ?>.
      </span>
    <?php endif; ?>
  </p>
<?php endif; ?>
