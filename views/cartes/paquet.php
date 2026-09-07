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

<p><a href="<?= url('cartes') ?>">← Cartes</a></p>

<div class="entete-page">
  <div>
    <h1><?= e($cours['titre']) ?></h1>
    <p class="discret">
      <?php if ($cartes === []): ?>
        Aucune carte pour l'instant.
      <?php else: ?>
        <?= count($cartes) ?> carte<?= count($cartes) > 1 ? 's' : '' ?>
        <?php if ($dues > 0): ?>· <?= $dues ?> à revoir aujourd'hui<?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cartes') ?>">Fabriquer des cartes</a>
    <?php if ($dues > 0): ?>
      <a class="bouton" href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>">Réviser</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>">Voir le cours</a>
  </div>
</div>

<section class="carte">
  <h2>Le paquet</h2>
  <?= Vue::rendre('cartes/_paquet', ['cours' => $cours, 'cartes' => $cartes]) ?>
</section>
