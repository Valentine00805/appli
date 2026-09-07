<?php
/**
 * Les paquets de cartes, un par cours, et ce qui est dû aujourd'hui.
 *
 * @var array $paquets  un cours par ligne, avec ses compteurs
 * @var int $aRevoir    cartes dues, tous cours confondus
 * @var int $total      cartes existantes
 * @var array $cours     tous les cours, pour choisir où puiser
 */
?>

<div class="entete-page">
  <div>
    <h1>🃏 Cartes</h1>
    <p>
      <?php if ($total === 0): ?>
        Des questions courtes, revues au bon moment.
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

<section class="carte fabrique">
  <h2>Fabriquer des cartes</h2>
  <?php if ($cours === []): ?>
    <p class="discret">Vous n'avez pas encore de cours.
      <a href="<?= url('cours/nouveau') ?>">En créer un</a>.</p>
  <?php else: ?>
    <p class="champ__aide">
      Choisissez un cours et ce que l'application doit relire. Elle propose une
      carte partout où elle reconnaît un terme suivi de sa définition, une
      question de devoir, ou une phrase dont un élément mérite d'être caché.
      Rien n'est enregistré : vous validez ensuite ce que vous gardez.
    </p>
    <form method="post" action="<?= url('cartes/proposer') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="cours">Cours</label>
        <select id="cours" name="cours" required>
          <?php $matiere = false; ?>
          <?php foreach ($cours as $c): ?>
            <?php if ($c['matiere_nom'] !== $matiere): ?>
              <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
              <?php $matiere = $c['matiere_nom']; ?>
              <optgroup label="<?= e($matiere ?? 'Sans matière') ?>">
            <?php endif; ?>
            <option value="<?= (int) $c['id'] ?>">
              <?= e($c['titre']) ?>
              <?php if (!$c['a_fiche']): ?> — sans fiche<?php endif; ?>
            </option>
          <?php endforeach; ?>
          <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
        </select>
      </div>

      <?= Vue::rendre('cartes/_sources', ['cours' => null]) ?>

      <button class="bouton" type="submit">Proposer des cartes</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($paquets === []): ?>
  <div class="vide">
    <span class="vide__icone">🃏</span>
    <p>Aucune carte pour l'instant. Choisissez un cours ci-dessus et laissez
       l'application vous en proposer.</p>
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
