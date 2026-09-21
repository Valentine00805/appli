<?php
/**
 * Une nouvelle échéance, ouverte en fenêtre par-dessus les échéances.
 *
 * @var array $projet
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1>📅 Nouvelle échéance</h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?= Vue::rendre('travaux/_champs_echeance', ['suffixe' => 'nouvelle', 'e' => null]) ?>
  <p class="champ__aide" style="margin-top:0">Chaque membre la retrouve dans son calendrier, avec ses rappels
    (rendu : 2 jours et la veille ; soutenance : la veille et 1 h avant ; réunion : 1 h et 15 min avant).</p>
  <p class="actions">
    <button class="bouton" type="submit">Poser dans le calendrier du groupe</button>
    <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
  </p>
</form>
