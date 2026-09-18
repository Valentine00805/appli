<?php
/**
 * La fenêtre des commentaires d'un document : le fil seul, sans la page du
 * document ni les réglages du partage.
 *
 * @var string $type
 * @var string $mot
 * @var array $cible
 * @var list<array> $commentaires
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$icone = match ($type) {
    'cours' => '📘',
    'fiche' => '📝',
    'dossier' => (string) $cible['icone'],
    'evenement' => '📅',
    default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
};
$nb = 0;
foreach ($commentaires as $c) {
    $nb += 1 + count($c['reponses']);
}
?>
<div class="entete-page">
  <div>
    <h1 style="margin:0">💬 Commentaires <span class="discret">(<?= $nb ?>)</span></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e($icone) ?> <?= e((string) $cible['titre']) ?></p>
  </div>
</div>

<section class="carte">
  <?= Vue::rendre('partages/_fil', [
      'commentaires' => $commentaires,
      'cible' => $cible,
      'base' => 'partages/' . $mot . '/' . (int) $cible['id'],
      'surPlace' => $dansUneFenetre ? ' data-envoi-fenetre' : '',
      'depuis' => 'fil',
  ]) ?>
</section>
