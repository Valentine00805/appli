<?php
/**
 * L'en-tête d'un travail de groupe et ses onglets.
 *
 * @var array $projet
 * @var string $onglet  taches | echeances | fichiers | document | membres
 * @var bool $dansUneFenetre  ouvert depuis la liste : les onglets restent dans la fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$onglets = [
    'taches'    => ['', '✅', 'Qui fait quoi'],
    'echeances' => ['/echeances', '📅', 'Échéances'],
    'fichiers'  => ['/fichiers', '📎', 'Fichiers'],
    'document'  => ['/document', '📝', 'Document commun'],
    'membres'   => ['/membres', '👥', 'Membres'],
];
?>
<?php // En fenêtre, toute la place : trois colonnes de tâches y tiennent. ?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux') ?>">← Tous les travaux de groupe</a></p>
    <?php endif; ?>
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
    <a href="<?= url('travaux/' . (int) $projet['id'] . $chemin) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?><?= $dansUneFenetre ? ' data-fenetre' : '' ?>>
      <span aria-hidden="true"><?= $icone ?></span> <?= e($nom) ?>
    </a>
  <?php endforeach; ?>
</nav>
