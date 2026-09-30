<?php
/**
 * Le paquet d'un cours, sur sa propre page.
 *
 * L'onglet Cartes déplie le même contenu sans changer de page ; cette page
 * reste pour qui arrive d'un lien — depuis une fiche de révision, notamment.
 *
 * @var array $cours
 * @var array $cartes
 */
$dues = 0;
foreach ($cartes as $c) {
    if ($c['revoir_le'] <= date('Y-m-d')) {
        $dues++;
    }
}
?>

<p><a href="<?= url('cartes') ?>"><?= e(t('crt.retour_cartes')) ?></a></p>

<div class="entete-page">
  <div>
    <h1><?= e($cours['titre']) ?></h1>
    <p class="discret">
      <?php if ($cartes === []): ?>
        <?= e(t('crt.aucune')) ?>
      <?php else: ?>
        <?= e(tn('crt.nb', count($cartes))) ?>
        <?php if ($dues > 0): ?><?= e(t('crt.dues_aujourdhui', ['n' => $dues])) ?><?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cartes') ?>"><?= e(t('crt.fabriquer')) ?></a>
    <?php if ($dues > 0): ?>
      <a class="bouton" href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>" data-fenetre><?= e(t('crt.reviser')) ?></a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>"><?= e(t('crt.voir_cours')) ?></a>
  </div>
</div>

<section class="carte">
  <h2><?= e(t('crt.le_paquet')) ?></h2>
  <?= Vue::rendre('cartes/_paquet', ['cours' => $cours, 'cartes' => $cartes]) ?>
</section>
