<?php
/** @var string $recherche @var array $cours, $evenements, $notes, $semaines, $taches, $termes */
?>

<div class="entete-page">
  <div>
    <h1>Recherche</h1>
    <?php if ($recherche !== ''): ?>
      <?php $total = count($cours) + count($evenements) + count($notes) + count($semaines) + count($taches); ?>
      <p><?= $total ?> résultat<?= $total > 1 ? 's' : '' ?> pour « <?= e($recherche) ?> » :
         <?= count($cours) ?> cours, <?= count($evenements) ?> évènement<?= count($evenements) > 1 ? 's' : '' ?>,
         <?= count($notes) ?> note<?= count($notes) > 1 ? 's' : '' ?>,
         <?= count($semaines) ?> semaine<?= count($semaines) > 1 ? 's' : '' ?> de journal,
         <?= count($taches) ?> tâche<?= count($taches) > 1 ? 's' : '' ?></p>
    <?php endif; ?>
  </div>
</div>

<form class="filtres" method="get" action="<?= url('recherche') ?>">
  <div class="champ" style="flex:1;min-width:240px">
    <label for="q">Mots-clés</label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>" autofocus
           placeholder="chapitre, tâche, mission, note…">
  </div>
  <button class="bouton" type="submit">Rechercher</button>
</form>

<?php if ($recherche === ''): ?>
  <div class="vide">
    <span class="vide__icone">🔍</span>
    <p>Saisissez un ou plusieurs mots pour chercher dans vos cours, votre calendrier,
      vos notes d’alternance, votre journal et vos tâches.</p>
  </div>
<?php else: ?>

  <h2>Cours</h2>
  <?php if ($cours === []): ?>
    <p class="discret">Aucun cours trouvé.</p>
  <?php else: ?>
    <div class="grille grille--3" style="margin-bottom:2rem">
      <?php foreach ($cours as $c): ?>
        <a class="carte cours-carte" href="<?= url('cours/' . $c['id']) ?>" data-fenetre>
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

  <?php if ($notes !== []): ?>
    <h2>Notes d’alternance</h2>
    <div class="grille grille--3" style="margin-bottom:2rem">
      <?php foreach ($notes as $n): ?>
        <a class="carte cours-carte" href="<?= url('alternance/notes/' . (int) $n['id']) ?>">
          <div class="cours-carte__titre">🗒️ <?= surligner(e((string) $n['titre']), $termes) ?></div>
          <p class="cours-carte__extrait"><?= surligner(e(extrait(TexteRiche::versTexte($n['contenu']), 220)), $termes) ?></p>
          <div class="cours-carte__bas">Modifiée le <?= e(date_fr((string) $n['updated_at'], false)) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($semaines !== []): ?>
    <h2>Journal des missions</h2>
    <div class="pile" style="margin-bottom:2rem">
      <?php foreach ($semaines as $s): ?>
        <a class="evt-ligne" href="<?= url('alternance/journal/semaine', ['semaine' => $s['semaine']]) ?>">
          <span class="evt-ligne__barre" style="background:var(--entreprise)"></span>
          <span style="min-width:0">
            <span class="evt-ligne__titre">📓 Semaine du <?= e(Alternance::jourCourt((string) $s['semaine'])) ?></span><br>
            <span class="evt-ligne__meta">
              <?= surligner(e(extrait(TexteRiche::versTexte($s['missions']), 160)), $termes) ?>
              <?php if ((string) ($s['competences'] ?? '') !== ''): ?>
                · <?= surligner(e((string) $s['competences']), $termes) ?>
              <?php endif; ?>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($taches !== []): ?>
    <h2>Tâches</h2>
    <div class="pile" style="margin-bottom:2rem">
      <?php foreach ($taches as $t): ?>
        <a class="evt-ligne" href="<?= url('taches', ['liste' => (int) $t['liste_id']]) ?>">
          <span class="evt-ligne__barre" style="background:var(--accent)"></span>
          <span style="min-width:0">
            <span class="evt-ligne__titre">
              <?= (int) $t['faite'] === 1 ? '☑' : '☐' ?> <?= surligner(e((string) $t['titre']), $termes) ?>
            </span><br>
            <span class="evt-ligne__meta"><?= e(trim((string) $t['liste_icone'] . ' ' . (string) $t['liste_nom'])) ?></span>
          </span>
          <?php if ($t['echeance'] !== null): ?>
            <span class="evt-ligne__droite">
              <span class="echeance echeance--<?= e(echeance_etat((string) $t['echeance'], (int) $t['faite'] === 1)) ?>">
                <?= e(echeance_libelle((string) $t['echeance'], (int) $t['faite'] === 1)) ?>
              </span>
            </span>
          <?php endif; ?>
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
