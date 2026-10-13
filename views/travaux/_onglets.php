<?php
/**
 * L'en-tête d'un travail de groupe et ses onglets.
 *
 * @var array $projet
 * @var string $onglet  taches | echeances | fichiers | cours | document | livrables | listes | membres
 * @var bool $dansUneFenetre  ouvert depuis la liste : les onglets restent dans la fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$onglets = [
    'taches'    => ['', '✅', t('tr.on.qui_fait_quoi')],
    'echeances' => ['/echeances', '📅', t('tr.on.echeances')],
    'fichiers'  => ['/fichiers', '📎', t('tr.on.fichiers')],
    'cours'     => ['/cours', '📘', t('tr.on.cours')],
    'document'  => ['/document', '📝', t('tr.on.document')],
    'membres'   => ['/membres', '👥', t('tr.on.membres')],
];
// « Livrables » (les documents finaux à rendre) puis « Listes » (des points à cocher), une fois les migrations passées : après « Document commun ».
$apres = 5;
if (Travaux::livrablesDisponibles()) {
    $onglets = array_slice($onglets, 0, $apres, true) + ['livrables' => ['/livrables', '📤', t('tr.on.livrables')]] + array_slice($onglets, $apres, null, true);
    $apres++;
}
if (Travaux::listesDisponibles()) {
    $onglets = array_slice($onglets, 0, $apres, true) + ['listes' => ['/listes', '☑️', t('tr.on.listes')]] + array_slice($onglets, $apres, null, true);
}
// Seulement ceux que le groupe a choisis (« Qui fait quoi » et « Membres » sont toujours là).
$onglets = array_filter($onglets, static fn (string $cle): bool => Travaux::ongletActif($projet, $cle), ARRAY_FILTER_USE_KEY);
?>
<?php // En fenêtre, toute la place : trois colonnes de tâches y tiennent. ?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux') ?>"><?= e(t('tr.on.retour')) ?></a></p>
    <?php endif; ?>
    <h1>👥 <?= e((string) $projet['nom']) ?></h1>
    <?php if ((string) ($projet['description'] ?? '') !== ''): ?>
      <p><?= nl2br(e((string) $projet['description'])) ?></p>
    <?php endif; ?>
  </div>
  <?php if ($projet['conversation_id'] !== null): ?>
    <a class="bouton bouton--secondaire" href="<?= url('groupes/' . (int) $projet['conversation_id']) ?>"><?= e(t('tr.on.discussion')) ?></a>
  <?php endif; ?>
</div>

<nav class="onglets" aria-label="<?= e(t('tr.on.sections')) ?>">
  <?php foreach ($onglets as $cle => [$chemin, $icone, $nom]): ?>
    <a href="<?= url('travaux/' . (int) $projet['id'] . $chemin) ?>"<?= $onglet === $cle ? ' aria-current="page"' : '' ?><?= $dansUneFenetre ? ' data-fenetre' : '' ?>>
      <span aria-hidden="true"><?= $icone ?></span> <?= e($nom) ?>
    </a>
  <?php endforeach; ?>
</nav>
