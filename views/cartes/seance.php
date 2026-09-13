<?php
/**
 * Une séance : les cartes dues, une par une.
 *
 * Tout tient dans la page. Le script montre une carte, révèle la réponse, prend
 * le verdict et passe à la suivante ; le serveur n'est prévenu que du verdict.
 * Sans JavaScript, les cartes s'affichent simplement à la suite, question et
 * réponse visibles : on peut au moins les relire.
 *
 * @var array $cartes
 * @var ?array $cours  le cours, si la séance ne porte que sur lui
 * @var int $duesEnTout  cartes dues en tout, avant le plafond
 * @var int $rezeroTotal  combien le paquet en compte en tout
 * @var int $paquetTotal  cartes du paquet entier ; 0 hors d'un cours
 * @var int $paquetSomme  la somme de leurs boîtes, pour l'anneau
 * @var bool $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$retour = $cours !== null ? url('cours/' . $cours['id'] . '/cartes') : url('cartes');
?>

<?php if ($dansUneFenetre): ?>
  <?php // Dans une fenêtre, la page des cartes est juste derrière : la croix y ramène. ?>
  <div data-large>
    <?php if ($cours !== null): ?><h1 class="seance__titre-fenetre"><?= e($cours['titre']) ?></h1><?php endif; ?>
  </div>
<?php else: ?>
  <p><a href="<?= e($retour) ?>">← <?= $cours !== null ? e($cours['titre']) : 'Cartes' ?></a></p>
<?php endif; ?>

<?php if ($cartes === []): ?>
  <div class="vide">
    <span class="vide__icone">✅</span>
    <p>Rien à revoir<?= $cours !== null ? ' dans ce cours' : '' ?> pour aujourd'hui.</p>
    <a class="bouton bouton--secondaire" href="<?= e($retour) ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Retour</a>
  </div>
<?php else: ?>

  <?= Vue::rendre('cartes/_seance', [
      'cartes' => $cartes, 'avecCours' => $cours === null, 'retour' => $retour,
      'duesEnTout' => $duesEnTout,
      'paquetTotal' => $paquetTotal,
      'paquetSomme' => $paquetSomme,
      'rezeroCours'    => $cours === null ? null : (int) $cours['id'],
      'rezeroTotal'    => $rezeroTotal,
      // Dans une fenêtre, la remise à zéro s'y enregistre et la séance repart.
      'rezeroRetour'   => $dansUneFenetre ? 'seance' : null,
      'dansUneFenetre' => $dansUneFenetre,
  ]) ?>

<?php endif; ?>
