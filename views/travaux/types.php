<?php
/**
 * Les types d'échéance du projet, réglés comme les types d'évènement :
 * nom, icône, couleur, rappels, et leur ordre dans le menu.
 *
 * @var array $projet
 * @var list<array> $types
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$dernier = count($types) - 1;
?>
<?php // Ouverte par-dessus le projet, la page se suffit : ni onglets, ni retour. ?>
<?php if (!$dansUneFenetre): ?>
  <?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet]) ?>
<?php endif; ?>

<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>">← Les échéances</a></p>
    <?php endif; ?>
    <h1 style="margin:0">🏷️ Types d’échéance</h1>
    <p class="discret" style="margin:.2rem 0 .4rem"><?= e((string) $projet['nom']) ?></p>
    <p>Ils classent les échéances du groupe. Chacun a son icône, sa couleur, ses rappels et sa place dans le menu —
      pour tous les membres du projet.</p>
  </div>
</div>

<p style="margin:0 0 1rem"><a class="bouton bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/types/nouveau') ?>" data-fenetre>+ Nouveau type</a></p>

<div class="pile"<?= $dansUneFenetre ? '' : ' style="max-width:48rem"' ?>>
  <?php if ($types === []): ?>
    <div class="vide">
      <span class="vide__icone">🏷️</span>
      <p>Aucun type : les échéances seront « Sans type ». Créez-en un.</p>
    </div>
  <?php endif; ?>
  <?php foreach ($types as $i => $t): ?>
    <?php $id = (int) $t['id']; $n = (int) $t['nb_echeances']; ?>
    <section class="carte">
      <div class="matiere-carte">
        <span class="matiere-pastille" style="background:<?= e((string) $t['couleur']) ?>;display:grid;place-items:center;font-size:1.2rem">
          <?= e((string) $t['icone']) ?>
        </span>
        <div style="flex:1;min-width:0">
          <h3 style="margin:0 0 .15rem"><?= e((string) $t['nom']) ?></h3>
          <p class="discret" style="margin:0">
            <?= $n ?> échéance<?= $n > 1 ? 's' : '' ?>
            <?php $dire = Rappels::dire((string) $t['rappels']); ?>
            · 🔔 <?= $dire === '' ? 'sans rappel' : e($dire) ?>
          </p>
        </div>
        <div class="actions">
          <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/deplacer') ?>" class="en-ligne">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="sens" value="haut">
            <button class="bouton bouton--discret bouton--petit" type="submit" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
          </form>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/deplacer') ?>" class="en-ligne">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="sens" value="bas">
            <button class="bouton bouton--discret bouton--petit" type="submit" title="Descendre"<?= $i === $dernier ? ' disabled' : '' ?>>↓</button>
          </form>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= url('travaux/types/' . $id . '/modifier') ?>" data-fenetre>Modifier</a>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</div>
