<?php
/**
 * Une nouvelle échéance, ouverte en fenêtre par-dessus les échéances.
 *
 * @var array $projet
 * @var list<array> $types  les types d'échéance du projet
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.ec.nouvelle_titre')) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?= Vue::rendre('travaux/_champs_echeance', ['suffixe' => 'nouvelle', 'e' => null, 'types' => $types]) ?>
  <p class="champ__aide" style="margin-top:0"><?= e(t('tr.ec.nouvelle_aide')) ?></p>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.ec.poser')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
