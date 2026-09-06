<?php
/**
 * Les paquets de cartes, un par cours, et ce qui est dû aujourd'hui.
 *
 * @var array $paquets  un cours par ligne, avec ses compteurs
 * @var int $aRevoir    cartes dues, tous cours confondus
 * @var int $total      cartes existantes
 */
?>

<div class="entete-page">
  <div>
    <h1>🃏 Cartes</h1>
    <p>
      <?php if ($total === 0): ?>
        Des questions courtes, revues au bon moment. Elles se fabriquent depuis un cours.
      <?php elseif ($aRevoir === 0): ?>
        Rien à revoir aujourd'hui. <?= $total ?> carte<?= $total > 1 ? 's' : '' ?> en tout.
      <?php else: ?>
        <?= $aRevoir ?> carte<?= $aRevoir > 1 ? 's' : '' ?> à revoir aujourd'hui,
        sur <?= $total ?>.
      <?php endif; ?>
    </p>
  </div>

  <?php if ($aRevoir > 0): ?>
    <a class="bouton" href="<?= url('cartes/seance') ?>">Réviser <?= $aRevoir ?> carte<?= $aRevoir > 1 ? 's' : '' ?></a>
  <?php endif; ?>
</div>

<?php if ($paquets === []): ?>
  <div class="vide">
    <span class="vide__icone">🃏</span>
    <p>Aucune carte pour l'instant. Ouvrez un cours, puis <strong>🃏 Cartes</strong> :
       l'application vous en proposera à partir de son texte, de sa fiche et de ses documents.</p>
    <a class="bouton" href="<?= url('cours') ?>">Voir mes cours</a>
  </div>
<?php else: ?>
  <div class="grille grille--fiches">
    <?php foreach ($paquets as $p): ?>
      <?php
      $dues = (int) $p['a_revoir'];
      $sues = (int) $p['sues'];
      $nb = (int) $p['total'];
      ?>
      <a class="carte fiche-carte" href="<?= url('cours/' . $p['id'] . '/cartes') ?>">
        <p class="fiche-carte__entete">
          <?php if ($p['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($p['matiere_couleur']) ?>;color:<?= e(couleur_texte($p['matiere_couleur'])) ?>">
              <?= e($p['matiere_nom']) ?>
            </span>
          <?php else: ?>
            <span class="discret">Sans matière</span>
          <?php endif; ?>
          <?php if ($dues > 0): ?>
            <span class="carte-du"><?= $dues ?> à revoir</span>
          <?php endif; ?>
        </p>

        <h3 class="fiche-carte__titre"><?= e($p['titre']) ?></h3>

        <p class="fiche-carte__compteurs">
          <?= $nb ?> carte<?= $nb > 1 ? 's' : '' ?>
          <?php if ($sues > 0): ?>· <?= $sues ?> sue<?= $sues > 1 ? 's' : '' ?><?php endif; ?>
        </p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
