<?php
/**
 * Sur un document du projet (cours, dossier, fichier) : les évènements du projet auquel il est lié, avec leur date. Rien quand il n'est
 * lié à aucun.
 *
 * @var list<array{titre: string, debut: string, url: string}> $evenements  voir LiensEvenements::evenementsDe
 * @var string $ouvre  l'attribut qui ouvre les liens dans la fenêtre, ou rien
 */
if ($evenements === []) {
    return;
}
?>
<span class="evenements-lies">
  <span class="discret">📅 <?= e(t('cam.lie_a')) ?></span>
  <?php foreach ($evenements as $evt): ?>
    <a class="pastille evenements-lies__evt" href="<?= e($evt['url']) ?>"<?= $ouvre ?>>
      <?= e($evt['titre']) ?> · <?= e(date(t('date.jour_mois'), strtotime($evt['debut']))) ?>
    </a>
  <?php endforeach; ?>
</span>
