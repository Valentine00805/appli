<?php
/** @var string $recherche @var array $cours, $evenements, $termes */
?>

<div class="entete-page">
  <div>
    <h1>Recherche</h1>
    <?php if ($recherche !== ''): ?>
      <p><?= count($cours) ?> cours et <?= count($evenements) ?> évènement<?= count($evenements) > 1 ? 's' : '' ?>
         pour « <?= e($recherche) ?> »</p>
    <?php endif; ?>
  </div>
</div>

<form class="filtres" method="get" action="<?= url('recherche') ?>">
  <div class="champ" style="flex:1;min-width:240px">
    <label for="q">Mots-clés</label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>" autofocus
           placeholder="chapitre, théorème, révision…">
  </div>
  <button class="bouton" type="submit">Rechercher</button>
</form>

<?php if ($recherche === ''): ?>
  <div class="vide">
    <span class="vide__icone">🔍</span>
    <p>Saisissez un ou plusieurs mots pour chercher dans vos cours et votre calendrier.</p>
  </div>
<?php else: ?>

  <h2>Cours</h2>
  <?php if ($cours === []): ?>
    <p class="discret">Aucun cours trouvé.</p>
  <?php else: ?>
    <div class="grille grille--3" style="margin-bottom:2rem">
      <?php foreach ($cours as $c): ?>
        <a class="carte cours-carte" href="<?= url('cours/' . $c['id']) ?>">
          <?php if ($c['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($c['matiere_couleur']) ?>;color:<?= e(couleur_texte($c['matiere_couleur'])) ?>">
              <?= e($c['matiere_nom']) ?>
            </span>
          <?php endif; ?>
          <div class="cours-carte__titre"><?= surligner(e($c['titre']), $termes) ?></div>
          <p class="cours-carte__extrait"><?= surligner(e(extrait($c['contenu'], 220)), $termes) ?></p>
          <div class="cours-carte__bas">Modifié le <?= e(date_fr($c['updated_at'], false)) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2>Calendrier</h2>
  <?php if ($evenements === []): ?>
    <p class="discret">Aucun évènement trouvé.</p>
  <?php else: ?>
    <div class="pile">
      <?php foreach ($evenements as $evt): ?>
        <a class="evt-ligne" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>">
          <span class="evt-ligne__barre" style="background:<?= e(couleur_evenement($evt)) ?>"></span>
          <span>
            <span class="evt-ligne__titre"><?= e(icone_evenement($evt)) ?> <?= surligner(e($evt['titre']), $termes) ?></span><br>
            <span class="evt-ligne__meta">
              <?= e(date_fr($evt['debut'])) ?><?= $evt['lieu'] ? ' · ' . e($evt['lieu']) : '' ?>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>
