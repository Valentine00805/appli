<?php
/**
 * Sous-navigation de l'espace alternance, et où l'on en est aujourd'hui.
 *
 * @var string $onglet  notes | rythme | journal | documents
 * @var array|null $situation  ce que rend Alternance::situation()
 */
$lieu = static fn (array $p): string => Alternance::LIEUX[$p['lieu']]['icone'] . ' ' . Alternance::lieuDans($p['lieu']);
?>
<nav class="onglets" aria-label="<?= e(t('alt.nav.titre')) ?>">
  <a href="<?= url('alternance/entreprise') ?>"<?= $onglet === 'entreprise' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🏢</span> <?= e(t('alt.nav.mon_alternance')) ?>
  </a>
  <a href="<?= url('alternance') ?>"<?= $onglet === 'notes' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🗒️</span> <?= e(t('alt.nav.notes')) ?>
  </a>
  <a href="<?= url('alternance/rythme') ?>"<?= $onglet === 'rythme' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🔁</span> <?= e(t('alt.nav.rythme')) ?>
  </a>
  <a href="<?= url('alternance/journal') ?>"<?= $onglet === 'journal' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📓</span> <?= e(t('alt.nav.journal')) ?>
  </a>
  <a href="<?= url('alternance/documents') ?>"<?= $onglet === 'documents' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">📁</span> <?= e(t('alt.nav.documents')) ?>
  </a>
</nav>

<?php if ($situation !== null): ?>
  <?php $maintenant = $situation['maintenant']; $ensuite = $situation['ensuite']; ?>
  <p class="alternance-situation<?= $maintenant !== null ? ' alternance-situation--' . e($maintenant['lieu']) : '' ?>">
    <?php if ($maintenant !== null): ?>
      <?= e(t('alt.situation.aujourdhui')) ?> <strong><?= e($lieu($maintenant)) ?></strong>
      <?= $maintenant['fin'] === date('Y-m-d')
          ? e(t('alt.situation.dernier_jour'))
          : e(t('alt.situation.jusquau', ['date' => Alternance::jourCourt($maintenant['fin'])])) ?>
      <?php if ($maintenant['note']): ?><span class="discret">· <?= e($maintenant['note']) ?></span><?php endif; ?>
    <?php endif; ?>
    <?php if ($ensuite !== null): ?>
      <span class="alternance-situation__ensuite">
        <?= e(t($maintenant !== null ? 'alt.situation.puis' : 'alt.situation.prochaine')) ?>
        <?= e(t('alt.situation.a_partir', [
            'lieu' => $lieu($ensuite),
            'date' => rtrim(Alternance::jourCourt($ensuite['debut']), '.'),
        ])) ?>
      </span>
    <?php endif; ?>
  </p>
<?php endif; ?>
