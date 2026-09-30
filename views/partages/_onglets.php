<?php
/**
 * Les deux sens du partage : ce qu'on m'a partagé, ce que je partage.
 *
 * @var string $vue      recus ou envoyes
 * @var int $nbRecus
 * @var int $nbEnvoyes
 */
$liens = [
    'recus' => ['libelle' => t('pt.recus_titre'), 'icone' => '📥', 'url' => url('partages'), 'nb' => $nbRecus],
    'envoyes' => ['libelle' => t('pt.envoyes_titre'), 'icone' => '📤', 'url' => url('partages/envoyes'), 'nb' => $nbEnvoyes],
];
?>
<nav class="onglets" aria-label="<?= e(t('pt.sens_du_partage')) ?>">
  <?php foreach ($liens as $cle => $lien): ?>
    <a href="<?= e((string) $lien['url']) ?>"<?= $vue === $cle ? ' aria-current="page"' : '' ?>>
      <span aria-hidden="true"><?= e($lien['icone']) ?></span> <?= e($lien['libelle']) ?>
      <?php if ((int) $lien['nb'] > 0): ?><span class="onglets__compteur"><?= (int) $lien['nb'] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
