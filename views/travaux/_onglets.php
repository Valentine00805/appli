<?php
/**
 * L'en-tête d'un travail de groupe et ses onglets.
 *
 * @var array $projet
 * @var string $onglet  taches | echeances | fichiers | document | membres
 */
$onglets = [
    'taches'    => ['', '✅', 'Qui fait quoi'],
    'echeances' => ['/echeances', '📅', 'Échéances'],
    'fichiers'  => ['/fichiers', '📎', 'Fichiers'],
    'document'  => ['/document', '📝', 'Document commun'],
    'membres'   => ['/membres', '👥', 'Membres'],
];
?>
<div class="entete-page">
  <div>
    <p style="margin:0 0 .3rem"><a href="<?= url('travaux') ?>">← Tous les travaux de groupe</a></p>
    <h1>👥 <?= e((string) $projet['nom']) ?></h1>
    <?php if ((string) ($projet['description'] ?? '') !== ''): ?>
      <p><?= nl2br(e((string) $projet['description'])) ?></p>
    <?php endif; ?>
  </div>
  <?php if ($projet['conversation_id'] !== null): ?>
    <a class="bouton bouton--secondaire" href="<?= url('groupes/' . (int) $projet['conversation_id']) ?>">💬 Discussion du groupe</a>
  <?php endif; ?>
</div>

<nav class="onglets" aria-label="Sections du travail de groupe">
  <?php foreach ($onglets as $cle => [$chemin, $icone, $nom]): ?>
    <a href="<?= url('travaux/' . (int) $projet['id'] . $chemin) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?>>
      <span aria-hidden="true"><?= $icone ?></span> <?= e($nom) ?>
    </a>
  <?php endforeach; ?>
</nav>
